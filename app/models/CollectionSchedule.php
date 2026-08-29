<?php
/** Collection plan created by an administrator. Author: Ong Kar Heng (2408830). */
class CollectionSchedule extends Model
{
    protected static string $table = 'collection_schedules';
    protected static string $primaryKey = 'schedule_id';
    protected static array $columns = [
        'admin_id', 'schedule_date', 'time_slot', 'strategy', 'schedule_status',
        'notes', 'created_at', 'updated_at',
    ];

    public function setDetails(int $adminId, string $date, string $timeSlot, string $strategy, ?string $notes): void
    {
        $this->set('admin_id', $adminId);
        $this->set('schedule_date', $date);
        $this->set('time_slot', $timeSlot);
        $this->set('strategy', $strategy);
        $this->set('notes', $notes);
        if ($this->getKey() === null) {
            $this->set('schedule_status', 'Planned');
        }
        $this->set('updated_at', ifaTimestamp());
    }

    public function setStatus(string $status): void { $this->set('schedule_status', $status); $this->set('updated_at', ifaTimestamp()); }
    public function getDate(): string { return (string) $this->get('schedule_date'); }
    public function getTimeSlot(): string { return (string) $this->get('time_slot'); }
    public function getStrategy(): string { return (string) $this->get('strategy'); }
    public function getStatus(): string { return (string) $this->get('schedule_status'); }
    public function getNotes(): ?string { return $this->get('notes'); }
    public function getAdministrator(): ?User { return $this->belongsTo(User::class, 'admin_id'); }
    public function getAssignments(): array { return $this->hasMany(CollectionAssignment::class, 'schedule_id'); }

    public static function search(string $date = '', string $status = ''): array
    {
        $sql = 'SELECT * FROM collection_schedules WHERE 1 = 1';
        $params = [];
        if ($date !== '') { $sql .= ' AND schedule_date = ?'; $params[] = $date; }
        if (in_array($status, ['Planned', 'Completed', 'Cancelled'], true)) { $sql .= ' AND schedule_status = ?'; $params[] = $status; }
        $sql .= ' ORDER BY schedule_date DESC, schedule_id DESC';
        return self::hydrateAll(Database::getInstance()->selectAll($sql, $params));
    }
}
