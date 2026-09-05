<div class="flex-between">
    <h1>Tasks</h1>
    <?php if (Permission::has('tasks.create')): ?><a href="<?= url('tasks', ['action' => 'create']) ?>" class="btn btn-primary">+ New Task</a><?php endif; ?>
</div>

<form class="filters-bar" method="get">
    <input type="hidden" name="page" value="tasks">
    <div class="form-group"><label>Search</label><input type="text" name="search" value="<?= e($filters['search']) ?>" placeholder="Task title or code&hellip;"></div>
    <div class="form-group"><label>Status</label>
        <select name="status"><option value="">All</option>
            <option value="overdue" <?= $filters['status']==='overdue'?'selected':'' ?>>Overdue</option>
            <?php foreach (TaskModel::STATUSES as $s): ?><option value="<?= $s ?>" <?= $filters['status']===$s?'selected':'' ?>><?= e(humanize($s)) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php if (Permission::has('tasks.view_all')): ?>
    <div class="form-group"><label>Assigned To</label>
        <select name="assigned_user_id"><option value="">All</option>
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= (string)$filters['assigned_user_id']===(string)$u['id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="form-group"><label>Client</label>
        <select name="client_id"><option value="">All</option>
            <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>" <?= (string)$filters['client_id']===(string)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><label>Priority</label>
        <select name="priority"><option value="">All</option>
            <?php foreach (['low','medium','high','urgent'] as $p): ?><option value="<?= $p ?>" <?= $filters['priority']===$p?'selected':'' ?>><?= e(humanize($p)) ?></option><?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-primary btn-sm">Filter</button>
    <a href="<?= url('tasks') ?>" class="btn btn-sm">Reset</a>
</form>

<div class="table-wrap">
<table>
<thead><tr><th>Task</th><th>Client</th><th>Assigned</th><th>Priority</th><th>Status</th><th>Deadline</th></tr></thead>
<tbody>
<?php if (empty($rows)): ?><tr><td colspan="6" class="text-muted">No tasks found.</td></tr><?php endif; ?>
<?php foreach ($rows as $t): $overdue = is_overdue($t['deadline'], $t['status']); ?>
    <tr>
        <td><a href="<?= url('tasks', ['action' => 'view', 'id' => $t['id']]) ?>"><?= e($t['title']) ?></a></td>
        <td><?= e($t['client_name'] ?? '—') ?></td>
        <td><?= e($t['assigned_name'] ?? 'Unassigned') ?></td>
        <td><span class="badge badge-secondary"><?= e(humanize($t['priority'])) ?></span></td>
        <td><span class="badge badge-<?= status_badge_class($overdue ? 'overdue' : $t['status']) ?>"><?= $overdue ? 'Overdue' : e(humanize($t['status'])) ?></span></td>
        <td><?= $t['deadline'] ? format_datetime($t['deadline']) : '—' ?></td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php render('partials/pagination', ['p' => $p]); ?>
