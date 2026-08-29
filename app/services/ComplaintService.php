<?php
/** Complaint business rules and Observer coordination. Author: Ong Kar Heng (2408830). */
class ComplaintService
{
    private ComplaintStatusSubject $subject;

    public function __construct()
    {
        $this->subject = new ComplaintStatusSubject();
        $this->subject->attach(new ComplaintHistoryObserver());
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
        if ($complaint === null) {
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
        if ($complaint === null) {
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

    private function validateComplaint(array $data): array
    {
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
