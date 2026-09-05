<h1>Leave Request — <?= e($leave['user_name']) ?></h1>
<div class="card" style="max-width:520px">
    <table>
        <tr><td class="text-muted">Dates</td><td><?= format_date($leave['start_date']) ?> &ndash; <?= format_date($leave['end_date']) ?></td></tr>
        <tr><td class="text-muted">Reason</td><td class="wrap"><?= nl2br(e($leave['reason'])) ?></td></tr>
        <tr><td class="text-muted">Status</td><td><span class="badge badge-<?= status_badge_class($leave['status']) ?>"><?= e(humanize($leave['status'])) ?></span></td></tr>
        <?php if ($leave['decision_note']): ?><tr><td class="text-muted">Decision Note</td><td><?= e($leave['decision_note']) ?></td></tr><?php endif; ?>
        <tr><td class="text-muted">Applied On</td><td><?= format_datetime($leave['created_at']) ?></td></tr>
    </table>

    <?php if ($leave['status'] === 'pending' && Permission::hasAny(['leave.approve_team', 'leave.approve_all'])): ?>
    <hr>
    <form method="post" action="<?= url('leave', ['action' => 'decide']) ?>">
        <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $leave['id'] ?>">
        <div class="form-group"><label>Decision Note (optional)</label><input type="text" name="decision_note"></div>
        <div class="btn-group">
            <button type="submit" name="status" value="approved" class="btn btn-primary">Approve</button>
            <button type="submit" name="status" value="rejected" class="btn btn-danger">Reject</button>
        </div>
    </form>
    <?php endif; ?>
</div>
<a href="<?= url('leave') ?>" class="btn">&larr; Back</a>
