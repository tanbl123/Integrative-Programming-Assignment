/* Warn before leaving a form with unsaved changes.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Shared core - applies to the forms of every module
 *
 * Every module has pages where a person types for a while before saving: a
 * complaint description, a user's demographics, a schedule's notes. Clicking
 * Cancel, or the browser's Back button, threw all of it away without asking.
 *
 * What is guarded is a form that has actually been changed. Opening an edit
 * page, reading it and leaving is not a loss and raises nothing, which is why
 * the state of every control is recorded at load and compared rather than a
 * flag being set on the first keystroke. Returning a field to what it said
 * originally makes the form clean again.
 *
 * Two warnings, because neither covers both cases:
 *
 *   - Clicking a link inside the page asks in the system's own dialog, the
 *     same one a delete asks in. The link is stopped, the question asked, and
 *     the page left only if the answer is yes.
 *   - Closing the tab, reloading, or the Back button raises the browser's own
 *     dialog, whose wording and appearance no site is allowed to set. That is
 *     a browser rule, not an oversight, and it is the reason the first case is
 *     handled separately rather than left to it.
 *
 * Submitting is saving, so it never warns. A form opts out with
 * data-no-guard - the sign-in form does, where a warning would only be in the
 * way.
 */
(() => {
    'use strict';

    const SKIP_TYPES = ['hidden', 'submit', 'button', 'reset', 'image'];

    const ASK = {
        title: 'Leave without saving?',
        message: 'You have unsaved changes on this page. '
               + 'Leaving now discards them.',
        confirmLabel: 'Discard changes',
        cancelLabel: 'Stay on this page',
    };

    /** The shared dialog from ui.js, or the browser's prompt if it is absent. */
    const ask = options => (window.EcoCampus && window.EcoCampus.confirm)
        ? window.EcoCampus.confirm(options)
        : Promise.resolve(window.confirm(options.message));

    /** What a control currently holds, as one comparable string. */
    const readValue = field => {
        if (field.type === 'checkbox' || field.type === 'radio') {
            return field.checked ? '1' : '0';
        }
        if (field.type === 'file') {
            return String(field.files ? field.files.length : 0);
        }
        return String(field.value);
    };

    document.querySelectorAll('main form:not(.filter-panel):not([data-no-guard])').forEach(form => {
        const fields = Array.from(form.querySelectorAll('input, select, textarea')).filter(field =>
            !field.disabled && !field.readOnly && !SKIP_TYPES.includes(field.type));
        if (fields.length === 0) {
            return;
        }

        // The form as it was handed to the person, before they touched it.
        const original = fields.map(readValue);
        let saving = false;

        const isDirty = () => !saving && fields.some((field, index) => readValue(field) !== original[index]);

        // Submitting is the point of the form, not a loss of work.
        form.addEventListener('submit', () => { saving = true; });

        /*
         * A link inside this form's page. The listener is on the document
         * because Cancel usually sits outside the form, in the page heading.
         */
        document.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            if (link === null || !isDirty()) {
                return;
            }
            // Anything that does not take the person away from this page:
            // opening the photo in a new tab, or a jump to an anchor.
            if (link.target === '_blank' || link.getAttribute('href').startsWith('#')) {
                return;
            }

            // A dialog cannot hold up a click the way the browser's own prompt
            // does, so the navigation is stopped first and repeated afterwards
            // if the answer is yes.
            event.preventDefault();

            ask(ASK).then(agreed => {
                if (!agreed) {
                    return;
                }
                // Leaving was chosen, so the departure warning below has
                // nothing left to add.
                saving = true;
                window.location.assign(link.href);
            });
        });

        // The backstop: closing the tab, reloading, or the Back button. The
        // browser shows its own wording here and ignores any we supply.
        window.addEventListener('beforeunload', event => {
            if (!isDirty()) {
                return;
            }
            event.preventDefault();
            event.returnValue = '';
        });
    });
})();
