<div class="flex-between">
    <h1>Announcements</h1>
    <?php if ($canManage): ?>
        <button type="button" class="btn btn-primary" data-modal-open="createAnnouncementModal">+ New Announcement</button>
    <?php endif; ?>
</div>

<div class="grid grid-1" style="margin-top:24px;">
    <?php if (empty($announcements)): ?>
        <p class="text-muted">No announcements found.</p>
    <?php else: ?>
        <?php foreach ($announcements as $a): ?>
            <div class="card" style="border-left: 4px solid var(--primary);">
                <div class="flex-between" style="margin-bottom:12px;">
                    <h2 style="margin:0; font-size: 18px;"><?= e($a['title']) ?></h2>
                    <span class="text-muted small"><?= format_datetime($a['created_at']) ?></span>
                </div>
                <div style="margin-bottom:16px;">
                    <?= nl2br(e($a['content'])) ?>
                </div>
                <div class="flex-between" style="font-size: 12px;">
                    <span class="text-muted">Posted by <strong><?= e($a['author_name']) ?></strong></span>
                    <?php if ($canManage): ?>
                        <div>
                            <button type="button" class="btn-link text-primary" style="margin-right: 12px;" onclick="openEditModal(<?= $a['id'] ?>, <?= htmlspecialchars(json_encode($a['title'])) ?>, <?= htmlspecialchars(json_encode($a['content'])) ?>)">Edit</button>
                            <form method="post" action="<?= url('announcements', ['action' => 'delete']) ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <button class="btn-link text-danger">Delete</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($canManage): ?>
<!-- Create Modal -->
<div class="modal-overlay" id="createAnnouncementModal">
    <div class="modal">
        <span class="modal-close" data-modal-close>&times;</span>
        <div class="modal-title">Post Announcement</div>
        <form method="post" action="<?= url('announcements', ['action' => 'create']) ?>">
            <?= Csrf::field() ?>
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" required placeholder="Important Update">
            </div>
            <div class="form-group">
                <label>Content</label>
                <textarea name="content" required rows="6" placeholder="Write your announcement here..."></textarea>
            </div>
            <button class="btn btn-primary">Post Announcement</button>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editAnnouncementModal">
    <div class="modal">
        <span class="modal-close" data-modal-close>&times;</span>
        <div class="modal-title">Edit Announcement</div>
        <form method="post" action="<?= url('announcements', ['action' => 'update']) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" id="edit_announcement_id">
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" id="edit_announcement_title" required>
            </div>
            <div class="form-group">
                <label>Content</label>
                <textarea name="content" id="edit_announcement_content" required rows="6"></textarea>
            </div>
            <button class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<script>
function openEditModal(id, title, content) {
    document.getElementById('edit_announcement_id').value = id;
    document.getElementById('edit_announcement_title').value = title;
    document.getElementById('edit_announcement_content').value = content;
    document.getElementById('editAnnouncementModal').classList.add('show');
}
</script>
<?php endif; ?>
