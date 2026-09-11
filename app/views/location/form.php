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

<form
    method="post"
    action="<?= $action ?>"
    class="form-card"
    id="locationForm"
    data-default-lat="<?= e((string) MAP_DEFAULT_LAT) ?>"
    data-default-lng="<?= e((string) MAP_DEFAULT_LNG) ?>"
    data-default-zoom="<?= (int) MAP_DEFAULT_ZOOM ?>"
>
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

    <section class="location-picker-section" aria-labelledby="map-pin-heading">
        <div class="section-heading-compact">
            <div>
                <h2 id="map-pin-heading">OpenStreetMap pin</h2>
                <p class="muted">Click the exact campus position so cleaners and reporters can find this area.</p>
            </div>
            <span class="badge badge-muted">Optional</span>
        </div>

        <div class="location-picker-layout">
            <div
                id="locationPickerMap"
                class="location-map location-picker-map"
                role="application"
                aria-label="Choose this location on OpenStreetMap"
            >
                <p class="map-loading">Loading OpenStreetMap…</p>
            </div>

            <div class="coordinate-panel">
                <div class="coordinate-grid">
                    <label>
                        Latitude
                        <input
                            type="number"
                            name="latitude"
                            id="locationLatitude"
                            value="<?= e((string) ($values['latitude'] ?? '')) ?>"
                            min="-90"
                            max="90"
                            step="0.0000001"
                            placeholder="e.g. 3.2151180"
                        >
                        <?php if (!empty($errors['latitude'])): ?><span class="field-error"><?= e($errors['latitude']) ?></span><?php endif; ?>
                    </label>
                    <label>
                        Longitude
                        <input
                            type="number"
                            name="longitude"
                            id="locationLongitude"
                            value="<?= e((string) ($values['longitude'] ?? '')) ?>"
                            min="-180"
                            max="180"
                            step="0.0000001"
                            placeholder="e.g. 101.7283450"
                        >
                        <?php if (!empty($errors['longitude'])): ?><span class="field-error"><?= e($errors['longitude']) ?></span><?php endif; ?>
                    </label>
                </div>
                <?php if (!empty($errors['coordinates'])): ?>
                    <span class="field-error coordinate-error"><?= e($errors['coordinates']) ?></span>
                <?php endif; ?>
                <div class="button-row">
                    <button type="button" class="button button-secondary button-small" id="useCurrentPosition">
                        Use my position
                    </button>
                    <button type="button" class="button button-secondary button-small" id="clearLocationPin">
                        Remove pin
                    </button>
                </div>
                <p class="map-picker-status muted" id="mapPickerStatus" aria-live="polite">
                    <?= ($values['latitude'] ?? '') !== '' && ($values['longitude'] ?? '') !== ''
                        ? 'Saved pin loaded. Click elsewhere to move it.'
                        : 'No pin selected yet.' ?>
                </p>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit" class="button"><?= $isEdit ? 'Save changes' : 'Add location' ?></button>
        <a class="button button-secondary" href="<?= url('location') ?>">Cancel</a>
    </div>
</form>
