<?php
/**
 * Secure sign-in form.
 * Author : Phang Jun Hong (2406646)
 * Module : User & Access Management
 */
?>

<section class="auth-card">
    <h1>Sign in</h1>
    <p class="lead">Use your EcoCampus account to manage or view campus bins.</p>

    <?php if (!empty($errors['form'])): ?>
        <div class="alert alert-error">
            <?= e($errors['form']) ?>
        </div>
    <?php endif; ?>

    <?php /* data-no-guard: signing in is not work in progress, and a warning on
         the way to the registration link would only be in the way. */ ?>
<form
    method="post"
    action="<?= url('auth/login') ?>"
    class="form-stack"
    data-no-auto-clear="1"
    data-no-guard="1"
>
        <?= csrfField() ?>

        <label>
            Email
            <input 
                type="email" 
                name="email" 
                value="<?= e($email) ?>" 
                required 
                autocomplete="username"
                >

            <?php if (!empty($errors['email'])): ?>
                <span class="field-error"><?= e($errors['email']) ?></span>
            <?php endif; ?>
        </label>

        <label>
            Password
            <input 
                type="password" 
                name="password" 
                required 
                autocomplete="current-password"
                >

            <?php if (!empty($errors['password'])): ?>
                <span class="field-error"><?= e($errors['password']) ?></span>
            <?php endif; ?>
        </label>

        <p class="field-help">
            Forgot your password? Please contact an Administrator to reset your password.
        </p>

        <button type="submit" class="button">
            Sign in
        </button>
    </form>

    <div class="demo-accounts">
        <strong>Development accounts</strong>
        <p>Administrator: admin@ecocampus.my</p>
        <p>Cleaner: zaki@cleaner.ecocampus.my</p>
        <p>Password: password123</p>
    </div>

    <p>
        <a href="<?= url('auth/register') ?>">Create a Reporter account</a>
    </p>
</section>