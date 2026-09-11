<?php
/**
 * Waste issue reported against a campus bin.
 * Author : Tan Boon Leong (2402865)
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

    /**
     * The complaint's number as every screen and message shows it,
     * for example CMP-2026-0008.
     *
     * complaint_id is AUTO_INCREMENT, so MySQL has already guaranteed the
     * number is unique within the table and never reissued. This method only
     * dresses that key for display, deriving the year from created_at and
     * padding the key to four digits. Nothing is stored, so the code and the
     * key cannot drift apart and no migration stands behind it.
     *
     * The padding is the point rather than decoration. A Reporter sees only
     * their own complaints, so the numbers they see are never consecutive -
     * 1, 4, 8 with the rest belonging to other people. A bare 1, 4, 8 reads
     * as a list that has lost rows; CMP-2026-0001, CMP-2026-0004 and
     * CMP-2026-0008 read as what they are, the complaint's own reference,
     * which no one expects to run consecutively. The same code serves the
     * Administrator, so both roles quote the same thing.
     *
     * The database keeps the integer key, so every foreign key that points at
     * a complaint - attachments, status history, notifications and the
     * Scheduling module's source_complaint_id - is untouched.
     *
     * Note that this is presentation, not protection. Another reporter cannot
     * reach a complaint by guessing a number: ComplaintService::findVisible()
     * refuses it on authorisation, which is what actually prevents
     * enumeration.
     */
    public function getNumber(): string
    {
        $id = $this->getKey();
        if ($id === null) {
            return 'CMP-NEW';
        }
        $created = (string) $this->get('created_at');
        $year = $created !== '' ? substr($created, 0, 4) : date('Y');

        return sprintf('CMP-%s-%04d', $year, $id);
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
            $clause = 'c.description LIKE ? OR c.complaint_type LIKE ? OR b.bin_code LIKE ?';
            array_push($params, $like, $like, $like);

            // A code such as CMP-2026-0007, or a bare 7 or #7, should find the
            // complaint it names. The code is derived from the key rather than
            // stored, so the key is recovered from it and matched directly.
            if (preg_match('/^\s*(?:CMP-\d{4}-|#)?0*(\d+)\s*$/i', $query, $found) === 1) {
                $clause .= ' OR c.complaint_id = ?';
                $params[] = (int) $found[1];
            }
            $sql .= ' AND (' . $clause . ')';
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

    /**
     * Open complaints for every bin, keyed by bin id.
     *
     * Used by the submission form to warn a reporter that an issue has
     * already been raised for the bin they picked, which is the cheapest
     * point at which to stop a duplicate being created at all.
     *
     * @return array<int, list<array{id:int,type:string,status:string,created_at:string}>>
     */
    public static function openSummaryByBin(?int $excludeId = null): array
    {
        // When a reporter edits their own complaint it must not be listed back
        // to them as an existing report of the same issue.
        $sql = 'SELECT complaint_id, bin_id, complaint_type, complaint_status, created_at'
             . ' FROM complaints'
             . ' WHERE deleted_at IS NULL AND complaint_status IN (?, ?)';
        $params = [self::STATUS_NEW, self::STATUS_ASSIGNED];

        if ($excludeId !== null) {
            $sql .= ' AND complaint_id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' ORDER BY complaint_id ASC';

        $rows = Database::getInstance()->selectAll($sql, $params);

        $byBin = [];
        foreach ($rows as $row) {
            $byBin[(int) $row['bin_id']][] = [
                'id'         => (int) $row['complaint_id'],
                'type'       => (string) $row['complaint_type'],
                'status'     => (string) $row['complaint_status'],
                'created_at' => (string) $row['created_at'],
            ];
        }
        return $byBin;
    }

    /**
     * Sets of open complaints that report the same issue on the same bin.
     *
     * When several people report one overflowing bin, the administrator is
     * looking at one real problem, not five. Grouping by bin and issue type
     * lets the list show it that way, and lets the duplicates be closed in a
     * single action while each reporter still receives their own outcome.
     *
     * Only groups of two or more are returned; a lone complaint is not a
     * duplicate of anything.
     *
     * @return list<array{bin:?Bin,type:string,keepId:int,complaints:list<Complaint>}>
     */
    public static function duplicateGroups(): array
    {
        $rows = Database::getInstance()->selectAll(
            'SELECT bin_id, complaint_type, COUNT(*) AS total, MIN(complaint_id) AS keep_id'
            . ' FROM complaints'
            . ' WHERE deleted_at IS NULL AND complaint_status IN (?, ?)'
            . ' GROUP BY bin_id, complaint_type'
            . ' HAVING COUNT(*) > 1'
            . ' ORDER BY total DESC, bin_id ASC',
            [self::STATUS_NEW, self::STATUS_ASSIGNED]
        );

        $groups = [];
        foreach ($rows as $row) {
            $groups[] = [
                'bin'        => Bin::find((int) $row['bin_id']),
                'type'       => (string) $row['complaint_type'],
                'keepId'     => (int) $row['keep_id'],
                'complaints' => self::openForBinAndType((int) $row['bin_id'], (string) $row['complaint_type']),
            ];
        }
        return $groups;
    }

    /**
     * Every open complaint of one issue type against one bin, oldest first.
     *
     * @return list<Complaint>
     */
    public static function openForBinAndType(int $binId, string $type): array
    {
        $rows = Database::getInstance()->selectAll(
            'SELECT * FROM complaints'
            . ' WHERE deleted_at IS NULL AND bin_id = ? AND complaint_type = ?'
            . ' AND complaint_status IN (?, ?)'
            . ' ORDER BY complaint_id ASC',
            [$binId, $type, self::STATUS_NEW, self::STATUS_ASSIGNED]
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
