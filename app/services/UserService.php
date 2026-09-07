<?php
/**
 * User account registration, administration, and profile rules.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : User & Access Management
 */
class UserService
{
    public function registerReporter(array $data): User
    {
        [$name, $email, $phone] = $this->validateIdentity($data);
        $password = $this->validateNewPassword($data);

        $user = new User();
        $user->setIdentity($name, $email, $phone);
        $user->setDemographics($this->validateDemographics($data));
        $user->setAccess(User::ROLE_REPORTER, 'Active');
        $user->setPassword($password);
        $user->save();
        return $user;
    }

    public function createByAdministrator(array $data): User
    {
        UserPermissions::require('user.manage');
        [$name, $email, $phone] = $this->validateIdentity($data);
        [$role, $status] = $this->validateAccess($data);
        $password = $this->validateNewPassword($data);

        $user = new User();
        $user->setIdentity($name, $email, $phone);
        $user->setDemographics($this->validateDemographics($data));
        $user->setAccess($role, $status);
        $user->setPassword($password);
        $user->save();
        return $user;
    }

    public function updateByAdministrator(int $id, array $data): User
    {
        $admin = UserPermissions::require('user.manage');
        $user = $this->requireUser($id);
        [$name, $email, $phone] = $this->validateIdentity($data, $id);
        [$role, $status] = $this->validateAccess($data);

        if ($admin->getKey() === $id && ($role !== User::ROLE_ADMIN || $status !== 'Active')) {
            throw new ValidationException(['role' => 'You cannot remove or disable your own Administrator access.']);
        }

        $user->setIdentity($name, $email, $phone);
        $user->setDemographics($this->validateDemographics($data));
        $user->setAccess($role, $status);
        $user->save();
        return $user;
    }

    public function updateOwnProfile(User $user, array $data): User
    {
        $signedIn = Auth::requireLogin();
        if ($signedIn->getKey() !== $user->getKey() || !$user->isActive()) {
            throw new AuthorizationException('You can only edit your own active profile.');
        }
        $this->validateTextFields($data, ['new_password', 'current_password', 'new_password_confirmation']);
        [$name, $email, $phone] = $this->validateIdentity($data, $user->getKey());
        $newPassword = (string) ($data['new_password'] ?? '');
        if ($newPassword !== '') {
            $current = (string) ($data['current_password'] ?? '');
            if (!password_verify($current, $user->getPasswordHash())) {
                throw new ValidationException(['current_password' => 'Current password is incorrect.']);
            }
            $newPassword = $this->validateNewPassword([
                'password' => $newPassword,
                'password_confirmation' => $data['new_password_confirmation'] ?? '',
            ]);
            $user->setPassword($newPassword);
        }

        $user->setIdentity($name, $email, $phone);
        $user->setDemographics($this->validateDemographics($data));
        $user->save();
        return $user;
    }

    public function search(string $query, string $role, string $status): array
    {
        UserPermissions::require('user.manage');
        return User::search(trim($query), $role, $status);
    }

    public function find(int $id): ?User
    {
        UserPermissions::require('user.manage');
        $user = User::find($id);
        return $user !== null && !$user->isDeleted() ? $user : null;
    }

    public function deleteByAdministrator(int $id): void
    {
        $admin = UserPermissions::require('user.manage');
        $user = $this->requireUser($id);
        if ($admin->getKey() === $id) {
            throw new ValidationException(['account' => 'You cannot delete your own administrator account.']);
        }
        if ($user->hasOpenAssignments()) {
            throw new ValidationException(['account' => 'Complete, skip, or reassign the open assignments for this cleaner before deleting the account.']);
        }
        $user->delete();
    }

    private function validateDemographics(array $data): array
    {
        $values = [];
        $errors = [];
        $limits = ['address_line1' => 200, 'address_line2' => 200, 'ic_no' => 14, 'gender' => 30,
            'birth_date' => 10, 'city' => 100, 'state' => 100, 'postcode' => 12, 'nationality' => 80];
        foreach ($limits as $field => $limit) {
            if (!array_key_exists($field, $data)) { continue; }
            if (!is_scalar($data[$field]) && $data[$field] !== null) {
                $errors[$field] = 'Enter a text value.';
                continue;
            }
            $value = trim((string) $data[$field]);
            if (mb_strlen($value) > $limit) { $errors[$field] = "Use at most {$limit} characters."; }
            $values[$field] = $value === '' ? null : $value;
        }
        if (!empty($values['ic_no'])) {
            $ic = str_replace('-', '', $values['ic_no']);
            if (!preg_match('/^[0-9]{12}$/', $ic)) { $errors['ic_no'] = 'Enter a 12-digit IC, with optional hyphens.'; }
            else { $values['ic_no'] = $ic; }
        }
        if (!empty($values['gender']) && !in_array($values['gender'], ['Male', 'Female', 'Other', 'Prefer not to say'], true)) {
            $errors['gender'] = 'Select a valid gender option.';
        }
        if (!empty($values['birth_date'])) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $values['birth_date']);
            if (!$date || $date->format('Y-m-d') !== $values['birth_date'] || $date > new DateTimeImmutable('today') || $date < new DateTimeImmutable('1900-01-01')) {
                $errors['birth_date'] = 'Enter a valid birth date between 1900 and today.';
            }
        }
        if ($errors !== []) { throw new ValidationException($errors); }
        return $values;
    }

    private function validateIdentity(array $data, ?int $currentId = null): array
    {
        $this->validateTextFields($data, ['full_name', 'email', 'phone_no']);
        $name = trim((string) ($data['full_name'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $phone = trim((string) ($data['phone_no'] ?? ''));
        $errors = [];

        if ($name === '' || mb_strlen($name) > 100) {
            $errors['full_name'] = 'Full name is required and cannot exceed 100 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            $errors['email'] = 'Enter a valid email address of at most 100 characters.';
        } else {
            $existing = User::findByEmail($email);
            if ($existing !== null && $existing->getKey() !== $currentId) {
                $errors['email'] = 'That email address is already registered.';
            }
        }
        if ($phone !== '' && (!preg_match('/^[0-9+() -]{7,20}$/', $phone))) {
            $errors['phone_no'] = 'Enter a valid phone number using 7-20 digits and separators.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return [$name, $email, $phone === '' ? null : $phone];
    }

    private function validateAccess(array $data): array
    {
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

    private function validateNewPassword(array $data): string
    {
        $this->validateTextFields($data, ['password', 'password_confirmation']);
        $password = (string) ($data['password'] ?? '');
        $confirmation = (string) ($data['password_confirmation'] ?? '');
        $errors = [];
        if (strlen($password) < 8 || strlen($password) > 72
            || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Password must be 8-72 characters and contain letters and numbers.';
        }
        if ($password !== $confirmation) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return $password;
    }

    private function validateTextFields(array $data, array $fields): void
    {
        $errors = [];
        foreach ($fields as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) { $errors[$field] = 'Enter a text value.'; }
        }
        if ($errors !== []) { throw new ValidationException($errors); }
    }

    private function requireUser(int $id): User
    {
        $user = User::find($id);
        if ($user === null || $user->isDeleted()) {
            throw new OutOfBoundsException('User not found.');
        }
        return $user;
    }
}
