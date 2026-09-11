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

    /** The complaint was withdrawn by its reporter, or deleted by an admin. */
    public const EVENT_WITHDRAWN = 'Withdrawn';

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
        // All three kinds of event are recorded. An edit and a withdrawal both
        // carry the same status on each side, and change_type is what tells
        // them apart from a transition when the history is read back.
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

        if ($event === self::EVENT_WITHDRAWN) {
            // Administrators are told, because a report they may already have
            // read and planned around has just left their list. The reporter
            // is not: either they withdrew it themselves, or an Administrator
            // removed a complaint that had already been resolved or rejected,
            // which notified them at the time.
            $notification = new ComplaintNotification();
            $notification->setDetails(
                $complaint->getKey(),
                User::ROLE_ADMIN,
                'Complaint ' . $complaint->getNumber() . ' withdrawn',
                ($actor?->getFullName() ?? 'Someone') . ' withdrew the ' . $complaint->getType()
                    . ' report about ' . $binCode . '.'
            );
            $notification->save();
            return;
        }

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
        } elseif ($newStatus === Complaint::STATUS_NEW) {
            // Told they were being dealt with, and now they are not. Leaving
            // this one out would make the earlier message the last thing they
            // ever heard, and it would no longer be true.
            $role  = User::ROLE_REPORTER;
            $title = 'Complaint ' . $complaint->getNumber() . ' is waiting again';
            $body  = 'The collection arranged for ' . $binCode . ' is no longer going ahead, '
                   . 'so your report is waiting to be dealt with again.'
                   . ($remarks !== null && $remarks !== '' ? ' ' . $remarks : '');
        } elseif ($newStatus === Complaint::STATUS_ASSIGNED) {
            // The reporter, not the administrators. Whoever moved it here
            // knows they did; the person waiting to hear does not, and this
            // is the first sign their report has been acted on at all. It
            // arrives whether an administrator triaged it by hand or the
            // Scheduling module raised a collection against it.
            $role  = User::ROLE_REPORTER;
            $title = 'Complaint ' . $complaint->getNumber() . ' is being dealt with';
            $body  = 'Your report about ' . $binCode . ' has been accepted and work is being arranged.'
                   . ($remarks !== null && $remarks !== '' ? ' ' . $remarks : '');
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
 * Observer 3 - tells the Bin module what a complaint reported.
 *
 * A complaint cannot establish that a bin is full. Nobody measured it: a
 * reporter looked at a bin and said so, and they may be mistaken. This
 * observer therefore records a CLAIM, attributed to the complaint that made
 * it, rather than asserting a fact.
 *
 * That distinction is the whole design here, and it was got wrong twice.
 *
 * The first attempt decided which complaints meant "full" by comparing the
 * issue type against the literal strings 'Overflow' and 'Full Bin'. That was
 * sound while the types were a fixed ENUM and stopped being sound the moment
 * an Administrator could maintain them - a rename cascades into every
 * complaint and the comparison then matches nothing, silently. Which types
 * carry the meaning is now marks_bin_full, a column a rename cannot reach.
 *
 * The second was worse and is what this class now fixes. It wrote
 * bins.fill_status directly - $bin->setFillStatus(); $bin->save() - which
 * reaches into another module's table behind that module's back. The Bin
 * module keeps every status change in bin_status_updates, with who made it
 * and why; a complaint-driven change appeared in none of them, so a bin
 * could turn Full with nothing anywhere to say what did it. It also skipped
 * that service's own checks, so a complaint could mark an inactive bin Full.
 *
 * Going through updateBinStatus() means the claim arrives the same way a
 * cleaner's does: validated, and recorded with its reason. An unreliable
 * claim is then merely wrong and visible, and anyone can correct it from the
 * bin's own screen - rather than silently corrupting a record whose owner
 * never heard about the change.
 *
 * Note that a complaint is not the only route from a report to a cleaner,
 * and not the important one. ComplaintPrioritySelectionStrategy schedules
 * straight off Complaint::unresolved(), needing no type, no flag and no
 * judgement from anybody. That path is exact. This one exists so that a bin
 * a reporter says is full looks full on the bin board too.
 *
 * A withdrawal is treated as a closure, not as a separate case: the claim
 * that marked the bin Full has been taken back, so the bin is released on
 * exactly the same condition as a resolution - nothing else open against it.
 *
 * Bins under maintenance are never touched - that status is owned by the Bin
 * module and must not be overwritten by a complaint.
 */
class ComplaintBinFlagObserver implements ComplaintObserver
{
    /**
     * The Bin module's own service, so its rules and its audit trail apply.
     * Injected so a test can watch what this observer asks for.
     */
    public function __construct(private ?BinLocationServiceInterface $bins = null)
    {
    }


    public function changed(
        Complaint $complaint,
        ?string $oldStatus,
        string $newStatus,
        ?User $actor,
        ?string $remarks,
        string $event = self::EVENT_STATUS,
        ?array $previous = null
    ): void {
        // Rewording a report says nothing about how full the bin is. A
        // withdrawal does: the claim that marked the bin Full has been taken
        // back, and is handled below alongside a resolution.
        if ($event !== self::EVENT_STATUS && $event !== self::EVENT_WITHDRAWN) {
            return;
        }
        $bin = $complaint->getBin();

        // An inactive bin is checked here as well as inside the Bin service,
        // because there it is a ValidationException - correct for a cleaner
        // filling in a form, and no way to answer a reporter who has just
        // submitted an unrelated complaint.
        if ($bin === null
            || !$bin->isActive()
            || $bin->getFillStatus() === Bin::STATUS_MAINTENANCE) {
            return;
        }

        $bins = $this->bins ?? new BinLocationService();

        // A fresh report of a type that means "full". The type says so, this
        // observer does not decide; and the Bin module records who said it.
        if ($oldStatus === null && ComplaintType::marksBinFullByName($complaint->getType())) {
            if ($bin->getFillStatus() !== Bin::STATUS_FULL) {
                $bins->updateBinStatus(
                    (int) $bin->getKey(),
                    Bin::STATUS_FULL,
                    'Reported full by complaint ' . $complaint->getNumber() . '.',
                    $complaint->getReporterId()
                );
            }
            return;
        }

        // The bin is released when the last thing keeping it Full goes away,
        // whether that is the complaint being resolved or being withdrawn.
        // A withdrawal is the case this observer used to miss entirely: the
        // complaint vanished, nothing ran, and the bin stayed Full with
        // nothing open to account for it.
        $closed = $event === self::EVENT_WITHDRAWN
            || $newStatus === Complaint::STATUS_RESOLVED;

        if ($closed
            && Complaint::countUnresolvedForBin((int) $bin->getKey()) === 0
            && $bin->getFillStatus() === Bin::STATUS_FULL) {
            $bins->updateBinStatus(
                (int) $bin->getKey(),
                Bin::STATUS_EMPTY,
                $event === self::EVENT_WITHDRAWN
                    ? 'Complaint ' . $complaint->getNumber() . ' withdrawn; no reports left open.'
                    : 'Complaint ' . $complaint->getNumber() . ' resolved; no reports left open.',
                $actor?->getKey() ?? $complaint->getReporterId()
            );
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
