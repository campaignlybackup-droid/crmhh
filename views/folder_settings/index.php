<div class="flex-between">
    <h1>Settings for: <?= e($folder['name']) ?></h1>
    <a href="<?= url('leads', ['folder_id' => $folder['id']]) ?>" class="btn">Back to Folder</a>
</div>

<div class="grid grid-2">
    <!-- LEAD STATUSES -->
    <div class="card">
        <div class="card-title">Custom Lead Statuses</div>
        <p class="text-muted small">Define the pipeline stages specific to this folder.</p>
        
        <table style="margin-bottom:20px;">
            <thead><tr><th>Name</th><th>Color</th><th>Props</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($statuses as $s): ?>
                <tr>
                    <td><?= e($s['name']) ?></td>
                    <td><span class="badge" style="background:<?= e($s['color']) ?>"><?= e($s['color']) ?></span></td>
                    <td>
                        <?php if($s['is_default']): ?><span class="badge badge-primary">Default</span><?php endif; ?>
                        <?php if($s['is_won']): ?><span class="badge badge-success">Won</span><?php endif; ?>
                        <?php if($s['is_lost']): ?><span class="badge badge-danger">Lost</span><?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-link" onclick="editStatus(<?= htmlspecialchars(json_encode($s)) ?>)">Edit</button>
                        <form method="post" action="<?= url('folder_settings', ['action' => 'delete_status', 'folder_id' => $folder['id']]) ?>" style="display:inline;" data-confirm="Delete this status?">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-link text-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($statuses)): ?><tr><td colspan="4" class="text-muted">No custom statuses yet.</td></tr><?php endif; ?>
            </tbody>
        </table>

        <button type="button" class="btn btn-primary btn-sm" onclick="editStatus()">+ Add Status</button>
    </div>

    <!-- CUSTOM FIELDS -->
    <div class="card">
        <div class="card-title">Custom Fields</div>
        <p class="text-muted small">Add extra fields to track specific data for leads in this folder.</p>
        
        <table style="margin-bottom:20px;">
            <thead><tr><th>Field Name</th><th>Type</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($customFields as $f): ?>
                <tr>
                    <td><?= e($f['field_name']) ?></td>
                    <td><span class="badge badge-secondary"><?= e(strtoupper($f['field_type'])) ?></span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-link" onclick="editField(<?= htmlspecialchars(json_encode($f)) ?>)">Edit</button>
                        <form method="post" action="<?= url('folder_settings', ['action' => 'delete_field', 'folder_id' => $folder['id']]) ?>" style="display:inline;" data-confirm="Delete this custom field? Data for existing leads will be lost!">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-link text-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($customFields)): ?><tr><td colspan="3" class="text-muted">No custom fields defined yet.</td></tr><?php endif; ?>
            </tbody>
        </table>

        <button type="button" class="btn btn-primary btn-sm" onclick="editField()">+ Add Custom Field</button>
    </div>
</div>

<!-- Modals -->
<div id="statusModal" class="modal-overlay">
    <div class="modal">
        <span class="modal-close" onclick="closeModal('statusModal')">&times;</span>
        <h2 id="statusModalTitle">Add Status</h2>
        <form method="post" action="<?= url('folder_settings', ['action' => 'save_status', 'folder_id' => $folder['id']]) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" id="status_id" value="">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" id="status_name" required onkeyup="updateSlug()">
            </div>
            <div class="form-group">
                <label>Slug (Used for integrations/imports)</label>
                <input type="text" name="slug" id="status_slug" required>
            </div>
            <div class="form-group">
                <label>Color (Hex)</label>
                <input type="text" name="color" id="status_color" placeholder="#ff0000" required>
            </div>
            <div class="checkbox-group form-group">
                <label><input type="checkbox" name="is_default" id="status_is_default" value="1"> Is Default (Applied to new leads)</label>
                <label><input type="checkbox" name="is_won" id="status_is_won" value="1"> Marks Lead as Won</label>
                <label><input type="checkbox" name="is_lost" id="status_is_lost" value="1"> Marks Lead as Lost</label>
            </div>
            <button class="btn btn-primary">Save Status</button>
        </form>
    </div>
</div>

<div id="fieldModal" class="modal-overlay">
    <div class="modal">
        <span class="modal-close" onclick="closeModal('fieldModal')">&times;</span>
        <h2 id="fieldModalTitle">Add Custom Field</h2>
        <form method="post" action="<?= url('folder_settings', ['action' => 'save_field', 'folder_id' => $folder['id']]) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" id="field_id" value="">
            <div class="form-group">
                <label>Field Name</label>
                <input type="text" name="field_name" id="field_name" required>
            </div>
            <div class="form-group">
                <label>Field Type</label>
                <select name="field_type" id="field_type" required onchange="toggleFieldOptions()">
                    <option value="text">Text (Single Line)</option>
                    <option value="number">Number</option>
                    <option value="select">Dropdown (Select)</option>
                    <option value="date">Date</option>
                </select>
            </div>
            <div class="form-group" id="optionsGroup" style="display:none;">
                <label>Dropdown Options (Comma separated)</label>
                <input type="text" name="options" id="field_options" placeholder="Option 1, Option 2, Option 3">
            </div>
            <button class="btn btn-primary">Save Field</button>
        </form>
    </div>
</div>

<script>
function editStatus(s = null) {
    document.getElementById('statusModalTitle').innerText = s ? 'Edit Status' : 'Add Status';
    document.getElementById('status_id').value = s ? s.id : '';
    document.getElementById('status_name').value = s ? s.name : '';
    document.getElementById('status_slug').value = s ? s.slug : '';
    document.getElementById('status_color').value = s ? s.color : '#6c757d';
    document.getElementById('status_is_default').checked = s && s.is_default == 1;
    document.getElementById('status_is_won').checked = s && s.is_won == 1;
    document.getElementById('status_is_lost').checked = s && s.is_lost == 1;
    document.getElementById('statusModal').classList.add('show');
}

function updateSlug() {
    const name = document.getElementById('status_name').value;
    document.getElementById('status_slug').value = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, '');
}

function editField(f = null) {
    document.getElementById('fieldModalTitle').innerText = f ? 'Edit Field' : 'Add Field';
    document.getElementById('field_id').value = f ? f.id : '';
    document.getElementById('field_name').value = f ? f.field_name : '';
    document.getElementById('field_type').value = f ? f.field_type : 'text';
    
    let opts = '';
    if (f && f.options) {
        try {
            const arr = JSON.parse(f.options);
            opts = Array.isArray(arr) ? arr.join(', ') : '';
        } catch(e){}
    }
    document.getElementById('field_options').value = opts;
    
    toggleFieldOptions();
    document.getElementById('fieldModal').classList.add('show');
}

function toggleFieldOptions() {
    const type = document.getElementById('field_type').value;
    document.getElementById('optionsGroup').style.display = type === 'select' ? 'block' : 'none';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}
</script>
