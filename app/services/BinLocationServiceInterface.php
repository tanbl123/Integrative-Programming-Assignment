<?php
/**
 * Operations exposed by the Bin & Location module.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
interface BinLocationServiceInterface
{
    /** Assert the caller may manage bins and locations. */
    public function authorizeAdministrator(): void;
    /** Assert the caller may record bin status. */
    public function authorizeCleaner(): void;
    /** Bin listing, filtered by text, fill status and location. */
    public function searchBins(string $query, string $status, ?int $locationId, bool $includeInactive): array;
    /** One bin by id, or null. */
    public function findBin(int $id): ?Bin;
    /** Register a new bin. */
    public function createBin(array $data): Bin;
    /** Save changes to a bin. */
    public function updateBin(int $id, array $data): Bin;
    /** Retire a bin from service. */
    public function deactivateBin(int $id): void;
    /** Return a retired bin to service. */
    public function reactivateBin(int $id): Bin;
    /** Record a change of fill status, with who changed it and why. */
    public function updateBinStatus(int $id, string $status, string $remarks, int $cleanerId): Bin;
    /** Location listing. */
    public function searchLocations(string $query): array;
    /** One live location by id, or null. */
    public function findLocation(int $id): ?Location;
    /** Create a location. */
    public function createLocation(array $data): Location;
    /** Save changes to a location. */
    public function updateLocation(int $id, array $data): Location;
    /** Soft-delete an empty location. */
    public function deleteLocation(int $id): void;
    /** The waste categories a bin may belong to. */
    public function categories(): array;
}
