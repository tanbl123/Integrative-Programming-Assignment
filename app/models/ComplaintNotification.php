<?php
/**
 * A dashboard notification raised by a complaint state change.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 */
class ComplaintNotification extends Model
{
    protected static string $table      = 'complaint_notifications';
    protected static string $primaryKey = 'notification_id';
    protected static array  $columns    = [
        'complaint_id', 'recipient_role', 'title', 'body', 'is_read', 'created_at',
    ];

    public function setDetails(int $complaintId, string $role, string $title, string $body): void
    {
        $this->set('complaint_id', $complaintId);
        $this->set('recipient_role', $role);
        $this->set('title', mb_substr($title, 0, 150));
        $this->set('body', mb_substr($body, 0, 255));
        $this->set('is_read', 0);
        $this->set('created_at', ifaTimestamp());
    }

    public function getTitle(): string   { return (string) $this->get('title'); }
    public function getBody(): string    { return (string) $this->get('body'); }
    public function getRole(): string    { return (string) $this->get('recipient_role'); }
    public function isRead(): bool       { return (int) $this->get('is_read') === 1; }
    public function getCreatedAt(): string { return (string) $this->get('created_at'); }

    public function getComplaint(): ?Complaint
    {
        return $this->belongsTo(Complaint::class, 'complaint_id');
    }

    public function getComplaintId(): int { return (int) $this->get('complaint_id'); }

    /**
     * Every notification raised for administrators, newest first.
     *
     * Administrators share one queue. recipient_role names a role rather than
     * a person, so is_read is shared too: one administrator reading an item
     * marks it read for all of them. That is the right behaviour for a queue
     * of work - a complaint only needs picking up once - but it is a choice,
     * not an accident, and it is why this is not called an inbox.
     *
     * Notifications about a withdrawn complaint are deliberately kept. The
     * complaint is soft-deleted and gone from every list, so this notice is
     * the only place an administrator learns that a report they may already
     * have read has been taken back.
     */
    public static function forAdministrators(bool $unreadOnly = false): array
    {
        $sql = 'SELECT * FROM complaint_notifications WHERE recipient_role = ?'
             . ($unreadOnly ? ' AND is_read = 0' : '')
             . ' ORDER BY notification_id DESC';

        return self::hydrateAll(
            Database::getInstance()->selectAll($sql, [User::ROLE_ADMIN])
        );
    }

    /**
     * The notifications belonging to ONE reporter, newest first.
     *
     * recipient_role says 'Reporter', which is every reporter in the system -
     * so reading that column alone would show Siti the outcome of Wei Jie's
     * complaints. What identifies the person is the complaint each notice is
     * about, and complaints.reporter_id is who wrote it. The join is
     * therefore not an optimisation; it is the access control.
     */
    public static function forReporter(int $reporterId, bool $unreadOnly = false): array
    {
        $sql = 'SELECT n.* FROM complaint_notifications n'
             . ' INNER JOIN complaints c ON c.complaint_id = n.complaint_id'
             . ' WHERE n.recipient_role = ? AND c.reporter_id = ?'
             . ($unreadOnly ? ' AND n.is_read = 0' : '')
             . ' ORDER BY n.notification_id DESC';

        return self::hydrateAll(
            Database::getInstance()->selectAll($sql, [User::ROLE_REPORTER, $reporterId])
        );
    }

    /** Marks these notifications read. Ownership is decided before this runs. */
    public static function markRead(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }
        Database::getInstance()->query(
            'UPDATE complaint_notifications SET is_read = 1 WHERE notification_id IN ('
            . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        );
    }
}
