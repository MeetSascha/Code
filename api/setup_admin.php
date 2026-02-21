<?php
// setup_admin.php — nach Ausführung SOFORT löschen!
require_once 'api/config.php';

$db   = get_db();
$hash = password_hash('DeinSicheresPasswort!', PASSWORD_BCRYPT);

$db->prepare("
    INSERT INTO users (username, email, password, role)
    VALUES ('admin', 'deine@email.de', :hash, 'admin')
")->execute([':hash' => $hash]);

echo "Admin angelegt. Diese Datei jetzt löschen!\n";