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

    public function delete(int $id, User $user): void
    {
        $complaint = $this->findVisible($user, $id);
        if ($complaint === null) { throw new OutOfBoundsException('Complaint not found.'); }
        if (!in_array($complaint->getStatus(), [Complaint::STATUS_NEW, Complaint::STATUS_RESOLVED, Complaint::STATUS_REJECTED], true)
            || $complaint->hasOpenAssignments()) {
            throw new ValidationException(['complaint' => 'Only new or final complaints without open assignments can be deleted.']);
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
