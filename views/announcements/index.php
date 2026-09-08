<div class="flex-between">
    <h1>Announcements</h1>
    <?php if ($canManage): ?>
        <button class="btn btn-primary" data-modal-open="createAnnouncementModal">+ New Announcement</button>
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
                        <form method="post" action="<?= url('announcements', ['action' => 'delete']) ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button class="btn-link text-danger">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($canManage): ?>
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
<?php endif; ?>
