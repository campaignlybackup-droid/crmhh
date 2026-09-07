<h1>Daily Reports</h1>

<div class="card" style="max-width:680px">
    <div class="card-title"><?= $today ? "Today's Report (submitted)" : "Submit Today's Report" ?></div>
    <form method="post" action="<?= url('reports', ['action' => 'submit']) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="report_date" value="<?= date('Y-m-d') ?>">
        <div class="form-group"><label>Work Completed</label><textarea name="work_completed"><?= e($today['work_completed'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Tasks Worked On</label><textarea name="tasks_worked_on"><?= e($today['tasks_worked_on'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Pending Work</label><textarea name="pending_work"><?= e($today['pending_work'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Blockers</label><textarea name="blockers"><?= e($today['blockers'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Notes</label><textarea name="notes"><?= e($today['notes'] ?? '') ?></textarea></div>
        <button class="btn btn-primary"><?= $today ? 'Update Report' : 'Submit Report' ?></button>
    </form>
</div>

<?php if ($canViewTeam): ?>
    <?php
        $prevMonth = $calMonth - 1; $prevYear = $calYear;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $nextMonth = $calMonth + 1; $nextYear = $calYear;
        if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
        
        $firstDayOfMonth = sprintf('%04d-%02d-01', $calYear, $calMonth);
        $daysInMonth = (int)date('t', strtotime($firstDayOfMonth));
        $startDayOfWeek = (int)date('N', strtotime($firstDayOfMonth)); // 1=Mon, 7=Sun
    ?>
    <div class="card-title" style="margin-top:20px; display:flex; justify-content:space-between; align-items:center;">
        <span>Team Reports Calendar</span>
        <div class="btn-group">
            <a href="<?= url('reports', ['year'=>$prevYear, 'month'=>$prevMonth]) ?>" class="btn btn-sm">&laquo; Prev</a>
            <span class="btn btn-sm" style="pointer-events:none; font-weight:bold; background:var(--bg-hover)"><?= date('F Y', strtotime($firstDayOfMonth)) ?></span>
            <a href="<?= url('reports', ['year'=>$nextYear, 'month'=>$nextMonth]) ?>" class="btn btn-sm">Next &raquo;</a>
        </div>
    </div>
    
    <div class="calendar-grid">
        <div class="cal-header">Mon</div><div class="cal-header">Tue</div><div class="cal-header">Wed</div>
        <div class="cal-header">Thu</div><div class="cal-header">Fri</div><div class="cal-header">Sat</div><div class="cal-header">Sun</div>
        <?php for ($i = 1; $i < $startDayOfWeek; $i++): ?><div class="cal-cell empty"></div><?php endfor; ?>
        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
            <?php 
                $currentDate = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $d);
                $isToday = $currentDate === date('Y-m-d');
                $reports = $monthlyReports[$currentDate] ?? [];
            ?>
            <div class="cal-cell <?= $isToday ? 'today' : '' ?>" style="cursor: pointer;" onclick="openReportsModal('<?= $currentDate ?>')">
                <div class="cal-date"><?= $d ?></div>
                <div class="cal-events" style="margin-top:8px;">
                    <?php if (count($reports) > 0): ?>
                        <div class="text-muted small" style="margin-bottom:4px; font-weight:bold; color:var(--primary);"><?= count($reports) ?> report(s)</div>
                        <?php foreach (array_slice($reports, 0, 3) as $rep): ?>
                            <div class="badge badge-secondary" style="display:block; margin-bottom:2px; font-size:10px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                <?= e($rep['user_name']) ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($reports) > 3): ?>
                            <div class="text-muted" style="font-size:10px;">+<?= count($reports) - 3 ?> more</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<div class="card-title" style="margin-top:40px"><?= $canViewTeam ? 'Month-wise List View' : 'My Report History' ?></div>
<form class="filters-bar" method="get">
    <input type="hidden" name="page" value="reports">
    <?php if ($canViewTeam): ?>
    <div class="form-group"><label>Employee</label>
        <select name="user_id"><option value="">All</option>
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= (string)$filters['user_id']===(string)$u['id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="form-group"><label>From</label><input type="date" name="date_from" value="<?= e($filters['date_from']) ?>"></div>
    <div class="form-group"><label>To</label><input type="date" name="date_to" value="<?= e($filters['date_to']) ?>"></div>
    <button class="btn btn-primary btn-sm">Filter</button>
</form>

<?php foreach ($rows as $r): ?>
    <div class="card">
        <div class="flex-between"><strong><?= e($r['user_name']) ?></strong><span class="text-muted small"><?= format_date($r['report_date']) ?></span></div>
        <?php if ($r['work_completed']): ?><p><strong>Completed:</strong> <?= nl2br(e($r['work_completed'])) ?></p><?php endif; ?>
        <?php if ($r['pending_work']): ?><p><strong>Pending:</strong> <?= nl2br(e($r['pending_work'])) ?></p><?php endif; ?>
        <?php if ($r['blockers']): ?><p><strong>Blockers:</strong> <?= nl2br(e($r['blockers'])) ?></p><?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if (empty($rows)): ?><p class="text-muted">No reports found.</p><?php endif; ?>
<?php render('partials/pagination', ['p' => $p]); ?>

<!-- Modal for Viewing Day Reports -->
<div class="modal" id="reportsModal">
    <div class="modal-content" style="max-width: 800px; max-height: 80vh; overflow-y: auto;">
        <div class="flex-between" style="position: sticky; top: 0; background: var(--bg); padding-bottom: 15px; border-bottom: 1px solid var(--border); margin-bottom: 20px; z-index: 10;">
            <h2 style="margin: 0;" id="modalDateTitle">Reports for ...</h2>
            <button class="btn btn-sm" data-modal-close>&times;</button>
        </div>
        <div id="modalReportsContainer">
            <p class="text-muted">Loading...</p>
        </div>
    </div>
</div>

<script>
function openReportsModal(dateStr) {
    const modal = document.getElementById('reportsModal');
    const title = document.getElementById('modalDateTitle');
    const container = document.getElementById('modalReportsContainer');
    
    title.textContent = 'Reports for ' + dateStr;
    container.innerHTML = '<p class="text-muted">Loading...</p>';
    
    modal.classList.add('active');
    
    fetch('?page=reports&action=ajax_day&date=' + dateStr)
        .then(res => res.json())
        .then(data => {
            if (data.length === 0) {
                container.innerHTML = '<p class="text-muted">No reports submitted on this day.</p>';
                return;
            }
            
            let html = '';
            data.forEach(rep => {
                html += '<div class="card" style="border: 1px solid var(--border); margin-bottom: 16px;">';
                html += '<div style="font-weight: bold; font-size: 1.1em; margin-bottom: 12px; color: var(--primary);">' + escapeHtml(rep.user_name) + '</div>';
                
                if (rep.work_completed) html += '<p><strong>Completed:</strong><br>' + escapeHtml(rep.work_completed).replace(/\\n/g, '<br>') + '</p>';
                if (rep.tasks_worked_on) html += '<p><strong>Tasks Worked On:</strong><br>' + escapeHtml(rep.tasks_worked_on).replace(/\\n/g, '<br>') + '</p>';
                if (rep.pending_work) html += '<p><strong>Pending:</strong><br>' + escapeHtml(rep.pending_work).replace(/\\n/g, '<br>') + '</p>';
                if (rep.blockers) html += '<p><strong>Blockers:</strong><br>' + escapeHtml(rep.blockers).replace(/\\n/g, '<br>') + '</p>';
                if (rep.notes) html += '<p><strong>Notes:</strong><br>' + escapeHtml(rep.notes).replace(/\\n/g, '<br>') + '</p>';
                
                html += '</div>';
            });
            container.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = '<p class="text-danger">Failed to load reports.</p>';
        });
}

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

// Close modal when clicking on background or close button
document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', e => {
        e.target.closest('.modal').classList.remove('active');
    });
});
</script>
