<?php
/**
 * One cleaner/bin task within a schedule.
 * Author : Ng Zi Zhang (2406898)
 * Module : Collection Scheduling & Assignment
 */
class CollectionAssignment extends Model
{
    protected static string $table = 'collection_assignments';
    protected static string $primaryKey = 'assignment_id';
    protected static array $columns = [
        'schedule_id', 'cleaner_id', 'bin_id', 'source_complaint_id', 'priority',
        'assignment_status', 'reason', 'completed_at', 'completion_notes',
    ];

    public function setDetails(int $scheduleId, int $cleanerId, int $binId, ?int $complaintId, string $priority, string $reason): void
    {
        $this->set('schedule_id', $scheduleId);
        $this->set('cleaner_id', $cleanerId);
        $this->set('bin_id', $binId);
        $this->set('source_complaint_id', $complaintId);
        $this->set('priority', $priority);
        $this->set('reason', $reason);
        if ($this->getKey() === null) { $this->set('assignment_status', 'Assigned'); }
    }

    /** Marks one bin collected, with whatever the cleaner wrote. */
    public function complete(?string $notes): void
    {
        $this->set('assignment_status', 'Completed');
        $this->set('completed_at', ifaTimestamp());
        $this->set('completion_notes', $notes);
    }

    /**
     * Marks one bin not collected, with the reason - what cancelling a round does to
     * each of its assignments.
     */
    public function skip(string $reason): void { $this->set('assignment_status', 'Skipped'); $this->set('completion_notes', $reason); }
    public function getCleanerId(): int { return (int) $this->get('cleaner_id'); }
    public function getStatus(): string { return (string) $this->get('assignment_status'); }
    public function getPriority(): string { return (string) $this->get('priority'); }
    public function getReason(): ?string { return $this->get('reason'); }
    public function getCompletedAt(): ?string { return $this->get('completed_at'); }
    public function getCompletionNotes(): ?string { return $this->get('completion_notes'); }
    public function getSchedule(): ?CollectionSchedule { return $this->belongsTo(CollectionSchedule::class, 'schedule_id'); }
    public function getCleaner(): ?User { return $this->belongsTo(User::class, 'cleaner_id'); }
    public function getBin(): ?Bin { return $this->belongsTo(Bin::class, 'bin_id'); }
    public function getSourceComplaint(): ?Complaint { return $this->belongsTo(Complaint::class, 'source_complaint_id'); }

 /** A cleaner’s own assignments, optionally filtered by status. */
 public static function forCleaner(int $cleanerId, string $status = '', string $routeView = ''): array {
        $sql = "
        SELECT a.* 
        FROM collection_assignments a 
        INNER JOIN collection_schedules s 
            ON s.schedule_id = a.schedule_id 
        WHERE a.cleaner_id = ? 
        AND s.schedule_status <> ? 
        AND s.deleted_at IS NULL
    ";

        $params = [$cleanerId, 'Cancelled'];

        if (in_array($status, ['Assigned', 'Completed', 'Skipped'], true)) {
            $sql .= ' AND a.assignment_status = ?';
            $params[] = $status;
        }

        if ($routeView === 'today') {
            $sql .= ' AND s.schedule_date = CURDATE()';
        }

        if ($routeView === 'week') {
            $sql .= ' AND YEARWEEK(s.schedule_date, 1) = YEARWEEK(CURDATE(), 1)';
        }

        $sql .= ' ORDER BY s.schedule_date DESC, a.assignment_id DESC';

        return self::hydrateAll(Database::getInstance()->selectAll($sql, $params));
    }

    /**
     * How many assignments are still open, so a round knows when it is finished and
     * whether it may be deleted.
     */
    public static function remainingForSchedule(int $scheduleId): int
    {
        $row = Database::getInstance()->selectOne(
            'SELECT COUNT(*) AS total FROM collection_assignments WHERE schedule_id = ? AND assignment_status = ?',
            [$scheduleId, 'Assigned']
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Open collection work for one bin, ordered by the next planned visit.
     * Used by Scheduling's REST endpoint; callers outside this module do not
     * need to read the scheduling tables themselves.
     *
     * @return CollectionAssignment[]
     */
    public static function openForBin(int $binId): array
    {
        $rows = Database::getInstance()->selectAll(
            'SELECT a.*
             FROM collection_assignments a
             INNER JOIN collection_schedules s ON s.schedule_id = a.schedule_id
             WHERE a.bin_id = ?
               AND a.assignment_status = ?
               AND s.schedule_status = ?
               AND s.deleted_at IS NULL
             ORDER BY s.schedule_date ASC, s.time_slot ASC, a.assignment_id ASC',
            [$binId, 'Assigned', 'Planned']
        );
        return self::hydrateAll($rows);
    }
}
