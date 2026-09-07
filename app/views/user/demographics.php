<?php /** Shared optional demographic fields. Author: Ong Kar Heng (2408830). */ ?>
<h2>Personal information</h2>
<p class="muted">These fields are optional. IC and address details are available only to you and administrators.</p>
<div class="form-grid">
<?php foreach (['address_line1' => ['Address line 1', 200], 'address_line2' => ['Address line 2', 200], 'city' => ['City', 100], 'state' => ['State', 100], 'postcode' => ['Postcode', 12], 'nationality' => ['Nationality', 80], 'ic_no' => ['IC number', 14]] as $field => [$label, $limit]): ?>
<label><?= e($label) ?><input name="<?= e($field) ?>" maxlength="<?= $limit ?>" value="<?= e((string) ($values[$field] ?? '')) ?>"><?php if (!empty($errors[$field])): ?><span class="field-error"><?= e($errors[$field]) ?></span><?php endif; ?></label>
<?php endforeach; ?>
<label>Gender<select name="gender"><option value="">Not provided</option><?php foreach (['Male', 'Female', 'Other', 'Prefer not to say'] as $gender): ?><option value="<?= e($gender) ?>" <?= ($values['gender'] ?? '') === $gender ? 'selected' : '' ?>><?= e($gender) ?></option><?php endforeach; ?></select><?php if (!empty($errors['gender'])): ?><span class="field-error"><?= e($errors['gender']) ?></span><?php endif; ?></label>
<label>Birth date<input type="date" name="birth_date" min="1900-01-01" max="<?= date('Y-m-d') ?>" value="<?= e((string) ($values['birth_date'] ?? '')) ?>"><?php if (!empty($errors['birth_date'])): ?><span class="field-error"><?= e($errors['birth_date']) ?></span><?php endif; ?></label>
</div>
