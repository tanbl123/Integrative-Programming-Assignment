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
     *
     * Built once here and published as EcoCampus.confirm(), so that anything
     * needing to ask before it acts - a delete, an unsaved form being
     * abandoned - asks in the same dialog rather than in the browser's own
     * unstyleable one.
     */
    let confirmWith = null;

    /*
     * Recognising a destructive form without being told.
     *
     * Two signals, either of which is enough: the action it posts to, and a
     * button styled as dangerous. Every module already writes both - user and
     * location delete, schedule cancel, bin deactivate, complaint withdraw -
     * so the confirmation follows the convention the team already uses rather
     * than asking anyone to remember a new attribute.
     */
    const UNDOING_ACTION = /\/(delete|cancel|deactivate|reject|remove|reset)/i;

    const GENERIC_CONFIRMATION =
        'This action cannot be undone from this page. Do you want to continue?';

    const undoesSomething = form =>
        UNDOING_ACTION.test(form.getAttribute('action') || '')
        || form.querySelector('.button-danger') !== null;

    if (typeof HTMLDialogElement !== 'undefined') {
        const dialog = make('dialog', '', 'confirm-dialog');

        const title = make('h2', 'Confirm action');
        title.id = 'confirmation-title';

        const message = make('p');
        message.id = 'confirmation-message';

        dialog.setAttribute('aria-labelledby', title.id);
        dialog.setAttribute('aria-describedby', message.id);

        const actions = make('div', '', 'button-row');

        // Resolved by whichever button closes the dialog.
        let settle = null;

        const finish = answer => {
            const resolve = settle;
            settle = null;
            dialog.close();

            if (resolve) {
                resolve(answer);
            }
        };

        const cancel = button('Keep unchanged', () => finish(false));
        const proceed = button('Confirm', () => finish(true));

        proceed.classList.remove('button-secondary');
        proceed.classList.add('button-danger');

        actions.append(cancel, proceed);
        dialog.append(title, message, actions);
        document.body.append(dialog);

        // Escape, or the backdrop, counts as declining.
        dialog.addEventListener('close', () => {
            const resolve = settle;
            settle = null;

            if (resolve) {
                resolve(false);
            }
        });

        /*
         * Asks the question in this dialog and resolves true if the person
         * agreed. Shared so that every confirmation in the system looks the
         * same: the browser's own confirm() cannot be styled and announces
         * itself as coming from localhost.
         */
        confirmWith = options => new Promise(resolve => {
            settle = resolve;

            title.textContent = options.title || 'Confirm action';
            message.textContent = options.message || '';
            proceed.textContent = options.confirmLabel || 'Confirm';
            cancel.textContent = options.cancelLabel || 'Keep unchanged';

            dialog.showModal();
            cancel.focus();
        });

        document.querySelectorAll('main form:not(.filter-panel)').forEach(form => {
            const match = (form.getAttribute('onsubmit') || '').match(/^\s*return confirm\((['"])(.*?)\1\);?\s*$/);

            // A module's own wording wins. Failing that, a form that undoes
            // something is confirmed anyway, with wording that suits any
            // module - so a delete added later is never silent just because
            // nobody remembered the attribute.
            const confirmation = form.dataset.confirm
                || match?.[2]
                || (undoesSomething(form) ? GENERIC_CONFIRMATION : null);

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

                const submitter = event.submitter;
                const actionLabel = submitter?.textContent.trim() || 'Confirm';

                confirmWith({
                    title: actionLabel + '?',
                    message: confirmation,
                    confirmLabel: actionLabel
                }).then(agreed => {
                    if (agreed) {
                        form.dataset.confirmed = 'true';
                        form.requestSubmit(submitter || undefined);
                    }
                });
            });
        });
    } else {
        // No <dialog> support: the browser's own prompt is all that is left.
        confirmWith = options => Promise.resolve(window.confirm(options.message || ''));

        document.querySelectorAll('main form:not(.filter-panel):not([onsubmit])').forEach(form => {
            const confirmation = form.dataset.confirm
                || (undoesSomething(form) ? GENERIC_CONFIRMATION : null);

            if (!confirmation) {
                return;
            }

            form.addEventListener('submit', event => {
                if (!window.confirm(confirmation)) {
                    event.preventDefault();
                }
            });
        });
    }

    window.EcoCampus = window.EcoCampus || {};
    window.EcoCampus.confirm = options => confirmWith(options || {});

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
     * Shared table sorting for User, Bin, Schedule and other simple tables.
     * Complaint table is skipped because complaint/index.php already has its own sorter.
     */
    document.querySelectorAll('table.table').forEach(table => {
        if (table.id === 'complaintsTable') {
            return;
        }

        const tbody = table.tBodies[0];
        const sortButtons = Array.from(table.querySelectorAll('.sort-header[data-column]'));

        if (!tbody || sortButtons.length === 0) {
            return;
        }

        let currentColumn = null;
        let currentDirection = 'asc';

        const rows = Array.from(tbody.rows).filter(row => {
            return !row.querySelector('td[colspan]');
        });

        sortButtons.forEach(button => {
            const header = button.closest('th');

            if (header) {
                header.classList.add('th-sortable');
            }

            if (!button.querySelector('.sort-arrow')) {
                const arrow = document.createElement('span');
                arrow.className = 'sort-arrow';
                arrow.setAttribute('aria-hidden', 'true');
                arrow.textContent = '\u21c5';
                button.append(' ', arrow);
            }

            button.addEventListener('click', function () {
                const column = parseInt(button.dataset.column, 10);

                if (Number.isNaN(column)) {
                    return;
                }

                sortTable(column);
            });
        });

        function sortTable(column) {
            if (currentColumn === column) {
                currentDirection = currentDirection === 'asc' ? 'desc' : 'asc';
            } else {
                currentColumn = column;
                currentDirection = 'asc';
            }

            rows.sort((a, b) => {
                const valueA = a.cells[column]?.textContent.trim() ?? '';
                const valueB = b.cells[column]?.textContent.trim() ?? '';

                return valueA.localeCompare(valueB, undefined, {
                    numeric: true,
                    sensitivity: 'base'
                }) * (currentDirection === 'asc' ? 1 : -1);
            });

            rows.forEach(row => tbody.appendChild(row));

            paintSortIndicators();
        }

        function paintSortIndicators() {
            sortButtons.forEach(button => {
                const column = parseInt(button.dataset.column, 10);
                const header = button.closest('th');
                const arrow = button.querySelector('.sort-arrow');
                const active = column === currentColumn;

                if (header) {
                    if (active) {
                        header.setAttribute(
                                'aria-sort',
                                currentDirection === 'asc' ? 'ascending' : 'descending'
                                );
                    } else {
                        header.removeAttribute('aria-sort');
                    }
                }

                if (arrow) {
                    arrow.textContent = active
                            ? (currentDirection === 'asc' ? '\u2191' : '\u2193')
                            : '\u21c5';
                }
            });
        }

        paintSortIndicators();
    });

    /*
     * A pager rendered as one joined control - Prev | 1 | 2 | Next - with the
     * position caption beneath it, so it reads as a single object rather than
     * two buttons drifting to opposite ends of the row. Long runs of pages
     * collapse to an ellipsis: 1 ... 4 [5] 6 ... 20.
     *
     * Author : Tan Boon Leong (2402865)
     */
    const buildPager = (onChange) => {
        const node = make('nav', '', 'list-pager');
        node.setAttribute('aria-label', 'Pagination');

        const group = make('div', '', 'pager-group');
        let current = 1;
        let total = 1;

        const step = (text, resolve) => {
            const control = make('button', text, 'pager-step');
            control.type = 'button';
            control.addEventListener('click', () => onChange(resolve()));
            return control;
        };

        const previous = step('Prev', () => Math.max(1, current - 1));
        const next = step('Next', () => Math.min(total, current + 1));

        group.append(previous, next);

        const summary = make('p', '', 'pager-summary');
        summary.setAttribute('role', 'status');

        node.append(group, summary);

        const update = (page, totalPages, totalItems) => {
            current = page;
            total = totalPages;

            group.querySelectorAll('.pager-page, .pager-ellipsis').forEach(old => old.remove());

            const wanted = new Set([1, totalPages, page, page - 1, page + 1]);

            if (page <= 3) {
                wanted.add(2).add(3);
            }

            if (page >= totalPages - 2) {
                wanted.add(totalPages - 1).add(totalPages - 2);
            }

            let previousNumber = 0;

            [...wanted]
                    .filter(n => n >= 1 && n <= totalPages)
                    .sort((a, b) => a - b)
                    .forEach(number => {
                        if (number - previousNumber > 1) {
                            group.insertBefore(make('span', '\u2026', 'pager-ellipsis'), next);
                        }

                        const control = make('button', String(number), 'pager-page');
                        control.type = 'button';

                        if (number === page) {
                            control.setAttribute('aria-current', 'page');
                        } else {
                            control.setAttribute('aria-label', 'Go to page ' + number);
                        }

                        control.addEventListener('click', () => onChange(number));
                        group.insertBefore(control, next);
                        previousNumber = number;
                    });

            previous.disabled = page === 1;
            next.disabled = page === totalPages;

            summary.textContent = 'Page ' + page + ' of ' + totalPages + ' \u00b7 '
                    + totalItems + ' result' + (totalItems === 1 ? '' : 's');
        };

        return {node, update};
    };

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

        const pager = buildPager(target => {
            page = target;
            render();
        });

        const tableWrapper = table.closest('.table-scroll') || table;
        tableWrapper.after(pager.node);

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

            pager.update(page, totalPages, visibleRows.length);
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

        const pager = buildPager(target => {
            page = target;
            render();
        });

        grid.after(pager.node);

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

            pager.update(page, totalPages, visibleCards.length);
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
    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-password-toggle]');

        if (!button) {
            return;
        }

        const targetId = button.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = button.querySelector('.password-icon');

        if (!input || !icon) {
            return;
        }

        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = 'visibility_off';
            button.setAttribute('aria-label', 'Hide password');
            button.setAttribute('title', 'Hide password');
        } else {
            input.type = 'password';
            icon.textContent = 'visibility';
            button.setAttribute('aria-label', 'Show password');
            button.setAttribute('title', 'Show password');
        }
    });
})();