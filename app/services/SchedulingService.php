<?php
/** Collection scheduling orchestration. Author: Ong Kar Heng (2408830). */
class SchedulingService
{
    public function create(User $administrator, array $data): CollectionSchedule
    {
        UserPermissions::require('schedule.manage');
        [$date, $timeSlot, $strategyName, $cleaner, $notes] = $this->validateSchedule($data);
        $selections = SchedulingStrategyFactory::make($strategyName)->select();
        if ($selections === []) {
            throw new ValidationException(['strategy' => 'The selected strategy currently finds no bins requiring work.']);
        }

        $pdo = Database::getInstance()->pdo();
        $pdo->beginTransaction();
        try {
            $schedule = new CollectionSchedule();
            $schedule->setDetails($administrator->getKey(), $date, $timeSlot, $strategyName, $notes);
            $schedule->save();
            foreach ($selections as $selection) {
                $assignment = new CollectionAssignment();
                $assignment->setDetails(
                    $schedule->getKey(),
                    $cleaner->getKey(),
                    $selection['bin']->getKey(),
                    $selection['complaint_id'],
                    $selection['priority'],
                    $selection['reason']
                );
                $assignment->save();
            }
            $pdo->commit();
            return $schedule;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        }
    }

    public function update(int $id, array $data): CollectionSchedule
    {
        UserPermissions::require('schedule.manage');
        $schedule = $this->requireSchedule($id);
        if ($schedule->getStatus() !== 'Planned') {
            throw new ValidationException(['schedule' => 'Only planned schedules can be modified.']);
        }
        [$date, $timeSlot, $strategyName, $cleaner, $notes] = $this->validateSchedule($data);
        $schedule->setDetails(Auth::requireLogin()->getKey(), $date, $timeSlot, $strategyName, $notes);
        $schedule->save();
        foreach ($schedule->getAssignments() as $assignment) {
            if ($assignment->getStatus() === 'Assigned') {
                $assignment->setDetails(
                    $schedule->getKey(), $cleaner->getKey(), $assignment->getBin()->getKey(),
                    $assignment->getSourceComplaint()?->getKey(), $assignment->getPriority(), (string) $assignment->getReason()
                );
                $assignment->save();
            }
        }
        return $schedule;
    }

    public function cancel(int $id): CollectionSchedule
    {
        UserPermissions::require('schedule.manage');
        $schedule = $this->requireSchedule($id);
        if ($schedule->getStatus() === 'Completed') {
            throw new ValidationException(['schedule' => 'A completed schedule cannot be cancelled.']);
        }
        $pdo = Database::getInstance()->pdo();
        $pdo->beginTransaction();
        try {
            $schedule->setStatus('Cancelled');
            $schedule->save();
            foreach ($schedule->getAssignments() as $assignment) {
                if ($assignment->getStatus() === 'Assigned') {
                    $assignment->skip('Schedule cancelled by administrator.');
                    $assignment->save();
                }
            }
            $pdo->commit();
            return $schedule;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        }
    }

    public function completeAssignment(int $id, User $cleaner, array $data): CollectionAssignment
    {
        UserPermissions::require('assignment.complete');
        $assignment = CollectionAssignment::find($id);
        if ($assignment === null) { throw new OutOfBoundsException('Assignment not found.'); }
        if ($assignment->getCleanerId() !== $cleaner->getKey()) {
            throw new AuthorizationException('A cleaner can only complete their own assignment.');
        }
        if ($assignment->getStatus() !== 'Assigned' || $assignment->getSchedule()?->getStatus() !== 'Planned') {
            throw new ValidationException(['assignment' => 'This assignment is no longer open.']);
        }
        $weightRaw = trim((string) ($data['estimated_weight_kg'] ?? ''));
        $weight = $weightRaw === '' ? null : filter_var($weightRaw, FILTER_VALIDATE_FLOAT);
        $notes = trim((string) ($data['notes'] ?? ''));
        $errors = [];
        if ($weight !== null && ($weight === false || $weight < 0 || $weight > 100000)) {
            $errors['estimated_weight_kg'] = 'Weight must be between 0 and 100,000 kg.';
        }
        if (mb_strlen($notes) > 1000) { $errors['notes'] = 'Notes cannot exceed 1,000 characters.'; }
        if ($errors !== []) { throw new ValidationException($errors); }

        $bin = $assignment->getBin();
        if ($bin === null) { throw new RuntimeException('Assigned bin is unavailable.'); }
        $pdo = Database::getInstance()->pdo();
        $pdo->beginTransaction();
        try {
            $assignment->complete($notes === '' ? null : $notes);
            $assignment->save();
            $record = new CollectionRecord();
            $record->setDetails($assignment->getKey(), $cleaner->getKey(), $bin, $weight === null ? null : (float) $weight, $notes === '' ? null : $notes);
            $record->save();
            (new BinLocationServiceProxy(new BinLocationService()))->updateBinStatus(
                $bin->getKey(), Bin::STATUS_EMPTY, 'Emptied during assignment #' . $assignment->getKey(), $cleaner->getKey()
            );

            $schedule = $assignment->getSchedule();
            if ($schedule !== null && CollectionAssignment::remainingForSchedule($schedule->getKey()) === 0) {
                $schedule->setStatus('Completed');
                $schedule->save();
            }
            $pdo->commit();
            return $assignment;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        }
    }

    public function findVisible(User $user, int $id): ?CollectionSchedule
    {
        $schedule = CollectionSchedule::find($id);
        if ($schedule === null) { return null; }
        if (UserPermissions::can($user, 'schedule.manage')) { return $schedule; }
        if (UserPermissions::can($user, 'assignment.view_own')) {
            foreach ($schedule->getAssignments() as $assignment) {
                if ($assignment->getCleanerId() === $user->getKey()) { return $schedule; }
            }
        }
        throw new AuthorizationException('You cannot access this schedule.');
    }

    private function validateSchedule(array $data): array
    {
        $date = trim((string) ($data['schedule_date'] ?? ''));
        $timeSlot = trim((string) ($data['time_slot'] ?? ''));
        $strategy = trim((string) ($data['strategy'] ?? ''));
        $cleanerId = filter_var($data['cleaner_id'] ?? null, FILTER_VALIDATE_INT);
        $notes = trim((string) ($data['notes'] ?? ''));
        $cleaner = $cleanerId === false ? null : User::find((int) $cleanerId);
        $errors = [];
        $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($dateObject === false || $dateObject->format('Y-m-d') !== $date || $date < date('Y-m-d')) {
            $errors['schedule_date'] = 'Select today or a future date.';
        }
        if ($timeSlot === '' || mb_strlen($timeSlot) > 50) { $errors['time_slot'] = 'Time slot is required and limited to 50 characters.'; }
        if (!in_array($strategy, SchedulingStrategyFactory::names(), true)) { $errors['strategy'] = 'Select a valid scheduling strategy.'; }
        if ($cleaner === null || !$cleaner->isCleaner() || !$cleaner->isActive()) { $errors['cleaner_id'] = 'Select an active Cleaner.'; }
        if (mb_strlen($notes) > 500) { $errors['notes'] = 'Notes cannot exceed 500 characters.'; }
        if ($errors !== []) { throw new ValidationException($errors); }
        return [$date, $timeSlot, $strategy, $cleaner, $notes === '' ? null : $notes];
    }

    private function requireSchedule(int $id): CollectionSchedule
    {
        $schedule = CollectionSchedule::find($id);
        if ($schedule === null) { throw new OutOfBoundsException('Schedule not found.'); }
        return $schedule;
    }
}
