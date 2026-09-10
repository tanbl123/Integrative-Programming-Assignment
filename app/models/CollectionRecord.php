<?php
/** Completed waste collection measurement.  */
class CollectionRecord extends Model
{
    protected static string $table = 'collection_records';
    protected static string $primaryKey = 'record_id';
    protected static array $columns = [
        'assignment_id', 'cleaner_id', 'bin_id', 'category_id',
        'estimated_weight_kg', 'notes', 'collected_at',
    ];

    public function setDetails(int $assignmentId, int $cleanerId, Bin $bin, ?float $weight, ?string $notes): void
    {
        $this->set('assignment_id', $assignmentId);
        $this->set('cleaner_id', $cleanerId);
        $this->set('bin_id', $bin->getKey());
        $this->set('category_id', $bin->getCategoryId());
        $this->set('estimated_weight_kg', $weight);
        $this->set('notes', $notes);
    }
}
