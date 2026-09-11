<?php
/** Shared demographic fields. Author: Phang Jun Hong (2406646). Module: User & Access Management. */
$fieldValue = static function (string $field, array $values, array $errors): string {
    if (!empty($errors[$field])) {
        return '';
    }

    return (string) ($values[$field] ?? '');
};
?>

<h2>Personal information</h2>

<div class="form-grid">
    <?php
    foreach ([
'address_line1' => ['Address line 1', 200, true, '', 'Address line 1 is required.'],
 'address_line2' => ['Address line 2', 200, false, '', ''],
 'city' => ['City', 100, true, '2', 'City must be between 2 and 100 characters.'],
 'state' => ['State', 100, true, '2', 'State must be between 2 and 100 characters.'],
 'postcode' => ['Postcode', 12, true, '', 'Postcode must be 5 digits.'],
 'nationality' => ['Nationality', 80, true, '2', 'Nationality must be between 2 and 80 characters.'],
 'ic_no' => ['IC number', 14, true, '', 'IC number must be 12 digits, with optional hyphens.'],
    ] as $field => [$label, $limit, $required, $min, $title]):
        ?>

        <label>
            <?= e($label) ?>

            <input
                name="<?= e($field) ?>"
                <?= $field === 'ic_no' ? 'id="ic_no"' : '' ?>
                <?= $required ? 'required' : '' ?>
                maxlength="<?= e((string) $limit) ?>"
                <?= $min !== '' ? 'minlength="' . e($min) . '"' : '' ?>
                <?= $field === 'postcode' ? 'pattern="[0-9]{5}"' : '' ?>
                <?= $field === 'ic_no' ? 'pattern="[0-9]{6}-?[0-9]{2}-?[0-9]{4}"' : '' ?>
                <?= $title !== '' ? 'title="' . e($title) . '"' : '' ?>
                value="<?= e($fieldValue($field, $values, $errors)) ?>"
                >

            <?php if (!empty($errors[$field])): ?>
                <span class="field-error"><?= e($errors[$field]) ?></span>
            <?php endif; ?>
        </label>

    <?php endforeach; ?>

    <label>
        Gender

        <select name="gender" required>
            <option value="">Select gender</option>

            <?php foreach (['Male', 'Female', 'Prefer not to say'] as $gender): ?>
                <option 
                    value="<?= e($gender) ?>"
                    <?= $fieldValue('gender', $values, $errors) === $gender ? 'selected' : '' ?>
                    >
                        <?= e($gender) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if (!empty($errors['gender'])): ?>
            <span class="field-error"><?= e($errors['gender']) ?></span>
        <?php endif; ?>
    </label>

    <label>
        Birth date

        <input
            type="date"
            name="birth_date"
            id="birth_date"
            required
            min="1900-01-01"
            max="<?= date('Y-m-d') ?>"
            value="<?= e($fieldValue('birth_date', $values, $errors)) ?>"
            >

        <?php if (!empty($errors['birth_date'])): ?>
            <span class="field-error"><?= e($errors['birth_date']) ?></span>
        <?php endif; ?>
    </label>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const icInput = document.getElementById('ic_no');
        const birthInput = document.getElementById('birth_date');

        if (!icInput || !birthInput) {
            return;
        }

        function updateBirthDateFromIc() {
            const ic = icInput.value.replace(/-/g, '').trim();

            if (ic.length < 6) {
                return;
            }

            const yy = ic.substring(0, 2);
            const mm = ic.substring(2, 4);
            const dd = ic.substring(4, 6);

            if (!/^\d{2}$/.test(yy) || !/^\d{2}$/.test(mm) || !/^\d{2}$/.test(dd)) {
                return;
            }

            const currentYear = new Date().getFullYear();
            const currentYY = currentYear % 100;

            const year = parseInt(yy, 10) <= currentYY
                    ? 2000 + parseInt(yy, 10)
                    : 1900 + parseInt(yy, 10);

            const month = parseInt(mm, 10);
            const day = parseInt(dd, 10);

            const checkDate = new Date(year, month - 1, day);

            const isValidDate =
                    checkDate.getFullYear() === year &&
                    checkDate.getMonth() === month - 1 &&
                    checkDate.getDate() === day;

            if (isValidDate) {
                const birthDate =
                        year + '-' +
                        String(month).padStart(2, '0') + '-' +
                        String(day).padStart(2, '0');

                birthInput.value = birthDate;
            }
        }

        icInput.addEventListener('input', updateBirthDateFromIc);
        updateBirthDateFromIc();
    });
</script>