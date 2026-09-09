<h1><?= $lead ? 'Edit Lead' : 'New Lead' ?></h1>
<div class="card" style="max-width:640px">
<form method="post" action="<?= $lead ? url('leads', ['action' => 'update']) : url('leads', ['action' => 'store']) ?>">
    <?= Csrf::field() ?>
    <?php if ($lead): ?><input type="hidden" name="id" value="<?= $lead['id'] ?>"><?php endif; ?>
    <?php if (!$lead && !empty($folderId)): ?><input type="hidden" name="folder_id" value="<?= $folderId ?>"><?php endif; ?>
    <div class="form-row">
        <div class="form-group"><label>Name *</label><input type="text" name="name" value="<?= e($lead['name'] ?? '') ?>" required></div>
        <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= e($lead['phone'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($lead['email'] ?? '') ?>"></div>
        <div class="form-group"><label>Company</label><input type="text" name="company" value="<?= e($lead['company'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>Source</label><input type="text" name="source" value="<?= e($lead['source'] ?? '') ?>" placeholder="e.g. Instagram, Referral, Website"></div>
        <div class="form-group"><label>Status</label>
            <select name="status_id">
                <?php foreach ($statuses as $s): ?><option value="<?= $s['id'] ?>" <?= ($lead['status_id'] ?? $statuses[0]['id']) == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-row">
        <?php if (!$lead && Permission::has('leads.assign')): ?>
        <div class="form-group"><label>Assign To</label>
            <select name="assigned_user_id"><option value="">— Unassigned —</option>
                <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group"><label>Next Follow-up</label><input type="date" name="next_followup_date" value="<?= e($lead['next_followup_date'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label>Next Step</label><input type="text" name="next_step" value="<?= e($lead['next_step'] ?? '') ?>"></div>
    <div class="form-group"><label>Notes</label><textarea name="notes"><?= e($lead['notes'] ?? '') ?></textarea></div>
    
    <?php if (!empty($customFields)): ?>
    <hr style="margin:20px 0; border:0; border-top:1px solid var(--border);">
    <h3 style="margin-bottom:15px; font-size:16px;">Custom Fields</h3>
    <div class="grid grid-2">
        <?php foreach ($customFields as $cf): $val = $customValues[$cf['id']] ?? ''; ?>
            <div class="form-group">
                <label><?= e($cf['field_name']) ?></label>
                <?php if ($cf['field_type'] === 'text'): ?>
                    <input type="text" name="custom_fields[<?= $cf['id'] ?>]" value="<?= e($val) ?>">
                <?php elseif ($cf['field_type'] === 'number'): ?>
                    <input type="number" step="any" name="custom_fields[<?= $cf['id'] ?>]" value="<?= e($val) ?>">
                <?php elseif ($cf['field_type'] === 'date'): ?>
                    <input type="date" name="custom_fields[<?= $cf['id'] ?>]" value="<?= e($val) ?>">
                <?php elseif ($cf['field_type'] === 'select'): 
                    $opts = json_decode($cf['options'] ?: '[]', true) ?: []; ?>
                    <select name="custom_fields[<?= $cf['id'] ?>]">
                        <option value="">— Select —</option>
                        <?php foreach ($opts as $opt): ?>
                            <option value="<?= e($opt) ?>" <?= $val === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <button class="btn btn-primary"><?= $lead ? 'Save Changes' : 'Create Lead' ?></button>
    <a href="<?= url('leads') ?>" class="btn">Cancel</a>
</form>
</div>
