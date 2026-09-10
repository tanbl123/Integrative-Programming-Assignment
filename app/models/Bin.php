<?php
/**
 * Bin entity - a waste bin on campus.
 * Module : Bin & Location Management
 * Note   : Skeleton provided because the Complaint module depends on it.
 *          A complaint is always raised against a specific bin.
 * Relationships are exposed as OBJECT REFERENCES, per the assignment brief:
 * getLocation() hands back a Location object, not a location_id integer.
 */
class Bin extends Model
{
    protected static string $table      = 'bins';
    protected static string $primaryKey = 'bin_id';
    protected static array  $columns    = [
        'bin_code', 'location_id', 'category_id',
        'fill_status', 'capacity_litre', 'is_active', 'last_updated',
    ];

    public const STATUS_EMPTY       = 'Empty';
    public const STATUS_HALF        = 'Half';
    public const STATUS_FULL        = 'Full';
    public const STATUS_MAINTENANCE = 'Under Maintenance';

    public function getBinCode(): string     { return (string) $this->get('bin_code'); }
    public function getFillStatus(): string  { return (string) $this->get('fill_status'); }
    public function getLastUpdated(): string { return (string) $this->get('last_updated'); }
    public function getLocationId(): int     { return (int) $this->get('location_id'); }
    public function getCategoryId(): int     { return (int) $this->get('category_id'); }
    public function getCapacityLitre(): ?int
    {
        $capacity = $this->get('capacity_litre');
        return $capacity === null ? null : (int) $capacity;
    }

    public function isActive(): bool { return (int) $this->get('is_active') === 1; }

    public static function statuses(): array
    {
        return [
            self::STATUS_EMPTY,
            self::STATUS_HALF,
            self::STATUS_FULL,
            self::STATUS_MAINTENANCE,
        ];
    }

    public function setDetails(
        string $code,
        int $locationId,
        int $categoryId,
        ?int $capacityLitre,
        bool $active = true
    ): void {
        $this->set('bin_code', $code);
        $this->set('location_id', $locationId);
        $this->set('category_id', $categoryId);
        $this->set('capacity_litre', $capacityLitre);
        $this->set('is_active', $active ? 1 : 0);
        $this->set('last_updated', ifaTimestamp());
    }

    public function setFillStatus(string $status): void
    {
        $this->set('fill_status', $status);
        $this->set('last_updated', ifaTimestamp());
    }

    public function deactivate(): void
    {
        $this->set('is_active', 0);
        $this->set('last_updated', ifaTimestamp());
    }

    public function reactivate(): void
    {
        $this->set('is_active', 1);
        $this->set('last_updated', ifaTimestamp());
    }
    
    public function hasOpenAssignments(): bool {
        foreach ($this->hasMany(CollectionAssignment::class, 'bin_id') as $assignment) {
            if ($assignment->getStatus() === 'Assigned') {
                return true;
            }
        }

        return false;
    }

    public function hasOpenWork(): bool {
        if ($this->hasOpenAssignments()) {
            return true;
        }

        foreach ($this->hasMany(Complaint::class, 'bin_id') as $complaint) {
            if (!$complaint->isDeleted() && in_array($complaint->getStatus(), ['New', 'Assigned'], true)) {
                return true;
            }
        }

        return false;
    }

    /** Object reference to the Location this bin sits in. */
    public function getLocation(): ?Location
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /** Object reference to the waste category this bin accepts. */
    public function getCategory(): ?WasteCategory
    {
        return $this->belongsTo(WasteCategory::class, 'category_id');
    }

    /** True when this bin needs collecting. */
    public function needsCollection(): bool
    {
        return $this->getFillStatus() === self::STATUS_FULL;
    }

    /** Every bin currently marked Full - consumed by the Scheduling module. */
    public static function findFullBins(): array
    {
        return self::search('', self::STATUS_FULL);
    }

    /** Only bins that are still in service, for dropdown lists. */
    public static function findActive(): array
    {
        return self::where('is_active', 1);
    }

    public static function findByCode(string $code): ?Bin
    {
        $row = Database::getInstance()->selectOne(
            'SELECT * FROM bins WHERE bin_code = ? LIMIT 1',
            [$code]
        );

        return $row === null ? null : self::hydrate($row);
    }

    public static function search(
        string $query = '',
        string $status = '',
        ?int $locationId = null,
        bool $includeInactive = false
    ): array {
        $sql = 'SELECT DISTINCT b.* FROM bins b'
             . ' INNER JOIN locations l ON l.location_id = b.location_id'
             . ' WHERE 1 = 1';
        $params = [];

        if (!$includeInactive) {
            $sql .= ' AND b.is_active = 1';
        }

        if ($query !== '') {
            $sql .= ' AND (b.bin_code LIKE ? OR l.location_name LIKE ?'
                  . ' OR l.building_name LIKE ?)';
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like);
        }

        if ($status !== '') {
            $sql .= ' AND b.fill_status = ?';
            $params[] = $status;
        }

        if ($locationId !== null) {
            $sql .= ' AND b.location_id = ?';
            $params[] = $locationId;
        }

        $sql .= ' ORDER BY b.bin_code ASC';

        return self::hydrateAll(Database::getInstance()->selectAll($sql, $params));
    }

    public function getStatusUpdates(): array
    {
        return $this->hasMany(BinStatusUpdate::class, 'bin_id');
    }
}
