/* Shared progressive enhancements. Author: Ong Kar Heng (2408830).
 * All persistence remains in authenticated MVC actions.
 */
(() => {
    'use strict';
    const make = (tag, text, className) => {
        const node = document.createElement(tag);
        if (text) node.textContent = text;
        if (className) node.className = className;
        return node;
    };
    const button = (text, action) => {
        const node = make('button', text, 'button button-secondary');
        node.type = 'button';
        node.addEventListener('click', action);
        return node;
    };
    const label = (text, control) => {
        const node = make('label', text);
        node.append(control);
        return node;
    };
    const select = (options) => {
        const node = make('select');
        options.forEach(([value, text]) => node.add(new Option(text, value)));
        return node;
    };
    document.querySelectorAll('main a:not(.button)').forEach(a => {
        if (/^(view|edit|sign in|sign out|profile|register|back|cancel|complete|create|add|delete|reactivate)/i.test(a.textContent.trim())) {
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
    // Retain native confirmation as the no-JavaScript / unsupported-browser fallback.
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
        dialog.addEventListener('close', () => { pending = null; });
        document.querySelectorAll('form[onsubmit], form[data-confirm]').forEach(form => {
            const match = (form.getAttribute('onsubmit') || '').match(/^\s*return confirm\((['"])(.*?)\1\);?\s*$/);
            const confirmation = form.dataset.confirm || match?.[2];
            if (!confirmation) return;
            form.removeAttribute('onsubmit');
            form.addEventListener('submit', event => {
                if (form.dataset.confirmed === 'true') {
                    delete form.dataset.confirmed;
                    return;
                }
                event.preventDefault();
                pending = {form, submitter: event.submitter};
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
                if (!window.confirm(form.dataset.confirm)) event.preventDefault();
            });
        });
    }
    document.querySelectorAll('form.filter-panel').forEach(form => {
        const cleanUrl = new URL(form.action, location.href);
        cleanUrl.search = '';
        cleanUrl.hash = '';
        const clear = make('a', 'Clear filters', 'button button-secondary');
        clear.href = cleanUrl.href;
        form.append(clear);
        form.querySelectorAll('input[type="search"]').forEach(input => {
            const initiallyFiltered = input.value.trim() !== '';
            input.addEventListener('input', () => {
                if (initiallyFiltered && input.value === '') location.assign(cleanUrl.href);
            });
        });
    });
    document.querySelectorAll('main form:not(.filter-panel)').forEach(form => {
        const fields = Array.from(form.querySelectorAll('input, select, textarea')).filter(field =>
            !field.disabled && !field.readOnly && !['hidden', 'submit', 'button', 'reset'].includes(field.type));
        if (!fields.length) return;
        const clear = button('Clear fields', () => {
            fields.forEach(field => {
                if (field.type === 'checkbox' || field.type === 'radio') field.checked = false;
                else if (field.tagName === 'SELECT') field.selectedIndex = Array.from(field.options).findIndex(option => option.value === '');
                else field.value = '';
                field.dispatchEvent(new Event('input', {bubbles: true}));
                field.dispatchEvent(new Event('change', {bubbles: true}));
            });
            fields[0].focus();
        });
        clear.classList.add('clear-fields');
        form.append(clear);
    });
    // Search/filter/sort the entire rendered result set before choosing a page.
    // Existing GET filters remain server-side; the toolbar clearly labels this scope.
    //
    // Sorting is driven by clicking a column header (the familiar spreadsheet
    // gesture) rather than a separate dropdown. Card grids have no headers, so
    // they keep a select as the fallback.
    // Updated: Tan Boon Leong (2402865) - header sorting and numbered pagination.
    function enhance(container, rows, columns, valueOf, anchor, table) {
        if (!rows.length) return;
        let page = 1;
        let sortIndex = null;
        let sortDir = 'asc';

        const toolbar = make('div', '', 'list-tools');
        const search = make('input');
        search.type = 'search';
        search.placeholder = 'Search these results';
        const perPage = select([['10', '10'], ['25', '25'], ['50', '50'], ['all', 'All']]);
        toolbar.append(label('Search results', search), label('Per page', perPage));

        // Header sorting when we have a real table; a dropdown otherwise.
        const headerCells = table && table.tHead ? table.tHead.rows[0].cells : null;
        let sortSelect = null;
        if (!headerCells) {
            sortSelect = select([
                ['', 'Original order'],
                ...columns.flatMap(([index, name]) => [[index + ':asc', name + ' ↑'], [index + ':desc', name + ' ↓']]),
            ]);
            toolbar.append(label('Sort by', sortSelect));
        }

        const filters = [];
        columns.filter(([, name]) => /status|role|category|building|strategy|cleaner/i.test(name)).forEach(([index, name]) => {
            const values = [...new Set(rows.map(row => valueOf(row, index)).filter(Boolean))].sort();
            if (!values.length || values.length > 40) return;
            const control = select([['', 'All'], ...values.map(value => [value, value])]);
            filters.push({index, control});
            toolbar.append(label(name, control));
        });

        const pager = make('nav', '', 'list-pager');
        pager.setAttribute('aria-label', 'Pagination');
        const summary = make('span');
        summary.setAttribute('role', 'status');
        const numbers = make('div', '', 'pager-pages');
        const previous = button('‹ Previous', () => { page--; render(); });
        const next = button('Next ›', () => { page++; render(); });
        previous.classList.add('button-small');
        next.classList.add('button-small');
        pager.append(summary, previous, numbers, next);

        const empty = make('p', 'No results match. Clear the filters to see all loaded records.', 'empty');
        empty.hidden = true;
        anchor.before(toolbar);
        anchor.after(empty, pager);

        // Wire each sortable column header: click or Enter/Space cycles
        // ascending -> descending -> unsorted.
        if (headerCells) {
            columns.forEach(([index, name]) => {
                const cell = headerCells[index];
                if (!cell) return;
                cell.classList.add('sortable');
                cell.tabIndex = 0;
                cell.setAttribute('role', 'button');
                cell.title = 'Sort by ' + name;
                const arrow = make('span', '⇅', 'sort-arrow');
                arrow.setAttribute('aria-hidden', 'true');
                cell.append(' ', arrow);
                const activate = () => {
                    if (sortIndex === index) {
                        if (sortDir === 'asc') { sortDir = 'desc'; }
                        else { sortIndex = null; sortDir = 'asc'; }
                    } else {
                        sortIndex = index;
                        sortDir = 'asc';
                    }
                    page = 1;
                    render();
                };
                cell.addEventListener('click', activate);
                cell.addEventListener('keydown', event => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        activate();
                    }
                });
            });
        }

        const paintHeaders = () => {
            if (!headerCells) return;
            columns.forEach(([index]) => {
                const cell = headerCells[index];
                if (!cell) return;
                const arrow = cell.querySelector('.sort-arrow');
                const active = sortIndex === index;
                if (active) {
                    cell.setAttribute('aria-sort', sortDir === 'asc' ? 'ascending' : 'descending');
                } else {
                    cell.removeAttribute('aria-sort');
                }
                if (arrow) { arrow.textContent = active ? (sortDir === 'asc' ? '↑' : '↓') : '⇅'; }
            });
        };

        // Numbered pages, collapsing long runs to an ellipsis so the control
        // stays one line: 1 ... 4 [5] 6 ... 20
        const paintPages = (current, total) => {
            numbers.replaceChildren();
            if (total <= 1) return;
            const wanted = new Set([1, total, current, current - 1, current + 1]);
            if (current <= 3) { wanted.add(2).add(3); }
            if (current >= total - 2) { wanted.add(total - 1).add(total - 2); }
            const shown = [...wanted].filter(n => n >= 1 && n <= total).sort((a, b) => a - b);
            let previousNumber = 0;
            shown.forEach(number => {
                if (number - previousNumber > 1) {
                    numbers.append(make('span', '…', 'pager-ellipsis'));
                }
                const node = make('button', String(number), 'pager-page');
                node.type = 'button';
                if (number === current) {
                    node.setAttribute('aria-current', 'page');
                } else {
                    node.setAttribute('aria-label', 'Go to page ' + number);
                }
                node.addEventListener('click', () => { page = number; render(); });
                numbers.append(node);
                previousNumber = number;
            });
        };

        const render = () => {
            const term = search.value.trim().toLocaleLowerCase();
            let matching = rows.filter(row => row.textContent.toLocaleLowerCase().includes(term)
                && filters.every(filter => !filter.control.value || valueOf(row, filter.index) === filter.control.value));

            let index = sortIndex;
            let direction = sortDir;
            if (sortSelect && sortSelect.value) {
                const parts = sortSelect.value.split(':');
                index = +parts[0];
                direction = parts[1];
            }
            if (index !== null && index !== undefined) {
                matching.sort((a, b) => valueOf(a, +index).localeCompare(valueOf(b, +index), undefined, {numeric: true, sensitivity: 'base'})
                    * (direction === 'asc' ? 1 : -1));
            }

            const size = perPage.value === 'all' ? Math.max(1, matching.length) : +perPage.value;
            const pages = Math.max(1, Math.ceil(matching.length / size));
            page = Math.max(1, Math.min(page, pages));
            rows.forEach(row => { row.hidden = true; });
            matching.slice((page - 1) * size, page * size).forEach(row => { row.hidden = false; container.append(row); });

            summary.textContent = matching.length
                ? `${(page - 1) * size + 1}–${Math.min(page * size, matching.length)} of ${matching.length} results`
                : '0 results';
            previous.disabled = page === 1;
            next.disabled = page === pages;
            // A single page needs no navigation - only the result summary.
            previous.hidden = pages === 1;
            next.hidden = pages === 1;
            pager.hidden = matching.length === 0;
            paintPages(page, pages);
            paintHeaders();
            empty.hidden = matching.length !== 0;
        };

        toolbar.append(button('Clear result filters', () => {
            search.value = '';
            if (sortSelect) { sortSelect.value = ''; }
            sortIndex = null;
            sortDir = 'asc';
            filters.forEach(filter => { filter.control.value = ''; });
            page = 1;
            render();
        }));
        toolbar.addEventListener('input', () => { page = 1; render(); });
        toolbar.addEventListener('change', () => { page = 1; render(); });
        render();
    }
    document.querySelectorAll('table').forEach(table => {
        const body = table.tBodies[0];
        if (!body || !table.tHead) return;
        const rows = Array.from(body.rows).filter(row => !row.querySelector('td[colspan]'));
        const columns = Array.from(table.tHead.rows[0].cells).map((cell, index) => [index, cell.textContent.trim()]).filter(([, name]) => name && !/^actions?$/i.test(name));
        enhance(body, rows, columns, (row, index) => row.cells[index]?.textContent.trim() || '', table.closest('.table-scroll') || table, table);
    });
    document.querySelectorAll('.card-grid').forEach(grid => {
        const rows = Array.from(grid.querySelectorAll(':scope > .location-card'));
        enhance(grid, rows, [[0, 'Building'], [1, 'Location']], (row, index) => row.querySelector(index === 0 ? '.eyebrow' : 'h2')?.textContent.trim() || '', grid);
    });
})();
