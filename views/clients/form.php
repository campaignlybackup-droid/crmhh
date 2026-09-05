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
    </div>
    <div class="form-group"><label>Google Drive Link</label><input type="url" name="drive_link" value="<?= e($client['drive_link'] ?? '') ?>" placeholder="https://drive.google.com/..."></div>
    <div class="form-group"><label>Notes</label><textarea name="notes"><?= e($client['notes'] ?? '') ?></textarea></div>
    <button class="btn btn-primary"><?= $client ? 'Save Changes' : 'Create Client' ?></button>
    <a href="<?= url('clients') ?>" class="btn">Cancel</a>
</form>
</div>
