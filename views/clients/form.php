<h1><?= $client ? 'Edit Client' : 'New Client' ?></h1>
<div class="card" style="max-width:700px">
<form method="post" action="<?= $client ? url('clients', ['action' => 'update']) : url('clients', ['action' => 'store']) ?>">
    <?= Csrf::field() ?>
    <?php if ($client): ?><input type="hidden" name="id" value="<?= $client['id'] ?>"><?php endif; ?>
    <div class="form-row">
        <div class="form-group"><label>Client / Company Name *</label><input type="text" name="name" value="<?= e($client['name'] ?? '') ?>" required></div>
        <div class="form-group"><label>Company (legal)</label><input type="text" name="company" value="<?= e($client['company'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person" value="<?= e($client['contact_person'] ?? '') ?>"></div>
        <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= e($client['phone'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($client['email'] ?? '') ?>"></div>
        <div class="form-group"><label>Website</label><input type="url" name="website" value="<?= e($client['website'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>Status</label>
            <select name="status">
                <option value="active" <?= ($client['status'] ?? 'active')==='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= ($client['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option>
                <option value="on_hold" <?= ($client['status'] ?? '')==='on_hold'?'selected':'' ?>>On Hold</option>
            </select>
        </div>
        <div class="form-group"><label>Start Date</label><input type="date" name="start_date" value="<?= e($client['start_date'] ?? '') ?>"></div>
        <div class="form-group"><label>Renewal Date</label><input type="date" name="renewal_date" value="<?= e($client['renewal_date'] ?? '') ?>"></div>
        <?php if (Permission::hasAny(['clients.edit', 'clients.create'])): ?>
        <div class="form-group"><label>Retention Date</label><input type="date" name="retention_date" value="<?= e($client['retention_date'] ?? '') ?>"></div>
        <?php endif; ?>
    </div>
    <div class="form-group"><label>Google Drive Link</label><input type="url" name="drive_link" value="<?= e($client['drive_link'] ?? '') ?>" placeholder="https://drive.google.com/..."></div>
    <div class="form-group"><label>Notes</label><textarea name="notes"><?= e($client['notes'] ?? '') ?></textarea></div>
    
    <?php if (!$client): ?>
    <hr style="margin:24px 0">
    <div class="card-title" style="margin-bottom:16px">Initial Service &amp; Scope (Optional)</div>
    <div class="form-row">
        <div class="form-group"><label>Primary Service</label>
            <select name="initial_service_id"><option value="">— None —</option><?php foreach ($allServices as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group"><label>Manager</label>
            <select name="initial_manager_id"><option value="">— Unassigned —</option><?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?><?= $m['id'] === Auth::id() ? ' (YOU)' : '' ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group"><label>Assign To</label>
            <select name="initial_assignee_id"><option value="">— Unassigned —</option><?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?><?= $m['id'] === Auth::id() ? ' (YOU)' : '' ?></option><?php endforeach; ?></select>
        </div>
    </div>
    <div class="form-group">
        <label>Scope of Work Details (e.g. Reels, Stories, Pages)</label>
        <div id="scope-builder">
            <div class="form-row" style="margin-bottom:8px">
                <div class="form-group" style="flex:1;margin:0"><input type="text" name="scope_keys[]" placeholder="Metric (e.g. Reels)" class="form-control"></div>
                <div class="form-group" style="flex:1;margin:0 8px"><input type="text" name="scope_values[]" placeholder="Value (e.g. 15)" class="form-control"></div>
            </div>
        </div>
        <button type="button" class="btn btn-sm mt-2" onclick="addScopeRow()">+ Add Detail</button>
    </div>
    <script>
    function addScopeRow() {
        const div = document.createElement('div');
        div.className = 'form-row';
        div.style.marginBottom = '8px';
        div.innerHTML = `
            <div class="form-group" style="flex:1;margin:0"><input type="text" name="scope_keys[]" placeholder="Metric" class="form-control"></div>
            <div class="form-group" style="flex:1;margin:0 8px"><input type="text" name="scope_values[]" placeholder="Value" class="form-control"></div>
            <button type="button" class="btn btn-sm btn-danger" style="margin:0" onclick="this.parentElement.remove()">&times;</button>
        `;
        document.getElementById('scope-builder').appendChild(div);
    }
    </script>
    <?php endif; ?>
    <button class="btn btn-primary"><?= $client ? 'Save Changes' : 'Create Client' ?></button>
    <a href="<?= url('clients') ?>" class="btn">Cancel</a>
</form>
</div>
