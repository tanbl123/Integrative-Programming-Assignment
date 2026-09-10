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
 */

/** Contract every complaint observer must satisfy. */
interface ComplaintObserver
{
    public function changed(
        Complaint $complaint,
        ?string $oldStatus,
        string $newStatus,
        ?User $actor,
        ?string $remarks
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
        ?string $remarks
    ): void {
        $history = new ComplaintStatusHistory();
        $history->setDetails($complaint->getKey(), $actor?->getKey(), $oldStatus, $newStatus, $remarks);
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
        ?string $remarks
    ): void {
        $binCode = $complaint->getBin()?->getBinCode() ?? 'an unknown bin';

        if ($oldStatus === null) {
            $role  = User::ROLE_ADMIN;
            $title = 'New complaint ' . $complaint->getReference() . ' - ' . $complaint->getType();
            $body  = 'A new ' . $complaint->getType() . ' issue was reported for ' . $binCode . '.';
        } elseif (in_array($newStatus, [Complaint::STATUS_RESOLVED, Complaint::STATUS_REJECTED], true)) {
            $role  = User::ROLE_REPORTER;
            $title = 'Complaint ' . $complaint->getReference() . ' ' . strtolower($newStatus);
            $body  = 'Your report about ' . $binCode . ' was marked ' . $newStatus . '.'
                   . ($remarks !== null && $remarks !== '' ? ' Remarks: ' . $remarks : '');
        } else {
            $role  = User::ROLE_ADMIN;
            $title = 'Complaint ' . $complaint->getReference() . ' moved to ' . $newStatus;
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
        ?string $remarks
    ): void {
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
        ?string $remarks
    ): void {
        foreach ($this->observers as $observer) {
            $observer->changed($complaint, $oldStatus, $newStatus, $actor, $remarks);
        }
    }
}
