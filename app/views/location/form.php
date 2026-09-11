<?php
/**
 * Administrator location create/edit form.
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
$isEdit = $mode === 'edit';
$action = $isEdit ? url('location/update/' . $location->getKey()) : url('location/store');
?>
<div class="page-heading">
    <div>
        <h1><?= $isEdit ? 'Edit location' : 'Add campus location' ?></h1>
        <p class="lead">Describe where bins can be physically found.</p>
    </div>
    <a class="button button-secondary" href="<?= url('location') ?>">Cancel</a>
</div>

<form method="post" action="<?= $action ?>" class="form-card">
    <?= csrfField() ?>
    <div class="form-grid">
        <label>
            Location name
            <input name="location_name" value="<?= e((string) ($values['location_name'] ?? '')) ?>" maxlength="100" required placeholder="e.g. Main Lobby">
            <?php if (!empty($errors['location_name'])): ?><span class="field-error"><?= e($errors['location_name']) ?></span><?php endif; ?>
        </label>
        <label>
            Building
            <select name="building_name" required>
                <option value="">Select building</option>

                <?php foreach (['Block A', 'Block B', 'Block C', 'Block D', 'Open Area'] as $building): ?>
                    <option value="<?= e($building) ?>" <?= ($values['building_name'] ?? '') === $building ? 'selected' : '' ?>>
                        <?= e($building) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if (!empty($errors['building_name'])): ?>
                <span class="field-error"><?= e($errors['building_name']) ?></span>
            <?php endif; ?>
        </label>
        <label>
            Floor
            <input name="floor_no" value="<?= e((string) ($values['floor_no'] ?? '')) ?>" maxlength="20" placeholder="e.g. Level 1">
            <?php if (!empty($errors['floor_no'])): ?><span class="field-error"><?= e($errors['floor_no']) ?></span><?php endif; ?>
        </label>
    </div>
    <label>
        Area description
        <textarea name="description" rows="4" maxlength="255" placeholder="e.g. Beside the main entrance"><?= e((string) ($values['description'] ?? '')) ?></textarea>
        <?php if (!empty($errors['description'])): ?><span class="field-error"><?= e($errors['description']) ?></span><?php endif; ?>
    </label>
    <button type="submit" class="button"><?= $isEdit ? 'Save changes' : 'Add location' ?></button>
</form>
