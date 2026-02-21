<?php
// Fehler nie an den Client ausgeben (nur loggen)
ini_set('display_errors', 0);
error_reporting(E_ALL);

define('DB_HOST', 'lindlar-anno-dazumal.de');
define('DB_NAME', 'd0464739');
define('DB_USER', 'd0464739');
define('DB_PASS', 'qp#APCJdKBTWjoV2)!U4');
define('DB_CHARSET', 'utf8mb4');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}