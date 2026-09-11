<?php
/** Complaint transition audit entry. Author: Tan Boon Leong (2402865). */
class ComplaintStatusHistory extends Model
{
    protected static string $table = 'complaint_status_history';
    protected static string $primaryKey = 'history_id';
    protected static array $columns = [
        'complaint_id', 'updated_by', 'old_status', 'new_status',
        'change_type', 'remarks', 'updated_at',
    ];

    public function setDetails(
        int $complaintId,
        ?int $updatedBy,
        ?string $old,
        string $new,
        ?string $remarks,
        string $changeType = ComplaintObserver::EVENT_STATUS
    ): void {
        $this->set('complaint_id', $complaintId);
        $this->set('updated_by', $updatedBy);
        $this->set('old_status', $old);
        $this->set('new_status', $new);
        $this->set('change_type', $changeType);
        $this->set('remarks', $remarks);
    }

    public function getOldStatus(): ?string { return $this->get('old_status'); }
    public function getNewStatus(): string { return (string) $this->get('new_status'); }
    public function getRemarks(): ?string { return $this->get('remarks'); }
    public function getChangeType(): string { return (string) ($this->get('change_type') ?: ComplaintObserver::EVENT_STATUS); }

    /** True when this row records an edit to the complaint's own fields. */
    public function isDetailsEdit(): bool
    {
        return $this->getChangeType() === ComplaintObserver::EVENT_DETAILS;
    }
    public function getUpdatedAt(): string { return (string) $this->get('updated_at'); }
    public function getUpdatedBy(): ?User { return $this->belongsTo(User::class, 'updated_by'); }
}
