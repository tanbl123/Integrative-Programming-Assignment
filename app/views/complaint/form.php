<?php /** Complaint submission form. Author: Tan Boon Leong (2402865). Module: Complaint / Report Management. */ ?>

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
    id="complaint-form"
>
    <?= csrfField() ?>

    <div class="form-grid">
        <label>
            Campus bin
            <select 
                name="bin_id" 
                required 
                data-message-required="Select an active campus bin."
            >
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

            <?php /* Filled in by the script below when a bin with open reports is chosen. */ ?>
            <div class="alert alert-warning" id="duplicate-warning" hidden></div>
        </label>

        <label>
            Issue type
            <select 
                name="complaint_type" 
                required 
                data-message-required="Select a valid issue type."
            >
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
        <span id="description-label">Description</span>
        <textarea 
            name="description" 
            id="complaint-description"
            rows="6" 
            minlength="10" 
            maxlength="2000" 
            required
            placeholder="Describe what needs attention, and where exactly it is."
            data-message-required="Describe the issue so it can be acted on."
            data-message-minlength="Description must contain 10-2,000 characters. You have entered {length}."
            data-message-maxlength="Description must contain 10-2,000 characters. You have entered {length}."
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
                data-max-bytes="5242880"
                data-message-accept="Only JPEG, PNG, or WebP images are allowed."
                data-message-size="Photo must be no larger than 5 MB."
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
<?php /*
 * Duplicate warning.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 *
 * The open complaints for every bin are rendered here as JSON and the warning
 * is shown as soon as a bin is picked, so a reporter is told before typing a
 * description rather than after submitting. It never blocks submission - a
 * second reporter may legitimately be reporting a different issue with the
 * same bin - it only makes the duplicate visible.
 */ ?>
<script>
(() => {
    'use strict';
    const openByBin = <?= json_encode($openByBin, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const select = document.querySelector('select[name="bin_id"]');
    const box = document.getElementById('duplicate-warning');
    if (!select || !box) return;

    const describe = (list) => {
        const heading = document.createElement('strong');
        heading.textContent = list.length === 1
            ? '1 issue is already open for this bin:'
            : list.length + ' issues are already open for this bin:';
        const ul = document.createElement('ul');
        list.forEach(item => {
            const li = document.createElement('li');
            li.textContent = `#${item.id} — ${item.type} (${item.status}), reported ${item.created_at}`;
            ul.append(li);
        });
        const note = document.createElement('p');
        note.textContent = 'Please check whether yours is the same issue. '
                         + 'You can still submit if it is different.';
        box.replaceChildren(heading, ul, note);
    };

    const update = () => {
        const list = openByBin[select.value];
        if (Array.isArray(list) && list.length) {
            describe(list);
            box.hidden = false;
        } else {
            box.replaceChildren();
            box.hidden = true;
        }
    };

    select.addEventListener('change', update);
    update();   // a re-rendered form keeps its selection, so check on load too
})();
</script>

<?php /*
 * Prompt for detail when the issue type is Other.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 *
 * Every other issue type names the problem on its own, so an administrator
 * scanning the list understands it at a glance. "Other" does not, and the
 * description is the only place the problem is stated. Asking for it plainly,
 * at the moment Other is chosen, is what keeps that description useful.
 */ ?>
<script>
(() => {
    'use strict';
    const type = document.querySelector('select[name="complaint_type"]');
    const label = document.getElementById('description-label');
    const field = document.getElementById('complaint-description');
    if (!type || !label || !field) return;

    const DEFAULT_LABEL = 'Description';
    const DEFAULT_HINT  = 'Describe what needs attention, and where exactly it is.';
    const OTHER_LABEL   = 'Describe the issue \u2014 you selected Other, '
                        + 'so please state clearly what the problem is';
    const OTHER_HINT    = 'For example: the bin has been moved from its usual spot '
                        + 'and nobody can find it.';

    const update = () => {
        const isOther = type.value === 'Other';
        label.textContent = isOther ? OTHER_LABEL : DEFAULT_LABEL;
        field.placeholder = isOther ? OTHER_HINT : DEFAULT_HINT;
        label.classList.toggle('label-emphasis', isOther);
    };

    type.addEventListener('change', update);
    update();   // a re-rendered form keeps its selection, so check on load too
})();
</script>
