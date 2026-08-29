<?php
/** JSON service providing unresolved complaints to Scheduling. Author: Ong Kar Heng (2408830). */
class ComplaintApiController extends Controller
{
    public function unresolved(): void
    {
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
