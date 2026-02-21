<?php
require_once __DIR__ . '/_auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort('Method Not Allowed', 405);
}

// -------------------------------------------------------
// Konfiguration
// -------------------------------------------------------
define('UPLOAD_DIR',      __DIR__ . '/../../uploads/artifacts/');
define('UPLOAD_DIR_THUMB', __DIR__ . '/../../uploads/artifacts/thumbs/');
define('UPLOAD_BASE_URL', '/uploads/artifacts/');
define('MAX_FILE_SIZE',   10 * 1024 * 1024);  // 10 MB
define('THUMB_WIDTH',     400);
define('THUMB_HEIGHT',    300);

$allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

if (!is_dir(UPLOAD_DIR))       mkdir(UPLOAD_DIR,       0755, true);
if (!is_dir(UPLOAD_DIR_THUMB)) mkdir(UPLOAD_DIR_THUMB, 0755, true);

// -------------------------------------------------------
// Datei prüfen
// -------------------------------------------------------
if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    abort('Kein Bild empfangen oder Upload-Fehler.', 400);
}

$file     = $_FILES['image'];
$mimeType = mime_content_type($file['tmp_name']);  // Nicht dem Client vertrauen!

if (!in_array($mimeType, $allowedMime, true)) {
    abort('Nur JPEG, PNG und WebP erlaubt.', 415);
}
if ($file['size'] > MAX_FILE_SIZE) {
    abort('Datei zu groß (max. 10 MB).', 413);
}

// -------------------------------------------------------
// Sicheren Dateinamen generieren
// -------------------------------------------------------
$ext       = match($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
};
$filename  = bin2hex(random_bytes(12)) . '.' . $ext;  // z.B. a3f9c2...d1.jpg
$destPath  = UPLOAD_DIR . $filename;
$thumbPath = UPLOAD_DIR_THUMB . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    abort('Konnte Datei nicht speichern.', 500);
}

// -------------------------------------------------------
// Thumbnail erstellen (natives PHP GD, kein ImageMagick nötig)
// -------------------------------------------------------
function create_thumbnail(string $src, string $dest, int $maxW, int $maxH): bool {
    [$origW, $origH, $type] = getimagesize($src);

    $ratio  = min($maxW / $origW, $maxH / $origH);
    $newW   = (int) round($origW * $ratio);
    $newH   = (int) round($origH * $ratio);

    $srcImg = match($type) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => imagecreatefrompng($src),
        IMAGETYPE_WEBP => imagecreatefromwebp($src),
        default        => false,
    };
    if (!$srcImg) return false;

    $thumb = imagecreatetruecolor($newW, $newH);

    // Transparenz für PNG erhalten
    if ($type === IMAGETYPE_PNG) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }

    imagecopyresampled($thumb, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    $ok = match($type) {
        IMAGETYPE_JPEG => imagejpeg($thumb, $dest, 85),
        IMAGETYPE_PNG  => imagepng($thumb,  $dest, 6),
        IMAGETYPE_WEBP => imagewebp($thumb, $dest, 85),
        default        => false,
    };

    imagedestroy($srcImg);
    imagedestroy($thumb);
    return $ok;
}

$thumbOk = create_thumbnail($destPath, $thumbPath, THUMB_WIDTH, THUMB_HEIGHT);

// -------------------------------------------------------
// URLs zurückgeben
// -------------------------------------------------------
json_response([
    'success'   => true,
    'image_url' => UPLOAD_BASE_URL . $filename,
    'thumb_url' => $thumbOk ? UPLOAD_BASE_URL . 'thumbs/' . $filename : null,
    'filename'  => $filename,
]);