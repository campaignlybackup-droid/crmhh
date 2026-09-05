<h1><?= $task ? 'Edit Task' : 'New Task' ?></h1>
<div class="card" style="max-width:680px">
<form method="post" action="<?= $task ? url('tasks', ['action' => 'update']) : url('tasks', ['action' => 'store']) ?>">
    <?= Csrf::field() ?>
    <?php if ($task): ?><input type="hidden" name="id" value="<?= $task['id'] ?>"><?php endif; ?>
    <div class="form-group"><label>Title *</label><input type="text" name="title" value="<?= e($task['title'] ?? '') ?>" required></div>
    <div class="form-group"><label>Description</label><textarea name="description"><?= e($task['description'] ?? '') ?></textarea></div>
    <div class="form-row">
        <div class="form-group"><label>Client</label>
            <select name="client_id"><option value="">— None —</option>
                <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>" <?= ((int)($task['client_id'] ?? ($preselectClientId ?? 0)))===$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Service</label>
            <select name="service_id"><option value="">— None —</option>
                <?php foreach ($services as $s): ?><option value="<?= $s['id'] ?>" <?= ($task['service_id'] ?? null)==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-row">
        <?php if (!$task): ?>
        <div class="form-group"><label>Assign To</label>
            <select name="assigned_user_id"><option value="">— Unassigned —</option>
                <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group"><label>Priority</label>
            <select name="priority">
                <?php foreach (['low','medium','high','urgent'] as $p): ?><option value="<?= $p ?>" <?= ($task['priority'] ?? 'medium')===$p?'selected':'' ?>><?= e(humanize($p)) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>Start Date</label><input type="date" name="start_date" value="<?= e($task['start_date'] ?? '') ?>"></div>
        <div class="form-group"><label>Deadline</label><input type="datetime-local" name="deadline" value="<?= $task['deadline'] ?? '' ? date('Y-m-d\TH:i', strtotime($task['deadline'])) : '' ?>"></div>
    </div>
    <?php if (!$task && Auth::isFounder()): ?>
    <div class="form-group checkbox-group"><label><input type="checkbox" name="is_private" value="1"> Private task (only visible to me and the assignee)</label></div>
    <?php endif; ?>
    <div class="form-group"><label>Notes</label><textarea name="notes"><?= e($task['notes'] ?? '') ?></textarea></div>
    <button class="btn btn-primary"><?= $task ? 'Save Changes' : 'Create Task' ?></button>
    <a href="<?= url('tasks') ?>" class="btn">Cancel</a>
</form>
</div>
