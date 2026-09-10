<?php $proposal = $proposal ?? null; ?>
<div class="flex-between">
    <h1><?= $proposal ? 'Edit Proposal' : 'New Proposal Request' ?></h1>
    <a href="<?= url('proposals') ?>" class="btn">Back</a>
</div>

<div class="card" style="max-width:800px">
    <form method="post" action="<?= $proposal ? url('proposals', ['action' => 'update']) : url('proposals', ['action' => 'store']) ?>">
        <?= Csrf::field() ?>
        <?php if ($proposal): ?><input type="hidden" name="id" value="<?= $proposal['id'] ?>"><?php endif; ?>
        
        <div class="form-group">
            <label>Business Name / Proposal Title *</label>
            <input type="text" name="title" class="form-control" value="<?= e($proposal['title'] ?? '') ?>" required placeholder="e.g. Nike Social Media Pitch">
        </div>
        
        <div class="form-group">
            <label>Business Details & Requirements *</label>
            <textarea name="business_details" class="form-control" rows="8" required placeholder="Describe what the proposal needs to cover..."><?= e($proposal['business_details'] ?? '') ?></textarea>
        </div>
        
        <div class="grid grid-2">
            <div class="form-group">
                <label>Related Client (Optional)</label>
                <select name="client_id" class="form-control">
                    <option value="">-- None --</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (($proposal['client_id'] ?? ($preselectClientId ?? 0)) == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Related Lead (Optional)</label>
                <select name="lead_id" class="form-control">
                    <option value="">-- None --</option>
                    <?php foreach ($leads as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= (($proposal['lead_id'] ?? ($preselectLeadId ?? 0)) == $l['id']) ? 'selected' : '' ?>><?= e($l['company']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="grid grid-3">
            <div class="form-group">
                <label>Priority</label>
                <select name="priority" class="form-control">
                    <?php foreach (['Low', 'Medium', 'High', 'Urgent'] as $pri): ?>
                        <option value="<?= $pri ?>" <?= ($proposal['priority'] ?? 'Medium') === $pri ? 'selected' : '' ?>><?= $pri ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Deadline (Hours from now)</label>
                <input type="number" name="deadline_hours" class="form-control" value="<?= e($proposal['deadline_hours'] ?? 24) ?>" min="1" max="720">
            </div>
            
            <div class="form-group">
                <label>Assign To (Optional)</label>
                <select name="assigned_user_id" class="form-control">
                    <option value="">-- Unassigned --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($proposal['assigned_user_id'] ?? null) == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div style="margin-top:24px;">
            <button type="submit" class="btn btn-primary"><?= $proposal ? 'Save Changes' : 'Submit Request' ?></button>
        </div>
    </form>
</div>
