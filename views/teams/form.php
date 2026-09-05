<h1><?= $team ? 'Edit Team' : 'New Team' ?></h1>
<div class="card" style="max-width:640px">
<form method="post" action="<?= $team ? url('teams', ['action' => 'update']) : url('teams', ['action' => 'store']) ?>">
    <?= Csrf::field() ?>
    <?php if ($team): ?><input type="hidden" name="id" value="<?= $team['id'] ?>"><?php endif; ?>
    <div class="form-group"><label>Team Name *</label><input type="text" name="name" value="<?= e($team['name'] ?? '') ?>" required></div>
    <div class="form-group"><label>Description</label><textarea name="description"><?= e($team['description'] ?? '') ?></textarea></div>

    <div class="form-group">
        <label>Managers</label>
        <select name="managers[]" multiple size="6">
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= in_array($u['id'], $managerIds) ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
        </select>
        <div class="help-text">Hold Ctrl/Cmd to select multiple.</div>
    </div>

    <div class="form-group">
        <label>Members</label>
        <select name="members[]" multiple size="8">
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= in_array($u['id'], $members) ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
        </select>
    </div>

    <button class="btn btn-primary"><?= $team ? 'Save Changes' : 'Create Team' ?></button>
    <a href="<?= url('teams') ?>" class="btn">Cancel</a>
</form>
</div>
