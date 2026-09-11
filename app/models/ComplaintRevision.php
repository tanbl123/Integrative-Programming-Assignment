<?php
/**
 * One snapshot of a complaint as it stood before an edit.
 *
 * Author : Tan Boon Leong (2402865)
 * Module : Complaint / Report Management
 *
 * Written once by ComplaintRevisionObserver and never changed afterwards,
 * which is what lets a reporter show that their report said something else
 * before somebody edited it.
 */
class ComplaintRevision extends Model
{
    protected static string $table = 'complaint_revisions';
    protected static string $primaryKey = 'revision_id';
    protected static array $columns = [
        'complaint_id', 'edited_by', 'bin_id', 'complaint_type', 'description',
        'attachment_id', 'edited_at',
    ];

    /** @param array{bin:int,type:string,description:string,attachment:?int} $previous */
    public function setDetails(int $complaintId, ?int $editedBy, array $previous): void
    {
        $this->set('complaint_id', $complaintId);
        $this->set('edited_by', $editedBy);
        $this->set('bin_id', $previous['bin']);
        $this->set('complaint_type', $previous['type']);
        $this->set('description', $previous['description']);
        $this->set('attachment_id', $previous['attachment'] ?? null);
    }

    public function getType(): string { return (string) $this->get('complaint_type'); }
    public function getDescription(): string { return (string) $this->get('description'); }
    public function getEditedAt(): string { return (string) $this->get('edited_at'); }
    public function getEditedBy(): ?User { return $this->belongsTo(User::class, 'edited_by'); }

    /** The photograph this edit replaced, where it replaced one. */
    public function getAttachment(): ?ComplaintAttachment
    {
        return $this->belongsTo(ComplaintAttachment::class, 'attachment_id');
    }
    public function getBin(): ?Bin { return $this->belongsTo(Bin::class, 'bin_id'); }
}
