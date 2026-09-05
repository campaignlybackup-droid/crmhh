<h1>Welcome back, <?= e(explode(' ', $currentUser['name'])[0]) ?></h1>
<p class="text-muted">
    <?php if (Auth::isFounder()): ?>Founder &middot; complete agency overview<?php else: ?>
    <?= e(implode(', ', array_column($roles, 'name'))) ?>
    <?php endif; ?>
</p>

<div class="grid grid-4" style="margin-top:16px">
    <?php if ($leadCounts): ?>
    <div class="stat-card">
        <div class="stat-label">Leads</div>
        <div class="stat-value"><?= (int)$leadCounts['total'] ?></div>
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
        <div class="stat-value"><?= (int)$taskCounts['pending'] ?></div>
        <div class="stat-sub"><?= (int)$taskCounts['upcoming'] ?> due within 3 days</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Overdue Tasks</div>
        <div class="stat-value" style="color:var(--danger)"><?= (int)$taskCounts['overdue'] ?></div>
        <div class="stat-sub"><?= (int)$taskCounts['completed'] ?> completed total</div>
    </div>
</div>

<div class="grid grid-2" style="margin-top:8px">
    <div class="card">
        <div class="card-title">Overdue Work <a href="<?= url('tasks', ['status' => 'overdue']) ?>" class="small">View all</a></div>
        <?php if (empty($overdueTasks)): ?>
            <p class="text-muted small">Nothing overdue. Great work!</p>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Task</th><th>Client</th><th>Assigned</th><th>Days Overdue</th></tr></thead>
            <tbody>
            <?php foreach ($overdueTasks as $t): ?>
                <tr>
                    <td><a href="<?= url('tasks', ['action' => 'view', 'id' => $t['id']]) ?>"><?= e($t['title']) ?></a></td>
                    <td><?= e($t['client_name'] ?? '—') ?></td>
                    <td><?= e($t['assigned_name'] ?? '—') ?></td>
                    <td><span class="badge badge-danger"><?= (int)$t['days_overdue'] ?>d</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">Recent Activity</div>
        <?php if (empty($recentActivity)): ?>
            <p class="text-muted small">No recent activity.</p>
        <?php else: ?>
        <ul class="timeline">
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

<?php if (!empty($myAssignedServices)): ?>
<div class="card" style="margin-top:8px">
    <div class="card-title">My Assigned Work & Scopes</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Client</th><th>Service</th><th>Assigned Requirement</th><th>Deadline</th><th>Progress</th></tr></thead>
            <tbody>
                <?php foreach ($myAssignedServices as $svc): ?>
                <tr>
                    <td><a href="<?= url('clients', ['action' => 'view', 'id' => $svc['client_id']]) ?>"><?= e($svc['client_name']) ?> <span class="small text-muted">(<?= e($svc['client_code']) ?>)</span></a></td>
                    <td><strong><?= e($svc['service_name']) ?></strong></td>
                    <td>
                        <strong><?= e($svc['requirement_name']) ?></strong>
                        <?php if (!empty($svc['req_notes'])): ?><br><span class="text-muted small"><?= e($svc['req_notes']) ?></span><?php endif; ?>
                    </td>
                    <td><?= format_date($svc['deadline']) ?: '—' ?></td>
                    <td><?= $svc['my_completed'] ?> / <?= $svc['quantity_assigned'] !== null ? $svc['quantity_assigned'] : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-2" style="margin-top:8px">
    <?php if ($clientsVisible && !empty($renewals)): ?>
    <div class="card">
        <div class="card-title">Upcoming Renewals</div>
        <div class="table-wrap"><table>
            <thead><tr><th>Client</th><th>Renewal Date</th></tr></thead>
            <tbody>
            <?php foreach ($renewals as $c): ?>
                <tr><td><a href="<?= url('clients', ['action' => 'view', 'id' => $c['id']]) ?>"><?= e($c['name']) ?></a></td><td><?= format_date($c['renewal_date']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($pendingLeaveApprovals)): ?>
    <div class="card">
        <div class="card-title">Leave Requests Awaiting Your Decision</div>
        <div class="table-wrap"><table>
            <thead><tr><th>Employee</th><th>Dates</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pendingLeaveApprovals as $lr): ?>
                <tr>
                    <td><?= e($lr['user_name']) ?></td>
                    <td><?= format_date($lr['start_date']) ?> &ndash; <?= format_date($lr['end_date']) ?></td>
                    <td><a href="<?= url('leave', ['action' => 'view', 'id' => $lr['id']]) ?>" class="btn btn-sm">Review</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($teamWorkload)): ?>
    <div class="card">
        <div class="card-title">Team Workload</div>
        <div class="table-wrap"><table>
            <thead><tr><th>Member</th><th>Open</th><th>Overdue</th><th>Completed</th></tr></thead>
            <tbody>
            <?php foreach ($teamWorkload as $w): ?>
                <tr><td><?= e($w['name']) ?></td><td><?= (int)$w['open_tasks'] ?></td><td><?= (int)$w['overdue_tasks'] ?></td><td><?= (int)$w['completed_tasks'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">Today's Report <a href="<?= url('reports') ?>" class="small"><?= $todaysReport ? 'Edit' : 'Submit now' ?></a></div>
        <?php if ($todaysReport): ?>
            <p class="small text-success">You've submitted today's report.</p>
        <?php else: ?>
            <p class="small text-muted">You haven't submitted a daily report for today yet.</p>
        <?php endif; ?>
    </div>
</div>
