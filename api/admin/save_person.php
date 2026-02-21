<?php
require_once __DIR__ . '/_auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') abort('Method Not Allowed', 405);

$body      = json_decode(file_get_contents('php://input'), true);
$firstName = trim($body['first_name'] ?? '');
$lastName  = trim($body['last_name']  ?? '');

if (!$firstName && !$lastName) abort('Vor- oder Nachname erforderlich.', 422);

$db        = get_db();
$id        = isset($body['id']) && $body['id'] !== null ? (int) $body['id'] : null;
$birthYear = isset($body['birth_year']) && $body['birth_year'] ? (int) $body['birth_year'] : null;
$deathYear = isset($body['death_year']) && $body['death_year'] ? (int) $body['death_year'] : null;
$biography = trim($body['biography'] ?? '') ?: null;

if (!$id) {
    // CREATE
    $stmt = $db->prepare("
        INSERT INTO persons (first_name, last_name, birth_year, death_year, biography)
        VALUES (:first, :last, :birth, :death, :bio)
    ");
    $stmt->execute([
        ':first' => $firstName,
        ':last'  => $lastName,
        ':birth' => $birthYear,
        ':death' => $deathYear,
        ':bio'   => $biography,
    ]);
    $id = (int) $db->lastInsertId();
} else {
    // UPDATE
    $stmt = $db->prepare("
        UPDATE persons SET first_name = :first, last_name = :last,
               birth_year = :birth, death_year = :death, biography = :bio
        WHERE id = :id
    ");
    $stmt->execute([
        ':first' => $firstName,
        ':last'  => $lastName,
        ':birth' => $birthYear,
        ':death' => $deathYear,
        ':bio'   => $biography,
        ':id'    => $id,
    ]);
}

json_response(['success' => true, 'id' => $id]);