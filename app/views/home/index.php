<?php
/**
 * Dashboard view.
 * Module : Shared core - EcoCampus Waste Management System
 */
?>

<h1>EcoCampus Waste Management System</h1>

<p class="lead">
    Welcome back, <?= e($user->getFullName()) ?>.
</p>

<?php if ($user->isAdmin()): ?>

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

        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Bin code</th>
                        <th>Location</th>
                        <th>Category</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($fullBins as $bin): ?>
                        <tr>
                            <td><?= e($bin->getBinCode()) ?></td>
                            <td><?= e($bin->getLocation()?->getFullLabel() ?? 'Unknown') ?></td>
                            <td><?= e($bin->getCategory()?->getCategoryName() ?? '-') ?></td>
                            <td>
                                <span class="badge badge-full">
                                    <?= e($bin->getFillStatus()) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="empty">No bins are currently marked as full.</p>
    <?php endif; ?>

<?php elseif ($user->isCleaner()): ?>

    <h2>Cleaner dashboard</h2>

    <section class="stats">
        <div class="stat">
            <span class="stat-value"><?= (int) $todayCount ?></span>
            <span class="stat-label">Today’s tasks</span>
        </div>

        <div class="stat">
            <span class="stat-value"><?= (int) $assignedCount ?></span>
            <span class="stat-label">Pending assignments</span>
        </div>

        <div class="stat">
            <span class="stat-value"><?= (int) $completedCount ?></span>
            <span class="stat-label">Completed assignments</span>
        </div>
    </section>

    <div class="page-heading">
        <div>
            <h2>My current assignments</h2>
            <p class="lead">These are the collection tasks assigned to your account.</p>
        </div>

        <a class="button" href="<?= url('schedule/my') ?>">
            View all assignments
        </a>
    </div>

    <?php if (!empty($cleanerAssignments)): ?>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Assignment</th>
                        <th>Date/time</th>
                        <th>Bin/location</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($cleanerAssignments as $assignment): ?>
                        <tr>
                            <td>#<?= e((string) $assignment['assignment_id']) ?></td>

                            <td>
                                <?= e((string) $assignment['schedule_date']) ?><br>
                                <small><?= e((string) $assignment['time_slot']) ?></small>
                            </td>

                            <td>
                                <strong><?= e((string) $assignment['bin_code']) ?></strong><br>
                                <small>
                                    <?=
                                    e(trim(
                                                    ($assignment['building_name'] ?? '') . ' ' .
                                                    ($assignment['floor_no'] ?? '') . ' ' .
                                                    ($assignment['location_name'] ?? '')
                                            ))
                                    ?>
                                </small>
                            </td>

                            <td><?= e((string) $assignment['reason']) ?></td>

                            <td>
                                <span class="badge badge-status">
            <?= e((string) $assignment['assignment_status']) ?>
                                </span>
                            </td>

                            <td>
                                <a 
                                    class="button button-secondary button-small" 
                                    href="<?= url('schedule/complete/' . $assignment['assignment_id']) ?>"
                                    >
                                    Complete
                                </a>
                            </td>
                        </tr>
        <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="empty">You do not have any pending assignments.</p>
    <?php endif; ?>

    <?php if (!empty($fullBins)): ?>
        <h2>Bins needing collection</h2>

        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Bin code</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
        <?php foreach ($fullBins as $bin): ?>
                        <tr>
                            <td><?= e($bin->getBinCode()) ?></td>
                            <td><?= e($bin->getLocation()?->getFullLabel() ?? 'Unknown') ?></td>
                            <td>
                                <span class="badge badge-full">
            <?= e($bin->getFillStatus()) ?>
                                </span>
                            </td>
                        </tr>
        <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="empty">No bins are currently marked as full.</p>
    <?php endif; ?>

<?php else: ?>

    <h2>Reporter dashboard</h2>

    <section class="stats">
        <div class="stat">
            <span class="stat-value"><?= (int) $myComplaintCount ?></span>
            <span class="stat-label">My complaints</span>
        </div>

        <div class="stat">
            <span class="stat-value"><?= (int) $pendingComplaintCount ?></span>
            <span class="stat-label">Pending complaints</span>
        </div>

        <div class="stat">
            <span class="stat-value"><?= (int) $resolvedComplaintCount ?></span>
            <span class="stat-label">Resolved complaints</span>
        </div>
    </section>

    <div class="content-card">
        <h2>Complaint actions</h2>

        <p>
            You can submit waste-related complaints and track your own complaint progress.
        </p>

        <div class="button-row">
            <a class="button" href="<?= url('complaint/create') ?>">
                Report issue
            </a>

            <a class="button button-secondary" href="<?= url('complaint') ?>">
                View my complaints
            </a>
        </div>
    </div>

<?php endif; ?>