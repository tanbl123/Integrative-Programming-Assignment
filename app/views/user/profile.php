<?php
/**
 * Personal profile form.
 * Author : Phang Jun Hong (2406646)
 * Module : User & Access Management
 */
$value = static fn(string $key, mixed $fallback): mixed =>
        array_key_exists($key, $values) ? $values[$key] : $fallback;
?>

<div class="page-heading">
    <div>
        <h1>My profile</h1>
        <p class="lead"><?= e($user->getRole()) ?> account</p>
    </div>
</div>

<form method="post" action="<?= url('user/update-profile') ?>" class="profile-layout" data-no-auto-clear="1">
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
                    value="<?= e((string) $value('full_name', $user->getFullName())) ?>"
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
                    value="<?= e((string) $value('email', $user->getEmail())) ?>"
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
                    value="<?= e((string) $value('phone_no', $user->getPhoneNo() ?? '')) ?>"
                    >

                <?php if (!empty($errors['phone_no'])): ?>
                    <span class="field-error"><?= e($errors['phone_no']) ?></span>
                <?php endif; ?>
            </label>
        </div>

        <?php
        $values += $user->demographics();
        require __DIR__ . '/demographics.php';
        ?>

        <div class="form-actions">
            <button class="button" type="submit">
                Save profile
            </button>

            <button class="button button-secondary" type="reset">
                Clear fields
            </button>
        </div>
    </div>

    <aside class="profile-side">
        <section class="info-card">
            <h2>Your account</h2>
            <p>
                You can update your contact details and personal information here.
                Contact an administrator if your account role needs changing.
            </p>
        </section>

        <section class="info-card">
            <h2>Change password</h2>
            <p class="muted">
                Leave these fields blank if you do not want to change your password.
            </p>

            <label>
                New password
                <input 
                    type="password" 
                    name="new_password"
                    minlength="8"
                    maxlength="72"
                    pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,72}"
                    title="Password must be 8-72 characters and contain letters and numbers."
                >

                <?php if (!empty($errors['password'])): ?>
                    <span class="field-error"><?= e($errors['password']) ?></span>
                <?php endif; ?>
            </label>

            <label>
                Confirm new password
                <input 
                    type="password" 
                    name="new_password_confirmation"
                    data-match="new_password"
                    data-message-match="Password confirmation does not match."
                >

                <?php if (!empty($errors['password_confirmation'])): ?>
                    <span class="field-error"><?= e($errors['password_confirmation']) ?></span>
                <?php endif; ?>
            </label>
        </section>
    </aside>
</form>