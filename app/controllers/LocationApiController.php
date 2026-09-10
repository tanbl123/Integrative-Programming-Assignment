<?php
/**
 * Read-only JSON web service for campus locations.
 *
 * Module : Bin & Location Management - Web Service Technologies
 */
class LocationApiController extends ApiController
{
    private BinLocationServiceInterface $service;

    public function __construct()
    {
        $this->service = new BinLocationServiceProxy(new BinLocationService());
    }

    public function index(): void
    {
        $this->apiAllow(['GET']);
        try {
            $locations = $this->service->searchLocations(trim((string) ($_GET['q'] ?? '')));
            $this->json([
                'success' => true,
                'data' => array_map(static fn(Location $location): array => [
                    'id' => $location->getKey(),
                    'name' => $location->getLocationName(),
                    'building' => $location->getBuildingName(),
                    'floor' => $location->getFloorNo(),
                    'description' => $location->getDescription(),
                    'label' => $location->getFullLabel(),
                ], $locations),
                'meta' => ['count' => count($locations)],
                'message' => null,
            ]);
        } catch (AuthenticationException $error) {
            $this->json(['success' => false, 'data' => null, 'message' => $error->getMessage()], 401);
        } catch (AuthorizationException $error) {
            $this->json(['success' => false, 'data' => null, 'message' => $error->getMessage()], 403);
        }
    }

    public function show(int $id): void
    {
        $this->apiAllow(['GET']);
        try {
            $location = $this->service->findLocation($id);
            if ($location === null) {
                $this->json(['success' => false, 'data' => null, 'message' => 'Location not found.'], 404);
            }
            $this->json([
                'success' => true,
                'data' => [
                    'id' => $location->getKey(),
                    'name' => $location->getLocationName(),
                    'building' => $location->getBuildingName(),
                    'floor' => $location->getFloorNo(),
                    'description' => $location->getDescription(),
                    'label' => $location->getFullLabel(),
                ],
                'message' => null,
            ]);
        } catch (AuthenticationException $error) {
            $this->json(['success' => false, 'data' => null, 'message' => $error->getMessage()], 401);
        } catch (AuthorizationException $error) {
            $this->json(['success' => false, 'data' => null, 'message' => $error->getMessage()], 403);
        }
    }

    public function resource(?int $id = null): void
    {
        try {
            $this->apiAllow($id === null ? ['GET', 'POST'] : ['GET', 'PUT', 'PATCH', 'DELETE']);
            if ($this->apiMethod() === 'GET') {
                $id === null ? $this->index() : $this->show($id);
                return;
            }
            $payload = $this->apiPayload();
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
