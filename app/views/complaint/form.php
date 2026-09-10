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

            <?php /* Filled in by the script below when a bin with open reports is chosen. */ ?>
            <div class="alert alert-warning" id="duplicate-warning" hidden></div>
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
        <span id="description-label">Description</span>
        <textarea 
            name="description" 
            id="complaint-description"
            rows="6" 
            minlength="10" 
            maxlength="2000" 
            required
            placeholder="Describe what needs attention, and where exactly it is."
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

<?php /*
 * Client-side validation.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 *
 * The second of three gates. The HTML attributes above (required, minlength,
 * maxlength, accept) are the first and keep working with JavaScript disabled;
 * ComplaintService::validateComplaint() and ComplaintUploadService are the
 * third and are the only authority - nothing here is trusted, because a
 * client-side check is advice to an honest user, not a control. What this
 * script adds is the message: the browser's own bubble names one field at a
 * time, disappears on the next click, and cannot be read by a screen reader
 * on some platforms, so it is switched off and every failing field is marked
 * at once with the same .field-error line the server renders after a rejected
 * submission. A reporter therefore sees identical wording whichever gate
 * stopped them.
 *
 * The rules below deliberately restate ComplaintService::validateComplaint().
 * If a rule changes there it must change here too, and the server remains
 * correct either way.
 */ ?>
<script>
(() => {
    'use strict';
    const form = document.getElementById('complaint-form');
    if (!form) return;

    const MIN_DESCRIPTION = 10;
    const MAX_DESCRIPTION = 2000;
    const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;
    const ALLOWED_UPLOAD_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    // One rule per field, returning the message to show or '' when valid.
    const rules = {
        // Only active bins are rendered as options, so any chosen option
        // satisfies the server's "select an active campus bin" rule.
        bin_id: field => field.value === '' ? 'Select an active campus bin.' : '',

        complaint_type: field => field.value === '' ? 'Select a valid issue type.' : '',

        description: field => {
            const length = field.value.trim().length;
            if (length === 0) {
                return 'Describe the issue so it can be acted on.';
            }
            if (length < MIN_DESCRIPTION || length > MAX_DESCRIPTION) {
                return 'Description must contain 10-2,000 characters. '
                     + 'You have entered ' + length.toLocaleString() + '.';
            }
            return '';
        },

        // Optional. Checked here only to save the reporter an upload that the
        // server would reject; the server re-reads the file's own bytes with
        // finfo, because file.type is supplied by the browser.
        attachment: field => {
            const file = field.files && field.files[0];
            if (!file) {
                return '';
            }
            if (!ALLOWED_UPLOAD_TYPES.includes(file.type)) {
                return 'Only JPEG, PNG, or WebP images are allowed.';
            }
            if (file.size > MAX_UPLOAD_BYTES) {
                return 'Photo must be no larger than 5 MB.';
            }
            return '';
        },
    };

    const fields = Object.keys(rules)
        .map(name => form.querySelector('[name="' + name + '"]'))
        .filter(Boolean);
    if (!fields.length) return;

    /*
     * The <span class="field-error"> for a field, reusing the one the server
     * already rendered so a message is never shown twice. A new span is placed
     * after the help text where there is one, matching the server's order of
     * control, help, error.
     */
    const messageFor = field => {
        const holder = field.closest('label') || field.parentElement;
        let span = holder.querySelector(':scope > .field-error');
        if (!span) {
            span = document.createElement('span');
            span.className = 'field-error';
            span.hidden = true;
            const anchor = holder.querySelector(':scope > .field-help') || field;
            anchor.insertAdjacentElement('afterend', span);
        }
        span.id = span.id || 'error-' + field.name;
        return span;
    };

    const show = (field, message) => {
        const span = messageFor(field);
        span.textContent = message;
        span.hidden = message === '';
        field.setAttribute('aria-invalid', message === '' ? 'false' : 'true');
        if (message === '') {
            field.removeAttribute('aria-describedby');
        } else {
            field.setAttribute('aria-describedby', span.id);
        }
    };

    const check = field => {
        const message = rules[field.name](field);
        show(field, message);
        return message === '';
    };

    /*
     * Fields are only marked after the reporter has tried to submit, or has
     * left a field they have filled in. Complaining about an empty field
     * before it has been touched is noise, not help.
     */
    const checkIfTouched = field => {
        if (field.dataset.touched === '1') {
            check(field);
        }
    };

    fields.forEach(field => {
        field.addEventListener('input', () => checkIfTouched(field));
        field.addEventListener('change', () => checkIfTouched(field));
        field.addEventListener('blur', () => {
            if (field.value !== '') {
                field.dataset.touched = '1';
                check(field);
            }
        });
    });

    // Replaces the browser's own bubble. Set from JavaScript, so that with
    // scripting off the attribute validation in the markup still applies.
    form.noValidate = true;

    form.addEventListener('submit', event => {
        let firstInvalid = null;
        fields.forEach(field => {
            field.dataset.touched = '1';
            if (!check(field) && firstInvalid === null) {
                firstInvalid = field;
            }
        });
        if (firstInvalid !== null) {
            event.preventDefault();
            firstInvalid.focus();
            firstInvalid.scrollIntoView({block: 'center', behavior: 'smooth'});
        }
    });

    // ui.js appends a Clear fields button to every form. Emptying the form
    // should take the messages with it rather than turn it red.
    form.addEventListener('click', event => {
        if (event.target.closest('.clear-fields') === null) return;
        fields.forEach(field => {
            delete field.dataset.touched;
            show(field, '');
        });
    });
})();
</script>
