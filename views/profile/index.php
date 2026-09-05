<h1>My Profile</h1>
<div class="grid grid-2">
    <div class="card">
        <div class="card-title">Account Details</div>
        <table>
            <tr><td class="text-muted">Employee Code</td><td><?= e($user['employee_code']) ?></td></tr>
            <tr><td class="text-muted">Name</td><td><?= e($user['name']) ?></td></tr>
            <tr><td class="text-muted">Email</td><td><?= e($user['email']) ?></td></tr>
            <tr><td class="text-muted">Roles</td><td><?= e(implode(', ', array_column($roles, 'name'))) ?: '—' ?></td></tr>
            <tr><td class="text-muted">Teams</td><td><?= e(implode(', ', array_column($teams, 'name'))) ?: '—' ?></td></tr>
        </table>
        <hr>
        <form method="post" action="<?= url('profile', ['action' => 'update']) ?>">
            <?= Csrf::field() ?>
            <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
            <button class="btn btn-primary btn-sm">Save</button>
        </form>
    </div>

    <div class="card">
        <div class="card-title">Change Password</div>
        <?php if ($user['must_change_password']): ?><p class="text-danger small">Your administrator has reset your password. Please set a new one.</p><?php endif; ?>
        <form method="post" action="<?= url('profile', ['action' => 'change_password']) ?>">
            <?= Csrf::field() ?>
            <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
            <div class="form-group"><label>New Password</label><input type="password" name="new_password" required minlength="8"></div>
            <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required minlength="8"></div>
            <button class="btn btn-primary">Change Password</button>
        </form>
    </div>
</div>
