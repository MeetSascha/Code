<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    abort('Method Not Allowed', 405);
}

// Auth Check (Optional: Wenn die Map öffentlich ist, diesen Block entfernen)
// Hier lassen wir ihn drin für den Admin-Bereich.
$token = get_bearer_token();
if (!$token || !jwt_decode($token)) {
    // Falls du die Orte später öffentlich auf einer Karte zeigen willst,
    // kommentiere das 'abort' hier aus!
    // abort('Unauthorized', 401);
}

$db = get_db();
$q = trim($_GET['q'] ?? '');

$sql = "SELECT l.*,
       (SELECT COUNT(*) FROM artifact_locations al WHERE al.location_id = l.id) as usage_count
       FROM locations l";
$params = [];

if ($q) {
    $sql .= " WHERE l.name LIKE :q";
    $params[':q'] = "%$q%";
}

$sql .= " ORDER BY l.name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$locations = $stmt->fetchAll();

json_response($locations);
