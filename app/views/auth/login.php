<?php
/**
 * Login page.
 * Author : Phang Jun Hong (2406646)
 * Module : User & Access Management
 */
$errors = $errors ?? [];
$email = $email ?? '';
?>

<div class="auth-card">
    <h1>Sign in</h1>

    <p class="lead">
        Use your EcoCampus account to manage or view campus bins.
    </p>

    <form method="post" action="<?= url('auth/login') ?>" data-no-auto-clear="1" novalidate>
        <?= csrfField() ?>

        <label class="form-field">
            Email
            <input
                type="email"
                name="email"
                maxlength="100"
                value="<?= e((string) $email) ?>"
                >

            <?php if (!empty($errors['email'])): ?>
                <span class="field-error"><?= e($errors['email']) ?></span>
            <?php endif; ?>
        </label>

        <label class="form-field">
            Password
            <div class="password-field">
                <input
                    type="password"
                    name="password"
                    id="login_password"
                    >

                <button
                    type="button"
                    class="password-toggle"
                    data-password-toggle
                    data-target="login_password"
                    aria-label="Show password"
                    title="Show password"
                    >
                    <span class="material-symbols-outlined password-icon" aria-hidden="true">visibility</span>
                </button>
            </div>

            <?php if (!empty($errors['password'])): ?>
                <span class="field-error"><?= e($errors['password']) ?></span>
            <?php endif; ?>
        </label>

        <p class="muted">
            Forgot your password? Please contact an Administrator to reset your password.
        </p>

        <button class="button button-full" type="submit">
            Sign in
        </button>
    </form>

<!--    <section class="demo-accounts">
        <h2>Development accounts</h2>
        <p>Administrator: admin@ecocampus.my</p>
        <p>Cleaner: zaki@cleaner.ecocampus.my</p>
        <p>Password: password123</p>
    </section>-->

    <p class="auth-switch-link">
        <a href="<?= url('auth/register') ?>">
            Create a Reporter account
        </a>
    </p>
</div>