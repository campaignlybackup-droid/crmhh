<div class="flex-between mb-3" style="flex-wrap:wrap; gap:12px;">
    <div>
        <h1 style="margin-bottom:4px;">Content Calendar &amp; Deliverables</h1>
        <div class="text-muted" style="font-size:13px;">Real-time pipeline linking Reels, Posts, and client deliverables with 2-tier Double Check verification.</div>
    </div>
    <div class="btn-group" style="flex-wrap:wrap;">
        <button class="btn btn-primary" onclick="openAddContentModal('reel')" style="background:linear-gradient(135deg, #7c3aed, #4f46e5); border:none; color:#fff;">
            🎬 + Add Reel
        </button>
        <button class="btn btn-primary" onclick="openAddContentModal('post')" style="background:linear-gradient(135deg, #0284c7, #0369a1); border:none; color:#fff;">
            🖼️ + Add Post
        </button>
        <button class="btn btn-secondary" onclick="openAddContentModal('reel')">
            + Custom Item
        </button>
    </div>
</div>

<!-- Quick Stats & View Switcher -->
<div class="grid grid-4 mb-4" style="gap:12px;">
    <a href="<?= url('content_calendar', ['type' => '', 'client_id' => $viewClientId, 'view_mode' => 'pipeline']) ?>" class="card text-center" style="padding:14px; text-decoration:none; border:<?= empty($contentTypeFilter) && $viewMode !== 'by_client' ? '2px solid var(--primary)' : '1px solid var(--border)' ?>; background:var(--card-bg);">
        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); font-weight:700;">All Content Items</div>
        <div style="font-size:24px; font-weight:800; color:var(--text); margin-top:4px;"><?= count($posts) ?></div>
        <div style="font-size:11px; color:var(--primary); font-weight:600; margin-top:2px;">View Pipeline &rarr;</div>
    </a>
    <a href="<?= url('content_calendar', ['type' => 'reel', 'client_id' => $viewClientId, 'view_mode' => 'pipeline']) ?>" class="card text-center" style="padding:14px; text-decoration:none; border:<?= $contentTypeFilter === 'reel' && $viewMode !== 'by_client' ? '2px solid #7c3aed' : '1px solid var(--border)' ?>; background:var(--card-bg);">
        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#7c3aed; font-weight:700;">🎬 Reels Deliverables</div>
        <div style="font-size:24px; font-weight:800; color:#7c3aed; margin-top:4px;"><?= $totalReelsCount ?></div>
        <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Reels only</div>
    </a>
    <a href="<?= url('content_calendar', ['type' => 'post', 'client_id' => $viewClientId, 'view_mode' => 'pipeline']) ?>" class="card text-center" style="padding:14px; text-decoration:none; border:<?= $contentTypeFilter === 'post' && $viewMode !== 'by_client' ? '2px solid #0284c7' : '1px solid var(--border)' ?>; background:var(--card-bg);">
        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:#0284c7; font-weight:700;">🖼️ Posts &amp; Statics</div>
        <div style="font-size:24px; font-weight:800; color:#0284c7; margin-top:4px;"><?= $totalPostsCount ?></div>
        <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Posts / Graphics only</div>
    </a>
    <a href="<?= url('content_calendar', ['view_mode' => 'by_client', 'client_id' => $viewClientId]) ?>" class="card text-center" style="padding:14px; text-decoration:none; border:<?= $viewMode === 'by_client' ? '2px solid var(--success)' : '1px solid var(--border)' ?>; background:var(--card-bg);">
        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:var(--success); font-weight:700;">🏢 Grouped by Client</div>
        <div style="font-size:24px; font-weight:800; color:var(--success); margin-top:4px;"><?= count($clients) ?> Clients</div>
        <div style="font-size:11px; color:var(--success); font-weight:600; margin-top:2px;">Client deliverable cards &rarr;</div>
    </a>
</div>

<!-- Filters Bar -->
<div class="card mb-4" style="background:var(--bg); padding:12px 16px;">
    <form method="get" class="form-row" style="align-items:flex-end; gap:12px; margin:0;">
        <input type="hidden" name="view_mode" value="<?= e($viewMode) ?>">
        <?php if (!empty($contentTypeFilter)): ?>
            <input type="hidden" name="type" value="<?= e($contentTypeFilter) ?>">
        <?php endif; ?>
        
        <div class="form-group mb-0" style="min-width:200px; flex:1;">
            <label style="font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:4px;">Filter by Client</label>
            <select name="client_id" onchange="this.form.submit()" class="form-control" style="font-size:13px;">
                <option value="">All Clients (<?= count($clients) ?>)</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $viewClientId ? 'selected' : '' ?>><?= e($c['name']) ?><?= !empty($c['company']) ? ' (' . e($c['company']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group mb-0" style="min-width:160px;">
            <label style="font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:4px;">Content Format</label>
            <select name="type" onchange="this.form.submit()" class="form-control" style="font-size:13px;">
                <option value="" <?= empty($contentTypeFilter) ? 'selected' : '' ?>>All Formats (Reels &amp; Posts)</option>
                <option value="reel" <?= $contentTypeFilter === 'reel' ? 'selected' : '' ?>>🎬 Reels Only</option>
                <option value="post" <?= $contentTypeFilter === 'post' ? 'selected' : '' ?>>🖼️ Posts &amp; Statics Only</option>
            </select>
        </div>

        <div class="form-group mb-0" style="display:flex; gap:6px;">
            <button class="btn btn-sm btn-secondary" style="height:36px;">Apply Filters</button>
            <?php if ($viewClientId || $contentTypeFilter || $viewMode === 'by_client'): ?>
                <a href="<?= url('content_calendar') ?>" class="btn btn-sm" style="height:36px; line-height:24px;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($viewMode === 'by_client'): ?>
    <!-- ============================================================= -->
    <!-- CLIENT GROUPED VIEW                                            -->
    <!-- ============================================================= -->
    <div class="mb-4">
        <h2 style="font-size:18px; margin-bottom:14px; display:flex; align-items:center; gap:8px;">
            <span>🏢 Deliverables Grouped by Client</span>
            <span class="badge badge-secondary" style="font-size:12px;"><?= count($clientsDeliverables) ?> Clients with Content</span>
        </h2>

        <?php if (empty($clientsDeliverables)): ?>
            <div class="card text-center" style="padding:40px 20px;">
                <div style="font-size:32px; margin-bottom:10px;">📋</div>
                <div style="font-weight:700; font-size:16px;">No Client Content Found</div>
                <p class="text-muted small">No deliverables or content calendar items have been scheduled yet.</p>
                <button class="btn btn-primary" onclick="openAddContentModal('reel')">+ Schedule First Content</button>
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:20px;">
                <?php foreach ($clientsDeliverables as $cd): 
                    $reelsCount = count($cd['reels']);
                    $postsCount = count($cd['posts']);
                    $reelsReq = $cd['reels_required'];
                    $postsReq = $cd['posts_required'];
                    $reelsPct = $reelsReq > 0 ? min(100, round(($cd['reels_completed'] / $reelsReq) * 100)) : ($reelsCount > 0 ? 100 : 0);
                    $postsPct = $postsReq > 0 ? min(100, round(($cd['posts_completed'] / $postsReq) * 100)) : ($postsCount > 0 ? 100 : 0);
                ?>
                <div class="card" style="padding:18px; border-left:5px solid var(--primary); box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                    <div class="flex-between mb-3" style="flex-wrap:wrap; gap:10px; border-bottom:1px solid var(--border); padding-bottom:12px;">
                        <div>
                            <a href="<?= url('clients', ['action' => 'view', 'id' => $cd['id']]) ?>" style="font-size:18px; font-weight:800; color:var(--text); text-decoration:none;">
                                <?= e($cd['name']) ?>
                            </a>
                            <?php if (!empty($cd['company'])): ?>
                                <span class="text-muted" style="font-size:13px; margin-left:6px;">— <?= e($cd['company']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-primary" onclick="openAddContentModal('reel', <?= $cd['id'] ?>)" style="background:#7c3aed; border-color:#7c3aed; font-size:11px;">+ Add Reel</button>
                            <button class="btn btn-sm btn-primary" onclick="openAddContentModal('post', <?= $cd['id'] ?>)" style="background:#0284c7; border-color:#0284c7; font-size:11px;">+ Add Post</button>
                            <a href="<?= url('clients', ['action' => 'view', 'id' => $cd['id']]) ?>" class="btn btn-sm btn-secondary" style="font-size:11px;">View Client &rarr;</a>
                        </div>
                    </div>

                    <!-- Client Deliverable Counters -->
                    <div class="grid grid-2 mb-3" style="gap:14px;">
                        <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:12px;">
                            <div class="flex-between" style="margin-bottom:6px;">
                                <strong style="font-size:12px; color:#7c3aed;">🎬 REELS PIPELINE</strong>
                                <span style="font-size:12px; font-weight:700; color:var(--text);"><?= $cd['reels_completed'] ?> / <?= $reelsReq > 0 ? $reelsReq : $reelsCount ?> Completed</span>
                            </div>
                            <div style="height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                                <div style="width:<?= $reelsPct ?>%; height:100%; background:linear-gradient(90deg, #7c3aed, #a855f7); border-radius:3px;"></div>
                            </div>
                        </div>
                        <div style="background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:12px;">
                            <div class="flex-between" style="margin-bottom:6px;">
                                <strong style="font-size:12px; color:#0284c7;">🖼️ POSTS &amp; STATICS PIPELINE</strong>
                                <span style="font-size:12px; font-weight:700; color:var(--text);"><?= $cd['posts_completed'] ?> / <?= $postsReq > 0 ? $postsReq : $postsCount ?> Completed</span>
                            </div>
                            <div style="height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                                <div style="width:<?= $postsPct ?>%; height:100%; background:linear-gradient(90deg, #0284c7, #38bdf8); border-radius:3px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Deliverable Items Table for this Client -->
                    <?php 
                        $allItems = array_merge($cd['reels'], $cd['posts']);
                        usort($allItems, fn($a, $b) => strcmp($a['post_date'], $b['post_date']));
                    ?>
                    <?php if (empty($allItems)): ?>
                        <div class="text-muted small" style="padding:10px 0;">No individual items created yet. Click "+ Add Reel" or "+ Add Post" above to schedule!</div>
                    <?php else: ?>
                        <div class="table-wrap responsive-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width:90px;">Type</th>
                                        <th>Content Title</th>
                                        <th>Date</th>
                                        <th>Assignee</th>
                                        <th>Double-Check Review</th>
                                        <th>Status</th>
                                        <th style="text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allItems as $item): 
                                        $isReel = ($item['content_type'] ?? 'reel') === 'reel';
                                        $isDone = in_array($item['status'], ['published', 'completed'], true);
                                    ?>
                                    <tr id="row-item-<?= $item['id'] ?>" style="<?= $isDone ? 'opacity:0.75; background:rgba(16, 185, 129, 0.04);' : '' ?>">
                                        <td data-label="Type">
                                            <?php if ($isReel): ?>
                                                <span class="badge" style="background:#7c3aed; color:#fff; font-size:10px; font-weight:700;">🎬 REEL</span>
                                            <?php else: ?>
                                                <span class="badge" style="background:#0284c7; color:#fff; font-size:10px; font-weight:700;">🖼️ POST</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Title">
                                            <strong><?= e($item['title']) ?></strong>
                                            <?php if (!empty($item['drive_link'])): ?>
                                                <a href="<?= e($item['drive_link']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:11px; margin-left:6px; color:var(--primary); text-decoration:none;">🔗 Drive Link</a>
                                            <?php endif; ?>
                                            <?php if (!empty($item['rectification_notes'])): ?>
                                                <div style="font-size:11px; color:#e11d48; margin-top:2px;">⚠️ Rectify: <?= e($item['rectification_notes']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Date"><?= format_date($item['post_date']) ?></td>
                                        <td data-label="Assignee"><?= e($item['assignee_name'] ?? '—') ?></td>
                                        <td data-label="Double-Check">
                                            <?php if ($item['status'] === 'needs_rectification'): ?>
                                                <span class="badge badge-danger" style="font-size:10px;">⚠️ Needs Rectification</span>
                                            <?php elseif ($item['status'] === 'manager_review'): ?>
                                                <span class="badge badge-info" style="font-size:10px;">Editor Checked ✓ &bull; In Manager Review</span>
                                            <?php elseif (!empty($item['manager_reviewed_by']) || in_array($item['status'], ['scheduled', 'published', 'completed'], true)): ?>
                                                <span class="badge badge-success" style="font-size:10px;">✓ Double-Checked</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning" style="font-size:10px;">Pending Editor Check</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Status">
                                            <span class="badge badge-<?= status_badge_class($item['status']) ?>" id="status-badge-<?= $item['id'] ?>"><?= e(humanize($item['status'])) ?></span>
                                        </td>
                                        <td data-label="Actions" style="text-align:right;">
                                            <div class="btn-group" style="justify-content:flex-end;">
                                                <button class="btn btn-sm <?= $isDone ? 'btn-secondary' : 'btn-success' ?>" style="font-size:11px; padding:3px 8px;" onclick="toggleDeliverableStatus(<?= $item['id'] ?>)" id="btn-done-<?= $item['id'] ?>">
                                                    <?= $isDone ? '↺ Mark Unfinished' : '✓ Mark as Done' ?>
                                                </button>
                                                <button class="btn btn-sm btn-secondary" style="font-size:11px; padding:3px 8px;" onclick="editContent(<?= htmlspecialchars(json_encode($item)) ?>)">Edit</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ============================================================= -->
    <!-- PIPELINE / KANBAN VIEW                                         -->
    <!-- ============================================================= -->
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:24px;">
        <?php
            $statuses = [
                'draft' => ['label' => '1. Drafts / Scripting', 'badge' => 'secondary', 'color' => '#64748b'],
                'pending_editor_check' => ['label' => '2. Editor Check Pending', 'badge' => 'warning', 'color' => '#f59e0b'],
                'needs_rectification' => ['label' => '⚠️ Needs Rectification', 'badge' => 'danger', 'color' => '#ef4444'],
                'manager_review' => ['label' => '3. Manager Review (Manav)', 'badge' => 'info', 'color' => '#3b82f6'],
                'scheduled' => ['label' => '4. Scheduled', 'badge' => 'primary', 'color' => '#6366f1'],
                'published' => ['label' => '5. Published &amp; Done', 'badge' => 'success', 'color' => '#10b981']
            ];
            foreach ($statuses as $statKey => $statInfo):
        ?>
        <div class="card" style="padding:12px; background:var(--bg); box-shadow:none; border:1px solid var(--border); min-width:210px;">
            <h3 style="font-size:12px; font-weight:700; margin-top:0; margin-bottom:12px; padding-bottom:8px; border-bottom:2px solid <?= $statInfo['color'] ?>; display:flex; justify-content:space-between; align-items:center;">
                <span><?= $statInfo['label'] ?></span>
            </h3>
            
            <?php 
                $count = 0;
                foreach ($posts as $p): 
                    $pStat = ($p['status'] === 'pending_approval') ? 'pending_editor_check' : (($p['status'] === 'completed') ? 'published' : $p['status']);
                    if ($pStat !== $statKey) continue;
                    $count++;
                    $isReel = ($p['content_type'] ?? 'reel') === 'reel';
                    $isDone = in_array($pStat, ['published', 'completed'], true);
            ?>
            <div class="card" id="card-item-<?= $p['id'] ?>" style="padding:12px; margin-bottom:10px; box-shadow:0 1px 4px rgba(0,0,0,0.06); border-left:4px solid <?= $statInfo['color'] ?>; background:var(--card-bg);">
                <div class="flex-between" style="margin-bottom:6px">
                    <?php if ($isReel): ?>
                        <span class="badge" style="background:#7c3aed; color:#fff; font-size:9px; font-weight:800; letter-spacing:0.5px;">🎬 REEL</span>
                    <?php else: ?>
                        <span class="badge" style="background:#0284c7; color:#fff; font-size:9px; font-weight:800; letter-spacing:0.5px;">🖼️ POST</span>
                    <?php endif; ?>
                    <span class="badge badge-secondary" style="font-size:10px"><?= format_date($p['post_date']) ?></span>
                </div>

                <strong style="font-size:13px; display:block; margin-bottom:3px; line-height:1.3; color:var(--text);"><?= e($p['title']) ?></strong>
                
                <div style="font-size:11px; color:var(--text-muted); margin-bottom:6px;">
                    <a href="<?= url('clients', ['action' => 'view', 'id' => $p['client_id']]) ?>" style="color:inherit; text-decoration:none; font-weight:600;">
                        👤 <?= e($p['client_name']) ?>
                    </a>
                </div>

                <?php if (!empty($p['drive_link'])): ?>
                    <div style="margin-bottom:6px;">
                        <a href="<?= e($p['drive_link']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:10px; background:#eff6ff; color:#1d4ed8; padding:2px 6px; border-radius:4px; text-decoration:none; display:inline-block;">
                            🔗 Drive Link
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($p['assignee_name'])): ?>
                    <div style="font-size:10px; color:var(--muted); margin-bottom:6px;">Assignee: <?= e($p['assignee_name']) ?></div>
                <?php endif; ?>

                <?php if (!empty($p['editor_name'])): ?>
                    <div style="font-size:10px; color:var(--muted); margin-bottom:4px;">Editor: <span style="font-weight:600; color:var(--info);"><?= e($p['editor_name']) ?></span></div>
                <?php endif; ?>

                <?php if (!empty($p['rectification_notes'])): ?>
                    <div style="background:#fff1f2; border:1px solid #fecdd3; border-radius:4px; padding:6px; font-size:11px; color:#9f1239; margin-bottom:8px;">
                        <strong>⚠️ Rectify:</strong> <?= e($p['rectification_notes']) ?>
                    </div>
                <?php endif; ?>

                <div style="display:flex; flex-direction:column; gap:4px; margin-top:8px;">
                    <!-- Stage 1 Action for Editor -->
                    <?php if ($pStat === 'pending_editor_check' || $pStat === 'draft'): ?>
                        <button class="btn btn-sm btn-primary" style="font-size:11px; padding:4px;" onclick="openEditorCheckModal(<?= $p['id'] ?>, '<?= e(addslashes($p['title'])) ?>')">
                            ✓ Editor Check / Flag
                        </button>
                    <?php endif; ?>

                    <!-- Rectification Action -->
                    <?php if ($pStat === 'needs_rectification'): ?>
                        <form method="post" action="<?= url('content_calendar', ['action' => 'editor_check']) ?>" style="margin:0;">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="decision" value="passed">
                            <button type="submit" class="btn btn-sm btn-primary" style="width:100%; font-size:11px; padding:4px;">
                                ✓ Rectified &rarr; Pass to Manager
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Stage 2 Action for Manager (Manav / Managers / Founder) -->
                    <?php if ($pStat === 'manager_review'): ?>
                        <button class="btn btn-sm btn-success" style="font-size:11px; padding:4px; background:var(--success); border-color:var(--success); color:#fff;" onclick="openManagerReviewModal(<?= $p['id'] ?>, '<?= e(addslashes($p['title'])) ?>')">
                            👑 Manager Review (Manav)
                        </button>
                    <?php endif; ?>

                    <!-- Realtime Mark as Done Button -->
                    <button class="btn btn-sm <?= $isDone ? 'btn-secondary' : 'btn-success' ?>" style="width:100%; font-size:11px; padding:4px; font-weight:600;" onclick="toggleDeliverableStatus(<?= $p['id'] ?>)" id="btn-pipe-done-<?= $p['id'] ?>">
                        <?= $isDone ? '↺ Mark Unfinished' : '✓ Mark Published &amp; Done' ?>
                    </button>

                    <button class="btn btn-sm btn-secondary" style="width:100%; font-size:11px; padding:4px" onclick="editContent(<?= htmlspecialchars(json_encode($p)) ?>)">Edit Details</button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if ($count === 0): ?><div style="font-size:12px; color:var(--text-muted); text-align:center; padding:16px 0">No items</div><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ============================================================= -->
<!-- MODALS                                                        -->
<!-- ============================================================= -->

<!-- Editor Check Modal -->
<div class="modal-overlay" id="editorCheckModal">
    <div class="modal" style="max-width:480px">
        <span class="modal-close" onclick="closeEditorCheckModal()">&times;</span>
        <div class="modal-title">Stage 1: Editor Quality Check</div>
        <p class="text-muted small" id="ecItemTitle"></p>
        <form method="post" action="<?= url('content_calendar', ['action' => 'editor_check']) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" id="ecItemId">
            
            <div class="form-group">
                <label>Editor Verification Decision</label>
                <select name="decision" id="ecDecision" class="form-control" onchange="toggleEcNotes(this.value)">
                    <option value="passed">✓ Verified &amp; Checked (Advance to Manager Review)</option>
                    <option value="flag_issues">⚠️ Issues Found (Require Rectification)</option>
                </select>
            </div>

            <div class="form-group" id="ecNotesGroup" style="display:none;">
                <label>What issues need to be rectified? *</label>
                <textarea name="rectification_notes" id="ecNotes" rows="3" placeholder="Specify video cut flaws, captions, typos, or audio adjustments..." class="form-control"></textarea>
            </div>

            <div class="flex-between" style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Submit Editor Decision</button>
                <button type="button" class="btn" onclick="closeEditorCheckModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Manager Review Modal -->
<div class="modal-overlay" id="managerReviewModal">
    <div class="modal" style="max-width:480px">
        <span class="modal-close" onclick="closeManagerReviewModal()">&times;</span>
        <div class="modal-title">Stage 2: Manager Review (Manav / Lead)</div>
        <p class="text-muted small" id="mrItemTitle"></p>
        <form method="post" action="<?= url('content_calendar', ['action' => 'manager_review']) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" id="mrItemId">
            
            <div class="form-group">
                <label>Manager Decision</label>
                <select name="decision" id="mrDecision" class="form-control" onchange="toggleMrNotes(this.value)">
                    <option value="approve">✓ Final Approve &amp; Schedule</option>
                    <option value="flag_issues">↩ Return for Rectification (Issues Found)</option>
                </select>
            </div>

            <div class="form-group" id="mrNotesGroup" style="display:none;">
                <label>Manager Rectification Feedback *</label>
                <textarea name="rectification_notes" id="mrNotes" rows="3" placeholder="Notes on what must be corrected before final approval..." class="form-control"></textarea>
            </div>

            <div class="flex-between" style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Submit Manager Approval</button>
                <button type="button" class="btn" onclick="closeManagerReviewModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Content Modal -->
<div class="modal-overlay" id="addContentModal">
    <div class="modal" style="max-width:540px">
        <span class="modal-close" onclick="closeAddContentModal()">&times;</span>
        <div class="modal-title" id="addModalTitle">Schedule New Deliverable</div>
        <form method="post" action="<?= url('content_calendar', ['action' => 'store']) ?>">
            <?= Csrf::field() ?>
            
            <div class="form-row">
                <div class="form-group"><label>Deliverable Format *</label>
                    <select name="content_type" id="addContentType" required class="form-control">
                        <option value="reel">🎬 Reel (Short-Form Video)</option>
                        <option value="post">🖼️ Post / Static Graphic</option>
                        <option value="carousel">📑 Carousel</option>
                        <option value="story">📱 Story</option>
                    </select>
                </div>
                <div class="form-group"><label>Scheduled Post Date *</label>
                    <input type="date" name="post_date" id="addPostDate" value="<?= date('Y-m-d') ?>" required class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label>Title / Deliverable Concept *</label>
                <input type="text" name="title" id="addTitle" placeholder="e.g. Founder Story Reel #03, or Product Launch Static Post" required class="form-control">
            </div>

            <div class="form-row">
                <div class="form-group"><label>Client *</label>
                    <select name="client_id" id="addClientId" required class="form-control">
                        <option value="">— Select Client —</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id'] == $viewClientId ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Assignee (Editor / Creator)</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($managers as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Google Drive / Asset Link (Optional)</label>
                <input type="url" name="drive_link" placeholder="https://drive.google.com/..." class="form-control">
            </div>

            <div class="form-row">
                <div class="form-group"><label>Pipeline Stage</label>
                    <select name="status" class="form-control">
                        <option value="draft">1. Draft / Scripting</option>
                        <option value="pending_editor_check">2. Pending Editor Check</option>
                        <option value="manager_review">3. Pending Manager Review (Manav)</option>
                        <option value="scheduled">4. Scheduled</option>
                        <option value="published">5. Published &amp; Completed</option>
                    </select>
                </div>
                <div class="form-group"><label>Service (Optional)</label>
                    <select name="service_id" id="serviceSelect" onchange="updateSubcategories('serviceSelect', 'subcategorySelect')" class="form-control">
                        <option value="">— Auto Detect Service —</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Caption / Concept Notes</label>
                <textarea name="content" rows="3" placeholder="Script summary, copy, hashtags, or editing instructions..." class="form-control"></textarea>
            </div>

            <div class="flex-between" style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Schedule Deliverable</button>
                <button type="button" class="btn" onclick="closeAddContentModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Content Modal -->
<div class="modal-overlay" id="editContentModal">
    <div class="modal" style="max-width:540px">
        <span class="modal-close" onclick="closeEditContentModal()">&times;</span>
        <div class="modal-title">Edit Deliverable Details</div>
        <form method="post" action="<?= url('content_calendar', ['action' => 'update']) ?>" id="editContentForm">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" id="editContentId">
            <input type="hidden" name="client_id" id="editClientId">

            <div class="form-row">
                <div class="form-group"><label>Format</label>
                    <select name="content_type" id="editContentType" class="form-control">
                        <option value="reel">🎬 Reel</option>
                        <option value="post">🖼️ Post / Static</option>
                        <option value="carousel">📑 Carousel</option>
                        <option value="story">📱 Story</option>
                    </select>
                </div>
                <div class="form-group"><label>Post Date</label>
                    <input type="date" name="post_date" id="editDate" required class="form-control">
                </div>
            </div>

            <div class="form-group"><label>Title</label>
                <input type="text" name="title" id="editTitle" required class="form-control">
            </div>

            <div class="form-row">
                <div class="form-group"><label>Status</label>
                    <select name="status" id="editStatus" class="form-control">
                        <option value="draft">1. Draft / Scripting</option>
                        <option value="pending_editor_check">2. Pending Editor Check</option>
                        <option value="needs_rectification">⚠️ Needs Rectification</option>
                        <option value="manager_review">3. Pending Manager Review (Manav)</option>
                        <option value="scheduled">4. Scheduled</option>
                        <option value="published">5. Published &amp; Completed</option>
                    </select>
                </div>
                <div class="form-group"><label>Assignee</label>
                    <select name="assigned_to" id="editAssignee" class="form-control">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($managers as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Drive / Asset Link</label>
                <input type="url" name="drive_link" id="editDriveLink" class="form-control">
            </div>

            <div class="form-group"><label>Content / Caption</label>
                <textarea name="content" id="editContentBody" rows="3" class="form-control"></textarea>
            </div>

            <div class="flex-between" style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="submit" formaction="<?= url('content_calendar', ['action' => 'delete']) ?>" class="btn btn-danger" onclick="return confirm('Delete this deliverable from calendar?')">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
const servicesData = <?= json_encode($services) ?>;

function openAddContentModal(format = 'reel', clientId = null) {
    document.getElementById('addContentType').value = format;
    document.getElementById('addModalTitle').innerText = (format === 'reel') ? '🎬 Schedule New Reel' : '🖼️ Schedule New Post / Static';
    if (clientId) {
        document.getElementById('addClientId').value = clientId;
    }
    document.getElementById('addContentModal').classList.add('show');
}

function closeAddContentModal() {
    document.getElementById('addContentModal').classList.remove('show');
}

function updateSubcategories(serviceSelectId, subcategorySelectId) {
    const sid = document.getElementById(serviceSelectId).value;
    const subSelect = document.getElementById(subcategorySelectId);
    if (!subSelect) return;
    subSelect.innerHTML = '<option value="">— Auto —</option>';
    
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
    document.getElementById('editClientId').value = post.client_id || '';
    document.getElementById('editTitle').value = post.title;
    document.getElementById('editDate').value = post.post_date;
    document.getElementById('editStatus').value = post.status === 'pending_approval' ? 'pending_editor_check' : (post.status === 'completed' ? 'published' : post.status);
    document.getElementById('editContentType').value = post.content_type || 'reel';
    document.getElementById('editDriveLink').value = post.drive_link || '';
    document.getElementById('editAssignee').value = post.assigned_to || '';
    document.getElementById('editContentBody').value = post.content || '';
    document.getElementById('editContentModal').classList.add('show');
}

function closeEditContentModal() {
    document.getElementById('editContentModal').classList.remove('show');
}

function openEditorCheckModal(id, title) {
    document.getElementById('ecItemId').value = id;
    document.getElementById('ecItemTitle').innerText = 'Content: ' + title;
    document.getElementById('ecDecision').value = 'passed';
    document.getElementById('ecNotesGroup').style.display = 'none';
    document.getElementById('editorCheckModal').classList.add('show');
}
function closeEditorCheckModal() {
    document.getElementById('editorCheckModal').classList.remove('show');
}
function toggleEcNotes(val) {
    document.getElementById('ecNotesGroup').style.display = (val === 'flag_issues') ? 'block' : 'none';
}

function openManagerReviewModal(id, title) {
    document.getElementById('mrItemId').value = id;
    document.getElementById('mrItemTitle').innerText = 'Content: ' + title;
    document.getElementById('mrDecision').value = 'approve';
    document.getElementById('mrNotesGroup').style.display = 'none';
    document.getElementById('managerReviewModal').classList.add('show');
}
function closeManagerReviewModal() {
    document.getElementById('managerReviewModal').classList.remove('show');
}
function toggleMrNotes(val) {
    document.getElementById('mrNotesGroup').style.display = (val === 'flag_issues') ? 'block' : 'none';
}

// Realtime deliverable completion toggle
function toggleDeliverableStatus(id) {
    const formData = new FormData();
    formData.append('id', id);
    formData.append('csrf_token', '<?= Csrf::token() ?>');

    fetch('<?= url("content_calendar", ["action" => "toggle_done"]) ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const isCompleted = data.is_completed;
            
            // Update table row if present
            const row = document.getElementById('row-item-' + id);
            if (row) {
                row.style.opacity = isCompleted ? '0.75' : '1';
                row.style.background = isCompleted ? 'rgba(16, 185, 129, 0.04)' : '';
            }

            const badge = document.getElementById('status-badge-' + id);
            if (badge) {
                badge.innerText = isCompleted ? 'Published' : 'Scheduled';
                badge.className = isCompleted ? 'badge badge-success' : 'badge badge-primary';
            }

            const btn = document.getElementById('btn-done-' + id);
            if (btn) {
                btn.innerText = isCompleted ? '↺ Mark Unfinished' : '✓ Mark as Done';
                btn.className = isCompleted ? 'btn btn-sm btn-secondary' : 'btn btn-sm btn-success';
            }

            const btnPipe = document.getElementById('btn-pipe-done-' + id);
            if (btnPipe) {
                btnPipe.innerText = isCompleted ? '↺ Mark Unfinished' : '✓ Mark Published & Done';
                btnPipe.className = isCompleted ? 'btn btn-sm btn-secondary' : 'btn btn-sm btn-success';
            }

            // If in pipeline mode, reload to place in right column cleanly
            if (document.getElementById('card-item-' + id)) {
                window.location.reload();
            }
        } else {
            alert(data.error || 'Failed to update status.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error while updating deliverable status.');
    });
}
</script>
