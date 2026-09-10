<?php
/** Complaint transition audit entry.  */
class ComplaintStatusHistory extends Model
{
    protected static string $table = 'complaint_status_history';
    protected static string $primaryKey = 'history_id';
    protected static array $columns = [
        'complaint_id', 'updated_by', 'old_status', 'new_status', 'remarks', 'updated_at',
    ];

    public function setDetails(int $complaintId, ?int $updatedBy, ?string $old, string $new, ?string $remarks): void
    {
        $this->set('complaint_id', $complaintId);
        $this->set('updated_by', $updatedBy);
        $this->set('old_status', $old);
        $this->set('new_status', $new);
        $this->set('remarks', $remarks);
    }

    public function getOldStatus(): ?string { return $this->get('old_status'); }
    public function getNewStatus(): string { return (string) $this->get('new_status'); }
    public function getRemarks(): ?string { return $this->get('remarks'); }
    public function getUpdatedAt(): string { return (string) $this->get('updated_at'); }
    public function getUpdatedBy(): ?User { return $this->belongsTo(User::class, 'updated_by'); }
}
