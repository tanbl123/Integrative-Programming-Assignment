<?php
/**
 * Decorator-based permission profiles for EcoCampus roles.
 *
 * Each role decorator wraps the same basic signed-in profile and adds its own
 * capabilities without changing the User entity. This is the User & Access
 * module's reportable Decorator design pattern.
 *
 * Author : Phang Jun Hong (2406646)
 * Module : User & Access Management
 */
interface PermissionProfile
{
    /** The Component operation: the full list of what this profile may do. */
    public function permissions(): array;
}

class BasicPermissionProfile implements PermissionProfile
{
    /**
     * The Concrete Component: what ANY signed-in user may do, whatever their
     * role. Every decorator below wraps this, so nobody repeats these three.
     */
    public function permissions(): array
    {
        return ['profile.update', 'bin.view', 'location.view'];
    }
}

abstract class PermissionProfileDecorator implements PermissionProfile
{
    /** Holds the profile being decorated, through the interface - never a concrete class. */
    public function __construct(protected PermissionProfile $wrapped) {}

    /**
     * Merges this decorator's own permissions onto whatever it wraps.
     *
     * Calling $this->wrapped->permissions() first is the whole pattern: each
     * layer extends the one beneath it instead of restating it, so a
     * permission added to the basic profile reaches all three roles at once.
     */
    protected function add(string ...$permissions): array
    {
        return array_values(array_unique(array_merge($this->wrapped->permissions(), $permissions)));
    }
}

class ReporterPermissionDecorator extends PermissionProfileDecorator
{
    /** Basic, plus submitting complaints and reading their own. */
    public function permissions(): array
    {
        return $this->add('complaint.create', 'complaint.view_own');
    }
}

class CleanerPermissionDecorator extends PermissionProfileDecorator
{
    /** Basic, plus recording bin status and completing their own assignments. */
    public function permissions(): array
    {
        return $this->add('bin.status', 'assignment.view_own', 'assignment.complete');
    }
}

class AdministratorPermissionDecorator extends PermissionProfileDecorator
{
    /** Basic, plus managing bins, locations, complaints, schedules and users. */
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
    /**
     * Builds the right decorator around a basic profile for this user's role.
     * Composed here at runtime rather than fixed by inheritance, which is why
     * a new role means a new decorator and nothing else changes.
     */
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

    /** Does this user hold this permission. Used to decide whether to show a control. */
    public static function can(User $user, string $permission): bool
    {
        return in_array($permission, self::for($user)->permissions(), true);
    }

    /**
     * The same check, but refuses instead of returning false.
     *
     * This is the method every other module calls. Every guarded action in the
     * system - in Bins, Complaints and Scheduling as much as here - ends up
     * here, which is what makes the Decorator the backbone of access control
     * rather than a demonstration of a pattern.
     */
    public static function require(string $permission): User
    {
        $user = Auth::requireLogin();
        if (!self::can($user, $permission)) {
            throw new AuthorizationException('Your account is not allowed to perform this action.');
        }
        return $user;
    }
}
