<?php
/**
 * An issue type a reporter may choose, maintained by an Administrator.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 *
 * The table carries a surrogate key like every other table in the system,
 * because Model::getKey() returns an int and a text key would be cast to
 * zero. Complaints reference the UNIQUE type_name rather than that id, so
 * they store the readable value and the database still refuses one that
 * does not exist - what the ENUM used to do, without needing a schema
 * change every time the campus wants a seventh category.
 */
class ComplaintType extends Model
{
    protected static string $table = 'complaint_types';
    protected static string $primaryKey = 'type_id';
    protected static array $columns = [
        'type_name', 'marks_bin_full', 'is_active', 'sort_order', 'created_at',
    ];

    public function getName(): string { return (string) $this->get('type_name'); }
    public function isActive(): bool { return (int) $this->get('is_active') === 1; }
    public function getSortOrder(): int { return (int) $this->get('sort_order'); }

    /**
     * True when a complaint of this type means the bin needs collecting.
     *
     * This is the type's meaning, not its name. An Administrator may rename
     * "Overflow" to anything they like and this stays where it is, which is
     * the whole reason it is a column rather than a list of strings in
     * ComplaintBinFlagObserver.
     */
    public function marksBinFull(): bool { return (int) $this->get('marks_bin_full') === 1; }

    public function setDetails(string $name, bool $marksBinFull, bool $active, int $order): void
    {
        $this->set('type_name', $name);
        $this->set('marks_bin_full', $marksBinFull ? 1 : 0);
        $this->set('is_active', $active ? 1 : 0);
        $this->set('sort_order', $order);
    }

    /**
     * Whether the type a complaint names means "this bin is full".
     *
     * Asked by name because that is what a complaint stores. The name is
     * kept in step by ON UPDATE CASCADE, so a rename moves the complaints
     * and this lookup together and the answer never changes underneath.
     */
    public static function marksBinFullByName(string $name): bool
    {
        $row = Database::getInstance()->query(
            'SELECT marks_bin_full FROM complaint_types WHERE type_name = ?',
            [$name]
        )->fetch();

        return (int) ($row['marks_bin_full'] ?? 0) === 1;
    }

    /** Where a newly added type goes: after everything already listed. */
    public static function nextSortOrder(): int
    {
        $row = Database::getInstance()
            ->query('SELECT COALESCE(MAX(sort_order), 0) AS highest FROM complaint_types')
            ->fetch();

        return (int) ($row['highest'] ?? 0) + 10;
    }

    /** Every type, in the order an Administrator arranged them. */
    public static function allOrdered(): array
    {
        $rows = Database::getInstance()->query(
            'SELECT * FROM complaint_types ORDER BY sort_order, type_name'
        )->fetchAll();

        return array_map(static fn(array $row): self => static::hydrate($row), $rows);
    }

    /** The names a reporter may choose from right now. */
    public static function activeNames(): array
    {
        $rows = Database::getInstance()->query(
            'SELECT type_name FROM complaint_types WHERE is_active = 1 ORDER BY sort_order, type_name'
        )->fetchAll();

        return array_map(static fn(array $row): string => (string) $row['type_name'], $rows);
    }

    /** How many complaints were filed under this type, deleted ones included. */
    public function complaintCount(): int
    {
        $row = Database::getInstance()->query(
            'SELECT COUNT(*) AS total FROM complaints WHERE complaint_type = ?',
            [$this->getName()]
        )->fetch();

        return (int) ($row['total'] ?? 0);
    }
}
