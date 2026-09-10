<?php

/**
 * User entity. 
 * 
 * Authentication prerequisite getter 
 * Module : User & Access Management 
 * Note   : Skeleton provided so other modules can reference users. 
 *          The owning member extends this with authentication logic. 
 */
class User extends Model {

    protected static string $table = 'users';
    protected static string $primaryKey = 'user_id';
    protected static array $columns = [
        'full_name', 'email', 'password_hash', 'phone_no',
        'role', 'account_status', 'created_at', 'updated_at', 'deleted_at',
        'address_line1', 'address_line2', 'ic_no', 'gender', 'birth_date',
        'city', 'state', 'postcode', 'nationality'
    ];

    public const ROLE_REPORTER = 'Reporter';
    public const ROLE_CLEANER = 'Cleaner';
    public const ROLE_ADMIN = 'Administrator';

    public function getFullName(): string {
        return (string) $this->get('full_name');
    }

    public function getEmail(): string {
        return (string) $this->get('email');
    }

    public function getPasswordHash(): string {
        return (string) $this->get('password_hash');
    }

    public function getPhoneNo(): ?string {
        return $this->get('phone_no');
    }

    public function getRole(): string {
        return (string) $this->get('role');
    }

    public function getAccountStatus(): string {
        return (string) $this->get('account_status');
    }

    public function getCreatedAt(): string {
        return (string) $this->get('created_at');
    }

    public function isReporter(): bool {
        return $this->getRole() === self::ROLE_REPORTER;
    }

    public function isCleaner(): bool {
        return $this->getRole() === self::ROLE_CLEANER;
    }

    public function isAdmin(): bool {
        return $this->getRole() === self::ROLE_ADMIN;
    }

    public function isActive(): bool {
        return !$this->isDeleted() && $this->get('account_status') === 'Active';
    }

    public const DEMOGRAPHIC_FIELDS = [
        'address_line1',
        'address_line2',
        'ic_no',
        'gender',
        'birth_date',
        'city',
        'state',
        'postcode',
        'nationality'
    ];

    public function isDeleted(): bool {
        return $this->get('deleted_at') !== null;
    }

    public function demographics(): array {
        return array_combine(
                self::DEMOGRAPHIC_FIELDS,
                array_map(fn(string $field) => $this->get($field), self::DEMOGRAPHIC_FIELDS)
        );
    }

    public function setDemographics(array $values): void {
        foreach (self::DEMOGRAPHIC_FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $this->set($field, $values[$field]);
            }
        }
    }

    /** Preserve foreign-key history while removing account access. */
    public function delete(): bool {
        if ($this->getKey() === null || $this->isDeleted()) {
            return false;
        }

        $this->set('deleted_at', ifaTimestamp());
        $this->setAccess($this->getRole(), 'Inactive');
        $this->save();

        return true;
    }

    public function hasOpenAssignments(): bool {
        return Database::getInstance()->selectOne(
                        "SELECT assignment_id 
             FROM collection_assignments 
             WHERE cleaner_id = ? 
             AND assignment_status = 'Assigned' 
             LIMIT 1",
                        [$this->getKey()]
                ) !== null;
    }

    public function apiData(): array {
        return [
            'id' => $this->getKey(),
            'full_name' => $this->getFullName(),
            'email' => $this->getEmail(),
            'phone_no' => $this->getPhoneNo(),
            'role' => $this->getRole(),
            'account_status' => $this->getAccountStatus()
                ] + $this->demographics();
    }

    public static function roles(): array {
        return [
            self::ROLE_REPORTER,
            self::ROLE_CLEANER,
            self::ROLE_ADMIN
        ];
    }

    public static function visibleCount(): int {
        $row = Database::getInstance()->selectOne(
                'SELECT COUNT(*) AS total FROM users WHERE deleted_at IS NULL'
        );

        return (int) $row['total'];
    }

    public function setIdentity(string $name, string $email, ?string $phone): void {
        $this->set('full_name', $name);
        $this->set('email', $email);
        $this->set('phone_no', $phone);
        $this->set('updated_at', ifaTimestamp());
    }

    public function setAccess(string $role, string $status): void {
        $this->set('role', $role);
        $this->set('account_status', $status);
        $this->set('updated_at', ifaTimestamp());
    }

    public function setPassword(string $password): void {
        $this->set('password_hash', password_hash($password, PASSWORD_DEFAULT));
        $this->set('updated_at', ifaTimestamp());
    }

    /** Finds a user by email address. Used at login. */
    public static function findByEmail(string $email): ?User {
        $matches = self::where('email', $email);
        return $matches[0] ?? null;
    }

    public static function search(string $query = '', string $role = '', string $status = ''): array {
        $sql = 'SELECT * FROM users WHERE deleted_at IS NULL';
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

    public static function findActiveCleaners(): array {
        return self::hydrateAll(Database::getInstance()->selectAll(
                                'SELECT * 
             FROM users 
             WHERE role = ? 
             AND account_status = ? 
             AND deleted_at IS NULL 
             ORDER BY full_name',
                                [self::ROLE_CLEANER, 'Active']
                        ));
    }
}
