<?php
/**
 * Location entity - where a bin physically sits on campus.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 * Note   : Skeleton provided so the Complaint module can display bin locations.
 */
class Location extends Model
{
    protected static string $table      = 'locations';
    protected static string $primaryKey = 'location_id';
    protected static array  $columns    = [
        'location_name', 'building_name', 'floor_no', 'description',
    ];

    public function getLocationName(): string { return (string) $this->get('location_name'); }
    public function getBuildingName(): ?string { return $this->get('building_name'); }
    public function getFloorNo(): ?string      { return $this->get('floor_no'); }
    public function getDescription(): ?string  { return $this->get('description'); }

    public function setDetails(
        string $name,
        ?string $building,
        ?string $floor,
        ?string $description
    ): void {
        $this->set('location_name', $name);
        $this->set('building_name', $building);
        $this->set('floor_no', $floor);
        $this->set('description', $description);
    }

    /** A single readable line, e.g. "Block A, Level 2 - Main Lobby". */
    public function getFullLabel(): string
    {
        $parts = array_filter([
            $this->getBuildingName(),
            $this->getFloorNo(),
            $this->getLocationName(),
        ]);

        return implode(', ', $parts);
    }

    public static function search(string $query = ''): array
    {
        if ($query === '') {
            return self::allAlphabetical();
        }

        $like = '%' . $query . '%';
        $rows = Database::getInstance()->selectAll(
            'SELECT * FROM locations'
            . ' WHERE location_name LIKE ? OR building_name LIKE ? OR floor_no LIKE ?'
            . ' ORDER BY building_name, floor_no, location_name',
            [$like, $like, $like]
        );

        return self::hydrateAll($rows);
    }

    public static function allAlphabetical(): array
    {
        return self::hydrateAll(Database::getInstance()->selectAll(
            'SELECT * FROM locations ORDER BY building_name, floor_no, location_name'
        ));
    }

    public function getBins(): array
    {
        return $this->hasMany(Bin::class, 'location_id');
    }
}
