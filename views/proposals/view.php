<div class="flex-between">
    <h1>Proposal Details</h1>
    <a href="<?= url('proposals') ?>" class="btn">Back</a>
</div>

<div class="grid grid-2">
    <!-- Left Column: Business Details -->
    <div class="card">
        <h2 style="margin-top:0;"><?= e($proposal['title']) ?></h2>
        
        <div class="flex-between" style="margin-bottom: 24px;">
            <div>
                <div class="text-muted small">Requested By</div>
                <div><?= e($proposal['creator_name']) ?></div>
            </div>
            <div>
                <div class="text-muted small">Assigned To</div>
                <div><?= e($proposal['assigned_name'] ?: 'Unassigned') ?></div>
            </div>
            <div>
                <div class="text-muted small">Priority</div>
                <?php
                    $priBadge = 'secondary';
                    if ($proposal['priority'] === 'High') $priBadge = 'warning';
                    if ($proposal['priority'] === 'Urgent') $priBadge = 'danger';
                ?>
                <div><span class="badge badge-<?= $priBadge ?>"><?= e($proposal['priority']) ?></span></div>
            </div>
        </div>

        <div class="text-muted small">Business Details & Requirements</div>
        <div style="background: var(--bg-hover); padding: 16px; border-radius: 4px; margin-bottom: 24px; white-space: pre-wrap; font-family: monospace;"><?= e($proposal['business_details']) ?></div>
        
        <div class="flex-between" style="border-top: 1px solid var(--border); padding-top: 16px;">
            <div>
                <div class="text-muted small">Time Allowed</div>
                <div><?= (int)$proposal['deadline_hours'] ?> Hours</div>
            </div>
            <div style="text-align: right;">
                <div class="text-muted small">Strict Deadline</div>
                <?php $isOverdue = strtotime($proposal['deadline_at']) < time() && !in_array($proposal['status'], ['ready', 'sent']); ?>
                <div class="<?= $isOverdue ? 'text-danger' : '' ?>" style="font-weight: bold;">
                    <?= format_date($proposal['deadline_at'], true) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Status & Delivery -->
    <div class="card">
        <h2 style="margin-top:0;">Status & Delivery</h2>
        
        <div style="margin-bottom: 24px;">
            <div class="text-muted small" style="margin-bottom: 8px;">Current Status</div>
            <?php
                $statBadge = 'secondary';
                if ($proposal['status'] === 'in_progress') $statBadge = 'primary';
                if ($proposal['status'] === 'ready') $statBadge = 'success';
                if ($proposal['status'] === 'sent') $statBadge = 'success';
            ?>
            <span class="badge badge-<?= $statBadge ?>" style="font-size: 1.2em; padding: 8px 16px;">
                <?= ucfirst(str_replace('_', ' ', e($proposal['status']))) ?>
            </span>
        </div>

        <form method="post" action="<?= url('proposals', ['action' => 'update_status']) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= $proposal['id'] ?>">
            
            <div class="form-group">
                <label>Update Status</label>
                <select name="status" class="form-control">
                    <option value="pending" <?= $proposal['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="in_progress" <?= $proposal['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="ready" <?= $proposal['status'] === 'ready' ? 'selected' : '' ?>>Ready for Review</option>
                    <?php if ($canManage): ?>
                        <option value="sent" <?= $proposal['status'] === 'sent' ? 'selected' : '' ?>>Sent to Client</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Notes / Links</label>
                <textarea name="notes" class="form-control" rows="5" placeholder="Paste Google Doc links, Canva links, or notes here..."><?= e($proposal['notes']) ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Updates</button>
        </form>
    </div>
</div>
