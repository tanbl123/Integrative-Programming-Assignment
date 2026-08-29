<?php
/**
 * Decorator-based permission profiles for EcoCampus roles.
 *
 * Each role decorator wraps the same basic signed-in profile and adds its own
 * capabilities without changing the User entity. This is the User & Access
 * module's reportable Decorator design pattern.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : User & Access Management - Decorator design pattern
 */
interface PermissionProfile
{
    public function permissions(): array;
}

class BasicPermissionProfile implements PermissionProfile
{
    public function permissions(): array
    {
        return ['profile.update', 'bin.view', 'location.view'];
    }
}

abstract class PermissionProfileDecorator implements PermissionProfile
{
    public function __construct(protected PermissionProfile $wrapped) {}

    protected function add(string ...$permissions): array
    {
        return array_values(array_unique(array_merge($this->wrapped->permissions(), $permissions)));
    }
}

class ReporterPermissionDecorator extends PermissionProfileDecorator
{
    public function permissions(): array
    {
        return $this->add('complaint.create', 'complaint.view_own');
    }
}

class CleanerPermissionDecorator extends PermissionProfileDecorator
{
    public function permissions(): array
    {
        return $this->add('bin.status', 'assignment.view_own', 'assignment.complete');
    }
}

class AdministratorPermissionDecorator extends PermissionProfileDecorator
{
    public function permissions(): array
    {
        return $this->add(
            'bin.manage', 'location.manage', 'complaint.manage',
            'schedule.manage', 'user.manage', 'report.view'
        );
    }
}

class UserPermissions
{
    public static function for(User $user): PermissionProfile
    {
        $basic = new BasicPermissionProfile();
        return match ($user->getRole()) {
            User::ROLE_REPORTER => new ReporterPermissionDecorator($basic),
            User::ROLE_CLEANER => new CleanerPermissionDecorator($basic),
            User::ROLE_ADMIN => new AdministratorPermissionDecorator($basic),
            default => $basic,
        };
    }

    public static function can(User $user, string $permission): bool
    {
        return in_array($permission, self::for($user)->permissions(), true);
    }

    public static function require(string $permission): User
    {
        $user = Auth::requireLogin();
        if (!self::can($user, $permission)) {
            throw new AuthorizationException('Your account is not allowed to perform this action.');
        }
        return $user;
    }
}
