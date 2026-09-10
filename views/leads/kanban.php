<?php
// Group leads by status
$kanbanColumns = [];
foreach ($statuses as $s) {
    $kanbanColumns[$s['id']] = ['status' => $s, 'leads' => []];
}
// Leads without a matching status (if any)
$kanbanColumns['unassigned'] = ['status' => ['id' => 'unassigned', 'name' => 'No Status', 'color' => '#6c757d'], 'leads' => []];

foreach ($rows as $lead) {
    $sid = $lead['status_id'] ?: 'unassigned';
    if (!isset($kanbanColumns[$sid])) $sid = 'unassigned';
    $kanbanColumns[$sid]['leads'][] = $lead;
}

// Remove unassigned column if empty
if (empty($kanbanColumns['unassigned']['leads'])) {
    unset($kanbanColumns['unassigned']);
}
?>

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
        <?php if (Permission::has('leads.create')): ?><a href="<?= url('leads', ['action' => 'create']) ?>" class="btn btn-primary">+ New Lead</a><?php endif; ?>
    </div>
</div>

<form class="filters-bar" method="get">
    <input type="hidden" name="page" value="leads">
    <input type="hidden" name="view" value="kanban">
    <div class="form-group">
        <label>Search</label>
        <input type="text" name="search" value="<?= e($filters['search']) ?>" placeholder="Name, phone, email, code&hellip;">
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
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="<?= url('leads', ['view' => 'kanban'] + (!empty($filters['folder_id']) ? ['folder_id' => $filters['folder_id']] : [])) ?>" class="btn">Reset</a>
    </div>
</form>

<div class="kanban-board" style="display: flex; overflow-x: auto; gap: 16px; padding: 16px 0; min-height: 70vh;">
    <?php foreach ($kanbanColumns as $colId => $col): ?>
        <div class="kanban-column" style="min-width: 320px; max-width: 320px; background: var(--bg-hover); border-radius: 8px; padding: 16px; display: flex; flex-direction: column;" data-status-id="<?= e($colId) ?>" ondragover="allowDrop(event)" ondrop="drop(event)">
            <h3 style="margin-top: 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid <?= e($col['status']['color'] ?? '#ccc') ?>; padding-bottom: 8px;">
                <?= e($col['status']['name']) ?>
                <span class="badge" style="background: var(--text-muted);"><?= count($col['leads']) ?></span>
            </h3>
            
            <div class="kanban-cards" style="flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($col['leads'] as $lead): ?>
                    <div class="kanban-card card" style="padding: 12px; cursor: grab; background: var(--bg); border-left: 4px solid <?= e($col['status']['color'] ?? '#ccc') ?>;" draggable="true" ondragstart="drag(event)" id="lead-<?= $lead['id'] ?>" data-lead-id="<?= $lead['id'] ?>">
                        <div class="flex-between" style="margin-bottom: 8px;">
                            <strong><a href="<?= url('leads', ['action' => 'view', 'id' => $lead['id']]) ?>" style="color: inherit; text-decoration: none;"><?= e($lead['name']) ?></a></strong>
                            <span class="text-muted small"><?= e($lead['lead_code']) ?></span>
                        </div>
                        
                        <?php if ($lead['company']): ?>
                            <div class="small text-muted" style="margin-bottom: 4px;">🏢 <?= e($lead['company']) ?></div>
                        <?php endif; ?>
                        
                        <div class="flex-between small" style="margin-top: 12px;">
                            <div>
                                <?php if ($lead['assigned_name']): ?>
                                    <span class="badge" style="background: var(--bg-hover); color: var(--text);">👤 <?= e($lead['assigned_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Unassigned</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($lead['next_followup_date']): ?>
                                <?php 
                                    $due = strtotime($lead['next_followup_date']);
                                    $today = strtotime(date('Y-m-d'));
                                    $color = $due < $today ? 'var(--danger)' : ($due == $today ? 'var(--warning)' : 'var(--text-muted)');
                                ?>
                                <span style="color: <?= $color ?>;">📅 <?= date('M j', $due) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
.kanban-board::-webkit-scrollbar { height: 8px; }
.kanban-board::-webkit-scrollbar-track { background: var(--border); border-radius: 4px; }
.kanban-board::-webkit-scrollbar-thumb { background: var(--text-muted); border-radius: 4px; }
.kanban-card:active { cursor: grabbing; }
.kanban-column.drag-over { background: var(--border) !important; }
</style>

<script>
function allowDrop(ev) {
    ev.preventDefault();
    ev.currentTarget.classList.add('drag-over');
}

function drag(ev) {
    ev.dataTransfer.setData("text/plain", ev.currentTarget.id);
}

document.querySelectorAll('.kanban-column').forEach(col => {
    col.addEventListener('dragleave', e => {
        if (e.target === col) {
            col.classList.remove('drag-over');
        }
    });
});

async function drop(ev) {
    ev.preventDefault();
    let col = ev.currentTarget;
    col.classList.remove('drag-over');
    
    let leadElId = ev.dataTransfer.getData("text/plain");
    let leadEl = document.getElementById(leadElId);
    let cardsContainer = col.querySelector('.kanban-cards');
    
    // Prevent dropping in same column
    if (leadEl.closest('.kanban-column') === col) return;
    
    // Move DOM element
    cardsContainer.appendChild(leadEl);
    
    // Update count badges
    updateBadges();
    
    // Save to DB
    let leadId = leadEl.getAttribute('data-lead-id');
    let statusId = col.getAttribute('data-status-id');
    
    if (statusId === 'unassigned') return; // Cannot explicitly unassign via kanban yet
    
    try {
        let res = await fetch('<?= url('leads', ['action' => 'api_update_status']) ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: leadId, status_id: statusId})
        });
        
        let json;
        try {
            json = await res.json();
        } catch (parseError) {
            console.error('Failed to parse response as JSON. Server might have returned an error page.');
            alert('Server error occurred while updating status. See console.');
            window.location.reload();
            return;
        }
        
        if (!json.success) {
            alert('Failed to update status: ' + (json.error || 'Unknown error'));
            window.location.reload();
        } else {
            // Update border color of the card to match the new column
            let color = col.querySelector('h3').style.borderBottomColor;
            leadEl.style.borderLeftColor = color;
        }
    } catch (e) {
        console.error('Network Error:', e);
        alert('Network error occurred.');
        window.location.reload();
    }
}

function updateBadges() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        let count = col.querySelectorAll('.kanban-card').length;
        col.querySelector('.badge').textContent = count;
    });
}
</script>
