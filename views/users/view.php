<div class="flex-between">
    <h1><?= e($user['name']) ?> <span class="text-muted small"><?= e($user['employee_code']) ?></span></h1>
    <div class="btn-group">
        <a href="<?= url('users', ['action' => 'edit', 'id' => $user['id']]) ?>" class="btn">Edit</a>
        <?php if ($user['status'] === 'active'): ?>
        <form method="post" action="<?= url('users', ['action' => 'disable']) ?>" style="display:inline" data-confirm="Disable this user? They will not be able to log in.">
            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $user['id'] ?>"><button class="btn btn-danger">Disable</button>
        </form>
        <?php else: ?>
        <form method="post" action="<?= url('users', ['action' => 'enable']) ?>" style="display:inline">
            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $user['id'] ?>"><button class="btn btn-primary">Enable</button>
        </form>
        <?php endif; ?>
        <form method="post" action="<?= url('users', ['action' => 'reset_password']) ?>" style="display:inline" data-confirm="Reset this user's password? A new temporary password will be generated.">
            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $user['id'] ?>"><button class="btn">Reset Password</button>
        </form>
    </div>
</div>
<div class="grid grid-2">
    <div class="card">
        <div class="card-title">Details</div>
        <table>
            <tr><td class="text-muted">Email</td><td><?= e($user['email']) ?></td></tr>
            <tr><td class="text-muted">Phone</td><td><?= e($user['phone'] ?: '—') ?></td></tr>
            <tr><td class="text-muted">Status</td><td><span class="badge badge-<?= $user['status']==='active'?'success':'secondary' ?>"><?= e(humanize($user['status'])) ?></span></td></tr>
            <tr><td class="text-muted">Reports To</td><td><?= e(Database::scalar('SELECT name FROM users WHERE id=?', [$user['manager_id']]) ?: '—') ?></td></tr>
            <tr><td class="text-muted">Last Login</td><td><?= $user['last_login_at'] ? format_datetime($user['last_login_at']) : 'Never' ?></td></tr>
        </table>
    </div>
    <div class="card">
        <div class="card-title">Roles</div>
        <?php foreach ($roles as $r): ?><span class="tag"><?= e($r['name']) ?></span><?php endforeach; ?>
        <?php if (empty($roles)): ?><p class="text-muted small">No roles assigned.</p><?php endif; ?>
        <div class="card-title" style="margin-top:14px">Teams</div>
        <?php foreach ($teams as $t): ?><span class="tag"><?= e($t['name']) ?></span><?php endforeach; ?>
        <?php if (empty($teams)): ?><p class="text-muted small">Not part of any team.</p><?php endif; ?>
    </div>
</div>
