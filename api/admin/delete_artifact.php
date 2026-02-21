<?php
require_once __DIR__ . '/_auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort('Method Not Allowed', 405);
}

$body = json_decode(file_get_contents('php://input'), true);
$id   = isset($body['id']) ? (int) $body['id'] : null;
$hard = (bool) ($body['hard_delete'] ?? false);  // Nur Admins erlaubt

if (!$id) {
    abort('ID fehlt.', 400);
}

// Hard-Delete nur für Admins
if ($hard && $CURRENT_USER['role'] !== 'admin') {
    abort('Keine Berechtigung für Hard-Delete.', 403);
}

$db = get_db();

if ($hard) {
    // Löscht auch artifact_persons & artifact_locations via CASCADE
    $db->prepare('DELETE FROM artifacts WHERE id = :id')->execute([':id' => $id]);
} else {
    // Soft-Delete: einfach depublizieren
    $db->prepare('UPDATE artifacts SET is_published = 0 WHERE id = :id')->execute([':id' => $id]);
}

json_response(['success' => true]);