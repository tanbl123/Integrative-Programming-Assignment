<?php /** Complaint submission form. */ ?>

<div class="page-heading">
    <div>
        <h1><?= $editId !== null ? 'Edit complaint' : 'Report a waste issue' ?></h1>
        <p class="lead">Select the affected bin and describe what needs attention.</p>
    </div>

    <a class="button button-secondary" href="<?= url('complaint') ?>">Cancel</a>
</div>

<form 
    method="post" 
    action="<?= url($editId !== null ? 'complaint/update/' . $editId : 'complaint/store') ?>" 
    enctype="multipart/form-data" 
    class="form-card"
>
    <?= csrfField() ?>

    <div class="form-grid">
        <label>
            Campus bin
            <select name="bin_id" required>
                <option value="">Select bin</option>

                <?php foreach ($bins as $bin): ?>
                    <option 
                        value="<?= $bin->getKey() ?>" 
                        <?= (string) ($values['bin_id'] ?? '') === (string) $bin->getKey() ? 'selected' : '' ?>
                    >
                        <?= e($bin->getBinCode() . ' — ' . ($bin->getLocation()?->getFullLabel() ?? 'Unknown')) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if (!empty($errors['bin_id'])): ?>
                <span class="field-error"><?= e($errors['bin_id']) ?></span>
            <?php endif; ?>
        </label>

        <label>
            Issue type
            <select name="complaint_type" required>
                <option value="">Select issue</option>

                <?php foreach ($types as $type): ?>
                    <option 
                        value="<?= e($type) ?>" 
                        <?= ($values['complaint_type'] ?? '') === $type ? 'selected' : '' ?>
                    >
                        <?= e($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if (!empty($errors['complaint_type'])): ?>
                <span class="field-error"><?= e($errors['complaint_type']) ?></span>
            <?php endif; ?>
        </label>
    </div>

    <label>
        Description
        <textarea 
            name="description" 
            rows="6" 
            minlength="10" 
            maxlength="2000" 
            required
        ><?= e((string) ($values['description'] ?? '')) ?></textarea>

        <?php if (!empty($errors['description'])): ?>
            <span class="field-error"><?= e($errors['description']) ?></span>
        <?php endif; ?>
    </label>

    <?php if ($editId === null): ?>
        <label>
            Photo evidence (optional)
            <input 
                type="file" 
                name="attachment" 
                accept="image/jpeg,image/png,image/webp"
            >

            <span class="field-help">JPEG, PNG, or WebP; maximum 5 MB.</span>

            <?php if (!empty($errors['attachment'])): ?>
                <span class="field-error"><?= e($errors['attachment']) ?></span>
            <?php endif; ?>
        </label>
    <?php endif; ?>

    <?php if (!empty($errors['complaint'])): ?>
        <div class="alert alert-error">
            <?= e($errors['complaint']) ?>
        </div>
    <?php endif; ?>

    <button class="button" type="submit">
        <?= $editId !== null ? 'Save complaint' : 'Submit complaint' ?>
    </button>
</form>