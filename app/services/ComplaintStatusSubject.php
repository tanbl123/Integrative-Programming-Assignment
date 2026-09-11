<?php
/**
 * Observer design pattern for complaint state transitions.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management - Observer design pattern
 *
 * PROBLEM
 * Submitting a complaint, or moving one through its lifecycle, has to cause
 * several unrelated things to happen: an audit record must be written, an
 * administrator must be alerted, and the affected bin's fill status may need
 * updating. Putting all three inside ComplaintService would tie one class to
 * three different concerns, and every new reaction would mean editing it again.
 *
 * SOLUTION
 * ComplaintService raises ONE event. Each reaction lives in its own observer
 * class implementing ComplaintObserver. The subject notifies whoever is
 * attached, without knowing or caring what they do. Adding a fourth reaction
 * later means writing one new class and attaching it - the service is untouched.
 *
 *   ComplaintStatusSubject (Subject)
 *        |  notify()
 *        +--> ComplaintHistoryObserver      writes the audit trail
 *        +--> ComplaintNotificationObserver raises an administrator alert
 *        +--> ComplaintBinFlagObserver      updates the affected bin
 *        +--> ComplaintRevisionObserver     keeps what an edit replaced
 *
 * TWO KINDS OF EVENT
 * The subject broadcasts a status transition, and separately an edit to a
 * complaint's own fields. Each observer decides for itself whether an event
 * concerns it: the audit trail records both, the notification differs between
 * them, and the bin observer ignores an edit outright, because changing the
 * wording of a report says nothing about how full the bin is. Adding the
 * second kind of event changed no observer that did not care about it, which
 * is the loose coupling the pattern exists to provide.
 */

/** Contract every complaint observer must satisfy. */
interface ComplaintObserver
{
    /** A move through the complaint lifecycle. */
    public const EVENT_STATUS = 'Status';

    /** An edit to the complaint's own fields, with the status unchanged. */
    public const EVENT_DETAILS = 'Details';

    /**
     * @param array{bin:int,type:string,description:string}|null $previous
     *        What the complaint said before an EVENT_DETAILS edit. Only the
     *        service holds this, because by the time an observer runs the
     *        complaint has already been saved.
     */
    public function changed(
        Complaint $complaint,
        ?string $oldStatus,
        string $newStatus,
        ?User $actor,
        ?string $remarks,
        string $event = self::EVENT_STATUS,
        ?array $previous = null
    ): void;
}

/**
 * Observer 1 - writes the immutable audit trail.
 * Every transition is recorded, including who made it.
 */
class ComplaintHistoryObserver implements ComplaintObserver
{
    public function changed(
        Complaint $complaint,
        ?string $oldStatus,
        string $newStatus,
        ?User $actor,
        ?string $remarks,
        string $event = self::EVENT_STATUS,
        ?array $previous = null
    ): void {
        // Both kinds of event are recorded. An edit carries the same status on
        // each side, and change_type is what tells the two apart when the
        // history is read back.
        $history = new ComplaintStatusHistory();
        $history->setDetails(
            $complaint->getKey(), $actor?->getKey(), $oldStatus, $newStatus, $remarks, $event);
        $history->save();
    }
}

/**
 * Observer 2 - raises a dashboard notification.
 *
 * A brand-new complaint alerts administrators; a complaint reaching a final
 * state notifies the reporter that their report was dealt with.
 */
class ComplaintNotificationObserver implements ComplaintObserver
{
    public function changed(
        Complaint $complaint,
        ?string $oldStatus,
        string $newStatus,
        ?User $actor,
        ?string $remarks,
        string $event = self::EVENT_STATUS,
        ?array $previous = null
    ): void {
        $binCode = $complaint->getBin()?->getBinCode() ?? 'an unknown bin';

        if ($event === self::EVENT_DETAILS) {
            // A reporter revising their own wording needs no telling. Somebody
            // else changing it is the case this notification exists for: the
            // reporter learns that their account of the issue was altered, and
            // by whom.
            if ($actor === null || $actor->getKey() === $complaint->getReporterId()) {
                return;
            }
            $notification = new ComplaintNotification();
            $notification->setDetails(
                $complaint->getKey(),
                User::ROLE_REPORTER,
                'Complaint ' . $complaint->getNumber() . ' was edited',
                $actor->getFullName() . ' changed the details of your report about ' . $binCode
                    . '. What it said before is kept on the complaint page.'
            );
            $notification->save();
            return;
        }

        if ($oldStatus === null) {
            $role  = User::ROLE_ADMIN;
            $title = 'New complaint ' . $complaint->getNumber() . ' - ' . $complaint->getType();
            $body  = 'A new ' . $complaint->getType() . ' issue was reported for ' . $binCode . '.';
        } elseif (in_array($newStatus, [Complaint::STATUS_RESOLVED, Complaint::STATUS_REJECTED], true)) {
            $role  = User::ROLE_REPORTER;
            $title = 'Complaint ' . $complaint->getNumber() . ' ' . strtolower($newStatus);
            $body  = 'Your report about ' . $binCode . ' was marked ' . $newStatus . '.'
                   . ($remarks !== null && $remarks !== '' ? ' Remarks: ' . $remarks : '');
        } else {
            $role  = User::ROLE_ADMIN;
            $title = 'Complaint ' . $complaint->getNumber() . ' moved to ' . $newStatus;
            $body  = 'Status changed from ' . (string) $oldStatus . ' to ' . $newStatus . ' for ' . $binCode . '.';
        }

        $notification = new ComplaintNotification();
        $notification->setDetails($complaint->getKey(), $role, $title, $body);
        $notification->save();
    }
}

/**
 * Observer 3 - keeps the affected bin's fill status honest.
 *
 * A newly reported overflow or full bin means the bin really is full, so the
 * Bin module's record is corrected. Once every complaint against that bin is
 * closed, and nothing else is outstanding, the bin is recorded as emptied.
 *
 * Bins under maintenance are never touched - that status is owned by the Bin
 * module and must not be overwritten by a complaint.
 */
class ComplaintBinFlagObserver implements ComplaintObserver
{
    private const FULL_TYPES = ['Overflow', 'Full Bin'];

    public function changed(
        Complaint $complaint,
        ?string $oldStatus,
        string $newStatus,
        ?User $actor,
        ?string $remarks,
        string $event = self::EVENT_STATUS,
        ?array $previous = null
    ): void {
        // Rewording a report says nothing about how full the bin is.
        if ($event !== self::EVENT_STATUS) {
            return;
        }
        $bin = $complaint->getBin();

        if ($bin === null || $bin->getFillStatus() === Bin::STATUS_MAINTENANCE) {
            return;
        }

        // A fresh overflow report means the bin needs collecting.
        if ($oldStatus === null && in_array($complaint->getType(), self::FULL_TYPES, true)) {
            if ($bin->getFillStatus() !== Bin::STATUS_FULL) {
                $bin->setFillStatus(Bin::STATUS_FULL);
                $bin->save();
            }
            return;
        }

        // Resolved, and nothing else outstanding for this bin: it has been emptied.
        if ($newStatus === Complaint::STATUS_RESOLVED
            && Complaint::countUnresolvedForBin((int) $bin->getKey()) === 0
            && $bin->getFillStatus() === Bin::STATUS_FULL) {
            $bin->setFillStatus(Bin::STATUS_EMPTY);
            $bin->save();
        }
    }
}

/**
 * Observer 4 - keeps what an edit replaced.
 *
 * The history records THAT a complaint was edited and which fields moved; it
 * cannot hold what they said, because remarks is 255 characters and a
 * description may run to 2,000. Without the wording itself a reporter has no
 * way to show that their report once said something else, which is the whole
 * reason an edit is recorded at all.
 *
 * This observer was written after the other three, and adding it changed
 * neither ComplaintService nor ComplaintStatusSubject: one class, one
 * attach() call.
 */
class ComplaintRevisionObserver implements ComplaintObserver
{
    public function changed(
        Complaint $complaint,
        ?string $oldStatus,
        string $newStatus,
        ?User $actor,
        ?string $remarks,
        string $event = self::EVENT_STATUS,
        ?array $previous = null
    ): void {
        // Only an edit replaces anything. A status change leaves the
        // complaint's own words exactly as they were.
        if ($event !== self::EVENT_DETAILS || $previous === null) {
            return;
        }

        $revision = new ComplaintRevision();
        $revision->setDetails($complaint->getKey(), $actor?->getKey(), $previous);
        $revision->save();
    }
}

/**
 * The Subject. Holds the observer list and broadcasts events to it.
 * It has no knowledge of what any observer actually does.
 */
class ComplaintStatusSubject
{
    /** @var ComplaintObserver[] */
    private array $observers = [];

    public function attach(ComplaintObserver $observer): void
    {
        $this->observers[] = $observer;
    }

    public function detach(ComplaintObserver $observer): void
    {
        $this->observers = array_values(array_filter(
            $this->observers,
            static fn(ComplaintObserver $attached): bool => $attached !== $observer
        ));
    }

    public function count(): int
    {
        return count($this->observers);
    }

    /** Broadcasts one complaint event to every attached observer, in order. */
    public function notify(
        Complaint $complaint,
        ?string $oldStatus,
        string $newStatus,
        ?User $actor,
        ?string $remarks,
        string $event = ComplaintObserver::EVENT_STATUS,
        ?array $previous = null
    ): void {
        foreach ($this->observers as $observer) {
            $observer->changed(
                $complaint, $oldStatus, $newStatus, $actor, $remarks, $event, $previous);
        }
    }
}
