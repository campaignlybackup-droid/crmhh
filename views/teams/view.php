<div class="flex-between">
    <h1><?= e($team['name']) ?></h1>
    <?php if ($canManage): ?>
    <div class="btn-group">
        <a href="<?= url('teams', ['action' => 'edit', 'id' => $team['id']]) ?>" class="btn">Edit</a>
        <form method="post" action="<?= url('teams', ['action' => 'delete']) ?>" style="display:inline" data-confirm="Delete this team?">
            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $team['id'] ?>"><button class="btn btn-danger">Delete</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<p class="text-muted"><?= e($team['description'] ?: '') ?></p>

<div class="grid grid-2">
    <div class="card">
        <div class="card-title">Managers</div>
        <?php foreach ($managers as $m): ?><span class="tag"><?= e($m['name']) ?></span><?php endforeach; ?>
        <?php if (empty($managers)): ?><p class="text-muted small">No managers assigned.</p><?php endif; ?>
    </div>
    <div class="card">
        <div class="card-title">Members (<?= count($members) ?>)</div>
        <?php foreach ($members as $m): ?><span class="tag"><?= e($m['name']) ?></span><?php endforeach; ?>
        <?php if (empty($members)): ?><p class="text-muted small">No members yet.</p><?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-title">Workload</div>
    <div class="table-wrap"><table>
        <thead><tr><th>Member</th><th>Open Tasks</th><th>Overdue</th><th>Completed</th></tr></thead>
        <tbody>
        <?php foreach ($workload as $w): ?>
            <tr><td><?= e($w['name']) ?></td><td><?= (int)$w['open_tasks'] ?></td><td><?= (int)$w['overdue_tasks'] ?></td><td><?= (int)$w['completed_tasks'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
