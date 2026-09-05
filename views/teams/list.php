<div class="flex-between">
    <h1>Teams</h1>
    <?php if ($canManage): ?><a href="<?= url('teams', ['action' => 'create']) ?>" class="btn btn-primary">+ New Team</a><?php endif; ?>
</div>
<div class="grid grid-3">
<?php foreach ($teams as $t): ?>
    <a href="<?= url('teams', ['action' => 'view', 'id' => $t['id']]) ?>" class="client-card">
        <h3><?= e($t['name']) ?></h3>
        <p class="text-muted small"><?= e($t['description'] ?: '') ?></p>
    </a>
<?php endforeach; ?>
<?php if (empty($teams)): ?><p class="text-muted">No teams yet.</p><?php endif; ?>
</div>
