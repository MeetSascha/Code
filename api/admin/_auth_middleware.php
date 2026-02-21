<?php
// Wird am Anfang jedes Admin-Endpoints per require_once eingebunden.
// Bricht mit 401 ab, wenn kein gültiger Token vorhanden.
// Stellt $CURRENT_USER global bereit.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

// OPTIONS-Preflight (CORS) direkt beantworten
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}

$token = get_bearer_token();
if (!$token) {
    abort('Nicht authentifiziert.', 401);
}

$payload = jwt_decode($token);
if (!$payload) {
    abort('Token ungültig oder abgelaufen.', 401);
}

// Optional: Rolle prüfen
// if ($payload['role'] !== 'admin') abort('Keine Berechtigung.', 403);

$CURRENT_USER = $payload;