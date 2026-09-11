<?php
/**
 * Personal profile display page.
 * Author : Phang Jun Hong (2406646)
 * Module : User & Access Management
 */
$demographics = $user->demographics();

$show = static function (mixed $value): string {
    $text = trim((string) ($value ?? ''));
    return $text !== '' ? $text : '-';
};
?>

<div class="page-heading">
    <div>
        <h1>My profile</h1>
        <p class="lead"><?= e($user->getRole()) ?> account &mdash; <?= e($user->describe()) ?></p>
    </div>

    <div class="button-row">
        <a class="button" href="<?= url('user/editProfile') ?>">
            Edit profile
        </a>

        <a class="button button-secondary" href="<?= url('user/changePassword') ?>">
            Change password
        </a>
    </div>
</div>

<section class="content-card">
    <h2>Account details</h2>

    <div class="detail-grid detail-grid-two">
        <div>
            <span>Full name</span>
            <strong><?= e($show($user->getFullName())) ?></strong>
        </div>

        <div>
            <span>Email</span>
            <strong><?= e($show($user->getEmail())) ?></strong>
        </div>

        <div>
            <span>Phone number</span>
            <strong><?= e($show($user->getPhoneNo())) ?></strong>
        </div>

        <div>
            <span>Role</span>
            <strong><?= e($show($user->getRole())) ?></strong>
        </div>

        <div class="detail-full">
            <span>Account status</span>
            <strong><?= e($show($user->getAccountStatus())) ?></strong>
        </div>
    </div>
</section>

<section class="content-card">
    <h2>Personal information</h2>

    <div class="detail-grid detail-grid-two">
        <div>
            <span>Address line 1</span>
            <strong><?= e($show($demographics['address_line1'] ?? null)) ?></strong>
        </div>

        <div>
            <span>Address line 2</span>
            <strong><?= e($show($demographics['address_line2'] ?? null)) ?></strong>
        </div>

        <div>
            <span>City</span>
            <strong><?= e($show($demographics['city'] ?? null)) ?></strong>
        </div>

        <div>
            <span>State</span>
            <strong><?= e($show($demographics['state'] ?? null)) ?></strong>
        </div>

        <div>
            <span>Postcode</span>
            <strong><?= e($show($demographics['postcode'] ?? null)) ?></strong>
        </div>

        <div>
            <span>Nationality</span>
            <strong><?= e($show($demographics['nationality'] ?? null)) ?></strong>
        </div>

        <div>
            <span>IC number</span>
            <strong><?= e($show($demographics['ic_no'] ?? null)) ?></strong>
        </div>

        <div>
            <span>Gender</span>
            <strong><?= e($show($demographics['gender'] ?? null)) ?></strong>
        </div>

        <div class="detail-full">
            <span>Birth date</span>
            <strong><?= e($show($demographics['birth_date'] ?? null)) ?></strong>
        </div>
    </div>
</section>