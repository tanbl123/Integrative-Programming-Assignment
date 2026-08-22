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
}
