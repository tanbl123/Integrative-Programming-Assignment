<?php
/**
 * Collection plan created by an administrator.
 * Author : Ng Zi Zhang (2406898)
 * Module : Collection Scheduling & Assignment
 */
class CollectionSchedule extends Model
{
    protected static string $table = 'collection_schedules';
    protected static string $primaryKey = 'schedule_id';
    protected static array $columns = [
        'admin_id', 'schedule_date', 'time_slot', 'strategy', 'schedule_status',
        'notes', 'created_at', 'updated_at', 'deleted_at',
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
    public function isDeleted(): bool { return $this->get('deleted_at') !== null; }
    /** Soft delete - the schedule and its assignments stay as a record of the round. */
    public function delete(): bool
    {
        if ($this->getKey() === null) { return false; }
        $this->set('deleted_at', ifaTimestamp());
        $this->save();
        return true;
    }
    /**
     * A stored time slot split back into a start and an end.
     *
     * Author : Tan Boon Leong (2402865)
     *
     * The column is free text and has held both "09:00-12:00" and the same
     * hours with an en dash, so both separators are accepted. Anything that is
     * not a pair of 24 hour times comes back empty rather than half-parsed,
     * which leaves the two inputs blank and makes the reader choose again
     * instead of silently keeping a value nobody can see.
     *
     * @return array{from:string,to:string}
     */
    public static function splitTimeSlot(?string $slot): array
    {
        $matched = preg_match(
            '/^\s*([01][0-9]|2[0-3]:?[0-5][0-9]|[01][0-9]:[0-5][0-9])\s*[-\x{2013}\x{2014}]\s*(.+?)\s*$/u',
            (string) $slot,
            $parts
        );

        $isTime = static fn(string $value): bool
            => (bool) preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value);

        if ($matched !== 1 || !$isTime($parts[1]) || !$isTime($parts[2])) {
            return ['from' => '', 'to' => ''];
        }

        return ['from' => $parts[1], 'to' => $parts[2]];
    }

    public function getDate(): string { return (string) $this->get('schedule_date'); }
    public function getTimeSlot(): string { return (string) $this->get('time_slot'); }
    public function getStrategy(): string { return (string) $this->get('strategy'); }
    public function getStatus(): string { return (string) $this->get('schedule_status'); }
    public function getNotes(): ?string { return $this->get('notes'); }
    public function getAdministrator(): ?User { return $this->belongsTo(User::class, 'admin_id'); }
    public function getAssignments(): array { return $this->hasMany(CollectionAssignment::class, 'schedule_id'); }

    /** Schedule listing, filtered by date and status. */
    public static function search(string $date = '', string $status = ''): array
    {
        $sql = 'SELECT * FROM collection_schedules WHERE deleted_at IS NULL';
        $params = [];
        if ($date !== '') { $sql .= ' AND schedule_date = ?'; $params[] = $date; }
        if (in_array($status, ['Planned', 'Completed', 'Cancelled'], true)) { $sql .= ' AND schedule_status = ?'; $params[] = $status; }
        $sql .= ' ORDER BY schedule_date DESC, schedule_id DESC';
        return self::hydrateAll(Database::getInstance()->selectAll($sql, $params));
    }
}
