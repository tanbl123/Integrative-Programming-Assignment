<?php
/**
 * Schedule details.
 * Author : Ng Zi Zhang (2406898)
 * Module : Collection Scheduling & Assignment
 */
$canManage = UserPermissions::can($user, 'schedule.manage');
$isCleanerView = $user->isCleaner() && !$canManage;
$backUrl = $canManage ? url('schedule') : url('schedule/my');
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">
            <?= $isCleanerView ? 'My schedule task list' : 'Schedule #' . $schedule->getKey() ?>
        </p>

        <h1>
            <?= e($schedule->getDate()) ?> · <?= e($schedule->getTimeSlot()) ?>
        </h1>

        <p class="lead">
            <?= e($schedule->getStrategy()) ?> Strategy · <?= e($schedule->getStatus()) ?>
        </p>
    </div>

    <div class="button-row">
        <?php if ($canManage && $schedule->getStatus() === 'Planned'): ?>
            <a class="button" href="<?= url('schedule/edit/' . $schedule->getKey()) ?>">
                Edit
            </a>
        <?php endif; ?>

        <a class="button button-secondary" href="<?= $backUrl ?>">
            Back
        </a>
    </div>
</div>

<?php if ($schedule->getNotes()): ?>
    <section class="content-card">
        <h2>Notes</h2>
        <p><?= e($schedule->getNotes()) ?></p>
    </section>
<?php endif; ?>

<div class="table-scroll">
    <table class="table">
        <thead>
            <tr>
                <th>Assignment</th>
                <th>Bin/location</th>

                <?php if ($canManage): ?>
                    <th>Cleaner</th>
                <?php endif; ?>

                <th>Priority</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($assignments as $assignment): ?>
                <?php
                $bin = $assignment->getBin();
                $location = $bin?->getLocation();
                ?>

                <tr>
                    <td>
                        #<?= e((string) $assignment->getKey()) ?>
                    </td>

                    <td>
                        <strong><?= e($bin?->getBinCode() ?? 'Unknown bin') ?></strong><br>
                        <small><?= e($location?->getFullLabel() ?? 'Unknown location') ?></small>
                    </td>

                    <?php if ($canManage): ?>
                        <td>
                            <?= e($assignment->getCleaner()?->getFullName() ?? 'Unknown cleaner') ?>
                        </td>
                    <?php endif; ?>

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
                        <?php if ($assignment->getStatus() === 'Assigned' && $assignment->getCleanerId() === $user->getKey()): ?>
                            <a 
                                class="button button-secondary button-small" 
                                href="<?= url('schedule/complete/' . $assignment->getKey()) ?>"
                                >
                                Complete
                            </a>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ($assignments === []): ?>
                <tr>
                    <td colspan="<?= $canManage ? '7' : '6' ?>" class="empty">
                        No assignments are available for this schedule.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($canManage && $schedule->getStatus() === 'Planned'): ?>
    <form 
        method="post" 
        action="<?= url('schedule/cancel/' . $schedule->getKey()) ?>" 
        class="danger-zone" 
        data-confirm="Cancel this schedule and skip its open assignments?"
        >
            <?= csrfField() ?>

        <div>
            <strong>Cancel schedule</strong>
            <p>Completed records remain; open assignments are marked Skipped.</p>
        </div>

        <button class="button button-danger" type="submit">
            Cancel schedule
        </button>
    </form>
<?php endif; ?>

<?php if ($canManage && in_array($schedule->getStatus(), ['Cancelled', 'Completed'], true)): ?>
    <form 
        method="post" 
        action="<?= url('schedule/delete/' . $schedule->getKey()) ?>" 
        class="danger-zone" 
        data-confirm="Delete this schedule? Collection history will be retained."
        >
            <?= csrfField() ?>

        <div>
            <strong>Delete schedule</strong>
            <p>Remove this schedule from active lists while retaining collection records.</p>
        </div>

        <button class="button button-danger" type="submit">
            Delete schedule
        </button>
    </form>
<?php endif; ?>