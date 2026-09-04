<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id = getCurrentUserId();
$isFounder = isFounder($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_availability'])) {
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $status = $_POST['status'];
    $notes = trim($_POST['notes']);
    
    $ins = $pdo->prepare("INSERT INTO availability (user_id, start_time, end_time, status, notes) VALUES (?, ?, ?, ?, ?)");
    $ins->execute([$user_id, $start_time, $end_time, $status, $notes]);
    
    $_SESSION['flash_success'] = "Availability updated.";
    header("Location: calendar.php");
    exit;
}

include 'header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Calendar</h3>
    <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#availabilityModal">
        <i class="bi bi-clock-history me-2"></i> Update Availability
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div id="calendar"></div>
    </div>
</div>

<!-- Add Availability Modal -->
<div class="modal fade" id="availabilityModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow" method="POST">
            <div class="modal-header border-0 bg-light pb-2">
                <h5 class="modal-title fw-bold">Update Availability</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <input type="hidden" name="add_availability" value="1">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold">Start Time</label>
                    <input type="datetime-local" class="form-control" name="start_time" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold">End Time</label>
                    <input type="datetime-local" class="form-control" name="end_time" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold">Status</label>
                    <select class="form-select" name="status" required>
                        <option value="Available">Available</option>
                        <option value="Busy">Busy</option>
                        <option value="Meeting">Meeting</option>
                        <option value="Unavailable">Unavailable</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold">Notes (Optional)</label>
                    <input type="text" class="form-control" name="notes" placeholder="e.g. Doctor appointment">
                </div>
                
            </div>
            <div class="modal-footer border-0">
                <button type="submit" class="btn btn-primary fw-bold w-100">Save</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        height: 'auto',
        themeSystem: 'bootstrap5',
        events: 'api_calendar.php',
    });
    calendar.render();
});
</script>

<?php include 'footer.php'; ?>
