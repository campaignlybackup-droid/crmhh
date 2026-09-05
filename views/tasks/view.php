<?php $overdue = is_overdue($task['deadline'], $task['status']); ?>
<div class="flex-between">
    <h1><?= e($task['title']) ?> <span class="text-muted small"><?= e($task['task_code']) ?></span></h1>
    <div class="btn-group">
        <?php if (Permission::has('tasks.edit')): ?><a href="<?= url('tasks', ['action' => 'edit', 'id' => $task['id']]) ?>" class="btn">Edit</a><?php endif; ?>
        <?php if (Permission::has('tasks.delete')): ?>
        <form method="post" action="<?= url('tasks', ['action' => 'delete']) ?>" style="display:inline" data-confirm="Delete this task?">
            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $task['id'] ?>"><button class="btn btn-danger">Delete</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-3">
    <div class="card" style="grid-column:span 2">
        <div class="card-title">Details</div>
        <table>
            <tr><td class="text-muted">Client</td><td><?= $task['client_name'] ? '<a href="'.url('clients',['action'=>'view','id'=>$task['client_id']]).'">'.e($task['client_name']).'</a>' : '—' ?></td></tr>
            <tr><td class="text-muted">Service</td><td><?= e($task['service_name'] ?? '—') ?></td></tr>
            <tr><td class="text-muted">Assigned By</td><td><?= e($task['assigned_by_name'] ?? '—') ?></td></tr>
            <tr><td class="text-muted">Start Date</td><td><?= format_date($task['start_date']) ?: '—' ?></td></tr>
            <tr><td class="text-muted">Deadline</td><td><?= $task['deadline'] ? format_datetime($task['deadline']) : '—' ?></td></tr>
            <tr><td class="text-muted">Description</td><td class="wrap"><?= nl2br(e($task['description'] ?? '')) ?: '—' ?></td></tr>
            <tr><td class="text-muted">Notes</td><td class="wrap"><?= nl2br(e($task['notes'] ?? '')) ?: '—' ?></td></tr>
        </table>

        <hr>
        <div class="card-title">Activity</div>
        <ul class="timeline">
            <?php foreach ($timeline as $t): ?>
                <li><strong><?= e($t['user_name'] ?? 'System') ?></strong> &mdash; <?= e(humanize($t['action'])) ?>
                    <?php if ($t['old_value'] || $t['new_value']): ?>: <em><?= e($t['old_value']) ?> &rarr; <?= e($t['new_value']) ?></em><?php endif; ?>
                    <?php if ($t['note']): ?><div><?= nl2br(e($t['note'])) ?></div><?php endif; ?>
                    <div class="timeline-meta"><?= format_datetime($t['created_at']) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div>
        <div class="card">
            <div class="card-title">Status</div>
            <span class="badge badge-<?= status_badge_class($overdue ? 'overdue' : $task['status']) ?>"><?= $overdue ? 'Overdue' : e(humanize($task['status'])) ?></span>
            <?php if (Permission::has('tasks.edit') || (int)$task['assigned_user_id'] === Auth::id()): ?>
            <form method="post" action="<?= url('tasks', ['action' => 'status']) ?>" class="mt-2">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $task['id'] ?>">
                <select name="status" class="quick-edit-select">
                    <?php foreach (TaskModel::STATUSES as $s): ?><option value="<?= $s ?>" <?= $s===$task['status']?'selected':'' ?>><?= e(humanize($s)) ?></option><?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-title">Priority</div>
            <span class="badge badge-secondary"><?= e(humanize($task['priority'])) ?></span>
        </div>
        <div class="card">
            <div class="card-title">Assigned To</div>
            <p><?= e($task['assigned_name'] ?? 'Unassigned') ?></p>
            <?php if (Permission::has('tasks.assign')): ?>
            <form method="post" action="<?= url('tasks', ['action' => 'reassign']) ?>">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $task['id'] ?>">
                <select name="assigned_user_id" class="quick-edit-select">
                    <option value="">— Select —</option>
                    <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $u['id']==$task['assigned_user_id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
