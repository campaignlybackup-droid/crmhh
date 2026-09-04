<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo);
$isManager = isManagerRole($pdo);
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);

// Handle CSV Import
$importPreview = null;
$importHeaders = [];
$mappedColumns = [];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    if (is_uploaded_file($file)) {
        $handle = fopen($file, "r");
        if ($handle !== FALSE) {
            $importHeaders = fgetcsv($handle);
            $previewRows = [];
            $rowLimit = 5;
            while (($data = fgetcsv($handle)) !== FALSE && $rowLimit > 0) {
                $previewRows[] = $data;
                $rowLimit--;
            }
            fclose($handle);
            
            // Auto map
            foreach ($importHeaders as $index => $header) {
                $h = strtolower(trim($header));
                if (in_array($h, ['name', 'full name', 'lead name', 'contact name'])) $mappedColumns[$index] = 'name';
                elseif (in_array($h, ['email', 'email address', 'e-mail', 'contact email'])) $mappedColumns[$index] = 'email';
                elseif (in_array($h, ['phone', 'phone number', 'mobile', 'mobile number', 'contact number'])) $mappedColumns[$index] = 'phone';
                elseif (in_array($h, ['status', 'lead status', 'current status', 'stage'])) $mappedColumns[$index] = 'status';
                elseif (in_array($h, ['source'])) $mappedColumns[$index] = 'source';
                else $mappedColumns[$index] = 'notes'; // default
            }
            
            $importPreview = [
                'headers' => $importHeaders,
                'rows' => $previewRows,
                'tmp_file' => base64_encode(file_get_contents($file))
            ];
        } else {
            $error = 'Could not read CSV file.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    $fileData = base64_decode($_POST['tmp_file']);
    $mapping = $_POST['mapping']; // array index => field name
    $skipDuplicates = isset($_POST['skip_duplicates']);
    
    $tmp = tmpfile();
    fwrite($tmp, $fileData);
    fseek($tmp, 0);
    
    fgetcsv($tmp); // skip header
    $imported = 0;
    $skipped = 0;
    
    while (($data = fgetcsv($tmp)) !== FALSE) {
        $row = ['name'=>'', 'email'=>'', 'phone'=>'', 'status'=>'New', 'source'=>'Walk-in', 'notes'=>''];
        foreach ($data as $index => $val) {
            if (isset($mapping[$index]) && $mapping[$index] !== 'skip') {
                $row[$mapping[$index]] = trim($val);
            }
        }
        
        if (empty($row['name'])) continue; // skip empty rows
        
        // Check duplicate
        $dupStmt = $pdo->prepare("SELECT id FROM leads WHERE phone = ? OR (email = ? AND email != '') LIMIT 1");
        $dupStmt->execute([$row['phone'], $row['email']]);
        if ($dupStmt->fetch()) {
            if ($skipDuplicates) {
                $skipped++;
                continue;
            }
        }
        
        $ins = $pdo->prepare("INSERT INTO leads (name, email, phone, status, source, notes, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$row['name'], $row['email'], $row['phone'], $row['status'], $row['source'], $row['notes'], $user_id]);
        $imported++;
        logActivity($pdo, 'Imported Lead', 'Lead', $pdo->lastInsertId());
    }
    fclose($tmp);
    $success = "Successfully imported $imported leads. Skipped $skipped duplicates.";
}

// Fetch Leads
$leadsSql = "SELECT * FROM leads";
if (!$isFounder) {
    $leadsSql .= " WHERE assigned_to IN ($visibleIdsStr)";
}
$leadsSql .= " ORDER BY created_at DESC LIMIT 50"; // simple pagination representation
$stmt = $pdo->query($leadsSql);
$leads = $stmt ? $stmt->fetchAll() : [];

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Leads Management</h3>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
        <i class="bi bi-upload me-2"></i> Import CSV
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<?php if ($importPreview): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 py-3">
        <h5 class="mb-0 fw-bold">Map Columns (Import Preview)</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="confirm_import" value="1">
            <input type="hidden" name="tmp_file" value="<?= $importPreview['tmp_file'] ?>">
            
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($importPreview['headers'] as $index => $header): ?>
                                <th>
                                    <div class="small fw-bold text-muted mb-1"><?= h($header) ?></div>
                                    <select name="mapping[<?= $index ?>]" class="form-select form-select-sm">
                                        <option value="skip">-- Skip --</option>
                                        <option value="name" <?= $mappedColumns[$index] == 'name' ? 'selected' : '' ?>>Name</option>
                                        <option value="phone" <?= $mappedColumns[$index] == 'phone' ? 'selected' : '' ?>>Phone</option>
                                        <option value="email" <?= $mappedColumns[$index] == 'email' ? 'selected' : '' ?>>Email</option>
                                        <option value="status" <?= $mappedColumns[$index] == 'status' ? 'selected' : '' ?>>Status</option>
                                        <option value="source" <?= $mappedColumns[$index] == 'source' ? 'selected' : '' ?>>Source</option>
                                        <option value="notes" <?= $mappedColumns[$index] == 'notes' ? 'selected' : '' ?>>Notes</option>
                                    </select>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($importPreview['rows'] as $row): ?>
                            <tr>
                                <?php foreach ($row as $val): ?>
                                    <td class="small"><?= h($val) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="skip_duplicates" id="skipDups" checked>
                <label class="form-check-label" for="skipDups">Skip rows with duplicate Phone/Email</label>
            </div>
            
            <button type="submit" class="btn btn-success fw-bold">Confirm & Import</button>
            <a href="leads.php" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Lead ID</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Source</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No leads found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td class="ps-4"><span class="badge bg-secondary">LD-<?= str_pad($lead['id'], 6, '0', STR_PAD_LEFT) ?></span></td>
                                <td class="fw-bold"><?= h($lead['name']) ?></td>
                                <td>
                                    <div class="small"><i class="bi bi-telephone me-1 text-muted"></i><?= h($lead['phone']) ?></div>
                                    <div class="small"><i class="bi bi-envelope me-1 text-muted"></i><?= h($lead['email']) ?></div>
                                </td>
                                <td><span class="badge bg-primary rounded-pill"><?= h($lead['status']) ?></span></td>
                                <td class="text-muted small"><?= h($lead['source']) ?></td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-light border">View</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" enctype="multipart/form-data">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">Import CSV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Select CSV File</label>
            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="submit" class="btn btn-primary fw-bold w-100">Upload & Preview</button>
      </div>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>
