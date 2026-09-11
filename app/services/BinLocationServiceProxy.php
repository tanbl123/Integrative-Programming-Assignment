<?php
/**
 * Protection Proxy for the Bin & Location module.
 *
 * It has the same interface as the real service, but checks the authenticated
 * role before delegating. Controllers cannot bypass these checks accidentally.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
class BinLocationServiceProxy implements BinLocationServiceInterface
{
    /**
     * Holds the real service THROUGH THE INTERFACE, never as a concrete class.
     * That is what makes this a Proxy rather than a wrapper: it is substitutable
     * for the thing it protects, so no caller can tell which one it is holding.
     */
    public function __construct(private BinLocationServiceInterface $realService)
    {
    }

    /** Requires bin.manage. */
    public function authorizeAdministrator(): void
    {
        UserPermissions::require('bin.manage');
    }

    /** Requires bin.status. */
    public function authorizeCleaner(): void
    {
        UserPermissions::require('bin.status');
    }

    /**
     * Signed in, and inactive bins are only included for an Administrator - a
     * non-admin asking for them simply does not get them, rather than being refused.
     */
    public function searchBins(string $query, string $status, ?int $locationId, bool $includeInactive): array
    {
        $user = Auth::requireLogin();
        return $this->realService->searchBins($query, $status, $locationId, $includeInactive && $user->isAdmin());
    }

    /**
     * Signed in, and an inactive bin reads as not found to anyone but an
     * Administrator.
     */
    public function findBin(int $id): ?Bin
    {
        $user = Auth::requireLogin();
        $bin = $this->realService->findBin($id);
        return $bin !== null && (!$bin->isActive() && !$user->isAdmin()) ? null : $bin;
    }

    /** Administrator only. */
    public function createBin(array $data): Bin
    {
        $this->authorizeAdministrator();
        return $this->realService->createBin($data);
    }

    /** Administrator only. */
    public function updateBin(int $id, array $data): Bin
    {
        $this->authorizeAdministrator();
        return $this->realService->updateBin($id, $data);
    }

    /** Administrator only. */
    public function deactivateBin(int $id): void
    {
        $this->authorizeAdministrator();
        $this->realService->deactivateBin($id);
    }

    /** Administrator only. */
    public function reactivateBin(int $id): Bin
    {
        $this->authorizeAdministrator();
        return $this->realService->reactivateBin($id);
    }

    /** Administrator only. */
    public function deleteLocation(int $id): void
    {
        $this->authorizeAdministrator();
        $this->realService->deleteLocation($id);
    }

    /**
     * The strictest check in the class, and the one worth reading.
     *
     * Holding bin.status is not enough: the cleaner id in the request must be the
     * caller’s own. Without that second test any cleaner could record work against
     * another cleaner’s name, and the audit trail would be worthless.
     */
    public function updateBinStatus(int $id, string $status, string $remarks, int $cleanerId): Bin
    {
        $user = UserPermissions::require('bin.status');
        if ($user->getKey() !== $cleanerId) {
            throw new AuthorizationException('A cleaner can only record their own status update.');
        }
        return $this->realService->updateBinStatus($id, $status, $remarks, $cleanerId);
    }

    /** Signed in. */
    public function searchLocations(string $query): array
    {
        Auth::requireLogin();
        return $this->realService->searchLocations($query);
    }

    /** Signed in. */
    public function findLocation(int $id): ?Location
    {
        Auth::requireLogin();
        return $this->realService->findLocation($id);
    }

    /** Administrator only. */
    public function createLocation(array $data): Location
    {
        $this->authorizeAdministrator();
        return $this->realService->createLocation($data);
    }

    /** Administrator only. */
    public function updateLocation(int $id, array $data): Location
    {
        $this->authorizeAdministrator();
        return $this->realService->updateLocation($id, $data);
    }

    /** Signed in. */
    public function categories(): array
    {
        Auth::requireLogin();
        return $this->realService->categories();
    }
}
