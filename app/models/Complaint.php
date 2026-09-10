<?php
/**
 * Waste issue reported against a campus bin.
 * Module : Complaint / Report Management
 */
class Complaint extends Model
{
    protected static string $table = 'complaints';
    protected static string $primaryKey = 'complaint_id';
    protected static array $columns = [
        'reporter_id', 'bin_id', 'complaint_type', 'description',
        'complaint_status', 'created_at', 'updated_at', 'deleted_at',
    ];

    public const STATUS_NEW = 'New';
    public const STATUS_ASSIGNED = 'Assigned';
    public const STATUS_RESOLVED = 'Resolved';
    public const STATUS_REJECTED = 'Rejected';

    public static function statuses(): array
    {
        return [self::STATUS_NEW, self::STATUS_ASSIGNED, self::STATUS_RESOLVED, self::STATUS_REJECTED];
    }

    public function isDeleted(): bool { return $this->get('deleted_at') !== null; }
    public function delete(): bool
    {
        if ($this->getKey() === null) { return false; }
        $this->set('deleted_at', ifaTimestamp());
        $this->save();
        return true;
    }

    public function hasOpenAssignments(): bool
    {
        foreach (CollectionAssignment::where('source_complaint_id', $this->getKey()) as $assignment) {
            if ($assignment->getStatus() === 'Assigned') { return true; }
        }
        return false;
    }

    /**
     * Return only the valid next steps in the agreed complaint lifecycle.
     * Resolved and Rejected are final states.
     */
    public static function allowedNextStatuses(string $current): array
    {
        return match ($current) {
            self::STATUS_NEW => [self::STATUS_ASSIGNED, self::STATUS_REJECTED],
            self::STATUS_ASSIGNED => [self::STATUS_RESOLVED, self::STATUS_REJECTED],
            default => [],
        };
    }

    public static function types(): array
    {
        return ['Full Bin', 'Overflow', 'Damaged Bin', 'Dirty Area', 'Wrong Waste Disposal', 'Other'];
    }

    public function setDetails(int $reporterId, int $binId, string $type, string $description): void
    {
        $this->set('reporter_id', $reporterId);
        $this->set('bin_id', $binId);
        $this->set('complaint_type', $type);
        $this->set('description', $description);
        $this->set('complaint_status', self::STATUS_NEW);
        $this->set('updated_at', ifaTimestamp());
    }

    public function setStatus(string $status): void
    {
        $this->set('complaint_status', $status);
        $this->set('updated_at', ifaTimestamp());
    }

    public function getReporterId(): int { return (int) $this->get('reporter_id'); }
    public function getType(): string { return (string) $this->get('complaint_type'); }
    public function getDescription(): string { return (string) $this->get('description'); }
    public function getStatus(): string { return (string) $this->get('complaint_status'); }
    public function getCreatedAt(): string { return (string) $this->get('created_at'); }
    public function getUpdatedAt(): string { return (string) $this->get('updated_at'); }
    public function getReporter(): ?User { return $this->belongsTo(User::class, 'reporter_id'); }
    public function getBin(): ?Bin { return $this->belongsTo(Bin::class, 'bin_id'); }
    public function getAttachments(): array { return $this->hasMany(ComplaintAttachment::class, 'complaint_id'); }
    public function getHistory(): array { return $this->hasMany(ComplaintStatusHistory::class, 'complaint_id'); }

    public static function search(
        ?int $reporterId,
        string $query,
        string $status,
        ?int $locationId
    ): array {
        $sql = 'SELECT DISTINCT c.* FROM complaints c'
             . ' INNER JOIN bins b ON b.bin_id = c.bin_id'
             . ' INNER JOIN locations l ON l.location_id = b.location_id'
             . ' WHERE c.deleted_at IS NULL';
        $params = [];
        if ($reporterId !== null) {
            $sql .= ' AND c.reporter_id = ?';
            $params[] = $reporterId;
        }
        if ($query !== '') {
            $like = '%' . $query . '%';
            $sql .= ' AND (c.description LIKE ? OR c.complaint_type LIKE ? OR b.bin_code LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if (in_array($status, self::statuses(), true)) {
            $sql .= ' AND c.complaint_status = ?';
            $params[] = $status;
        }
        if ($locationId !== null) {
            $sql .= ' AND b.location_id = ?';
            $params[] = $locationId;
        }
        $sql .= ' ORDER BY c.complaint_id DESC';
        return self::hydrateAll(Database::getInstance()->selectAll($sql, $params));
    }

    public static function unresolved(): array
    {
        $rows = Database::getInstance()->selectAll(
            'SELECT * FROM complaints WHERE deleted_at IS NULL AND complaint_status IN (?, ?) ORDER BY created_at ASC',
            [self::STATUS_NEW, self::STATUS_ASSIGNED]
        );
        return self::hydrateAll($rows);
    }

    public static function countUnresolvedForBin(int $binId): int
    {
        $row = Database::getInstance()->selectOne(
            'SELECT COUNT(*) AS total FROM complaints'
            . ' WHERE deleted_at IS NULL AND bin_id = ? AND complaint_status IN (?, ?)',
            [$binId, self::STATUS_NEW, self::STATUS_ASSIGNED]
        );
        return (int) ($row['total'] ?? 0);
    }
}
