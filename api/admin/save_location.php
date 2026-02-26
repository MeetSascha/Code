<?php
require_once __DIR__ . '/_auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort('Method Not Allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$id    = $input['id'] ?? null;
$name  = trim($input['name'] ?? '');
$lat   = $input['lat'] ?? null;
$lng   = $input['lng'] ?? null;

if (!$name) abort('Name ist erforderlich', 400);

$slug = make_slug($name);

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

json_response(['success' => true, 'id' => $newId, 'name' => $name, 'slug' => $slug]);
