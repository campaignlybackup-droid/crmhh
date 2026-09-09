<div class="flex-between mb-4">
    <h1>Content Calendar</h1>
    <button class="btn btn-primary" data-modal-open="addContentModal">+ New Content</button>
</div>

<div class="card mb-4" style="background:var(--bg)">
    <form method="get" class="form-row" style="align-items:flex-end">
        <div class="form-group mb-0">
            <label>Filter by Client</label>
            <select name="client_id">
                <option value="">All Clients</option>
                <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>" <?= $c['id']==$viewClientId?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <button class="btn btn-sm btn-secondary">Filter</button>
        </div>
    </form>
</div>

<div class="grid grid-4" style="gap:16px; margin-bottom:24px;">
    <?php
        $statuses = ['draft' => 'Drafts', 'pending_approval' => 'Pending Approval', 'scheduled' => 'Scheduled', 'published' => 'Published'];
        foreach ($statuses as $statKey => $statLabel):
    ?>
    <div class="card" style="padding:12px; background:var(--bg); box-shadow:none; border:1px solid var(--border)">
        <h3 style="font-size:14px; margin-top:0; margin-bottom:12px; padding-bottom:8px; border-bottom:1px solid var(--border)"><?= $statLabel ?></h3>
        
        <?php 
            $count = 0;
            foreach ($posts as $p): 
                if ($p['status'] !== $statKey) continue;
                $count++;
        ?>
        <div class="card" style="padding:12px; margin-bottom:8px; box-shadow:0 1px 3px rgba(0,0,0,0.05)">
            <div class="flex-between" style="margin-bottom:4px">
                <span class="badge badge-secondary" style="font-size:10px"><?= format_date($p['post_date']) ?></span>
                <span class="badge badge-primary" style="font-size:10px"><?= e($p['subcategory_name'] ?: $p['service_name']) ?></span>
            </div>
            <strong style="font-size:13px; display:block; margin-bottom:4px"><?= e($p['title']) ?></strong>
            <div style="font-size:11px; color:var(--text-muted); margin-bottom:8px"><?= e($p['client_name']) ?></div>
            
            <button class="btn btn-sm" style="width:100%; font-size:11px; padding:4px" onclick="editContent(<?= htmlspecialchars(json_encode($p)) ?>)">Edit</button>
        </div>
        <?php endforeach; ?>
        <?php if ($count === 0): ?><div style="font-size:12px; color:var(--text-muted); text-align:center; padding:16px 0">No items</div><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>


<!-- Modals -->
<div class="modal-overlay" id="addContentModal">
    <div class="modal" style="max-width:500px">
        <span class="modal-close" data-modal-close>&times;</span>
        <div class="modal-title">Schedule New Content</div>
        <form method="post" action="<?= url('content_calendar', ['action' => 'store']) ?>">
            <?= Csrf::field() ?>
            <div class="form-group"><label>Title (e.g. "Summer Sale Reel")</label><input type="text" name="title" required></div>
            <div class="form-row">
                <div class="form-group"><label>Client</label>
                    <select name="client_id" required>
                        <option value="">—</option>
                        <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Service</label>
                    <select name="service_id" id="serviceSelect" required onchange="updateSubcategories('serviceSelect', 'subcategorySelect')">
                        <option value="">—</option>
                        <?php foreach ($services as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Subcategory</label>
                    <select name="subcategory_id" id="subcategorySelect">
                        <option value="">— Select Service First —</option>
                    </select>
                </div>
                <div class="form-group"><label>Post Date</label><input type="date" name="post_date" required></div>
            </div>
            <div class="form-group"><label>Status</label>
                <select name="status">
                    <option value="draft">Draft</option>
                    <option value="pending_approval">Pending Approval</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="published">Published</option>
                </select>
            </div>
            <div class="form-group"><label>Assignee</label>
                <select name="assigned_to"><option value="">—</option>
                    <?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Content / Caption</label><textarea name="content" rows="3"></textarea></div>
            <button class="btn btn-primary w-100">Add Content</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editContentModal">
    <div class="modal" style="max-width:500px">
        <span class="modal-close" data-modal-close>&times;</span>
        <div class="modal-title">Edit Content</div>
        <form method="post" action="<?= url('content_calendar', ['action' => 'update']) ?>" id="editContentForm">
            <?= Csrf::field() ?><input type="hidden" name="id" id="editContentId">
            <div class="form-group"><label>Title</label><input type="text" name="title" id="editTitle" required></div>
            <div class="form-row">
                <div class="form-group"><label>Post Date</label><input type="date" name="post_date" id="editDate" required></div>
                <div class="form-group"><label>Status</label>
                    <select name="status" id="editStatus">
                        <option value="draft">Draft</option>
                        <option value="pending_approval">Pending Approval</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="published">Published</option>
                    </select>
                </div>
            </div>
            <div class="form-group"><label>Assignee</label>
                <select name="assigned_to" id="editAssignee"><option value="">—</option>
                    <?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Content / Caption</label><textarea name="content" id="editContentBody" rows="3"></textarea></div>
            <div class="flex-between">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="submit" formaction="<?= url('content_calendar', ['action' => 'delete']) ?>" class="btn btn-danger" onclick="return confirm('Delete this content?')">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
const servicesData = <?= json_encode($services) ?>;

function updateSubcategories(serviceSelectId, subcategorySelectId) {
    const sid = document.getElementById(serviceSelectId).value;
    const subSelect = document.getElementById(subcategorySelectId);
    subSelect.innerHTML = '<option value="">—</option>';
    
    if (sid) {
        const svc = servicesData.find(s => s.id == sid);
        if (svc && svc.subcategories) {
            svc.subcategories.forEach(sub => {
                subSelect.innerHTML += `<option value="${sub.id}">${sub.name}</option>`;
            });
        }
    }
}

function editContent(post) {
    document.getElementById('editContentId').value = post.id;
    document.getElementById('editTitle').value = post.title;
    document.getElementById('editDate').value = post.post_date;
    document.getElementById('editStatus').value = post.status;
    document.getElementById('editAssignee').value = post.assigned_to || '';
    document.getElementById('editContentBody').value = post.content || '';
    document.getElementById('editContentModal').classList.add('show');
}
</script>
