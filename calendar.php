<?php
require_once 'functions.php';
requireLogin();

$isSuper = isSuperAdmin();

// Fetch filter options for SuperAdmin
$users = [];
$projects = [];
$clients = [];

if ($isSuper) {
    try {
        $users = $pdo->query("SELECT id, username FROM users WHERE deleted_at IS NULL ORDER BY username")->fetchAll();
        $projects = $pdo->query("SELECT id, project_name FROM projects WHERE deleted_at IS NULL ORDER BY project_name")->fetchAll();
        $clients = $pdo->query("SELECT id, client_name FROM clients WHERE deleted_at IS NULL ORDER BY client_name")->fetchAll();
    } catch (Exception $e) {
        // ignore
    }
}

include 'header.php';
?>
<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">

<div class="row g-4">
    <!-- Filters Sidebar (Admin Only) -->
    <?php if ($isSuper): ?>
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm sticky-top" style="top: 90px;">
            <div class="card-header border-0 pb-0">
                <h5 class="fw-bold mb-0">Filters</h5>
            </div>
            <div class="card-body">
                <form id="calendarFilterForm">
                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">Event Type</label>
                        <select class="form-select" id="filterType">
                            <option value="">All Events</option>
                            <option value="Task">Tasks</option>
                            <option value="Shoot">Shoots</option>
                            <option value="Delivery">Deliveries</option>
                            <option value="Meeting">Meetings</option>
                            <option value="Leave">Leaves</option>
                            <option value="Holiday">Holidays</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">Employee</label>
                        <select class="form-select" id="filterUser">
                            <option value="">Everyone</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= h($u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">Project</label>
                        <select class="form-select" id="filterProject">
                            <option value="">All Projects</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= h($p['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">Client</label>
                        <select class="form-select" id="filterClient">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= h($c['client_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <div class="mt-4 border-top pt-3">
                    <h6 class="small text-muted text-uppercase fw-bold mb-2">Legend</h6>
                    <div class="d-flex align-items-center mb-1"><span class="badge bg-primary me-2" style="width:15px;height:15px;padding:0;"></span> Tasks</div>
                    <div class="d-flex align-items-center mb-1"><span class="badge bg-danger me-2" style="width:15px;height:15px;padding:0;"></span> Shoots</div>
                    <div class="d-flex align-items-center mb-1"><span class="badge bg-success me-2" style="width:15px;height:15px;padding:0;"></span> Deliveries</div>
                    <div class="d-flex align-items-center mb-1"><span class="badge bg-info me-2" style="width:15px;height:15px;padding:0;"></span> Meetings</div>
                    <div class="d-flex align-items-center mb-1"><span class="badge bg-warning me-2" style="width:15px;height:15px;padding:0;"></span> Leaves</div>
                    <div class="d-flex align-items-center mb-1"><span class="badge bg-secondary me-2" style="width:15px;height:15px;padding:0;"></span> Holidays</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Calendar Area -->
    <div class="<?= $isSuper ? 'col-lg-9' : 'col-lg-12' ?>">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="eventModalTitle">Event Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3 text-uppercase fw-bold" id="eventModalType">TYPE</p>
        <div class="mb-2"><strong>Start:</strong> <span id="eventModalStart"></span></div>
        <div class="mb-3" id="eventModalEndContainer"><strong>End:</strong> <span id="eventModalEnd"></span></div>
        
        <a href="#" id="eventModalLink" class="btn btn-primary w-100">View Details</a>
      </div>
    </div>
  </div>
</div>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var eventModal = new bootstrap.Modal(document.getElementById('eventModal'));

    function getEventSource() {
        var url = 'api_calendar.php?';
        <?php if ($isSuper): ?>
        url += 'event_type=' + document.getElementById('filterType').value + '&';
        url += 'user_id=' + document.getElementById('filterUser').value + '&';
        url += 'project_id=' + document.getElementById('filterProject').value + '&';
        url += 'client_id=' + document.getElementById('filterClient').value;
        <?php endif; ?>
        return url;
    }

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        height: 'auto',
        themeSystem: 'bootstrap5',
        events: getEventSource(),
        eventClick: function(info) {
            info.jsEvent.preventDefault(); // don't let the browser navigate
            
            document.getElementById('eventModalTitle').innerText = info.event.title;
            document.getElementById('eventModalType').innerText = info.event.extendedProps.type || 'Event';
            document.getElementById('eventModalStart').innerText = info.event.start.toLocaleString();
            
            if (info.event.end) {
                document.getElementById('eventModalEndContainer').style.display = 'block';
                document.getElementById('eventModalEnd').innerText = info.event.end.toLocaleString();
            } else {
                document.getElementById('eventModalEndContainer').style.display = 'none';
            }

            var linkBtn = document.getElementById('eventModalLink');
            if (info.event.url && info.event.url !== '#') {
                linkBtn.style.display = 'block';
                linkBtn.href = info.event.url;
            } else {
                linkBtn.style.display = 'none';
            }

            eventModal.show();
        }
    });

    calendar.render();

    // Setup filter listeners
    <?php if ($isSuper): ?>
    var filters = ['filterType', 'filterUser', 'filterProject', 'filterClient'];
    filters.forEach(function(id) {
        document.getElementById(id).addEventListener('change', function() {
            var eventSource = calendar.getEventSourceById('dynamicSource');
            if(eventSource) eventSource.remove();
            
            calendar.addEventSource({
                id: 'dynamicSource',
                url: getEventSource()
            });
        });
    });
    // Add initial id
    calendar.getEventSources()[0].remove();
    calendar.addEventSource({ id: 'dynamicSource', url: getEventSource() });
    <?php endif; ?>

    // Handle theme toggle for calendar
    document.getElementById('themeToggle')?.addEventListener('click', function() {
        // Just re-render occasionally helps, but FullCalendar picks up CSS vars mostly
        setTimeout(() => calendar.render(), 100);
    });
});
</script>

<?php include 'footer.php'; ?>
