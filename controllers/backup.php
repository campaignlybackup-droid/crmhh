<?php

Permission::requireRole('founder');

$action = $_GET['action'] ?? 'download';

switch ($action) {
    case 'download': {
        $zip = new ZipArchive();
        $tmpFile = tempnam(sys_get_temp_dir(), 'crm_backup');
        
        if ($zip->open($tmpFile, ZipArchive::CREATE) !== true) {
            fatal_error('Could not create backup zip file.');
        }

        // Fetch all tables
        $tables = Database::all('SHOW TABLES');
        $dbName = config('db')['name'];
        $tableKey = 'Tables_in_' . $dbName;
        
        if (empty($tables) || !isset($tables[0][$tableKey])) {
             // Fallback if key is somehow different
             $tableKey = array_key_first($tables[0]);
        }

        foreach ($tables as $tRow) {
            $tableName = $tRow[$tableKey];
            
            $rows = Database::all("SELECT * FROM `$tableName`");
            
            if (empty($rows)) {
                $zip->addFromString($tableName . '.csv', "No data\n");
                continue;
            }
            
            $csv = fopen('php://temp', 'r+');
            
            // Add headers
            fputcsv($csv, array_keys($rows[0]));
            
            // Add rows
            foreach ($rows as $row) {
                fputcsv($csv, $row);
            }
            
            rewind($csv);
            $csvString = stream_get_contents($csv);
            fclose($csv);
            
            $zip->addFromString($tableName . '.csv', $csvString);
        }

        $zip->close();
        
        $filename = 'crm_full_backup_' . date('Ymd_His') . '.zip';
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpFile));
        
        readfile($tmpFile);
        unlink($tmpFile);
        exit;
    }
    
    default:
        redirect(url('dashboard'));
}
