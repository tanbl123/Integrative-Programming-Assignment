<?php
/**
 * Model - the Object-Relational Mapping (ORM) base class.
 *
 * Author : Group D - Ng Zi Zhang (2406898), Ong Kar Heng (2408830),
 *          Tan Boon Leong (2402865), Phang Jun Hong (2406646)
 * Module : Shared core - EcoCampus Waste Management System
 *
 * WHAT "ORM" MEANS HERE
 * A database row is just an array of strings. An object is a thing with
 * behaviour. This class maps one to the other: rows coming out of MySQL are
 * turned into PHP objects, and objects being saved are turned back into rows.
 * Controllers therefore never see SQL or column arrays - they work with
 * objects such as $complaint->getDescription() or $complaint->getBin().
 *
 * Every subclass declares its table and its columns, then inherits
 * find(), all(), where(), save() and delete() from here.
 *
 * IMPORTANT (assignment requirement): relationships between entity classes
 * are exposed as OBJECT REFERENCES, not foreign keys. A Complaint gives you
 * back a Bin object via getBin(), not an integer bin_id. Subclasses implement
 * those getters using belongsTo().
 */
abstract class Model
{
    /** Table this model maps to. Every subclass must set this. */
    protected static string $table = '';

    /** Primary key column. Every subclass must set this. */
    protected static string $primaryKey = 'id';

    /** Columns the ORM is allowed to read and write. */
    protected static array $columns = [];

    /** Raw column values for this row, keyed by column name. */
    protected array $attributes = [];

    /** Cache for already-loaded related objects, so we query only once. */
    protected array $relatedCache = [];

    // ------------------------------------------------------------------
    // Creating objects from rows
    // ------------------------------------------------------------------

    /** Builds an object of the calling class from one database row. */
    public static function hydrate(array $row): static
    {
        $model = new static();
        $model->attributes = $row;
        return $model;
    }

    /** Builds a list of objects from a list of rows. */
    protected static function hydrateAll(array $rows): array
    {
        return array_map(static fn(array $row): static => static::hydrate($row), $rows);
    }

    // ------------------------------------------------------------------
    // Reading
    // ------------------------------------------------------------------

    /** Finds one record by primary key, or null if it does not exist. */
    public static function find(int $id): ?static
    {
        $sql = 'SELECT * FROM ' . static::$table
             . ' WHERE ' . static::$primaryKey . ' = ? LIMIT 1';

        $row = Database::getInstance()->selectOne($sql, [$id]);

        return $row === null ? null : static::hydrate($row);
    }

    /** Returns every record, newest first by primary key. */
    public static function all(): array
    {
        $sql = 'SELECT * FROM ' . static::$table
             . ' ORDER BY ' . static::$primaryKey . ' DESC';

        return static::hydrateAll(Database::getInstance()->selectAll($sql));
    }

    /**
     * Returns every record where $column equals $value.
     *
     * $column is validated against the model's declared column list, because
     * a column name cannot be sent as a bound parameter - only values can.
     * Without this check, a caller could inject SQL through the column name.
     */
    public static function where(string $column, mixed $value): array
    {
        static::assertColumn($column);

        $sql = 'SELECT * FROM ' . static::$table
             . ' WHERE ' . $column . ' = ?'
             . ' ORDER BY ' . static::$primaryKey . ' DESC';

        return static::hydrateAll(Database::getInstance()->selectAll($sql, [$value]));
    }

    /** Counts records, optionally filtered by one column. */
    public static function count(?string $column = null, mixed $value = null): int
    {
        $sql = 'SELECT COUNT(*) AS total FROM ' . static::$table;
        $params = [];

        if ($column !== null) {
            static::assertColumn($column);
            $sql .= ' WHERE ' . $column . ' = ?';
            $params[] = $value;
        }

        $row = Database::getInstance()->selectOne($sql, $params);

        return (int) ($row['total'] ?? 0);
    }

    // ------------------------------------------------------------------
    // Writing
    // ------------------------------------------------------------------

    /**
     * Saves this object: INSERT when it has no primary key yet,
     * UPDATE when it does. Returns the primary key.
     */
    public function save(): int
    {
        $data = [];
        foreach (static::$columns as $column) {
            if (array_key_exists($column, $this->attributes)) {
                $data[$column] = $this->attributes[$column];
            }
        }

        $db = Database::getInstance();

        if ($this->getKey() === null) {
            // INSERT - placeholders are built from our own column list,
            // never from user-supplied keys.
            $names        = array_keys($data);
            $placeholders = implode(', ', array_fill(0, count($names), '?'));

            $sql = 'INSERT INTO ' . static::$table
                 . ' (' . implode(', ', $names) . ') VALUES (' . $placeholders . ')';

            $db->query($sql, array_values($data));

            $this->attributes[static::$primaryKey] = $db->lastInsertId();
        } else {
            // UPDATE
            $assignments = implode(', ', array_map(
                static fn(string $c): string => $c . ' = ?',
                array_keys($data)
            ));

            $sql = 'UPDATE ' . static::$table . ' SET ' . $assignments
                 . ' WHERE ' . static::$primaryKey . ' = ?';

            $params   = array_values($data);
            $params[] = $this->getKey();

            $db->query($sql, $params);
        }

        return $this->getKey();
    }

    /** Deletes this record. Returns false if it was never saved. */
    public function delete(): bool
    {
        if ($this->getKey() === null) {
            return false;
        }

        $sql = 'DELETE FROM ' . static::$table
             . ' WHERE ' . static::$primaryKey . ' = ?';

        Database::getInstance()->query($sql, [$this->getKey()]);

        return true;
    }

    // ------------------------------------------------------------------
    // Attribute access
    // ------------------------------------------------------------------

    /** The primary key value, or null when this object is not yet saved. */
    public function getKey(): ?int
    {
        $key = $this->attributes[static::$primaryKey] ?? null;
        return $key === null ? null : (int) $key;
    }

    /** Reads one raw column value. */
    protected function get(string $column): mixed
    {
        return $this->attributes[$column] ?? null;
    }

    /** Sets one raw column value. */
    protected function set(string $column, mixed $value): void
    {
        $this->attributes[$column] = $value;
    }

    /** All raw column values - handy when building a JSON web-service reply. */
    public function toArray(): array
    {
        return $this->attributes;
    }

    // ------------------------------------------------------------------
    // Relationships as object references
    // ------------------------------------------------------------------

    /**
     * Loads the parent object this record points at, and remembers it.
     *
     * Example, inside Complaint:
     *     public function getBin(): ?Bin {
     *         return $this->belongsTo(Bin::class, 'bin_id');
     *     }
     *
     * Callers then use $complaint->getBin()->getBinCode() - an object
     * reference - instead of handling the raw bin_id foreign key.
     */
    protected function belongsTo(string $relatedClass, string $foreignKey): ?Model
    {
        if (array_key_exists($foreignKey, $this->relatedCache)) {
            return $this->relatedCache[$foreignKey];
        }

        $id = $this->get($foreignKey);
        $related = $id === null ? null : $relatedClass::find((int) $id);

        $this->relatedCache[$foreignKey] = $related;

        return $related;
    }

    /**
     * Loads the child objects that point back at this record.
     *
     * Example, inside Complaint:
     *     public function getAttachments(): array {
     *         return $this->hasMany(ComplaintAttachment::class, 'complaint_id');
     *     }
     */
    protected function hasMany(string $relatedClass, string $foreignKey): array
    {
        $cacheKey = $relatedClass . ':' . $foreignKey;

        if (array_key_exists($cacheKey, $this->relatedCache)) {
            return $this->relatedCache[$cacheKey];
        }

        $id = $this->getKey();
        $children = $id === null ? [] : $relatedClass::where($foreignKey, $id);

        $this->relatedCache[$cacheKey] = $children;

        return $children;
    }

    // ------------------------------------------------------------------
    // Safety
    // ------------------------------------------------------------------

    /** Rejects any column name the model did not declare. */
    protected static function assertColumn(string $column): void
    {
        $allowed = array_merge(static::$columns, [static::$primaryKey]);

        if (!in_array($column, $allowed, true)) {
            throw new InvalidArgumentException(
                'Unknown column "' . $column . '" on ' . static::class
            );
        }
    }
}
