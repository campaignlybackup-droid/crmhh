<h1>Leave Management</h1>

<div class="card" style="max-width:520px">
    <div class="card-title">Apply for Leave</div>
    <form method="post" action="<?= url('leave', ['action' => 'apply']) ?>">
        <?= Csrf::field() ?>
        <div class="form-row">
            <div class="form-group"><label>Start Date *</label><input type="date" name="start_date" required></div>
            <div class="form-group"><label>End Date *</label><input type="date" name="end_date" required></div>
        </div>
        <div class="form-group"><label>Reason *</label><textarea name="reason" required></textarea></div>
        <button class="btn btn-primary">Submit Request</button>
    </form>
</div>

<div class="card-title" style="margin-top:20px">Leave Requests</div>
<form class="filters-bar" method="get">
    <input type="hidden" name="page" value="leave">
    <div class="form-group"><label>Status</label>
        <select name="status" data-autosubmit>
            <option value="">All</option>
            <option value="pending" <?= $filters['status']==='pending'?'selected':'' ?>>Pending</option>
            <option value="approved" <?= $filters['status']==='approved'?'selected':'' ?>>Approved</option>
            <option value="rejected" <?= $filters['status']==='rejected'?'selected':'' ?>>Rejected</option>
        </select>
    </div>
</form>

<div class="table-wrap"><table>
<thead><tr><th>Employee</th><th>From</th><th>To</th><th>Reason</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php if (empty($rows)): ?><tr><td colspan="6" class="text-muted">No leave requests.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
    <tr>
        <td><?= e($r['user_name']) ?></td>
        <td><?= format_date($r['start_date']) ?></td>
        <td><?= format_date($r['end_date']) ?></td>
        <td class="wrap"><?= e($r['reason']) ?></td>
        <td><span class="badge badge-<?= status_badge_class($r['status']) ?>"><?= e(humanize($r['status'])) ?></span></td>
        <td><a href="<?= url('leave', ['action' => 'view', 'id' => $r['id']]) ?>" class="btn btn-sm">View</a></td>
    </tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php render('partials/pagination', ['p' => $p]); ?>
