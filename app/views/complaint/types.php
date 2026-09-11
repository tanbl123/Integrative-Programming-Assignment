<?php /** Issue type maintenance. Author: Tan Boon Leong (2402865). Module: Complaint / Report Management. */ ?>

<?php $editing = $editId !== null; ?>

<div class="page-heading">
    <div>
        <h1>Issue types</h1>
        <p class="lead">
            What a reporter may choose when describing a waste issue.
        </p>
    </div>
</div>

<form
    method="post"
    action="<?= url($editing ? 'complaint-type/update/' . $editId : 'complaint-type/store') ?>"
    class="form-card"
    id="issue-type-form"
>
    <?= csrfField() ?>

    <h2><?= $editing ? 'Rename issue type' : 'Add an issue type' ?></h2>

    <?php if (!empty($errors['type'])): ?>
        <div class="alert alert-error"><?= e($errors['type']) ?></div>
    <?php endif; ?>

    <?php /* The name is the whole of it. A new type is available to reporters
             at once - one nobody may choose would be pointless - and where it
             sits in the dropdown is decided for it, after the types already
             there. */ ?>
    <label>
        Name
        <input
            name="type_name"
            required
            maxlength="50"
            title="Give the issue type a name of 1-50 characters."
            placeholder="Pest Sighting"
            value="<?= e((string) ($values['type_name'] ?? '')) ?>"
        >
        <?php if (!empty($errors['type_name'])): ?>
            <span class="field-error"><?= e($errors['type_name']) ?></span>
        <?php endif; ?>
    </label>

    <button class="button" type="submit"><?= $editing ? 'Save name' : 'Add issue type' ?></button>
    <?php if ($editing): ?>
        <a class="button button-secondary" href="<?= url('complaint-type') ?>">Cancel</a>
    <?php endif; ?>
</form>

<form method="get" action="<?= url('complaint-type') ?>" class="filter-panel user-filter-panel" id="typeFilterForm">
    <div class="filter-field filter-search">
        <label>Search</label>
        <input type="search" name="q" id="typeSearch" placeholder="Issue type">
    </div>

    <div class="filter-field">
        <label>Availability</label>
        <select id="typeAvailability">
            <option value="">All</option>
            <option value="Available">Available</option>
            <option value="Withdrawn">Withdrawn</option>
        </select>
    </div>

    <div class="filter-actions">
        <button class="button button-secondary" type="button" id="clearTypeFilters">Clear</button>
    </div>
</form>

<div class="table-scroll">
    <table class="table" id="issueTypesTable">
        <thead>
            <tr>
                <th><button type="button" class="sort-header" data-column="0">Issue type</button></th>
                <th><button type="button" class="sort-header" data-column="1">Available</button></th>
                <th><button type="button" class="sort-header" data-column="2">Complaints filed</button></th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($types as $type): ?>
                <?php $used = $type->complaintCount(); ?>
                <tr
                    class="type-row"
                    data-search="<?= e(mb_strtolower($type->getName())) ?>"
                    data-available="<?= $type->isActive() ? 'Available' : 'Withdrawn' ?>"
                >
                    <td><strong><?= e($type->getName()) ?></strong></td>
                    <td>
                        <span class="badge badge-status">
                            <?= $type->isActive() ? 'Available' : 'Withdrawn' ?>
                        </span>
                    </td>
                    <td><?= $used ?></td>
                    <td>
                        <div class="button-row">
                            <a class="button button-secondary"
                               href="<?= url('complaint-type?edit=' . $type->getKey()) ?>">Rename</a>

                            <?php /* Withdrawing is the counterpart to deleting, and for a
                                     type anyone has used it is the only option: the row has
                                     to stay for those complaints to remain valid. */ ?>
                            <form method="post" action="<?= url('complaint-type/toggle/' . $type->getKey()) ?>">
                                <?= csrfField() ?>
                                <input type="hidden" name="is_active" value="<?= $type->isActive() ? '0' : '1' ?>">
                                <button class="button button-secondary" type="submit">
                                    <?= $type->isActive() ? 'Withdraw' : 'Make available' ?>
                                </button>
                            </form>

                            <?php if ($used === 0): ?>
                                <form method="post" action="<?= url('complaint-type/delete/' . $type->getKey()) ?>"
                                      data-confirm="Delete &quot;<?= e($type->getName()) ?>&quot;? No complaint has been filed under it.">
                                    <?= csrfField() ?>
                                    <button class="button button-danger" type="submit">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <tr id="noTypeResults" hidden>
                <td colspan="4" class="empty">No issue types match the filters.</td>
            </tr>

            <?php if ($types === []): ?>
                <tr><td colspan="4" class="empty">No issue types have been defined.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php /*
 * Filtering and sorting, to the same pattern as the complaint listing.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 *
 * Paging is not set up here: ui.js already pages any table.table past seven
 * rows, so this table gains a pager on its own once there are enough types to
 * need one, exactly as the other listings do.
 */ ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('typeFilterForm');
    const search = document.getElementById('typeSearch');
    const availability = document.getElementById('typeAvailability');
    const clear = document.getElementById('clearTypeFilters');
    const table = document.getElementById('issueTypesTable');
    const noResults = document.getElementById('noTypeResults');
    if (!form || !table) return;

    const tbody = table.tBodies[0];
    const rows = Array.from(table.querySelectorAll('.type-row'));

    let currentColumn = null;
    let currentDirection = 'asc';

    form.addEventListener('submit', event => event.preventDefault());

    const filter = () => {
        const keyword = search.value.toLowerCase().trim();
        const state = availability.value;
        let shown = 0;

        rows.forEach(row => {
            const matches = row.dataset.search.includes(keyword)
                && (state === '' || row.dataset.available === state);
            row.hidden = !matches;
            if (matches) shown++;
        });
        noResults.hidden = shown !== 0;
    };

    const sort = column => {
        currentDirection = currentColumn === column && currentDirection === 'asc' ? 'desc' : 'asc';
        currentColumn = column;

        rows.sort((a, b) => a.cells[column].textContent.trim()
            .localeCompare(b.cells[column].textContent.trim(), undefined,
                {numeric: true, sensitivity: 'base'})
            * (currentDirection === 'asc' ? 1 : -1));

        rows.forEach(row => tbody.appendChild(row));
        tbody.appendChild(noResults);
        paint();
        filter();
    };

    search.addEventListener('input', filter);
    availability.addEventListener('change', filter);
    clear.addEventListener('click', () => {
        search.value = '';
        availability.value = '';
        filter();
    });

    const buttons = Array.from(table.querySelectorAll('.sort-header'));

    function paint() {
        buttons.forEach(button => {
            const column = Number(button.dataset.column);
            const arrow = button.querySelector('.sort-arrow');
            arrow.textContent = currentColumn === column
                ? (currentDirection === 'asc' ? '↑' : '↓')
                : '⇅';
        });
    }

    buttons.forEach(button => {
        button.closest('th')?.classList.add('th-sortable');
        const arrow = document.createElement('span');
        arrow.className = 'sort-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        button.append(arrow);
        button.addEventListener('click', () => sort(Number(button.dataset.column)));
    });

    paint();
});
</script>
