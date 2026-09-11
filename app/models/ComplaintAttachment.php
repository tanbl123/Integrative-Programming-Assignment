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
    public function getUploadedAt(): string { return (string) $this->get('uploaded_at'); }

    /**
     * The name shown to a user and offered when the file is downloaded.
     *
     * A reporter's own filename says nothing useful about the evidence and is
     * seen by the administrator handling the complaint as well as by the
     * reporter, so a neutral name is presented instead. The filename the
     * reporter used is still recorded in original_name, which keeps the
     * provenance of the file without putting it on screen.
     *
     * The extension comes from stored_name, which was built from the media
     * type finfo read out of the file itself - never from the name the
     * browser supplied.
     */
    public function getDisplayName(): string
    {
        $extension = pathinfo($this->getStoredName(), PATHINFO_EXTENSION);

        return $extension === '' ? 'PhotoEvidence' : 'PhotoEvidence.' . $extension;
    }

    /** The stored size written the way a person reads it, such as 284 KB. */
    public function getReadableSize(): string
    {
        $bytes = $this->getFileSize();

        return $bytes < 1048576
            ? max(1, (int) round($bytes / 1024)) . ' KB'
            : number_format($bytes / 1048576, 1) . ' MB';
    }
}
