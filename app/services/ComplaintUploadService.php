<?php
/** Secure MIME-verified complaint image storage. Author: Ong Kar Heng (2408830). */
class ComplaintUploadService
{
    private const MAX_BYTES = 5_242_880;
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function validateAndStore(?array $upload): ?array
    {
        if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !isset($upload['tmp_name'], $upload['name'], $upload['size'])
            || !is_uploaded_file($upload['tmp_name'])) {
            throw new ValidationException(['attachment' => 'The photo upload did not complete safely.']);
        }
        if ((int) $upload['size'] < 1 || (int) $upload['size'] > self::MAX_BYTES) {
            throw new ValidationException(['attachment' => 'Photo must be no larger than 5 MB.']);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        if (!is_string($mime) || !isset(self::ALLOWED[$mime])) {
            throw new ValidationException(['attachment' => 'Only JPEG, PNG, or WebP images are allowed.']);
        }

        $storedName = bin2hex(random_bytes(20)) . '.' . self::ALLOWED[$mime];
        if (!is_dir(UPLOAD_PATH) && !mkdir(UPLOAD_PATH, 0750, true) && !is_dir(UPLOAD_PATH)) {
            throw new RuntimeException('Upload storage is unavailable.');
        }
        $destination = UPLOAD_PATH . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            throw new RuntimeException('The verified photo could not be stored.');
        }

        return [
            'original_name' => mb_substr(basename((string) $upload['name']), 0, 255),
            'stored_name' => $storedName,
            'mime_type' => $mime,
            'file_size' => (int) $upload['size'],
            'path' => $destination,
        ];
    }
}
