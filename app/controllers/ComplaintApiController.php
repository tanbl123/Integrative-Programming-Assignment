<?php
/** JSON service providing unresolved complaints to Scheduling. Author: Ong Kar Heng (2408830). */
class ComplaintApiController extends ApiController
{
    public function resource(?int $id = null): void
    {
        try {
            $user = Auth::requireLogin();
            $service = new ComplaintService();
            $method = $this->apiMethod();
            $this->apiAllow($id === null ? ['GET', 'POST'] : ['GET', 'PUT', 'PATCH', 'DELETE']);
            if ($method === 'GET') {
                if ($id === null) {
                    $location = filter_var($_GET['location_id'] ?? null, FILTER_VALIDATE_INT);
                    $rows = $service->searchVisible($user, trim((string) ($_GET['q'] ?? '')), (string) ($_GET['status'] ?? ''), $location === false ? null : $location);
                    $this->apiRespond(array_map(static fn(Complaint $row): array => $row->toArray(), $rows));
                } else {
                    $row = $service->findVisible($user, $id);
                    if ($row === null) { throw new OutOfBoundsException('Complaint not found.'); }
                    $this->apiRespond($row->toArray());
                }
                return;
            }
            $data = $this->apiPayload();
            $this->apiWriteGuard($data);
            unset($data['_token']);
            if ($method === 'POST' && $id === null) {
                $this->apiRespond($service->create($user, $data, null)->toArray(), 201);
            } elseif (in_array($method, ['PUT', 'PATCH'], true) && $id !== null) {
                if (isset($data['complaint_status']) && array_intersect(['bin_id', 'complaint_type', 'description'], array_keys($data)) !== []) {
                    throw new ValidationException(['complaint' => 'Update complaint details and lifecycle status in separate requests.']);
                }
                $row = isset($data['complaint_status'])
                    ? $service->updateStatus($id, (string) $data['complaint_status'], (string) ($data['remarks'] ?? ''), $user)
                    : $service->update($id, $data, $user);
                $this->apiRespond($row->toArray());
            } elseif ($method === 'DELETE' && $id !== null) {
                $service->delete($id, $user);
                $this->apiRespond(null, 200, 'Complaint deleted; history retained.');
            } else { $this->apiRespond(null, 405, 'Method not allowed.'); }
        } catch (Throwable $error) { $this->apiFailure($error); }
    }

    public function unresolved(): void
    {
        $this->apiAllow(['GET']);
        try {
            UserPermissions::require('schedule.manage');
            $complaints = Complaint::unresolved();
            $this->json([
                'success' => true,
                'data' => array_map(static fn(Complaint $complaint): array => [
                    'id' => $complaint->getKey(),
                    'type' => $complaint->getType(),
                    'status' => $complaint->getStatus(),
                    'description' => $complaint->getDescription(),
                    'created_at' => $complaint->getCreatedAt(),
                    'bin' => [
                        'id' => $complaint->getBin()?->getKey(),
                        'code' => $complaint->getBin()?->getBinCode(),
                        'location' => $complaint->getBin()?->getLocation()?->getFullLabel(),
                    ],
                ], $complaints),
                'meta' => ['count' => count($complaints)],
                'message' => null,
            ]);
        } catch (AuthenticationException $error) {
            $this->json(['success' => false, 'data' => null, 'message' => $error->getMessage()], 401);
        } catch (AuthorizationException $error) {
            $this->json(['success' => false, 'data' => null, 'message' => $error->getMessage()], 403);
        }
    }
}
