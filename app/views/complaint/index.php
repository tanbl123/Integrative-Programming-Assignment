<?php /** Complaint listing. Author: Tan Boon Leong (2402865). */ ?>
<div class="page-heading"><div><h1><?= $user->isAdmin() ? 'All complaints' : 'My complaints' ?></h1><p class="lead">Track reported waste issues from submission to resolution.</p></div><?php if (UserPermissions::can($user, 'complaint.create')): ?><a class="button" href="<?= url('complaint/create') ?>">Report issue</a><?php endif; ?></div>
<form method="get" action="<?= url('complaint') ?>" class="filter-panel">
<label>Search<input type="search" name="q" value="<?= e($filters['query']) ?>" placeholder="Issue, description, or bin"></label>
<label>Status<select name="status"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
<label>Location<select name="location_id"><option value="">All locations</option><?php foreach ($locations as $location): ?><option value="<?= $location->getKey() ?>" <?= $filters['location'] === $location->getKey() ? 'selected' : '' ?>><?= e($location->getFullLabel()) ?></option><?php endforeach; ?></select></label>
<button class="button button-secondary" type="submit">Apply</button>
</form>
<div class="table-scroll"><table class="table"><thead><tr><th>Reference</th><th>Bin</th><th>Issue</th><th>Status</th><th>Submitted</th><th></th></tr></thead><tbody>
<?php foreach ($complaints as $complaint): ?><tr><td>#<?= $complaint->getKey() ?></td><td><?= e($complaint->getBin()?->getBinCode() ?? 'Unknown') ?><br><small><?= e($complaint->getBin()?->getLocation()?->getFullLabel() ?? '') ?></small></td><td><?= e($complaint->getType()) ?></td><td><span class="badge badge-status"><?= e($complaint->getStatus()) ?></span></td><td><?= e($complaint->getCreatedAt()) ?></td><td><a href="<?= url('complaint/show/' . $complaint->getKey()) ?>">View</a></td></tr><?php endforeach; ?>
<?php if ($complaints === []): ?><tr><td colspan="6" class="empty">No complaints match the filters.</td></tr><?php endif; ?>
</tbody></table></div>
