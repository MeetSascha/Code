<?php
require_once __DIR__ . '/_auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort('Method Not Allowed', 405);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    abort('Ungültiger JSON-Body.', 400);
}

// -------------------------------------------------------
// Felder einlesen & validieren
// -------------------------------------------------------
$id          = isset($body['id']) && $body['id'] !== null && $body['id'] !== '' 
                    ? (int) $body['id'] 
                    : null;
$title       = trim($body['title']       ?? '');
$description = trim($body['description'] ?? '');
$year        = isset($body['year'])   ? (int) $body['year']   : null;
$decade      = isset($body['decade']) ? (int) $body['decade'] : null;
$type        = trim($body['artifact_type'] ?? 'photo');
$source      = trim($body['source']      ?? '');
$imageUrl    = trim($body['image_url']   ?? '');
$thumbUrl    = trim($body['thumb_url']   ?? '');
$locationId  = isset($body['location_id']) ? (int) $body['location_id'] : null;
$isPublished = isset($body['is_published']) ? (int) (bool) $body['is_published'] : 0;
$persons     = $body['persons']   ?? [];  // [['id' => 1, 'role' => 'Abgebildet'], ...]
$locations   = $body['locations'] ?? [];  // [['id' => 2, 'note' => '...'], ...]

$allowedTypes = ['photo', 'document', 'postcard', 'map', 'other'];

if (!$title) {
    abort('Titel ist erforderlich.', 422);
}
if (!in_array($type, $allowedTypes, true)) {
    abort('Ungültiger artifact_type.', 422);
}

// make_slug() kommt aus helpers.php

$db = get_db();

// -------------------------------------------------------
// CREATE oder UPDATE
// -------------------------------------------------------
if (!$id) {
    // --- CREATE ---
    $baseSlug = make_slug($title);
    $slug     = $baseSlug;
    $counter  = 1;

    // Slug eindeutig machen
    while (true) {
        $check = $db->prepare('SELECT id FROM artifacts WHERE slug = :s LIMIT 1');
        $check->execute([':s' => $slug]);
        if (!$check->fetch()) break;
        $slug = $baseSlug . '-' . $counter++;
    }

    $stmt = $db->prepare("
        INSERT INTO artifacts
            (title, slug, description, year, decade, artifact_type,
             image_url, thumb_url, source, location_id, is_published)
        VALUES
            (:title, :slug, :desc, :year, :decade, :type,
             :image, :thumb, :source, :loc, :pub)
    ");
    $stmt->execute([
        ':title'  => $title,
        ':slug'   => $slug,
        ':desc'   => $description ?: null,
        ':year'   => $year,
        ':decade' => $decade,
        ':type'   => $type,
        ':image'  => $imageUrl  ?: null,
        ':thumb'  => $thumbUrl  ?: null,
        ':source' => $source    ?: null,
        ':loc'    => $locationId,
        ':pub'    => $isPublished,
    ]);
    $id = (int) $db->lastInsertId();

} else {
    // --- UPDATE ---
    $existing = $db->prepare('SELECT id FROM artifacts WHERE id = :id LIMIT 1');
    $existing->execute([':id' => $id]);
    if (!$existing->fetch()) {
        abort('Artefakt nicht gefunden.', 404);
    }

    $stmt = $db->prepare("
        UPDATE artifacts SET
            title        = :title,
            description  = :desc,
            year         = :year,
            decade       = :decade,
            artifact_type = :type,
            image_url    = :image,
            thumb_url    = :thumb,
            source       = :source,
            location_id  = :loc,
            is_published = :pub
        WHERE id = :id
    ");
    $stmt->execute([
        ':title'  => $title,
        ':desc'   => $description ?: null,
        ':year'   => $year,
        ':decade' => $decade,
        ':type'   => $type,
        ':image'  => $imageUrl  ?: null,
        ':thumb'  => $thumbUrl  ?: null,
        ':source' => $source    ?: null,
        ':loc'    => $locationId,
        ':pub'    => $isPublished,
        ':id'     => $id,
    ]);
}

// -------------------------------------------------------
// Verknüpfte Personen & Orte synchronisieren (Replace)
// -------------------------------------------------------
$db->prepare('DELETE FROM artifact_persons   WHERE artifact_id = :id')->execute([':id' => $id]);
$db->prepare('DELETE FROM artifact_locations WHERE artifact_id = :id')->execute([':id' => $id]);

if (!empty($persons)) {
    $stmtP = $db->prepare('INSERT INTO artifact_persons (artifact_id, person_id, role) VALUES (:a, :p, :r)');
    foreach ($persons as $p) {
        if (empty($p['id'])) continue;
        $stmtP->execute([':a' => $id, ':p' => (int)$p['id'], ':r' => $p['role'] ?? null]);
    }
}

if (!empty($locations)) {
    $stmtL = $db->prepare('INSERT INTO artifact_locations (artifact_id, location_id, note) VALUES (:a, :l, :n)');
    foreach ($locations as $l) {
        if (empty($l['id'])) continue;
        $stmtL->execute([':a' => $id, ':l' => (int)$l['id'], ':n' => $l['note'] ?? null]);
    }
}

json_response(['success' => true, 'id' => $id], 200);