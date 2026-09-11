<?php
/**
 * JSON web service for consumers of the Bin module.
 *
 * Endpoints:
 *   GET  /bin-api
 *   GET  /bin-api/show/{id}
 *   POST /bin-api/update-status/{id}
 *
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
class BinApiController extends ApiController
{
    private BinLocationServiceInterface $service;

    /**
     * Wraps the real service in the Protection Proxy, so the web service is guarded
     * by exactly the same rules as the web pages.
     */
    public function __construct()
    {
        $this->service = new BinLocationServiceProxy(new BinLocationService());
    }

    /**
     * Every bin as JSON. Consumed by the Scheduling module when previewing which
     * bins have work waiting.
     */
    public function index(): void
    {
        $this->apiEnableIfa();
        $this->apiAllow(['GET']);
        try {
            $this->apiRequireTracking();
            $location = filter_var($_GET['location_id'] ?? null, FILTER_VALIDATE_INT);
            $location = $location === false ? null : $location;
            $user = Auth::requireLogin();
            $includeInactive = $user->isAdmin() && ($_GET['include_inactive'] ?? '') === '1';
            $bins = $this->service->searchBins(
                trim((string) ($_GET['q'] ?? '')),
                trim((string) ($_GET['status'] ?? '')),
                $location,
                $includeInactive
            );

            $this->apiRespond(
                array_map([$this, 'serializeBin'], $bins),
                200,
                null,
                ['meta' => ['count' => count($bins)]]
            );
        } catch (Throwable $error) {
            $this->apiFailure($error);
        }
    }

    /**
     * One bin with its location and category. Consumed by the Complaint module to
     * show live bin details on a complaint.
     */
    public function show(int $id): void
    {
        $this->apiEnableIfa();
        $this->apiAllow(['GET']);
        try {
            $this->apiRequireTracking();
            $bin = $this->service->findBin($id);
            if ($bin === null) {
                throw new OutOfBoundsException('Bin not found.');
            }
            $this->apiRespond($this->serializeBin($bin));
        } catch (Throwable $error) {
            $this->apiFailure($error);
        }
    }

    /**
     * Lets a Cleaner record a fill-status change over the API. POST only, and the
     * CSRF token is required.
     */
    public function updateStatus(int $id): void
    {
        $this->apiEnableIfa();
        try {
            $this->apiAllow(['POST']);
            $payload = $this->apiPayload();
            $this->apiRequireTracking($payload);
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($payload['_token'] ?? null);
            Csrf::requireValid(is_string($token) ? $token : null);
            $user = Auth::requireLogin();
            $bin = $this->service->updateBinStatus(
                $id,
                trim((string) ($payload['fill_status'] ?? '')),
                trim((string) ($payload['remarks'] ?? '')),
                (int) $user->getKey()
            );
            $this->apiRespond($this->serializeBin($bin), 200, 'Bin status recorded.');
        } catch (Throwable $error) {
            $this->apiFailure($error);
        }
    }

    /**
     * REST CRUD on one URL, routed by HTTP method: GET and POST on the collection,
     * GET, PUT, PATCH and DELETE on a single bin.
     */
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
                $this->service->deactivateBin($id);
                $this->apiRespond(['id' => $id, 'active' => false], 200, 'Bin deactivated; history retained.');
            }
            if ($id === null) {
                $bin = $this->service->createBin($payload);
                header('Location: ' . BASE_URL . '/bin-api/' . $bin->getKey());
                $this->apiRespond($this->serializeBin($bin), 201, 'Bin created.');
            }
            $existing = $this->service->findBin($id);
            if ($existing === null) { throw new OutOfBoundsException('Bin not found.'); }
            if ($this->apiMethod() === 'PATCH') { $payload = array_replace($existing->toArray(), $payload); }
            $this->apiRespond($this->serializeBin($this->service->updateBin($id, $payload)), 200, 'Bin updated.');
        } catch (Throwable $error) { $this->apiFailure($error); }
    }

    /**
     * Turns a Bin into the JSON shape. One method, so every endpoint in this
     * controller returns the same fields.
     */
    private function serializeBin(Bin $bin): array
    {
        return [
            'id' => $bin->getKey(),
            'code' => $bin->getBinCode(),
            'fill_status' => $bin->getFillStatus(),
            'capacity_litre' => $bin->getCapacityLitre(),
            'active' => $bin->isActive(),
            'location' => [
                'id' => $bin->getLocation()?->getKey(),
                'label' => $bin->getLocation()?->getFullLabel(),
            ],
            'category' => [
                'id' => $bin->getCategory()?->getKey(),
                'name' => $bin->getCategory()?->getCategoryName(),
            ],
            'last_updated' => $bin->getLastUpdated(),
        ];
    }

}
