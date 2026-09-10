<?php
/**
 * Complaint business rules and Observer coordination.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 *
 * This service is the only place complaint state changes. It raises a single
 * event through ComplaintStatusSubject; the attached observers decide what
 * that event means for the audit trail, the admin inbox and the bin record.
 */
class ComplaintService
{
    private ComplaintStatusSubject $subject;

    public function __construct()
    {
        $this->subject = new ComplaintStatusSubject();

        // Three independent reactions to one complaint event. The service
        // knows only that it must notify - not what any observer does.
        $this->subject->attach(new ComplaintHistoryObserver());
        $this->subject->attach(new ComplaintNotificationObserver());
        $this->subject->attach(new ComplaintBinFlagObserver());
    }

    public function create(User $reporter, array $data, ?array $upload): Complaint
    {
        UserPermissions::require('complaint.create');
        [$binId, $type, $description] = $this->validateComplaint($data);
        $stored = null;
        $pdo = Database::getInstance()->pdo();
        $pdo->beginTransaction();
        try {
            $complaint = new Complaint();
            $complaint->setDetails($reporter->getKey(), $binId, $type, $description);
            $complaint->save();

            $stored = (new ComplaintUploadService())->validateAndStore($upload);
            if ($stored !== null) {
                $attachment = new ComplaintAttachment();
                $attachment->setDetails($complaint->getKey(), $stored);
                $attachment->save();
            }
            $this->subject->notify($complaint, null, Complaint::STATUS_NEW, $reporter, 'Complaint submitted.');
            $pdo->commit();
            return $complaint;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($stored !== null && isset($stored['path']) && is_file($stored['path'])) {
                unlink($stored['path']);
            }
            throw $error;
        }
    }

    public function searchVisible(User $user, string $query, string $status, ?int $locationId): array
    {
        if (UserPermissions::can($user, 'complaint.manage')) {
            return Complaint::search(null, trim($query), $status, $locationId);
        }
        if (UserPermissions::can($user, 'complaint.view_own')) {
            return Complaint::search($user->getKey(), trim($query), $status, $locationId);
        }
        throw new AuthorizationException('Your role does not have complaint access.');
    }

    public function findVisible(User $user, int $id): ?Complaint
    {
        $complaint = Complaint::find($id);
        if ($complaint === null || $complaint->isDeleted()) {
            return null;
        }
        if (UserPermissions::can($user, 'complaint.manage')
            || (UserPermissions::can($user, 'complaint.view_own') && $complaint->getReporterId() === $user->getKey())) {
            return $complaint;
        }
        throw new AuthorizationException('You cannot access another reporter’s complaint.');
    }

    public function updateStatus(int $id, string $status, string $remarks, User $administrator): Complaint
    {
        UserPermissions::require('complaint.manage');
        $complaint = Complaint::find($id);
        if ($complaint === null || $complaint->isDeleted()) {
            throw new OutOfBoundsException('Complaint not found.');
        }
        $old = $complaint->getStatus();
        $errors = [];
        if (!in_array($status, Complaint::allowedNextStatuses($old), true)) {
            $errors['complaint_status'] = Complaint::allowedNextStatuses($old) === []
                ? 'This complaint is already in a final state.'
                : 'Select a valid next status in the complaint lifecycle.';
        }
        if (mb_strlen($remarks) > 255) {
            $errors['remarks'] = 'Remarks cannot exceed 255 characters.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $pdo = Database::getInstance()->pdo();
        $pdo->beginTransaction();
        try {
            $complaint->setStatus($status);
            $complaint->save();
            $this->subject->notify(
                $complaint,
                $old,
                $status,
                $administrator,
                $remarks === '' ? null : $remarks
            );
            $pdo->commit();
            return $complaint;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    /**
     * Closes a set of duplicate reports, keeping one as the live complaint.
     *
     * Every other open complaint of the same issue type on the same bin is
     * rejected with a remark naming the one that was kept. Each rejection goes
     * through updateStatus(), so the observers run once per complaint: every
     * reporter is notified individually and every complaint keeps its own
     * history. One administrator action, one outcome per person.
     *
     * New and Assigned both permit a transition to Rejected, so no complaint
     * in the group can be left stranded.
     *
     * Each rejection commits on its own - updateStatus() opens its own
     * transaction and PDO will not nest them - so a failure part way through
     * leaves the earlier rejections recorded rather than rolling them back.
     *
     * @return int how many duplicates were rejected
     */
    public function rejectDuplicates(int $binId, string $type, int $keepId, User $administrator): int
    {
        UserPermissions::require('complaint.manage');

        $group = Complaint::openForBinAndType($binId, $type);
        if (count($group) < 2) {
            throw new ValidationException([
                'complaint' => 'There are no longer multiple open reports for this bin.']);
        }

        $ids = array_map(static fn(Complaint $c): int => (int) $c->getKey(), $group);
        if (!in_array($keepId, $ids, true)) {
            throw new ValidationException([
                'complaint' => 'Choose which report to keep from this group.']);
        }

        $keptNumber = '#' . $keepId;
        foreach ($group as $complaint) {
            if ((int) $complaint->getKey() === $keepId) {
                $keptNumber = $complaint->getNumber();
            }
        }

        $rejected = 0;
        foreach ($group as $complaint) {
            if ((int) $complaint->getKey() === $keepId) {
                continue;
            }
            $this->updateStatus(
                (int) $complaint->getKey(),
                Complaint::STATUS_REJECTED,
                'Duplicate of complaint ' . $keptNumber . '.',
                $administrator
            );
            $rejected++;
        }
        return $rejected;
    }

    public function update(int $id, array $data, User $user): Complaint
    {
        $complaint = $this->findVisible($user, $id);
        if ($complaint === null) { throw new OutOfBoundsException('Complaint not found.'); }
        if ($complaint->getStatus() !== Complaint::STATUS_NEW || $complaint->hasOpenAssignments()) {
            throw new ValidationException(['complaint' => 'Only new complaints without open assignments can be edited.']);
        }
        [$binId, $type, $description] = $this->validateComplaint(array_replace($complaint->toArray(), $data));
        $complaint->setDetails($complaint->getReporterId(), $binId, $type, $description);
        $complaint->save();
        return $complaint;
    }

    /**
     * Statuses from which each role may remove a complaint.
     *
     * A Reporter may withdraw only a complaint nobody has acted on yet. Once
     * an Administrator has taken it up, the record belongs to the audit trail
     * as much as to the reporter, and removing it would erase the account of
     * how it was handled.
     *
     * Nobody may delete from Assigned. Deletion writes no history and notifies
     * nobody, so ending a live complaint that way would leave its story
     * unfinished and its reporter uninformed. An Administrator resolves or
     * rejects it first - which records a reason and notifies the reporter
     * through the observers - and may then delete it.
     */
    private const DELETABLE_BY_ADMIN = [
        Complaint::STATUS_NEW, Complaint::STATUS_RESOLVED, Complaint::STATUS_REJECTED,
    ];
    private const WITHDRAWABLE_BY_REPORTER = [Complaint::STATUS_NEW];

    /** True when this user may remove this complaint right now. */
    public function canDelete(Complaint $complaint, User $user): bool
    {
        if ($complaint->hasOpenAssignments()) {
            return false;
        }
        $allowed = UserPermissions::can($user, 'complaint.manage')
            ? self::DELETABLE_BY_ADMIN
            : self::WITHDRAWABLE_BY_REPORTER;

        return in_array($complaint->getStatus(), $allowed, true);
    }

    public function delete(int $id, User $user): void
    {
        $complaint = $this->findVisible($user, $id);
        if ($complaint === null) { throw new OutOfBoundsException('Complaint not found.'); }

        if ($complaint->hasOpenAssignments()) {
            throw new ValidationException([
                'complaint' => 'A cleaner is still assigned to this complaint. '
                             . 'Complete or cancel the assignment first.']);
        }
        if (!$this->canDelete($complaint, $user)) {
            throw new ValidationException([
                'complaint' => UserPermissions::can($user, 'complaint.manage')
                    ? 'Resolve or reject this complaint before deleting it, so the '
                    . 'outcome is recorded and the reporter is notified.'
                    : 'You can only withdraw a complaint that has not been acted on yet.']);
        }
        $complaint->delete();
    }

    private function validateComplaint(array $data): array
    {
        $errors = [];
        foreach (['bin_id', 'complaint_type', 'description'] as $field) {
            if (array_key_exists($field, $data) && !is_scalar($data[$field]) && $data[$field] !== null) {
                $errors[$field] = 'Enter a single value.';
            }
        }
        if ($errors !== []) { throw new ValidationException($errors); }
        $binId = filter_var($data['bin_id'] ?? null, FILTER_VALIDATE_INT);
        $type = trim((string) ($data['complaint_type'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $errors = [];
        $bin = $binId === false ? null : Bin::find((int) $binId);
        if ($bin === null || !$bin->isActive()) {
            $errors['bin_id'] = 'Select an active campus bin.';
        }
        if (!in_array($type, Complaint::types(), true)) {
            $errors['complaint_type'] = 'Select a valid issue type.';
        }
        if (mb_strlen($description) < 10 || mb_strlen($description) > 2000) {
            $errors['description'] = 'Description must contain 10-2,000 characters.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return [(int) $binId, $type, $description];
    }
}
