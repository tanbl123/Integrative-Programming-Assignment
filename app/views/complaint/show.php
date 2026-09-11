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

<?php
/* The lifecycle status is an Administrator's decision; whether a collection
   has actually been raised is the Scheduling module's record. Generating a
   Complaint Priority schedule moves the status by itself, but an
   Administrator can also set Assigned by hand here - and then forget to
   schedule anything, leaving a complaint that says it is being dealt with
   while nobody has been sent. Rather than forbid the manual move, which is
   the only way to record a collection arranged off-system, the two facts are
   shown side by side and the gap between them is named. */
$assignments = $complaint->assignmentCounts();
?>
<section class="detail-grid">
    <div>
        <span>Status</span>
        <strong><?= e($complaint->getStatus()) ?></strong>
        <?php if ($assignments['open'] > 0): ?>
            <span class="detail-note">A cleaner has been scheduled for this bin.</span>
        <?php elseif ($assignments['completed'] > 0): ?>
            <span class="detail-note">The scheduled collection has been completed.</span>
        <?php elseif ($assignments['total'] > 0): ?>
            <span class="detail-note detail-note-alert">
                The scheduled collection was cancelled.
            </span>
        <?php elseif ($complaint->getStatus() === Complaint::STATUS_ASSIGNED): ?>
            <span class="detail-note detail-note-alert">
                No collection has been scheduled for this bin.
            </span>
        <?php endif; ?>
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
                    <tr><th>Capacity</th><td><?= ($binInfo['capacity_litre'] ?? null) === null ? '-' : (int) $binInfo['capacity_litre'] . ' litres' ?></td></tr>
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
                    <header class="revision-head">
                        <span class="badge badge-status">Version <?= count($revisions) - $index ?></span>
                        <span class="field-help">
                            replaced <?= e($revision->getEditedAt()) ?>
                            by <?= e($revision->getEditedBy()?->getFullName() ?? 'a deleted account') ?>
                        </span>
                    </header>

                    <p class="field-help">
                        <?= e($revision->getBin()?->getBinCode() ?? 'Bin no longer on record') ?>
                        &middot; <?= e($revision->getType()) ?>
                    </p>

                    <p class="pre-wrap"><?= e($revision->getDescription()) ?></p>

                    <?php /* The photograph this edit replaced, in the same card
                             the edit form uses for the current one, so the two
                             read as the same kind of thing. It is marked
                             superseded rather than deleted, so swapping a
                             damning photo for an innocuous one is as visible as
                             rewriting the words. */ ?>
                    <?php if ($revision->getAttachment() !== null): ?>
                        <div class="current-photo">
                            <a
                                class="current-photo-link"
                                href="<?= url('complaint/attachment/' . $revision->getAttachment()->getKey()) ?>"
                                target="_blank"
                            >
                                <img src="<?= url('complaint/attachment/' . $revision->getAttachment()->getKey()) ?>" alt="">
                                <span>
                                    <strong>Photo before this edit</strong>
                                    <small><?= e($revision->getAttachment()->getReadableSize()) ?> &middot; opens in a new tab</small>
                                </span>
                            </a>
                        </div>
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

<?php /*
 * Book a cleaner, without leaving the complaint.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management - cross-module integration
 *
 * This is the Scheduling module's work, shown here because this is where the
 * decision is made. It posts to ComplaintController::assign(), which calls
 * SchedulingService::createForComplaint() - so the booking is created by the
 * module that owns collections, and this page only asks for the three things
 * it needs. Offered only while the complaint has no collection: once one
 * exists the status form takes over.
 */ ?>
<?php if (UserPermissions::can($user, 'complaint.manage')
          && $complaint->getStatus() === Complaint::STATUS_NEW
          && !in_array(Complaint::STATUS_ASSIGNED, $statuses, true)): ?>
    <form
        method="post"
        action="<?= url('complaint/assign/' . $complaint->getKey()) ?>"
        class="form-card"
    >
        <h2>Book a cleaner</h2>

        <?= csrfField() ?>

        <?php if (!empty($errors['schedule'])): ?>
            <div class="alert alert-error"><?= e($errors['schedule']) ?></div>
        <?php endif; ?>

        <p class="field-help">
            Raises a collection for <strong><?= e($complaint->getBin()?->getBinCode() ?? 'this bin') ?></strong>
            and moves this complaint to Assigned. The reporter is told.
        </p>

        <label>
            Collection date
            <input
                type="date"
                name="schedule_date"
                required
                min="<?= e(date('Y-m-d')) ?>"
                value="<?= e((string) ($_POST['schedule_date'] ?? date('Y-m-d'))) ?>"
                data-message-required="Choose the day the cleaner should go."
            >
            <?php if (!empty($errors['schedule_date'])): ?>
                <span class="field-error"><?= e($errors['schedule_date']) ?></span>
            <?php endif; ?>
        </label>

        <label>
            Time slot
            <input
                name="time_slot"
                required
                maxlength="50"
                placeholder="e.g. 09:00-12:00"
                value="<?= e((string) ($_POST['time_slot'] ?? '')) ?>"
                data-message-required="Give the time slot for the visit."
            >
            <?php if (!empty($errors['time_slot'])): ?>
                <span class="field-error"><?= e($errors['time_slot']) ?></span>
            <?php endif; ?>
        </label>

        <label>
            Cleaner
            <select name="cleaner_id" required>
                <option value="">Select a cleaner</option>
                <?php foreach ($cleaners as $cleaner): ?>
                    <option
                        value="<?= (int) $cleaner->getKey() ?>"
                        <?= (string) ($_POST['cleaner_id'] ?? '') === (string) $cleaner->getKey() ? 'selected' : '' ?>
                    ><?= e($cleaner->getFullName()) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['cleaner_id'])): ?>
                <span class="field-error"><?= e($errors['cleaner_id']) ?></span>
            <?php endif; ?>
            <?php if ($cleaners === []): ?>
                <span class="field-error">No active cleaner is available to assign.</span>
            <?php endif; ?>
        </label>

        <label>
            Notes for the cleaner
            <textarea name="notes" rows="2" maxlength="500"
                      placeholder="e.g. Bin is behind the drinks stall."><?= e((string) ($_POST['notes'] ?? '')) ?></textarea>
            <?php if (!empty($errors['notes'])): ?>
                <span class="field-error"><?= e($errors['notes']) ?></span>
            <?php endif; ?>
        </label>

        <div class="form-actions">
            <button class="button" type="submit" <?= $cleaners === [] ? 'disabled' : '' ?>>
                Book cleaner and mark Assigned
            </button>
            <button class="button button-secondary" type="reset">Clear fields</button>
        </div>
    </form>
<?php endif; ?>

<?php if (UserPermissions::can($user, 'complaint.manage') && $statuses !== []): ?>
    <form 
        method="post" 
        action="<?= url('complaint/update-status/' . $complaint->getKey()) ?>" 
        class="form-card"
    >
        <h2>Update status</h2>

        <?= csrfField() ?>

        <?php /* Assigned is not in this list until a cleaner is booked to visit
                 the bin - ComplaintService::nextStatusesFor() removes it, and
                 the service refuses it as well, so the form and the rule
                 cannot drift apart. The booking itself is offered below rather
                 than linked to: sending an administrator to another module to
                 do it is exactly where the step used to be forgotten. */ ?>
        <?php if ($complaint->getStatus() === Complaint::STATUS_NEW
                  && !in_array(Complaint::STATUS_ASSIGNED, $statuses, true)): ?>
            <p class="field-help">
                <strong>Assigned</strong> is not available yet. It means a cleaner is on the
                way, so it can only be set once one is booked to visit this bin.
                Book one below and this complaint moves to Assigned on its own.
            </p>
        <?php endif; ?>

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
            <textarea
                name="remarks"
                rows="3"
                maxlength="255"
                data-required-for="<?= e(Complaint::STATUS_REJECTED) ?>"
                data-message-required="Say why this report is being rejected."
                placeholder="e.g. The bin was already emptied before this was reported."
            ></textarea>

            <?php if (!empty($errors['remarks'])): ?>
                <span class="field-error"><?= e($errors['remarks']) ?></span>
            <?php endif; ?>

            <span class="field-help" id="remarks-help">
                Optional, and sent to the reporter with the outcome. Required when rejecting.
            </span>
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
                            <?php /* An edit and a withdrawal both carry the same status
                                     on each side, so the arrow would read "New → New".
                                     change_type is what separates the three kinds of
                                     row. */ ?>
                            <?php if ($entry->isDetailsEdit()): ?>
                                Details edited
                            <?php elseif ($entry->isWithdrawal()): ?>
                                Withdrawn
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
<?php /*
 * Remarks become required as soon as Rejected is the chosen outcome.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 *
 * validate.js reads field.required as a live property rather than caching the
 * attribute at load, so toggling it here is all that is needed for the same
 * inline message to appear under this field as under every other required one.
 * The server enforces it as well; this only saves a round trip.
 *
 * Placed after the form so the elements exist, in keeping with the other
 * view-local scripts in this module.
 */ ?>
<script>
(() => {
    'use strict';
    const remarks = document.querySelector('textarea[name="remarks"][data-required-for]');
    const select = document.querySelector('select[name="complaint_status"]');
    if (!remarks || !select) return;

    const help = document.getElementById('remarks-help');
    const requiredFor = remarks.dataset.requiredFor;

    const sync = () => {
        const needed = select.value === requiredFor;
        remarks.required = needed;
        if (help) {
            help.textContent = needed
                ? 'Required. The reporter is told their report was rejected, so tell them why.'
                : 'Optional, and sent to the reporter with the outcome. Required when rejecting.';
        }
    };

    select.addEventListener('change', sync);
    sync();   // a re-rendered form keeps its selection, so check on load too
})();
</script>
