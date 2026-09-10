<?php
/**
 * Cleaner assignment list.
 * Author : Ng Zi Zhang (2406898)
 * Module : Collection Scheduling & Assignment
 */
?>

<div class="page-heading">
    <div>
        <h1>My collection assignments</h1>
        <p class="lead">Tasks assigned to your Cleaner account.</p>
    </div>
</div>

<form method="get" action="<?= url('schedule/my') ?>" class="filter-panel assignment-filter-panel">
    <label>
        Status
        <select name="status">
            <option value="">All statuses</option>

            <?php foreach (['Assigned', 'Completed', 'Skipped'] as $option): ?>
                <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>>
                    <?= e($option) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    
    <label>
        Route view
        <select name="route_view">
            <option value="" <?= ($routeView ?? '') === '' ? 'selected' : '' ?>>
                All assignments
            </option>
            <option value="today" <?= ($routeView ?? '') === 'today' ? 'selected' : '' ?>>
                Today
            </option>
            <option value="week" <?= ($routeView ?? '') === 'week' ? 'selected' : '' ?>>
                This week
            </option>
        </select>
    </label>

    <div class="filter-actions">
        <button class="button" type="submit">
            Apply
        </button>

        <a class="button button-secondary" href="<?= url('schedule/my') ?>">
            Clear
        </a>
    </div>
</form>

<div class="table-scroll">
    <table class="table">
        <thead>
            <tr>
                <th>Assignment</th>
                <th>Date/time</th>
                <th>Bin/location</th>
                <th>Priority</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($assignments as $assignment): ?>
                <?php
                $schedule = $assignment->getSchedule();
                $bin = $assignment->getBin();
                $location = $bin?->getLocation();
                ?>

                <tr>
                    <td>
                        #<?= e((string) $assignment->getKey()) ?>
                    </td>

                    <td>
                        <?= e($schedule?->getDate() ?? 'No date') ?><br>
                        <small><?= e($schedule?->getTimeSlot() ?? 'No time slot') ?></small>
                    </td>

                    <td>
                        <strong><?= e($bin?->getBinCode() ?? 'Unknown bin') ?></strong><br>
                        <small><?= e($location?->getFullLabel() ?? 'Unknown location') ?></small>
                    </td>

                    <td>
                        <span class="badge badge-status">
                            <?= e($assignment->getPriority()) ?>
                        </span>
                    </td>

                    <td>
                        <?= e($assignment->getReason() ?? 'No reason provided') ?>
                    </td>

                    <td>
                        <span class="badge badge-status">
                            <?= e($assignment->getStatus()) ?>
                        </span>
                    </td>

                    <td>
                        <?php if ($assignment->getStatus() === 'Assigned'): ?>
                            <a 
                                class="button button-secondary button-small" 
                                href="<?= url('schedule/complete/' . $assignment->getKey()) ?>"
                                >
                                Complete
                            </a>
                        <?php else: ?>
                            <a 
                                class="button button-secondary button-small" 
                                href="<?= url('schedule/show/' . $schedule?->getKey()) ?>"
                                >
                                View
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ($assignments === []): ?>
                <tr>
                    <td colspan="7" class="empty">
                        No assignments match this filter.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>