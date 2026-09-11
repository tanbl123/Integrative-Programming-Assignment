<?php
/**
 * A user who manages the system.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 *
 * One of three subclasses of User, chosen by User::hydrate() from the role
 * stored on the row.
 *
 * An Administrator sees every complaint, which is why the search is given no
 * reporter to filter by. Seeing all of them is not the same as being free to
 * change them: ComplaintService::canEdit() still allows only a New and
 * unassigned complaint to be edited, and an edit is recorded against this
 * account either way.
 */
class Administrator extends User
{
    public function describe(): string
    {
        return 'You manage bins, users, schedules and every complaint reported.';
    }

    /** Every complaint, whoever reported it. */
    public function visibleComplaints(string $query, string $status, ?int $locationId): array
    {
        UserPermissions::require('complaint.manage');

        return Complaint::search(null, trim($query), $status, $locationId);
    }

    public function archivedComplaints(): array
    {
        UserPermissions::require('complaint.manage');

        return Complaint::archived(null);
    }

    public function maySee(Complaint $complaint): bool
    {
        return UserPermissions::can($this, 'complaint.manage');
    }

    /**
     * The administrators' shared queue of complaint notices.
     *
     * Shared on purpose: a new complaint needs picking up once, not once per
     * administrator, so marking one read clears it for all of them.
     */
    public function visibleNotifications(bool $unreadOnly = false): array
    {
        return ComplaintNotification::forAdministrators($unreadOnly);
    }

    public function maySeeNotification(ComplaintNotification $notification): bool
    {
        return $notification->getRole() === self::ROLE_ADMIN
            && UserPermissions::can($this, 'complaint.manage');
    }
}
