<?php
/**
 * Change password form.
 * Author : Phang Jun Hong (2406646)
 * Module : User & Access Management
 */
$errors = $errors ?? [];
?>

<div class="page-heading">
    <div>
        <h1>Change password</h1>
        <p class="lead">Update your account password securely.</p>
    </div>

    <a class="button button-secondary" href="<?= url('user/profile') ?>">
        Back to profile
    </a>
</div>

<form method="post" action="<?= url('user/updatePassword') ?>" class="profile-layout" data-no-auto-clear="1" novalidate>
<?= csrfField() ?>

    <div class="form-card">
        <h2>Password details</h2>

        <div class="form-grid">
            <label class="form-field">
                Current password
                <div class="password-field">
                    <input
                        type="password"
                        name="current_password"
                        id="current_password"
                        >

                    <button
                        type="button"
                        class="password-toggle"
                        data-password-toggle
                        data-target="current_password"
                        aria-label="Show current password"
                        title="Show password"
                        >
                        <span class="material-symbols-outlined password-icon" aria-hidden="true">visibility</span>
                    </button>
                </div>

<?php if (!empty($errors['current_password'])): ?>
                    <span class="field-error"><?= e($errors['current_password']) ?></span>
                <?php endif; ?>
            </label>

            <label class="form-field">
                New password
                <div class="password-field">
                    <input
                        type="password"
                        name="new_password"
                        id="new_password"
                        minlength="8"
                        maxlength="72"
                        pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,72}"
                        title="Password must be 8-72 characters and contain letters and numbers."
                        >

                    <button
                        type="button"
                        class="password-toggle"
                        data-password-toggle
                        data-target="new_password"
                        aria-label="Show new password"
                        title="Show password"
                        >
                        <span class="material-symbols-outlined password-icon" aria-hidden="true">visibility</span>
                    </button>
                </div>

                <?php if (!empty($errors['password'])): ?>
                    <span class="field-error"><?= e($errors['password']) ?></span>
                <?php endif; ?>
            </label>

            <label class="form-field form-full">
                Confirm new password
                <div class="password-field">
                    <input
                        type="password"
                        name="new_password_confirmation"
                        id="new_password_confirmation"
                        >

                    <button
                        type="button"
                        class="password-toggle"
                        data-password-toggle
                        data-target="new_password_confirmation"
                        aria-label="Show confirmed password"
                        title="Show password"
                        >
                        <span class="material-symbols-outlined password-icon" aria-hidden="true">visibility</span>
                    </button>
                </div>

<?php if (!empty($errors['password_confirmation'])): ?>
                    <span class="field-error"><?= e($errors['password_confirmation']) ?></span>
                <?php endif; ?>
            </label>
        </div>

        <div class="form-actions">
            <button class="button" type="submit">
                Update password
            </button>

            <a class="button button-secondary" href="<?= url('user/profile') ?>">
                Cancel
            </a>
        </div>
    </div>

    <aside class="profile-side">
        <section class="info-card">
            <h2>Password reminder</h2>
            <p>
                Enter your current password before setting a new password.
                The new password must contain 8 characters, including uppercase letters, lowercase letters, numbers, and special characters.
            </p>
        </section>
    </aside>
</form>