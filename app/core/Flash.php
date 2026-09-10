<?php
/**
 * One-request messages stored in the session.
 *
 * Author : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
 *          Tan Boon Leong (2402865), Phang Jun Hong (2406646)
 * Module : Shared presentation support
 */
class Flash
{
    public static function set(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    public static function get(string $key): ?string
    {
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return is_string($message) ? $message : null;
    }
}
