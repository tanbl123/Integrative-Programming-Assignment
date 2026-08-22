<?php
/**
 * Bin entity - a waste bin on campus.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 * Note   : Skeleton provided because the Complaint module depends on it.
 *          A complaint is always raised against a specific bin.
 *
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
    public function getCapacityLitre(): ?int
    {
        $capacity = $this->get('capacity_litre');
        return $capacity === null ? null : (int) $capacity;
    }

    public function isActive(): bool { return (int) $this->get('is_active') === 1; }

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
        return self::where('fill_status', self::STATUS_FULL);
    }

    /** Only bins that are still in service, for dropdown lists. */
    public static function findActive(): array
    {
        return self::where('is_active', 1);
    }
}
