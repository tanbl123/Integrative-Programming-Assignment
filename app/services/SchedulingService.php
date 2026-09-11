<?php

/**
 * Collection scheduling orchestration.
 * Author : Ng Zi Zhang (2406898)
 * Module : Collection Scheduling & Assignment
 */
class SchedulingService {

    public function create(User $administrator, array $data): CollectionSchedule {
        UserPermissions::require('schedule.manage');

        $data['time_slot'] = $this->composeTimeSlot($data);

        [$date, $timeSlot, $strategyName, $cleaner, $notes] = $this->validateSchedule($data);

        $selections = SchedulingStrategyFactory::make($strategyName)->select();

        if ($selections === []) {
            throw new ValidationException([
                        'strategy' => 'The selected strategy currently finds no bins requiring work.'
            ]);
        }

        $pdo = Database::getInstance()->pdo();
        $pdo->beginTransaction();

        try {
            $schedule = new CollectionSchedule();
            $schedule->setDetails($administrator->getKey(), $date, $timeSlot, $strategyName, $notes);
            $schedule->save();

            $complaints = new ComplaintService();

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

                $this->markComplaintsAssigned(
                        $complaints,
                        (int) $selection['bin']->getKey(),
                        (int) $schedule->getKey(),
                        $administrator
                );
            }

            $pdo->commit();

            return $schedule;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }

    /**
     * Books one cleaner for one bin, raised from one complaint.
     *
     * Author : Tan Boon Leong (2402865)
     * Module : Complaint / Report Management - cross-module integration
     *
     * create() generates a round: a strategy chooses the bins and the
     * administrator schedules whatever it found. That is the right shape for
     * planning a day's work and the wrong one for answering a single report,
     * which is what an administrator is doing when they have a complaint open
     * in front of them. This books the one bin that complaint names.
     *
     * The stored strategy is still Complaint Priority, because that is
     * truthfully why the collection exists, and it keeps the row inside the
     * schedules enum rather than needing a fourth value for what is really the
     * same reason with a narrower selection.
     *
     * The complaint moves to Assigned through the same private helper the
     * generated rounds use, so a booking made here is recorded and notified
     * exactly as one made there.
     */
    public function createForComplaint(
            User $administrator,
            Complaint $complaint,
            array $data
    ): CollectionSchedule {
        UserPermissions::require('schedule.manage');

        $bin = $complaint->getBin();

        if ($bin === null || !$bin->isActive()) {
            throw new ValidationException([
                        'schedule' => 'This complaint\'s bin is no longer active, so no collection can be booked for it.'
            ]);
        }

        // The form withdraws once a collection exists, but the form is not the
        // guarantee: two tabs, a double submit or a posted request would
        // otherwise send a second cleaner to a bin somebody is already on
        // their way to. Duplicate reports make this likely rather than
        // theoretical - three people report one bin, and each of their
        // complaints offers to book somebody.
        if ($complaint->binHasOpenCollection()) {
            throw new ValidationException([
                        'schedule' => 'A cleaner is already booked to visit this bin. '
                                    . 'Mark this complaint Assigned instead of booking a second visit.'
            ]);
        }

        $data['time_slot'] = $this->composeTimeSlot($data);

        [$date, $timeSlot, , $cleaner, $notes] = $this->validateSchedule(
                $data + ['strategy' => 'Complaint Priority']
        );

        $pdo = Database::getInstance()->pdo();
        $pdo->beginTransaction();

        try {
            $schedule = new CollectionSchedule();
            $schedule->setDetails(
                    $administrator->getKey(),
                    $date,
                    $timeSlot,
                    'Complaint Priority',
                    $notes ?? ('Raised from complaint ' . $complaint->getNumber() . '.')
            );
            $schedule->save();

            $assignment = new CollectionAssignment();
            $assignment->setDetails(
                    $schedule->getKey(),
                    $cleaner->getKey(),
                    $bin->getKey(),
                    $complaint->getKey(),
                    'Urgent',
                    'Complaint ' . $complaint->getNumber() . ': ' . $complaint->getType()
            );
            $assignment->save();

            $this->markComplaintsAssigned(
                    new ComplaintService(),
                    (int) $bin->getKey(),
                    (int) $schedule->getKey(),
                    $administrator
            );

            $pdo->commit();

            return $schedule;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }

    /**
     * Builds a time slot from a start and an end time.
     *
     * Author : Tan Boon Leong (2402865)
     * Module : Complaint / Report Management
     *
     * time_slot is a free-text column, and free text is where inconsistent
     * data comes from: the same three hours have already been written into it
     * as "09:00-12:00" and as "09:00–:12:00" with an en dash, which no
     * report could group and no person would think to search for twice. Two
     * time inputs cannot produce either mistake, and the browser shows them in
     * whatever notation the reader expects while posting an unambiguous 24
     * hour value.
     *
     * A caller that already has a composed slot - anything posting time_slot
     * directly rather than the pair - keeps working unchanged.
     */
    private function composeTimeSlot(array $data): string {
        $existing = trim((string) ($data['time_slot'] ?? ''));

        if (!isset($data['time_from'], $data['time_to']) && $existing !== '') {
            return $existing;
        }

        $from = is_scalar($data['time_from'] ?? null) ? trim((string) $data['time_from']) : '';
        $to = is_scalar($data['time_to'] ?? null) ? trim((string) $data['time_to']) : '';

        $isTime = static fn(string $value): bool
                => (bool) preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value);

        if (!$isTime($from) || !$isTime($to)) {
            throw new ValidationException([
                        'time_slot' => 'Choose a start time and an end time.'
            ]);
        }

        // Zero-padded 24 hour times compare correctly as strings.
        if ($to <= $from) {
            throw new ValidationException([
                        'time_slot' => 'The end time must be later than the start time.'
            ]);
        }

        return $from . '-' . $to;
    }

    public function update(int $id, array $data): CollectionSchedule {
        UserPermissions::require('schedule.manage');

        $schedule = $this->requireSchedule($id);

        if ($schedule->getStatus() !== 'Planned') {
            throw new ValidationException([
                        'schedule' => 'Only planned schedules can be modified.'
            ]);
        }

        $data['time_slot'] = $this->composeTimeSlot($data);

        [$date, $timeSlot, $strategyName, $cleaner, $notes] = $this->validateSchedule($data);

        if ($strategyName !== $schedule->getStrategy()) {
            throw new ValidationException([
                        'strategy' => 'The generation strategy cannot change after creation. Create a new schedule to use another strategy.'
            ]);
        }

        $pdo = Database::getInstance()->pdo();
        $pdo->beginTransaction();

        try {
            $schedule->setDetails(
                    Auth::requireLogin()->getKey(),
                    $date,
                    $timeSlot,
                    $strategyName,
                    $notes
            );

            $schedule->save();

            foreach ($schedule->getAssignments() as $assignment) {
                if ($assignment->getStatus() === 'Assigned') {
                    $assignment->setDetails(
                            $schedule->getKey(),
                            $cleaner->getKey(),
                            $assignment->getBin()->getKey(),
                            $assignment->getSourceComplaint()?->getKey(),
                            $assignment->getPriority(),
                            (string) $assignment->getReason()
                    );

                    $assignment->save();
                }
            }

            $pdo->commit();

            return $schedule;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }

    public function cancel(int $id): CollectionSchedule {
        UserPermissions::require('schedule.manage');

        $schedule = $this->requireSchedule($id);

        if ($schedule->getStatus() === 'Completed') {
            throw new ValidationException([
                        'schedule' => 'A completed schedule cannot be cancelled.'
            ]);
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
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }

    public function completeAssignment(int $id, User $cleaner, array $data): CollectionAssignment {
        UserPermissions::require('assignment.complete');

        $assignment = CollectionAssignment::find($id);

        if ($assignment === null) {
            throw new OutOfBoundsException('Assignment not found.');
        }

        if ($assignment->getCleanerId() !== $cleaner->getKey()) {
            throw new AuthorizationException('A cleaner can only complete their own assignment.');
        }

        if ($assignment->getStatus() !== 'Assigned' || $assignment->getSchedule()?->getStatus() !== 'Planned') {
            throw new ValidationException([
                        'assignment' => 'This assignment is no longer open.'
            ]);
        }

        if (isset($data['notes']) && !is_string($data['notes'])) {
            throw new ValidationException([
                        'notes' => 'Enter a text value.'
            ]);
        }

        $notes = trim((string) ($data['notes'] ?? ''));

        if (mb_strlen($notes) > 1000) {
            throw new ValidationException([
                        'notes' => 'Notes cannot exceed 1,000 characters.'
            ]);
        }

        $bin = $assignment->getBin();

        if ($bin === null) {
            throw new RuntimeException('Assigned bin is unavailable.');
        }

        $pdo = Database::getInstance()->pdo();
        $pdo->beginTransaction();

        try {
            $assignment->complete($notes === '' ? null : $notes);
            $assignment->save();

            $record = new CollectionRecord();

            $record->setDetails(
                    $assignment->getKey(),
                    $cleaner->getKey(),
                    $bin,
                    null,
                    $notes === '' ? null : $notes
            );

            $record->save();

            (new BinLocationServiceProxy(new BinLocationService()))->updateBinStatus(
                    $bin->getKey(),
                    Bin::STATUS_EMPTY,
                    'Emptied during assignment #' . $assignment->getKey(),
                    $cleaner->getKey()
            );

            $schedule = $assignment->getSchedule();

            if ($schedule !== null && CollectionAssignment::remainingForSchedule($schedule->getKey()) === 0) {
                $schedule->setStatus('Completed');
                $schedule->save();
            }

            $pdo->commit();

            return $assignment;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }

    public function findVisible(User $user, int $id): ?CollectionSchedule {
        $schedule = CollectionSchedule::find($id);

        if ($schedule === null || $schedule->isDeleted()) {
            return null;
        }

        if (UserPermissions::can($user, 'schedule.manage')) {
            return $schedule;
        }

        if (UserPermissions::can($user, 'assignment.view_own')) {
            foreach ($schedule->getAssignments() as $assignment) {
                if ($assignment->getCleanerId() === $user->getKey()) {
                    return $schedule;
                }
            }
        }

        throw new AuthorizationException('You cannot access this schedule.');
    }

    public function delete(int $id): void {
        UserPermissions::require('schedule.manage');

        $schedule = $this->requireSchedule($id);

        if (
                !in_array($schedule->getStatus(), ['Cancelled', 'Completed'], true) ||
                CollectionAssignment::remainingForSchedule($id) > 0
        ) {
            throw new ValidationException([
                        'schedule' => 'Cancel or complete the schedule before deleting it.'
            ]);
        }

        $schedule->delete();
    }

    /**
     * Moves every open complaint against a booked bin to Assigned.
     *
     * Author : Tan Boon Leong (2402865)
     * Module : Complaint / Report Management - cross-module integration
     *
     * Every open report of that bin, not only the one the task was raised
     * from. A strategy picks one complaint per bin, so booking a bin that
     * three people had reported used to move one of them and leave the other
     * two reading New while a cleaner was on the way to the very bin they
     * wrote about - the reporters heard nothing, and an administrator looking
     * at one of those reports saw no sign the work existed. The collection
     * answers the bin, so it answers all of them.
     *
     * It goes through ComplaintService rather than writing complaint_status,
     * so the complaint module's own rules apply and its observers run: each
     * change is recorded in the history against this administrator, and each
     * reporter is notified separately.
     *
     * Only a New complaint is moved. Assigned, Resolved and Rejected are all
     * refused by the lifecycle, and a refusal here would throw and take the
     * whole schedule down with it - so the check happens before the call
     * rather than as an exception afterwards. A complaint can legitimately
     * already be Assigned: an administrator may have triaged it by hand, or
     * an earlier schedule may have covered the same bin.
     *
     * There is no matching step when a schedule is cancelled. The lifecycle
     * has no route from Assigned back to New, and it should not: the
     * administrator's triage decision still stands even if this particular
     * round was called off. What disappears is the "a cleaner has been
     * scheduled" note on the complaint, which is read from the assignments
     * themselves and so corrects itself.
     */
    private function markComplaintsAssigned(
            ComplaintService $complaints,
            int $binId,
            int $scheduleId,
            User $administrator
    ): void {
        foreach (Complaint::openForBin($binId) as $complaint) {
            if ($complaint->getStatus() !== Complaint::STATUS_NEW) {
                continue;
            }

            $complaints->updateStatus(
                    (int) $complaint->getKey(),
                    Complaint::STATUS_ASSIGNED,
                    'Collection scheduled (schedule #' . $scheduleId . ').',
                    $administrator
            );
        }
    }

    private function validateSchedule(array $data): array {
        $errors = [];

        foreach (['schedule_date', 'time_slot', 'strategy', 'cleaner_id', 'notes'] as $field) {
            if (array_key_exists($field, $data) && !is_scalar($data[$field]) && $data[$field] !== null) {
                $errors[$field] = 'Enter a single value.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

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

        if ($timeSlot === '' || mb_strlen($timeSlot) > 50) {
            $errors['time_slot'] = 'Time slot is required and limited to 50 characters.';
        }

        if (!in_array($strategy, SchedulingStrategyFactory::names(), true)) {
            $errors['strategy'] = 'Select a valid scheduling strategy.';
        }

        if ($cleaner === null || !$cleaner->isCleaner() || !$cleaner->isActive()) {
            $errors['cleaner_id'] = 'Select an active Cleaner.';
        }

        if (mb_strlen($notes) > 500) {
            $errors['notes'] = 'Notes cannot exceed 500 characters.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            $date,
            $timeSlot,
            $strategy,
            $cleaner,
            $notes === '' ? null : $notes
        ];
    }

    private function requireSchedule(int $id): CollectionSchedule {
        $schedule = CollectionSchedule::find($id);

        if ($schedule === null || $schedule->isDeleted()) {
            throw new OutOfBoundsException('Schedule not found.');
        }

        return $schedule;
    }
}
