<?php
/**
 * Shared page header.
 *
 * Author : Tan Boon Leong (2402865)
 * Updated: Ong Kar Heng (2408830) - authenticated module navigation
 * Module : Shared core - EcoCampus Waste Management System
 */
$currentUser = Auth::user();
$successMessage = Flash::get('success');
$errorMessage = Flash::get('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'EcoCampus') ?></title>
    <link rel="stylesheet" href="<?= url('public/css/style.css') ?>">
</head>
<body>
<header class="site-header">
    <div class="wrap">
        <a class="brand" href="<?= url() ?>">EcoCampus</a>
        <nav>
            <a href="<?= url() ?>">Dashboard</a>
            <?php if ($currentUser !== null): ?>
                <a href="<?= url('bin') ?>">Bins</a>
                <a href="<?= url('location') ?>">Locations</a>
                <?php if (UserPermissions::can($currentUser, 'complaint.create') || UserPermissions::can($currentUser, 'complaint.manage')): ?>
                    <a href="<?= url('complaint') ?>">Complaints</a>
                <?php endif; ?>
                <?php if (UserPermissions::can($currentUser, 'schedule.manage')): ?>
                    <a href="<?= url('schedule') ?>">Schedules</a>
                <?php elseif (UserPermissions::can($currentUser, 'assignment.view_own')): ?>
                    <a href="<?= url('schedule/my') ?>">My assignments</a>
                <?php endif; ?>
                <?php if (UserPermissions::can($currentUser, 'user.manage')): ?>
                    <a href="<?= url('user') ?>">Users</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
        <div class="account-nav">
            <?php if ($currentUser !== null): ?>
                <a href="<?= url('user/profile') ?>"><?= e($currentUser->getFullName()) ?> · <?= e($currentUser->getRole()) ?></a>
                <form method="post" action="<?= url('auth/logout') ?>">
                    <?= csrfField() ?>
                    <button type="submit" class="link-button">Sign out</button>
                </form>
            <?php else: ?>
                <a href="<?= url('auth') ?>">Sign in</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="wrap">
<?php if ($successMessage !== null): ?>
    <div class="alert alert-success"><?= e($successMessage) ?></div>
<?php endif; ?>
<?php if ($errorMessage !== null): ?>
    <div class="alert alert-error"><?= e($errorMessage) ?></div>
<?php endif; ?>
