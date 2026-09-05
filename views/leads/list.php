<div class="flex-between">
    <h1>Leads</h1>
    <div class="btn-group">
        <?php if (Permission::has('leads.import')): ?><a href="<?= url('leads', ['action' => 'import']) ?>" class="btn">Import CSV</a><?php endif; ?>
        <?php if (Permission::has('leads.export')): ?><a href="<?= url('leads', ['action' => 'export'] + $filters) ?>" class="btn">Export CSV</a><?php endif; ?>
        <?php if (Permission::has('leads.create')): ?><a href="<?= url('leads', ['action' => 'create']) ?>" class="btn btn-primary">+ New Lead</a><?php endif; ?>
    </div>
</div>

<form class="filters-bar" method="get">
    <input type="hidden" name="page" value="leads">
    <div class="form-group">
        <label>Search</label>
        <input type="text" name="search" value="<?= e($filters['search']) ?>" placeholder="Name, phone, email, code&hellip;">
    </div>
    <div class="form-group">
        <label>Status</label>
        <select name="status_id"><option value="">All</option>
            <?php foreach ($statuses as $s): ?><option value="<?= $s['id'] ?>" <?= (string)$filters['status_id'] === (string)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php if (Permission::has('leads.view_all')): ?>
    <div class="form-group">
        <label>Assigned To</label>
        <select name="assigned_user_id"><option value="">All</option>
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= (string)$filters['assigned_user_id'] === (string)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="form-group">
        <label>Source</label>
        <select name="source"><option value="">All</option>
            <?php foreach ($sources as $s): ?><option value="<?= e($s) ?>" <?= $filters['source'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Follow-up</label>
        <select name="followup"><option value="">Any</option>
            <option value="today" <?= $filters['followup']==='today'?'selected':'' ?>>Due Today</option>
            <option value="overdue" <?= $filters['followup']==='overdue'?'selected':'' ?>>Overdue</option>
            <option value="upcoming" <?= $filters['followup']==='upcoming'?'selected':'' ?>>Next 7 Days</option>
        </select>
    </div>
    <button class="btn btn-primary btn-sm">Filter</button>
    <a href="<?= url('leads') ?>" class="btn btn-sm">Reset</a>
</form>

<div class="table-wrap">
<table>
<thead><tr><th>Lead ID</th><th>Name</th><th>Phone</th><th>Company</th><th>Status</th><th>Assigned To</th><th>Next Follow-up</th><th>Created</th></tr></thead>
<tbody>
<?php if (empty($rows)): ?>
    <tr><td colspan="8" class="text-muted">No leads found.</td></tr>
<?php endif; ?>
<?php foreach ($rows as $r): ?>
    <tr>
        <td><a href="<?= url('leads', ['action' => 'view', 'id' => $r['id']]) ?>"><?= e($r['lead_code']) ?></a></td>
        <td><?= e($r['name']) ?></td>
        <td><?= e($r['phone']) ?></td>
        <td><?= e($r['company']) ?></td>
        <td><span class="badge" style="background:<?= e($r['status_color']) ?>"><?= e($r['status_name']) ?></span></td>
        <td><?= e($r['assigned_name'] ?? 'Unassigned') ?></td>
        <td><?= format_date($r['next_followup_date']) ?></td>
        <td><?= format_date($r['created_at']) ?></td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php render('partials/pagination', ['p' => $p]); ?>
