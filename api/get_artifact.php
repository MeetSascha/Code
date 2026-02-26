<?php
// 1. Konfiguration und Helper laden
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// 2. Eingabe validieren
$id = $_GET['id'] ?? null;

// Einfache Validierung: ID muss eine Zahl sein
if (!$id || !is_numeric($id)) {
    abort('Ungültige oder fehlende ID', 400);
}

// 3. Datenbankverbindung
$db = get_db();

// 4. AUTH CHECK: Ist der User ein Admin?
// Wir prüfen den JWT Token aus dem Authorization Header.
$isAdmin = false;
$token = get_bearer_token();

if ($token) {
    $payload = jwt_decode($token);
    // Wenn der Token gültig ist (Signatur & Ablaufzeit korrekt), ist es ein Admin.
    if ($payload) {
        $isAdmin = true;
    }
}

// 5. Haupt-Artefakt abrufen
// Wir joinen direkt die Tabelle 'locations', um den primären Ort zu bekommen.
$sql = "SELECT 
            a.*,
            l.name as loc_name,
            l.slug as loc_slug,
            l.lat as loc_lat,
            l.lng as loc_lng
        FROM artifacts a
        LEFT JOIN locations l ON a.location_id = l.id
        WHERE a.id = :id";

// WICHTIG: Filterlogik
// Wenn KEIN Admin, dann nur veröffentlichte zeigen.
// Wenn Admin, dann diesen Filter weglassen (= alles zeigen).
if (!$isAdmin) {
    $sql .= " AND a.is_published = 1";
}

$stmt = $db->prepare($sql);
$stmt->execute([':id' => $id]);
$artifact = $stmt->fetch();

if (!$artifact) {
    // Falls nichts gefunden wurde (oder Zugriff verweigert durch Filter)
    abort('Artefakt nicht gefunden', 404);
}

// 6. Daten formatieren
// Die Join-Daten für den Ort in ein sauberes Unter-Objekt packen (wie im Frontend erwartet)
$primaryLocation = null;
if ($artifact['location_id']) {
    $primaryLocation = [
        'id'   => $artifact['location_id'],
        'name' => $artifact['loc_name'],
        'slug' => $artifact['loc_slug'],
        'lat'  => $artifact['loc_lat'],
        'lng'  => $artifact['loc_lng']
    ];
}

// Aufräumen: Die flachen Join-Spalten entfernen wir aus dem Hauptobjekt, damit es sauber bleibt
unset($artifact['loc_name'], $artifact['loc_slug'], $artifact['loc_lat'], $artifact['loc_lng']);

// Das Objekt wieder anhängen
$artifact['primary_location'] = $primaryLocation;


// 7. Verknüpfte PERSONEN laden (n:m Beziehung)
$sqlPersons = "SELECT p.*, ap.role 
               FROM persons p 
               JOIN artifact_persons ap ON p.id = ap.person_id 
               WHERE ap.artifact_id = :id
               ORDER BY p.last_name ASC, p.first_name ASC";

$stmtP = $db->prepare($sqlPersons);
$stmtP->execute([':id' => $id]);
$artifact['persons'] = $stmtP->fetchAll();


// 8. Verknüpfte WEITERE ORTE laden (n:m Beziehung)
$sqlLocations = "SELECT l.*, al.note
                 FROM locations l
                 JOIN artifact_locations al ON l.id = al.location_id
                 WHERE al.artifact_id = :id
                 ORDER BY l.name ASC";

$stmtL = $db->prepare($sqlLocations);
$stmtL->execute([':id' => $id]);
$artifact['locations'] = $stmtL->fetchAll();


// 9. JSON Ausgabe senden
json_response($artifact);