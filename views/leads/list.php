<div class="flex-between">
    <div style="display: flex; align-items: center; gap: 16px;">
        <h1 style="margin: 0;">Leads</h1>
        <div class="btn-group" style="display: inline-flex;">
            <a href="<?= url('leads', $filters + ['view' => 'table']) ?>" class="btn btn-sm <?= ($_GET['view'] ?? 'table') === 'table' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 4px 0 0 4px;">Table</a>
            <a href="<?= url('leads', $filters + ['view' => 'kanban']) ?>" class="btn btn-sm <?= ($_GET['view'] ?? '') === 'kanban' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 0 4px 4px 0; border-left: none;">Kanban</a>
        </div>
    </div>
    <div class="btn-group">
        <?php if (!empty($filters['folder_id']) && Auth::hasRole('founder')): ?>
        <a href="<?= url('folder_settings', ['folder_id' => $filters['folder_id']]) ?>" class="btn btn-secondary">Folder Settings</a>
        <?php endif; ?>
        <?php if (Permission::has('leads.delete') || Auth::hasRole('founder')): ?>
        <button id="bulk-delete-btn" class="btn btn-danger" style="display:none;" onclick="bulkDelete()">Delete Selected (<span id="bulk-count">0</span>)</button>
        <?php endif; ?>
        <?php if (Permission::has('leads.import')): ?><a href="<?= url('leads', ['action' => 'import'] + (isset($filters['folder_id']) && $filters['folder_id'] !== '' ? ['folder_id' => $filters['folder_id']] : [])) ?>" class="btn">Import CSV</a><?php endif; ?>
        <?php if (Permission::has('leads.export')): ?><a href="<?= url('leads', ['action' => 'export'] + $filters) ?>" class="btn">Export CSV</a><?php endif; ?>
        <?php if (Permission::has('leads.create')): ?><a href="<?= url('leads', ['action' => 'create']) ?>" class="btn btn-primary">+ New Lead</a><?php endif; ?>
        <?php if (Auth::hasRole('founder')): ?>
        <form method="post" action="<?= url('leads', ['action' => 'clear_all']) ?>" style="display:inline;" data-confirm="⚠️ This will soft-delete ALL leads. Are you absolutely sure? This action affects every lead in the system.">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-danger">🗑 Clear All Leads</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<form class="filters-bar" method="get">
    <input type="hidden" name="page" value="leads">
    <div class="form-group">
        <label>Search</label>
        <input type="text" name="search" value="<?= e($filters['search']) ?>" placeholder="Name, phone, email, code&hellip;">
    </div>
    <div class="form-group">
        <label>Status</label>
        <select name="status_id"><option value="">All</option>
            <?php foreach ($statuses as $s): ?><option value="<?= $s['id'] ?>" <?= (string)$filters['status_id'] === (string)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php if (Permission::has('leads.view_all')): ?>
    <div class="form-group">
        <label>Assigned To</label>
        <select name="assigned_user_id"><option value="">All</option>
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= (string)$filters['assigned_user_id'] === (string)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="form-group">
        <label>Folder</label>
        <select name="folder_id" data-autosubmit="true">
            <option value="all" <?= ($filters['folder_id'] ?? '') === 'all' ? 'selected' : '' ?>>All Folders</option>
            <option value="" <?= ($filters['folder_id'] ?? '') === '' ? 'selected' : '' ?>>Main / Uncategorized</option>
            <?php foreach ($allFolders ?? [] as $f): ?><option value="<?= $f['id'] ?>" <?= (string)($filters['folder_id'] ?? '') === (string)$f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Source</label>
        <select name="source"><option value="">All</option>
            <?php foreach ($sources as $s): ?><option value="<?= e($s) ?>" <?= $filters['source'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Follow-up</label>
        <select name="followup"><option value="">Any</option>
            <option value="today" <?= $filters['followup']==='today'?'selected':'' ?>>Due Today</option>
            <option value="overdue" <?= $filters['followup']==='overdue'?'selected':'' ?>>Overdue</option>
            <option value="upcoming" <?= $filters['followup']==='upcoming'?'selected':'' ?>>Next 7 Days</option>
        </select>
    </div>
    <div class="form-group">
        <label>Created From</label>
        <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
    </div>
    <div class="form-group">
        <label>Created To</label>
        <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
    </div>
    <button class="btn btn-primary btn-sm">Filter</button>
    <a href="<?= url('leads') ?>" class="btn btn-sm">Reset</a>
</form>

<?php if (isset($dashboardStats)): ?>
<div class="grid grid-4" style="margin-bottom:24px;">
    <div class="card" style="text-align:center; padding:16px;">
        <div style="font-size:2rem; font-weight:bold; color:var(--primary);"><?= (int)$dashboardStats['contacted'] ?></div>
        <div class="text-muted small text-uppercase">Contacted Today</div>
    </div>
    <div class="card" style="text-align:center; padding:16px;">
        <div style="font-size:2rem; font-weight:bold; color:var(--warning);"><?= (int)$dashboardStats['followups'] ?></div>
        <div class="text-muted small text-uppercase">Follow-ups Today</div>
    </div>
    <div class="card" style="text-align:center; padding:16px;">
        <div style="font-size:2rem; font-weight:bold; color:var(--info);"><?= (int)$dashboardStats['pending'] ?></div>
        <div class="text-muted small text-uppercase">Pending (New)</div>
    </div>
    <div class="card" style="text-align:center; padding:16px;">
        <div style="font-size:2rem; font-weight:bold; color:var(--danger);"><?= (int)$dashboardStats['missed'] ?></div>
        <div class="text-muted small text-uppercase">Missed Follow-ups</div>
    </div>
</div>
<?php endif; ?>


<div class="table-wrap responsive-table" style="position:relative;">
<table>
<thead><tr>
    <?php if (Permission::has('leads.delete') || Auth::hasRole('founder')): ?><th class="checkbox-col" style="width:40px;"><input type="checkbox" onclick="toggleAllLeads(this)" title="Select All"></th><?php endif; ?>
    <th>ID</th><th>Folder</th><th>Name</th><th>Phone</th><th>Email</th><th>Company</th><th>Source</th>
    <?php if (!empty($customFields)) foreach ($customFields as $cf): ?><th><?= e($cf['field_name']) ?></th><?php endforeach; ?>
    <th>Status</th><th>Assigned</th><th>Follow-up</th><th>Next Step</th><th>Notes</th><th>Actions</th>
</tr></thead>
<tbody>
<?php if (Permission::has('leads.create')): ?>
<tr id="quick-add-row" style="background:var(--bg-hover)">
    <?php if (Permission::has('leads.delete') || Auth::hasRole('founder')): ?><td class="checkbox-col"></td><?php endif; ?>
    <td data-label="ID" class="text-muted small">New</td>
    <td data-label="Folder">
        <select id="qa_folder_id" class="form-control form-control-sm" style="width:100%;min-width:100px;">
            <option value="">Main</option>
            <?php foreach ($allFolders ?? [] as $f): ?><option value="<?= $f['id'] ?>" <?= (string)($filters['folder_id'] ?? '') === (string)$f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option><?php endforeach; ?>
        </select>
    </td>
    <td data-label="Name"><input type="text" id="qa_name" placeholder="Name *" class="form-control form-control-sm" style="width:100%; min-width:90px;"></td>
    <td data-label="Phone"><input type="text" id="qa_phone" placeholder="Phone" class="form-control form-control-sm" style="width:100%; min-width:90px;"></td>
    <td data-label="Email"><input type="email" id="qa_email" placeholder="Email" class="form-control form-control-sm" style="width:100%; min-width:100px;"></td>
    <td data-label="Company"><input type="text" id="qa_company" placeholder="Company" class="form-control form-control-sm" style="width:100%; min-width:90px;"></td>
    <td data-label="Source"><input type="text" id="qa_source" placeholder="Source" class="form-control form-control-sm" style="width:100%; min-width:80px;"></td>
    
    <?php if (!empty($customFields)) foreach ($customFields as $cf): ?>
    <td data-label="<?= e($cf['field_name']) ?>">
        <?php if ($cf['field_type'] === 'select'): 
            $opts = json_decode($cf['options'] ?: '[]', true) ?: []; ?>
            <select class="form-control form-control-sm cf-input" data-cf-id="<?= $cf['id'] ?>">
                <option value="">—</option>
                <?php foreach ($opts as $opt): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endforeach; ?>
            </select>
        <?php elseif ($cf['field_type'] === 'date'): ?>
            <input type="date" class="form-control form-control-sm cf-input" data-cf-id="<?= $cf['id'] ?>">
        <?php elseif ($cf['field_type'] === 'number'): ?>
            <input type="number" step="any" class="form-control form-control-sm cf-input" data-cf-id="<?= $cf['id'] ?>" style="width:100%; min-width:80px;">
        <?php else: ?>
            <input type="text" class="form-control form-control-sm cf-input" data-cf-id="<?= $cf['id'] ?>" style="width:100%; min-width:90px;">
        <?php endif; ?>
    </td>
    <?php endforeach; ?>

    <td data-label="Status">
        <select id="qa_status_id" class="form-control form-control-sm">
            <?php foreach ($statuses as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
    </td>
    <?php if (Permission::has('leads.assign')): ?>
    <td data-label="Assigned">
        <select id="qa_assigned_user_id" class="form-control form-control-sm" style="width:100%; min-width:100px;">
            <option value="">Unassigned</option>
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
        </select>
    </td>
    <?php else: ?><td data-label="Assigned"></td><?php endif; ?>
    <td data-label="Follow-up"><input type="date" id="qa_next_followup_date" class="form-control form-control-sm" style="width:100%; min-width:110px;"></td>
    <td data-label="Next Step"><input type="text" id="qa_next_step" placeholder="Next step" class="form-control form-control-sm" style="width:100%; min-width:90px;"></td>
    <td data-label="Notes"><input type="text" id="qa_notes" placeholder="Notes" class="form-control form-control-sm" style="width:100%; min-width:100px;"></td>
    <td data-label="Actions"><button class="btn btn-sm btn-primary" onclick="quickAddLead()" style="width:100%; white-space:nowrap;">+ Add</button></td>
</tr>
<?php endif; ?>

<?php if (empty($rows)): ?>
    <tr><td colspan="12" class="text-muted">No leads found.</td></tr>
<?php endif; ?>
<?php foreach ($rows as $r): ?>
    <tr id="row_<?= $r['id'] ?>">
        <?php if (Permission::has('leads.delete') || Auth::hasRole('founder')): ?>
            <td class="checkbox-col"><input type="checkbox" class="lead-checkbox" value="<?= $r['id'] ?>" onchange="updateBulkDeleteBtn()"></td>
        <?php endif; ?>
        <td data-label="ID">
            <a href="<?= url('leads', ['action' => 'view', 'id' => $r['id']]) ?>"><?= e($r['lead_code']) ?></a>
            <input type="hidden" class="edit-input" data-field="id" value="<?= $r['id'] ?>">
        </td>
        <td data-label="Folder">
            <select class="edit-input" style="width:100%;min-width:100px;" data-field="folder_id">
                <option value="">Main</option>
                <?php foreach ($allFolders ?? [] as $f): ?><option value="<?= $f['id'] ?>" <?= (string)$r['folder_id'] === (string)$f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option><?php endforeach; ?>
            </select>
        </td>
        <td data-label="Name">
            <input type="text" class="edit-input" style="width:100%;min-width:100px;" data-field="name" value="<?= e($r['name']) ?>">
        </td>
        <td data-label="Phone">
            <input type="text" class="edit-input" style="width:100%;min-width:100px;" data-field="phone" value="<?= e($r['phone']) ?>">
        </td>
        <td data-label="Email">
            <input type="email" class="edit-input" style="width:100%;min-width:110px;" data-field="email" value="<?= e($r['email']) ?>">
        </td>
        <td data-label="Company">
            <input type="text" class="edit-input" style="width:100%;min-width:100px;" data-field="company" value="<?= e($r['company'] ?? '') ?>">
        </td>
        <td data-label="Source">
            <input type="text" class="edit-input" style="width:100%;min-width:90px;" data-field="source" value="<?= e($r['source'] ?? '') ?>">
        </td>
        
        <?php if (!empty($customFields)) foreach ($customFields as $cf): 
            $val = $customValuesMap[$r['id']][$cf['id']] ?? '';
        ?>
        <td data-label="<?= e($cf['field_name']) ?>">
            <?php if ($cf['field_type'] === 'select'): 
                $opts = json_decode($cf['options'] ?: '[]', true) ?: []; ?>
                <select class="edit-input" style="width:100%;min-width:100px;" data-cf-id="<?= $cf['id'] ?>">
                    <option value="">—</option>
                    <?php foreach ($opts as $opt): ?><option value="<?= e($opt) ?>" <?= $val===$opt?'selected':'' ?>><?= e($opt) ?></option><?php endforeach; ?>
                </select>
            <?php elseif ($cf['field_type'] === 'date'): ?>
                <input type="date" class="edit-input" data-cf-id="<?= $cf['id'] ?>" value="<?= e($val) ?>">
            <?php elseif ($cf['field_type'] === 'number'): ?>
                <input type="number" step="any" class="edit-input" style="width:100%;min-width:80px;" data-cf-id="<?= $cf['id'] ?>" value="<?= e($val) ?>">
            <?php else: ?>
                <input type="text" class="edit-input" style="width:100%;min-width:90px;" data-cf-id="<?= $cf['id'] ?>" value="<?= e($val) ?>">
            <?php endif; ?>
        </td>
        <?php endforeach; ?>

        <td data-label="Status">
            <select class="edit-input" style="width:100%;min-width:100px;" data-field="status_id">
                <?php foreach ($statuses as $s): ?><option value="<?= $s['id'] ?>" <?= $s['id']==$r['status_id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
        </td>
        <td data-label="Assigned">
            <select class="edit-input" style="width:100%;min-width:100px;" data-field="assigned_user_id">
                <option value="">Unassigned</option>
                <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $u['id']==$r['assigned_user_id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
            </select>
        </td>
        <td data-label="Follow-up">
            <input type="date" class="edit-input" style="width:100%;min-width:110px;" data-field="next_followup_date" value="<?= $r['next_followup_date'] ?>">
        </td>
        <td data-label="Next Step">
            <input type="text" class="edit-input" style="width:100%;min-width:100px;" data-field="next_step" value="<?= e($r['next_step'] ?? '') ?>">
        </td>
        <td data-label="Notes">
            <input type="text" class="edit-input" style="width:100%;min-width:120px;" data-field="notes" value="<?= e($r['notes'] ?? '') ?>">
        </td>
        <td data-label="Actions" style="white-space:nowrap;">
            <?php if (Permission::has('leads.delete') || Auth::hasRole('founder')): ?>
            <form method="post" action="<?= url('leads', ['action' => 'delete']) ?>" style="display:inline;" data-confirm="Delete this lead?">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-link text-danger">Delete</button>
            </form>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<script>
(function() {
    var inputs = document.querySelectorAll('.edit-input');
    for (var i = 0; i < inputs.length; i++) {
        var inp = inputs[i];
        inp.setAttribute('data-original-value', inp.value);
        
        if (inp.tagName === 'SELECT' || inp.type === 'date' || inp.type === 'number' || inp.type === 'checkbox') {
            inp.addEventListener('change', handleInlineEdit);
        } else {
            inp.addEventListener('blur', handleInlineEdit);
            inp.addEventListener('keydown', function(e) {
                if (e.keyCode === 13 || e.key === 'Enter') this.blur();
            });
        }
    }
})();

function getClosestRow(el) {
    while (el && el.tagName !== 'TR') {
        el = el.parentNode;
    }
    return el;
}

function handleInlineEdit(e) {
    var inp = e.target || e.srcElement;
    var row = getClosestRow(inp);
    if (!row || !row.id || row.id.indexOf('row_') !== 0) return;
    
    var leadId = row.id.replace('row_', '');
    var field = inp.getAttribute('data-field');
    var cfId = inp.getAttribute('data-cf-id');
    if (!field && cfId) field = 'custom_field_' + cfId;
    if (!field || field === 'id') return;

    var newValue = inp.type === 'checkbox' ? (inp.checked ? '1' : '0') : inp.value;
    var originalValue = inp.getAttribute('data-original-value');
    
    if (newValue === originalValue) return;

    inp.disabled = true;
    var originalBg = inp.style.backgroundColor || '';
    inp.style.backgroundColor = '#f8f9fa';

    var data = {
        id: leadId,
        field: field,
        new_value: newValue,
        _csrf: '<?= e(csrf_token()) ?>'
    };

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?= url('leads', ['action' => 'api_update_inline']) ?>', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            inp.disabled = false;
            if (xhr.status === 200) {
                try {
                    var json = JSON.parse(xhr.responseText);
                    if (json.success) {
                        inp.setAttribute('data-original-value', json.new_value !== null ? json.new_value : '');
                        inp.style.backgroundColor = '#d4edda'; // success green
                        setTimeout(function() { inp.style.backgroundColor = originalBg; }, 1000);
                    } else {
                        alert('Error: ' + json.error);
                        inp.value = originalValue;
                        inp.style.backgroundColor = '#f8d7da'; // error red
                        setTimeout(function() { inp.style.backgroundColor = originalBg; }, 1000);
                    }
                } catch(err) {
                    alert('Error: Invalid server response.');
                    inp.value = originalValue;
                    inp.style.backgroundColor = '#f8d7da';
                    setTimeout(function() { inp.style.backgroundColor = originalBg; }, 1000);
                }
            } else {
                alert('Error: Network request failed.');
                inp.value = originalValue;
                inp.style.backgroundColor = '#f8d7da';
                setTimeout(function() { inp.style.backgroundColor = originalBg; }, 1000);
            }
        }
    };
    xhr.send(JSON.stringify(data));
}

function quickAddLead() {
    var data = {
        name: document.getElementById('qa_name').value,
        phone: document.getElementById('qa_phone').value,
        email: document.getElementById('qa_email').value,
        company: document.getElementById('qa_company').value,
        source: document.getElementById('qa_source').value,
        status_id: document.getElementById('qa_status_id').value,
        assigned_user_id: document.getElementById('qa_assigned_user_id') ? document.getElementById('qa_assigned_user_id').value : '',
        next_followup_date: document.getElementById('qa_next_followup_date').value,
        next_step: document.getElementById('qa_next_step').value,
        notes: document.getElementById('qa_notes').value,
        folder_id: document.getElementById('qa_folder_id') ? document.getElementById('qa_folder_id').value : ''
    };
    
    var cfInputs = document.querySelectorAll('#quick-add-row .cf-input');
    var cf = {};
    for (var i = 0; i < cfInputs.length; i++) {
        cf[cfInputs[i].dataset.cfId] = cfInputs[i].value;
    }
    data.custom_fields = cf;

    if (!data.name) return alert('Name is required');
    
    var btn = document.querySelector('#quick-add-row .btn-primary');
    if (btn) { btn.innerText = '...'; btn.disabled = true; }
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?= url('leads', ['action' => 'api_create']) ?>', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var json = JSON.parse(xhr.responseText);
                    if (json.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + json.error);
                        if (btn) { btn.innerText = '+ Add'; btn.disabled = false; }
                    }
                } catch(e) {
                    alert('Error: Failed to parse response as JSON. The server might have returned an HTML error.');
                    if (btn) { btn.innerText = '+ Add'; btn.disabled = false; }
                }
            } else {
                alert('Error: Network request failed with status ' + xhr.status);
                if (btn) { btn.innerText = '+ Add'; btn.disabled = false; }
            }
        }
    };
    xhr.send(JSON.stringify(data));
}

function toggleAllLeads(source) {
    var checkboxes = document.querySelectorAll('.lead-checkbox');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
    updateBulkDeleteBtn();
}

function updateBulkDeleteBtn() {
    var checked = document.querySelectorAll('.lead-checkbox:checked').length;
    var btn = document.getElementById('bulk-delete-btn');
    if (btn) {
        btn.style.display = checked > 0 ? 'inline-flex' : 'none';
        document.getElementById('bulk-count').innerText = checked;
    }
}

function bulkDelete() {
    var checkedNodes = document.querySelectorAll('.lead-checkbox:checked');
    var checked = [];
    for (var i = 0; i < checkedNodes.length; i++) {
        checked.push(checkedNodes[i].value);
    }
    if (checked.length === 0) return;
    if (!confirm('Are you sure you want to delete ' + checked.length + ' selected leads?')) return;
    
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= url('leads', ['action' => 'bulk_delete']) ?>';
    
    var csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = 'csrf_token';
    csrf.value = '<?= e(csrf_token()) ?>';
    form.appendChild(csrf);
    
    for (var j = 0; j < checked.length; j++) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = checked[j];
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
}
</script>
<?php render('partials/pagination', ['p' => $p]); ?>
