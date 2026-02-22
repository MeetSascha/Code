<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// Nur Admins dürfen speichern
$token = get_bearer_token();
if (!$token || !jwt_decode($token)) abort('Unauthorized', 401);

$input = json_decode(file_get_contents('php://input'), true);
$id    = $input['id'] ?? null;
$name  = trim($input['name'] ?? '');
$lat   = $input['lat'] ?? null;
$lng   = $input['lng'] ?? null;

if (!$name) abort('Name ist erforderlich', 400);

// Slug generieren (vereinfacht)
$slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));

$db = get_db();

if ($id) {
    // Update
    $stmt = $db->prepare("UPDATE locations SET name = ?, slug = ?, lat = ?, lng = ? WHERE id = ?");
    $stmt->execute([$name, $slug, $lat, $lng, $id]);
    $newId = $id;
} else {
    // Insert
    $stmt = $db->prepare("INSERT INTO locations (name, slug, lat, lng) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $slug, $lat, $lng]);
    $newId = $db->lastInsertId();
}

json_response(['id' => $newId, 'name' => $name, 'slug' => $slug, 'status' => 'success']);