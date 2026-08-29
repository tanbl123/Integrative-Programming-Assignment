<?php
/**
 * Session-backed authentication and role checks.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : Shared security support
 */
class Auth
{
    private static ?User $cachedUser = null;
    private static bool $resolved = false;

    public static function user(): ?User
    {
        if (self::$resolved) {
            return self::$cachedUser;
        }

        self::$resolved = true;
        $userId = $_SESSION['user_id'] ?? null;
        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }

        $user = User::find((int) $userId);
        self::$cachedUser = $user?->isActive() ? $user : null;

        if (self::$cachedUser === null) {
            unset($_SESSION['user_id']);
        }

        return self::$cachedUser;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail(mb_strtolower(trim($email)));
        if ($user === null || !$user->isActive()
            || !password_verify($password, $user->getPasswordHash())) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->getKey();
        self::$cachedUser = $user;
        self::$resolved = true;

        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        self::$cachedUser = null;
        self::$resolved = true;
        session_regenerate_id(true);
    }

    public static function requireLogin(): User
    {
        $user = self::user();
        if ($user === null) {
            throw new AuthenticationException('Please sign in to continue.');
        }

        return $user;
    }

    public static function requireRole(string ...$roles): User
    {
        $user = self::requireLogin();
        if (!in_array($user->getRole(), $roles, true)) {
            throw new AuthorizationException('Your account is not allowed to perform this action.');
        }

        return $user;
    }
}
