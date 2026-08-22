<?php
/**
 * Dashboard view.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Shared core - EcoCampus Waste Management System
 *
 * Proves the whole stack works end to end: the router found this controller,
 * the ORM read real rows out of MySQL, and the view rendered them.
 */
?>
<h1>EcoCampus Waste Management System</h1>
<p class="lead">
    Campus waste monitoring, complaint handling and collection scheduling.
</p>

<section class="stats">
    <div class="stat">
        <span class="stat-value"><?= (int) $binCount ?></span>
        <span class="stat-label">Bins registered</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= (int) $userCount ?></span>
        <span class="stat-label">Users</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= count($fullBins) ?></span>
        <span class="stat-label">Bins needing collection</span>
    </div>
</section>

<?php if (!empty($fullBins)): ?>
    <h2>Bins currently full</h2>
    <table class="table">
        <thead>
            <tr><th>Bin code</th><th>Location</th><th>Category</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php foreach ($fullBins as $bin): ?>
            <tr>
                <td><?= e($bin->getBinCode()) ?></td>
                <td><?= e($bin->getLocation()?->getFullLabel() ?? 'Unknown') ?></td>
                <td><?= e($bin->getCategory()?->getCategoryName() ?? '-') ?></td>
                <td><span class="badge badge-full"><?= e($bin->getFillStatus()) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="empty">No bins are currently marked as full.</p>
<?php endif; ?>
