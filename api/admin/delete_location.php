<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// Nur Admins
$token = get_bearer_token();
if (!$token || !jwt_decode($token)) abort('Unauthorized', 401);

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id) abort('ID fehlt', 400);

$db = get_db();

// Prüfen ob noch verwendet
$stmt = $db->prepare("SELECT COUNT(*) FROM artifact_locations WHERE location_id = ?");
$stmt->execute([$id]);
if ($stmt->fetchColumn() > 0) {
    abort('Ort kann nicht gelöscht werden, da er noch mit Artefakten verknüpft ist.', 409);
}

$stmt = $db->prepare("DELETE FROM locations WHERE id = ?");
$stmt->execute([$id]);

json_response(['status' => 'deleted']);