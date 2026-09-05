<h1><?= $user ? 'Edit User' : 'New User' ?></h1>
<div class="card" style="max-width:640px">
<form method="post" action="<?= $user ? url('users', ['action' => 'update']) : url('users', ['action' => 'store']) ?>">
    <?= Csrf::field() ?>
    <?php if ($user): ?><input type="hidden" name="id" value="<?= $user['id'] ?>"><?php endif; ?>
    <div class="form-row">
        <div class="form-group"><label>Full Name *</label><input type="text" name="name" value="<?= e($user['name'] ?? '') ?>" required></div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" value="<?= e($user['email'] ?? '') ?>" required></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
        <div class="form-group"><label>Reports To (Manager)</label>
            <select name="manager_id"><option value="">— None —</option>
                <?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>" <?= ($user['manager_id'] ?? null)==$m['id']?'selected':'' ?>><?= e($m['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php if (!$user): ?>
    <div class="form-group"><label>Password *</label><input type="password" name="password" required minlength="8"></div>
    <?php endif; ?>
    <div class="form-group">
        <label>Roles</label>
        <select name="roles[]" multiple size="8">
            <?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>" <?= in_array($r['id'], $userRoleIds) ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
        </select>
        <div class="help-text">A user can hold multiple roles — their dashboard automatically combines relevant sections for each.</div>
    </div>
    <button class="btn btn-primary"><?= $user ? 'Save Changes' : 'Create User' ?></button>
    <a href="<?= url('users') ?>" class="btn">Cancel</a>
</form>
</div>
