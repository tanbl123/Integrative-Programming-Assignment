<?php

/**
 * User account registration, administration, and profile rules.
 * Author : Phang Jun Hong (2406646)
 * Module : User & Access Management
 */
class UserService {

    public function registerReporter(array $data): User {
        [$name, $email, $phone] = $this->validateIdentity($data);
        $password = $this->validateNewPassword($data);

        $user = User::forRole(User::ROLE_REPORTER);
        $user->setIdentity($name, $email, $phone);
        $user->setDemographics($this->validateDemographics($data));
        $user->setAccess(User::ROLE_REPORTER, 'Active');
        $user->setPassword($password);
        $user->save();

        return $user;
    }

    public function createByAdministrator(array $data): array {
        UserPermissions::require('user.manage');

        [$name, $email, $phone] = $this->validateIdentity($data);
        [$role, $status] = $this->validateAccess($data);

        $temporaryPassword = $this->generateTemporaryPassword();

        $user = User::forRole($role);
        $user->setIdentity($name, $email, $phone);
        $user->setDemographics($this->validateDemographics($data));
        $user->setAccess($role, $status);
        $user->setPassword($temporaryPassword);
        $user->save();

        return [$user, $temporaryPassword];
    }

    public function updateByAdministrator(int $id, array $data): User {
        $admin = UserPermissions::require('user.manage');
        $user = $this->requireUser($id);

        [$name, $email, $phone] = $this->validateIdentity($data, $id);
        [$role, $status] = $this->validateAccess($data);

        if ($admin->getKey() === $id && ($role !== User::ROLE_ADMIN || $status !== 'Active')) {
            throw new ValidationException([
                        'role' => 'You cannot remove or disable your own Administrator access.'
            ]);
        }

        $user->setIdentity($name, $email, $phone);
        $user->setDemographics($this->validateDemographics($data));
        $user->setAccess($role, $status);
        $user->save();

        return $user;
    }

    public function updateOwnProfile(User $user, array $data): User {
        $signedIn = Auth::requireLogin();

        if ($signedIn->getKey() !== $user->getKey() || !$user->isActive()) {
            throw new AuthorizationException('You can only edit your own active profile.');
        }

        [$name, $email, $phone] = $this->validateIdentity($data, $user->getKey());

        $user->setIdentity($name, $email, $phone);
        $user->setDemographics($this->validateDemographics($data));
        $user->save();

        return $user;
    }

    public function updateOwnPassword(User $user, array $data): User {
        $signedIn = Auth::requireLogin();

        if ($signedIn->getKey() !== $user->getKey() || !$user->isActive()) {
            throw new AuthorizationException('You can only change your own active account password.');
        }

        $this->validateTextFields($data, [
            'current_password',
            'new_password',
            'new_password_confirmation'
        ]);

        $currentPassword = (string) ($data['current_password'] ?? '');

        if ($currentPassword === '') {
            throw new ValidationException([
                        'current_password' => 'Current password is required.'
            ]);
        }

        if (!password_verify($currentPassword, $user->getPasswordHash())) {
            throw new ValidationException([
                        'current_password' => 'Current password is incorrect.'
            ]);
        }

        $newPassword = $this->validateNewPassword([
            'password' => (string) ($data['new_password'] ?? ''),
            'password_confirmation' => (string) ($data['new_password_confirmation'] ?? ''),
        ]);

        $user->setPassword($newPassword);
        $user->save();

        return $user;
    }

    public function search(string $query, string $role, string $status): array {
        UserPermissions::require('user.manage');
        return User::search(trim($query), $role, $status);
    }

    public function find(int $id): ?User {
        UserPermissions::require('user.manage');

        $user = User::find($id);

        return $user !== null && !$user->isDeleted() ? $user : null;
    }

    public function deleteByAdministrator(int $id): void {
        $admin = UserPermissions::require('user.manage');
        $user = $this->requireUser($id);

        if ($admin->getKey() === $id) {
            throw new ValidationException([
                        'account' => 'You cannot delete your own administrator account.'
            ]);
        }

        $scheduleClient = new ScheduleServiceClient();

        if ($user->isCleaner() && $scheduleClient->hasOpenAssignments((int) $user->getKey())) {
            throw new ValidationException([
                        'account' => 'Complete, skip, or reassign the open assignments for this cleaner before deleting the account.'
            ]);
        }

        $user->delete();
    }

    public function resetPasswordByAdministrator(int $id): array {
        $admin = UserPermissions::require('user.manage');
        $user = $this->requireUser($id);

        if ($admin->getKey() === $user->getKey()) {
            throw new ValidationException([
                        'account' => 'You cannot reset your own password from user management.'
            ]);
        }

        $temporaryPassword = $this->generateTemporaryPassword();

        $user->setPassword($temporaryPassword);
        $user->save();

        return [$user, $temporaryPassword];
    }

    private function validateDemographics(array $data): array {
        $values = [];
        $errors = [];

        $limits = [
            'address_line1' => 200,
            'address_line2' => 200,
            'ic_no' => 14,
            'gender' => 30,
            'birth_date' => 10,
            'city' => 100,
            'state' => 100,
            'postcode' => 12,
            'nationality' => 80
        ];

        foreach ($limits as $field => $limit) {
            if (isset($data[$field]) && !is_scalar($data[$field]) && $data[$field] !== null) {
                $errors[$field] = 'Enter a text value.';
                continue;
            }

            $value = trim((string) ($data[$field] ?? ''));

            if (mb_strlen($value) > $limit) {
                $errors[$field] = "Use at most {$limit} characters.";
            }

            $values[$field] = $value;
        }

        $requiredFields = [
            'address_line1' => 'Address line 1 is required.',
            'city' => 'City is required.',
            'state' => 'State is required.',
            'postcode' => 'Postcode is required.',
            'nationality' => 'Nationality is required.',
            'ic_no' => 'IC number is required.',
            'gender' => 'Gender is required.',
            'birth_date' => 'Birth date is required.'
        ];

        foreach ($requiredFields as $field => $message) {
            if ($values[$field] === '') {
                $errors[$field] = $message;
            }
        }

        $validStates = [
            'Johor',
            'Kedah',
            'Kelantan',
            'Melaka',
            'Negeri Sembilan',
            'Pahang',
            'Penang',
            'Perak',
            'Perlis',
            'Sabah',
            'Sarawak',
            'Selangor',
            'Terengganu',
            'Kuala Lumpur',
            'Putrajaya',
            'Labuan'
        ];

        if ($values['state'] !== '' && !in_array($values['state'], $validStates, true)) {
            $errors['state'] = 'Enter a valid state.';
        }

        if ($values['postcode'] !== '' && !preg_match('/^[0-9]{5}$/', $values['postcode'])) {
            $errors['postcode'] = 'Postcode must be 5 digits.';
        }

        if ($values['ic_no'] !== '') {
            $ic = str_replace('-', '', $values['ic_no']);

            if (!preg_match('/^[0-9]{12}$/', $ic)) {
                $errors['ic_no'] = 'Enter a 12-digit IC, with optional hyphens.';
            } else {
                $values['ic_no'] = $ic;

                $yy = substr($ic, 0, 2);
                $mm = substr($ic, 2, 2);
                $dd = substr($ic, 4, 2);

                $currentYY = (int) date('y');
                $year = ((int) $yy <= $currentYY) ? '20' . $yy : '19' . $yy;

                $icBirthDate = $year . '-' . $mm . '-' . $dd;

                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $icBirthDate);

                if (
                        !$date ||
                        $date->format('Y-m-d') !== $icBirthDate ||
                        $date > new DateTimeImmutable('today') ||
                        $date < new DateTimeImmutable('1900-01-01')
                ) {
                    $errors['ic_no'] = 'IC number contains an invalid birth date.';
                } else {
                    $values['birth_date'] = $icBirthDate;
                }
            }
        }

        if ($values['birth_date'] !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $values['birth_date']);

            if (
                    !$date ||
                    $date->format('Y-m-d') !== $values['birth_date'] ||
                    $date > new DateTimeImmutable('today') ||
                    $date < new DateTimeImmutable('1900-01-01')
            ) {
                $errors['birth_date'] = 'Enter a valid birth date between 1900 and today.';
            }
        }

        if ($values['gender'] !== '' && !in_array($values['gender'], ['Male', 'Female', 'Prefer not to say'], true)) {
            $errors['gender'] = 'Select a valid gender option.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        foreach ($values as $field => $value) {
            if ($value === '') {
                $values[$field] = null;
            }
        }

        return $values;
    }

    private function validateIdentity(array $data, ?int $currentId = null): array {
        $this->validateTextFields($data, ['full_name', 'email', 'phone_no']);

        $name = trim((string) ($data['full_name'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $phone = trim((string) ($data['phone_no'] ?? ''));

        $errors = [];

        if ($name === '') {
            $errors['full_name'] = 'Full name is required.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['full_name'] = 'Full name must be between 2 and 100 characters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            $errors['email'] = 'Enter a valid email address of at most 100 characters.';
        } else {
            $existing = User::findByEmail($email);

            if ($existing !== null && $existing->getKey() !== $currentId) {
                $errors['email'] = 'That email address is already registered.';
            }
        }

        if ($phone === '') {
            $errors['phone_no'] = 'Phone number is required.';
        } elseif (!preg_match('/^[0-9+() -]{7,20}$/', $phone)) {
            $errors['phone_no'] = 'Enter a valid phone number using 7-20 digits and separators.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [$name, $email, $phone === '' ? null : $phone];
    }

    private function validateAccess(array $data): array {
        $this->validateTextFields($data, ['role', 'account_status']);

        $role = (string) ($data['role'] ?? '');
        $status = (string) ($data['account_status'] ?? '');

        $errors = [];

        if (!in_array($role, User::roles(), true)) {
            $errors['role'] = 'Select a valid role.';
        }

        if (!in_array($status, ['Active', 'Inactive'], true)) {
            $errors['account_status'] = 'Select a valid account status.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [$role, $status];
    }

    private function generateTemporaryPassword(): string {
        return 'Temp@' . random_int(100000, 999999);
    }

    private function validateNewPassword(array $data): string {
        $this->validateTextFields($data, ['password', 'password_confirmation']);

        $password = (string) ($data['password'] ?? '');
        $confirmation = (string) ($data['password_confirmation'] ?? '');

        $errors = [];

        if ($password === '') {
            $errors['password'] = 'New password is required.';
        } elseif (
                strlen($password) < 8 ||
                strlen($password) > 72 ||
                !preg_match('/[A-Z]/', $password) ||
                !preg_match('/[a-z]/', $password) ||
                !preg_match('/[0-9]/', $password) ||
                !preg_match('/[^A-Za-z0-9]/', $password)
        ) {
            $errors['password'] = 'Password must be 8-72 characters and include uppercase, lowercase, number, and special character.';
        }

        if ($confirmation === '') {
            $errors['password_confirmation'] = 'Confirm new password is required.';
        } elseif ($password !== $confirmation) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $password;
    }

    private function validateTextFields(array $data, array $fields): void {
        $errors = [];

        foreach ($fields as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) {
                $errors[$field] = 'Enter a text value.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    private function requireUser(int $id): User {
        $user = User::find($id);

        if ($user === null || $user->isDeleted()) {
            throw new OutOfBoundsException('User not found.');
        }

        return $user;
    }
}
