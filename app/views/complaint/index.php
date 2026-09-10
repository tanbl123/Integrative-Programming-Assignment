<?php /** Complaint listing. Author: Tan Boon Leong (2402865). Module: Complaint / Report Management. */ ?>

<div class="page-heading">
    <div>
        <h1><?= $user->isAdmin() ? 'All complaints' : 'My complaints' ?></h1>
        <p class="lead">Track reported waste issues from submission to resolution.</p>
    </div>

    <?php if (UserPermissions::can($user, 'complaint.create')): ?>
        <a class="button" href="<?= url('complaint/create') ?>">Report issue</a>
    <?php endif; ?>
</div>

<?php /*
 * Duplicate reports panel - administrators only.
 *
 * Several people reporting one overflowing bin is one problem, not several.
 * Each group is shown as a single row that expands to the individual reports,
 * and can be closed in one action: the oldest report stays open and the rest
 * are rejected as duplicates, each notifying its own reporter.
 */ ?>
<?php if ($duplicateGroups !== []): ?>
    <section class="content-card duplicate-groups">
        <h2>Duplicate reports</h2>
        <p class="lead">
            These bins have more than one open report of the same issue.
            Keep one and reject the rest; every reporter is told the outcome.
        </p>

        <?php foreach ($duplicateGroups as $group): ?>
            <details class="duplicate-group">
                <summary>
                    <strong><?= e($group['bin']?->getBinCode() ?? 'Unknown bin') ?></strong>
                    &middot; <?= e($group['type']) ?>
                    <span class="badge badge-status"><?= count($group['complaints']) ?> reports</span>
                    <small><?= e($group['bin']?->getLocation()?->getFullLabel() ?? '') ?></small>
                </summary>

                <form method="post" action="<?= url('complaint/reject-duplicates') ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="bin_id" value="<?= (int) ($group['bin']?->getKey() ?? 0) ?>">
                    <input type="hidden" name="complaint_type" value="<?= e($group['type']) ?>">

                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Keep</th><th>Complaint ID</th><th>Reporter</th>
                                    <th>Reason given</th><th>Status</th><th>Submitted</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($group['complaints'] as $item): ?>
                                <tr>
                                    <td>
                                        <input
                                            type="radio"
                                            name="keep_id"
                                            value="<?= (int) $item->getKey() ?>"
                                            <?= (int) $item->getKey() === $group['keepId'] ? 'checked' : '' ?>
                                            aria-label="Keep complaint #<?= (int) $item->getKey() ?>">
                                    </td>
                                    <td><?= e($item->getReference()) ?></td>
                                    <td><?= e($item->getReporter()?->getFullName() ?? 'Unknown') ?></td>
                                    <?php /* Each reporter described the issue in their own words. The
                                            administrator needs them side by side to judge which report
                                            to keep, and whether they really are the same issue. */ ?>
                                    <td class="duplicate-reason"><?= e($item->getDescription()) ?></td>
                                    <td><span class="badge badge-status"><?= e($item->getStatus()) ?></span></td>
                                    <td><?= e($item->getCreatedAt()) ?></td>
                                    <td><a href="<?= url('complaint/show/' . $item->getKey()) ?>">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <button
                        class="button button-danger"
                        type="submit"
                        data-confirm="Reject the other <?= count($group['complaints']) - 1 ?> report(s) as duplicates? Each reporter will be notified.">
                        Keep selected &middot; reject the other <?= count($group['complaints']) - 1 ?>
                    </button>
                </form>
            </details>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<form method="get" action="<?= url('complaint') ?>" class="filter-panel user-filter-panel" id="complaintFilterForm">
    <div class="filter-field filter-search">
        <label>Search</label>
        <input 
            type="search" 
            name="q" 
            id="complaintSearch"
            value="<?= e($filters['query']) ?>" 
            placeholder="Issue, bin, or location"
            >
    </div>

    <div class="filter-field">
        <label>Status</label>
        <select name="status" id="complaintStatusFilter">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                    <?= e($status) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-field">
        <label>Location</label>
        <select name="location_id" id="complaintLocationFilter">
            <option value="">All locations</option>
            <?php foreach ($locations as $location): ?>
                <option value="<?= $location->getKey() ?>" <?= (string) $filters['location'] === (string) $location->getKey() ? 'selected' : '' ?>>
                    <?= e($location->getFullLabel()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-actions">
        <button class="button button-secondary" type="button" id="clearComplaintFilters">
            Clear
        </button>
    </div>
</form>

<div class="table-scroll">
    <table class="table" id="complaintsTable">
        <thead>
            <tr>
                <th><button type="button" class="sort-header" data-column="0">Complaint ID</button></th>
                <th><button type="button" class="sort-header" data-column="1">Bin</button></th>
                <th><button type="button" class="sort-header" data-column="2">Issue</button></th>
                <th><button type="button" class="sort-header" data-column="3">Status</button></th>
                <th><button type="button" class="sort-header" data-column="4">Submitted</button></th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($complaints as $complaint): ?>
                <?php
                $bin = $complaint->getBin();
                $location = $bin?->getLocation();
                ?>

                <tr 
                    class="complaint-row"
                    data-search="<?=
                    e(mb_strtolower(
                                    $complaint->getReference() . ' ' .
                                    ($bin?->getBinCode() ?? '') . ' ' .
                                    ($location?->getFullLabel() ?? '') . ' ' .
                                    $complaint->getType()
                            ))
                    ?>"
                    data-status="<?= e($complaint->getStatus()) ?>"
                    data-location="<?= e((string) ($location?->getKey() ?? '')) ?>"
                    >
                    <td><?= e($complaint->getReference()) ?></td>

                    <td>
    <?= e($bin?->getBinCode() ?? 'Unknown') ?><br>
                        <small><?= e($location?->getFullLabel() ?? '') ?></small>
                    </td>

                    <td><?= e($complaint->getType()) ?></td>

                    <td>
                        <span class="badge badge-status">
    <?= e($complaint->getStatus()) ?>
                        </span>
                    </td>

                    <td><?= e($complaint->getCreatedAt()) ?></td>

                    <td>
                        <a href="<?= url('complaint/show/' . $complaint->getKey()) ?>">View</a>
                    </td>
                </tr>
<?php endforeach; ?>

            <tr id="noComplaintResults" <?= $complaints === [] ? '' : 'hidden' ?>>
                <td colspan="6" class="empty">No complaints match the filters.</td>
            </tr>
        </tbody>
    </table>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('complaintFilterForm');
        const search = document.getElementById('complaintSearch');
        const status = document.getElementById('complaintStatusFilter');
        const location = document.getElementById('complaintLocationFilter');
        const clear = document.getElementById('clearComplaintFilters');
        // Must be scoped to the complaints table by id. The duplicate reports
        // panel above also renders .table elements, so a bare '.table tbody'
        // selector matches the first duplicate group instead, and sorting then
        // moves every complaint row into that group's collapsed table.
        const complaintsTable = document.getElementById('complaintsTable');
        const tbody = complaintsTable ? complaintsTable.tBodies[0] : null;
        const rows = Array.from(document.querySelectorAll('.complaint-row'));
        const noResults = document.getElementById('noComplaintResults');

        if (!form || !tbody) {
            return;
        }

        let currentColumn = null;
        let currentDirection = 'asc';

        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });

        function filterComplaints() {
            const keyword = search.value.toLowerCase().trim();
            const selectedStatus = status.value;
            const selectedLocation = location.value;
            let count = 0;

            rows.forEach(row => {
                const matchSearch = row.dataset.search.includes(keyword);
                const matchStatus = selectedStatus === '' || row.dataset.status === selectedStatus;
                const matchLocation = selectedLocation === '' || row.dataset.location === selectedLocation;

                const show = matchSearch && matchStatus && matchLocation;
                row.hidden = !show;

                if (show) {
                    count++;
                }
            });

            if (noResults) {
                noResults.hidden = count !== 0;
            }
        }

        function sortComplaints(column) {
            if (currentColumn === column) {
                currentDirection = currentDirection === 'asc' ? 'desc' : 'asc';
            } else {
                currentColumn = column;
                currentDirection = 'asc';
            }

            rows.sort((a, b) => {
                const valueA = a.cells[column].textContent.trim();
                const valueB = b.cells[column].textContent.trim();

                return valueA.localeCompare(valueB, undefined, {
                    numeric: true,
                    sensitivity: 'base'
                }) * (currentDirection === 'asc' ? 1 : -1);
            });

            rows.forEach(row => tbody.appendChild(row));

            if (noResults) {
                tbody.appendChild(noResults);
            }

            paintSortIndicators();
            filterComplaints();
        }

        search.addEventListener('input', filterComplaints);
        status.addEventListener('change', filterComplaints);
        location.addEventListener('change', filterComplaints);

        clear.addEventListener('click', function () {
            search.value = '';
            status.value = '';
            location.value = '';
            filterComplaints();
        });

        // Each sortable header carries an arrow so the current sort column and
        // direction are visible: neutral until sorted, then up or down.
        const sortButtons = Array.from(complaintsTable.querySelectorAll('.sort-header'));

        sortButtons.forEach(button => {
            // Let the cell hand its padding to the button, so the entire header
            // cell is clickable rather than only the width of the label.
            const header = button.closest('th');
            if (header) {
                header.classList.add('th-sortable');
            }

            const arrow = document.createElement('span');
            arrow.className = 'sort-arrow';
            arrow.setAttribute('aria-hidden', 'true');
            arrow.textContent = '\u21c5';
            button.append(' ', arrow);

            button.addEventListener('click', function () {
                sortComplaints(parseInt(button.dataset.column, 10));
            });
        });

        // aria-sort belongs on the column header itself, so a screen reader
        // announces the sort state when it reads the column.
        function paintSortIndicators() {
            sortButtons.forEach(button => {
                const column = parseInt(button.dataset.column, 10);
                const header = button.closest('th');
                const arrow = button.querySelector('.sort-arrow');
                const active = column === currentColumn;

                if (header) {
                    if (active) {
                        header.setAttribute('aria-sort',
                            currentDirection === 'asc' ? 'ascending' : 'descending');
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
        filterComplaints();
    });
</script>