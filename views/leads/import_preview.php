<h1>Confirm Import</h1>

<div class="grid grid-4">
    <div class="stat-card"><div class="stat-label">Total Rows</div><div class="stat-value"><?= $preview['total'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Will Import</div><div class="stat-value" style="color:var(--success)"><?= $preview['new'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Duplicates</div><div class="stat-value" style="color:var(--warning)"><?= $preview['duplicates'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Invalid Rows</div><div class="stat-value" style="color:var(--danger)"><?= $preview['invalid'] ?></div></div>
</div>

<form method="post" action="<?= url('leads', ['action' => 'import_confirm']) ?>">
    <?= Csrf::field() ?>
    <div class="card">
        <div class="card-title">Column Mapping</div>
        <p class="text-muted small">We matched these columns automatically. Correct any that look wrong before importing.</p>
        <div class="grid grid-3">
        <?php
        $fields = ['name' => 'Name *', 'phone' => 'Phone', 'email' => 'Email', 'company' => 'Company', 'source' => 'Source', 'status' => 'Status', 'notes' => 'Notes'];
        foreach ($fields as $field => $label): ?>
            <div class="form-group">
                <label><?= e($label) ?></label>
                <select name="map[<?= $field ?>]">
                    <option value="">— Not in file —</option>
                    <?php foreach ($headers as $i => $h): ?>
                        <option value="<?= $i ?>" <?= ($mapping[$field] ?? null) === $i ? 'selected' : '' ?>><?= e($h) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>
        </div>

        <?php if (Permission::has('leads.assign')): ?>
        <div class="form-group" style="max-width:300px">
            <label>Assign all imported leads to</label>
            <select name="assign_to">
                <option value="">— Leave unassigned —</option>
                <?php foreach (UserModel::activeSelectList() as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">Sample Rows (first <?= count($preview['sample']) ?> of <?= $preview['total'] ?>)</div>
        <div class="table-wrap"><table>
            <thead><tr><?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ($preview['sample'] as $row): ?>
                <tr><?php foreach ($headers as $i => $h): ?><td><?= e($row[$i] ?? '') ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>

    <button class="btn btn-primary">Confirm &amp; Import <?= $preview['new'] ?> Leads</button>
    <a href="<?= url('leads', ['action' => 'import']) ?>" class="btn">Cancel</a>
</form>
