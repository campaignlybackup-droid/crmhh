<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo, $user_id);
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);

$error = '';
$success = '';
$importPreview = null;

// Function to generate and update Lead Serial
function assignLeadSerial($pdo, $lead_id) {
    $serial = 'LD-' . str_pad($lead_id, 6, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE leads SET lead_serial = ? WHERE id = ?")->execute([$serial, $lead_id]);
    return $serial;
}

// Handle CRUD Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $status = $_POST['status'] ?? 'New';
        $deal_value = $_POST['deal_value'] ?? 0;
        
        $ins = $pdo->prepare("INSERT INTO leads (lead_serial, name, email, phone, status, deal_value, assigned_to) VALUES ('TEMP', ?, ?, ?, ?, ?, ?)");
        $ins->execute([$name, $email, $phone, $status, $deal_value, $user_id]);
        $new_id = $pdo->lastInsertId();
        assignLeadSerial($pdo, $new_id);
        
        logActivity($pdo, 'Created Lead', 'Lead', $new_id);
        $_SESSION['flash_success'] = "Lead created successfully.";
        header("Location: leads.php");
        exit;
    }
    
    if ($action === 'edit') {
        $id = $_POST['id'];
        if (!canAccessEntity($pdo, $user_id, 'Lead', $id)) {
            $_SESSION['flash_error'] = "403 Forbidden: You do not have permission to edit this lead.";
            header("Location: leads.php");
            exit;
        }
        
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $status = $_POST['status'];
        $deal_value = $_POST['deal_value'] ?? 0;
        
        $upd = $pdo->prepare("UPDATE leads SET name=?, email=?, phone=?, status=?, deal_value=? WHERE id=?");
        $upd->execute([$name, $email, $phone, $status, $deal_value, $id]);
        
        logActivity($pdo, 'Updated Lead', 'Lead', $id);
        $_SESSION['flash_success'] = "Lead updated successfully.";
        header("Location: leads.php");
        exit;
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        if (!canAccessEntity($pdo, $user_id, 'Lead', $id)) {
            $_SESSION['flash_error'] = "403 Forbidden: You do not have permission to delete this lead.";
            header("Location: leads.php");
            exit;
        }
        
        $pdo->prepare("UPDATE leads SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
        logActivity($pdo, 'Deleted Lead', 'Lead', $id);
        $_SESSION['flash_success'] = "Lead securely soft-deleted.";
        header("Location: leads.php");
        exit;
    }
}

// Smart CSV Importer: Step 1 (Upload) -> Step 2 (Preview)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    if (is_uploaded_file($file)) {
        $handle = fopen($file, "r");
        if ($handle !== FALSE) {
            $importHeaders = fgetcsv($handle);
            $previewRows = [];
            
            // Read all rows for duplicate checking during preview
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (array_filter($data)) {
                    $previewRows[] = $data;
                }
            }
            fclose($handle);
            
            // Regex Auto-Map
            $mappedColumns = [];
            foreach ($importHeaders as $index => $header) {
                if (preg_match('/name|full name|contact/i', $header)) $mappedColumns[$index] = 'name';
                elseif (preg_match('/email|e-mail/i', $header)) $mappedColumns[$index] = 'email';
                elseif (preg_match('/phone|mobile|contact number/i', $header)) $mappedColumns[$index] = 'phone';
                elseif (preg_match('/status|stage/i', $header)) $mappedColumns[$index] = 'status';
                elseif (preg_match('/value|deal/i', $header)) $mappedColumns[$index] = 'deal_value';
                else $mappedColumns[$index] = 'skip';
            }
            
            // Duplicate Check Engine (Check against DB)
            foreach ($previewRows as $rowIndex => $row) {
                $phone = '';
                $email = '';
                foreach ($row as $colIdx => $val) {
                    if (isset($mappedColumns[$colIdx])) {
                        if ($mappedColumns[$colIdx] === 'phone') $phone = trim($val);
                        if ($mappedColumns[$colIdx] === 'email') $email = trim($val);
                    }
                }
                
                $isDuplicate = false;
                $existingId = '';
                
                // Rule: Check Phone first, then Email
                if (!empty($phone)) {
                    $stmt = $pdo->prepare("SELECT lead_serial FROM leads WHERE phone = ? AND deleted_at IS NULL LIMIT 1");
                    $stmt->execute([$phone]);
                    $res = $stmt->fetchColumn();
                    if ($res) { $isDuplicate = true; $existingId = $res; }
                }
                if (!$isDuplicate && !empty($email)) {
                    $stmt = $pdo->prepare("SELECT lead_serial FROM leads WHERE email = ? AND deleted_at IS NULL LIMIT 1");
                    $stmt->execute([$email]);
                    $res = $stmt->fetchColumn();
                    if ($res) { $isDuplicate = true; $existingId = $res; }
                }
                
                $previewRows[$rowIndex]['_duplicate'] = $isDuplicate;
                $previewRows[$rowIndex]['_existing_serial'] = $existingId;
            }
            
            $importPreview = [
                'headers' => $importHeaders,
                'rows' => $previewRows,
                'mapping' => $mappedColumns,
                'tmp_file' => base64_encode(file_get_contents($file))
            ];
        }
    }
}

// Smart CSV Importer: Step 3 (Execute)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    $fileData = base64_decode($_POST['tmp_file']);
    $mapping = $_POST['mapping'];
    
    $tmp = tmpfile();
    fwrite($tmp, $fileData);
    fseek($tmp, 0);
    fgetcsv($tmp); // skip header
    
    $imported = 0;
    $skipped = 0;
    
    while (($data = fgetcsv($tmp)) !== FALSE) {
        if (!array_filter($data)) continue;
        
        $row = ['name'=>'', 'email'=>'', 'phone'=>'', 'status'=>'New', 'deal_value'=>0];
        foreach ($data as $index => $val) {
            if (isset($mapping[$index]) && $mapping[$index] !== 'skip') {
                $row[$mapping[$index]] = trim($val);
            }
        }
        if (empty($row['name'])) continue;
        
        $isDuplicate = false;
        if (!empty($row['phone'])) {
            $stmt = $pdo->prepare("SELECT id FROM leads WHERE phone = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$row['phone']]);
            if ($stmt->fetchColumn()) $isDuplicate = true;
        }
        if (!$isDuplicate && !empty($row['email'])) {
            $stmt = $pdo->prepare("SELECT id FROM leads WHERE email = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$row['email']]);
            if ($stmt->fetchColumn()) $isDuplicate = true;
        }
        
        if ($isDuplicate) {
            $skipped++;
            continue;
        }
        
        $ins = $pdo->prepare("INSERT INTO leads (lead_serial, name, email, phone, status, deal_value, assigned_to) VALUES ('TEMP', ?, ?, ?, ?, ?, ?)");
        $ins->execute([$row['name'], $row['email'], $row['phone'], $row['status'], $row['deal_value'], $user_id]);
        $new_id = $pdo->lastInsertId();
        assignLeadSerial($pdo, $new_id);
        
        logActivity($pdo, 'Imported Lead via CSV', 'Lead', $new_id);
        $imported++;
    }
    fclose($tmp);
    $_SESSION['flash_success'] = "Successfully imported $imported leads. Safely skipped $skipped duplicates.";
    header("Location: leads.php");
    exit;
}

// Fetch Leads (Strict Visibility)
$leadsSql = "SELECT l.*, u.username as assigned_user FROM leads l LEFT JOIN users u ON l.assigned_to = u.id WHERE l.deleted_at IS NULL";
if (!$isFounder) {
    $leadsSql .= " AND l.assigned_to IN ($visibleIdsStr)";
}
$leadsSql .= " ORDER BY l.created_at DESC LIMIT 100";
$leads = $pdo->query($leadsSql)->fetchAll();

// Flash Messages
if (isset($_SESSION['flash_success'])) { $success = $_SESSION['flash_success']; unset($_SESSION['flash_success']); }
if (isset($_SESSION['flash_error'])) { $error = $_SESSION['flash_error']; unset($_SESSION['flash_error']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lead Management - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>body { background-color: #f8f9fa; }</style>
</head>
<body>
<div class="d-flex">
    <?php include 'header.php'; ?>
    <div class="main-content flex-grow-1 p-4" style="margin-left: 250px; overflow-y: auto; height: 100vh;">
        
        <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

        <?php if ($importPreview): ?>
            <!-- CSV Step 2: Preview -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 border-top border-warning border-4">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h4 class="fw-bold text-warning"><i class="bi bi-exclamation-triangle"></i> Step 2: Smart Preview & Map</h4>
                    <p class="text-muted">Review the detected columns and any duplicate warnings before executing.</p>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="tmp_file" value="<?= h($importPreview['tmp_file']) ?>">
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Status</th>
                                        <?php foreach ($importPreview['headers'] as $index => $header): ?>
                                            <th>
                                                <div class="mb-1 fw-bold"><?= h($header) ?></div>
                                                <select name="mapping[<?= $index ?>]" class="form-select form-select-sm border-warning">
                                                    <option value="skip" <?= $importPreview['mapping'][$index] == 'skip' ? 'selected' : '' ?>>Skip Column</option>
                                                    <option value="name" <?= $importPreview['mapping'][$index] == 'name' ? 'selected' : '' ?>>Name</option>
                                                    <option value="email" <?= $importPreview['mapping'][$index] == 'email' ? 'selected' : '' ?>>Email</option>
                                                    <option value="phone" <?= $importPreview['mapping'][$index] == 'phone' ? 'selected' : '' ?>>Phone</option>
                                                    <option value="status" <?= $importPreview['mapping'][$index] == 'status' ? 'selected' : '' ?>>Status</option>
                                                    <option value="deal_value" <?= $importPreview['mapping'][$index] == 'deal_value' ? 'selected' : '' ?>>Deal Value</option>
                                                </select>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $displayLimit = 10; ?>
                                    <?php foreach ($importPreview['rows'] as $rIdx => $row): ?>
                                        <?php if ($rIdx >= $displayLimit) {
                                            echo "<tr><td colspan='100%' class='text-center text-muted'>... " . (count($importPreview['rows']) - $displayLimit) . " more rows hidden for preview ...</td></tr>";
                                            break;
                                        } ?>
                                        <tr class="<?= $row['_duplicate'] ? 'table-danger' : 'table-success' ?>">
                                            <td class="fw-bold text-center">
                                                <?php if ($row['_duplicate']): ?>
                                                    <span class="text-danger"><i class="bi bi-x-circle-fill"></i> Duplicate<br><small><?= h($row['_existing_serial']) ?></small></span>
                                                <?php else: ?>
                                                    <span class="text-success"><i class="bi bi-check-circle-fill"></i> Safe</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php foreach ($importPreview['headers'] as $cIdx => $h): ?>
                                                <td><?= h($row[$cIdx] ?? '') ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" name="confirm_import" class="btn btn-warning fw-bold px-4">Execute Safe Import</button>
                        <a href="leads.php" class="btn btn-light ms-2">Cancel</a>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Lead Management</h2>
            <div class="d-flex gap-2">
                <form method="POST" enctype="multipart/form-data" class="d-flex gap-2">
                    <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv" required>
                    <button type="submit" class="btn btn-dark btn-sm text-nowrap"><i class="bi bi-upload"></i> Smart CSV</button>
                </form>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeadModal"><i class="bi bi-plus-lg"></i> New Lead</button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Serial ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($leads)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No leads found.</td></tr>
                            <?php endif; ?>
                            <?php foreach($leads as $lead): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-secondary"><?= h($lead['lead_serial']) ?></td>
                                <td class="fw-bold"><?= h($lead['name']) ?></td>
                                <td>
                                    <div><i class="bi bi-envelope text-muted me-1"></i> <?= h($lead['email']) ?></div>
                                    <div><i class="bi bi-telephone text-muted me-1"></i> <?= h($lead['phone']) ?></div>
                                </td>
                                <td class="text-success fw-bold">$<?= number_format($lead['deal_value'], 2) ?></td>
                                <td>
                                    <?php
                                    $badge = 'bg-secondary';
                                    if ($lead['status'] == 'Won') $badge = 'bg-success';
                                    if ($lead['status'] == 'Lost') $badge = 'bg-danger';
                                    if ($lead['status'] == 'Contacted') $badge = 'bg-info';
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= h($lead['status']) ?></span>
                                </td>
                                <td><span class="badge bg-light text-dark border"><i class="bi bi-person"></i> <?= h($lead['assigned_user']) ?></span></td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-light border me-1" onclick='editLead(<?= json_encode($lead) ?>)'><i class="bi bi-pencil"></i></button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Securely soft-delete this lead?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= h($lead['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addLeadModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="modalTitle">New Lead</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" id="modalAction" value="add">
        <input type="hidden" name="id" id="modalId">
        <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Name</label>
            <input type="text" name="name" id="modalName" class="form-control" required>
        </div>
        <div class="row">
            <div class="col-6 mb-3">
                <label class="form-label text-muted small fw-bold">Email</label>
                <input type="email" name="email" id="modalEmail" class="form-control">
            </div>
            <div class="col-6 mb-3">
                <label class="form-label text-muted small fw-bold">Phone</label>
                <input type="text" name="phone" id="modalPhone" class="form-control">
            </div>
        </div>
        <div class="row">
            <div class="col-6 mb-3">
                <label class="form-label text-muted small fw-bold">Status</label>
                <select name="status" id="modalStatus" class="form-select">
                    <option value="New">New</option>
                    <option value="Contacted">Contacted</option>
                    <option value="Qualified">Qualified</option>
                    <option value="Proposal">Proposal</option>
                    <option value="Won">Won</option>
                    <option value="Lost">Lost</option>
                </select>
            </div>
            <div class="col-6 mb-3">
                <label class="form-label text-muted small fw-bold">Deal Value ($)</label>
                <input type="number" step="0.01" name="deal_value" id="modalValue" class="form-control" value="0.00">
            </div>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary px-4 fw-bold">Save Lead</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editLead(lead) {
    document.getElementById('modalTitle').innerText = 'Edit Lead: ' + lead.lead_serial;
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('modalId').value = lead.id;
    document.getElementById('modalName').value = lead.name;
    document.getElementById('modalEmail').value = lead.email;
    document.getElementById('modalPhone').value = lead.phone;
    document.getElementById('modalStatus').value = lead.status;
    document.getElementById('modalValue').value = lead.deal_value;
    new bootstrap.Modal(document.getElementById('addLeadModal')).show();
}
</script>
</body>
</html>
