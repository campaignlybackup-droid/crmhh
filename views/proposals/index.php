<div class="flex-between">
    <h1>Proposals</h1>
    <?php if ($canManage): ?>
        <a href="<?= url('proposals', ['action' => 'create']) ?>" class="btn btn-primary">+ New Proposal</a>
    <?php endif; ?>
</div>

<form class="filters-bar" method="get">
    <input type="hidden" name="page" value="proposals">
    <div class="form-group"><label>Status</label>
        <select name="status">
            <option value="">All</option>
            <option value="pending" <?= $filters['status']==='pending'?'selected':'' ?>>Pending</option>
            <option value="in_progress" <?= $filters['status']==='in_progress'?'selected':'' ?>>In Progress</option>
            <option value="ready" <?= $filters['status']==='ready'?'selected':'' ?>>Ready</option>
            <option value="sent" <?= $filters['status']==='sent'?'selected':'' ?>>Sent</option>
        </select>
    </div>
    <button class="btn btn-primary btn-sm">Filter</button>
    <a href="<?= url('proposals') ?>" class="btn btn-sm">Reset</a>
</form>

<div class="card">
    <div class="table-wrap responsive-table">
    <table class="table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Client/Lead</th>
                <th>Priority</th>
                <th>Assigned To</th>
                <th>Deadline</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="text-muted text-center">No proposals found.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <?php
                $isOverdue = (strtotime($r['deadline_at']) < time() && !in_array($r['status'], ['won', 'lost', 'delivered']));
                
                $badge = match($r['status']) {
                    'won' => 'success',
                    'lost' => 'danger',
                    'delivered' => 'primary',
                    'sent' => 'info',
                    default => 'secondary'
                };
                $priBadge = match($r['priority']) {
                    'High' => 'warning',
                    'Urgent' => 'danger',
                    default => 'secondary'
                };
            ?>
            <tr <?= $isOverdue ? 'style="background: #fff3f3;"' : '' ?>>
                <td data-label="Title"><strong><?= e($r['title']) ?></strong></td>
                <td data-label="Client/Lead">
                    <?php if ($r['client_name']): ?>
                        <a href="<?= url('clients', ['action' => 'view', 'id' => $r['client_id']]) ?>" class="badge badge-primary">Client: <?= e($r['client_name']) ?></a>
                    <?php elseif ($r['lead_name']): ?>
                        <a href="<?= url('leads', ['action' => 'view', 'id' => $r['lead_id']]) ?>" class="badge badge-secondary">Lead: <?= e($r['lead_name']) ?></a>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td data-label="Priority"><span class="badge badge-<?= $priBadge ?>"><?= e($r['priority']) ?></span></td>
                <td data-label="Assigned To"><?= e($r['assigned_name'] ?: 'Unassigned') ?></td>
                <td data-label="Deadline">
                    <span class="<?= $isOverdue ? 'text-danger' : '' ?>">
                        <?= format_datetime($r['deadline_at']) ?>
                    </span>
                </td>
                <td data-label="Status"><span class="badge badge-<?= $badge ?>"><?= ucfirst(str_replace('_', ' ', e($r['status']))) ?></span></td>
                <td data-label="Actions"><a href="<?= url('proposals', ['action' => 'view', 'id' => $r['id']]) ?>" class="btn btn-sm">View</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php render('partials/pagination', ['p' => $p]); ?>
</div>
