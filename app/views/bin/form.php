<?php
/**
 * Administrator bin create/edit form.
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
$isEdit = $mode === 'edit';
$action = $isEdit ? url('bin/update/' . $bin->getKey()) : url('bin/store');
?>
<div class="page-heading">
    <div>
        <h1><?= $isEdit ? 'Edit bin' : 'Register a new bin' ?></h1>
        <p class="lead">Maintain the authoritative bin record used by complaints and schedules.</p>
    </div>
    <a class="button button-secondary" href="<?= url('bin') ?>">Cancel</a>
</div>

<form method="post" action="<?= $action ?>" class="form-card">
    <?= csrfField() ?>
    <div class="form-grid">
        <label>
            Bin code
            <input name="bin_code" value="<?= e((string) ($values['bin_code'] ?? '')) ?>" required maxlength="50" placeholder="BIN-A-001">
            <?php if (!empty($errors['bin_code'])): ?><span class="field-error"><?= e($errors['bin_code']) ?></span><?php endif; ?>
        </label>
        <label>
            Capacity (litres)
            <input type="number" name="capacity_litre" value="<?= e((string) ($values['capacity_litre'] ?? '')) ?>" min="1" max="10000">
            <?php if (!empty($errors['capacity_litre'])): ?><span class="field-error"><?= e($errors['capacity_litre']) ?></span><?php endif; ?>
        </label>
        <label>
            Location
            <select name="location_id" required>
                <option value="">Select location</option>
                <?php foreach ($locations as $location): ?>
                    <option value="<?= $location->getKey() ?>" <?= (string) ($values['location_id'] ?? '') === (string) $location->getKey() ? 'selected' : '' ?>><?= e($location->getFullLabel()) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['location_id'])): ?><span class="field-error"><?= e($errors['location_id']) ?></span><?php endif; ?>
        </label>
        <label>
            Waste category
            <select name="category_id" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= $category->getKey() ?>" <?= (string) ($values['category_id'] ?? '') === (string) $category->getKey() ? 'selected' : '' ?>><?= e($category->getCategoryName()) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['category_id'])): ?><span class="field-error"><?= e($errors['category_id']) ?></span><?php endif; ?>
        </label>
    </div>
    <input type="hidden" name="is_active" value="0">
    <label class="checkbox-label">
        <input type="checkbox" name="is_active" value="1" <?= (string) ($values['is_active'] ?? '1') === '1' ? 'checked' : '' ?>>
        Bin is active and available for reporting/scheduling
    </label>
    <button type="submit" class="button"><?= $isEdit ? 'Save changes' : 'Register bin' ?></button>
</form>
