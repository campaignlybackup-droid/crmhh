<?php

Permission::requireAny(['leads.view', 'leads.view_all']);

$action = $_GET['action'] ?? 'index';

switch ($action) {

    case 'view': {
        $id = (int)($_GET['id'] ?? 0);
        if (!LeadModel::canAccess($id)) Permission::deny();
        $lead = LeadModel::find($id);
        if (!$lead) fatal_error('Lead not found.');
        $timeline = ActivityModel::timeline('lead', $id);
        $statuses = LeadModel::statuses();
        $users = UserModel::activeSelectList();
        render_page('leads/view', compact('lead', 'timeline', 'statuses', 'users'), 'Lead ' . $lead['lead_code']);
        break;
    }

    case 'create': {
        Permission::require('leads.create');
        $statuses = LeadModel::statuses();
        $users = UserModel::activeSelectList();
        render_page('leads/form', ['lead' => null, 'statuses' => $statuses, 'users' => $users], 'New Lead');
        break;
    }

    case 'store': {
        Permission::require('leads.create');
        csrf_check_or_die();
        $v = Validator::make($_POST)->required('name', 'Name')->maxLength('name', 120, 'Name')->email('email', 'Email');
        if ($v->fails()) {
            Flash::error($v->firstError());
            redirect(url('leads', ['action' => 'create']));
        }
        $dup = LeadModel::findByPhoneOrEmail($_POST['phone'] ?? null, $_POST['email'] ?? null);
        if ($dup) {
            Flash::error('A lead with this phone or email already exists: ' . $dup['lead_code'] . ' (' . $dup['name'] . ')');
            redirect(url('leads', ['action' => 'create']));
        }
        $assignedUserId = $_POST['assigned_user_id'] ?? null;
        if ($assignedUserId && !Permission::has('leads.assign')) $assignedUserId = null;
        $id = LeadModel::create([
            'name' => trim($_POST['name']), 'phone' => trim($_POST['phone'] ?? ''), 'email' => trim($_POST['email'] ?? ''),
            'company' => trim($_POST['company'] ?? ''), 'source' => trim($_POST['source'] ?? ''),
            'status_id' => $_POST['status_id'] ?? null, 'assigned_user_id' => $assignedUserId ?: null,
            'next_followup_date' => $_POST['next_followup_date'] ?? null, 'notes' => trim($_POST['notes'] ?? ''),
        ]);
        Flash::success('Lead created successfully.');
        redirect(url('leads', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'edit': {
        $id = (int)($_GET['id'] ?? 0);
        if (!LeadModel::canAccess($id)) Permission::deny();
        Permission::require('leads.edit');
        $lead = LeadModel::find($id);
        if (!$lead) fatal_error('Lead not found.');
        $statuses = LeadModel::statuses();
        $users = UserModel::activeSelectList();
        render_page('leads/form', compact('lead', 'statuses', 'users'), 'Edit Lead');
        break;
    }

    case 'update': {
        $id = (int)($_POST['id'] ?? 0);
        if (!LeadModel::canAccess($id)) Permission::deny();
        Permission::require('leads.edit');
        csrf_check_or_die();
        $v = Validator::make($_POST)->required('name', 'Name')->email('email', 'Email');
        if ($v->fails()) {
            Flash::error($v->firstError());
            redirect(url('leads', ['action' => 'edit', 'id' => $id]));
        }
        LeadModel::update($id, [
            'name' => trim($_POST['name']), 'phone' => trim($_POST['phone'] ?? ''), 'email' => trim($_POST['email'] ?? ''),
            'company' => trim($_POST['company'] ?? ''), 'source' => trim($_POST['source'] ?? ''),
            'status_id' => $_POST['status_id'] ?? null, 'next_followup_date' => $_POST['next_followup_date'] ?? null,
            'notes' => trim($_POST['notes'] ?? ''),
        ]);
        Flash::success('Lead updated.');
        redirect(url('leads', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'delete': {
        $id = (int)($_POST['id'] ?? 0);
        if (!LeadModel::canAccess($id)) Permission::deny();
        Permission::require('leads.delete');
        csrf_check_or_die();
        LeadModel::softDelete($id);
        Flash::success('Lead deleted.');
        redirect(url('leads'));
        break;
    }

    case 'assign': {
        $id = (int)($_POST['id'] ?? 0);
        if (!LeadModel::canAccess($id)) Permission::deny();
        Permission::require('leads.assign');
        csrf_check_or_die();
        $newUserId = (int)($_POST['assigned_user_id'] ?? 0);
        if ($newUserId) {
            LeadModel::assign($id, $newUserId, $_POST['note'] ?? null);
            Flash::success('Lead reassigned.');
        }
        redirect(url('leads', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'status': {
        $id = (int)($_POST['id'] ?? 0);
        if (!LeadModel::canAccess($id)) Permission::deny();
        Permission::require('leads.edit');
        csrf_check_or_die();
        $statusId = (int)($_POST['status_id'] ?? 0);
        if ($statusId) LeadModel::changeStatus($id, $statusId, $_POST['note'] ?? null);
        Flash::success('Status updated.');
        redirect(url('leads', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'followup': {
        $id = (int)($_POST['id'] ?? 0);
        if (!LeadModel::canAccess($id)) Permission::deny();
        csrf_check_or_die();
        $note = trim($_POST['note'] ?? '');
        if ($note !== '') {
            LeadModel::addFollowUp($id, $note, $_POST['next_followup_date'] ?? null);
            Flash::success('Follow-up added.');
        }
        redirect(url('leads', ['action' => 'view', 'id' => $id]));
        break;
    }

    case 'import': {
        Permission::require('leads.import');
        cleanup_stale_imports();
        render_page('leads/import', [], 'Import Leads');
        break;
    }

    case 'import_preview': {
        Permission::require('leads.import');
        csrf_check_or_die();
        if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            Flash::error('Please choose a valid CSV file to upload.');
            redirect(url('leads', ['action' => 'import']));
        }
        $file = $_FILES['csv_file'];
        if ($file['size'] > 5 * 1024 * 1024) {
            Flash::error('File is too large (max 5 MB).');
            redirect(url('leads', ['action' => 'import']));
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            Flash::error('Only .csv files are supported.');
            redirect(url('leads', ['action' => 'import']));
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowedMimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        if (!in_array($mime, $allowedMimes, true)) {
            Flash::error('The uploaded file does not look like a valid CSV file.');
            redirect(url('leads', ['action' => 'import']));
        }

        $uploadDir = __DIR__ . '/../uploads';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $storedName = 'import_' . bin2hex(random_bytes(8)) . '.csv';
        $storedPath = $uploadDir . '/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
            Flash::error('Could not process the uploaded file.');
            redirect(url('leads', ['action' => 'import']));
        }

        [$headers, $rows] = CsvImporter::read($storedPath);
        if (empty($headers) || empty($rows)) {
            @unlink($storedPath);
            Flash::error('The CSV file appears to be empty.');
            redirect(url('leads', ['action' => 'import']));
        }
        $mapping = CsvImporter::detectMapping($headers);

        $_SESSION['_import'] = ['file' => $storedName, 'headers' => $headers, 'mapping' => $mapping, 'original_name' => basename($file['name'])];

        $preview = build_import_preview($headers, $rows, $mapping);
        render_page('leads/import_preview', compact('headers', 'mapping', 'preview', 'rows'), 'Confirm Import');
        break;
    }

    case 'import_confirm': {
        Permission::require('leads.import');
        csrf_check_or_die();
        if (empty($_SESSION['_import'])) {
            Flash::error('Import session expired. Please upload the file again.');
            redirect(url('leads', ['action' => 'import']));
        }
        $imp = $_SESSION['_import'];
        $storedPath = __DIR__ . '/../uploads/' . $imp['file'];
        if (!file_exists($storedPath)) {
            Flash::error('Import session expired. Please upload the file again.');
            redirect(url('leads', ['action' => 'import']));
        }

        $mapping = [];
        foreach (['name', 'email', 'phone', 'company', 'source', 'status', 'notes'] as $field) {
            $val = $_POST['map'][$field] ?? '';
            $mapping[$field] = ($val === '' ? null : (int)$val);
        }

        [$headers, $rows] = CsvImporter::read($storedPath);
        $statuses = LeadModel::statuses();
        $statusByName = [];
        foreach ($statuses as $s) { $statusByName[strtolower($s['name'])] = $s['id']; }
        $defaultStatus = LeadModel::defaultStatusId();

        $result = ['total' => count($rows), 'new' => 0, 'duplicate' => 0, 'invalid' => 0];
        $batchAssignee = !empty($_POST['assign_to']) && Permission::has('leads.assign') ? (int)$_POST['assign_to'] : null;

        Database::beginTransaction();
        try {
            foreach ($rows as $row) {
                $name = CsvImporter::value($row, $mapping['name']);
                $phone = CsvImporter::value($row, $mapping['phone']);
                $email = CsvImporter::value($row, $mapping['email']);
                $company = CsvImporter::value($row, $mapping['company']);
                $source = CsvImporter::value($row, $mapping['source']) ?? 'CSV Import';
                $statusRaw = CsvImporter::value($row, $mapping['status']);
                $notes = CsvImporter::value($row, $mapping['notes']);

                if (!$name || (!$phone && !valid_email($email))) {
                    $result['invalid']++;
                    continue;
                }
                $dup = LeadModel::findByPhoneOrEmail($phone, $email);
                if ($dup) {
                    $result['duplicate']++;
                    continue;
                }
                $statusId = $defaultStatus;
                if ($statusRaw && isset($statusByName[strtolower($statusRaw)])) {
                    $statusId = $statusByName[strtolower($statusRaw)];
                }
                LeadModel::create([
                    'name' => $name, 'phone' => $phone, 'email' => $email, 'company' => $company,
                    'source' => $source, 'status_id' => $statusId, 'assigned_user_id' => $batchAssignee,
                    'next_followup_date' => null, 'notes' => $notes,
                ]);
                $result['new']++;
            }

            Database::run(
                'INSERT INTO lead_import_batches (imported_by, filename, total_rows, new_count, updated_count, duplicate_count, invalid_count, created_at)
                 VALUES (?,?,?,?,0,?,?,NOW())',
                [Auth::id(), $imp['original_name'], $result['total'], $result['new'], $result['duplicate'], $result['invalid']]
            );
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            @unlink($storedPath);
            unset($_SESSION['_import']);
            app_log('CSV import failed: ' . $e->getMessage());
            fatal_error('The import could not be completed. No leads were added.');
        }

        @unlink($storedPath);
        unset($_SESSION['_import']);
        Flash::success("Import complete — {$result['new']} new leads, {$result['duplicate']} duplicates skipped, {$result['invalid']} invalid rows skipped out of {$result['total']} total.");
        redirect(url('leads'));
        break;
    }

    case 'export': {
        Permission::require('leads.export');
        $filters = leads_filters_from_request();
        [$rows] = LeadModel::paginate(1, 100000, $filters);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads_export_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Lead ID', 'Name', 'Phone', 'Email', 'Company', 'Source', 'Status', 'Assigned To', 'Next Follow-up', 'Created']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['lead_code'], $r['name'], $r['phone'], $r['email'], $r['company'], $r['source'], $r['status_name'], $r['assigned_name'], $r['next_followup_date'], $r['created_at']]);
        }
        fclose($out);
        exit;
    }

    case 'api_create': {
        Permission::require('leads.create');
        $json = json_decode(file_get_contents('php://input'), true);
        if (!$json) { echo json_encode(['success' => false, 'error' => 'Invalid JSON']); exit; }
        
        $v = Validator::make($json)->required('name', 'Name')->email('email', 'Email');
        if ($v->fails()) { echo json_encode(['success' => false, 'error' => $v->firstError()]); exit; }
        
        $dup = LeadModel::findByPhoneOrEmail($json['phone'] ?? null, $json['email'] ?? null);
        if ($dup) { echo json_encode(['success' => false, 'error' => 'Lead with this phone/email already exists.']); exit; }
        
        $assignedUserId = Auth::id(); // Default to self
        if (!empty($json['assigned_user_id']) && Permission::has('leads.assign')) {
            $assignedUserId = (int)$json['assigned_user_id'];
        }
        $folderId = !empty($json['folder_id']) ? (int)$json['folder_id'] : null;
        if ($folderId && !Auth::hasRole('founder')) {
            $hasAccess = (int)Database::scalar('SELECT COUNT(*) FROM lead_folder_users WHERE folder_id = ? AND user_id = ?', [$folderId, Auth::id()]);
            if (!$hasAccess) { echo json_encode(['success' => false, 'error' => 'Access denied to this folder']); exit; }
        }
        
        $id = LeadModel::create([
            'name' => trim($json['name']), 'phone' => trim($json['phone'] ?? ''), 'email' => trim($json['email'] ?? ''),
            'company' => trim($json['company'] ?? ''), 'source' => trim($json['source'] ?? ''),
            'status_id' => $json['status_id'] ?? null, 'assigned_user_id' => $assignedUserId,
            'next_followup_date' => $json['next_followup_date'] ?: null, 'notes' => '',
            'folder_id' => $folderId
        ]);
        
        $lead = LeadModel::find($id);
        echo json_encode(['success' => true, 'lead' => $lead]);
        exit;
    }

    case 'api_update': {
        $json = json_decode(file_get_contents('php://input'), true);
        $id = (int)($json['id'] ?? 0);
        if (!$id || !LeadModel::canAccess($id)) { echo json_encode(['success' => false, 'error' => 'Access denied']); exit; }
        Permission::require('leads.edit');
        
        $v = Validator::make($json)->required('name', 'Name')->email('email', 'Email');
        if ($v->fails()) { echo json_encode(['success' => false, 'error' => $v->firstError()]); exit; }
        
        $dup = LeadModel::findByPhoneOrEmail($json['phone'] ?? null, $json['email'] ?? null);
        if ($dup && (int)$dup['id'] !== $id) { echo json_encode(['success' => false, 'error' => 'Phone/email belongs to another lead.']); exit; }
        
        LeadModel::update($id, [
            'name' => trim($json['name']), 'phone' => trim($json['phone'] ?? ''), 'email' => trim($json['email'] ?? ''),
            'company' => trim($json['company'] ?? ''), 'source' => trim($json['source'] ?? ''),
            'status_id' => $json['status_id'] ?? null, 'next_followup_date' => $json['next_followup_date'] ?: null,
            'notes' => $json['notes'] ?? null
        ]);
        
        $lead = LeadModel::find($id);
        echo json_encode(['success' => true, 'lead' => $lead]);
        exit;
    }
    
    case 'api_create_folder': {
        Permission::require('leads.assign'); // Only founder
        $json = json_decode(file_get_contents('php://input'), true);
        $name = trim($json['name'] ?? '');
        if (!$name) { echo json_encode(['success' => false, 'error' => 'Folder name is required']); exit; }
        
        Database::run('INSERT INTO lead_folders (name, created_by) VALUES (?, ?)', [$name, Auth::id()]);
        $folderId = Database::lastInsertId();
        
        if (!empty($json['users']) && is_array($json['users'])) {
            foreach ($json['users'] as $uid) {
                Database::run('INSERT IGNORE INTO lead_folder_users (folder_id, user_id) VALUES (?, ?)', [$folderId, (int)$uid]);
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    default: {
        $filters = leads_filters_from_request();
        $isManagerOrFounder = Auth::hasRole('founder') || Auth::hasRole('manager');
        $hasActiveFilters = !empty($filters['status_id']) || !empty($filters['source']) || !empty($filters['followup']) || !empty($filters['search']);
        
        // Show folder view for admins if they aren't looking for a specific list
        if ($isManagerOrFounder && empty($filters['assigned_user_id']) && empty($filters['folder_id']) && !$hasActiveFilters) {
            $folders = LeadModel::getFolderStats(Auth::id());
            $customFolders = LeadModel::getCustomFolders(Auth::id());
            render_page('leads/folders', compact('folders', 'customFolders'), 'Lead Folders');
            break;
        }

        $page = current_page_int();
        [$rows, $p] = LeadModel::paginate($page, 25, $filters);
        $statuses = LeadModel::statuses();
        $users = UserModel::activeSelectList();
        $sources = LeadModel::distinctSources();
        $dashboardStats = LeadModel::getDashboardStats($filters);
        
        render_page('leads/list', compact('rows', 'p', 'statuses', 'users', 'sources', 'filters', 'dashboardStats'), 'Leads');
        break;
    }
}

function cleanup_stale_imports(): void
{
    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) return;
    foreach (glob($dir . '/import_*.csv') ?: [] as $file) {
        if (is_file($file) && filemtime($file) < time() - 3600) {
            @unlink($file);
        }
    }
}

function leads_filters_from_request(): array
{
    return [
        'status_id' => $_GET['status_id'] ?? '',
        'assigned_user_id' => $_GET['assigned_user_id'] ?? '',
        'source' => $_GET['source'] ?? '',
        'followup' => $_GET['followup'] ?? '',
        'search' => trim($_GET['search'] ?? ''),
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'folder_id' => $_GET['folder_id'] ?? '',
    ];
}

function build_import_preview(array $headers, array $rows, array $mapping): array
{
    $sample = array_slice($rows, 0, 8);
    $total = count($rows);
    $invalid = 0;
    $duplicates = 0;
    foreach ($rows as $row) {
        $name = CsvImporter::value($row, $mapping['name']);
        $phone = CsvImporter::value($row, $mapping['phone']);
        $email = CsvImporter::value($row, $mapping['email']);
        if (!$name || (!$phone && !valid_email($email))) { $invalid++; continue; }
        if (LeadModel::findByPhoneOrEmail($phone, $email)) $duplicates++;
    }
    return [
        'total' => $total,
        'invalid' => $invalid,
        'duplicates' => $duplicates,
        'new' => $total - $invalid - $duplicates,
        'sample' => $sample,
    ];
}
