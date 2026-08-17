<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();
$isManager = isManager();
$user_id = getCurrentUserId();
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = implode(',', $visibleIds);

$view_user_id = $_GET['user_id'] ?? null;
$current_month = date('m');
$current_year = date('Y');

// Security check for detailed view
if ($view_user_id && !$isSuper && !in_array($view_user_id, $visibleIds)) {
    $_SESSION['flash_error'] = "Unauthorized access to employee dashboard.";
    header("Location: team_dashboard.php");
    exit;
}

if ($view_user_id) {
    try {
        // Detailed view for a specific user
        $stmt = $pdo->prepare("SELECT id, username, role, designation, department FROM users WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$view_user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $_SESSION['flash_error'] = "Employee not found.";
            header("Location: team_dashboard.php");
            exit;
        }
        
        // 1. Work Logs
        $stmt = $pdo->prepare("SELECT * FROM daily_work WHERE user_id = ? AND MONTH(work_date) = ? AND YEAR(work_date) = ? ORDER BY work_date DESC");
        $stmt->execute([$view_user_id, $current_month, $current_year]);
        $work_logs = $stmt->fetchAll();
        
        // 2. Tasks Performance
        $stmt = $pdo->prepare("SELECT t.*, p.project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.assigned_to = ? ORDER BY t.due_date ASC");
        $stmt->execute([$view_user_id]);
        $all_tasks = $stmt->fetchAll();
        
        $completed_tasks = array_filter($all_tasks, fn($t) => $t['status'] === 'Done');
        $ongoing_tasks = array_filter($all_tasks, fn($t) => $t['status'] === 'In Progress');
        
        // 3. Converted Leads
        $stmt = $pdo->prepare("SELECT * FROM leads WHERE assigned_to = ? AND deleted_at IS NULL ORDER BY created_at DESC");
        $stmt->execute([$view_user_id]);
        $assigned_leads = $stmt->fetchAll();
        
        $won_leads = array_filter($assigned_leads, fn($l) => $l['status'] === 'Won');
        $won_value = array_reduce($won_leads, fn($sum, $l) => $sum + floatval($l['deal_value']), 0);
        
        // 4. Assigned Clients (Contract Dates & Payment Dates)
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE assigned_to = ? AND deleted_at IS NULL ORDER BY client_name ASC");
        $stmt->execute([$view_user_id]);
        $assigned_clients = $stmt->fetchAll();
        
        // 5. Assigned Projects
        $stmt = $pdo->prepare("SELECT p.*, c.client_name FROM projects p LEFT JOIN clients c ON p.client_id = c.id WHERE p.assigned_to = ? AND p.deleted_at IS NULL ORDER BY p.created_at DESC");
        $stmt->execute([$view_user_id]);
        $assigned_projects = $stmt->fetchAll();

    } catch (PDOException $e) {
        $user = null;
    }
} else {
    try {
        // Overview for all visible team members
        if ($isSuper) {
            $users = $pdo->query("SELECT id, username, role, designation, department FROM users WHERE deleted_at IS NULL ORDER BY username ASC")->fetchAll();
        } else {
            $users = $pdo->query("SELECT id, username, role, designation, department FROM users WHERE deleted_at IS NULL AND id IN ($visibleIdsStr) ORDER BY username ASC")->fetchAll();
        }
        
        $user_stats = [];
        foreach ($users as $u) {
            $uid = $u['id'];
            
            // Presence
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT work_date) FROM daily_work WHERE user_id = ? AND MONTH(work_date) = ? AND YEAR(work_date) = ?");
            $stmt->execute([$uid, $current_month, $current_year]);
            $presence_count = $stmt->fetchColumn() ?: 0;
            
            // Tasks
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
            $stmt->execute([$uid]);
            $total_tasks = $stmt->fetchColumn() ?: 0;
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'Done'");
            $stmt->execute([$uid]);
            $done_tasks = $stmt->fetchColumn() ?: 0;
            
            $stmt = $pdo->prepare("SELECT task_name, p.project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.assigned_to = ? AND t.status = 'In Progress'");
            $stmt->execute([$uid]);
            $ongoing_tasks = $stmt->fetchAll();
            
            // Leads Converted
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND deleted_at IS NULL");
            $stmt->execute([$uid]);
            $total_leads = $stmt->fetchColumn() ?: 0;
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND status = 'Won' AND deleted_at IS NULL");
            $stmt->execute([$uid]);
            $won_leads = $stmt->fetchColumn() ?: 0;
            
            $stmt = $pdo->prepare("SELECT SUM(deal_value) FROM leads WHERE assigned_to = ? AND status = 'Won' AND deleted_at IS NULL");
            $stmt->execute([$uid]);
            $won_value = $stmt->fetchColumn() ?: 0;
            
            // Assigned Clients & Payments/Contracts
            $stmt = $pdo->prepare("SELECT id, client_name, onboarding_date, monthly_payment_date, contract_file, drive_folder_url FROM clients WHERE assigned_to = ? AND deleted_at IS NULL ORDER BY client_name ASC");
            $stmt->execute([$uid]);
            $clients_list = $stmt->fetchAll();
            
            $user_stats[] = [
                'id' => $uid,
                'username' => $u['username'],
                'role' => $u['role'],
                'designation' => $u['designation'],
                'department' => $u['department'],
                'presence' => $presence_count,
                'total_tasks' => $total_tasks,
                'done_tasks' => $done_tasks,
                'ongoing_tasks' => $ongoing_tasks,
                'total_leads' => $total_leads,
                'won_leads' => $won_leads,
                'won_value' => $won_value,
                'clients' => $clients_list
            ];
        }
    } catch (PDOException $e) {
        $user_stats = [];
    }
}

include 'header.php';
?>

<?php if ($view_user_id && $user): ?>
    <!-- Single Employee Detailed Performance View -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="team_dashboard.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Back to Team Overview</a>
            <h3 class="fw-bold mb-0 mt-1 d-flex align-items-center">
                <?= h($user['username']) ?>
                <span class="badge bg-soft-primary text-primary fs-6 ms-2 align-middle text-capitalize"><?= h($user['role']) ?></span>
            </h3>
            <div class="text-muted small">
                <i class="bi bi-person-badge me-1"></i> <?= h($user['designation'] ?: 'Employee') ?> &bull; 
                <i class="bi me-1 bi-building"></i> <?= h($user['department'] ?: 'General') ?>
            </div>
        </div>
        <div>
            <a href="daily_work.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-check me-1"></i> View Daily Logs</a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="text-muted small fw-bold d-block text-uppercase mb-1">Work Done (Tasks)</span>
                    <div class="d-flex justify-content-between align-items-baseline">
                        <h3 class="fw-bold mb-0 text-success"><?= count($completed_tasks) ?> / <?= count($all_tasks) ?></h3>
                        <span class="badge bg-soft-success text-success">
                            <?= count($all_tasks) > 0 ? round((count($completed_tasks)/count($all_tasks))*100) : 0 ?>% Done
                        </span>
                    </div>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar bg-success" style="width: <?= count($all_tasks) > 0 ? round((count($completed_tasks)/count($all_tasks))*100) : 0 ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="text-muted small fw-bold d-block text-uppercase mb-1">Leads Converted</span>
                    <div class="d-flex justify-content-between align-items-baseline">
                        <h3 class="fw-bold mb-0 text-primary"><?= count($won_leads) ?> <small class="fs-6 text-muted">/ <?= count($assigned_leads) ?></small></h3>
                        <span class="badge bg-soft-primary text-primary">
                            <?= count($assigned_leads) > 0 ? round((count($won_leads)/count($assigned_leads))*100, 1) : 0 ?>% Won
                        </span>
                    </div>
                    <div class="small text-muted mt-1">Converted Value: <strong class="text-dark">AED <?= number_format($won_value, 2) ?></strong></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="text-muted small fw-bold d-block text-uppercase mb-1">Assigned Clients</span>
                    <h3 class="fw-bold mb-0 text-dark"><?= count($assigned_clients) ?></h3>
                    <div class="small text-muted mt-1"><i class="bi bi-calendar-check me-1"></i> Active Contract Accounts</div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="text-muted small fw-bold d-block text-uppercase mb-1">Presence (<?= date('F Y') ?>)</span>
                    <h3 class="fw-bold mb-0 text-info"><?= count($work_logs) ?> Days</h3>
                    <div class="small text-muted mt-1"><i class="bi bi-journal-text me-1"></i> Logs Submitted</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabbed Detailed Breakdown -->
    <ul class="nav nav-tabs mb-4 border-bottom-0" id="employeeTab" role="tablist">
        <li class="nav-item">
            <button class="nav-link active fw-bold" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" type="button"><i class="bi bi-check2-square me-2"></i> Work Done & Tasks (<?= count($all_tasks) ?>)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="leads-tab" data-bs-toggle="tab" data-bs-target="#leads" type="button"><i class="bi bi-funnel me-2"></i> Converted Leads (<?= count($won_leads) ?>)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="clients-tab" data-bs-toggle="tab" data-bs-target="#clients" type="button"><i class="bi bi-people me-2"></i> Client Contracts & Payment Dates (<?= count($assigned_clients) ?>)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button"><i class="bi bi-journal-text me-2"></i> Daily Work Logs (<?= count($work_logs) ?>)</button>
        </li>
    </ul>

    <div class="tab-content" id="employeeTabContent">
        <!-- 1. TASKS TAB -->
        <div class="tab-pane fade show active" id="tasks" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3 pb-2 border-0">
                    <h6 class="fw-bold mb-0">Assigned Tasks & Progress</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">Task Name</th>
                                    <th>Project</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th class="pe-3 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($all_tasks)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">No tasks assigned to this employee.</td></tr>
                                <?php else: ?>
                                    <?php foreach($all_tasks as $t): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold">
                                            <a href="task_view.php?id=<?= $t['id'] ?>" class="text-decoration-none text-dark"><?= h($t['task_name']) ?></a>
                                        </td>
                                        <td><?= h($t['project_name'] ?? 'General Task') ?></td>
                                        <td>
                                            <?php
                                                $pc = 'bg-soft-secondary';
                                                if ($t['priority'] == 'High') $pc = 'bg-soft-danger';
                                                if ($t['priority'] == 'Medium') $pc = 'bg-soft-warning';
                                            ?>
                                            <span class="badge <?= $pc ?>"><?= h($t['priority']) ?></span>
                                        </td>
                                        <td>
                                            <?php
                                                $sc = 'bg-soft-secondary';
                                                if ($t['status'] == 'In Progress') $sc = 'bg-soft-primary';
                                                if ($t['status'] == 'Review') $sc = 'bg-soft-warning';
                                                if ($t['status'] == 'Done') $sc = 'bg-soft-success';
                                            ?>
                                            <span class="badge <?= $sc ?>"><?= h($t['status']) ?></span>
                                        </td>
                                        <td><?= h($t['due_date'] ? date('M j, Y', strtotime($t['due_date'])) : 'No due date') ?></td>
                                        <td class="pe-3 text-end">
                                            <a href="task_view.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> View</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. LEADS TAB -->
        <div class="tab-pane fade" id="leads" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3 pb-2 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">Leads Handled & Converted</h6>
                    <span class="badge bg-success">Total Converted Value: AED <?= number_format($won_value, 2) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">Lead Name</th>
                                    <th>Source</th>
                                    <th>Industry</th>
                                    <th>Status</th>
                                    <th>Deal Value</th>
                                    <th>Next Action Date</th>
                                    <th class="pe-3 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($assigned_leads)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No leads assigned to this employee.</td></tr>
                                <?php else: ?>
                                    <?php foreach($assigned_leads as $l): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold"><?= h($l['name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= h($l['source']) ?></span></td>
                                        <td><?= h($l['industry']) ?></td>
                                        <td>
                                            <?php
                                                $lsc = 'bg-soft-secondary';
                                                if ($l['status'] == 'Won') $lsc = 'bg-soft-success';
                                                if ($l['status'] == 'Lost') $lsc = 'bg-soft-danger';
                                                if ($l['status'] == 'Proposal Sent') $lsc = 'bg-soft-info';
                                            ?>
                                            <span class="badge <?= $lsc ?>"><?= h($l['status']) ?></span>
                                        </td>
                                        <td class="fw-bold text-success">AED <?= number_format($l['deal_value'], 2) ?></td>
                                        <td><?= h($l['next_action_date'] ?: 'N/A') ?></td>
                                        <td class="pe-3 text-end">
                                            <a href="leads.php?search=<?= urlencode($l['name']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. CLIENT CONTRACTS & PAYMENT DATES TAB -->
        <div class="tab-pane fade" id="clients" role="tabpanel">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white pt-3 pb-2 border-0">
                    <h6 class="fw-bold mb-0">Assigned Clients (Contract Dates & Monthly Payment Schedule)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">Client Name</th>
                                    <th>Primary Contact</th>
                                    <th>Status</th>
                                    <th>Contract / Onboarding Date</th>
                                    <th>Monthly Payment Date</th>
                                    <th>Files & Links</th>
                                    <th class="pe-3 text-end">Profile</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($assigned_clients)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No clients assigned to this employee.</td></tr>
                                <?php else: ?>
                                    <?php foreach($assigned_clients as $c): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold">
                                            <a href="client_profile.php?id=<?= $c['id'] ?>" class="text-decoration-none text-dark"><?= h($c['client_name']) ?></a>
                                        </td>
                                        <td><?= h($c['primary_contact'] ?: 'N/A') ?></td>
                                        <td><span class="badge bg-soft-success"><?= h($c['status']) ?></span></td>
                                        <td><i class="bi bi-calendar-event text-primary me-1"></i> <?= h($c['onboarding_date'] ?: 'N/A') ?></td>
                                        <td><i class="bi bi-clock-history text-warning me-1"></i> <strong><?= h($c['monthly_payment_date'] ?: 'Not set') ?></strong></td>
                                        <td>
                                            <?php if($c['drive_folder_url']): ?>
                                                <a href="<?= h($c['drive_folder_url']) ?>" target="_blank" class="btn btn-sm btn-light mb-1"><i class="bi bi-folder-fill text-warning"></i> Drive</a>
                                            <?php endif; ?>
                                            <?php if($c['contract_file']): ?>
                                                <a href="download.php?file=<?= urlencode($c['contract_file']) ?>" target="_blank" class="btn btn-sm btn-light mb-1"><i class="bi bi-file-earmark-text text-primary"></i> Contract</a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="pe-3 text-end">
                                            <a href="client_profile.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Assigned Projects Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3 pb-2 border-0">
                    <h6 class="fw-bold mb-0">Assigned Projects (Shoot & Delivery Dates)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">Project Name</th>
                                    <th>Client</th>
                                    <th>Status</th>
                                    <th>Shoot Date</th>
                                    <th>Delivery Date</th>
                                    <th>Payment Status</th>
                                    <th class="pe-3 text-end">View</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($assigned_projects)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No projects assigned to this employee.</td></tr>
                                <?php else: ?>
                                    <?php foreach($assigned_projects as $p): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold">
                                            <a href="project_view.php?id=<?= $p['id'] ?>" class="text-decoration-none text-dark"><?= h($p['project_name']) ?></a>
                                        </td>
                                        <td><?= h($p['client_name'] ?? 'N/A') ?></td>
                                        <td><span class="badge bg-soft-primary"><?= h($p['status']) ?></span></td>
                                        <td><i class="bi bi-camera-video me-1"></i> <?= h($p['shoot_date'] ?: 'N/A') ?></td>
                                        <td><i class="bi bi-calendar-check me-1"></i> <?= h($p['delivery_date'] ?: 'N/A') ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= h($p['payment_status']) ?></span></td>
                                        <td class="pe-3 text-end">
                                            <a href="project_view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. DAILY WORK LOGS TAB -->
        <div class="tab-pane fade" id="logs" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white pt-3 pb-2 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">Daily Work Logs (<?= date('F Y') ?>)</h6>
                    <span class="badge bg-primary rounded-pill"><?= count($work_logs) ?> Days Logged</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3" style="width: 160px;">Date</th>
                                    <th>Work Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($work_logs)): ?>
                                    <tr><td colspan="2" class="text-center text-muted py-4">No daily work logged this month.</td></tr>
                                <?php else: ?>
                                    <?php foreach($work_logs as $log): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold"><i class="bi bi-calendar3 me-1 text-primary"></i> <?= h(date('M d, Y', strtotime($log['work_date']))) ?></td>
                                        <td><?= nl2br(h($log['description'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Overview of All Accessible Team Members -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h3 class="fw-bold mb-0">Team Performance & Employee Analytics</h3>
            <p class="text-muted mb-0">Comprehensive tracking of employee work done, converted leads, contract dates, and payment schedules.</p>
        </div>
    </div>
    
    <div class="row g-4">
        <?php if(empty($user_stats)): ?>
            <div class="col-12"><div class="alert alert-info border-0 shadow-sm">No team members found.</div></div>
        <?php else: ?>
            <?php foreach($user_stats as $stat): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body d-flex flex-column">
                        <!-- Employee Header -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0 fw-bold d-flex align-items-center">
                                <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center me-2 fw-bold" style="width: 38px; height: 38px; font-size: 1.1rem;">
                                    <?= strtoupper(substr($stat['username'], 0, 1)) ?>
                                </div>
                                <?= h($stat['username']) ?>
                            </h5>
                            <span class="badge bg-soft-success text-success" title="Presence This Month">
                                <i class="bi bi-calendar-check"></i> <?= $stat['presence'] ?> Days
                            </span>
                        </div>
                        
                        <div class="small text-muted mb-3 border-bottom pb-2">
                            <span class="badge bg-light text-dark border text-capitalize me-1"><?= h($stat['role']) ?></span>
                            <strong><?= h($stat['department'] ?: 'General') ?></strong> &bull; <?= h($stat['designation'] ?: 'Employee') ?>
                        </div>
                        
                        <!-- Employee Performance Key Metrics -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="p-2 bg-light rounded text-center">
                                    <span class="d-block text-muted small fw-bold">WORK DONE</span>
                                    <span class="fw-bold text-success fs-6"><?= $stat['done_tasks'] ?> / <?= $stat['total_tasks'] ?></span>
                                    <small class="d-block text-muted" style="font-size:0.75rem;"><?= $stat['total_tasks'] > 0 ? round(($stat['done_tasks']/$stat['total_tasks'])*100) : 0 ?>% Tasks</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded text-center">
                                    <span class="d-block text-muted small fw-bold">LEADS WON</span>
                                    <span class="fw-bold text-primary fs-6"><?= $stat['won_leads'] ?> / <?= $stat['total_leads'] ?></span>
                                    <small class="d-block text-muted text-truncate" style="font-size:0.75rem;">AED <?= number_format($stat['won_value']) ?></small>
                                </div>
                            </div>
                        </div>

                        <!-- Assigned Clients & Monthly Payment Schedule -->
                        <div class="flex-grow-1">
                            <h6 class="text-muted small fw-bold mb-2">ASSIGNED CLIENTS & PAYMENTS</h6>
                            <?php if(empty($stat['clients'])): ?>
                                <div class="text-muted small fst-italic mb-2">No clients assigned.</div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush small mb-2">
                                    <?php foreach(array_slice($stat['clients'], 0, 3) as $c): ?>
                                        <li class="list-group-item px-0 py-1 bg-transparent border-0 d-flex justify-content-between align-items-center">
                                            <span class="fw-semibold text-truncate" style="max-width: 140px;"><?= h($c['client_name']) ?></span>
                                            <span class="badge bg-soft-warning text-dark small" style="font-size: 0.7rem;"><i class="bi bi-clock me-1"></i> <?= h($c['monthly_payment_date'] ?: 'Pay Date TBD') ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if(count($stat['clients']) > 3): ?>
                                        <li class="text-muted fst-italic small pt-1">+<?= count($stat['clients']) - 3 ?> more clients</li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Action Button -->
                        <div class="mt-3 pt-3 border-top">
                            <a href="?user_id=<?= $stat['id'] ?>" class="btn btn-sm btn-outline-primary w-100">
                                <i class="bi bi-bar-chart-line me-1"></i> View Detailed Performance
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
