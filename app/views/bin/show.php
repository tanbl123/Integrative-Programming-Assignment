<?php
/**
 * Bin details and status audit history.
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Bin record</p>
        <h1><?= e($bin->getBinCode()) ?></h1>
    </div>
    <div class="button-row">
        <?php if ($user->isAdmin()): ?>
            <a class="button" href="<?= url('bin/edit/' . $bin->getKey()) ?>">Edit bin</a>
        <?php elseif ($user->isCleaner() && $bin->isActive()): ?>
            <a class="button" href="<?= url('bin/status/' . $bin->getKey()) ?>">Record status</a>
        <?php endif; ?>
        <a class="button button-secondary" href="<?= url('bin') ?>">Back to bins</a>
    </div>
</div>

<section class="detail-grid">
    <div><span>Location</span><strong><?= e($bin->getLocation()?->getFullLabel() ?? 'Unknown') ?></strong></div>
    <div><span>Waste category</span><strong><?= e($bin->getCategory()?->getCategoryName() ?? 'Unknown') ?></strong></div>
    <div><span>Capacity</span><strong><?= $bin->getCapacityLitre() === null ? 'Not specified' : $bin->getCapacityLitre() . ' litres' ?></strong></div>
    <div><span>Fill status</span><strong><?= e($bin->getFillStatus()) ?></strong></div>
    <div><span>Record status</span><strong><?= $bin->isActive() ? 'Active' : 'Inactive' ?></strong></div>
    <div><span>Last updated</span><strong><?= e($bin->getLastUpdated()) ?></strong></div>
</section>

<?php if ($user->isAdmin() && $bin->isActive()): ?>
    <form method="post" action="<?= url('bin/deactivate/' . $bin->getKey()) ?>" class="danger-zone" onsubmit="return confirm('Deactivate this bin? Historical records will be retained.');">
        <?= csrfField() ?>
        <div>
            <strong>Deactivate bin</strong>
            <p>Use this when a bin is permanently removed from service.</p>
        </div>
        <button type="submit" class="button button-danger">Deactivate</button>
    </form>
<?php endif; ?>

<h2>Status history</h2>
<div class="table-scroll">
    <table class="table">
        <thead><tr><th>Time</th><th>Cleaner</th><th>Change</th><th>Remarks</th></tr></thead>
        <tbody>
        <?php if ($updates === []): ?>
            <tr><td colspan="4" class="empty">No status updates have been recorded.</td></tr>
        <?php endif; ?>
        <?php foreach ($updates as $update): ?>
            <tr>
                <td><?= e($update->getUpdatedAt()) ?></td>
                <td><?= e($update->getCleaner()?->getFullName() ?? 'Unknown') ?></td>
                <td><?= e($update->getOldStatus()) ?> → <?= e($update->getNewStatus()) ?></td>
                <td><?= e($update->getRemarks() ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
