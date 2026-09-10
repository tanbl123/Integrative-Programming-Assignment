<?php
/**
 * JSON web service for consumers of the Bin module.
 *
 * Endpoints:
 *   GET  /bin-api
 *   GET  /bin-api/show/{id}
 *   POST /bin-api/update-status/{id}
 *
 * Module : Bin & Location Management - Web Service Technologies
 */
class BinApiController extends ApiController
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

            $this->json([
                'success' => true,
                'data' => array_map([$this, 'serializeBin'], $bins),
                'meta' => ['count' => count($bins)],
                'message' => null,
            ]);
        } catch (Throwable $error) {
            $this->jsonFailure($error);
        }
    }

    public function show(int $id): void
    {
        $this->apiAllow(['GET']);
        try {
            $bin = $this->service->findBin($id);
            if ($bin === null) {
                throw new OutOfBoundsException('Bin not found.');
            }
            $this->json([
                'success' => true,
                'data' => $this->serializeBin($bin),
                'message' => null,
            ]);
        } catch (Throwable $error) {
            $this->jsonFailure($error);
        }
    }

    public function updateStatus(int $id): void
    {
        try {
            if (!$this->isPost()) {
                $this->json(['success' => false, 'data' => null, 'message' => 'POST required.'], 405);
            }
            $payload = $this->apiPayload();
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($payload['_token'] ?? null);
            Csrf::requireValid(is_string($token) ? $token : null);
            $user = Auth::requireLogin();
            $bin = $this->service->updateBinStatus(
                $id,
                trim((string) ($payload['fill_status'] ?? '')),
                trim((string) ($payload['remarks'] ?? '')),
                (int) $user->getKey()
            );
            $this->json([
                'success' => true,
                'data' => $this->serializeBin($bin),
                'message' => 'Bin status recorded.',
            ]);
        } catch (Throwable $error) {
            $this->jsonFailure($error);
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

    private function payload(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            return is_array($decoded) ? $decoded : [];
        }
        return $_POST;
    }

    private function jsonFailure(Throwable $error): void
    {
        $status = match (true) {
            $error instanceof AuthenticationException => 401,
            $error instanceof AuthorizationException => 403,
            $error instanceof ValidationException => 422,
            $error instanceof OutOfBoundsException => 404,
            default => 500,
        };
        $message = $status === 500 && !DEBUG ? 'The service is temporarily unavailable.' : $error->getMessage();
        $payload = ['success' => false, 'data' => null, 'message' => $message];
        if ($error instanceof ValidationException) {
            $payload['errors'] = $error->getErrors();
        }
        $this->json($payload, $status);
    }
}
