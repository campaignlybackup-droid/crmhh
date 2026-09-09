<?php

Auth::requireLogin();
if (!Auth::hasRole('founder')) Permission::deny();

$action = $_GET['action'] ?? 'download';

switch ($action) {
    case 'download': {
        // Check ZipArchive is available
        if (!class_exists('ZipArchive')) {
            fatal_error('ZipArchive extension is not available on this server. Please enable php-zip.');
        }

        $zip = new ZipArchive();
        $tmpFile = tempnam(sys_get_temp_dir(), 'crm_backup_');

        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            fatal_error('Could not create backup zip file. Check server temp directory permissions.');
        }

        // Fetch all tables
        $tables = Database::all('SHOW TABLES');

        if (empty($tables)) {
            $zip->addFromString('README.txt', "Database appears to be empty.\n");
        } else {
            // Determine the column key dynamically (handles any DB name)
            $tableKey = array_key_first($tables[0]);

            foreach ($tables as $tRow) {
                $tableName = $tRow[$tableKey];

                $rows = Database::all("SELECT * FROM `$tableName`");

                if (empty($rows)) {
                    $zip->addFromString($tableName . '.csv', "No data\n");
                    continue;
                }

                $csv = fopen('php://temp', 'r+');

                // Headers
                fputcsv($csv, array_keys($rows[0]));

                // Rows
                foreach ($rows as $row) {
                    fputcsv($csv, $row);
                }

                rewind($csv);
                $csvString = stream_get_contents($csv);
                fclose($csv);

                $zip->addFromString($tableName . '.csv', $csvString);
            }
        }

        $zip->close();

        $filename = 'crm_full_backup_' . date('Ymd_His') . '.zip';

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpFile));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');

        readfile($tmpFile);
        @unlink($tmpFile);
        exit;
    }

    default:
        redirect(url('dashboard'));
}
