<div class="flex-between">
    <h1>Clients</h1>
    <?php if (Permission::has('clients.create')): ?><a href="<?= url('clients', ['action' => 'create']) ?>" class="btn btn-primary">+ New Client</a><?php endif; ?>
</div>

<form class="filters-bar" method="get">
    <input type="hidden" name="page" value="clients">
    <div class="form-group"><label>Search</label><input type="text" name="search" value="<?= e($filters['search']) ?>" placeholder="Client name, company&hellip;"></div>
    <div class="form-group"><label>Status</label>
        <select name="status"><option value="">All</option>
            <option value="active" <?= $filters['status']==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $filters['status']==='inactive'?'selected':'' ?>>Inactive</option>
            <option value="on_hold" <?= $filters['status']==='on_hold'?'selected':'' ?>>On Hold</option>
        </select>
    </div>
    <div class="form-group"><label>Service</label>
        <select name="service_id"><option value="">All</option>
            <?php foreach ($allServices as $s): ?><option value="<?= $s['id'] ?>" <?= (string)$filters['service_id']===(string)$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><label>Renewal</label>
        <select name="renewal"><option value="">Any</option>
            <option value="upcoming" <?= $filters['renewal']==='upcoming'?'selected':'' ?>>Next 30 Days</option>
            <option value="overdue" <?= $filters['renewal']==='overdue'?'selected':'' ?>>Overdue</option>
        </select>
    </div>
    <button class="btn btn-primary btn-sm">Filter</button>
    <a href="<?= url('clients') ?>" class="btn btn-sm">Reset</a>
</form>

<div class="grid grid-3">
<?php if (empty($rows)): ?><p class="text-muted">No clients found.</p><?php endif; ?>
<?php foreach ($rows as $c): ?>
    <a href="<?= url('clients', ['action' => 'view', 'id' => $c['id']]) ?>" class="client-card">
        <div class="flex-between">
            <h3><?= e($c['name']) ?></h3>
            <span class="badge badge-<?= status_badge_class($c['status']) ?>"><?= e(humanize($c['status'])) ?></span>
        </div>
        <div class="text-muted small"><?= e($c['company'] ?: '') ?></div>
        <div class="services-tags">
            <?php foreach ($c['services'] as $s): ?>
                <span class="tag"><?= e($s['service_name']) ?>: <?= (int)$s['quantity_completed'] ?>/<?= (int)$s['quantity_required'] ?></span>
            <?php endforeach; ?>
            <?php if (empty($c['services'])): ?><span class="text-muted small">No services configured</span><?php endif; ?>
        </div>
        <div class="small text-muted">Renewal: <?= $c['renewal_date'] ? format_date($c['renewal_date']) : '—' ?></div>
    </a>
<?php endforeach; ?>
</div>
<?php render('partials/pagination', ['p' => $p]); ?>
