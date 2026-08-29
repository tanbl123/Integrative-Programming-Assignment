<?php /** Schedule create/edit form. Author: Ong Kar Heng (2408830). */
$isEdit = $mode === 'edit'; $action = $isEdit ? url('schedule/update/' . $schedule->getKey()) : url('schedule/store');
?>
<div class="page-heading"><div><h1><?= $isEdit ? 'Edit schedule' : 'Generate collection schedule' ?></h1><p class="lead">The selected Strategy decides which active bins become assignments.</p></div><a class="button button-secondary" href="<?= url('schedule') ?>">Cancel</a></div>
<form method="post" action="<?= $action ?>" class="form-card" id="schedule-form" data-bin-api="<?= url('bin-api') ?>" data-complaint-api="<?= url('complaint-api/unresolved') ?>" data-user-api="<?= url('user-api/cleaners') ?>">
<?= csrfField() ?>
<?php if (!empty($errors['schedule'])): ?><div class="alert alert-error"><?= e($errors['schedule']) ?></div><?php endif; ?>
<div class="form-grid">
<label>Date<input type="date" name="schedule_date" min="<?= date('Y-m-d') ?>" required value="<?= e((string) $values['schedule_date']) ?>"><?php if (!empty($errors['schedule_date'])): ?><span class="field-error"><?= e($errors['schedule_date']) ?></span><?php endif; ?></label>
<label>Time slot<input name="time_slot" required maxlength="50" value="<?= e((string) $values['time_slot']) ?>"><?php if (!empty($errors['time_slot'])): ?><span class="field-error"><?= e($errors['time_slot']) ?></span><?php endif; ?></label>
<label>Selection strategy<?php if ($isEdit): ?><input type="hidden" name="strategy" value="<?= e((string) $values['strategy']) ?>"><input value="<?= e((string) $values['strategy']) ?>" disabled><?php else: ?><select name="strategy" id="strategy" required><?php foreach ($strategies as $strategy): ?><option value="<?= e($strategy) ?>" <?= $values['strategy'] === $strategy ? 'selected' : '' ?>><?= e($strategy) ?></option><?php endforeach; ?></select><?php endif; ?><?php if (!empty($errors['strategy'])): ?><span class="field-error"><?= e($errors['strategy']) ?></span><?php endif; ?></label>
<label>Cleaner<select name="cleaner_id" required><option value="">Select cleaner</option><?php foreach ($cleaners as $cleaner): ?><option value="<?= $cleaner->getKey() ?>" <?= (string) $values['cleaner_id'] === (string) $cleaner->getKey() ? 'selected' : '' ?>><?= e($cleaner->getFullName()) ?></option><?php endforeach; ?></select><?php if (!empty($errors['cleaner_id'])): ?><span class="field-error"><?= e($errors['cleaner_id']) ?></span><?php endif; ?></label>
</div>
<label>Notes<textarea name="notes" maxlength="500" rows="3"><?= e((string) $values['notes']) ?></textarea><?php if (!empty($errors['notes'])): ?><span class="field-error"><?= e($errors['notes']) ?></span><?php endif; ?></label>
<?php if (!$isEdit): ?><div id="service-preview" class="info-card" aria-live="polite">Checking module web services…</div><?php endif; ?>
<button class="button" type="submit"><?= $isEdit ? 'Save changes' : 'Generate assignments' ?></button>
</form>
<?php if (!$isEdit): ?><script src="<?= url('public/js/scheduling.js') ?>" defer></script><?php endif; ?>
