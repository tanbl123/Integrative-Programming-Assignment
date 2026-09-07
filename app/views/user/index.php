<?php /** User administration. Author: Ong Kar Heng (2408830). */ ?>
<div class="page-heading">
    <div><h1>User accounts</h1><p class="lead">Manage roles and active access.</p></div>
    <a class="button" href="<?= url('user/create') ?>">Add user</a>
</div>
<form method="get" action="<?= url('user') ?>" class="filter-panel">
    <label>Search<input type="search" name="q" value="<?= e($filters['query']) ?>" placeholder="Name, email, or phone"></label>
    <label>Role<select name="role"><option value="">All roles</option><?php foreach ($roles as $role): ?><option <?= $filters['role'] === $role ? 'selected' : '' ?>><?= e($role) ?></option><?php endforeach; ?></select></label>
    <label>Status<select name="status"><option value="">All statuses</option><option <?= $filters['status'] === 'Active' ? 'selected' : '' ?>>Active</option><option <?= $filters['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></label>
    <button class="button button-secondary" type="submit">Apply</button>
</form>
<div class="table-scroll"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody>
<?php foreach ($users as $account): ?><tr><td><?= e($account->getFullName()) ?></td><td><?= e($account->getEmail()) ?></td><td><?= e($account->getRole()) ?></td><td><span class="badge <?= $account->isActive() ? 'badge-status' : 'badge-muted' ?>"><?= e($account->getAccountStatus()) ?></span></td><td><?= e($account->getCreatedAt()) ?></td><td><a class="button button-secondary" href="<?= url('user/edit/' . $account->getKey()) ?>">Edit</a>
<?php if ($account->getKey() !== Auth::user()?->getKey()): ?><form method="post" action="<?= url('user/delete/' . $account->getKey()) ?>" data-confirm="Delete this account? Access will be removed; historical records will be preserved."><?= csrfField() ?><button class="button button-danger" type="submit">Delete account</button></form><?php endif; ?></td></tr><?php endforeach; ?>
<?php if ($users === []): ?><tr><td colspan="6" class="empty">No users match the filters.</td></tr><?php endif; ?>
</tbody></table></div>
