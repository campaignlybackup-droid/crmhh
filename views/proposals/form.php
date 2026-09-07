<div class="flex-between">
    <h1>New Proposal Request</h1>
    <a href="<?= url('proposals') ?>" class="btn">Back</a>
</div>

<div class="card" style="max-width:800px">
    <form method="post" action="<?= url('proposals', ['action' => 'store']) ?>">
        <?= Csrf::field() ?>
        
        <div class="form-group">
            <label>Business Name / Proposal Title *</label>
            <input type="text" name="title" class="form-control" required placeholder="e.g. Nike Social Media Pitch">
        </div>
        
        <div class="form-group">
            <label>Business Details & Requirements *</label>
            <textarea name="business_details" class="form-control" rows="8" required placeholder="Describe what the proposal needs to cover..."></textarea>
        </div>
        
        <div class="grid grid-3">
            <div class="form-group">
                <label>Priority</label>
                <select name="priority" class="form-control">
                    <option value="Low">Low</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="High">High</option>
                    <option value="Urgent">Urgent</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Deadline (in Hours) *</label>
                <select name="deadline_hours" class="form-control" required>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $i === 6 ? 'selected' : '' ?>><?= $i ?> Hour<?= $i > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                    <option value="24">24 Hours</option>
                    <option value="48">48 Hours</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Assign To (Optional)</label>
                <select name="assigned_user_id" class="form-control">
                    <option value="">-- Unassigned --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">Submit Proposal Request</button>
    </form>
</div>
