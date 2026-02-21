<?php
require_once __DIR__ . '/_auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') abort('Method Not Allowed', 405);

$body = json_decode(file_get_contents('php://input'), true);
$name = trim($body['name'] ?? '');

if (!$name) abort('Name ist erforderlich.', 422);

// Slug generieren
function make_slug(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss']);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

$db   = get_db();
$id   = isset($body['id']) && $body['id'] !== null ? (int) $body['id'] : null;
$lat  = isset($body['lat'])  ? (float) $body['lat']  : null;
$lng  = isset($body['lng'])  ? (float) $body['lng']  : null;
$desc = trim($body['description'] ?? '') ?: null;

if (!$id) {
    // CREATE
    $baseSlug = make_slug($name);
    $slug     = $baseSlug;
    $counter  = 1;
    while (true) {
        $check = $db->prepare('SELECT id FROM locations WHERE slug = :s LIMIT 1');
        $check->execute([':s' => $slug]);
        if (!$check->fetch()) break;
        $slug = $baseSlug . '-' . $counter++;
    }

    $stmt = $db->prepare("
        INSERT INTO locations (name, slug, description, lat, lng)
        VALUES (:name, :slug, :desc, :lat, :lng)
    ");
    $stmt->execute([':name' => $name, ':slug' => $slug, ':desc' => $desc, ':lat' => $lat, ':lng' => $lng]);
    $id = (int) $db->lastInsertId();
} else {
    // UPDATE
    $stmt = $db->prepare("
        UPDATE locations SET name = :name, description = :desc, lat = :lat, lng = :lng
        WHERE id = :id
    ");
    $stmt->execute([':name' => $name, ':desc' => $desc, ':lat' => $lat, ':lng' => $lng, ':id' => $id]);
}

json_response(['success' => true, 'id' => $id]);