<?php
/**
 * Operations exposed by the Bin & Location module.
 *
 * Module : Bin & Location Management
 */
interface BinLocationServiceInterface
{
    public function authorizeAdministrator(): void;
    public function authorizeCleaner(): void;
    public function searchBins(string $query, string $status, ?int $locationId, bool $includeInactive): array;
    public function findBin(int $id): ?Bin;
    public function createBin(array $data): Bin;
    public function updateBin(int $id, array $data): Bin;
    public function deactivateBin(int $id): void;
    public function reactivateBin(int $id): Bin;
    public function updateBinStatus(int $id, string $status, string $remarks, int $cleanerId): Bin;
    public function searchLocations(string $query): array;
    public function findLocation(int $id): ?Location;
    public function createLocation(array $data): Location;
    public function updateLocation(int $id, array $data): Location;
    public function deleteLocation(int $id): void;
    public function categories(): array;
}
