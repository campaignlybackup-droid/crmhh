<h1><?= $role ? 'Edit Role' : 'New Role' ?></h1>
<div class="card" style="max-width:700px">
<form method="post" action="<?= $role ? url('roles', ['action' => 'update']) : url('roles', ['action' => 'store']) ?>">
    <?= Csrf::field() ?>
    <?php if ($role): ?><input type="hidden" name="id" value="<?= $role['id'] ?>"><?php endif; ?>
    <div class="form-group"><label>Role Name *</label><input type="text" name="name" value="<?= e($role['name'] ?? '') ?>" required <?= ($role['is_system'] ?? false) ? 'readonly' : '' ?>></div>
    <div class="form-group"><label>Description</label><input type="text" name="description" value="<?= e($role['description'] ?? '') ?>"></div>

    <div class="form-group">
        <label>Permissions</label>
        <?php $grouped = []; foreach ($permissions as $p) { $grouped[$p['group']][] = $p; } ?>
        <?php foreach ($grouped as $group => $perms): ?>
            <div style="margin-bottom:10px">
                <strong class="small"><?= e(humanize($group)) ?></strong>
                <div class="grid grid-2" style="gap:4px">
                <?php foreach ($perms as $p): ?>
                    <label style="font-weight:400"><input type="checkbox" name="permissions[]" value="<?= $p['id'] ?>" <?= in_array($p['id'], $rolePermIds) ? 'checked' : '' ?>> <?= e($p['name']) ?></label>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <button class="btn btn-primary"><?= $role ? 'Save Changes' : 'Create Role' ?></button>
    <a href="<?= url('roles') ?>" class="btn">Cancel</a>
</form>
</div>
