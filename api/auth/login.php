<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort('Method Not Allowed', 405);
}

$body = json_decode(file_get_contents('php://input'), true);
$username = trim($body['username'] ?? '');
$password = trim($body['password'] ?? '');

if (!$username || !$password) {
    abort('Benutzername und Passwort erforderlich.', 400);
}

$db   = get_db();
$stmt = $db->prepare('SELECT id, username, email, password, role FROM users WHERE username = :u LIMIT 1');
$stmt->execute([':u' => $username]);
$user = $stmt->fetch();

// Timing-Angriffe verhindern: immer verifizieren, auch wenn kein User gefunden
$hash = $user['password'] ?? '$2y$10$invalidhashpadding000000000000000000000000000000000000';
if (!$user || !password_verify($password, $hash)) {
    abort('Ungültige Anmeldedaten.', 401);
}

// Login-Zeitpunkt aktualisieren
$db->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')
   ->execute([':id' => $user['id']]);

$payload = [
    'sub'      => $user['id'],
    'username' => $user['username'],
    'role'     => $user['role'],
    'iat'      => time(),
    'exp'      => time() + JWT_TTL,
];

json_response([
    'token' => jwt_encode($payload),
    'user'  => [
        'id'       => $user['id'],
        'username' => $user['username'],
        'email'    => $user['email'],
        'role'     => $user['role'],
    ],
    'expires_in' => JWT_TTL,
]);