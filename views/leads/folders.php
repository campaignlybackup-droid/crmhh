<div class="flex-between">
    <h1>Lead Folders</h1>
    <?php if (Auth::hasRole('founder')): ?>
        <button class="btn btn-primary" onclick="document.getElementById('newFolderModal').style.display='block'">+ Create Custom Folder</button>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:24px;">
    <div class="card-title" style="margin-bottom:24px">Agency Leads by Team Member</div>
    
    <div class="grid grid-3">
        <?php if (Auth::hasRole('founder')): ?>
        <a href="<?= url('leads', ['assigned_user_id' => Auth::id()]) ?>" style="text-decoration:none; color:inherit;">
            <div class="card" style="text-align:center; padding:32px 16px; background:var(--bg-hover); transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow)'">
                <div style="font-size:3rem; margin-bottom:12px; color:var(--primary)">👑</div>
                <h3 style="margin:0 0 8px 0">My Personal Leads</h3>
                <div class="text-muted small">Only visible to you</div>
            </div>
        </a>
        <?php endif; ?>
        <?php foreach ($folders as $f): ?>
            <a href="<?= url('leads', ['assigned_user_id' => $f['id']]) ?>" style="text-decoration:none; color:inherit;">
                <div class="card" style="text-align:center; padding:32px 16px; background:var(--bg-hover); transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow)'">
                    <div style="font-size:3rem; margin-bottom:12px; color:var(--primary)">📁</div>
                    <h3 style="margin:0 0 8px 0"><?= e($f['name']) ?><?= $f['id'] === Auth::id() ? ' (YOU)' : '' ?></h3>
                    <div class="text-muted small"><?= (int)$f['lead_count'] ?> Active Lead<?= (int)$f['lead_count'] !== 1 ? 's' : '' ?></div>
                </div>
            </a>
        <?php endforeach; ?>

        <a href="<?= url('leads', ['assigned_user_id' => 'all']) ?>" style="text-decoration:none; color:inherit;">
            <div class="card" style="text-align:center; padding:32px 16px; background:var(--bg-hover); transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow)'">
                <div style="font-size:3rem; margin-bottom:12px; color:var(--text-muted)">📋</div>
                <h3 style="margin:0 0 8px 0">All Leads</h3>
                <div class="text-muted small">View all agency leads</div>
            </div>
        </a>

        <a href="<?= url('leads', ['assigned_user_id' => 'unassigned']) ?>" style="text-decoration:none; color:inherit;">
            <div class="card" style="text-align:center; padding:32px 16px; background:var(--bg-hover); transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow)'">
                <div style="font-size:3rem; margin-bottom:12px; color:var(--text-muted)">📂</div>
                <h3 style="margin:0 0 8px 0">Unassigned Leads</h3>
                <div class="text-muted small">View unassigned</div>
            </div>
        </a>
    </div>
</div>

<?php if (!empty($customFolders)): ?>
<div class="card" style="margin-top:24px;">
    <div class="card-title" style="margin-bottom:24px">Custom Shared Folders</div>
    <div class="grid grid-4">
        <?php foreach ($customFolders as $cf): ?>
            <a href="<?= url('leads', ['folder_id' => $cf['id']]) ?>" style="text-decoration:none; color:inherit;">
                <div class="card" style="text-align:center; padding:24px 16px; background:var(--bg-hover); transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow)'">
                    <div style="font-size:2.5rem; margin-bottom:12px; color:var(--warning)">📁</div>
                    <h3 style="margin:0 0 8px 0"><?= e($cf['name']) ?></h3>
                    <div class="text-muted small"><?= (int)$cf['lead_count'] ?> Active Lead<?= (int)$cf['lead_count'] !== 1 ? 's' : '' ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (Auth::hasRole('founder')): ?>
<div id="newFolderModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999;">
    <div class="modal-content card" style="max-width:500px; margin:100px auto; padding:24px;">
        <h2 style="margin-top:0;">Create Custom Folder</h2>
        <div class="form-group">
            <label>Folder Name</label>
            <input type="text" id="cf_name" class="form-control" placeholder="e.g. Q4 High Value Leads">
        </div>
        <div class="form-group">
            <label>Share with Users (Hold Ctrl/Cmd to select multiple)</label>
            <select id="cf_users" class="form-control" multiple style="height:150px;">
                <?php foreach (UserModel::activeSelectList() as $u): if($u['id'] == Auth::id()) continue; ?>
                    <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-between" style="margin-top:24px;">
            <button class="btn btn-primary" onclick="createFolder()">Create Folder</button>
            <button class="btn" onclick="document.getElementById('newFolderModal').style.display='none'">Cancel</button>
        </div>
    </div>
</div>
<script>
async function createFolder() {
    const name = document.getElementById('cf_name').value;
    const usersSelect = document.getElementById('cf_users');
    const users = Array.from(usersSelect.selectedOptions).map(opt => opt.value);
    
    if (!name) return alert('Name is required');
    
    const res = await fetch('<?= url('leads', ['action' => 'api_create_folder']) ?>', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ name, users })
    });
    const json = await res.json();
    if (json.success) {
        window.location.reload();
    } else {
        alert(json.error);
    }
}
</script>
<?php endif; ?>
