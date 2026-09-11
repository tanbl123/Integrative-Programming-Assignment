<?php
/**
 * A user who carries out collection assignments.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 *
 * One of three subclasses of User, chosen by User::hydrate() from the role
 * stored on the row.
 *
 * A Cleaner works from assignments rather than from complaints, and has no
 * complaint of their own to see - so this class answers "none", in the same
 * shape the other two answer "mine" and "all". The refusal is a case of the
 * rule rather than an exception to it, which is the point of expressing it
 * this way.
 */
class Cleaner extends User
{
    public function describe(): string
    {
        return 'You collect from the bins you are assigned and record what you did.';
    }

    public function visibleComplaints(string $query, string $status, ?int $locationId): array
    {
        throw new AuthorizationException('Your role does not have complaint access.');
    }

    public function maySee(Complaint $complaint): bool
    {
        return false;
    }
}
