<h1>Audit Log</h1>
<form class="filters-bar" method="get">
    <input type="hidden" name="page" value="audit">
    <div class="form-group"><label>Entity Type</label>
        <select name="entity_type" data-autosubmit><option value="">All</option>
            <?php foreach ($entityTypes as $t): ?><option value="<?= e($t) ?>" <?= $filters['entity_type']===$t?'selected':'' ?>><?= e(humanize($t)) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><label>User</label>
        <select name="user_id" data-autosubmit><option value="">All</option>
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= (string)$filters['user_id']===(string)$u['id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
</form>

<div class="table-wrap responsive-table"><table>
<thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>ID</th><th>Old &rarr; New</th></tr></thead>
<tbody>
<?php if (empty($rows)): ?><tr><td colspan="6" class="text-muted">No audit entries.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
    <tr>
        <td data-label="When"><?= format_datetime($r['created_at']) ?></td>
        <td data-label="User"><?= e($r['user_name'] ?? 'System') ?></td>
        <td data-label="Action"><?= e(humanize($r['action'])) ?></td>
        <td data-label="Entity"><?= e(humanize($r['entity_type'])) ?></td>
        <td data-label="ID">#<?= (int)$r['entity_id'] ?></td>
        <td data-label="Old &rarr; New" class="wrap"><?= e($r['old_value']) ?> <?= $r['new_value'] ? ' &rarr; '.e($r['new_value']) : '' ?></td>
    </tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php render('partials/pagination', ['p' => $p]); ?>
