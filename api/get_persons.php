<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') abort('Method Not Allowed', 405);

$q  = trim($_GET['q'] ?? '');
$db = get_db();

if ($q !== '') {
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, birth_year, death_year
        FROM persons
        WHERE first_name LIKE :q OR last_name LIKE :q
        ORDER BY last_name, first_name
        LIMIT 20
    ");
    $stmt->execute([':q' => '%' . $q . '%']);
} else {
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, birth_year, death_year
        FROM persons
        ORDER BY last_name, first_name
        LIMIT 100
    ");
    $stmt->execute();
}

$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    $r['id']         = (int)   $r['id'];
    $r['birth_year'] = $r['birth_year'] ? (int) $r['birth_year'] : null;
    $r['death_year'] = $r['death_year'] ? (int) $r['death_year'] : null;
}
unset($r);

json_response(['data' => $rows]);