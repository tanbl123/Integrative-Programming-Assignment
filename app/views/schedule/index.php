<?php /** Schedule administration list. Author: Ng Zi Zhang (2406898). Module: Collection Scheduling & Assignment. */ ?>

<div class="page-heading">
    <div>
        <h1>Collection schedules</h1>
        <p class="lead">Generate tasks from full bins, unresolved complaints, or a routine route.</p>
    </div>

    <a class="button" href="<?= url('schedule/create') ?>">Generate schedule</a>
</div>

<form method="get" action="<?= url('schedule') ?>" class="filter-panel schedule-filter-panel">
    <label>
        Date
        <input 
            type="date" 
            name="date" 
            value="<?= e($filters['date']) ?>"
        >
    </label>

    <label>
        Status
        <select name="status">
            <option value="">All statuses</option>

            <?php foreach (['Planned', 'Completed', 'Cancelled'] as $option): ?>
                <option value="<?= e($option) ?>" <?= $filters['status'] === $option ? 'selected' : '' ?>>
                    <?= e($option) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <div class="filter-actions">
        <button class="button" type="submit">Apply</button>
        <a class="button button-secondary" href="<?= url('schedule') ?>">Clear</a>
    </div>
</form>

<div class="table-scroll">
    <table class="table">
        <thead>
            <tr>
                <th>
                    <button type="button" class="sort-header" data-column="0">Schedule</button>
                </th>
                <th>
                    <button type="button" class="sort-header" data-column="1">Date/time</button>
                </th>
                <th>
                    <button type="button" class="sort-header" data-column="2">Strategy</button>
                </th>
                <th>
                    <button type="button" class="sort-header" data-column="3">Tasks</button>
                </th>
                <th>
                    <button type="button" class="sort-header" data-column="4">Status</button>
                </th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($schedules as $schedule): ?>
            <tr>
                <td>#<?= $schedule->getKey() ?></td>

                <td>
                    <?= e($schedule->getDate()) ?><br>
                    <?= e($schedule->getTimeSlot()) ?>
                </td>

                <td><?= e($schedule->getStrategy()) ?></td>

                <td><?= count($schedule->getAssignments()) ?></td>

                <td>
                    <span class="badge badge-status">
                        <?= e($schedule->getStatus()) ?>
                    </span>
                </td>

                <td>
                    <a href="<?= url('schedule/show/' . $schedule->getKey()) ?>">View</a>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if ($schedules === []): ?>
            <tr>
                <td colspan="6" class="empty">No schedules match the filters.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.querySelector('.table');
    if (!table || !table.tBodies.length) return;

    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.querySelectorAll('tr'))
        .filter(row => !row.querySelector('td[colspan]'));

    let currentColumn = null;
    let currentDirection = 'asc';

    function sortTable(column) {
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
    }

    document.querySelectorAll('.sort-header').forEach(button => {
        button.addEventListener('click', function () {
            sortTable(parseInt(button.dataset.column, 10));
        });
    });
});
</script>