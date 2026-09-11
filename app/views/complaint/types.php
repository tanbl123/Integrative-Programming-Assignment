<?php /** Issue type maintenance. Author: Tan Boon Leong (2402865). Module: Complaint / Report Management. */ ?>

<?php
$editing = $editId !== null;
$value = static function (string $field, $fallback = '') use ($values) {
    return $values[$field] ?? $fallback;
};
?>

<div class="page-heading">
    <div>
        <h1>Issue types</h1>
        <p class="lead">
            What a reporter may choose when describing a waste issue.
            Withdrawing a type hides it from the form; complaints already filed under it are unaffected.
        </p>
    </div>

    <a class="button button-secondary" href="<?= url('complaint') ?>">Back to complaints</a>
</div>

<form
    method="post"
    action="<?= url($editing ? 'complaint-type/update/' . $editId : 'complaint-type/store') ?>"
    class="form-card"
    id="issue-type-form"
>
    <?= csrfField() ?>

    <h2><?= $editing ? 'Edit issue type' : 'Add an issue type' ?></h2>

    <?php if (!empty($errors['type'])): ?>
        <div class="alert alert-error"><?= e($errors['type']) ?></div>
    <?php endif; ?>

    <div class="form-grid">
        <label>
            Name
            <input
                name="type_name"
                required
                maxlength="50"
                title="Give the issue type a name of 1-50 characters."
                value="<?= e((string) $value('type_name')) ?>"
            >
            <?php if (!empty($errors['type_name'])): ?>
                <span class="field-error"><?= e($errors['type_name']) ?></span>
            <?php endif; ?>
        </label>

        <label>
            Order in the list
            <input
                type="number"
                name="sort_order"
                min="0"
                max="999"
                title="Lower numbers appear first."
                value="<?= e((string) $value('sort_order', '0')) ?>"
            >
            <?php if (!empty($errors['sort_order'])): ?>
                <span class="field-error"><?= e($errors['sort_order']) ?></span>
            <?php endif; ?>
        </label>
    </div>

    <label>
        Description
        <input
            name="description"
            maxlength="255"
            title="Description cannot exceed 255 characters."
            placeholder="What this type covers. Shown to administrators only."
            value="<?= e((string) $value('description')) ?>"
        >
        <?php if (!empty($errors['description'])): ?>
            <span class="field-error"><?= e($errors['description']) ?></span>
        <?php endif; ?>
    </label>

    <input type="hidden" name="is_active" value="0">
    <label class="checkbox-label">
        <input type="checkbox" name="is_active" value="1"
            <?= (string) $value('is_active', '1') === '1' ? 'checked' : '' ?>>
        Available to reporters
    </label>

    <div class="button-row">
        <button class="button" type="submit"><?= $editing ? 'Save issue type' : 'Add issue type' ?></button>
        <?php if ($editing): ?>
            <a class="button button-secondary" href="<?= url('complaint-type') ?>">Cancel edit</a>
        <?php endif; ?>
    </div>
</form>

<div class="table-scroll">
    <table class="table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Issue type</th>
                <th>Description</th>
                <th>Available</th>
                <th>Complaints filed</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($types as $type): ?>
                <?php $used = $type->complaintCount(); ?>
                <tr>
                    <td><?= (int) $type->getSortOrder() ?></td>
                    <td><strong><?= e($type->getName()) ?></strong></td>
                    <td><?= e($type->getDescription() ?? '—') ?></td>
                    <td>
                        <span class="badge badge-status">
                            <?= $type->isActive() ? 'Available' : 'Withdrawn' ?>
                        </span>
                    </td>
                    <td><?= $used ?></td>
                    <td>
                        <div class="button-row">
                            <a class="button button-secondary"
                               href="<?= url('complaint-type?edit=' . $type->getKey()) ?>">Edit</a>

                            <?php /* Withdrawing is the safe counterpart to deleting: the row
                                     stays, so complaints filed under it remain valid. */ ?>
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

            <?php if ($types === []): ?>
                <tr><td colspan="6" class="empty">No issue types have been defined.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
