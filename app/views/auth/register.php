<?php
/** Reporter self-registration form. */
$fieldValue = static function (string $field, array $values, array $errors): string {
    if (!empty($errors[$field])) {
        return '';
    }

    return (string) ($values[$field] ?? '');
};

$displayValues = $values;

foreach (array_keys($errors) as $errorField) {
    unset($displayValues[$errorField]);
}
?>

<section class="auth-card auth-card-wide">
    <h1>Create Reporter account</h1>

    <p class="lead">
        Students and staff can register to report and track campus waste issues.
    </p>

    <form 
        method="post" 
        action="<?= url('auth/store-registration') ?>" 
        class="form-stack" 
        data-no-auto-clear="1"
        >
            <?= csrfField() ?>

        <label>
            Full name
            <input 
                name="full_name" 
                required 
                maxlength="100" 
                value="<?= e($fieldValue('full_name', $values, $errors)) ?>"
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
                required 
                maxlength="100" 
                value="<?= e($fieldValue('email', $values, $errors)) ?>"
                >

            <?php if (!empty($errors['email'])): ?>
                <span class="field-error"><?= e($errors['email']) ?></span>
            <?php endif; ?>
        </label>

        <label>
            Phone number
            <input 
                name="phone_no" 
                maxlength="20" 
                value="<?= e($fieldValue('phone_no', $values, $errors)) ?>"
                >

            <?php if (!empty($errors['phone_no'])): ?>
                <span class="field-error"><?= e($errors['phone_no']) ?></span>
            <?php endif; ?>
        </label>

        <div class="form-grid">
            <label>
                Password
                <input 
                    type="password" 
                    name="password" 
                    required 
                    autocomplete="new-password"
                    >

                <?php if (!empty($errors['password'])): ?>
                    <span class="field-error"><?= e($errors['password']) ?></span>
                <?php endif; ?>
            </label>

            <label>
                Confirm password
                <input 
                    type="password" 
                    name="password_confirmation" 
                    required 
                    autocomplete="new-password"
                    >

                <?php if (!empty($errors['password_confirmation'])): ?>
                    <span class="field-error"><?= e($errors['password_confirmation']) ?></span>
                <?php endif; ?>
            </label>
        </div>

        <?php
        $values = $displayValues;
        require __DIR__ . '/../user/demographics.php';
        ?>

        <button class="button" type="submit">
            Create account
        </button>
    </form>

    <p class="auth-switch-link">
        <a href="<?= url('auth') ?>">Already registered? Sign in</a>
    </p>
</section>