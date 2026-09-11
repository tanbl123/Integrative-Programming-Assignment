<?php
/**
 * Cleaner assignment JSON service.
 * Author : Ng Zi Zhang (2406898)
 * Module : Collection Scheduling & Assignment
 */
class ScheduleApiController extends ApiController
{
    public function resource(?int $id = null): void
    {
        try {
            $user = UserPermissions::require('schedule.manage');
            $service = new SchedulingService();
            $method = $this->apiMethod();
            $this->apiAllow($id === null ? ['GET', 'POST'] : ['GET', 'PUT', 'PATCH', 'DELETE']);
            if ($method === 'GET') {
                if ($id === null) {
                    $rows = CollectionSchedule::search((string) ($_GET['date'] ?? ''), (string) ($_GET['status'] ?? ''));
                    $this->apiRespond(array_map(static fn(CollectionSchedule $row): array => $row->toArray(), $rows));
                } else {
                    $row = $service->findVisible($user, $id);
                    if ($row === null) { throw new OutOfBoundsException('Schedule not found.'); }
                    $this->apiRespond($row->toArray());
                }
                return;
            }
            $data = $this->apiPayload();
            $this->apiWriteGuard($data);
            unset($data['_token']);
            if ($method === 'POST' && $id === null) {
                $this->apiRespond($service->create($user, $data)->toArray(), 201);
            } elseif (in_array($method, ['PUT', 'PATCH'], true) && $id !== null) {
                if (isset($data['schedule_status'])) {
                    if ($data['schedule_status'] !== 'Cancelled' || count($data) !== 1) {
                        throw new ValidationException(['schedule_status' => 'Use a separate request with schedule_status Cancelled; completion is derived from assignments.']);
                    }
                    $row = $service->cancel($id);
                } else {
                    $row = $service->findVisible($user, $id);
                    if ($row === null) { throw new OutOfBoundsException('Schedule not found.'); }
                    $defaults = $row->toArray();
                    $defaults['cleaner_id'] = ($row->getAssignments()[0] ?? null)?->getCleanerId();
                    $row = $service->update($id, array_replace($defaults, $data));
                }
                $this->apiRespond($row->toArray());
            } elseif ($method === 'DELETE' && $id !== null) {
                $service->delete($id);
                $this->apiRespond(null, 200, 'Schedule deleted; collection history retained.');
            } else { $this->apiRespond(null, 405, 'Method not allowed.'); }
        } catch (Throwable $error) { $this->apiFailure($error); }
    }

    public function mine(): void
    {
        $this->apiAllow(['GET']);
        try {
            $user = UserPermissions::require('assignment.view_own');
            $assignments = CollectionAssignment::forCleaner($user->getKey(), trim((string) ($_GET['status'] ?? '')));
            $this->json([
                'success' => true,
                'data' => array_map(static fn(CollectionAssignment $assignment): array => [
                    'id' => $assignment->getKey(), 'status' => $assignment->getStatus(),
                    'priority' => $assignment->getPriority(), 'reason' => $assignment->getReason(),
                    'schedule' => ['id' => $assignment->getSchedule()?->getKey(), 'date' => $assignment->getSchedule()?->getDate(), 'time_slot' => $assignment->getSchedule()?->getTimeSlot()],
                    'bin' => ['id' => $assignment->getBin()?->getKey(), 'code' => $assignment->getBin()?->getBinCode(), 'location' => $assignment->getBin()?->getLocation()?->getFullLabel()],
                ], $assignments),
                'meta' => ['count' => count($assignments)], 'message' => null,
            ]);
        } catch (AuthenticationException $error) { $this->json(['success' => false, 'data' => null, 'message' => $error->getMessage()], 401); }
        catch (AuthorizationException $error) { $this->json(['success' => false, 'data' => null, 'message' => $error->getMessage()], 403); }
    }
    
    public function cleanerOpenAssignments(): void {
        try {
            $this->apiAllow(['GET']);

            $requestId = trim((string) ($_GET['requestID'] ?? ''));
            $timeStamp = trim((string) ($_GET['timeStamp'] ?? ''));
            $cleanerId = filter_var($_GET['cleanerId'] ?? null, FILTER_VALIDATE_INT);

            if ($requestId === '' || $timeStamp === '' || $cleanerId === false) {
                $this->json([
                    'status' => 'F',
                    'success' => false,
                    'hasOpenAssignments' => false,
                    'openAssignmentCount' => 0,
                    'message' => 'requestID, cleanerId, and timeStamp are required.',
                    'requestID' => $requestId,
                    'timeStamp' => ifaTimestamp()
                ]);
                return;
            }

            $row = Database::getInstance()->selectOne(
                    "SELECT COUNT(*) AS total
                    FROM collection_assignments
                    WHERE cleaner_id = ?
                    AND assignment_status = 'Assigned'",
                    [(int) $cleanerId]
            );

            $count = (int) ($row['total'] ?? 0);

            $this->json([
                'status' => 'S',
                'success' => true,
                'hasOpenAssignments' => $count > 0,
                'openAssignmentCount' => $count,
                'message' => $count > 0 ? 'Cleaner has open assignments.' : 'Cleaner has no open assignments.',
                'requestID' => $requestId,
                'timeStamp' => ifaTimestamp()
            ]);
        } catch (Throwable $error) {
            $this->json([
                'status' => 'E',
                'success' => false,
                'hasOpenAssignments' => false,
                'openAssignmentCount' => 0,
                'message' => 'Unable to check cleaner assignments.',
                'requestID' => $_GET['requestID'] ?? '',
                'timeStamp' => ifaTimestamp()
                    ], 500);
        }
    }
}
