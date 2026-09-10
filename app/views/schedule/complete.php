<?php /** Assignment completion form. Author: Ng Zi Zhang (2406898). Module: Collection Scheduling & Assignment. */ ?>

<div class="page-heading">
    <div>
        <p class="eyebrow">Assignment #<?= $assignment->getKey() ?></p>
        <h1>Complete <?= e($assignment->getBin()?->getBinCode() ?? 'bin task') ?></h1>
        <p class="lead">
            <?= e($assignment->getBin()?->getLocation()?->getFullLabel() ?? '') ?>
        </p>
    </div>

    <a class="button button-secondary" href="<?= url('schedule/my') ?>">
        Cancel
    </a>
</div>

<form 
    method="post" 
    action="<?= url('schedule/store-completion/' . $assignment->getKey()) ?>" 
    class="form-card narrow-form"
    data-no-auto-clear="1"
    >
        <?= csrfField() ?>

    <?php if (!empty($errors['assignment'])): ?>
        <div class="alert alert-error">
            <?= e($errors['assignment']) ?>
        </div>
    <?php endif; ?>

    <label>
        Completion notes
        <textarea 
            name="notes" 
            rows="6" 
            maxlength="1000"
            placeholder="Enter completion remarks if needed"
            ><?= e((string) ($values['notes'] ?? '')) ?></textarea>

        <?php if (!empty($errors['notes'])): ?>
            <span class="field-error"><?= e($errors['notes']) ?></span>
        <?php endif; ?>

        <span class="field-help">
            Optional. Maximum 1,000 characters.
        </span>
    </label>

    <div class="form-actions">
        <button class="button" type="submit">
            Complete assignment
        </button>

        <button class="button button-secondary" type="reset">
            Clear fields
        </button>
    </div>
</form>