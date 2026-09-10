<div class="flex-between">
    <h1>Approvals</h1>
    <a href="<?= url('approvals', ['action' => 'create']) ?>" class="btn btn-primary">+ New Request</a>
</div>

<div class="card">
    <div class="table-wrap responsive-table">
    <table class="table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Sender</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
            <tr><td colspan="5" class="text-muted text-center">No approval requests found.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td data-label="Title"><?= e($r['title']) ?></td>
                <td data-label="Sender"><?= e($r['sender_name']) ?></td>
                <td data-label="Status">
                    <?php
                        $badge = 'secondary';
                        if ($r['status'] === 'approved') $badge = 'success';
                        if ($r['status'] === 'rejected') $badge = 'danger';
                        if ($r['status'] === 'pending') $badge = 'warning';
                    ?>
                    <span class="badge badge-<?= $badge ?>"><?= ucfirst(e($r['status'])) ?></span>
                </td>
                <td data-label="Date"><?= format_date($r['created_at']) ?></td>
                <td data-label="Actions"><a href="<?= url('approvals', ['action' => 'view', 'id' => $r['id']]) ?>" class="btn btn-sm">View</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php render('partials/pagination', ['p' => $p]); ?>
</div>
