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

    /** Unread notifications for a role, newest first. */
    public static function unreadFor(string $role): array
    {
        $rows = Database::getInstance()->selectAll(
            'SELECT * FROM complaint_notifications'
            . ' WHERE recipient_role = ? AND is_read = 0'
            . ' ORDER BY notification_id DESC',
            [$role]
        );
        return self::hydrateAll($rows);
    }
}
