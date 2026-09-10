<?php
/**
 * Front controller - the single entry point for every request.
 * Author : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
 *          Tan Boon Leong (2402865), Phang Jun Hong (2406646)
 * Module : Shared core - EcoCampus Waste Management System
 * .htaccess rewrites every URL to this file, so all requests are bootstrapped
 * the same way: configuration, autoloading, session, then routing.
 */

require_once __DIR__ . '/config/config.php';

// Show errors while developing, hide them from visitors once DEBUG is off.
ini_set('display_errors', DEBUG ? '1' : '0');
error_reporting(DEBUG ? E_ALL : 0);

require_once APP_ROOT . '/app/core/helpers.php';

/**
 * Autoloader: loads core classes and models on first use, so individual
 * files never need long lists of require statements.
 */
spl_autoload_register(static function (string $class): void {
    foreach (['/app/core/', '/app/models/', '/app/services/'] as $directory) {
        $file = APP_ROOT . $directory . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Session cookie hardening - applied before the session starts, or it has
// no effect. HttpOnly keeps JavaScript away from the session id, which
// limits the damage an XSS bug could do.
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();

(new App())->run();
