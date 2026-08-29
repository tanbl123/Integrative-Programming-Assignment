<?php
/** Reporter self-registration form. Author: Ong Kar Heng (2408830). */
?>
<section class="auth-card auth-card-wide">
    <h1>Create Reporter account</h1>
    <p class="lead">Students and staff can register to report and track campus waste issues.</p>
    <form method="post" action="<?= url('auth/store-registration') ?>" class="form-stack">
        <?= csrfField() ?>
        <label>Full name
            <input name="full_name" required maxlength="100" value="<?= e((string) ($values['full_name'] ?? '')) ?>">
            <?php if (!empty($errors['full_name'])): ?><span class="field-error"><?= e($errors['full_name']) ?></span><?php endif; ?>
        </label>
        <label>Email
            <input type="email" name="email" required maxlength="100" value="<?= e((string) ($values['email'] ?? '')) ?>">
            <?php if (!empty($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?>
        </label>
        <label>Phone number
            <input name="phone_no" maxlength="20" value="<?= e((string) ($values['phone_no'] ?? '')) ?>">
            <?php if (!empty($errors['phone_no'])): ?><span class="field-error"><?= e($errors['phone_no']) ?></span><?php endif; ?>
        </label>
        <div class="form-grid">
            <label>Password
                <input type="password" name="password" required autocomplete="new-password">
                <?php if (!empty($errors['password'])): ?><span class="field-error"><?= e($errors['password']) ?></span><?php endif; ?>
            </label>
            <label>Confirm password
                <input type="password" name="password_confirmation" required autocomplete="new-password">
                <?php if (!empty($errors['password_confirmation'])): ?><span class="field-error"><?= e($errors['password_confirmation']) ?></span><?php endif; ?>
            </label>
        </div>
        <button class="button" type="submit">Create account</button>
    </form>
    <p><a href="<?= url('auth') ?>">Already registered? Sign in</a></p>
</section>
