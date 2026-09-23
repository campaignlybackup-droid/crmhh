<div class="flex-between mb-4">
    <div>
        <h1 style="margin-bottom:4px;">Teams &amp; Departments</h1>
        <div class="text-muted" style="font-size:13px;">Manage functional teams, leaders, member assignments, and operational capacity.</div>
    </div>
    <?php if ($canManage): ?>
        <a href="<?= url('teams', ['action' => 'create']) ?>" class="btn btn-primary">+ New Team</a>
    <?php endif; ?>
</div>

<div class="grid grid-3" style="gap:16px;">
<?php foreach ($teams as $t): ?>
    <a href="<?= url('teams', ['action' => 'view', 'id' => $t['id']]) ?>" class="card" style="text-decoration:none; color:inherit; display:flex; flex-direction:column; justify-content:space-between; border-left:4px solid var(--primary); transition:transform 0.15s ease, box-shadow 0.15s ease;">
        <div>
            <div class="flex-between mb-2">
                <h3 style="margin:0; font-size:16px; color:var(--text);"><?= e($t['name']) ?></h3>
                <span class="badge badge-secondary" style="font-size:11px;"><?= (int)($t['members_count'] ?? 0) ?> Members</span>
            </div>
            <p class="text-muted small" style="margin-bottom:12px; min-height:36px;"><?= e($t['description'] ?: 'No description provided.') ?></p>
        </div>
        <div style="border-top:1px solid var(--border); padding-top:10px; margin-top:8px;">
            <div style="font-size:11px; text-transform:uppercase; color:var(--text-muted); font-weight:700; margin-bottom:4px;">Team Leads / Managers:</div>
            <?php if (!empty($t['managers'])): ?>
                <div style="display:flex; flex-wrap:wrap; gap:4px;">
                    <?php foreach ($t['managers'] as $m): ?>
                        <span class="tag" style="font-size:11px; padding:2px 8px; background:var(--bg);"><?= e($m['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <span class="text-muted small">No manager assigned</span>
            <?php endif; ?>
        </div>
    </a>
<?php endforeach; ?>
<?php if (empty($teams)): ?>
    <div class="card text-center" style="grid-column:1 / -1; padding:40px;">
        <p class="text-muted">No teams created yet.</p>
        <?php if ($canManage): ?><a href="<?= url('teams', ['action' => 'create']) ?>" class="btn btn-primary">+ Create First Team</a><?php endif; ?>
    </div>
<?php endif; ?>
</div>
