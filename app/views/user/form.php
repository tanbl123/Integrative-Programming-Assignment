<?php

/**
 * Author : Phang Jun Hong (2406646)
 * Module : User & Access Management
 */
$isEdit = $mode === 'edit';
$action = $isEdit ? url('user/update/' . $user->getKey()) : url('user/store');
?>

<div class="page-heading">
    <div>
        <h1><?= $isEdit ? 'Edit user' : 'Add user' ?></h1>
        <p class="lead">Only Administrators can assign system roles.</p>
    </div>
    <a class="button button-secondary" href="<?= url('user') ?>">Cancel</a>
</div>

<form method="post" action="<?= $action ?>" class="form-card">
    <?= csrfField() ?>

    <div class="form-grid">

        <label>
            Full name
            <input 
                name="full_name"
                minlength="2"
                maxlength="100"
                title="Full name must be between 2 and 100 characters."
                value="<?= e((string) ($values['full_name'] ?? '')) ?>"
                required
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
                title="Enter a valid email address."
                value="<?= e((string) ($values['email'] ?? '')) ?>"
                required
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
                value="<?= e((string) ($values['phone_no'] ?? '')) ?>"
            >
            <?php if (!empty($errors['phone_no'])): ?>
                <span class="field-error"><?= e($errors['phone_no']) ?></span>
            <?php endif; ?>
        </label>

        <label>
            Role
            <select name="role" required>
                <option value="" disabled <?= empty($values['role']) ? 'selected' : '' ?>>
                    Select role
                </option>

                <?php foreach ($roles as $role): ?>
                    <option value="<?= e($role) ?>" <?= ($values['role'] ?? '') === $role ? 'selected' : '' ?>>
                        <?= e($role) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if (!empty($errors['role'])): ?>
                <span class="field-error"><?= e($errors['role']) ?></span>
            <?php endif; ?>
        </label>

        <label>
            Account status
            <select name="account_status" required>
                <option value="Active" <?= ($values['account_status'] ?? '') === 'Active' ? 'selected' : '' ?>>
                    Active
                </option>
                <option value="Inactive" <?= ($values['account_status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>
                    Inactive
                </option>
            </select>

            <?php if (!empty($errors['account_status'])): ?>
                <span class="field-error"><?= e($errors['account_status']) ?></span>
            <?php endif; ?>
        </label>

    </div>

    <?php require __DIR__ . '/demographics.php'; ?>

    <?php if (!$isEdit): ?>
        <p class="field-help">
            A temporary password will be generated automatically after the account is created.
        </p>
    <?php endif; ?>

    <button class="button" type="submit">
        <?= $isEdit ? 'Save changes' : 'Add user' ?>
    </button>
</form>