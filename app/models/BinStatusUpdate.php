<?php
/**
 * Immutable audit entry created whenever a cleaner changes bin status.
 *
 * Module : Bin & Location Management
 */
class BinStatusUpdate extends Model
{
    protected static string $table = 'bin_status_updates';
    protected static string $primaryKey = 'update_id';
    protected static array $columns = [
        'bin_id', 'cleaner_id', 'old_status', 'new_status', 'remarks', 'updated_at',
    ];

    public function setDetails(
        int $binId,
        int $cleanerId,
        string $oldStatus,
        string $newStatus,
        ?string $remarks
    ): void {
        $this->set('bin_id', $binId);
        $this->set('cleaner_id', $cleanerId);
        $this->set('old_status', $oldStatus);
        $this->set('new_status', $newStatus);
        $this->set('remarks', $remarks);
    }

    public function getOldStatus(): string { return (string) $this->get('old_status'); }
    public function getNewStatus(): string { return (string) $this->get('new_status'); }
    public function getRemarks(): ?string { return $this->get('remarks'); }
    public function getUpdatedAt(): string { return (string) $this->get('updated_at'); }
    public function getCleaner(): ?User { return $this->belongsTo(User::class, 'cleaner_id'); }
}
