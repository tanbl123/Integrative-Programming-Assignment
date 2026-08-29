<?php
/**
 * Searchable bin register.
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
?>
<div class="page-heading">
    <div>
        <h1>Campus bins</h1>
        <p class="lead">Search bins by code, building, location, or current fill status.</p>
    </div>
    <?php if ($user->isAdmin()): ?>
        <a class="button" href="<?= url('bin/create') ?>">Register bin</a>
    <?php endif; ?>
</div>

<form method="get" action="<?= url('bin') ?>" class="filter-panel">
    <label>
        Search
        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Bin code or location">
    </label>
    <label>
        Status
        <select name="status">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Location
        <select name="location_id">
            <option value="">All locations</option>
            <?php foreach ($locations as $location): ?>
                <option value="<?= $location->getKey() ?>" <?= $filters['location_id'] === $location->getKey() ? 'selected' : '' ?>>
                    <?= e($location->getFullLabel()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if ($user->isAdmin()): ?>
        <label class="checkbox-label">
            <input type="checkbox" name="include_inactive" value="1" <?= $filters['include_inactive'] ? 'checked' : '' ?>>
            Include inactive bins
        </label>
    <?php endif; ?>
    <button class="button button-secondary" type="submit">Apply filters</button>
</form>

<div class="table-scroll">
    <table class="table">
        <thead>
            <tr>
                <th>Bin</th>
                <th>Location</th>
                <th>Category</th>
                <th>Capacity</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($bins === []): ?>
            <tr><td colspan="6" class="empty">No bins match the selected filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($bins as $bin): ?>
            <tr class="<?= $bin->isActive() ? '' : 'row-inactive' ?>">
                <td>
                    <a href="<?= url('bin/show/' . $bin->getKey()) ?>"><strong><?= e($bin->getBinCode()) ?></strong></a>
                    <?php if (!$bin->isActive()): ?><span class="badge badge-muted">Inactive</span><?php endif; ?>
                </td>
                <td><?= e($bin->getLocation()?->getFullLabel() ?? 'Unknown') ?></td>
                <td><?= e($bin->getCategory()?->getCategoryName() ?? 'Unknown') ?></td>
                <td><?= $bin->getCapacityLitre() === null ? '—' : $bin->getCapacityLitre() . ' L' ?></td>
                <td><span class="badge badge-status"><?= e($bin->getFillStatus()) ?></span></td>
                <td class="actions">
                    <a href="<?= url('bin/show/' . $bin->getKey()) ?>">View</a>
                    <?php if ($user->isAdmin()): ?>
                        <a href="<?= url('bin/edit/' . $bin->getKey()) ?>">Edit</a>
                    <?php elseif ($user->isCleaner() && $bin->isActive()): ?>
                        <a href="<?= url('bin/status/' . $bin->getKey()) ?>">Update status</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
