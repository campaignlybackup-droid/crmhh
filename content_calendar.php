<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo, $user_id);
$visibleIds = getVisibleUserIds($pdo, $user_id);
$visibleIdsStr = empty($visibleIds) ? '' : implode(',', $visibleIds);

// Determine Year and Month
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

// Navigation logic
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth == 0) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth == 13) { $nextMonth = 1; $nextYear++; }

// Fetch Tasks for the selected month
$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate = date('Y-m-t', strtotime($startDate));

$tasksSql = "
    SELECT t.*, c.company_name, u.username as assigned_user
    FROM tasks t
    LEFT JOIN clients c ON t.client_id = c.id
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.deleted_at IS NULL
    AND t.due_date >= ? AND t.due_date <= ?
";
$params = [$startDate, $endDate];

if (!$isFounder) {
    $tasksSql .= " AND t.assigned_to IN ($visibleIdsStr)";
}
$tasksSql .= " ORDER BY t.due_date ASC, t.priority DESC";

$stmt = $pdo->prepare($tasksSql);
$stmt->execute($params);
$rawTasks = $stmt->fetchAll();

// Group tasks by day
$calendarTasks = [];
foreach ($rawTasks as $task) {
    $day = (int)date('j', strtotime($task['due_date']));
    $calendarTasks[$day][] = $task;
}

// Calendar Generation Variables
$daysInMonth = date('t', strtotime($startDate));
$firstDayOfWeek = date('w', strtotime($startDate)); // 0 = Sunday, 6 = Saturday
$monthName = date('F Y', strtotime($startDate));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Content Calendar - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background-color: #dee2e6;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        .calendar-header {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            color: #495057;
        }
        .calendar-day {
            background-color: #ffffff;
            min-height: 120px;
            padding: 8px;
            display: flex;
            flex-direction: column;
        }
        .calendar-day.empty { background-color: #f8f9fa; }
        .calendar-day.today { background-color: #fff8e1; border: 2px solid #ffc107; }
        .day-number {
            font-weight: bold;
            color: #6c757d;
            margin-bottom: 5px;
            text-align: right;
        }
        .task-badge {
            font-size: 0.75rem;
            padding: 4px 6px;
            margin-bottom: 4px;
            border-radius: 4px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .task-badge:hover { opacity: 0.8; }
        
        .status-completed { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .priority-urgent { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .priority-high { background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }
        .default-badge { background-color: #e2e3e5; color: #41464b; border: 1px solid #d3d6d8; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include 'header.php'; ?>
    <div class="main-content flex-grow-1 p-4" style="margin-left: 250px; overflow-y: auto; height: 100vh;">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Task Calendar</h2>
            <div class="d-flex align-items-center gap-3">
                <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-left"></i></a>
                <h4 class="mb-0 fw-bold text-primary mx-3" style="min-width: 180px; text-align: center;"><?= $monthName ?></h4>
                <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-right"></i></a>
                <a href="?year=<?= date('Y') ?>&month=<?= date('n') ?>" class="btn btn-primary btn-sm ms-3 fw-bold">Today</a>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-0">
                <div class="calendar-grid">
                    <!-- Week Headers -->
                    <div class="calendar-header">Sun</div>
                    <div class="calendar-header">Mon</div>
                    <div class="calendar-header">Tue</div>
                    <div class="calendar-header">Wed</div>
                    <div class="calendar-header">Thu</div>
                    <div class="calendar-header">Fri</div>
                    <div class="calendar-header">Sat</div>

                    <!-- Empty slots before 1st of month -->
                    <?php for($i = 0; $i < $firstDayOfWeek; $i++): ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor; ?>

                    <!-- Days -->
                    <?php 
                    $currentDate = date('Y-m-d');
                    for($day = 1; $day <= $daysInMonth; $day++): 
                        $thisDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $isToday = ($thisDate === $currentDate);
                    ?>
                        <div class="calendar-day <?= $isToday ? 'today' : '' ?>">
                            <div class="day-number <?= $isToday ? 'text-warning' : '' ?>"><?= $day ?></div>
                            
                            <?php if(isset($calendarTasks[$day])): ?>
                                <?php foreach($calendarTasks[$day] as $task): 
                                    $badgeClass = 'default-badge';
                                    if ($task['status'] === 'Completed') {
                                        $badgeClass = 'status-completed';
                                    } elseif ($task['priority'] === 'Urgent') {
                                        $badgeClass = 'priority-urgent';
                                    } elseif ($task['priority'] === 'High') {
                                        $badgeClass = 'priority-high';
                                    }
                                ?>
                                    <div class="task-badge <?= $badgeClass ?>" onclick='showTaskModal(<?= json_encode($task) ?>)'>
                                        <strong><?= h($task['task_name']) ?></strong><br>
                                        <span class="text-muted" style="font-size:0.65rem;"><?= h($task['company_name'] ?? 'Internal') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                        </div>
                    <?php endfor; ?>

                    <!-- Empty slots after end of month to complete grid -->
                    <?php 
                    $totalCells = $firstDayOfWeek + $daysInMonth;
                    $remainingCells = ($totalCells % 7 == 0) ? 0 : 7 - ($totalCells % 7);
                    for($i = 0; $i < $remainingCells; $i++): 
                    ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- Task Quick View Modal -->
<div class="modal fade" id="taskModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="modalTitle">Task Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-3">
        <h4 id="mTaskName" class="fw-bold text-primary mb-1"></h4>
        <p id="mClient" class="text-muted small mb-3"><i class="bi bi-briefcase"></i> <span></span></p>
        
        <p id="mDesc" class="mb-4"></p>
        
        <div class="row mb-3">
            <div class="col-6">
                <span class="small text-muted fw-bold d-block">Status</span>
                <span id="mStatus" class="badge bg-secondary"></span>
            </div>
            <div class="col-6">
                <span class="small text-muted fw-bold d-block">Priority</span>
                <span id="mPriority" class="badge bg-secondary"></span>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-6">
                <span class="small text-muted fw-bold d-block">Assigned To</span>
                <span id="mAssignee" class="fw-bold"><i class="bi bi-person"></i> <span></span></span>
            </div>
            <div class="col-6">
                <span class="small text-muted fw-bold d-block">Due Date</span>
                <span id="mDate" class="text-danger fw-bold"><i class="bi bi-calendar"></i> <span></span></span>
            </div>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
        <a href="tasks.php" class="btn btn-primary fw-bold">Open Execution Engine</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showTaskModal(task) {
    document.getElementById('mTaskName').innerText = task.task_name;
    document.getElementById('mClient').querySelector('span').innerText = task.company_name || 'Internal';
    document.getElementById('mDesc').innerText = task.description;
    document.getElementById('mStatus').innerText = task.status;
    
    let pBadge = document.getElementById('mPriority');
    pBadge.innerText = task.priority;
    pBadge.className = 'badge';
    if(task.priority == 'Urgent') pBadge.classList.add('bg-danger');
    else if(task.priority == 'High') pBadge.classList.add('bg-warning', 'text-dark');
    else pBadge.classList.add('bg-secondary');
    
    let sBadge = document.getElementById('mStatus');
    sBadge.className = 'badge';
    if(task.status == 'Completed') sBadge.classList.add('bg-success');
    else if(task.status == 'In Progress') sBadge.classList.add('bg-primary');
    else sBadge.classList.add('bg-secondary');

    document.getElementById('mAssignee').querySelector('span').innerText = task.assigned_user || 'Unassigned';
    document.getElementById('mDate').querySelector('span').innerText = task.due_date;
    
    new bootstrap.Modal(document.getElementById('taskModal')).show();
}
</script>
</body>
</html>
