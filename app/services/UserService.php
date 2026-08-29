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
        $user->setAccess($role, $status);
        $user->save();
        return $user;
    }

    public function updateOwnProfile(User $user, array $data): User
    {
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
        return User::find($id);
    }

    private function validateIdentity(array $data, ?int $currentId = null): array
    {
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

    private function requireUser(int $id): User
    {
        $user = User::find($id);
        if ($user === null) {
            throw new OutOfBoundsException('User not found.');
        }
        return $user;
    }
}
