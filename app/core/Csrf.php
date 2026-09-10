<?php
/**
 * CSRF token generation and constant-time verification.
 *
 * Module : Shared security support
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }

    public static function verify(?string $submitted): bool
    {
        $stored = $_SESSION['csrf_token'] ?? '';
        return is_string($submitted) && is_string($stored)
            && $stored !== '' && hash_equals($stored, $submitted);
    }

    public static function requireValid(?string $submitted): void
    {
        if (!self::verify($submitted)) {
            throw new AuthorizationException('The form expired. Refresh the page and try again.');
        }
    }
}
