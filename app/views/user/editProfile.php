<?php

/**
 * Edit personal profile form.
 * Author : Phang Jun Hong (2406646)
 * Module : User & Access Management
 */

$values = $values ?? [];
$errors = $errors ?? [];

$value = static fn(string $key, mixed $fallback = ''): mixed =>
        array_key_exists($key, $values) ? $values[$key] : $fallback;

$fieldValue = static function (string $field, array $values, array $errors): string {
    if (!empty($errors[$field])) {
        return '';
    }

    return (string) ($values[$field] ?? '');
};
?>

<div class="page-heading">
    <div>
        <h1>Edit profile</h1>
        <p class="lead">Update your account details and personal information.</p>
    </div>

    <a class="button button-secondary" href="<?= url('user/profile') ?>">
        Back to profile
    </a>
</div>

<form method="post" action="<?= url('user/updateProfile') ?>" class="profile-layout" data-no-auto-clear="1">
    <?= csrfField() ?>

    <div class="form-card">
        <h2>Account details</h2>

        <div class="form-grid">
            <label>
                Full name
                <input
                    name="full_name"
                    minlength="2"
                    maxlength="100"
                    required
                    value="<?= e((string) $value('full_name')) ?>"
                >

                <?php if (!empty($errors['full_name'])): ?>
                    <span class="field-error"><?= e($errors['full_name']) ?></span>
                <?php endif; ?>
            </label>

            <label>
                Email
                <input
                    type="email"
                    name="email"
                    maxlength="100"
                    required
                    value="<?= e((string) $value('email')) ?>"
                >

                <?php if (!empty($errors['email'])): ?>
                    <span class="field-error"><?= e($errors['email']) ?></span>
                <?php endif; ?>
            </label>

            <label>
                Phone
                <input
                    name="phone_no"
                    maxlength="20"
                    pattern="[0-9+() -]{7,20}"
                    title="Phone number must be 7 to 20 characters."
                    required
                    value="<?= e((string) $value('phone_no')) ?>"
                >

                <?php if (!empty($errors['phone_no'])): ?>
                    <span class="field-error"><?= e($errors['phone_no']) ?></span>
                <?php endif; ?>
            </label>
        </div>

        <?php require __DIR__ . '/demographics.php'; ?>

        <div class="form-actions">
            <button class="button" type="submit">
                Save profile
            </button>

            <a class="button button-secondary" href="<?= url('user/profile') ?>">
                Cancel
            </a>
        </div>
    </div>

    <aside class="profile-side">
        <section class="info-card">
            <h2>Edit profile</h2>
            <p>
                This page is used to update your contact details and personal information only.
                Password changes are handled on a separate page for better security and clarity.
            </p>
        </section>
    </aside>
</form>