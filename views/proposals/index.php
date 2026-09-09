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
    <div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Context</th>
                <th>Assigned To</th>
                <th>Priority</th>
                <th>Deadline</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
            <tr><td colspan="6" class="text-muted text-center">No proposals found.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <?php
                $isOverdue = (strtotime($r['deadline_at']) < time() && !in_array($r['status'], ['ready', 'sent']));
                
                $priBadge = 'secondary';
                if ($r['priority'] === 'High') $priBadge = 'warning';
                if ($r['priority'] === 'Urgent') $priBadge = 'danger';
                
                $statBadge = 'secondary';
                if ($r['status'] === 'in_progress') $statBadge = 'primary';
                if ($r['status'] === 'ready') $statBadge = 'success';
                if ($r['status'] === 'sent') $statBadge = 'success';
            ?>
            <tr <?= $isOverdue ? 'style="background: #fff3f3;"' : '' ?>>
                <td><strong><?= e($r['title']) ?></strong></td>
                <td>
                    <?php if ($r['client_name']): ?>
                        <a href="<?= url('clients', ['action' => 'view', 'id' => $r['client_id']]) ?>" class="badge badge-primary">Client: <?= e($r['client_name']) ?></a>
                    <?php elseif ($r['lead_name']): ?>
                        <a href="<?= url('leads', ['action' => 'view', 'id' => $r['lead_id']]) ?>" class="badge badge-secondary">Lead: <?= e($r['lead_name']) ?></a>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?= e($r['assigned_name'] ?: 'Unassigned') ?></td>
                <td><span class="badge badge-<?= $priBadge ?>"><?= e($r['priority']) ?></span></td>
                <td>
                    <span class="<?= $isOverdue ? 'text-danger' : '' ?>">
                        <?= format_date($r['deadline_at'], true) ?>
                    </span>
                </td>
                <td><span class="badge badge-<?= $statBadge ?>"><?= ucfirst(str_replace('_', ' ', e($r['status']))) ?></span></td>
                <td><a href="<?= url('proposals', ['action' => 'view', 'id' => $r['id']]) ?>" class="btn btn-sm">View</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php render('partials/pagination', ['p' => $p]); ?>
</div>
