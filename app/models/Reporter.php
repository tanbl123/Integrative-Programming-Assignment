<?php
/**
 * A user who reports waste issues.
 *
 * Author : Phang Jun Hong (2406646)
 * Module : User & Access Management
 *
 * One of three subclasses of User, chosen by User::hydrate() from the role
 * stored on the row. They share the users table and every column in it; what
 * differs is behaviour, and the behaviour that differs is what a role's
 * relationship to a complaint is.
 *
 * A Reporter's complaints are the ones they filed, and no others. That rule
 * used to be an if inside ComplaintService; it now lives with the role it
 * describes, and the service simply asks.
 *
 * Permission and identity are deliberately separate. UserPermissions, built
 * with the Decorator pattern, answers whether a role may do something at all;
 * these classes answer which records are that role's own. A Reporter with
 * complaint access still sees only their own complaints, and that is this
 * class's business rather than the decorator's.
 */
class Reporter extends User
{
    public function describe(): string
    {
        return 'You report waste issues and follow what happens to them.';
    }

    /** Only the complaints this reporter filed. */
    public function visibleComplaints(string $query, string $status, ?int $locationId): array
    {
        UserPermissions::require('complaint.view_own');

        return Complaint::search($this->getKey(), trim($query), $status, $locationId);
    }

    /** Only the reports this reporter withdrew, or that were archived after they were answered. */
    public function archivedComplaints(): array
    {
        UserPermissions::require('complaint.view_own');

        return Complaint::archived($this->getKey());
    }

    /** A reporter may open their own complaint and nobody else's. */
    public function maySee(Complaint $complaint): bool
    {
        return UserPermissions::can($this, 'complaint.view_own')
            && $complaint->getReporterId() === $this->getKey();
    }

    /**
     * Only notices about complaints this reporter wrote.
     *
     * recipient_role says 'Reporter', which is everyone; the complaint behind
     * each notice is what makes it this person's.
     */
    public function visibleNotifications(bool $unreadOnly = false): array
    {
        return ComplaintNotification::forReporter((int) $this->getKey(), $unreadOnly);
    }

    public function maySeeNotification(ComplaintNotification $notification): bool
    {
        if ($notification->getRole() !== self::ROLE_REPORTER) {
            return false;
        }
        $complaint = $notification->getComplaint();

        return $complaint !== null && $complaint->getReporterId() === $this->getKey();
    }
}
