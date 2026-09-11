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
        // Added last, and needing no change to this service beyond this line.
        $this->subject->attach(new ComplaintRevisionObserver());
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

    /**
     * The complaints this user may see.
     *
     * Which complaints those are is the user's own business, so the question is
     * put to them: a Reporter answers with theirs, an Administrator with all of
     * them, a Cleaner by refusing. This method used to test the permissions and
     * branch on the answer, which meant every role's rule lived here rather
     * than with the role.
     */
    public function searchVisible(User $user, string $query, string $status, ?int $locationId): array
    {
        return $user->visibleComplaints($query, $status, $locationId);
    }

    public function findVisible(User $user, int $id): ?Complaint
    {
        $complaint = Complaint::find($id);
        if ($complaint === null || $complaint->isDeleted()) {
            return null;
        }
        if ($user->maySee($complaint)) {
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

    /**
     * Edits a complaint, optionally replacing or removing its photograph.
     *
     * A reporter who attached the wrong photo could previously only withdraw
     * the complaint and start again, because the evidence was fixed at
     * submission. Editing is permitted only while a complaint is New and
     * unassigned, so nobody has yet acted on what is being changed.
     *
     * The replacement is verified and written to disk before anything else
     * changes, so a rejected file leaves the complaint exactly as it was. The
     * photograph it replaces is marked superseded rather than deleted: the
     * revision written by ComplaintRevisionObserver points at it, so swapping a
     * damning photo for an innocuous one stays as visible as rewriting the
     * words. Removing a photograph without supplying another does the same -
     * evidence is optional on a complaint, so a reporter must be able to get
     * back to having none without withdrawing and losing the complaint's number
     * and history. Nothing on this path removes a file; the only unlink is in
     * the rollback below, where a stored file has no row to belong to.
     */
    public function update(
        int $id,
        array $data,
        User $user,
        ?array $upload = null,
        bool $removePhoto = false
    ): Complaint {
        $complaint = $this->findVisible($user, $id);
        if ($complaint === null) { throw new OutOfBoundsException('Complaint not found.'); }
        if (!$this->canEdit($complaint, $user)) {
            throw new ValidationException([
                'complaint' => 'Only new complaints without open assignments can be edited.']);
        }
        [$binId, $type, $description] = $this->validateComplaint(array_replace($complaint->toArray(), $data));

        // What the complaint said before: the audit row names which fields
        // moved, and ComplaintRevisionObserver keeps the wording itself.
        $existing = $complaint->getAttachments()[0] ?? null;
        $before = [
            'bin' => $complaint->getBinId(),
            'type' => $complaint->getType(),
            'description' => $complaint->getDescription(),
            'attachment' => $existing?->getKey(),
        ];

        $stored = (new ComplaintUploadService())->validateAndStore($upload);

        $pdo = Database::getInstance()->pdo();
        $pdo->beginTransaction();
        try {
            $complaint->setDetails($complaint->getReporterId(), $binId, $type, $description);
            $complaint->save();

            if ($stored !== null || $removePhoto) {
                // Marked as replaced, never deleted. The revision written below
                // points at it, so the photograph an edit replaced can still be
                // seen beside the wording it replaced.
                $existing?->supersede();
            }
            if ($stored !== null) {
                $attachment = new ComplaintAttachment();
                $attachment->setDetails($complaint->getKey(), $stored);
                $attachment->save();
            }

            $changed = array_keys(array_diff_assoc(
                ['bin' => $binId, 'issue type' => $type, 'description' => $description],
                ['bin' => $before['bin'], 'issue type' => $before['type'],
                 'description' => $before['description']]
            ));
            if ($stored !== null) {
                $changed[] = 'photo';
            } elseif ($removePhoto && $existing !== null) {
                $changed[] = 'photo removed';
            }

            // Saving without changing anything is not an event worth recording.
            if ($changed !== []) {
                $this->subject->notify(
                    $complaint,
                    $complaint->getStatus(),
                    $complaint->getStatus(),
                    $user,
                    'Edited: ' . implode(', ', $changed) . '.',
                    ComplaintObserver::EVENT_DETAILS,
                    $before
                );
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($stored !== null && isset($stored['path']) && is_file($stored['path'])) {
                unlink($stored['path']);
            }
            throw $error;
        }
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

    /**
     * Adds an issue type, or renames one.
     *
     * The name is the whole of it, and a new type is NOT offered to reporters
     * until someone says so. A type is permanent the moment a complaint names
     * it - the foreign key sees to that - so a misspelling that a reporter
     * files against in the seconds after it is created can never be deleted
     * afterwards. Adding it unavailable leaves a gap in which the spelling can
     * be checked. Making it available is a separate, deliberate act.
     *
     * Its place in the dropdown is not an Administrator's decision: the six
     * seeded types keep Other last, and anything added goes after them.
     *
     * Only an Administrator, because this list governs what every reporter may
     * file. The name must be unique, which the database enforces as well.
     *
     * @throws ValidationException
     */
    public function saveType(?int $id, array $data, User $user): ComplaintType
    {
        UserPermissions::require('complaint.manage');

        $name = trim((string) ($data['type_name'] ?? ''));

        $errors = [];
        if ($name === '' || mb_strlen($name) > 50) {
            $errors['type_name'] = 'Give the issue type a name of 1-50 characters.';
        }

        $type = $id === null ? new ComplaintType() : ComplaintType::find($id);
        if ($id !== null && $type === null) {
            throw new OutOfBoundsException('Issue type not found.');
        }

        foreach (ComplaintType::allOrdered() as $existing) {
            if (mb_strtolower($existing->getName()) === mb_strtolower($name)
                && $existing->getKey() !== $type?->getKey()) {
                $errors['type_name'] = 'That issue type already exists.';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $type->setDetails(
            $name,
            $id === null ? false : $type->isActive(),
            $id === null ? ComplaintType::nextSortOrder() : $type->getSortOrder()
        );
        $type->save();

        return $type;
    }

    /**
     * Withdraws an issue type from use, or brings it back.
     *
     * Withdrawing is not deleting. Complaints already filed under the type
     * keep naming it, and the row has to stay for them to remain valid; what
     * changes is that no new complaint can choose it.
     */
    public function setTypeActive(int $id, bool $active, User $user): ComplaintType
    {
        UserPermissions::require('complaint.manage');

        $type = ComplaintType::find($id);
        if ($type === null) {
            throw new OutOfBoundsException('Issue type not found.');
        }
        $type->setDetails($type->getName(), $active, $type->getSortOrder());
        $type->save();

        return $type;
    }

    /**
     * Deletes an issue type nobody has used.
     *
     * A type complaints were filed under is never deleted: the complaints
     * name it, and removing it would leave them naming something that does
     * not exist. The database refuses it too, through ON DELETE RESTRICT;
     * this check exists so the refusal arrives as a sentence rather than as
     * a foreign key error.
     */
    public function deleteType(int $id, User $user): void
    {
        UserPermissions::require('complaint.manage');

        $type = ComplaintType::find($id);
        if ($type === null) {
            throw new OutOfBoundsException('Issue type not found.');
        }
        $used = $type->complaintCount();
        if ($used > 0) {
            throw new ValidationException(['type' => $used . ' complaint'
                . ($used === 1 ? ' was' : 's were') . ' filed under "' . $type->getName()
                . '", so it cannot be deleted. Withdraw it from use instead.']);
        }
        $type->delete();
    }

    /**
     * True when this user may change this complaint's own details right now.
     *
     * The reporter who wrote it, or an Administrator. Editing stays shut once a
     * complaint leaves New or a cleaner is assigned, because from that point
     * the description is what somebody is acting upon.
     *
     * An Administrator editing another person's report is a real power - the
     * description is that reporter's account of what they saw, and a bin that
     * keeps vanishing could be reworded into something milder. It is allowed
     * because an Administrator does need to fix a plainly wrong bin so the
     * right cleaner is sent, and because moderating abusive wording is part of
     * running the system. What makes it safe is not the permission but the
     * record: update() raises ComplaintObserver::EVENT_DETAILS, so the history
     * gains a row naming who edited it and which fields changed, and the
     * reporter is notified that their words were altered. The power exists and
     * is answerable, rather than being silent.
     */
    public function canEdit(Complaint $complaint, User $user): bool
    {
        return $complaint->getStatus() === Complaint::STATUS_NEW
            && !$complaint->hasOpenAssignments()
            && ($complaint->getReporterId() === $user->getKey()
                || UserPermissions::can($user, 'complaint.manage'));
    }

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
        // Checked against the types currently in use. The database refuses
        // an unknown one as well, through the foreign key, so a type deleted
        // between this check and the insert cannot slip through.
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
