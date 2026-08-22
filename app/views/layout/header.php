<?php
/**
 * Shared page header.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Shared core - EcoCampus Waste Management System
 */
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
            <a href="<?= url('complaint') ?>">Complaints</a>
            <a href="<?= url('bin') ?>">Bins</a>
        </nav>
    </div>
</header>
<main class="wrap">
