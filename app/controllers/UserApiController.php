<?php
/**
 * User REST service with service/decorator access checks.
 * Author : Phang Jun Hong (2406646)
 * Module : User & Access Management
 */
class UserApiController extends ApiController
{
    /** REST CRUD on user accounts, routed by HTTP method. Requires user.manage. */
    public function resource(?int $id = null): void
    {
        try {
            $this->apiAllow($id === null ? ['GET', 'POST'] : ['GET', 'PUT', 'PATCH', 'DELETE']);
            UserPermissions::require('user.manage');
            $service = new UserService();
            $method = $this->apiMethod();
            if ($method === 'GET') {
                if ($id === null) {
                    $users = $service->search((string) ($_GET['q'] ?? ''), (string) ($_GET['role'] ?? ''), (string) ($_GET['status'] ?? ''));
                    // Demographics belong to the individual administrator detail view.
                    $this->apiRespond(array_map(static fn(User $user) => array_diff_key($user->apiData(), array_flip(User::DEMOGRAPHIC_FIELDS)), $users));
                } else {
                    $user = $service->find($id);
                    if ($user === null) { throw new OutOfBoundsException('User not found.'); }
                    $this->apiRespond($user->apiData());
                }
                return;
            }
            $data = $this->apiPayload();
            $this->apiWriteGuard($data);
            if ($method === 'POST') {
                $this->apiRespond($service->createByAdministrator($data)->apiData(), 201, 'User created.');
            } elseif ($method === 'DELETE') {
                $service->deleteByAdministrator($id);
                $this->apiRespond(null, 200, 'Account deleted; historical records preserved.');
            } else {
                if ($method === 'PATCH') {
                    $user = $service->find($id);
                    if ($user === null) { throw new OutOfBoundsException('User not found.'); }
                    $data += $user->apiData();
                }
                $this->apiRespond($service->updateByAdministrator($id, $data)->apiData(), 200, 'User updated.');
            }
        } catch (Throwable $error) {
            $this->apiFailure($error);
        }
    }

    /**
     * The active cleaners as JSON. Consumed by the Scheduling module, whose schedule
     * form needs somebody to assign the work to.
     */
    public function cleaners(): void {
        try {
            $this->apiAllow(['GET']);
            Auth::requireLogin();

            $requestId = trim((string) ($_GET['requestID'] ?? ''));
            $requestTimeStamp = trim((string) ($_GET['timeStamp'] ?? ''));

            if ($requestId === '' || $requestTimeStamp === '') {
                $this->json([
                    'status' => 'F',
                    'success' => false,
                    'data' => [],
                    'meta' => [
                        'count' => 0
                    ],
                    'message' => 'requestID and timeStamp are required.',
                    'requestID' => $requestId,
                    'timeStamp' => ifaTimestamp()
                ]);
                return;
            }

            $cleaners = User::findActiveCleaners();

            $this->json([
                'status' => 'S',
                'success' => true,
                'data' => array_map(static fn(User $user): array => [
                    'id' => $user->getKey(),
                    'name' => $user->getFullName(),
                    'email' => $user->getEmail(),
                        ], $cleaners),
                'meta' => [
                    'count' => count($cleaners)
                ],
                'message' => 'Active cleaners retrieved successfully.',
                'requestID' => $requestId,
                'timeStamp' => ifaTimestamp()
            ]);
        } catch (Throwable $error) {
            $this->apiFailure($error);
        }
    }
}
