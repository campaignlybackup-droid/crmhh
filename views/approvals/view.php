<div class="flex-between">
    <h1>Approval Details</h1>
    <a href="<?= url('approvals') ?>" class="btn">Back</a>
</div>

<div class="grid grid-2">
    <!-- Left Column: Details -->
    <div class="card">
        <h2 style="margin-top:0;"><?= e($approval['title']) ?></h2>
        
        <div class="flex-between" style="margin-bottom: 24px;">
            <div>
                <div class="text-muted small">Sender</div>
                <div><?= e($approval['sender_name']) ?></div>
            </div>
            <div>
                <div class="text-muted small">Date Submitted</div>
                <div><?= format_date($approval['created_at']) ?></div>
            </div>
            <div>
                <div class="text-muted small">Status</div>
                <?php
                    $badge = 'secondary';
                    if ($approval['status'] === 'approved') $badge = 'success';
                    if ($approval['status'] === 'rejected') $badge = 'danger';
                    if ($approval['status'] === 'pending') $badge = 'warning';
                ?>
                <div><span class="badge badge-<?= $badge ?>"><?= ucfirst(e($approval['status'])) ?></span></div>
            </div>
        </div>

        <?php if ($approval['description']): ?>
            <div class="text-muted small">Description</div>
            <p style="background: var(--bg-hover); padding: 12px; border-radius: 4px;">
                <?= nl2br(e($approval['description'])) ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Right Column: Review & Notes -->
    <div class="card">
        <h2 style="margin-top:0;">Reviewer Notes</h2>
        
        <?php if ($approval['status'] !== 'pending'): ?>
            <div style="margin-bottom: 16px;">
                <strong>Reviewed by:</strong> <?= e($approval['reviewer_name'] ?: 'Unknown') ?>
            </div>
            <?php if ($approval['reviewer_notes']): ?>
                <div class="text-muted small">Notes:</div>
                <p style="background: var(--bg-hover); padding: 12px; border-radius: 4px;">
                    <?= nl2br(e($approval['reviewer_notes'])) ?>
                </p>
            <?php else: ?>
                <p class="text-muted">No notes left by the reviewer.</p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($approval['status'] === 'pending' && $isReviewer): ?>
            <form method="post" action="<?= url('approvals', ['action' => 'review']) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= $approval['id'] ?>">
                
                <div class="form-group">
                    <label>Leave a Note (Optional)</label>
                    <textarea name="reviewer_notes" class="form-control" rows="4" placeholder="Feedback or reason for approval/rejection..."></textarea>
                </div>
                
                <div class="flex-between">
                    <button type="submit" name="status" value="rejected" class="btn btn-danger">Reject</button>
                    <button type="submit" name="status" value="approved" class="btn btn-success">Approve</button>
                </div>
            </form>
        <?php elseif ($approval['status'] === 'pending' && !$isReviewer): ?>
            <p class="text-muted">Waiting for Founder or Manager to review this request.</p>
        <?php endif; ?>
    </div>
</div>
