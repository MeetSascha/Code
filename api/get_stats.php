<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// Nur Admins dürfen Statistiken sehen
$token = get_bearer_token();
if (!$token || !jwt_decode($token)) {
    abort('Unauthorized', 401);
}

$db = get_db();

// 1. Artefakte Gesamt
$stmt = $db->query("SELECT COUNT(*) FROM artifacts");
$totalArtifacts = $stmt->fetchColumn();

// 2. Entwürfe (Nicht veröffentlicht)
$stmt = $db->query("SELECT COUNT(*) FROM artifacts WHERE is_published = 0");
$pendingArtifacts = $stmt->fetchColumn();

// 3. Personen
$stmt = $db->query("SELECT COUNT(*) FROM persons");
$totalPersons = $stmt->fetchColumn();

// 4. Orte
$stmt = $db->query("SELECT COUNT(*) FROM locations");
$totalLocations = $stmt->fetchColumn();

// (Hier später: Ereignisse, Straßen, etc.)

json_response([
    'artifacts_total'   => $totalArtifacts,
    'artifacts_pending' => $pendingArtifacts,
    'persons_total'     => $totalPersons,
    'locations_total'   => $totalLocations,
]);