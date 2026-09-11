<?php
/**
 * Shared page header.
 * Author : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
 *          Tan Boon Leong (2402865), Phang Jun Hong (2406646)
 * Module : Shared core - EcoCampus Waste Management System
 */
$currentUser = Auth::user();
// Asked of the user, so a Cleaner - who has no complaint notices - gets 0
// without this layout needing to know which roles do.
$unreadNotifications = $currentUser?->unreadNotificationCount() ?? 0;
$successMessage = Flash::get('success');
$errorMessage = Flash::get('error');
$styleVersion = filemtime(APP_ROOT . '/public/css/style.css');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'EcoCampus') ?></title>
    <link rel="stylesheet" href="<?= url('public/css/style.css') ?>?v=<?= (int) $styleVersion ?>">
    <?php if (!empty($useLeaflet)): ?>
        <link
            rel="stylesheet"
            href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
            crossorigin=""
        >
    <?php endif; ?>
</head>
<body>
<header class="site-header">
    <div class="wrap">
        <a class="brand" href="<?= url() ?>">EcoCampus</a>
        <nav aria-label="Main navigation">
            <a href="<?= url() ?>">Dashboard</a>
            <?php if ($currentUser !== null): ?>
                <?php if (UserPermissions::can($currentUser, 'user.manage')): ?>
                    <a href="<?= url('user') ?>">Users</a>
                <?php endif; ?>
                <a href="<?= url('bin') ?>">Bins</a>
                <a href="<?= url('location') ?>">Locations</a>
                <?php if (UserPermissions::can($currentUser, 'complaint.create') || UserPermissions::can($currentUser, 'complaint.manage')): ?>
                    <a href="<?= url('complaint') ?>">Complaints</a>
                <?php endif; ?>
                <?php if (UserPermissions::can($currentUser, 'complaint.manage')): ?>
                    <a href="<?= url('complaint-type') ?>">Issue types</a>
                <?php endif; ?>
                <?php if (UserPermissions::can($currentUser, 'schedule.manage')): ?>
                    <a href="<?= url('schedule') ?>">Schedules</a>
                <?php elseif (UserPermissions::can($currentUser, 'assignment.view_own')): ?>
                    <a href="<?= url('schedule/my') ?>">My assignments</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
        <div class="account-nav">
            <?php if ($currentUser !== null): ?>
                <?php /* Shown only to roles that can receive complaint notices, so a
                         Cleaner is not given a link that is always empty.

                         A bell rather than the word: this row already carries the
                         name, the role and Sign out, and at 1080px a fourth text
                         button pushed the whole account block onto a second line.
                         The count rides the bell, so an unread item is still the
                         first thing seen. */ ?>
                <?php if ($currentUser->isReporter() || $currentUser->isAdmin()): ?>
                    <a
                        class="button nav-button notification-link"
                        href="<?= url('complaint-notification') ?>"
                        title="Notifications"
                        <?php /* The icon carries no text, so the label has to. */ ?>
                        aria-label="Notifications<?= $unreadNotifications > 0
                            ? ', ' . (int) $unreadNotifications . ' unread' : '' ?>"
                    >
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="notification-count" aria-hidden="true">
                                <?= $unreadNotifications > 9 ? '9+' : (int) $unreadNotifications ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <a class="button nav-button" href="<?= url('user/profile') ?>"><?= e($currentUser->getFullName()) ?> · <?= e($currentUser->getRole()) ?></a>
                <form method="post" action="<?= url('auth/logout') ?>">
                    <?= csrfField() ?>
                    <button type="submit" class="button nav-button">Sign out</button>
                </form>
            <?php else: ?>
                <a class="button nav-button" href="<?= url('auth') ?>">Sign in</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="wrap">
<?php if ($successMessage !== null): ?>
    <div class="alert alert-success" data-toast role="status"><?= e($successMessage) ?></div>
<?php endif; ?>
<?php if ($errorMessage !== null): ?>
    <div class="alert alert-error" data-toast role="alert"><?= e($errorMessage) ?></div>
<?php endif; ?>
