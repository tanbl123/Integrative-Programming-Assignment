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
                         Cleaner is not given a link that is always empty. */ ?>
                <?php if ($currentUser->isReporter() || $currentUser->isAdmin()): ?>
                    <a class="button nav-button notification-link" href="<?= url('complaint-notification') ?>">
                        Notifications
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="notification-count"><?= (int) $unreadNotifications ?></span>
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
