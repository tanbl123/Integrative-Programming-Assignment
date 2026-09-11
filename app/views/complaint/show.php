<?php /** Complaint details/history. Author: Tan Boon Leong (2402865). Module: Complaint / Report Management. */ ?>

<?php
// Decided by ComplaintService::canEdit() so the button and the service agree:
// only the reporter who wrote a complaint may change its details, and only
// while nobody has acted on it.
$canEditComplaint = $canEdit;

// Decided by ComplaintService::canDelete() so the button and the service
// agree: a Reporter may withdraw only an untouched complaint, an
// Administrator may delete only from New or a final state.
$canDeleteComplaint = $canDelete;
$removeLabel = $isAdmin ? 'Delete complaint' : 'Withdraw complaint';
$removeConfirm = $isAdmin
    ? 'Delete this complaint? Its history is retained.'
    : 'Withdraw this complaint? You can submit a new one later if needed.';
?>

<div class="page-heading">
    <div>
        <p class="eyebrow"><?= e($complaint->getNumber()) ?></p>
        <h1><?= e($complaint->getType()) ?></h1>
        <p class="lead">
            <?= e($complaint->getBin()?->getBinCode() ?? 'Unknown bin') ?>
            ·
            <?= e($complaint->getBin()?->getLocation()?->getFullLabel() ?? '') ?>
        </p>
    </div>

    <div class="button-row">
        <?php if ($canEditComplaint): ?>
            <a class="button button-secondary" href="<?= url('complaint/edit/' . $complaint->getKey()) ?>">
                Edit complaint
            </a>
        <?php endif; ?>

        <?php if ($canDeleteComplaint): ?>
            <form 
                method="post" 
                action="<?= url('complaint/delete/' . $complaint->getKey()) ?>" 
                data-confirm="<?= e($removeConfirm) ?>"
            >
                <?= csrfField() ?>
                <button class="button button-danger" type="submit">
                    <?= e($removeLabel) ?>
                </button>
            </form>
        <?php endif; ?>

        <a class="button button-secondary" href="<?= url('complaint') ?>">
            Back
        </a>
    </div>
</div>

<section class="detail-grid">
    <div>
        <span>Status</span>
        <strong><?= e($complaint->getStatus()) ?></strong>
    </div>

    <div>
        <span>Reporter</span>
        <strong><?= e($complaint->getReporter()?->getFullName() ?? 'Unknown') ?></strong>
    </div>

    <div>
        <span>Submitted</span>
        <strong><?= e($complaint->getCreatedAt()) ?></strong>
    </div>
</section>

<?php /* Rendered from data returned by the Bin module's REST web service. */ ?>
<section class="content-card">
    <h2>Live bin details</h2>
    <p class="lead">Retrieved from the Bin &amp; Location module web service at page load.</p>
    <?php if ($binInfo !== null): ?>
        <div class="table-scroll">
            <table class="table">
                <tbody>
                    <tr><th>Bin code</th><td><?= e((string) ($binInfo['code'] ?? '-')) ?></td></tr>
                    <tr><th>Current fill status</th><td><span class="badge badge-status"><?= e((string) ($binInfo['fill_status'] ?? '-')) ?></span></td></tr>
                    <tr><th>Location</th><td><?= e((string) ($binInfo['location']['label'] ?? '-')) ?></td></tr>
                    <tr><th>Waste category</th><td><?= e((string) ($binInfo['category']['name'] ?? '-')) ?></td></tr>
                    <tr><th>Capacity</th><td><?= $binInfo['capacity_litre'] === null ? '-' : (int) $binInfo['capacity_litre'] . ' litres' ?></td></tr>
                    <tr><th>Bin last updated</th><td><?= e((string) ($binInfo['last_updated'] ?? '-')) ?></td></tr>
                    <tr><th>Service request ID</th><td><code><?= e((string) $binServiceRequestId) ?></code></td></tr>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="empty">
            Live bin details are unavailable<?= $binServiceError === null ? '.' : ': ' . e($binServiceError) ?>
            The complaint details below are unaffected.
        </p>
    <?php endif; ?>
</section>

<section class="content-card">
    <h2>Description</h2>
    <p class="pre-wrap"><?= e($complaint->getDescription()) ?></p>

    <?php /* Earlier versions, collapsed. A complaint that was never edited
             shows nothing at all, so the common case stays quiet; where one was
             edited, both the reporter and the administrator can read what it
             said before and see who changed it. The current wording above is
             always the complaint as it stands now. */ ?>
    <?php if ($revisions !== []): ?>
        <details class="revisions">
            <summary>
                <?= count($revisions) === 1
                    ? 'This complaint was edited once'
                    : 'This complaint was edited ' . count($revisions) . ' times' ?>
                &mdash; show what it said before
            </summary>

            <?php foreach ($revisions as $index => $revision): ?>
                <article class="revision">
                    <p class="field-help">
                        Version <?= count($revisions) - $index ?>,
                        replaced <?= e($revision->getEditedAt()) ?>
                        by <?= e($revision->getEditedBy()?->getFullName() ?? 'a deleted account') ?>
                    </p>

                    <p class="field-help">
                        <?= e($revision->getBin()?->getBinCode() ?? 'Bin no longer on record') ?>
                        &middot; <?= e($revision->getType()) ?>
                    </p>

                    <p class="pre-wrap"><?= e($revision->getDescription()) ?></p>

                    <?php /* The photograph this edit replaced. It is marked
                             superseded rather than deleted, so swapping a
                             damning photo for an innocuous one is as visible as
                             rewriting the words. */ ?>
                    <?php if ($revision->getAttachment() !== null): ?>
                        <a
                            class="current-photo"
                            href="<?= url('complaint/attachment/' . $revision->getAttachment()->getKey()) ?>"
                            target="_blank"
                        >
                            <img src="<?= url('complaint/attachment/' . $revision->getAttachment()->getKey()) ?>" alt="">
                            <span>
                                <strong>Photo replaced by this edit</strong>
                                <small><?= e($revision->getAttachment()->getReadableSize()) ?></small>
                            </span>
                        </a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </details>
    <?php endif; ?>
</section>

<?php if ($attachments !== []): ?>
    <section class="content-card">
        <h2>Photo evidence</h2>

        <?php /* A neutral name is shown rather than the reporter's own filename,
                 which says nothing about the evidence and is read by the
                 administrator as well as the reporter. The original filename
                 stays recorded in the attachment row. */ ?>
        <?php foreach ($attachments as $attachment): ?>
            <a href="<?= url('complaint/attachment/' . $attachment->getKey()) ?>" target="_blank">
                <img 
                    class="complaint-photo" 
                    src="<?= url('complaint/attachment/' . $attachment->getKey()) ?>" 
                    alt="Photo submitted with this complaint"
                >
            </a>

            <p class="field-help">
                <strong><?= e($attachment->getDisplayName()) ?></strong>
                &middot; <?= e($attachment->getReadableSize()) ?>
                &middot; uploaded <?= e($attachment->getUploadedAt()) ?>
            </p>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if (UserPermissions::can($user, 'complaint.manage') && $statuses !== []): ?>
    <form 
        method="post" 
        action="<?= url('complaint/update-status/' . $complaint->getKey()) ?>" 
        class="form-card"
    >
        <h2>Update status</h2>

        <?= csrfField() ?>

        <label>
            Next status
            <select name="complaint_status">
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>">
                        <?= e($status) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if (!empty($errors['complaint_status'])): ?>
                <span class="field-error"><?= e($errors['complaint_status']) ?></span>
            <?php endif; ?>
        </label>

        <label>
            Remarks
            <textarea name="remarks" rows="3" maxlength="255"></textarea>

            <?php if (!empty($errors['remarks'])): ?>
                <span class="field-error"><?= e($errors['remarks']) ?></span>
            <?php endif; ?>
        </label>

        <button class="button" type="submit">
            Save status
        </button>
    </form>

<?php elseif (UserPermissions::can($user, 'complaint.manage')): ?>

    <?php if (!empty($errors['complaint_status'])): ?>
        <div class="alert alert-error">
            <?= e($errors['complaint_status']) ?>
        </div>
    <?php endif; ?>

    <section class="info-card">
        <strong>Lifecycle complete</strong>
        <p>This complaint is in a final state and cannot be changed again.</p>
    </section>

<?php endif; ?>

<section class="content-card">
    <h2>Status history</h2>

    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Changed by</th>
                    <th>Transition</th>
                    <th>Remarks</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($history as $entry): ?>
                    <tr>
                        <td><?= e($entry->getUpdatedAt()) ?></td>
                        <td><?= e($entry->getUpdatedBy()?->getFullName() ?? 'System') ?></td>
                        <td>
                            <?php /* An edit carries the same status on both sides, so the
                                     arrow would read "New → New". change_type is what
                                     separates the two kinds of row. */ ?>
                            <?php if ($entry->isDetailsEdit()): ?>
                                Details edited
                            <?php else: ?>
                                <?= e($entry->getOldStatus() ?? 'Created') ?>
                                →
                                <?= e($entry->getNewStatus()) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e($entry->getRemarks() ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($history === []): ?>
                    <tr>
                        <td colspan="4" class="empty">No status history has been recorded.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>