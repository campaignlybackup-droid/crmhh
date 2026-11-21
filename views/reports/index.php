<h1>Daily Reports</h1>

<div style="background: var(--bg-hover); border-left: 4px solid var(--danger); padding: 16px; margin-bottom: 24px; border-radius: 4px;">
    <h3 style="margin-top:0; color: var(--danger);">Strict Policy: Please Note</h3>
    <p>All employees are required to send their daily work update in this CRM at the end of each working day. Kindly note the following policy will be strictly implemented:</p>
    <ul style="margin-bottom: 0;">
        <li><strong>No Daily Update Submitted:</strong> If your daily work update is not received, that day will be considered as leave.</li>
        <li><strong>Leave Without Prior Notice:</strong> If anyone takes leave without informing management in advance, double leave deduction (2x days) will be applied.</li>
        <li><strong>Continuous Leave with Sunday/Weekly Off:</strong> If leave is taken continuously attached to Sunday/weekly off/rest day, double leave deduction will also apply.</li>
        <li><strong>Salary Deductions:</strong> Leave deductions from salary will be implemented strictly. Please ensure proper communication to avoid unnecessary deductions.</li>
        <li><strong>Prior Approved Leave:</strong> If leave is informed and approved in advance, and it is not continuous with Sunday/Weekly Rest Day, only the actual leave days will be deducted.</li>
        <li><strong>Urgent Work on Sunday/Weekly Off:</strong> In such cases, it will be treated as company work adjustment/company leave, with no salary deduction.</li>
    </ul>
    <p style="margin-bottom: 0; margin-top: 12px;"><strong>We expect everyone’s cooperation, professionalism, and discipline moving forward. Please take this seriously and ensure compliance. Thank you.</strong></p>
    <p style="margin-bottom: 0; margin-top: 8px; color: var(--danger);"><strong>Note:</strong> New daily reports must be submitted within 36 hours of the workday. Once submitted, you can view and update your report anytime.</p>
</div>

<div class="card" style="max-width:680px">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
        <span><?= !empty($currentReport) ? "Report for " . format_date($selectedDate) . " (submitted)" : "Submit Report for " . format_date($selectedDate) ?></span>
        <?php if ($selectedDate !== date('Y-m-d') || ($isFounder && $selectedUserId !== Auth::id())): ?>
            <a href="<?= url('reports') ?>" class="btn btn-sm btn-secondary">Go to Today's Report</a>
        <?php endif; ?>
    </div>

    <?php if ($isFounder && $selectedUserId !== Auth::id()): ?>
        <div style="background:var(--bg-hover); padding:10px 14px; border-radius:4px; margin-bottom:16px; border-left:3px solid var(--primary); font-size:13px;">
            <strong>Founder Edit Mode:</strong> Updating report on behalf of <strong><?= e(Database::scalar('SELECT name FROM users WHERE id=?', [$selectedUserId]) ?: 'User #' . $selectedUserId) ?></strong>.
        </div>
    <?php endif; ?>

    <form method="post" action="<?= url('reports', ['action' => 'submit']) ?>" id="dailyReportForm">
        <?= Csrf::field() ?>
        <?php if ($isFounder && $selectedUserId !== Auth::id()): ?>
            <input type="hidden" name="user_id" value="<?= (int)$selectedUserId ?>">
        <?php endif; ?>

        <div class="form-group">
            <label>Report Date</label>
            <input type="date" name="report_date" id="reportDateInput" class="form-control"
                   value="<?= e($selectedDate) ?>"
                   max="<?= date('Y-m-d') ?>"
                   required
                   onchange="onReportDateChange(this.value)">
            <small class="text-muted">Select any date to view or update your work report for that day.</small>
        </div>
        <div class="form-group"><label>Work Completed</label><textarea name="work_completed" rows="3"><?= e($currentReport['work_completed'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Tasks Worked On</label><textarea name="tasks_worked_on" rows="3"><?= e($currentReport['tasks_worked_on'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Pending Work</label><textarea name="pending_work" rows="3"><?= e($currentReport['pending_work'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Blockers</label><textarea name="blockers" rows="2"><?= e($currentReport['blockers'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Notes</label><textarea name="notes" rows="2"><?= e($currentReport['notes'] ?? '') ?></textarea></div>
        <div style="display:flex; gap:10px; align-items:center;">
            <button class="btn btn-primary"><?= !empty($currentReport) ? 'Update Report' : 'Submit Report' ?></button>
            <?php if ($selectedDate !== date('Y-m-d') || ($isFounder && $selectedUserId !== Auth::id())): ?>
                <a href="<?= url('reports') ?>" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </div>
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
            <a href="<?= url('reports', ['year'=>$prevMonth < 1 ? $prevYear : $calYear, 'month'=>$prevMonth]) ?>" class="btn btn-sm">&laquo; Prev</a>
            <span class="btn btn-sm" style="pointer-events:none; font-weight:bold; background:var(--bg-hover)"><?= date('F Y', strtotime($firstDayOfMonth)) ?></span>
            <a href="<?= url('reports', ['year'=>$nextMonth > 12 ? $nextYear : $calYear, 'month'=>$nextMonth]) ?>" class="btn btn-sm">Next &raquo;</a>
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
    <?php
        $canEditRow = ($r['user_id'] == Auth::id()) || $isFounder;
        $editParams = ['date' => $r['report_date']];
        if ($isFounder && $r['user_id'] != Auth::id()) {
            $editParams['edit_user_id'] = $r['user_id'];
        }
        $editUrl = url('reports', $editParams);
    ?>
    <div class="card">
        <div class="flex-between">
            <strong><?= e($r['user_name']) ?></strong>
            <div style="display:flex; align-items:center; gap:12px;">
                <span class="text-muted small"><?= format_date($r['report_date']) ?></span>
                <?php if ($canEditRow): ?>
                    <a href="<?= $editUrl ?>" class="btn btn-sm btn-secondary" style="padding:3px 10px; font-size:12px;">Edit / Update</a>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($r['work_completed'])): ?><p style="margin-top:8px;"><strong>Completed:</strong><br><?= nl2br(e($r['work_completed'])) ?></p><?php endif; ?>
        <?php if (!empty($r['tasks_worked_on'])): ?><p><strong>Tasks Worked On:</strong><br><?= nl2br(e($r['tasks_worked_on'])) ?></p><?php endif; ?>
        <?php if (!empty($r['pending_work'])): ?><p><strong>Pending:</strong><br><?= nl2br(e($r['pending_work'])) ?></p><?php endif; ?>
        <?php if (!empty($r['blockers'])): ?><p><strong>Blockers:</strong><br><?= nl2br(e($r['blockers'])) ?></p><?php endif; ?>
        <?php if (!empty($r['notes'])): ?><p><strong>Notes:</strong><br><?= nl2br(e($r['notes'])) ?></p><?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if (empty($rows)): ?><p class="text-muted">No reports found.</p><?php endif; ?>
<?php render('partials/pagination', ['p' => $p]); ?>

<!-- Modal for Viewing Day Reports -->
<div class="modal-overlay" id="reportsModalOverlay">
    <div class="modal" id="reportsModal" style="max-width: 800px; padding: 0;">
        <div class="modal-content" style="max-width: 800px; max-height: 80vh; overflow-y: auto;">
            <div class="flex-between" style="position: sticky; top: 0; background: var(--bg); padding-bottom: 15px; border-bottom: 1px solid var(--border); margin-bottom: 20px; z-index: 10;">
                <h2 style="margin: 0;" id="modalDateTitle">Reports for ...</h2>
                <button type="button" class="btn btn-sm" data-modal-close>&times;</button>
            </div>
            <div id="modalReportsContainer">
                <p class="text-muted">Loading...</p>
            </div>
        </div>
    </div>
</div>

<script>
const currentUserId = <?= (int)Auth::id() ?>;
const isFounder = <?= $isFounder ? 'true' : 'false' ?>;

function onReportDateChange(newDate) {
    if (newDate) {
        let targetUrl = '<?= url('reports') ?>&date=' + encodeURIComponent(newDate);
        <?php if ($isFounder && $selectedUserId !== Auth::id()): ?>
            targetUrl += '&edit_user_id=<?= (int)$selectedUserId ?>';
        <?php endif; ?>
        window.location.href = targetUrl;
    }
}

function openReportsModal(dateStr) {
    const modal = document.getElementById('reportsModal');
    const title = document.getElementById('modalDateTitle');
    const container = document.getElementById('modalReportsContainer');

    title.textContent = 'Reports for ' + dateStr;
    container.innerHTML = '<p class="text-muted">Loading...</p>';

    document.getElementById('reportsModalOverlay').classList.add('show');

    fetch('?page=reports&action=ajax_day&date=' + encodeURIComponent(dateStr))
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.text();
        })
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON', text);
                throw new Error('Invalid JSON response from server');
            }
        })
        .then(data => {
            if (data.length === 0) {
                container.innerHTML = '<p class="text-muted">No reports submitted on this day.</p>';
                return;
            }

            let html = '';
            data.forEach(rep => {
                const canEdit = (rep.user_id == currentUserId) || isFounder;
                let editUrl = 'index.php?page=reports&date=' + encodeURIComponent(rep.report_date);
                if (isFounder && rep.user_id != currentUserId) {
                    editUrl += '&edit_user_id=' + encodeURIComponent(rep.user_id);
                }

                html += '<div class="card" style="border: 1px solid var(--border); margin-bottom: 16px;">';
                html += '<div class="flex-between" style="margin-bottom: 12px; align-items:center;">';
                html += '<div style="font-weight: bold; font-size: 1.1em; color: var(--primary);">' + escapeHtml(rep.user_name) + '</div>';
                if (canEdit) {
                    html += '<a href="' + editUrl + '" class="btn btn-sm btn-secondary" style="padding:3px 10px; font-size:12px;">Edit / Update</a>';
                }
                html += '</div>';

                if (rep.work_completed) html += '<p><strong>Completed:</strong><br>' + escapeHtml(rep.work_completed).replace(/\n/g, '<br>') + '</p>';
                if (rep.tasks_worked_on) html += '<p><strong>Tasks Worked On:</strong><br>' + escapeHtml(rep.tasks_worked_on).replace(/\n/g, '<br>') + '</p>';
                if (rep.pending_work) html += '<p><strong>Pending:</strong><br>' + escapeHtml(rep.pending_work).replace(/\n/g, '<br>') + '</p>';
                if (rep.blockers) html += '<p><strong>Blockers:</strong><br>' + escapeHtml(rep.blockers).replace(/\n/g, '<br>') + '</p>';
                if (rep.notes) html += '<p><strong>Notes:</strong><br>' + escapeHtml(rep.notes).replace(/\n/g, '<br>') + '</p>';

                html += '</div>';
            });
            container.innerHTML = html;
        })
        .catch(err => {
            console.error('Fetch error:', err);
            container.innerHTML = '<p class="text-danger">Failed to load reports. Please try again.</p>';
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
</script>
