<?php
/** Administrator user form. Author: Ong Kar Heng (2408830). */
$isEdit = $mode === 'edit';
$action = $isEdit ? url('user/update/' . $user->getKey()) : url('user/store');
?>
<div class="page-heading"><div><h1><?= $isEdit ? 'Edit user' : 'Add user' ?></h1><p class="lead">Only Administrators can assign system roles.</p></div><a class="button button-secondary" href="<?= url('user') ?>">Cancel</a></div>
<form method="post" action="<?= $action ?>" class="form-card">
<?= csrfField() ?>
<div class="form-grid">
<label>Full name<input name="full_name" required maxlength="100" value="<?= e((string) ($values['full_name'] ?? '')) ?>"><?php if (!empty($errors['full_name'])): ?><span class="field-error"><?= e($errors['full_name']) ?></span><?php endif; ?></label>
<label>Email<input type="email" name="email" required maxlength="100" value="<?= e((string) ($values['email'] ?? '')) ?>"><?php if (!empty($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?></label>
<label>Phone<input name="phone_no" maxlength="20" value="<?= e((string) ($values['phone_no'] ?? '')) ?>"><?php if (!empty($errors['phone_no'])): ?><span class="field-error"><?= e($errors['phone_no']) ?></span><?php endif; ?></label>
<label>Role<select name="role"><?php foreach ($roles as $role): ?><option value="<?= e($role) ?>" <?= ($values['role'] ?? '') === $role ? 'selected' : '' ?>><?= e($role) ?></option><?php endforeach; ?></select><?php if (!empty($errors['role'])): ?><span class="field-error"><?= e($errors['role']) ?></span><?php endif; ?></label>
<label>Account status<select name="account_status"><option <?= ($values['account_status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option><option <?= ($values['account_status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select><?php if (!empty($errors['account_status'])): ?><span class="field-error"><?= e($errors['account_status']) ?></span><?php endif; ?></label>
</div>
<?php require __DIR__ . '/demographics.php'; ?>
<?php if (!$isEdit): ?><div class="form-grid"><label>Temporary password<input type="password" name="password" required><?php if (!empty($errors['password'])): ?><span class="field-error"><?= e($errors['password']) ?></span><?php endif; ?></label><label>Confirm password<input type="password" name="password_confirmation" required><?php if (!empty($errors['password_confirmation'])): ?><span class="field-error"><?= e($errors['password_confirmation']) ?></span><?php endif; ?></label></div><?php endif; ?>
<button class="button" type="submit"><?= $isEdit ? 'Save changes' : 'Add user' ?></button>
</form>
