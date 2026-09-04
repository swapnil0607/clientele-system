<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function handle_logo_upload(array $file, string $clientName, ?string $currentLogo = null): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $currentLogo ?? '';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Logo upload failed. Please try again.');
    }

    if ((int) ($file['size'] ?? 0) > MAX_LOGO_UPLOAD_BYTES) {
        throw new RuntimeException('Logo file is too large. Maximum allowed size is 2 MB.');
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        throw new RuntimeException('Please upload a valid image file.');
    }

    $allowedTypes = [
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];

    $imageType = $imageInfo[2] ?? null;
    if (!isset($allowedTypes[$imageType])) {
        throw new RuntimeException('Logo must be PNG, JPG, WEBP, or GIF.');
    }

    if (!is_dir(CLIENT_LOGO_UPLOAD_DIR) && !mkdir(CLIENT_LOGO_UPLOAD_DIR, 0755, true) && !is_dir(CLIENT_LOGO_UPLOAD_DIR)) {
        throw new RuntimeException('Could not create logo upload folder.');
    }

    $safeName = preg_replace('/[^a-z0-9]+/', '-', strtolower($clientName));
    $safeName = trim((string) $safeName, '-') ?: 'client-logo';
    $extension = $allowedTypes[$imageType];
    $fileName = $safeName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = CLIENT_LOGO_UPLOAD_DIR . '/' . $fileName;

    if (!move_uploaded_file($tmpPath, $destination)) {
        throw new RuntimeException('Could not save uploaded logo.');
    }

    return CLIENT_LOGO_PUBLIC_PATH . '/' . $fileName;
}
