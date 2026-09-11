<?php /** Complaint notifications. Author: Tan Boon Leong (2402865). Module: Complaint / Report Management. */ ?>

<div class="page-heading">
    <div>
        <h1>Notifications</h1>
        <p class="lead">
            <?php if ($user->isAdmin()): ?>
                Raised automatically as complaints are submitted, moved on and withdrawn.
                Administrators share this queue, so reading an item clears it for everyone.
            <?php elseif ($user->isReporter()): ?>
                What has happened to the complaints you reported.
            <?php else: ?>
                Complaint notices are not part of your role.
            <?php endif; ?>
        </p>
    </div>

    <?php if ($unreadCount > 0): ?>
        <form method="post" action="<?= url('complaint-notification/read-all') ?>">
            <?= csrfField() ?>
            <button class="button button-secondary" type="submit">
                Mark all as read
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if ($notifications === []): ?>
    <div class="content-card">
        <p class="empty-state">
            <?= $user->isCleaner()
                ? 'You have no complaint notifications. Your work arrives as assignments instead.'
                : 'You have no notifications yet.' ?>
        </p>
    </div>
<?php else: ?>
    <ul class="notification-list">
        <?php foreach ($notifications as $notification): ?>
            <?php $complaint = $notification->getComplaint(); ?>
            <li class="notification<?= $notification->isRead() ? '' : ' notification-unread' ?>">
                <div class="notification-body">
                    <p class="notification-title">
                        <?= e($notification->getTitle()) ?>
                        <?php if (!$notification->isRead()): ?>
                            <span class="badge badge-full">New</span>
                        <?php endif; ?>
                    </p>
                    <p class="notification-text"><?= e($notification->getBody()) ?></p>
                    <p class="notification-meta">
                        <?= e($notification->getCreatedAt()) ?>
                        <?php /* A withdrawn complaint is soft-deleted, so its page is gone.
                                 The notice is all that is left of it, which is exactly why
                                 these rows are kept rather than hidden with the complaint. */ ?>
                        <?php if ($complaint !== null && !$complaint->isDeleted()): ?>
                            &middot;
                            <a href="<?= url('complaint/show/' . $complaint->getKey()) ?>">
                                Open <?= e($complaint->getNumber()) ?>
                            </a>
                        <?php elseif ($complaint !== null): ?>
                            &middot; <span class="notification-gone">
                                <?= e($complaint->getNumber()) ?> is no longer open
                            </span>
                        <?php endif; ?>
                    </p>
                </div>

                <?php if (!$notification->isRead()): ?>
                    <form method="post"
                          action="<?= url('complaint-notification/read/' . $notification->getKey()) ?>">
                        <?= csrfField() ?>
                        <button class="button button-secondary" type="submit">Mark as read</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
