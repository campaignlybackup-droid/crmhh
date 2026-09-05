<h1>Daily Reports</h1>

<div class="card" style="max-width:680px">
    <div class="card-title"><?= $today ? "Today's Report (submitted)" : "Submit Today's Report" ?></div>
    <form method="post" action="<?= url('reports', ['action' => 'submit']) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="report_date" value="<?= date('Y-m-d') ?>">
        <div class="form-group"><label>Work Completed</label><textarea name="work_completed"><?= e($today['work_completed'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Tasks Worked On</label><textarea name="tasks_worked_on"><?= e($today['tasks_worked_on'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Pending Work</label><textarea name="pending_work"><?= e($today['pending_work'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Blockers</label><textarea name="blockers"><?= e($today['blockers'] ?? '') ?></textarea></div>
        <div class="form-group"><label>Notes</label><textarea name="notes"><?= e($today['notes'] ?? '') ?></textarea></div>
        <button class="btn btn-primary"><?= $today ? 'Update Report' : 'Submit Report' ?></button>
    </form>
</div>

<div class="card-title" style="margin-top:20px"><?= $canViewTeam ? 'Team Reports' : 'My Report History' ?></div>
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
    <div class="card">
        <div class="flex-between"><strong><?= e($r['user_name']) ?></strong><span class="text-muted small"><?= format_date($r['report_date']) ?></span></div>
        <?php if ($r['work_completed']): ?><p><strong>Completed:</strong> <?= nl2br(e($r['work_completed'])) ?></p><?php endif; ?>
        <?php if ($r['pending_work']): ?><p><strong>Pending:</strong> <?= nl2br(e($r['pending_work'])) ?></p><?php endif; ?>
        <?php if ($r['blockers']): ?><p><strong>Blockers:</strong> <?= nl2br(e($r['blockers'])) ?></p><?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if (empty($rows)): ?><p class="text-muted">No reports found.</p><?php endif; ?>
<?php render('partials/pagination', ['p' => $p]); ?>
