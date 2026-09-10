<?php /** User administration. Author: Phang Jun Hong (2406646). Module: User & Access Management. */ ?>

<div class="page-heading"> 
    <div>
        <h1>User accounts</h1>
        <p class="lead">Manage roles and active access.</p>
    </div> 
    <a class="button" href="<?= url('user/create') ?>">Add user</a> 
</div> 

<form method="get" action="<?= url('user') ?>" class="filter-panel user-filter-panel" id="userFilterForm"> 
    <div class="filter-field filter-search">
        <label>Search</label>
        <input 
            type="search" 
            name="q" 
            id="userSearch"
            value="<?= e($filters['query']) ?>" 
            placeholder="Name or email"
        >
    </div>

    <div class="filter-field">
        <label>Role</label>
        <select name="role" id="roleFilter">
            <option value="">All roles</option>
            <?php foreach ($roles as $role): ?>
                <option value="<?= e($role) ?>" <?= $filters['role'] === $role ? 'selected' : '' ?>>
                    <?= e($role) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-field">
        <label>Status</label>
        <select name="status" id="statusFilter">
            <option value="">All statuses</option>
            <option value="Active" <?= $filters['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= $filters['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>

    <div class="filter-actions">
        <button class="button button-secondary" type="button" id="clearUserFilters">Clear</button>
    </div>
</form> 

<div class="table-scroll">
    <table class="table">
        <thead>
            <tr>
                <th><button type="button" class="sort-header" data-column="0">Name</button></th>
                <th><button type="button" class="sort-header" data-column="1">Email</button></th>
                <th><button type="button" class="sort-header" data-column="2">Role</button></th>
                <th><button type="button" class="sort-header" data-column="3">Status</button></th>
                <th><button type="button" class="sort-header" data-column="4">Created</button></th>
                <th></th>
            </tr>
        </thead>

        <tbody> 
        <?php foreach ($users as $account): ?>
            <tr 
                class="user-row"
                data-search="<?=e(mb_strtolower(
                        $account->getFullName() . ' ' .
                        $account->getEmail()
                ))?>"
                data-role="<?= e($account->getRole()) ?>"
                data-status="<?= e($account->getAccountStatus()) ?>"
            >
                <td><?= e($account->getFullName()) ?></td>
                <td><?= e($account->getEmail()) ?></td>
                <td><?= e($account->getRole()) ?></td>
                <td>
                    <span class="badge <?= $account->isActive() ? 'badge-status' : 'badge-muted' ?>">
                        <?= e($account->getAccountStatus()) ?>
                    </span>
                </td>
                <td><?= e($account->getCreatedAt()) ?></td>
                
                <td>
                    <div class="table-actions">
                        <a class="button button-secondary" href="<?= url('user/edit/' . $account->getKey()) ?>">
                            Edit
                        </a>

                        <?php if ($account->getKey() !== Auth::user()?->getKey()): ?>
                            <form 
                                method="post" 
                                action="<?= url('user/resetPassword/' . $account->getKey()) ?>" 
                                data-confirm="Reset this user's password? A new temporary password will be generated and shown once."
                            >
                                <?= csrfField() ?>
                                <button class="button button-secondary" type="submit">
                                    Reset
                                </button>
                            </form>

                            <form 
                                method="post" 
                                action="<?= url('user/delete/' . $account->getKey()) ?>" 
                                data-confirm="Delete this account? Access will be removed; historical records will be preserved."
                            >
                                <?= csrfField() ?>
                                <button class="button button-danger" type="submit">
                                    Delete
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?> 

        <tr id="noUserResults" <?= $users === [] ? '' : 'hidden' ?>>
            <td colspan="6" class="empty">No users match the filters.</td>
        </tr>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('userFilterForm');
    const search = document.getElementById('userSearch');
    const role = document.getElementById('roleFilter');
    const status = document.getElementById('statusFilter');
    const clear = document.getElementById('clearUserFilters');
    const tbody = document.querySelector('.table tbody');
    const rows = Array.from(document.querySelectorAll('.user-row'));
    const noResults = document.getElementById('noUserResults');

    let currentColumn = null;
    let currentDirection = 'asc';

    form.addEventListener('submit', function (event) {
        event.preventDefault();
    });

    function filterTable() {
        const keyword = search.value.toLowerCase().trim();
        const selectedRole = role.value;
        const selectedStatus = status.value;
        let count = 0;

        rows.forEach(row => {
            const matchSearch = row.dataset.search.includes(keyword);
            const matchRole = selectedRole === '' || row.dataset.role === selectedRole;
            const matchStatus = selectedStatus === '' || row.dataset.status === selectedStatus;

            const show = matchSearch && matchRole && matchStatus;
            row.hidden = !show;

            if (show) {
                count++;
            }
        });

        if (noResults) {
            noResults.hidden = count !== 0;
        }
    }

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

        if (noResults) {
            tbody.appendChild(noResults);
        }

        filterTable();
    }

    search.addEventListener('input', filterTable);
    role.addEventListener('change', filterTable);
    status.addEventListener('change', filterTable);

    clear.addEventListener('click', function () {
        search.value = '';
        role.value = '';
        status.value = '';
        filterTable();
    });

    document.querySelectorAll('.sort-header').forEach(button => {
        button.addEventListener('click', function () {
            sortTable(parseInt(button.dataset.column, 10));
        });
    });

    filterTable();
});
</script>