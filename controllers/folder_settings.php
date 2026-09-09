<?php
Auth::requireRole('founder');

$action = $_GET['action'] ?? 'index';
$folderId = (int)($_GET['folder_id'] ?? 0);

if (!$folderId) {
    Flash::error('Folder not found.');
    redirect(url('leads'));
}

$folder = Database::one('SELECT * FROM lead_folders WHERE id = ?', [$folderId]);
if (!$folder) {
    Flash::error('Folder not found.');
    redirect(url('leads'));
}

switch ($action) {
    case 'index': {
        $statuses = Database::all('SELECT * FROM lead_statuses WHERE folder_id = ? ORDER BY sort_order ASC', [$folderId]);
        $customFields = Database::all('SELECT * FROM folder_custom_fields WHERE folder_id = ? ORDER BY sort_order ASC', [$folderId]);
        render_page('folder_settings/index', compact('folder', 'statuses', 'customFields'), 'Settings: ' . $folder['name']);
        break;
    }

    case 'save_status': {
        csrf_check_or_die();
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $color = trim($_POST['color'] ?? '#6c757d');
        $is_won = (int)isset($_POST['is_won']);
        $is_lost = (int)isset($_POST['is_lost']);
        $is_default = (int)isset($_POST['is_default']);

        if (!$name || !$slug) {
            Flash::error('Name and slug are required.');
            redirect(url('folder_settings', ['folder_id' => $folderId]));
        }

        $exists = Database::scalar('SELECT 1 FROM lead_statuses WHERE folder_id = ? AND slug = ? AND id != ?', [$folderId, $slug, $id]);
        if ($exists) {
            Flash::error('A status with this slug already exists in this folder.');
            redirect(url('folder_settings', ['folder_id' => $folderId]));
        }

        if ($is_default) {
            Database::run('UPDATE lead_statuses SET is_default = 0 WHERE folder_id = ?', [$folderId]);
        }

        if ($id) {
            Database::run('UPDATE lead_statuses SET name=?, slug=?, color=?, is_won=?, is_lost=?, is_default=? WHERE id=? AND folder_id=?', [$name, $slug, $color, $is_won, $is_lost, $is_default, $id, $folderId]);
            Flash::success('Status updated.');
        } else {
            $maxSort = (int)Database::scalar('SELECT MAX(sort_order) FROM lead_statuses WHERE folder_id = ?', [$folderId]);
            Database::run('INSERT INTO lead_statuses (folder_id, name, slug, color, sort_order, is_won, is_lost, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$folderId, $name, $slug, $color, $maxSort + 1, $is_won, $is_lost, $is_default]);
            Flash::success('Status added.');
        }
        redirect(url('folder_settings', ['folder_id' => $folderId]));
    }

    case 'delete_status': {
        csrf_check_or_die();
        $id = (int)$_POST['id'];
        $inUse = (int)Database::scalar('SELECT COUNT(*) FROM leads WHERE status_id = ?', [$id]);
        if ($inUse > 0) {
            Flash::error('Cannot delete this status because ' . $inUse . ' leads are currently using it. Reassign them first.');
        } else {
            Database::run('DELETE FROM lead_statuses WHERE id = ? AND folder_id = ?', [$id, $folderId]);
            Flash::success('Status deleted.');
        }
        redirect(url('folder_settings', ['folder_id' => $folderId]));
    }

    case 'save_field': {
        csrf_check_or_die();
        $id = (int)($_POST['id'] ?? 0);
        $fieldName = trim($_POST['field_name'] ?? '');
        $fieldType = $_POST['field_type'] ?? 'text';
        $options = trim($_POST['options'] ?? '');

        if (!$fieldName) {
            Flash::error('Field name is required.');
            redirect(url('folder_settings', ['folder_id' => $folderId]));
        }

        $optsJson = null;
        if ($fieldType === 'select' && $options) {
            $optsArr = array_map('trim', explode(',', $options));
            $optsJson = json_encode(array_filter($optsArr));
        }

        if ($id) {
            Database::run('UPDATE folder_custom_fields SET field_name=?, field_type=?, options=? WHERE id=? AND folder_id=?', [$fieldName, $fieldType, $optsJson, $id, $folderId]);
            Flash::success('Custom field updated.');
        } else {
            $maxSort = (int)Database::scalar('SELECT MAX(sort_order) FROM folder_custom_fields WHERE folder_id = ?', [$folderId]);
            Database::run('INSERT INTO folder_custom_fields (folder_id, field_name, field_type, options, sort_order) VALUES (?, ?, ?, ?, ?)', [$folderId, $fieldName, $fieldType, $optsJson, $maxSort + 1]);
            Flash::success('Custom field added.');
        }
        redirect(url('folder_settings', ['folder_id' => $folderId]));
    }

    case 'delete_field': {
        csrf_check_or_die();
        $id = (int)$_POST['id'];
        Database::run('DELETE FROM folder_custom_fields WHERE id = ? AND folder_id = ?', [$id, $folderId]);
        Flash::success('Custom field deleted.');
        redirect(url('folder_settings', ['folder_id' => $folderId]));
    }

    default:
        redirect(url('folder_settings', ['folder_id' => $folderId]));
}
