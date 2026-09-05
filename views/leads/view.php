<div class="flex-between">
    <h1><?= e($lead['name']) ?> <span class="text-muted small"><?= e($lead['lead_code']) ?></span></h1>
    <div class="btn-group">
        <?php if (Permission::has('leads.edit')): ?><a href="<?= url('leads', ['action' => 'edit', 'id' => $lead['id']]) ?>" class="btn">Edit</a><?php endif; ?>
        <?php if (Permission::has('leads.delete')): ?>
        <form method="post" action="<?= url('leads', ['action' => 'delete']) ?>" style="display:inline" data-confirm="Delete this lead? This can be recovered by an administrator.">
            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $lead['id'] ?>">
            <button class="btn btn-danger">Delete</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-3">
    <div class="card" style="grid-column:span 2">
        <div class="card-title">Lead Details</div>
        <table>
            <tr><td class="text-muted">Phone</td><td><?= e($lead['phone'] ?: '—') ?></td></tr>
            <tr><td class="text-muted">Email</td><td><?= e($lead['email'] ?: '—') ?></td></tr>
            <tr><td class="text-muted">Company</td><td><?= e($lead['company'] ?: '—') ?></td></tr>
            <tr><td class="text-muted">Source</td><td><?= e($lead['source'] ?: '—') ?></td></tr>
            <tr><td class="text-muted">Created By</td><td><?= e($lead['created_by_name'] ?? '—') ?> on <?= format_date($lead['created_at']) ?></td></tr>
            <tr><td class="text-muted">Notes</td><td class="wrap"><?= nl2br(e($lead['notes'] ?? '')) ?: '—' ?></td></tr>
        </table>

        <hr>
        <div class="card-title">Activity Timeline</div>
        <form method="post" action="<?= url('leads', ['action' => 'followup']) ?>" class="form-group">
            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $lead['id'] ?>">
            <div class="form-row">
                <div class="form-group" style="flex:2"><textarea name="note" placeholder="Log a call, email, or note&hellip;" required></textarea></div>
                <div class="form-group"><label>Next follow-up</label><input type="date" name="next_followup_date"></div>
            </div>
            <button class="btn btn-primary btn-sm">Add Follow-up</button>
        </form>
        <ul class="timeline">
            <?php foreach ($timeline as $t): ?>
                <li>
                    <strong><?= e($t['user_name'] ?? 'System') ?></strong> &mdash; <?= e(humanize($t['action'])) ?>
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
            <span class="badge" style="background:<?= e($lead['status_color']) ?>"><?= e($lead['status_name']) ?></span>
            <?php if (Permission::has('leads.edit')): ?>
            <form method="post" action="<?= url('leads', ['action' => 'status']) ?>" class="mt-2">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $lead['id'] ?>">
                <select name="status_id" class="quick-edit-select">
                    <?php foreach ($statuses as $s): ?><option value="<?= $s['id'] ?>" <?= $s['id']==$lead['status_id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-title">Assignment</div>
            <p><?= e($lead['assigned_name'] ?? 'Unassigned') ?></p>
            <?php if (Permission::has('leads.assign')): ?>
            <form method="post" action="<?= url('leads', ['action' => 'assign']) ?>">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $lead['id'] ?>">
                <select name="assigned_user_id" class="quick-edit-select">
                    <option value="">— Select —</option>
                    <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $u['id']==$lead['assigned_user_id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-title">Next Follow-up</div>
            <p><?= $lead['next_followup_date'] ? format_date($lead['next_followup_date']) : 'Not scheduled' ?></p>
        </div>
    </div>
</div>
