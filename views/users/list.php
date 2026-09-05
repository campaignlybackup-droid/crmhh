<div class="flex-between">
    <h1>Users</h1>
    <a href="<?= url('users', ['action' => 'create']) ?>" class="btn btn-primary">+ New User</a>
</div>
<form class="filters-bar" method="get">
    <input type="hidden" name="page" value="users">
    <div class="form-group"><label>Search</label><input type="text" name="search" value="<?= e($search) ?>"></div>
    <div class="form-group"><label>Status</label>
        <select name="status" data-autosubmit><option value="">All</option>
            <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
            <option value="disabled" <?= $status==='disabled'?'selected':'' ?>>Disabled</option>
        </select>
    </div>
    <button class="btn btn-primary btn-sm">Filter</button>
</form>
<div class="table-wrap"><table>
<thead><tr><th>Code</th><th>Name</th><th>Email</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($rows as $u): ?>
    <tr>
        <td><?= e($u['employee_code']) ?></td>
        <td><a href="<?= url('users', ['action' => 'view', 'id' => $u['id']]) ?>"><?= e($u['name']) ?></a><?php if ($u['is_founder']): ?> <span class="badge badge-primary">Founder</span><?php endif; ?></td>
        <td><?= e($u['email']) ?></td>
        <td><span class="badge badge-<?= $u['status']==='active'?'success':'secondary' ?>"><?= e(humanize($u['status'])) ?></span></td>
    </tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php render('partials/pagination', ['p' => $p]); ?>
