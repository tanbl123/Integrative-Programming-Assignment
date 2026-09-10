<?php
/**
 * Searchable bin register.
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
?>

<div class="page-heading">
    <div>
        <h1>Campus bins</h1>
        <p class="lead">Search bins by code, building, location, or current fill status.</p>
    </div>

    <?php if ($user->isAdmin()): ?>
        <a class="button" href="<?= url('bin/create') ?>">Register bin</a>
    <?php endif; ?>
</div>

<form method="get" action="<?= url('bin') ?>" class="filter-panel bin-filter-panel" id="binFilterForm">
    <div class="filter-field filter-search">
        <label>Search</label>
        <input 
            type="search" 
            name="q" 
            id="binSearch"
            value="<?= e($filters['q']) ?>" 
            placeholder="Bin code or location"
        >
    </div>

    <div class="filter-field">
        <label>Status</label>
        <select name="status" id="binStatusFilter">
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
        <select name="location_id" id="binLocationFilter">
            <option value="">All locations</option>
            <?php foreach ($locations as $location): ?>
                <option value="<?= $location->getKey() ?>" <?= $filters['location_id'] === $location->getKey() ? 'selected' : '' ?>>
                    <?= e($location->getFullLabel()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($user->isAdmin()): ?>
        <label class="checkbox-label bin-checkbox">
            <input 
                type="checkbox" 
                name="include_inactive" 
                value="1" 
                id="includeInactiveBins"
                <?= $filters['include_inactive'] ? 'checked' : '' ?>
                >
            Show inactive
        </label>
    <?php endif; ?>

    <div class="filter-actions">
        <button class="button button-secondary" type="button" id="clearBinFilters">Clear</button>
    </div>
</form>

<div class="table-scroll">
    <table class="table">
        <thead>
            <tr>
                <th><button type="button" class="sort-header" data-column="0">Bin</button></th>
                <th><button type="button" class="sort-header" data-column="1">Location</button></th>
                <th><button type="button" class="sort-header" data-column="2">Category</button></th>
                <th><button type="button" class="sort-header" data-column="3">Capacity</button></th>
                <th><button type="button" class="sort-header" data-column="4">Status</button></th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($bins as $bin): ?>
            <tr 
                class="bin-row <?= $bin->isActive() ? '' : 'row-inactive' ?>"
                data-search="<?= e(mb_strtolower(
                    $bin->getBinCode() . ' ' .
                    ($bin->getLocation()?->getFullLabel() ?? '')
                )) ?>"
                data-status="<?= e($bin->getFillStatus()) ?>"
                data-location="<?= e((string) ($bin->getLocation()?->getKey() ?? '')) ?>"
                data-active="<?= $bin->isActive() ? '1' : '0' ?>"
            >
                <td>
                    <a href="<?= url('bin/show/' . $bin->getKey()) ?>">
                        <strong><?= e($bin->getBinCode()) ?></strong>
                    </a>

                    <?php if (!$bin->isActive()): ?>
                        <span class="badge badge-muted">Inactive</span>
                    <?php endif; ?>
                </td>

                <td><?= e($bin->getLocation()?->getFullLabel() ?? 'Unknown') ?></td>
                <td><?= e($bin->getCategory()?->getCategoryName() ?? 'Unknown') ?></td>
                <td><?= $bin->getCapacityLitre() === null ? '—' : $bin->getCapacityLitre() . ' L' ?></td>
                <td><span class="badge badge-status"><?= e($bin->getFillStatus()) ?></span></td>

                <td class="actions">
                    <a 
                        class="button button-secondary button-small" 
                        href="<?= url('bin/show/' . $bin->getKey()) ?>"
                        >
                        View
                    </a>

                    <?php if ($user->isAdmin()): ?>
                        <a 
                            class="button button-secondary button-small" 
                            href="<?= url('bin/edit/' . $bin->getKey()) ?>"
                            >
                            Edit
                        </a>
                    <?php elseif ($user->isCleaner() && $bin->isActive()): ?>
                        <a 
                            class="button button-secondary button-small" 
                            href="<?= url('bin/status/' . $bin->getKey()) ?>"
                            >
                            Update status
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

        <tr id="noBinResults" <?= $bins === [] ? '' : 'hidden' ?>>
            <td colspan="6" class="empty">No bins match the selected filters.</td>
        </tr>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('binFilterForm');
    const search = document.getElementById('binSearch');
    const status = document.getElementById('binStatusFilter');
    const location = document.getElementById('binLocationFilter');
    const includeInactive = document.getElementById('includeInactiveBins');
    const clear = document.getElementById('clearBinFilters');
    const tbody = document.querySelector('.table tbody');
    const rows = Array.from(document.querySelectorAll('.bin-row'));
    const noResults = document.getElementById('noBinResults');

    let currentColumn = null;
    let currentDirection = 'asc';

    form.addEventListener('submit', function (event) {
        event.preventDefault();
    });

    function filterBins() {
        const keyword = search.value.toLowerCase().trim();
        const selectedStatus = status.value;
        const selectedLocation = location.value;
        const showInactive = includeInactive ? includeInactive.checked : true;
        let count = 0;

        rows.forEach(row => {
            const matchSearch = row.dataset.search.includes(keyword);
            const matchStatus = selectedStatus === '' || row.dataset.status === selectedStatus;
            const matchLocation = selectedLocation === '' || row.dataset.location === selectedLocation;
            const matchActive = showInactive || row.dataset.active === '1';

            const show = matchSearch && matchStatus && matchLocation && matchActive;
            row.hidden = !show;

            if (show) {
                count++;
            }
        });

        if (noResults) {
            noResults.hidden = count !== 0;
        }
    }

    function sortBins(column) {
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

        filterBins();
    }

    search.addEventListener('input', filterBins);
    status.addEventListener('change', filterBins);
    location.addEventListener('change', filterBins);

    if (includeInactive) {
        includeInactive.addEventListener('change', filterBins);
    }

    clear.addEventListener('click', function () {
        search.value = '';
        status.value = '';
        location.value = '';

        if (includeInactive) {
            includeInactive.checked = false;
        }

        filterBins();
    });

    document.querySelectorAll('.sort-header').forEach(button => {
        button.addEventListener('click', function () {
            sortBins(parseInt(button.dataset.column, 10));
        });
    });

    filterBins();
});
</script>