<?php
/**
 * User entity.
 *
 * Author : Phang Jun Hong (2406646)
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
        'role', 'account_status', 'created_at',
    ];

    public const ROLE_REPORTER = 'Reporter';
    public const ROLE_CLEANER  = 'Cleaner';
    public const ROLE_ADMIN    = 'Administrator';

    public function getFullName(): string { return (string) $this->get('full_name'); }
    public function getEmail(): string    { return (string) $this->get('email'); }
    public function getPhoneNo(): ?string { return $this->get('phone_no'); }
    public function getRole(): string     { return (string) $this->get('role'); }

    public function isReporter(): bool { return $this->getRole() === self::ROLE_REPORTER; }
    public function isCleaner(): bool  { return $this->getRole() === self::ROLE_CLEANER; }
    public function isAdmin(): bool    { return $this->getRole() === self::ROLE_ADMIN; }

    public function isActive(): bool { return $this->get('account_status') === 'Active'; }

    /** Finds a user by email address. Used at login. */
    public static function findByEmail(string $email): ?User
    {
        $matches = self::where('email', $email);
        return $matches[0] ?? null;
    }
}
