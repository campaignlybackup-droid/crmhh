<?php
require __DIR__ . '/core/bootstrap.php';
$stmt = Database::pdo()->query("DESCRIBE leads;");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . "\n";
}
