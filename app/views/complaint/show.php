<?php /** Complaint details/history. Author: Tan Boon Leong (2402865). Module: Complaint / Report Management. */ ?>

<?php
$canEditComplaint =
    $complaint->getStatus() === Complaint::STATUS_NEW &&
    !$complaint->hasOpenAssignments();

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
</section>

<?php if ($attachments !== []): ?>
    <section class="content-card">
        <h2>Photo evidence</h2>

        <?php foreach ($attachments as $attachment): ?>
            <a href="<?= url('complaint/attachment/' . $attachment->getKey()) ?>" target="_blank">
                <img 
                    class="complaint-photo" 
                    src="<?= url('complaint/attachment/' . $attachment->getKey()) ?>" 
                    alt="Complaint evidence"
                >
            </a>
            <p><?= e($attachment->getOriginalName()) ?></p>
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
                            <?= e($entry->getOldStatus() ?? 'Created') ?>
                            →
                            <?= e($entry->getNewStatus()) ?>
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