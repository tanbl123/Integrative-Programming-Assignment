<?php
/** Cleaner assignment JSON service. Author: Ong Kar Heng (2408830). */
class ScheduleApiController extends Controller
{
    public function mine(): void
    {
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
