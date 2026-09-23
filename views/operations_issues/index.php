<div class="flex-between" style="margin-bottom: 20px;">
    <div>
        <h1 style="margin:0;">Operations Quality &amp; Errors</h1>
        <p class="text-muted" style="margin:4px 0 0;">Track operational mistakes, responsible assignments, deductions, and delay escalations.</p>
    </div>
    <div class="btn-group">
        <?php if ($delayedCount > 0): ?>
            <a href="<?= url('operations_issues', ['delayed_only' => 1]) ?>" class="btn btn-danger" style="animation: pulse 2s infinite;">
                🚨 <?= $delayedCount ?> Delayed / At Risk
            </a>
        <?php endif; ?>
        <a href="<?= url('operations_issues', ['action' => 'create']) ?>" class="btn btn-primary">+ Log Operational Error</a>
    </div>
</div>

<form class="filters-bar" method="get">
    <input type="hidden" name="page" value="operations_issues">
    <div class="form-group">
        <label>Search Issue</label>
        <input type="text" name="search" value="<?= e($filters['search']) ?>" placeholder="Issue title or description&hellip;">
    </div>
    <div class="form-group">
        <label>Status</label>
        <select name="status">
            <option value="">All Statuses</option>
            <option value="assigned" <?= $filters['status']==='assigned'?'selected':'' ?>>Assigned</option>
            <option value="rectifying" <?= $filters['status']==='rectifying'?'selected':'' ?>>In Rectification</option>
            <option value="corrected" <?= $filters['status']==='corrected'?'selected':'' ?>>Corrected</option>
            <option value="escalated_to_founder" <?= $filters['status']==='escalated_to_founder'?'selected':'' ?>>Escalated to Founder</option>
        </select>
    </div>
    <div class="form-group">
        <label>Severity</label>
        <select name="severity">
            <option value="">All</option>
            <option value="minor" <?= $filters['severity']==='minor'?'selected':'' ?>>Minor</option>
            <option value="medium" <?= $filters['severity']==='medium'?'selected':'' ?>>Medium</option>
            <option value="major" <?= $filters['severity']==='major'?'selected':'' ?>>Major</option>
            <option value="critical" <?= $filters['severity']==='critical'?'selected':'' ?>>Critical</option>
        </select>
    </div>
    <?php if (Auth::hasRole('founder') || Auth::hasRole('manager')): ?>
    <div class="form-group">
        <label>Responsible Staff</label>
        <select name="responsible_id">
            <option value="">All Staff</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= (string)$filters['responsible_id']===(string)$u['id']?'selected':'' ?>><?= e($u['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Client</label>
        <select name="client_id">
            <option value="">All Clients</option>
            <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (string)$filters['client_id']===(string)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="form-group" style="padding-bottom: 4px;">
        <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
            <input type="checkbox" name="delayed_only" value="1" <?= !empty($filters['delayed_only']) ? 'checked' : '' ?>>
            <span style="color:var(--danger); font-weight:600;">Only Delayed</span>
        </label>
    </div>
    <button class="btn btn-secondary btn-sm">Filter</button>
    <a href="<?= url('operations_issues') ?>" class="btn btn-sm">Reset</a>
</form>

<div class="table-wrap responsive-table">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Issue / Problem</th>
                <th>Who Noticed</th>
                <th>Who Responsible</th>
                <th>Deduction</th>
                <th>Severity</th>
                <th>Target Date</th>
                <th>Status</th>
                <th>Who Corrected</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="10" class="text-muted" style="text-align:center; padding:32px;">
                        No operational errors recorded. Operations running smoothly!
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): 
                    $isDelayed = (int)$r['is_delayed'] === 1 || (!empty($r['due_date']) && strtotime($r['due_date']) < strtotime('today') && $r['status'] !== 'corrected');
                ?>
                <tr style="<?= $isDelayed ? 'background:#fff1f2; border-left:4px solid var(--danger);' : ($r['status'] === 'corrected' ? 'background:#f0fdf4; border-left:4px solid var(--success);' : '') ?>">
                    <td><strong>#<?= $r['id'] ?></strong></td>
                    <td>
                        <a href="<?= url('operations_issues', ['action' => 'view', 'id' => $r['id']]) ?>" style="font-weight:600;">
                            <?= e($r['issue_title']) ?>
                        </a>
                        <?php if ($r['client_name']): ?>
                            <br><small class="text-muted">Client: <?= e($r['client_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e($r['noticed_by_name']) ?></td>
                    <td>
                        <span class="badge badge-dark"><?= e($r['responsible_name']) ?></span>
                    </td>
                    <td>
                        <?php if ((float)$r['deduction_amount'] > 0): ?>
                            <span class="badge badge-danger">₹<?= number_format((float)$r['deduction_amount'], 2) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                            $sevBadge = 'badge-secondary';
                            if ($r['severity'] === 'critical') $sevBadge = 'badge-danger';
                            elseif ($r['severity'] === 'major') $sevBadge = 'badge-warning';
                            elseif ($r['severity'] === 'medium') $sevBadge = 'badge-info';
                        ?>
                        <span class="badge <?= $sevBadge ?>"><?= ucfirst(e($r['severity'])) ?></span>
                    </td>
                    <td>
                        <?php if ($r['due_date']): ?>
                            <span style="<?= $isDelayed ? 'color:var(--danger); font-weight:700;' : '' ?>">
                                <?= format_date($r['due_date']) ?>
                            </span>
                            <?php if ($isDelayed): ?>
                                <br><small class="badge badge-danger" style="font-size:9px;">DELAYED</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'corrected'): ?>
                            <span class="badge badge-success">✓ Corrected</span>
                        <?php elseif ($r['status'] === 'escalated_to_founder'): ?>
                            <span class="badge badge-danger">🚨 Escalated to Founder</span>
                        <?php else: ?>
                            <span class="badge badge-warning"><?= humanize($r['status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['corrected_by_name']): ?>
                            <span style="color:var(--success); font-weight:500;"><?= e($r['corrected_by_name']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= url('operations_issues', ['action' => 'view', 'id' => $r['id']]) ?>" class="btn btn-sm btn-secondary">Details</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (!empty($p['totalPages']) && $p['totalPages'] > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $p['totalPages']; $i++): ?>
        <a href="<?= url('operations_issues', array_merge($filters, ['p' => $i])) ?>" class="<?= $i === $p['page'] ? 'current' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
