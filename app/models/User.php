<?php
/**
 * User entity.
 *
 * Author : Phang Jun Hong (2406646)
 * Updated: Ong Kar Heng (2408830) - authentication prerequisite getter
 * Module : User & Access Management
 * Note   : Skeleton provided so other modules can reference users.
 *          The owning member extends this with authentication logic.
 */
class User extends Model
{
    protected static string $table      = 'users';
    protected static string $primaryKey = 'user_id';
    protected static array  $columns    = [
        'full_name', 'email', 'password_hash', 'phone_no',
        'role', 'account_status', 'created_at', 'updated_at',
    ];

    public const ROLE_REPORTER = 'Reporter';
    public const ROLE_CLEANER  = 'Cleaner';
    public const ROLE_ADMIN    = 'Administrator';

    public function getFullName(): string { return (string) $this->get('full_name'); }
    public function getEmail(): string    { return (string) $this->get('email'); }
    public function getPasswordHash(): string { return (string) $this->get('password_hash'); }
    public function getPhoneNo(): ?string { return $this->get('phone_no'); }
    public function getRole(): string     { return (string) $this->get('role'); }
    public function getAccountStatus(): string { return (string) $this->get('account_status'); }
    public function getCreatedAt(): string { return (string) $this->get('created_at'); }

    public function isReporter(): bool { return $this->getRole() === self::ROLE_REPORTER; }
    public function isCleaner(): bool  { return $this->getRole() === self::ROLE_CLEANER; }
    public function isAdmin(): bool    { return $this->getRole() === self::ROLE_ADMIN; }

    public function isActive(): bool { return $this->get('account_status') === 'Active'; }

    public static function roles(): array
    {
        return [self::ROLE_REPORTER, self::ROLE_CLEANER, self::ROLE_ADMIN];
    }

    public function setIdentity(string $name, string $email, ?string $phone): void
    {
        $this->set('full_name', $name);
        $this->set('email', $email);
        $this->set('phone_no', $phone);
        $this->set('updated_at', ifaTimestamp());
    }

    public function setAccess(string $role, string $status): void
    {
        $this->set('role', $role);
        $this->set('account_status', $status);
        $this->set('updated_at', ifaTimestamp());
    }

    public function setPassword(string $password): void
    {
        $this->set('password_hash', password_hash($password, PASSWORD_DEFAULT));
        $this->set('updated_at', ifaTimestamp());
    }

    /** Finds a user by email address. Used at login. */
    public static function findByEmail(string $email): ?User
    {
        $matches = self::where('email', $email);
        return $matches[0] ?? null;
    }

    public static function search(string $query = '', string $role = '', string $status = ''): array
    {
        $sql = 'SELECT * FROM users WHERE 1 = 1';
        $params = [];
        if ($query !== '') {
            $like = '%' . $query . '%';
            $sql .= ' AND (full_name LIKE ? OR email LIKE ? OR phone_no LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if (in_array($role, self::roles(), true)) {
            $sql .= ' AND role = ?';
            $params[] = $role;
        }
        if (in_array($status, ['Active', 'Inactive'], true)) {
            $sql .= ' AND account_status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY full_name ASC';
        return self::hydrateAll(Database::getInstance()->selectAll($sql, $params));
    }

    public static function findActiveCleaners(): array
    {
        return self::hydrateAll(Database::getInstance()->selectAll(
            'SELECT * FROM users WHERE role = ? AND account_status = ? ORDER BY full_name',
            [self::ROLE_CLEANER, 'Active']
        ));
    }
}
