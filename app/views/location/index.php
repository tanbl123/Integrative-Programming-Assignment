<?php
/**
 * Searchable campus location register.
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
?>
<div class="page-heading">
    <div>
        <h1>Campus locations</h1>
        <p class="lead">Locations are shared reference records for bins and complaint reporting.</p>
    </div>
    <?php if ($user->isAdmin()): ?>
        <a class="button" href="<?= url('location/create') ?>">Add location</a>
    <?php endif; ?>
</div>

<form method="get" action="<?= url('location') ?>" class="filter-panel filter-compact">
    <label>
        Search
        <input type="search" name="q" value="<?= e($query) ?>" placeholder="Building, floor, or area">
    </label>
    <button class="button button-secondary" type="submit">Search</button>
</form>

<div class="card-grid">
    <?php if ($locations === []): ?>
        <p class="empty">No locations match your search.</p>
    <?php endif; ?>
    <?php foreach ($locations as $location): ?>
        <article class="location-card">
            <div>
                <p class="eyebrow"><?= e($location->getBuildingName() ?? 'Campus') ?></p>
                <h2><?= e($location->getLocationName()) ?></h2>
                <p><?= e($location->getFloorNo() ?? 'Floor not specified') ?></p>
                <p class="muted"><?= e($location->getDescription() ?? 'No description') ?></p>
            </div>
            <div class="location-card-footer">
                <span><?= count($location->getBins()) ?> bin(s)</span>
                <?php if ($user->isAdmin()): ?>
                    <a href="<?= url('location/edit/' . $location->getKey()) ?>">Edit</a>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
