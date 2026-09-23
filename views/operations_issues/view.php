<div style="max-width: 800px; margin: 0 auto;">
    <div class="flex-between" style="margin-bottom: 20px;">
        <div>
            <h1 style="margin:0;">Issue #<?= $issue['id'] ?>: <?= e($issue['issue_title']) ?></h1>
            <div style="margin-top:4px;">
                <?php if ($issue['status'] === 'corrected'): ?>
                    <span class="badge badge-success">✓ Corrected &amp; Rectified</span>
                <?php elseif ($issue['status'] === 'escalated_to_founder' || (int)$issue['is_delayed'] === 1): ?>
                    <span class="badge badge-danger">🚨 Escalated to Founder (Delayed)</span>
                <?php else: ?>
                    <span class="badge badge-warning"><?= humanize($issue['status']) ?></span>
                <?php endif; ?>
                &middot; <span class="text-muted">Reported <?= time_ago($issue['created_at']) ?></span>
            </div>
        </div>
        <div class="btn-group">
            <a href="<?= url('operations_issues') ?>" class="btn">Back to Log</a>
            <?php if ($issue['status'] !== 'corrected' && $issue['status'] !== 'escalated_to_founder'): ?>
            <form method="post" action="<?= url('operations_issues', ['action' => 'escalate_founder']) ?>" style="display:inline;" data-confirm="Escalate this issue immediately to the Founder?">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= $issue['id'] ?>">
                <button type="submit" class="btn btn-danger">🚨 Escalate to Founder</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ((int)$issue['is_delayed'] === 1 || $issue['status'] === 'escalated_to_founder'): ?>
    <div class="alert alert-danger" style="margin-bottom:20px;">
        <strong>⚠️ Founder Priority Escalation:</strong> This operational issue is delayed or has been marked as high risk to client delivery. Immediate rectification is required.
    </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:20px;">
        <div class="card-title">Issue Details &amp; Operational Audit</div>
        <table class="table" style="margin-bottom:0;">
            <tbody>
                <tr>
                    <th style="width:200px;">What is the Issue:</th>
                    <td><strong><?= e($issue['issue_title']) ?></strong></td>
                </tr>
                <tr>
                    <th>Description / Details:</th>
                    <td><?= nl2br(e($issue['description'] ?: 'No additional description provided.')) ?></td>
                </tr>
                <tr>
                    <th>Who Noticed:</th>
                    <td><span class="badge badge-secondary"><?= e($issue['noticed_by_name']) ?></span></td>
                </tr>
                <tr>
                    <th>Who is Responsible:</th>
                    <td><span class="badge badge-dark"><?= e($issue['responsible_name']) ?></span></td>
                </tr>
                <tr>
                    <th>Deduction / Fine:</th>
                    <td>
                        <?php if ((float)$issue['deduction_amount'] > 0): ?>
                            <span class="badge badge-danger" style="font-size:13px;">₹<?= number_format((float)$issue['deduction_amount'], 2) ?></span> (<?= humanize($issue['deduction_type']) ?>)
                        <?php else: ?>
                            <span class="text-muted">₹0.00 (No monetary deduction)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Client / Project:</th>
                    <td><?= $issue['client_name'] ? e($issue['client_name']) : '<span class="text-muted">—</span>' ?></td>
                </tr>
                <tr>
                    <th>Target Rectification Date:</th>
                    <td>
                        <?= $issue['due_date'] ? format_date($issue['due_date']) : '<span class="text-muted">—</span>' ?>
                        <?php if (!empty($issue['due_date']) && strtotime($issue['due_date']) < strtotime('today') && $issue['status'] !== 'corrected'): ?>
                            <span class="badge badge-danger" style="margin-left:8px;">OVERDUE</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Who Corrected:</th>
                    <td>
                        <?php if ($issue['corrected_by_name']): ?>
                            <span style="color:var(--success); font-weight:600;"><?= e($issue['corrected_by_name']) ?></span>
                            <span class="text-muted small">(<?= format_datetime($issue['resolved_at']) ?>)</span>
                        <?php else: ?>
                            <span class="text-muted">Not corrected yet</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($issue['correction_notes']): ?>
                <tr>
                    <th>Correction / Rectification Notes:</th>
                    <td style="background:#f0fdf4; border-radius:6px; padding:12px;">
                        <?= nl2br(e($issue['correction_notes'])) ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mark as Corrected Form -->
    <?php if ($issue['status'] !== 'corrected'): ?>
    <div class="card" style="border: 2px solid var(--success);">
        <div class="card-title" style="color:var(--success);">Rectify &amp; Mark Corrected</div>
        <p class="text-muted small">Once the mistake has been resolved or corrected, document the resolution below. The reporter and founder will be notified.</p>
        <form method="post" action="<?= url('operations_issues', ['action' => 'rectify']) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= $issue['id'] ?>">
            <div class="form-group">
                <label>How was this issue corrected? *</label>
                <textarea name="correction_notes" rows="3" required placeholder="Explain the actions taken to fix the issue and prevent future occurrence..." class="form-control"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="background:var(--success); border-color:var(--success);">
                ✓ Confirm Issue Corrected &amp; Notify Founder
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>
