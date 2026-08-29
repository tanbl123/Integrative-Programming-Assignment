<?php
/**
 * Observer pattern for complaint state transitions.
 * Author : Ong Kar Heng (2408830)
 * Module : Complaint / Report Management - Observer design pattern
 */
interface ComplaintObserver
{
    public function changed(Complaint $complaint, ?string $oldStatus, string $newStatus, ?User $actor, ?string $remarks): void;
}

class ComplaintHistoryObserver implements ComplaintObserver
{
    public function changed(Complaint $complaint, ?string $oldStatus, string $newStatus, ?User $actor, ?string $remarks): void
    {
        $history = new ComplaintStatusHistory();
        $history->setDetails($complaint->getKey(), $actor?->getKey(), $oldStatus, $newStatus, $remarks);
        $history->save();
    }
}

class ComplaintStatusSubject
{
    private array $observers = [];

    public function attach(ComplaintObserver $observer): void
    {
        $this->observers[] = $observer;
    }

    public function notify(Complaint $complaint, ?string $oldStatus, string $newStatus, ?User $actor, ?string $remarks): void
    {
        foreach ($this->observers as $observer) {
            $observer->changed($complaint, $oldStatus, $newStatus, $actor, $remarks);
        }
    }
}
