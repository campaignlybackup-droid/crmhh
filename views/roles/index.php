<div class="flex-between">
    <h1>Roles &amp; Permissions</h1>
    <a href="<?= url('roles', ['action' => 'create']) ?>" class="btn btn-primary">+ New Role</a>
</div>
<div class="table-wrap"><table>
<thead><tr><th>Role</th><th>Description</th><th>Permissions</th><th>Users</th><th></th></tr></thead>
<tbody>
<?php foreach ($roles as $r): ?>
    <tr>
        <td><?= e($r['name']) ?> <?php if ($r['is_system']): ?><span class="badge badge-secondary">System</span><?php endif; ?></td>
        <td class="wrap"><?= e($r['description'] ?: '') ?></td>
        <td><?= $r['permission_count'] ?></td>
        <td><?= $r['user_count'] ?></td>
        <td>
            <a href="<?= url('roles', ['action' => 'edit', 'id' => $r['id']]) ?>" class="btn btn-sm">Edit</a>
            <?php if (!$r['is_system']): ?>
            <form method="post" action="<?= url('roles', ['action' => 'delete']) ?>" style="display:inline" data-confirm="Delete this role?">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button>
            </form>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table></div>
