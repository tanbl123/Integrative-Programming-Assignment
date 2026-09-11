<?php
/**
 * Cleaner assignment JSON service.
 * Author : Ng Zi Zhang (2406898)
 * Module : Collection Scheduling & Assignment
 */
class ScheduleApiController extends ApiController
{
    /**
     * REST service consumed by Bin & Location Management.
     * GET /schedule-api/bin-status/{binId}?requestID=...&timeStamp=...
     */
    public function binStatus(int $binId): void
    {
        $this->apiEnableIfa();
        $this->apiAllow(['GET']);
        try {
            $this->apiRequireTracking();
            UserPermissions::require('bin.view');
            $bin = Bin::find($binId);
            if ($bin === null || !$bin->isActive()) {
                throw new OutOfBoundsException('Bin not found.');
            }

            $assignments = CollectionAssignment::openForBin($binId);
            $next = $assignments[0] ?? null;
            $schedule = $next?->getSchedule();
            $cleaner = $next?->getCleaner();

            $this->apiRespond([
                'binId' => $binId,
                'hasOpenAssignment' => $next !== null,
                'openAssignmentCount' => count($assignments),
                'scheduleStatus' => $schedule?->getStatus() ?? 'Not Scheduled',
                'nextCollection' => $schedule === null ? null : [
                    'scheduleId' => $schedule->getKey(),
                    'date' => $schedule->getDate(),
                    'timeSlot' => $schedule->getTimeSlot(),
                    'cleaner' => $cleaner === null ? null : [
                        'id' => $cleaner->getKey(),
                        'name' => $cleaner->getFullName(),
                    ],
                ],
            ]);
        } catch (Throwable $error) { $this->apiFailure($error); }
    }

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
}
