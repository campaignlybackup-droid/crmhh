<h1><?= $lead ? 'Edit Lead' : 'New Lead' ?></h1>
<div class="card" style="max-width:640px">
<form method="post" action="<?= $lead ? url('leads', ['action' => 'update']) : url('leads', ['action' => 'store']) ?>">
    <?= Csrf::field() ?>
    <?php if ($lead): ?><input type="hidden" name="id" value="<?= $lead['id'] ?>"><?php endif; ?>
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
    <div class="form-group"><label>Notes</label><textarea name="notes"><?= e($lead['notes'] ?? '') ?></textarea></div>
    <button class="btn btn-primary"><?= $lead ? 'Save Changes' : 'Create Lead' ?></button>
    <a href="<?= url('leads') ?>" class="btn">Cancel</a>
</form>
</div>
