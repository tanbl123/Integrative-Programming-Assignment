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

    <?php /* The photo already on file, OUTSIDE the label below. Inside it, a
             click anywhere here activates the file input and opens the picker
             instead of the photograph the reporter meant to look at. */ ?>
    <?php if ($attachments !== []): ?>
        <div class="field-block">
            <span class="field-help">Current photo</span>

            <?php /* The photograph and the control that removes it sit on one
                     row, so the button reads as belonging to the photo beside
                     it rather than to the drop zone below.

                     Photo evidence is optional, so a reporter must be able to
                     get back to having none without withdrawing the complaint
                     and losing its number and history. The checkbox is the
                     baseline; the script turns it into the button. Nothing
                     happens until the form is saved. */ ?>
            <?php foreach ($attachments as $current): ?>
                <div class="current-photo-row">
                    <a
                        class="current-photo"
                        href="<?= url('complaint/attachment/' . $current->getKey()) ?>"
                        target="_blank"
                    >
                        <img src="<?= url('complaint/attachment/' . $current->getKey()) ?>" alt="">
                        <span>
                            <strong><?= e($current->getDisplayName()) ?></strong>
                            <small><?= e($current->getReadableSize()) ?> &middot; opens in a new tab</small>
                        </span>
                    </a>

                    <div class="remove-photo" id="remove-photo-field">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remove_attachment" value="1" id="remove-attachment">
                            Remove this photo when I save
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>

            <span class="field-help" id="remove-photo-note"></span>
        </div>
    <?php endif; ?>

    <label>
            <?= $editId === null ? 'Photo evidence (optional)' : 'Replace this photo' ?>

            <?php /* The plain file input is the baseline and is never removed.
                     The script below only dresses this container: with scripting
                     off the input renders as the browser's own control and the
                     drop zone is simply a bordered box around it. */ ?>
            <div class="dropzone" id="attachment-dropzone">
                <input 
                    type="file" 
                    name="attachment" 
                    id="attachment" 
                    accept="image/jpeg,image/png,image/webp"
                    data-max-bytes="5242880"
                    data-message-accept="Only JPEG, PNG, or WebP images are allowed."
                    data-message-size="Photo must be no larger than 5 MB."
                >

                <p class="dropzone-hint" id="attachment-hint">
                    <strong><?= $attachments !== [] ? 'Drag a new photo here' : 'Drag a photo here' ?></strong>
                    <span><?= $attachments !== []
                        ? 'or click to choose one. It replaces the photo above.'
                        : 'or click to choose one' ?></span>
                </p>

                <div class="dropzone-preview" id="attachment-preview" hidden>
                    <img id="attachment-thumb" alt="" hidden>
                    <span class="dropzone-file">
                        <strong id="attachment-name"></strong>
                        <small id="attachment-size"></small>
                    </span>
                    <button type="button" class="button button-secondary" id="attachment-remove">
                        Remove
                    </button>
                </div>
            </div>

            <span class="field-help">JPEG, PNG, or WebP; maximum 5 MB.</span>

            <?php if (!empty($errors['attachment'])): ?>
                <span class="field-error"><?= e($errors['attachment']) ?></span>
            <?php endif; ?>
        </label>


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
 * Drag-and-drop photo evidence with a preview.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 *
 * A reporter photographs an overflowing bin on their phone and then has to
 * recognise it again in a file picker, from a name like IMG_20260911_0842.jpg.
 * The preview is what tells them they attached the right photo, before it
 * becomes evidence an administrator acts on.
 *
 * The <input type="file"> is never replaced, only hidden from view once this
 * script runs, so the form still works with scripting disabled and the file
 * still posts through the ordinary multipart field. A dropped file is written
 * back into that input through a DataTransfer, which means the upload path,
 * and the validation in public/js/validate.js, see a dropped file and a chosen
 * file as exactly the same thing.
 *
 * A browser does not apply the accept attribute to a dropped file, so a
 * reporter can drop anything at all here. That is the point at which the size
 * and type rules matter, so the field is marked as touched and a change event
 * raised, which makes validate.js report the problem immediately rather than
 * at submission.
 */ ?>
<script>
(() => {
    'use strict';
    const zone    = document.getElementById('attachment-dropzone');
    const input   = document.getElementById('attachment');
    const hint    = document.getElementById('attachment-hint');
    const preview = document.getElementById('attachment-preview');
    const thumb   = document.getElementById('attachment-thumb');
    const name    = document.getElementById('attachment-name');
    const size    = document.getElementById('attachment-size');
    const remove  = document.getElementById('attachment-remove');
    if (!zone || !input || !preview) return;

    // Only now is the plain input hidden, so nothing is lost without scripting.
    zone.classList.add('is-enhanced');

    let objectUrl = null;
    const releaseThumb = () => {
        if (objectUrl !== null) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    };

    const readableSize = bytes => bytes < 1024 * 1024
        ? Math.max(1, Math.round(bytes / 1024)) + ' KB'
        : (bytes / (1024 * 1024)).toFixed(1) + ' MB';

    const render = () => {
        releaseThumb();
        const file = input.files && input.files[0];

        if (!file) {
            preview.hidden = true;
            hint.hidden = false;
            thumb.hidden = true;
            thumb.removeAttribute('src');
            name.textContent = '';
            size.textContent = '';
            return;
        }

        name.textContent = file.name;
        size.textContent = readableSize(file.size);

        // A file that is not an image has nothing to show. Its name is still
        // listed, and validate.js explains why it will not be accepted.
        if (file.type.startsWith('image/')) {
            objectUrl = URL.createObjectURL(file);
            thumb.src = objectUrl;
            thumb.hidden = false;
        } else {
            thumb.hidden = true;
            thumb.removeAttribute('src');
        }

        hint.hidden = true;
        preview.hidden = false;
    };

    /** Writes a dropped file into the real input, so one code path remains. */
    const accept = file => {
        const carrier = new DataTransfer();
        if (file) {
            carrier.items.add(file);
        }
        input.files = carrier.files;
        // validate.js marks a field once the user has acted on it; dropping a
        // file is acting on it, so a bad file is reported straight away.
        input.dataset.touched = '1';
        input.dispatchEvent(new Event('change', {bubbles: true}));
    };

    ['dragenter', 'dragover'].forEach(type => {
        zone.addEventListener(type, event => {
            event.preventDefault();
            zone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'dragend'].forEach(type => {
        zone.addEventListener(type, () => zone.classList.remove('is-dragover'));
    });

    zone.addEventListener('drop', event => {
        event.preventDefault();
        zone.classList.remove('is-dragover');
        const dropped = event.dataTransfer && event.dataTransfer.files[0];
        if (dropped) {
            accept(dropped);
        }
    });

    // A file can claim to be an image and not decode as one - a renamed
    // upload, or a truncated photo. The name and size still describe it.
    thumb.addEventListener('error', () => {
        thumb.hidden = true;
        thumb.removeAttribute('src');
    });

    input.addEventListener('change', render);

    remove.addEventListener('click', event => {
        // The zone sits inside the <label>, so without this the click would
        // reopen the file picker the reporter is trying to back out of.
        event.preventDefault();
        event.stopPropagation();
        accept(null);
        input.focus();
    });

    render();   // a re-rendered form after a failed submit starts empty
})();
</script>

<?php /*
 * Removing the photo already on file.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 *
 * The checkbox above is what posts, and it is what works with scripting
 * disabled. This turns it into a button sitting beside the photograph, because
 * a checkbox below a drop zone does not say which photo it means, and a
 * reporter who cannot tell will leave it alone.
 *
 * Nothing is removed here. The complaint is changed when the form is saved,
 * and even then the photograph is marked superseded rather than deleted, so
 * the revision it belonged to can still show it.
 */ ?>
<script>
(() => {
    'use strict';
    const field = document.getElementById('remove-photo-field');
    const box = document.getElementById('attachment-remove-box') || document.getElementById('remove-attachment');
    const photo = document.querySelector('.current-photo');
    // Below the row rather than inside it, so a long sentence cannot squash
    // the photograph or push the button out of line.
    const note = document.getElementById('remove-photo-note');
    if (!field || !box || !photo || !note) return;

    field.classList.add('is-enhanced');

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button button-secondary';

    const render = () => {
        const removing = box.checked;
        button.textContent = removing ? 'Keep this photo' : 'Remove photo';
        note.textContent = removing ? 'This photo will be removed when you save.' : '';
        photo.classList.toggle('is-removing', removing);
    };

    button.addEventListener('click', () => {
        box.checked = !box.checked;
        // ui.js clears the form from this event, and unsaved.js watches it.
        box.dispatchEvent(new Event('change', {bubbles: true}));
        render();
    });

    // Clear fields resets the checkbox; the button has to follow it.
    box.addEventListener('change', render);

    field.append(button);
    render();
})();
</script>
