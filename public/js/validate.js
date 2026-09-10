/* Shared client-side form validation.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Shared core - applies to the forms of every module
 *
 * The middle of three gates. The constraint attributes already written into
 * each form (required, minlength, maxlength, pattern, min, max, type, accept)
 * are the first gate and keep working with scripting disabled; each module's
 * service class is the third and is the only authority, because a check
 * running in the browser is advice to an honest user and not a control.
 *
 * What this file adds is the message. The browser's own bubble names one field
 * at a time, disappears on the next click, and its wording has nothing to do
 * with the message the server returns after a rejected submission. Here every
 * failing field is marked at once with the same <span class="field-error">
 * line the server renders, so a user sees identical wording whichever gate
 * stopped them.
 *
 * No rules are written here. They are read from the attributes each module
 * already declares, so a module changes its client-side validation by editing
 * its own form, not this file. Where the generic wording would not match what
 * that module's service says, the field carries the server's exact sentence in
 * a data-message attribute:
 *
 *     data-message-required   shown when a required field is left empty
 *     data-message-type       an email that is not an email address
 *     data-message-minlength  too short, after trimming
 *     data-message-maxlength  too long, after trimming
 *     data-message-pattern    does not match pattern
 *     data-message-min/max    outside a numeric or date range
 *     data-message-accept     a file whose type is not in accept
 *     data-message-size       a file larger than data-max-bytes
 *     data-message            any of the above, when one sentence covers them
 *     data-match              the name of a field this one must equal
 *
 * Where none is given, a field's own title attribute is used for the format
 * and length messages, which is where several modules already keep exactly
 * that sentence.
 *
 * The tokens {label}, {length}, {min} and {max} are substituted into whichever
 * message is used. A form opts out entirely with data-no-validate.
 */
(() => {
    'use strict';

    const SKIP_TYPES = ['hidden', 'submit', 'button', 'reset', 'image'];

    const holderOf = field => field.closest('label') || field.parentElement;

    /** The field's own label text, used in the generic messages. */
    const labelOf = field => {
        const holder = holderOf(field);
        if (holder === null) {
            return 'This field';
        }
        let text = '';
        for (const node of holder.childNodes) {
            if (node === field || node.contains(field)) {
                break;
            }
            text += node.textContent || '';
        }
        text = text.replace(/\(optional\)/i, '').replace(/\s+/g, ' ').trim();
        return text === '' ? 'This field' : text;
    };

    const render = (template, tokens) =>
        template.replace(/\{(\w+)\}/g, (whole, key) =>
            Object.prototype.hasOwnProperty.call(tokens, key) ? tokens[key] : whole);

    // Kinds for which a field's title attribute is a usable message. It
    // describes the format or the limit, so it does not suit an empty field.
    const TITLE_KINDS = ['type', 'minlength', 'maxlength', 'pattern', 'min', 'max'];

    /** A module's own wording where it gives one, otherwise the generic line. */
    const say = (field, kind, generic, tokens) => render(
        field.getAttribute('data-message-' + kind)
            || field.getAttribute('data-message')
            || (TITLE_KINDS.includes(kind) ? field.title.trim() : '')
            || generic,
        tokens);

    const checkFile = (field, tokens) => {
        const file = field.files && field.files[0];
        if (!file) {
            return field.required ? say(field, 'required', 'Choose a file.', tokens) : '';
        }
        const accept = (field.getAttribute('accept') || '')
            .split(',').map(entry => entry.trim()).filter(Boolean);
        if (accept.length && !accept.includes(file.type)) {
            return say(field, 'accept', '{label} must be one of: {max}.',
                Object.assign({}, tokens, {max: accept.join(', ')}));
        }
        const limit = parseInt(field.getAttribute('data-max-bytes') || '', 10);
        if (limit > 0 && file.size > limit) {
            return say(field, 'size', '{label} must be no larger than {max}.',
                Object.assign({}, tokens, {max: Math.round(limit / 1048576) + ' MB'}));
        }
        return '';
    };

    /** The message this field should show right now, or '' when it is valid. */
    const messageFor = field => {
        const tokens = {label: labelOf(field)};

        if (field.type === 'file') {
            return checkFile(field, tokens);
        }

        if (field.type === 'checkbox' || field.type === 'radio') {
            return field.required && !field.checked
                ? say(field, 'required', '{label} is required.', tokens)
                : '';
        }

        // Trimmed, because a description of five spaces is not a description
        // and every service class trims before it validates.
        const value = String(field.value).trim();
        tokens.length = value.length.toLocaleString();

        if (value === '') {
            return field.required
                ? say(field, 'required', '{label} is required.', tokens)
                : '';   // an optional field left empty has nothing to check
        }

        const type = (field.getAttribute('type') || '').toLowerCase();
        if (type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            return say(field, 'type', 'Enter a valid email address.', tokens);
        }

        const min = parseInt(field.getAttribute('minlength') || '', 10);
        if (min > 0 && value.length < min) {
            return say(field, 'minlength', '{label} must be at least {min} characters.',
                Object.assign({}, tokens, {min: min.toLocaleString()}));
        }

        const max = parseInt(field.getAttribute('maxlength') || '', 10);
        if (max > 0 && value.length > max) {
            return say(field, 'maxlength', '{label} must be no more than {max} characters.',
                Object.assign({}, tokens, {max: max.toLocaleString()}));
        }

        const lowest = field.getAttribute('min');
        const highest = field.getAttribute('max');
        if (type === 'number') {
            const entered = Number(value);
            if (Number.isNaN(entered)) {
                return say(field, 'type', '{label} must be a number.', tokens);
            }
            if (lowest !== null && entered < Number(lowest)) {
                return say(field, 'min', '{label} must be {min} or more.',
                    Object.assign({}, tokens, {min: lowest}));
            }
            if (highest !== null && entered > Number(highest)) {
                return say(field, 'max', '{label} must be {max} or less.',
                    Object.assign({}, tokens, {max: highest}));
            }
        } else if (type === 'date') {
            // ISO dates compare correctly as text, which avoids time zones.
            if (lowest !== null && value < lowest) {
                return say(field, 'min', '{label} cannot be earlier than {min}.',
                    Object.assign({}, tokens, {min: lowest}));
            }
            if (highest !== null && value > highest) {
                return say(field, 'max', '{label} cannot be later than {max}.',
                    Object.assign({}, tokens, {max: highest}));
            }
        }

        // A confirmation field, which no attribute can express on its own.
        const matchName = field.getAttribute('data-match');
        if (matchName !== null) {
            const other = field.form && field.form.elements[matchName];
            if (other && String(other.value) !== String(field.value)) {
                return say(field, 'match', '{label} does not match.', tokens);
            }
        }

        const pattern = field.getAttribute('pattern');
        if (pattern !== null && pattern !== '') {
            let expression = null;
            try {
                // An HTML pattern is anchored implicitly; a JavaScript one is not.
                expression = new RegExp('^(?:' + pattern + ')$');
            } catch (error) {
                expression = null;   // an unusable pattern is left to the server
            }
            if (expression !== null && !expression.test(value)) {
                return say(field, 'pattern', '{label} is not in the expected format.', tokens);
            }
        }

        return '';
    };

    /*
     * The <span class="field-error"> for a field, reusing the one the server
     * already rendered so a message is never shown twice. A new span is placed
     * after the help text where there is one, matching the server's order of
     * control, help, error.
     */
    const spanFor = (field, create) => {
        const holder = holderOf(field);
        let span = holder.querySelector(':scope > .field-error');
        if (span === null) {
            if (!create) {
                return null;   // nothing to say and nowhere it needs saying
            }
            span = document.createElement('span');
            span.className = 'field-error';
            span.hidden = true;
            const anchor = holder.querySelector(':scope > .field-help') || field;
            anchor.insertAdjacentElement('afterend', span);
        }
        if (span.id === '') {
            span.id = 'error-' + (field.name || field.id || Math.random().toString(36).slice(2));
        }
        return span;
    };

    const show = (field, message) => {
        const span = spanFor(field, message !== '');
        field.setAttribute('aria-invalid', message === '' ? 'false' : 'true');
        if (span === null) {
            return;
        }
        span.textContent = message;
        span.hidden = message === '';
        if (message === '') {
            field.removeAttribute('aria-describedby');
        } else {
            field.setAttribute('aria-describedby', span.id);
        }
    };

    const check = field => {
        const message = messageFor(field);
        show(field, message);
        return message === '';
    };

    document.querySelectorAll('main form:not(.filter-panel):not([data-no-validate])').forEach(form => {
        const fields = Array.from(form.querySelectorAll('input, select, textarea')).filter(field =>
            !field.disabled && !field.readOnly && !SKIP_TYPES.includes(field.type));
        if (fields.length === 0) {
            return;   // a form that only carries a token and a button
        }

        /*
         * A field is only marked once the user has tried to submit, or has
         * filled it in and moved on. Complaining about an empty field before
         * it has been touched is noise rather than help.
         */
        // A confirmation field must be re-checked when the field it mirrors
        // changes, not only when the confirmation itself does.
        const mirrors = fields.filter(field => field.getAttribute('data-match') !== null);

        fields.forEach(field => {
            const recheck = () => {
                if (field.dataset.touched === '1') {
                    check(field);
                }
                mirrors.forEach(mirror => {
                    if (mirror !== field
                        && mirror.getAttribute('data-match') === field.name
                        && mirror.dataset.touched === '1') {
                        check(mirror);
                    }
                });
            };
            field.addEventListener('input', recheck);
            field.addEventListener('change', recheck);
            field.addEventListener('blur', () => {
                if (String(field.value) !== '') {
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
            if (event.target.closest('.clear-fields') === null) {
                return;
            }
            fields.forEach(field => {
                delete field.dataset.touched;
                show(field, '');
            });
        });
    });
})();
