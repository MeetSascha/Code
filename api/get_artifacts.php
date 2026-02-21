<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    abort('Method Not Allowed', 405);
}

$db = get_db();

// -------------------------------------------------------
// 1. Parameter einlesen & validieren
// -------------------------------------------------------
$page     = max(1, (int) ($_GET['page']     ?? 1));
$perPage  = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
$offset   = ($page - 1) * $perPage;

$q          = trim($_GET['q']           ?? '');
$type       = trim($_GET['type']        ?? '');
$year       = filter_input(INPUT_GET, 'year',        FILTER_VALIDATE_INT);
$decade     = filter_input(INPUT_GET, 'decade',      FILTER_VALIDATE_INT);
$locationId = filter_input(INPUT_GET, 'location_id', FILTER_VALIDATE_INT);
$personId   = filter_input(INPUT_GET, 'person_id',   FILTER_VALIDATE_INT);

$allowedTypes = ['photo', 'document', 'postcard', 'map', 'other'];
if ($type && !in_array($type, $allowedTypes, true)) {
    abort('Ungültiger type-Parameter.', 400);
}

$allowedSorts = [
    'year_desc'  => 'a.year DESC,  a.id DESC',
    'year_asc'   => 'a.year ASC,   a.id ASC',
    'title_asc'  => 'a.title ASC',
];
$sortKey   = $_GET['sort'] ?? 'year_desc';
$orderBy   = $allowedSorts[$sortKey] ?? $allowedSorts['year_desc'];

// -------------------------------------------------------
// 2. WHERE-Klauseln & Parameter dynamisch aufbauen
// -------------------------------------------------------
$where  = ['a.is_published = 1'];
$params = [];

if ($q !== '') {
    // Einfache LIKE-Suche – für größere Datenmengen später FULLTEXT INDEX
    $where[]          = '(a.title LIKE :q OR a.description LIKE :q)';
    $params[':q']     = '%' . $q . '%';
}

if ($type) {
    $where[]          = 'a.artifact_type = :type';
    $params[':type']  = $type;
}

if ($year) {
    $where[]          = 'a.year = :year';
    $params[':year']  = $year;
} elseif ($decade) {
    // Dekade: z.B. 1920 → 1920–1929
    $where[]              = 'a.decade = :decade';
    $params[':decade']    = $decade;
}

if ($locationId) {
    // Primärer Ort ODER in artifact_locations verknüpft
    $where[] = '(a.location_id = :loc_id OR EXISTS (
        SELECT 1 FROM artifact_locations al
        WHERE al.artifact_id = a.id AND al.location_id = :loc_id2
    ))';
    $params[':loc_id']  = $locationId;
    $params[':loc_id2'] = $locationId;
}

if ($personId) {
    $where[] = 'EXISTS (
        SELECT 1 FROM artifact_persons ap
        WHERE ap.artifact_id = a.id AND ap.person_id = :person_id
    )';
    $params[':person_id'] = $personId;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// -------------------------------------------------------
// 3. Gesamtanzahl für Pagination ermitteln
// -------------------------------------------------------
$countSQL = "SELECT COUNT(*) FROM artifacts a $whereSQL";
$stmtCount = $db->prepare($countSQL);
$stmtCount->execute($params);
$total = (int) $stmtCount->fetchColumn();

// -------------------------------------------------------
// 4. Artefakte laden (mit primärem Ort via LEFT JOIN)
// -------------------------------------------------------
$sql = "
    SELECT
        a.id,
        a.title,
        a.slug,
        a.description,
        a.year,
        a.decade,
        a.artifact_type,
        a.thumb_url,
        a.image_url,
        a.source,
        a.created_at,
        l.id   AS loc_id,
        l.name AS loc_name
    FROM artifacts a
    LEFT JOIN locations l ON a.location_id = l.id
    $whereSQL
    ORDER BY $orderBy
    LIMIT :limit OFFSET :offset
";

// LIMIT/OFFSET separat binden (PDO mag keine benannten Params für diese)
$stmtList = $db->prepare($sql);
foreach ($params as $key => $val) {
    $stmtList->bindValue($key, $val);
}
$stmtList->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmtList->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmtList->execute();
$rows = $stmtList->fetchAll();

// -------------------------------------------------------
// 5. Daten aufbereiten
// -------------------------------------------------------
$artifacts = array_map(function (array $row): array {
    return [
        'id'            => (int)    $row['id'],
        'title'         => $row['title'],
        'slug'          => $row['slug'],
        'description'   => $row['description'],
        'year'          => $row['year']   ? (int) $row['year']   : null,
        'decade'        => $row['decade'] ? (int) $row['decade'] : null,
        'artifact_type' => $row['artifact_type'],
        'thumb_url'     => $row['thumb_url'],
        'image_url'     => $row['image_url'],
        'source'        => $row['source'],
        'created_at'    => $row['created_at'],
        'location'      => $row['loc_id'] ? [
            'id'   => (int) $row['loc_id'],
            'name' => $row['loc_name'],
        ] : null,
    ];
}, $rows);

// -------------------------------------------------------
// 6. Pagination-Meta berechnen & antworten
// -------------------------------------------------------
$lastPage = (int) ceil($total / $perPage);

json_response([
    'data' => $artifacts,
    'meta' => [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $page,
        'last_page'    => $lastPage,
        'has_more'     => $page < $lastPage,
    ],
    'filters' => [          // Aktive Filter zurückspiegeln (nützlich für Vue)
        'q'           => $q        ?: null,
        'type'        => $type     ?: null,
        'year'        => $year     ?: null,
        'decade'      => $decade   ?: null,
        'location_id' => $locationId ?: null,
        'person_id'   => $personId   ?: null,
        'sort'        => $sortKey,
    ],
]);

