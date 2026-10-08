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
        <div class="form-group"><label>Start Date</label><input type="date" name="start_date" id="client_start_date" value="<?= e($client['start_date'] ?? '') ?>"></div>
        <div class="form-group"><label>Renewal Date</label><input type="date" name="renewal_date" id="client_renewal_date" value="<?= e($client['renewal_date'] ?? '') ?>"></div>
        <?php if (Permission::hasAny(['clients.edit', 'clients.create'])): ?>
        <div class="form-group"><label>Retention Date</label><input type="date" name="retention_date" value="<?= e($client['retention_date'] ?? '') ?>"></div>
        <?php endif; ?>
    </div>

    <?php if ($client): ?>
    <div class="card" id="cycle_reset_notice" style="background:var(--bg); border:1px dashed var(--primary); padding:12px 16px; margin:14px 0 18px; border-radius:8px;">
        <label style="display:flex; align-items:center; gap:8px; font-weight:600; cursor:pointer; font-size:13px; margin:0;">
            <input type="checkbox" name="reset_cycle" id="reset_cycle" value="1" style="width:17px; height:17px;">
            <span>🔄 Reset monthly deliverables (Reels, Posts, Requirements) back to 0 for this new cycle</span>
        </label>
        <div class="text-muted small" style="margin-left:25px; margin-top:4px;">
            Check this when advancing the dates for a new month. This sets Reels, Posts, and Work Requirements completed counts back to 0 so the team starts fresh for the new month, keeping all existing targets and assignments intact.
        </div>
    </div>
    <script>
    (function(){
        var initialStart = <?= json_encode($client['start_date'] ?? '') ?>;
        var initialRenewal = <?= json_encode($client['renewal_date'] ?? '') ?>;
        var startDateInput = document.getElementById('client_start_date');
        var renewalDateInput = document.getElementById('client_renewal_date');
        var resetCheckbox = document.getElementById('reset_cycle');
        var noticeCard = document.getElementById('cycle_reset_notice');

        function checkDateChange() {
            var currentStart = startDateInput ? startDateInput.value : '';
            var currentRenewal = renewalDateInput ? renewalDateInput.value : '';
            // If month or year of start/renewal date changed
            var startMonthChanged = initialStart && currentStart && initialStart.substring(0, 7) !== currentStart.substring(0, 7);
            var renewalMonthChanged = initialRenewal && currentRenewal && initialRenewal.substring(0, 7) !== currentRenewal.substring(0, 7);

            if (startMonthChanged || renewalMonthChanged) {
                if (resetCheckbox && !resetCheckbox.checked) {
                    resetCheckbox.checked = true;
                    if (noticeCard) {
                        noticeCard.style.borderColor = '#10b981';
                        noticeCard.style.background = '#ecfdf5';
                    }
                }
            }
        }

        if (startDateInput) startDateInput.addEventListener('change', checkDateChange);
        if (renewalDateInput) renewalDateInput.addEventListener('change', checkDateChange);
    })();
    </script>
    <?php endif; ?>

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
