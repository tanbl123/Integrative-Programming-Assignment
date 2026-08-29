<?php
/**
 * Cleaner status update form.
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Cleaner inspection</p>
        <h1>Update <?= e($bin->getBinCode()) ?></h1>
        <p class="lead"><?= e($bin->getLocation()?->getFullLabel() ?? 'Unknown location') ?></p>
    </div>
    <a class="button button-secondary" href="<?= url('bin/show/' . $bin->getKey()) ?>">Cancel</a>
</div>

<form method="post" action="<?= url('bin/update-status/' . $bin->getKey()) ?>" class="form-card narrow-form">
    <?= csrfField() ?>
    <label>
        Current fill status
        <select name="fill_status" required>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status) ?>" <?= ($values['fill_status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['fill_status'])): ?><span class="field-error"><?= e($errors['fill_status']) ?></span><?php endif; ?>
    </label>
    <label>
        Inspection or service remarks
        <textarea name="remarks" rows="4" maxlength="255" placeholder="Example: Bin emptied and liner replaced."><?= e((string) ($values['remarks'] ?? '')) ?></textarea>
        <?php if (!empty($errors['remarks'])): ?><span class="field-error"><?= e($errors['remarks']) ?></span><?php endif; ?>
    </label>
    <button type="submit" class="button">Record update</button>
</form>
