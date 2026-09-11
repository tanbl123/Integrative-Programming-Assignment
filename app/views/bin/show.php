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
            <a class="button" href="<?= url('bin/status/' . $bin->getKey()) ?>">
                Update status
            </a>
        <?php endif; ?>
        <a class="button button-secondary" href="<?= url('bin') ?>">Back to bins</a>
    </div>
</div>

<section class="detail-grid">
    <div><span>Location</span><strong><?= e($bin->getLocation()?->getFullLabel() ?? 'Unknown') ?></strong></div>
    <div><span>Waste category</span><strong><?= e($bin->getCategory()?->getCategoryName() ?? 'Unknown') ?></strong></div>
    <div><span>Capacity</span><strong><?= $bin->getCapacityLitre() === null ? 'Not specified' : $bin->getCapacityLitre() . ' litres' ?></strong></div>
    <div><span>Fill status</span><strong><?= e($bin->getFillStatus()) ?></strong></div>
    <div><span>Update status</span><strong><?= $bin->isActive() ? 'Active' : 'Inactive' ?></strong></div>
    <div><span>Last updated</span><strong><?= e($bin->getLastUpdated()) ?></strong></div>
</section>

<section class="content-card">
    <h2>Collection schedule</h2>
    <?php if ($scheduleStatus !== null): ?>
        <?php $nextCollection = $scheduleStatus['nextCollection'] ?? null; ?>
        <div class="detail-grid">
            <div>
                <span>Scheduling status</span>
                <strong><?= e((string) ($scheduleStatus['scheduleStatus'] ?? 'Not Scheduled')) ?></strong>
            </div>
            <div>
                <span>Open assignments</span>
                <strong><?= (int) ($scheduleStatus['openAssignmentCount'] ?? 0) ?></strong>
            </div>
            <div>
                <span>Next collection</span>
                <strong>
                    <?= $nextCollection === null
                        ? 'No collection assigned'
                        : e((string) ($nextCollection['date'] ?? 'Unknown date') . ' · ' . (string) ($nextCollection['timeSlot'] ?? 'Unknown time')) ?>
                </strong>
            </div>
            <div>
                <span>Assigned cleaner</span>
                <strong><?= e((string) ($nextCollection['cleaner']['name'] ?? 'Not assigned')) ?></strong>
            </div>
        </div>
        <p class="muted">Loaded from the Scheduling REST service · Request <?= e((string) $scheduleServiceRequestId) ?></p>
    <?php else: ?>
        <p>The Scheduling service is currently unavailable. Bin details are still available.</p>
        <?php if ($scheduleServiceError !== null): ?>
            <p class="muted"><?= e($scheduleServiceError) ?> · Request <?= e((string) $scheduleServiceRequestId) ?></p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($user->isAdmin() && $bin->isActive()): ?>
    <form method="post" action="<?= url('bin/deactivate/' . $bin->getKey()) ?>" class="danger-zone" onsubmit="return confirm('Deactivate this bin? Historical records will be retained.');">
        <?= csrfField() ?>
        <div>
            <strong>Deactivate bin</strong>
            <p>Remove this bin from service after resolving its open work. You can reactivate it later.</p>
        </div>
        <button type="submit" class="button button-danger">Deactivate</button>
    </form>
<?php endif; ?>

<?php if ($user->isAdmin() && !$bin->isActive()): ?>
    <form method="post" action="<?= url('bin/reactivate/' . $bin->getKey()) ?>" class="danger-zone">
        <?= csrfField() ?>
        <div><strong>Reactivate bin</strong><p>Return this bin to service while keeping its history.</p></div>
        <button type="submit" class="button">Reactivate</button>
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
