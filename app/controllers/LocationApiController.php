<?php
/**
 * Read-only JSON web service for campus locations.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
class LocationApiController extends ApiController
{
    private BinLocationServiceInterface $service;

    /**
     * Wraps the real service in the Protection Proxy, so the API is guarded by the
     * same rules as the web pages.
     */
    public function __construct()
    {
        $this->service = new BinLocationServiceProxy(new BinLocationService());
    }

    /** Every location as JSON, in the IFA envelope. */
    public function index(): void
    {
        $this->apiEnableIfa();
        $this->apiAllow(['GET']);
        try {
            $this->apiRequireTracking();
            $locations = $this->service->searchLocations(trim((string) ($_GET['q'] ?? '')));
            $this->apiRespond(
                array_map(static fn(Location $location): array => [
                    'id' => $location->getKey(),
                    'name' => $location->getLocationName(),
                    'building' => $location->getBuildingName(),
                    'floor' => $location->getFloorNo(),
                    'description' => $location->getDescription(),
                    'latitude' => $location->getLatitude(),
                    'longitude' => $location->getLongitude(),
                    'label' => $location->getFullLabel(),
                ], $locations),
                200,
                null,
                ['meta' => ['count' => count($locations)]]
            );
        } catch (Throwable $error) { $this->apiFailure($error); }
    }

    /** One location with the bins it holds. */
    public function show(int $id): void
    {
        $this->apiEnableIfa();
        $this->apiAllow(['GET']);
        try {
            $this->apiRequireTracking();
            $location = $this->service->findLocation($id);
            if ($location === null) {
                throw new OutOfBoundsException('Location not found.');
            }
            $this->apiRespond([
                    'id' => $location->getKey(),
                    'name' => $location->getLocationName(),
                    'building' => $location->getBuildingName(),
                    'floor' => $location->getFloorNo(),
                    'description' => $location->getDescription(),
                    'latitude' => $location->getLatitude(),
                    'longitude' => $location->getLongitude(),
                    'label' => $location->getFullLabel(),
            ]);
        } catch (Throwable $error) { $this->apiFailure($error); }
    }

    /** REST CRUD on one URL, routed by HTTP method. */
    public function resource(?int $id = null): void
    {
        $this->apiEnableIfa();
        try {
            $this->apiAllow($id === null ? ['GET', 'POST'] : ['GET', 'PUT', 'PATCH', 'DELETE']);
            if ($this->apiMethod() === 'GET') {
                $id === null ? $this->index() : $this->show($id);
                return;
            }
            $payload = $this->apiPayload();
            $this->apiRequireTracking($payload);
            $this->apiWriteGuard($payload);
            $this->service->authorizeAdministrator();
            if ($this->apiMethod() === 'DELETE') {
                $this->service->deleteLocation($id);
                $this->apiRespond(['id' => $id, 'deleted' => true], 200, 'Location deleted.');
            }
            if ($id === null) {
                $location = $this->service->createLocation($payload);
                header('Location: ' . BASE_URL . '/location-api/' . $location->getKey());
                $this->apiRespond($location->toArray(), 201, 'Location created.');
            }
            $existing = $this->service->findLocation($id);
            if ($existing === null) { throw new OutOfBoundsException('Location not found.'); }
            if ($this->apiMethod() === 'PATCH') { $payload = array_replace($existing->toArray(), $payload); }
            $this->apiRespond($this->service->updateLocation($id, $payload)->toArray(), 200, 'Location updated.');
        } catch (Throwable $error) { $this->apiFailure($error); }
    }
}
