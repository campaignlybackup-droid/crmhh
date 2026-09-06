<h1>Lead Folders</h1>

<div class="card">
    <div class="card-title" style="margin-bottom:24px">Agency Leads by Team Member</div>
    
    <div class="grid grid-3">
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
