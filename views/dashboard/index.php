<div class="flex-between" style="align-items:flex-start; margin-bottom: 20px;">
    <div>
        <h1 style="margin: 0;">Welcome back, <?= e(explode(' ', $currentUser['name'])[0]) ?></h1>
        <p class="text-muted" style="margin: 4px 0 0;">
            <?php if (Auth::hasRole('founder')): ?>👑 Founder &middot; Complete Agency Overview<?php else: ?>
            <?= e(implode(', ', array_column($roles, 'name'))) ?>
            <?php endif; ?>
        </p>
    </div>
    <div style="display:flex; align-items:center; gap:12px;">
        <span id="live-indicator" style="display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--muted); background:var(--surface); border:1px solid var(--border); padding:6px 12px; border-radius:20px;">
            <span style="width:8px; height:8px; border-radius:50%; background:var(--success); display:inline-block; box-shadow:0 0 8px var(--success);"></span>
            Live Updates Active &middot; <span id="last-updated-text">Just now</span>
        </span>
        <button class="btn btn-secondary btn-sm" onclick="manualRefreshDashboard()" id="manual-refresh-btn" title="Refresh Dashboard Data">
            ↻ Refresh
        </button>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 1. AT RISK & DELAYED FOUNDER ESCALATION CENTER -->
<!-- ========================================================================= -->
<?php if (!empty($clientAtRiskItems)): ?>
<div class="card" style="border: 2px solid var(--danger); background: #fff5f5; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.1);">
    <div class="card-title" style="color:var(--danger); display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px;">
        <span>🚨 Client Delivery At Risk &middot; Founder Escalation Queue (<?= count($clientAtRiskItems) ?> Items)</span>
        <span class="badge badge-danger">Immediate Action Required</span>
    </div>
    <p class="small text-muted" style="margin-top:-6px; margin-bottom:12px;">
        The following client deliverables or operations issues are delayed past deadlines. Founder intervention is requested to reassign or resolve.
    </p>
    <div class="table-wrap">
        <table class="table" style="background:#fff;">
            <thead>
                <tr>
                    <th>Type / Source</th>
                    <th>Client</th>
                    <th>Deliverable / Problem</th>
                    <th>Responsible</th>
                    <th>Delay / Overdue</th>
                    <th>Severity</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientAtRiskItems as $risk): ?>
                <tr>
                    <td><span class="badge badge-secondary" style="font-size:11px;"><?= e($risk['source']) ?></span></td>
                    <td><strong><?= e($risk['client']) ?></strong></td>
                    <td><?= e($risk['item']) ?></td>
                    <td><span class="badge badge-dark"><?= e($risk['responsible']) ?></span></td>
                    <td><span style="color:var(--danger); font-weight:700;"><?= e($risk['delay']) ?></span></td>
                    <td>
                        <?php if ($risk['severity'] === 'critical'): ?>
                            <span class="badge badge-danger">Critical Risk</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Delayed</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= $risk['action_url'] ?>" class="btn btn-sm btn-danger" style="font-size:11px; padding:3px 8px;">Resolve Now</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- 2. HIGH-LEVEL STAT CARDS -->
<!-- ========================================================================= -->
<div class="grid grid-4" style="margin-bottom: 24px;">
    <?php if ($leadCounts): ?>
    <div class="stat-card">
        <div class="stat-label">Total Leads</div>
        <div class="stat-value" id="stat-leads-total"><?= (int)$leadCounts['total'] ?></div>
        <div class="stat-sub"><?= (int)$leadCounts['new_today'] ?> new today &middot; <?= (int)$leadCounts['overdue_followups'] ?> overdue follow-ups</div>
    </div>
    <?php endif; ?>
    <?php if ($clientsVisible): ?>
    <div class="stat-card">
        <div class="stat-label">Active Clients</div>
        <div class="stat-value"><?= (int)$activeClientsCount ?></div>
        <div class="stat-sub"><?= count($renewals) ?> renewing in 30 days</div>
    </div>
    <?php endif; ?>
    <div class="stat-card">
        <div class="stat-label">Pending Tasks</div>
        <div class="stat-value" id="stat-tasks-pending"><?= (int)$taskCounts['pending'] ?></div>
        <div class="stat-sub"><?= (int)$taskCounts['upcoming'] ?> due within 3 days</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Overdue Tasks</div>
        <div class="stat-value" style="color:var(--danger)" id="stat-tasks-overdue"><?= (int)$taskCounts['overdue'] ?></div>
        <div class="stat-sub"><?= (int)$taskCounts['completed'] ?> completed total</div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 3. ALL-TIME TABLE VIEWS (SHOOTS, APPROVALS, DUE TOMORROW) -->
<!-- ========================================================================= -->
<div class="card" style="margin-bottom: 24px; padding: 0; overflow:hidden;">
    <div style="background:var(--bg); border-bottom:1px solid var(--border); padding: 12px 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div class="tabs" style="margin-bottom:0; border-bottom:none;">
            <a href="javascript:void(0)" onclick="switchDashboardTable('shoots')" id="tab-btn-shoots" class="active">
                🎬 Shoots Scheduled (<?= count($shootsScheduled) ?>)
            </a>
            <a href="javascript:void(0)" onclick="switchDashboardTable('approvals')" id="tab-btn-approvals">
                ✓ Approval Pending (<?= count($pendingApprovalsList) ?>)
            </a>
            <a href="javascript:void(0)" onclick="switchDashboardTable('due-tomorrow')" id="tab-btn-due-tomorrow">
                ⏳ Due Tomorrow (<?= count($dueTomorrowList) ?>)
            </a>
        </div>
        <span class="text-muted small">Full Table View (All Time)</span>
    </div>

    <!-- TABLE 1: SHOOTS SCHEDULED -->
    <div id="table-sec-shoots" style="display:block; padding:16px;">
        <div class="flex-between" style="margin-bottom:12px;">
            <h3 style="margin:0; font-size:15px;">All Scheduled Shoots (Videography &amp; Photography)</h3>
            <a href="<?= url('calendar') ?>" class="btn btn-sm btn-secondary">Open Full Calendar &rarr;</a>
        </div>
        <?php if (empty($shootsScheduled)): ?>
            <p class="text-muted small" style="text-align:center; padding:32px 0;">No shoots currently scheduled. Use the Calendar to schedule shoots.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Shoot Date &amp; Time</th>
                            <th>Client</th>
                            <th>Shoot Title / Details</th>
                            <th>Location</th>
                            <th>Assigned Crew / Videographer</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shootsScheduled as $shoot): ?>
                        <tr>
                            <td>
                                <strong><?= format_datetime($shoot['start_datetime']) ?></strong>
                            </td>
                            <td><?= e($shoot['client_name'] ?: 'Internal / Agency') ?></td>
                            <td><strong><?= e($shoot['title']) ?></strong></td>
                            <td><?= e($shoot['location'] ?: 'On Site / Client Studio') ?></td>
                            <td><span class="badge badge-dark"><?= e($shoot['crew_name'] ?: 'Unassigned') ?></span></td>
                            <td>
                                <?php if (!empty($shoot['client_id'])): ?>
                                    <a href="<?= url('clients', ['action' => 'view', 'id' => $shoot['client_id']]) ?>" class="btn btn-sm btn-secondary">Client Profile</a>
                                <?php else: ?>
                                    <a href="<?= url('calendar') ?>" class="btn btn-sm btn-secondary">View in Calendar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TABLE 2: APPROVAL PENDING (Double Check Process) -->
    <div id="table-sec-approvals" style="display:none; padding:16px;">
        <div class="flex-between" style="margin-bottom:12px;">
            <h3 style="margin:0; font-size:15px;">Approvals &amp; Double Check Queue (Editor Check ➔ Manager Review)</h3>
            <a href="<?= url('approvals') ?>" class="btn btn-sm btn-secondary">All Approvals &rarr;</a>
        </div>
        <?php if (empty($pendingApprovalsList)): ?>
            <p class="text-muted small" style="text-align:center; padding:32px 0;">No approvals pending. All work is verified and approved!</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Item Title</th>
                            <th>Submitter</th>
                            <th>Editor Quality Check</th>
                            <th>Current Stage</th>
                            <th>Date Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingApprovalsList as $appr): ?>
                        <tr>
                            <td><span class="badge badge-secondary" style="font-size:11px;"><?= e($appr['type']) ?></span></td>
                            <td><strong><?= e($appr['title']) ?></strong></td>
                            <td><?= e($appr['submitter']) ?></td>
                            <td>
                                <?php if ($appr['editor'] !== '—' && $appr['editor'] !== 'Pending'): ?>
                                    <span style="color:var(--info); font-weight:600;">✓ <?= e($appr['editor']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Awaiting Editor Check</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= strpos($appr['stage'], 'Rectification') !== false ? 'badge-danger' : (strpos($appr['stage'], 'Manager') !== false ? 'badge-info' : 'badge-warning') ?>">
                                    <?= e($appr['stage']) ?>
                                </span>
                            </td>
                            <td><?= time_ago($appr['created_at']) ?></td>
                            <td>
                                <a href="<?= $appr['url'] ?>" class="btn btn-sm btn-primary">Review / Decide</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TABLE 3: DUE TOMORROW -->
    <div id="table-sec-due-tomorrow" style="display:none; padding:16px;">
        <div class="flex-between" style="margin-bottom:12px;">
            <h3 style="margin:0; font-size:15px;">Due Tomorrow (<?= format_date($tomorrowDate) ?>)</h3>
            <span class="badge badge-warning">Prepare Deliverables</span>
        </div>
        <?php if (empty($dueTomorrowList)): ?>
            <p class="text-muted small" style="text-align:center; padding:32px 0;">Nothing due tomorrow. All caught up!</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Deliverable / Task</th>
                            <th>Client</th>
                            <th>Assigned Staff</th>
                            <th>Due Time</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dueTomorrowList as $due): ?>
                        <tr>
                            <td><span class="badge badge-secondary" style="font-size:11px;"><?= e($due['type']) ?></span></td>
                            <td><strong><?= e($due['title']) ?></strong></td>
                            <td><?= e($due['client']) ?></td>
                            <td><span class="badge badge-dark"><?= e($due['assignee']) ?></span></td>
                            <td><?= format_datetime($due['due']) ?></td>
                            <td><span class="badge badge-warning"><?= humanize($due['status']) ?></span></td>
                            <td><a href="<?= $due['url'] ?>" class="btn btn-sm btn-secondary">Open</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 4. MASTER PENDING ACTIONS & OVERDUE WORK -->
<!-- ========================================================================= -->
<div class="grid grid-2" style="margin-bottom: 24px;">
    <!-- Overdue Tasks -->
    <div class="card">
        <div class="card-title">
            <span>Overdue Work</span>
            <a href="<?= url('tasks', ['status' => 'overdue']) ?>" class="small">View all</a>
        </div>
        <?php if (empty($overdueTasks)): ?>
            <p class="text-muted small">Nothing overdue. Great work!</p>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Task</th><th>Client</th><th>Assigned</th><th>Days Overdue</th></tr></thead>
            <tbody>
            <?php foreach ($overdueTasks as $t): ?>
                <tr>
                    <td><a href="<?= url('tasks', ['action' => 'view', 'id' => $t['id']]) ?>" style="font-weight:600;"><?= e($t['title']) ?></a></td>
                    <td><?= e($t['client_name'] ?? '—') ?></td>
                    <td><?= e($t['assigned_name'] ?? '—') ?></td>
                    <td><span class="badge badge-danger"><?= (int)$t['days_overdue'] ?>d</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="card">
        <div class="card-title">Recent Activity</div>
        <?php if (empty($recentActivity)): ?>
            <p class="text-muted small">No recent activity.</p>
        <?php else: ?>
        <ul class="timeline" style="max-height:300px; overflow-y:auto;">
            <?php foreach ($recentActivity as $a): ?>
                <li>
                    <strong><?= e($a['user_name'] ?? 'System') ?></strong> <?= e(humanize($a['action'])) ?> a <?= e($a['entity_type']) ?>
                    <?php if ($a['new_value']): ?> &rarr; <em><?= e($a['new_value']) ?></em><?php endif; ?>
                    <div class="timeline-meta"><?= time_ago($a['created_at']) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for Tab Switching and Auto-Refresh Polling -->
<script>
function switchDashboardTable(tabId) {
    document.getElementById('table-sec-shoots').style.display = (tabId === 'shoots') ? 'block' : 'none';
    document.getElementById('table-sec-approvals').style.display = (tabId === 'approvals') ? 'block' : 'none';
    document.getElementById('table-sec-due-tomorrow').style.display = (tabId === 'due-tomorrow') ? 'block' : 'none';

    document.getElementById('tab-btn-shoots').className = (tabId === 'shoots') ? 'active' : '';
    document.getElementById('tab-btn-approvals').className = (tabId === 'approvals') ? 'active' : '';
    document.getElementById('tab-btn-due-tomorrow').className = (tabId === 'due-tomorrow') ? 'active' : '';
}

function manualRefreshDashboard() {
    var btn = document.getElementById('manual-refresh-btn');
    if (btn) {
        btn.innerText = '↻ Refreshing...';
        btn.disabled = true;
    }
    window.location.reload();
}

// Background poll every 45 seconds to keep counters fresh
var lastSyncSeconds = 0;
setInterval(function() {
    lastSyncSeconds += 5;
    var el = document.getElementById('last-updated-text');
    if (el) {
        if (lastSyncSeconds < 60) el.innerText = lastSyncSeconds + 's ago';
        else el.innerText = Math.floor(lastSyncSeconds / 60) + 'm ago';
    }
}, 5000);

setInterval(function() {
    fetch('<?= url('dashboard', ['action' => 'live_sync']) ?>')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data && data.success) {
                lastSyncSeconds = 0;
                var el = document.getElementById('last-updated-text');
                if (el) el.innerText = 'Just now';
                
                if (data.leads && document.getElementById('stat-leads-total')) {
                    document.getElementById('stat-leads-total').innerText = data.leads.total;
                }
                if (data.tasks) {
                    if (document.getElementById('stat-tasks-pending')) {
                        document.getElementById('stat-tasks-pending').innerText = data.tasks.pending;
                    }
                    if (document.getElementById('stat-tasks-overdue')) {
                        document.getElementById('stat-tasks-overdue').innerText = data.tasks.overdue;
                    }
                }
            }
        }).catch(function(err) {});
}, 45000);
</script>
