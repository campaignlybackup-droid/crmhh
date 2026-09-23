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
        <h2 style="margin-top:0;">Double Check Review Process</h2>

        <!-- Double Check Workflow Status -->
        <div style="background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:12px; margin-bottom:16px;">
            <div style="font-size:12px; font-weight:600; margin-bottom:6px;">Stage 1: Editor Quality Check</div>
            <?php if (!empty($approval['editor_name'])): ?>
                <div style="color:var(--success); font-size:13px;">✓ Verified by Editor: <strong><?= e($approval['editor_name']) ?></strong></div>
                <?php if ($approval['editor_notes']): ?>
                    <small class="text-muted"><?= nl2br(e($approval['editor_notes'])) ?></small>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-muted small">Pending Editor Quality Check</div>
            <?php endif; ?>

            <div style="font-size:12px; font-weight:600; margin-top:10px; margin-bottom:6px;">Stage 2: Manager Review (Manav / Lead)</div>
            <?php if (!empty($approval['reviewer_name'])): ?>
                <div style="color:var(--primary); font-size:13px;">👑 Reviewed by Manager: <strong><?= e($approval['reviewer_name']) ?></strong></div>
                <?php if ($approval['reviewer_notes']): ?>
                    <small class="text-muted"><?= nl2br(e($approval['reviewer_notes'])) ?></small>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-muted small">Pending Manager Final Review</div>
            <?php endif; ?>
        </div>

        <?php if (!empty($approval['rectification_notes'])): ?>
            <div class="alert alert-danger" style="margin-bottom:16px;">
                <strong>⚠️ Issues Flagged for Rectification:</strong><br>
                <?= nl2br(e($approval['rectification_notes'])) ?>
            </div>
        <?php endif; ?>

        <!-- Editor Check Form (Stage 1) -->
        <?php if (($approval['stage'] ?? '') === 'pending_editor' && $approval['status'] === 'pending'): ?>
            <div style="border-top:1px solid var(--border); padding-top:14px;">
                <h3 style="font-size:14px; margin-bottom:8px;">Step 1: Editor Review Action</h3>
                <form method="post" action="<?= url('approvals', ['action' => 'editor_check']) ?>">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= $approval['id'] ?>">
                    <div class="form-group">
                        <label>Editor Notes / Verification Details</label>
                        <textarea name="editor_notes" class="form-control" rows="3" placeholder="Check video cuts, files, attachments, copy, requirements..."></textarea>
                    </div>
                    <div class="flex-between">
                        <button type="submit" name="decision" value="flag_issues" class="btn btn-danger">⚠️ Flag Issues to Rectify</button>
                        <button type="submit" name="decision" value="passed" class="btn btn-primary">✓ Editor Checked &rarr; Send to Manager</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Manager Review Form (Stage 2 - Manav / Manager / Founder) -->
        <?php if (($approval['stage'] ?? '') === 'pending_manager' && $approval['status'] === 'pending' && $isReviewer): ?>
            <div style="border-top:1px solid var(--border); padding-top:14px;">
                <h3 style="font-size:14px; margin-bottom:8px;">Step 2: Manager Review Action (Manav / Lead)</h3>
                <form method="post" action="<?= url('approvals', ['action' => 'review']) ?>">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= $approval['id'] ?>">
                    <div class="form-group">
                        <label>Manager Review Notes</label>
                        <textarea name="reviewer_notes" class="form-control" rows="3" placeholder="Final feedback or reason for decision..."></textarea>
                    </div>
                    <div class="flex-between">
                        <button type="submit" name="status" value="rejected" class="btn btn-danger">Reject</button>
                        <button type="submit" name="status" value="approved" class="btn btn-success">✓ Final Approve</button>
                    </div>
                </form>
            </div>
        <?php elseif ($approval['status'] === 'needs_rectification'): ?>
            <div style="border-top:1px solid var(--border); padding-top:14px;">
                <p class="text-muted small">Issues have been flagged. Once rectified, submit below to re-verify.</p>
                <form method="post" action="<?= url('approvals', ['action' => 'editor_check']) ?>">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= $approval['id'] ?>">
                    <input type="hidden" name="decision" value="passed">
                    <button type="submit" class="btn btn-primary w-100">✓ Issues Rectified &rarr; Re-Submit for Review</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
