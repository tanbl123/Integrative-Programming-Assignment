<?php /** Personal profile form. Author: Ong Kar Heng (2408830). */
$value = static fn(string $key, mixed $fallback): mixed => array_key_exists($key, $values) ? $values[$key] : $fallback;
?>
<div class="page-heading"><div><h1>My profile</h1><p class="lead"><?= e($user->getRole()) ?> account</p></div></div>
<div class="two-column-layout">
<form method="post" action="<?= url('user/update-profile') ?>" class="form-card">
<?= csrfField() ?>
<div class="form-grid">
<label>Full name<input name="full_name" required value="<?= e((string) $value('full_name', $user->getFullName())) ?>"><?php if (!empty($errors['full_name'])): ?><span class="field-error"><?= e($errors['full_name']) ?></span><?php endif; ?></label>
<label>Email<input type="email" name="email" required value="<?= e((string) $value('email', $user->getEmail())) ?>"><?php if (!empty($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?></label>
<label>Phone<input name="phone_no" value="<?= e((string) $value('phone_no', $user->getPhoneNo() ?? '')) ?>"><?php if (!empty($errors['phone_no'])): ?><span class="field-error"><?= e($errors['phone_no']) ?></span><?php endif; ?></label>
</div>
<h2>Change password (optional)</h2>
<div class="form-grid">
<label>Current password<input type="password" name="current_password"><?php if (!empty($errors['current_password'])): ?><span class="field-error"><?= e($errors['current_password']) ?></span><?php endif; ?></label>
<label>New password<input type="password" name="new_password"><?php if (!empty($errors['password'])): ?><span class="field-error"><?= e($errors['password']) ?></span><?php endif; ?></label>
<label>Confirm new password<input type="password" name="new_password_confirmation"><?php if (!empty($errors['password_confirmation'])): ?><span class="field-error"><?= e($errors['password_confirmation']) ?></span><?php endif; ?></label>
</div>
<button class="button" type="submit">Save profile</button>
</form>
<aside class="info-card"><h2>Decorator permissions</h2><p>Your role adds these capabilities to the basic signed-in profile:</p><ul><?php foreach ($permissions as $permission): ?><li><?= e($permission) ?></li><?php endforeach; ?></ul></aside>
</div>
