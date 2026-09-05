<h1>Search Results <?php if ($q): ?><span class="text-muted small">for "<?= e($q) ?>"</span><?php endif; ?></h1>

<?php if (mb_strlen($q) < 2): ?>
    <p class="text-muted">Type at least 2 characters to search.</p>
<?php else: ?>

<?php if (!empty($results['leads'])): ?>
<div class="card">
    <div class="card-title">Leads</div>
    <div class="table-wrap"><table><tbody>
    <?php foreach ($results['leads'] as $l): ?>
        <tr><td><a href="<?= url('leads', ['action' => 'view', 'id' => $l['id']]) ?>"><?= e($l['lead_code']) ?> &mdash; <?= e($l['name']) ?></a></td><td class="text-muted"><?= e($l['phone']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php if (!empty($results['clients'])): ?>
<div class="card">
    <div class="card-title">Clients</div>
    <div class="table-wrap"><table><tbody>
    <?php foreach ($results['clients'] as $c): ?>
        <tr><td><a href="<?= url('clients', ['action' => 'view', 'id' => $c['id']]) ?>"><?= e($c['client_code']) ?> &mdash; <?= e($c['name']) ?></a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php if (!empty($results['tasks'])): ?>
<div class="card">
    <div class="card-title">Tasks</div>
    <div class="table-wrap"><table><tbody>
    <?php foreach ($results['tasks'] as $t): ?>
        <tr><td><a href="<?= url('tasks', ['action' => 'view', 'id' => $t['id']]) ?>"><?= e($t['task_code']) ?> &mdash; <?= e($t['title']) ?></a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php if (!empty($results['users'])): ?>
<div class="card">
    <div class="card-title">People</div>
    <div class="table-wrap"><table><tbody>
    <?php foreach ($results['users'] as $u): ?>
        <tr><td><a href="<?= url('users', ['action' => 'view', 'id' => $u['id']]) ?>"><?= e($u['employee_code']) ?> &mdash; <?= e($u['name']) ?></a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php if (empty($results['leads']) && empty($results['clients']) && empty($results['tasks']) && empty($results['users'])): ?>
    <p class="text-muted">No results found.</p>
<?php endif; ?>
<?php endif; ?>
