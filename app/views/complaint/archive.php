<?php /** Archived complaints. Author: Tan Boon Leong (2402865). Module: Complaint / Report Management. */ ?>

<div class="page-heading">
    <div>
        <h1>Archived complaints</h1>
        <p class="lead">
            <?= $isAdmin
                ? 'Reports that were withdrawn by their reporter, or deleted once they had been answered.'
                : 'Reports you withdrew, and reports that were closed and archived after you were told the outcome.' ?>
        </p>
    </div>

    <a class="button button-secondary" href="<?= url('complaint') ?>">Back to complaints</a>
</div>

<?php /*
 * Why this page exists.
 *
 * Removal in this module is a soft delete: the row stays, and so does the
 * status history written against it by ComplaintHistoryObserver. Until this
 * page existed nothing could reach either of them, so as far as anybody using
 * the system could tell, a complaint and the entire record of how it had been
 * handled left together - which is exactly what writing the history down was
 * meant to prevent.
 *
 * It is read-only by construction rather than by omission. Nothing here posts
 * anywhere, and the detail page an entry links to withdraws every action it
 * would normally offer; see showData() in ComplaintController.
 */ ?>
<section class="content-card">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Complaint</th>
                    <th>Bin</th>
                    <th>Issue</th>
                    <th>Last status</th>
                    <th>Reported</th>
                    <?php if ($isAdmin): ?><th>Reporter</th><?php endif; ?>
                    <th>Archived</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($complaints as $complaint): ?>
                    <?php
                    $bin = $complaint->getBin();
                    $location = $bin?->getLocation();
                    ?>
                    <tr>
                        <td><?= e($complaint->getNumber()) ?></td>

                        <td>
                            <?= e($bin?->getBinCode() ?? 'Unknown') ?><br>
                            <small><?= e($location?->getFullLabel() ?? '') ?></small>
                        </td>

                        <td><?= e($complaint->getType()) ?></td>

                        <?php /* The status it held when it was archived. A
                                 withdrawn report reads New, which is true: it
                                 was never acted on, and that is the point of
                                 the reporter being allowed to take it back. */ ?>
                        <td>
                            <span class="badge badge-status"><?= e($complaint->getStatus()) ?></span>
                        </td>

                        <td><?= e(Complaint::friendlyDate($complaint->getCreatedAt())) ?></td>

                        <?php if ($isAdmin): ?>
                            <td><?= e($complaint->getReporter()?->getFullName() ?? 'Unknown') ?></td>
                        <?php endif; ?>

                        <td><?= e(Complaint::friendlyDate((string) $complaint->getDeletedAt())) ?></td>

                        <td>
                            <a href="<?= url('complaint/show/' . $complaint->getKey()) ?>">View record</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($complaints === []): ?>
                    <tr>
                        <td colspan="<?= $isAdmin ? 8 : 7 ?>" class="empty">
                            <?= $isAdmin
                                ? 'Nothing has been archived yet.'
                                : 'You have not withdrawn any reports, and none of yours have been archived.' ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
