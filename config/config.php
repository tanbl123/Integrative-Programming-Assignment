<?php
/**
 * Application configuration.
 * Author : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
 *          Tan Boon Leong (2402865), Phang Jun Hong (2406646)
 * Module : Shared core - EcoCampus Waste Management System
 *
 * The values below are the XAMPP defaults and work out of the box.
 * If your MySQL root account has a password, create config/config.local.php
 * and define DB_PASS there. That file is loaded FIRST and is git-ignored,
 * so your credentials never reach GitHub.
 *
 *   <?php
 *   define('DB_PASS', 'your_password_here');
 */

// Per-developer overrides load first: a constant defined here wins,
// because the defaults below are only applied if not already set.
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// ---------- Database ----------
defined('DB_HOST')    or define('DB_HOST', 'localhost');
defined('DB_NAME')    or define('DB_NAME', 'ecocampus');
defined('DB_USER')    or define('DB_USER', 'root');
defined('DB_PASS')    or define('DB_PASS', '');
defined('DB_CHARSET') or define('DB_CHARSET', 'utf8mb4');

// ---------- Application ----------
defined('BASE_URL')    or define('BASE_URL', '/EcoCampus');
defined('APP_ROOT')    or define('APP_ROOT', dirname(__DIR__));
defined('UPLOAD_PATH') or define('UPLOAD_PATH', APP_ROOT . '/uploads');
defined('APP_TIMEZONE') or define('APP_TIMEZONE', 'Asia/Kuala_Lumpur');
date_default_timezone_set(APP_TIMEZONE);

// Set to false before the demo so visitors never see raw error text.
defined('DEBUG') or define('DEBUG', true);
