<?php
/**
 * Shared helper functions.
 *
 * CSRF form helper
 * Author : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
 *          Tan Boon Leong (2402865), Phang Jun Hong (2406646)
 * Module : Shared core - EcoCampus Waste Management System
 */

/**
 * Escapes a value for safe output inside HTML.
 *
 * Anything a user typed must pass through this before it is echoed into a
 * page. htmlspecialchars() converts < > " ' & into harmless entities, so a
 * submitted <script> tag is DISPLAYED as text instead of being EXECUTED by
 * the browser. This is the core defence against Cross-Site Scripting (XSS).
 *
 * Usage in a view:  <?= e($complaint->getDescription()) ?>
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Builds a full URL for a path inside the application. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Current timestamp in the format the assignment's IFA requires. */
function ifaTimestamp(): string
{
    return date('Y-m-d H:i:s');
}

/** Hidden CSRF field required in every state-changing HTML form. */
function csrfField(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}
