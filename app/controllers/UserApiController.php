<?php
/**
 * JSON service exposing active cleaners to the Scheduling module.
 * Author : Ong Kar Heng (2408830)
 * Module : User & Access Management - Web Service Technologies
 */
class UserApiController extends Controller
{
    public function cleaners(): void
    {
        try {
            Auth::requireLogin();
            $cleaners = User::findActiveCleaners();
            $this->json([
                'success' => true,
                'data' => array_map(static fn(User $user): array => [
                    'id' => $user->getKey(),
                    'name' => $user->getFullName(),
                    'email' => $user->getEmail(),
                ], $cleaners),
                'meta' => ['count' => count($cleaners)],
                'message' => null,
            ]);
        } catch (AuthenticationException $error) {
            $this->json(['success' => false, 'data' => null, 'message' => $error->getMessage()], 401);
        }
    }
}
