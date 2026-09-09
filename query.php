<?php
require __DIR__ . '/core/bootstrap.php';
$p = Database::all('SELECT * FROM permissions WHERE `group` = "proposals"');
print_r($p);
