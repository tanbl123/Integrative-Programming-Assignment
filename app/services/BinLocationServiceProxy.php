<?php
/**
 * Protection Proxy for the Bin & Location module.
 *
 * It has the same interface as the real service, but checks the authenticated
 * role before delegating. Controllers cannot bypass these checks accidentally.
 *
 * Module : Bin & Location Management - Proxy design pattern
 */
class BinLocationServiceProxy implements BinLocationServiceInterface
{
    public function __construct(private BinLocationServiceInterface $realService)
    {
    }

    public function authorizeAdministrator(): void
    {
        UserPermissions::require('bin.manage');
    }

    public function authorizeCleaner(): void
    {
        UserPermissions::require('bin.status');
    }

    public function searchBins(string $query, string $status, ?int $locationId, bool $includeInactive): array
    {
        $user = Auth::requireLogin();
        return $this->realService->searchBins($query, $status, $locationId, $includeInactive && $user->isAdmin());
    }

    public function findBin(int $id): ?Bin
    {
        $user = Auth::requireLogin();
        $bin = $this->realService->findBin($id);
        return $bin !== null && (!$bin->isActive() && !$user->isAdmin()) ? null : $bin;
    }

    public function createBin(array $data): Bin
    {
        $this->authorizeAdministrator();
        return $this->realService->createBin($data);
    }

    public function updateBin(int $id, array $data): Bin
    {
        $this->authorizeAdministrator();
        return $this->realService->updateBin($id, $data);
    }

    public function deactivateBin(int $id): void
    {
        $this->authorizeAdministrator();
        $this->realService->deactivateBin($id);
    }

    public function reactivateBin(int $id): Bin
    {
        $this->authorizeAdministrator();
        return $this->realService->reactivateBin($id);
    }

    public function deleteLocation(int $id): void
    {
        $this->authorizeAdministrator();
        $this->realService->deleteLocation($id);
    }

    public function updateBinStatus(int $id, string $status, string $remarks, int $cleanerId): Bin
    {
        $user = UserPermissions::require('bin.status');
        if ($user->getKey() !== $cleanerId) {
            throw new AuthorizationException('A cleaner can only record their own status update.');
        }
        return $this->realService->updateBinStatus($id, $status, $remarks, $cleanerId);
    }

    public function searchLocations(string $query): array
    {
        Auth::requireLogin();
        return $this->realService->searchLocations($query);
    }

    public function findLocation(int $id): ?Location
    {
        Auth::requireLogin();
        return $this->realService->findLocation($id);
    }

    public function createLocation(array $data): Location
    {
        $this->authorizeAdministrator();
        return $this->realService->createLocation($data);
    }

    public function updateLocation(int $id, array $data): Location
    {
        $this->authorizeAdministrator();
        return $this->realService->updateLocation($id, $data);
    }

    public function categories(): array
    {
        Auth::requireLogin();
        return $this->realService->categories();
    }
}
