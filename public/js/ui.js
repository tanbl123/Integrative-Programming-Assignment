/* Shared progressive enhancements.
 * All persistence remains in authenticated MVC actions.
 * Author : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
 *          Tan Boon Leong (2402865), Phang Jun Hong (2406646)
 */
(() => {
    'use strict';

    const make = (tag, text, className) => {
        const node = document.createElement(tag);

        if (text) {
            node.textContent = text;
        }

        if (className) {
            node.className = className;
        }

        return node;
    };

    const button = (text, action) => {
        const node = make('button', text, 'button button-secondary');
        node.type = 'button';
        node.addEventListener('click', action);
        return node;
    };

    /*
     * Convert simple text links into button-style links.
     * This helps links such as View, Edit, Complete, Update status, etc.
     */
    document.querySelectorAll('main a:not(.button)').forEach(a => {
        const text = a.textContent.trim();

        if (/^(view|view bins|edit|sign in|sign out|profile|register|back|cancel|complete|create|add|delete|reactivate|reset|update|update status)/i.test(text)) {
            a.classList.add('button', 'button-secondary', 'button-small');
        }
    });

    const toasts = document.querySelectorAll('[data-toast]');

    if (toasts.length) {
        const region = make('div', '', 'toast-region');
        region.setAttribute('aria-label', 'Notifications');
        document.body.append(region);

        toasts.forEach(toast => {
            toast.classList.add('toast');

            const close = button('×', () => toast.remove());
            close.className = 'toast-close';
            close.setAttribute('aria-label', 'Dismiss notification');

            toast.append(close);
            region.append(toast);
        });
    }

    /*
     * Confirmation dialog.
     */
    if (typeof HTMLDialogElement !== 'undefined') {
        const dialog = make('dialog', '', 'confirm-dialog');

        const title = make('h2', 'Confirm action');
        title.id = 'confirmation-title';

        const message = make('p');
        message.id = 'confirmation-message';

        dialog.setAttribute('aria-labelledby', title.id);
        dialog.setAttribute('aria-describedby', message.id);

        const actions = make('div', '', 'button-row');

        let pending = null;

        const cancel = button('Keep unchanged', () => dialog.close());

        const proceed = button('Confirm', () => {
            const current = pending;
            pending = null;
            dialog.close();

            if (current) {
                current.form.dataset.confirmed = 'true';
                current.form.requestSubmit(current.submitter || undefined);
            }
        });

        proceed.classList.remove('button-secondary');
        proceed.classList.add('button-danger');

        actions.append(cancel, proceed);
        dialog.append(title, message, actions);
        document.body.append(dialog);

        dialog.addEventListener('close', () => {
            pending = null;
        });

        document.querySelectorAll('form[onsubmit], form[data-confirm]').forEach(form => {
            const match = (form.getAttribute('onsubmit') || '').match(/^\s*return confirm\((['"])(.*?)\1\);?\s*$/);
            const confirmation = form.dataset.confirm || match?.[2];

            if (!confirmation) {
                return;
            }

            form.removeAttribute('onsubmit');

            form.addEventListener('submit', event => {
                if (form.dataset.confirmed === 'true') {
                    delete form.dataset.confirmed;
                    return;
                }

                event.preventDefault();

                pending = {
                    form,
                    submitter: event.submitter
                };

                const actionLabel = event.submitter?.textContent.trim() || 'Confirm';

                title.textContent = actionLabel + '?';
                proceed.textContent = actionLabel;
                message.textContent = confirmation;

                dialog.showModal();
                cancel.focus();
            });
        });
    } else {
        document.querySelectorAll('form[data-confirm]:not([onsubmit])').forEach(form => {
            form.addEventListener('submit', event => {
                if (!window.confirm(form.dataset.confirm)) {
                    event.preventDefault();
                }
            });
        });
    }

    /*
     * Filter panels.
     * Do not auto-add Clear button here because filter pages already have their own Clear button.
     */
    document.querySelectorAll('form.filter-panel').forEach(form => {
        const cleanUrl = new URL(form.action, location.href);
        cleanUrl.search = '';
        cleanUrl.hash = '';

        form.querySelectorAll('input[type="search"]').forEach(input => {
            const initiallyFiltered = input.value.trim() !== '';

            input.addEventListener('input', () => {
                if (initiallyFiltered && input.value === '') {
                    location.assign(cleanUrl.href);
                }
            });
        });
    });

    /*
     * Add Clear fields button to normal forms.
     * Forms with data-no-auto-clear="1" will be skipped.
     */
    document.querySelectorAll('main form:not(.filter-panel):not([data-no-auto-clear])').forEach(form => {
        const fields = Array.from(form.querySelectorAll('input, select, textarea')).filter(field =>
            !field.disabled &&
                    !field.readOnly &&
                    !['hidden', 'submit', 'button', 'reset'].includes(field.type)
        );

        if (!fields.length) {
            return;
        }

        const clear = button('Clear fields', () => {
            fields.forEach(field => {
                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = false;
                } else if (field.tagName === 'SELECT') {
                    const emptyIndex = Array.from(field.options).findIndex(option => option.value === '');
                    field.selectedIndex = emptyIndex >= 0 ? emptyIndex : 0;
                } else {
                    field.value = '';
                }

                field.dispatchEvent(new Event('input', {bubbles: true}));
                field.dispatchEvent(new Event('change', {bubbles: true}));
            });

            fields[0].focus();
        });

        clear.classList.add('clear-fields');
        form.append(clear);
    });

    /*
     * Simple pagination for tables.
     */
    document.querySelectorAll('table.table').forEach(table => {
        const body = table.tBodies[0];

        if (!body) {
            return;
        }

        const allRows = Array.from(body.rows).filter(row => {
            return !row.querySelector('td[colspan]');
        });

        const pageSize = 7;

        if (allRows.length <= pageSize) {
            return;
        }

        let page = 1;

        const pager = make('div', '', 'list-pager');
        const summary = make('span');

        const previous = button('Previous', () => {
            page--;
            render();
        });

        const next = button('Next', () => {
            page++;
            render();
        });

        pager.append(summary, previous, next);

        const tableWrapper = table.closest('.table-scroll') || table;
        tableWrapper.after(pager);

        function getCurrentRows() {
            return Array.from(body.rows).filter(row => {
                return allRows.includes(row);
            });
        }

        function render() {
            const currentRows = getCurrentRows();
            const visibleRows = currentRows.filter(row => !row.hidden);
            const totalPages = Math.max(1, Math.ceil(visibleRows.length / pageSize));

            page = Math.max(1, Math.min(page, totalPages));

            allRows.forEach(row => {
                row.classList.add('page-hidden');
            });

            visibleRows
                    .slice((page - 1) * pageSize, page * pageSize)
                    .forEach(row => {
                        row.classList.remove('page-hidden');
                    });

            const start = visibleRows.length === 0 ? 0 : (page - 1) * pageSize + 1;
            const end = Math.min(page * pageSize, visibleRows.length);

            summary.textContent = `${start}–${end} of ${visibleRows.length} results · Page ${page} of ${totalPages}`;

            previous.disabled = page === 1;
            next.disabled = page === totalPages;
        }

        allRows.forEach(row => {
            new MutationObserver(() => {
                page = 1;
                render();
            }).observe(row, {
                attributes: true,
                attributeFilter: ['hidden']
            });
        });

        new MutationObserver(() => {
            render();
        }).observe(body, {
            childList: true
        });

        render();
    });

    /*
     * Simple pagination for location cards.
     */
    document.querySelectorAll('.card-grid').forEach(grid => {
        const cards = Array.from(grid.querySelectorAll(':scope > .location-card'));

        const pageSize = 8;

        if (cards.length <= pageSize) {
            return;
        }

        let page = 1;

        const pager = make('div', '', 'list-pager');
        const summary = make('span');

        const previous = button('Previous', () => {
            page--;
            render();
        });

        const next = button('Next', () => {
            page++;
            render();
        });

        pager.append(summary, previous, next);
        grid.after(pager);

        function render() {
            const visibleCards = cards.filter(card => !card.hidden);
            const totalPages = Math.max(1, Math.ceil(visibleCards.length / pageSize));

            page = Math.max(1, Math.min(page, totalPages));

            cards.forEach(card => {
                card.classList.add('page-hidden');
            });

            visibleCards
                    .slice((page - 1) * pageSize, page * pageSize)
                    .forEach(card => {
                        card.classList.remove('page-hidden');
                    });

            const start = visibleCards.length === 0 ? 0 : (page - 1) * pageSize + 1;
            const end = Math.min(page * pageSize, visibleCards.length);

            summary.textContent = `${start}–${end} of ${visibleCards.length} results · Page ${page} of ${totalPages}`;

            previous.disabled = page === 1;
            next.disabled = page === totalPages;
        }

        cards.forEach(card => {
            new MutationObserver(() => {
                page = 1;
                render();
            }).observe(card, {
                attributes: true,
                attributeFilter: ['hidden']
            });
        });

        render();
    });
})();