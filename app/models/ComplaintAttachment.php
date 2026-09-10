<?php
/** Secure complaint photo metadata. Author: Tan Boon Leong (2402865). */
class ComplaintAttachment extends Model
{
    protected static string $table = 'complaint_attachments';
    protected static string $primaryKey = 'attachment_id';
    protected static array $columns = [
        'complaint_id', 'original_name', 'stored_name', 'mime_type', 'file_size', 'uploaded_at',
    ];

    public function setDetails(int $complaintId, array $file): void
    {
        $this->set('complaint_id', $complaintId);
        $this->set('original_name', $file['original_name']);
        $this->set('stored_name', $file['stored_name']);
        $this->set('mime_type', $file['mime_type']);
        $this->set('file_size', $file['file_size']);
    }

    public function getComplaintId(): int { return (int) $this->get('complaint_id'); }
    public function getOriginalName(): string { return (string) $this->get('original_name'); }
    public function getStoredName(): string { return (string) $this->get('stored_name'); }
    public function getMimeType(): string { return (string) $this->get('mime_type'); }
    public function getFileSize(): int { return (int) $this->get('file_size'); }
}
