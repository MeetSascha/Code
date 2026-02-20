<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// Nur GET erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    abort('Method Not Allowed', 405);
}

// --- Parameter validieren ---
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    abort('Ungültige oder fehlende ID.', 400);
}

$db = get_db();

// --- 1. Artefakt laden (inkl. primärem Ort via JOIN) ---
$stmtArtifact = $db->prepare("
    SELECT
        a.id,
        a.title,
        a.slug,
        a.description,
        a.year,
        a.decade,
        a.artifact_type,
        a.image_url,
        a.thumb_url,
        a.source,
        a.created_at,
        -- Primärer Ort direkt im Artefakt-Datensatz
        l.id   AS primary_location_id,
        l.name AS primary_location_name,
        l.lat  AS primary_location_lat,
        l.lng  AS primary_location_lng
    FROM artifacts a
    LEFT JOIN locations l ON a.location_id = l.id
    WHERE a.id = :id
      AND a.is_published = 1
    LIMIT 1
");
$stmtArtifact->execute([':id' => $id]);
$artifact = $stmtArtifact->fetch();

if (!$artifact) {
    abort('Artefakt nicht gefunden.', 404);
}

// --- 2. Verknüpfte Personen laden ---
$stmtPersons = $db->prepare("
    SELECT
        p.id,
        p.first_name,
        p.last_name,
        p.birth_year,
        p.death_year,
        p.portrait_url,
        ap.role
    FROM persons p
    INNER JOIN artifact_persons ap ON ap.person_id = p.id
    WHERE ap.artifact_id = :artifact_id
    ORDER BY p.last_name, p.first_name
");
$stmtPersons->execute([':artifact_id' => $id]);
$persons = $stmtPersons->fetchAll();

// --- 3. Weitere verknüpfte Orte laden ---
$stmtLocations = $db->prepare("
    SELECT
        l.id,
        l.name,
        l.slug,
        l.lat,
        l.lng,
        al.note
    FROM locations l
    INNER JOIN artifact_locations al ON al.location_id = l.id
    WHERE al.artifact_id = :artifact_id
    ORDER BY l.name
");
$stmtLocations->execute([':artifact_id' => $id]);
$locations = $stmtLocations->fetchAll();

// --- 4. Antwort zusammenbauen ---

// Primären Ort aus dem Artefakt-Datensatz extrahieren & sauber strukturieren
$primaryLocation = null;
if ($artifact['primary_location_id']) {
    $primaryLocation = [
        'id'   => (int) $artifact['primary_location_id'],
        'name' => $artifact['primary_location_name'],
        'lat'  => $artifact['primary_location_lat'] ? (float) $artifact['primary_location_lat'] : null,
        'lng'  => $artifact['primary_location_lng'] ? (float) $artifact['primary_location_lng'] : null,
    ];
}

// Zahlen casten (PDO gibt alles als String zurück)
foreach ($persons as &$p) {
    $p['id']         = (int)   $p['id'];
    $p['birth_year'] = $p['birth_year'] ? (int) $p['birth_year'] : null;
    $p['death_year'] = $p['death_year'] ? (int) $p['death_year'] : null;
}
unset($p);

foreach ($locations as &$l) {
    $l['id']  = (int)   $l['id'];
    $l['lat'] = $l['lat'] ? (float) $l['lat'] : null;
    $l['lng'] = $l['lng'] ? (float) $l['lng'] : null;
}
unset($l);

$response = [
    'id'               => (int)    $artifact['id'],
    'title'            => $artifact['title'],
    'slug'             => $artifact['slug'],
    'description'      => $artifact['description'],
    'year'             => $artifact['year']   ? (int) $artifact['year']   : null,
    'decade'           => $artifact['decade'] ? (int) $artifact['decade'] : null,
    'artifact_type'    => $artifact['artifact_type'],
    'image_url'        => $artifact['image_url'],
    'thumb_url'        => $artifact['thumb_url'],
    'source'           => $artifact['source'],
    'created_at'       => $artifact['created_at'],
    'primary_location' => $primaryLocation,
    'persons'          => $persons,
    'locations'        => $locations,
];

json_response($response);