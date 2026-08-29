<?php
/**
 * Read-only JSON web service for campus locations.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management - Web Service Technologies
 */
class LocationApiController extends Controller
{
    private BinLocationServiceInterface $service;

    public function __construct()
    {
        $this->service = new BinLocationServiceProxy(new BinLocationService());
    }

    public function index(): void
    {
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
}
