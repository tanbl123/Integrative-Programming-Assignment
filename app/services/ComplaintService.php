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
    /**
     * How long a message to the reporter may be.
     *
     * The notification body is 255 characters and already holds a sentence
     * naming the bin, so this is what is left over with room to spare. The
     * booking forms carry the same number in maxlength, so the browser stops
     * at the same place the service does.
     */
    public const REPORTER_MESSAGE_MAX = 120;

    private ComplaintStatusSubject $subject;

    public function __construct()
    {
        $this->subject = new ComplaintStatusSubject();

        // Four independent reactions to one complaint event. The service
        // knows only that it must notify - not what any observer does.
        $this->subject->attach(new ComplaintHistoryObserver());
        $this->subject->attach(new ComplaintNotificationObserver());
        // Given the Bin module's own service, so a complaint's claim about a
        // bin is validated and recorded by the module that owns the record.
        $this->subject->attach(new ComplaintBinFlagObserver(new BinLocationService()));
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

    /**
     * Moves a complaint through the lifecycle.
     *
     * $remarks is written for the record and $forReporter is written for the
     * reporter; see ComplaintObserver::changed() for why those are two things
     * rather than one. $forReporter is optional everywhere - most transitions
     * have nothing to add - and is simply carried to the observers, which
     * decide whether anybody reads it.
     */
    public function updateStatus(
        int $id,
        string $status,
        string $remarks,
        User $administrator,
        ?string $forReporter = null
    ): Complaint {
        UserPermissions::require('complaint.manage');
        $complaint = Complaint::find($id);
        if ($complaint === null || $complaint->isDeleted()) {
            throw new OutOfBoundsException('Complaint not found.');
        }
        $old = $complaint->getStatus();
        $errors = [];
        if (!in_array($status, $this->nextStatusesFor($complaint), true)) {
            $errors['complaint_status'] = match (true) {
                Complaint::allowedNextStatuses($old) === []
                    => 'This complaint is already in a final state.',
                // Named separately from "not a valid next status", because the
                // step is valid and the reason it is refused is fixable.
                $status === Complaint::STATUS_ASSIGNED
                    => 'No cleaner is booked to visit this bin, so this complaint cannot be '
                     . 'marked Assigned. Generate a collection schedule for the bin first; '
                     . 'that moves the complaint here on its own.',
                default => 'Select a valid next status in the complaint lifecycle.',
            };
        }
        if (mb_strlen($remarks) > 255) {
            $errors['remarks'] = 'Remarks cannot exceed 255 characters.';
        }
        // Shorter than the remark, because this one is appended to a sentence
        // that already names the bin, inside a notification body of 255
        // characters. A limit the writer is told about beats a message that
        // saves in full and then arrives cut in half.
        if ($forReporter !== null && mb_strlen($forReporter) > self::REPORTER_MESSAGE_MAX) {
            $errors['reporter_message'] = 'The message to the reporter cannot exceed '
                                        . self::REPORTER_MESSAGE_MAX . ' characters.';
        }
        // Required for a rejection and optional everywhere else. Resolved and
        // Assigned are good news and say what happened on their own; Rejected
        // tells a reporter their report was turned down, and "Your report was
        // marked Rejected" with nothing after it is the one message in this
        // system that leaves somebody worse informed than before they read it.
        if ($status === Complaint::STATUS_REJECTED && trim($remarks) === '') {
            $errors['remarks'] = 'Say why this report is being rejected. The reporter is '
                               . 'told the outcome, so they should be told the reason.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        // A caller may already be inside a transaction - the Scheduling module
        // moves a complaint to Assigned from inside its own - and PDO refuses
        // a nested beginTransaction(). Joining the caller's transaction rather
        // than opening one means the complaint, its history and the caller's
        // own work commit or roll back together.
        $pdo = Database::getInstance()->pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $complaint->setStatus($status);
            $complaint->save();
            $this->subject->notify(
                $complaint,
                $old,
                $status,
                $administrator,
                $remarks === '' ? null : $remarks,
                ComplaintObserver::EVENT_STATUS,
                null,
                $forReporter === null || trim($forReporter) === '' ? null : trim($forReporter)
            );
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $complaint;
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    /**
     * The statuses this complaint may actually be moved to right now.
     *
     * The lifecycle says which steps exist; this says which of them are open,
     * and Assigned has a condition attached. Assigned means a cleaner is
     * coming, so it is refused until one is: an Administrator who sets it
     * without scheduling anything leaves a complaint that reads as being
     * dealt with while nobody has been sent, and a reporter who has been told
     * so. The Scheduling module saves the assignment before asking for this
     * move, so its own call passes.
     *
     * Both the form and updateStatus() read this, so what an Administrator is
     * offered and what the service accepts cannot drift apart.
     *
     * @return list<string>
     */
    public function nextStatusesFor(Complaint $complaint): array
    {
        $allowed = Complaint::allowedNextStatuses($complaint->getStatus());

        if ($complaint->binHasOpenCollection()) {
            return $allowed;
        }

        return array_values(array_filter(
            $allowed,
            static fn(string $status): bool => $status !== Complaint::STATUS_ASSIGNED
        ));
    }

    /**
     * Closes a whole group of reports of the same issue together.
     *
     * The group used to be closed by keeping one report and rejecting the
     * rest. That is the wrong word for what happened to them. Rejected is the
     * outcome for a report the campus is not going to act on, and it is the
     * outcome the reporter is told: somebody who saw a genuine overflow and
     * took the trouble to report it was told their report was rejected,
     * because a colleague had happened to report the same bin first. The
     * issue then got dealt with and they never heard so.
     *
     * Being second is not a reason to be turned down. One collection answers
     * every report of that bin, so every one of those reports is resolved by
     * it, and every reporter is told the same true thing.
     *
     * Rejecting stays available on each complaint on its own, for a report
     * that is genuinely not going to be acted on.
     *
     * A report still waiting on a cleaner cannot be resolved - the lifecycle
     * refuses it and so does this. Where the bin already has a collection
     * booked, one filed after that booking is moved along to Assigned first,
     * which is what would have happened had it arrived a minute earlier.
     *
     * Each complaint goes through updateStatus(), so the observers run once
     * per report: every reporter is notified individually and every complaint
     * keeps its own history. One administrator action, one outcome each.
     *
     * The whole group commits or none of it does.
     *
     * Rejecting a group is the other outcome and needs no collection: a
     * report nobody is going to act on can be turned down whether or not a
     * cleaner was ever sent. Resolving one does need a collection, because
     * resolved means the issue was dealt with.
     *
     * @return array{closed:int,waiting:list<string>}
     */
    public function closeDuplicates(
        int $binId,
        string $type,
        string $status,
        string $remarks,
        User $administrator
    ): array {
        UserPermissions::require('complaint.manage');

        if (!in_array($status, [Complaint::STATUS_RESOLVED, Complaint::STATUS_REJECTED], true)) {
            throw new ValidationException([
                'complaint' => 'Choose whether to resolve or to reject these reports.']);
        }

        $group = Complaint::openForBinAndType($binId, $type);
        if (count($group) < 2) {
            throw new ValidationException([
                'complaint' => 'There are no longer multiple open reports for this bin.']);
        }
        if (trim($remarks) === '') {
            throw new ValidationException([
                'remarks' => $status === Complaint::STATUS_REJECTED
                    ? 'Say why these reports are being rejected. Every reporter is told the same thing.'
                    : 'Say what was done. Every reporter in this group is told the same thing.']);
        }

        $pdo = Database::getInstance()->pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $closed = 0;
            $waiting = [];

            foreach ($group as $complaint) {
                $id = (int) $complaint->getKey();

                // Rejecting is reachable from New and from Assigned alike, so
                // only resolving has to pass through Assigned first.
                if ($status === Complaint::STATUS_RESOLVED
                        && $complaint->getStatus() === Complaint::STATUS_NEW) {
                    if (!$complaint->binHasOpenCollection()) {
                        $waiting[] = $complaint->getNumber();
                        continue;
                    }
                    $this->updateStatus($id, Complaint::STATUS_ASSIGNED, $remarks, $administrator);
                }

                $this->updateStatus($id, $status, $remarks, $administrator);
                $closed++;
            }

            if ($closed === 0) {
                throw new ValidationException([
                    'complaint' => 'No cleaner has been sent to this bin yet, so none of these '
                                 . 'reports can be resolved. Book a collection first, or reject them.']);
            }

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return ['closed' => $closed, 'waiting' => $waiting];
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
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
     * An Administrator may only delete a complaint that has already been
     * answered. Deleting is not an outcome and nobody is told it happened -
     * the withdrawal event alerts the other administrators, and that is all -
     * so it must never be the thing that ENDS a complaint. It removes one
     * that is already over.
     *
     * New used to be in this list, and that was the hole. A report waiting to
     * be looked at could be deleted outright: it vanished from the reporter's
     * list with no outcome, no reason and no notification, and from the
     * administrator's own list too, so nobody could see it had ever existed.
     * The reporter was left believing their report was still being
     * considered. The comment here described the rule correctly and the
     * constant did not implement it, and canDelete()'s own refusal message
     * has always said what it should be: resolve or reject it first, so the
     * outcome is recorded and the reporter is told. A rejection cannot even
     * be saved without a reason. Deleting afterwards then removes nothing the
     * reporter has not already heard about.
     *
     * Assigned is refused to everybody, and separately hasOpenAssignments()
     * refuses any complaint a cleaner is still on their way to.
     */
    private const DELETABLE_BY_ADMIN = [
        Complaint::STATUS_RESOLVED, Complaint::STATUS_REJECTED,
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
            ($data['marks_bin_full'] ?? '') === '1',
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
        $type->setDetails($type->getName(), $type->marksBinFull(), $active, $type->getSortOrder());
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

        $status = $complaint->getStatus();
        $pdo = Database::getInstance()->pdo();
        $pdo->beginTransaction();
        try {
            $complaint->delete();

            // Raised AFTER the soft delete, so that an observer asking what is
            // still open for this bin gets an answer that already excludes
            // this complaint. Withdrawing used to raise nothing at all, which
            // left the history with no record of who removed the complaint
            // and left a bin marked Full by a report that no longer existed.
            $this->subject->notify(
                $complaint,
                $status,
                $status,
                $user,
                'Withdrawn by ' . $user->getFullName() . '.',
                ComplaintObserver::EVENT_WITHDRAWN
            );
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
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
