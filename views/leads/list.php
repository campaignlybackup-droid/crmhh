<div class="flex-between">
    <h1>Leads</h1>
    <div class="btn-group">
        <?php if (Permission::has('leads.import')): ?><a href="<?= url('leads', ['action' => 'import']) ?>" class="btn">Import CSV</a><?php endif; ?>
        <?php if (Permission::has('leads.export')): ?><a href="<?= url('leads', ['action' => 'export'] + $filters) ?>" class="btn">Export CSV</a><?php endif; ?>
        <?php if (Permission::has('leads.create')): ?><a href="<?= url('leads', ['action' => 'create']) ?>" class="btn btn-primary">+ New Lead</a><?php endif; ?>
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

<?php if (Permission::has('leads.delete') || Auth::hasRole('founder')): ?>
<div id="bulk-action-bar" style="display:none; position:fixed; bottom:30px; left:50%; transform:translateX(-50%); background:#fff; padding:12px 24px; border-radius:30px; box-shadow:0 4px 20px rgba(0,0,0,0.15); z-index:999; border:1px solid var(--border); align-items:center; justify-content:center; gap:16px;">
    <span id="bulk-count" style="font-weight:bold; color:var(--text);">0 leads selected</span>
    <button class="btn btn-danger btn-sm" onclick="bulkDelete()" style="border-radius:20px;">Delete Selected</button>
</div>
<?php endif; ?>

<div class="table-wrap responsive-table" style="position:relative;">
<table>
<thead><tr>
    <?php if (Permission::has('leads.delete') || Auth::hasRole('founder')): ?><th style="width:30px;"><input type="checkbox" onclick="toggleAllLeads(this)" title="Select All"></th><?php endif; ?>
    <th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Company</th><th>Source</th><th>Status</th><th>Assigned</th><th>Follow-up</th><th>Next Step</th><th>Notes</th><th>Actions</th>
</tr></thead>
<tbody>
<?php if (Permission::has('leads.create')): ?>
<tr id="quick-add-row" style="background:var(--bg-hover)">
    <?php if (Permission::has('leads.delete') || Auth::hasRole('founder')): ?><td></td><?php endif; ?>
    <td data-label="ID" class="text-muted small">New</td>
    <td data-label="Name"><input type="text" id="qa_name" placeholder="Name *" class="form-control form-control-sm" style="width:100%; min-width:90px;"></td>
    <td data-label="Phone"><input type="text" id="qa_phone" placeholder="Phone" class="form-control form-control-sm" style="width:100%; min-width:90px;"></td>
    <td data-label="Email"><input type="email" id="qa_email" placeholder="Email" class="form-control form-control-sm" style="width:100%; min-width:100px;"></td>
    <td data-label="Company"><input type="text" id="qa_company" placeholder="Company" class="form-control form-control-sm" style="width:100%; min-width:90px;"></td>
    <td data-label="Source"><input type="text" id="qa_source" placeholder="Source" class="form-control form-control-sm" style="width:100%; min-width:80px;"></td>
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
            <td><input type="checkbox" class="lead-checkbox" value="<?= $r['id'] ?>" onchange="updateBulkDeleteBtn()"></td>
        <?php endif; ?>
        <td data-label="ID">
            <a href="<?= url('leads', ['action' => 'view', 'id' => $r['id']]) ?>"><?= e($r['lead_code']) ?></a>
            <input type="hidden" class="edit-input" data-field="id" value="<?= $r['id'] ?>">
        </td>
        <td data-label="Name">
            <span class="view-mode"><?= e($r['name']) ?></span>
            <input type="text" class="edit-input edit-mode form-control form-control-sm" style="display:none;width:100%;min-width:100px;" data-field="name" value="<?= e($r['name']) ?>">
        </td>
        <td data-label="Phone">
            <span class="view-mode"><?= e($r['phone']) ?></span>
            <input type="text" class="edit-input edit-mode form-control form-control-sm" style="display:none;width:100%;min-width:90px;" data-field="phone" value="<?= e($r['phone']) ?>">
        </td>
        <td data-label="Email">
            <span class="view-mode" style="word-break:break-all;"><?= e($r['email']) ?></span>
            <input type="email" class="edit-input edit-mode form-control form-control-sm" style="display:none;width:100%;min-width:100px;" data-field="email" value="<?= e($r['email']) ?>">
        </td>
        <td data-label="Company">
            <span class="view-mode"><?= e($r['company']) ?></span>
            <input type="text" class="edit-input edit-mode form-control form-control-sm" style="display:none;width:100%;min-width:100px;" data-field="company" value="<?= e($r['company']) ?>">
        </td>
        <td data-label="Source">
            <span class="view-mode"><?= e($r['source']) ?></span>
            <input type="text" class="edit-input edit-mode form-control form-control-sm" style="display:none;width:100%;min-width:80px;" data-field="source" value="<?= e($r['source']) ?>">
        </td>
        <td data-label="Status">
            <span class="view-mode badge" style="background:<?= e($r['status_color']) ?>; white-space:normal; text-align:center;"><?= e($r['status_name']) ?></span>
            <select class="edit-input edit-mode form-control form-control-sm" style="display:none;width:100%;min-width:100px;" data-field="status_id">
                <?php foreach ($statuses as $s): ?><option value="<?= $s['id'] ?>" <?= $s['id']==$r['status_id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
        </td>
        <td data-label="Assigned"><?= e($r['assigned_name'] ?? 'Unassigned') ?></td>
        <td data-label="Follow-up">
            <span class="view-mode"><?= format_date($r['next_followup_date']) ?></span>
            <input type="date" class="edit-input edit-mode form-control form-control-sm" style="display:none;width:100%;min-width:110px;" data-field="next_followup_date" value="<?= $r['next_followup_date'] ?>">
        </td>
        <td data-label="Next Step">
            <span class="view-mode"><?= e($r['next_step'] ?? '') ?></span>
            <input type="text" class="edit-input edit-mode form-control form-control-sm" style="display:none;width:100%;min-width:100px;" data-field="next_step" value="<?= e($r['next_step'] ?? '') ?>">
        </td>
        <td data-label="Notes">
            <span class="view-mode"><?= e($r['notes'] ?? '') ?></span>
            <input type="text" class="edit-input edit-mode form-control form-control-sm" style="display:none;width:100%;min-width:120px;" data-field="notes" value="<?= e($r['notes'] ?? '') ?>">
        </td>
        <td data-label="Actions" style="white-space:nowrap;">
            <button class="btn btn-sm btn-link view-mode" onclick="toggleEdit(<?= $r['id'] ?>)">Edit</button>
            <?php if (Permission::has('leads.delete') || Auth::hasRole('founder')): ?>
            <form method="post" action="<?= url('leads', ['action' => 'delete']) ?>" style="display:inline;" data-confirm="Delete this lead?">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-link text-danger view-mode">Delete</button>
            </form>
            <?php endif; ?>
            <button class="btn btn-sm btn-primary edit-mode" style="display:none;" onclick="saveEdit(<?= $r['id'] ?>)">Save</button>
            <button class="btn btn-sm btn-link text-muted edit-mode" style="display:none;" onclick="toggleEdit(<?= $r['id'] ?>)">Cancel</button>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<script>
function toggleEdit(id) {
    const row = document.getElementById('row_' + id);
    const viewModes = row.querySelectorAll('.view-mode');
    const editModes = row.querySelectorAll('.edit-mode');
    const isEditing = row.classList.contains('is-editing');
    
    if (isEditing) {
        viewModes.forEach(el => el.style.display = '');
        editModes.forEach(el => el.style.display = 'none');
        row.classList.remove('is-editing');
    } else {
        viewModes.forEach(el => el.style.display = 'none');
        editModes.forEach(el => el.style.display = '');
        row.classList.add('is-editing');
    }
}

async function saveEdit(id) {
    const row = document.getElementById('row_' + id);
    const inputs = row.querySelectorAll('.edit-input');
    const data = {};
    inputs.forEach(inp => data[inp.dataset.field] = inp.value);
    
    try {
        const btn = row.querySelector('.btn-primary.edit-mode');
        btn.innerText = '...'; btn.disabled = true;
        
        const res = await fetch('<?= url('leads', ['action' => 'api_update']) ?>', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const json = await res.json();
        
        if (json.success) {
            window.location.reload(); // Quickest way to reflect colors, statuses, sorting
        } else {
            alert('Error: ' + json.error);
            btn.innerText = 'Save'; btn.disabled = false;
        }
    } catch (e) {
        alert('Network error.');
        window.location.reload();
    }
}

async function quickAddLead() {
    const data = {
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
        folder_id: '<?= e($filters['folder_id'] ?? '') ?>'
    };
    
    if (!data.name) return alert('Name is required');
    
    try {
        const btn = document.querySelector('#quick-add-row .btn-primary');
        btn.innerText = '...'; btn.disabled = true;
        
        const res = await fetch('<?= url('leads', ['action' => 'api_create']) ?>', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const json = await res.json();
        
        if (json.success) {
            window.location.reload();
        } else {
            alert('Error: ' + json.error);
            btn.innerText = '+ Add'; btn.disabled = false;
        }
    } catch (e) {
        alert('Network error.');
        window.location.reload();
    }
}

function toggleAllLeads(source) {
    const checkboxes = document.querySelectorAll('.lead-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateBulkDeleteBtn();
}

function updateBulkDeleteBtn() {
    const checked = document.querySelectorAll('.lead-checkbox:checked').length;
    const bar = document.getElementById('bulk-action-bar');
    if (bar) {
        bar.style.display = checked > 0 ? 'flex' : 'none';
        document.getElementById('bulk-count').innerText = checked + ' leads selected';
    }
}

function bulkDelete() {
    const checked = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(cb => cb.value);
    if (checked.length === 0) return;
    if (!confirm('Are you sure you want to delete ' + checked.length + ' selected leads?')) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= url('leads', ['action' => 'bulk_delete']) ?>';
    
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = 'csrf_token';
    csrf.value = '<?= e(csrf_token()) ?>';
    form.appendChild(csrf);
    
    checked.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
}
</script>
<?php render('partials/pagination', ['p' => $p]); ?>
