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
    <table class="table">
        <thead>
            <tr>
                <th><button type="button" class="sort-header" data-column="0">Reference</button></th>
                <th><button type="button" class="sort-header" data-column="1">Bin</button></th>
                <th><button type="button" class="sort-header" data-column="2">Issue</button></th>
                <th><button type="button" class="sort-header" data-column="3">Status</button></th>
                <th><button type="button" class="sort-header" data-column="4">Submitted</button></th>
                <th></th>
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
                                    '#' . $complaint->getKey() . ' ' .
                                    ($bin?->getBinCode() ?? '') . ' ' .
                                    ($location?->getFullLabel() ?? '') . ' ' .
                                    $complaint->getType()
                            ))
                    ?>"
                    data-status="<?= e($complaint->getStatus()) ?>"
                    data-location="<?= e((string) ($location?->getKey() ?? '')) ?>"
                    >
                    <td>#<?= $complaint->getKey() ?></td>

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
        const tbody = document.querySelector('.table tbody');
        const rows = Array.from(document.querySelectorAll('.complaint-row'));
        const noResults = document.getElementById('noComplaintResults');

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

        document.querySelectorAll('.sort-header').forEach(button => {
            button.addEventListener('click', function () {
                sortComplaints(parseInt(button.dataset.column, 10));
            });
        });

        filterComplaints();
    });
</script>