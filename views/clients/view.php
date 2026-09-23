<div class="flex-between">
    <h1><?= e($client['name']) ?> <span class="text-muted small"><?= e($client['client_code']) ?></span></h1>
    <?php if ($fullAccess): ?>
    <div class="btn-group">
        <?php if (Permission::has('clients.edit')): ?><a href="<?= url('clients', ['action' => 'edit', 'id' => $client['id']]) ?>" class="btn">Edit</a><?php endif; ?>
        <?php if (Permission::has('clients.delete')): ?>
        <form method="post" action="<?= url('clients', ['action' => 'delete']) ?>" style="display:inline" data-confirm="Delete this client?">
            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $client['id'] ?>"><button class="btn btn-danger">Delete</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="card" style="margin-bottom: 16px;">
    <div class="flex-between">
        <strong>Quick Actions</strong>
        <div class="btn-group">
            <a href="<?= url('tasks', ['action' => 'create', 'client_id' => $client['id']]) ?>" class="btn btn-sm btn-primary">+ Add Task</a>
            <a href="<?= url('content_calendar', ['client_id' => $client['id']]) ?>" class="btn btn-sm btn-primary">+ View/Add Content</a>
            <a href="<?= url('proposals', ['action' => 'create', 'client_id' => $client['id']]) ?>" class="btn btn-sm btn-primary">+ Add Proposal</a>
        </div>
    </div>
</div>

<?php if ($fullAccess): ?>
<div class="card">
    <div class="card-title">Client Details</div>
    <div class="grid grid-3">
        <div><span class="text-muted small">Contact Person</span><br><?= e($client['contact_person'] ?: '—') ?></div>
        <div><span class="text-muted small">Phone</span><br><?= e($client['phone'] ?: '—') ?></div>
        <div><span class="text-muted small">Email</span><br><?= e($client['email'] ?: '—') ?></div>
        <div><span class="text-muted small">Website</span><br><?= $client['website'] ? '<a href="'.e($client['website']).'" target="_blank" rel="noopener">'.e($client['website']).'</a>' : '—' ?></div>
        <div><span class="text-muted small">Start Date</span><br><?= format_date($client['start_date']) ?: '—' ?></div>
        <div><span class="text-muted small">Renewal Date</span><br><?= format_date($client['renewal_date']) ?: '—' ?></div>
        <div><span class="text-muted small">Status</span><br><span class="badge badge-<?= status_badge_class($client['status']) ?>"><?= e(humanize($client['status'])) ?></span></div>
        <div><span class="text-muted small">Retention Date</span><br><?= format_date($client['retention_date']) ?: '—' ?></div>
        <div><span class="text-muted small">Google Drive</span><br><?= $client['drive_link'] ? '<a href="'.e($client['drive_link']).'" target="_blank" rel="noopener">Open folder</a>' : '—' ?></div>
    </div>
    <?php if ($client['notes']): ?><hr><div class="text-muted small">Notes</div><p><?= nl2br(e($client['notes'])) ?></p><?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title">
        Services &amp; Work Requirements
        <?php if ($fullAccess && Permission::has('clients.manage_services')): ?>
        <button class="btn btn-sm" data-modal-open="addServiceModal">+ Add Service</button>
        <?php endif; ?>
    </div>

    <?php foreach ($services as $svc): ?>
        <?php
            $required = (int)($svc['quantity_required'] ?? 0);
            $completed = (int)($svc['quantity_completed'] ?? $svc['my_completed'] ?? 0);
            $pct = $required > 0 ? min(100, round($completed / $required * 100)) : 0;
        ?>
        <div class="card" style="background:var(--bg);border-style:dashed">
            <div class="flex-between">
                <div>
                    <strong><?= e($svc['service_name']) ?></strong> <span class="badge badge-secondary" style="font-size:10px;text-transform:uppercase"><?= e($svc['tenure']) ?></span>
                    <?php if (!empty($svc['subcategories'])): ?>
                        <div style="font-size:0.85rem;margin-top:6px;color:var(--text)">
                            <?php foreach ($svc['subcategories'] as $sub): ?>
                                <?php
                                    $sReq = (int)$sub['quantity_required'];
                                    $sCom = (int)$sub['quantity_completed'];
                                    $sPct = $sReq > 0 ? min(100, round($sCom / $sReq * 100)) : 0;
                                ?>
                                <div style="margin-bottom:6px">
                                    <div class="flex-between" style="font-size:11px;font-weight:600">
                                        <span><?= e($sub['subcategory_name']) ?></span>
                                        <span><?= $sCom ?> / <?= $sReq ?></span>
                                    </div>
                                    <div class="progress" style="margin:2px 0;height:4px"><div class="progress-bar" style="width:<?= $sPct ?>%"></div></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($svc['scope_details'])): ?>
                        <div style="font-size:0.85rem;margin-top:4px;color:var(--text-muted)">
                            <?php $scopes = json_decode($svc['scope_details'], true); ?>
                            <?php if (is_array($scopes)): ?>
                                <?php foreach ($scopes as $sk => $sv): ?>
                                    <span style="background:var(--bg-hover);padding:2px 6px;border-radius:4px;margin-right:4px;"><strong><?= e($sk) ?>:</strong> <?= e($sv) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <span class="small text-muted"><?= $completed ?> / <?= $required ?> <?= e($svc['unit_label']) ?></span>
            </div>
            <div class="progress" style="margin:8px 0"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>

            <?php if ($fullAccess): ?>
                <div class="small text-muted" style="margin-bottom:8px">Manager: <?= e(Database::scalar('SELECT name FROM users WHERE id=?', [$svc['manager_id']]) ?: '—') ?></div>
                
                <?php if (Permission::hasAny(['clients.manage_services','clients.assign'])): ?>
                <button class="btn btn-sm" onclick="openAddRequirement(<?= $svc['id'] ?>, '<?= e(addslashes($svc['service_name'])) ?>')">+ Add Requirement</button>
                <?php endif; ?>

                <?php if (!empty($svc['assignments'])): ?>
                <table style="margin-top:8px">
                    <thead><tr><th>Requirement</th><th>Assigned To</th><th>Qty</th><th>Deadline</th><th>Completed</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($svc['assignments'] as $a): ?>
                        <tr>
                            <td><strong><?= e($a['requirement_name']) ?></strong><br><span class="small text-muted" style="font-weight:normal"><?= e($a['notes']) ?></span></td>
                            <td><?= e($a['user_name']) ?></td>
                            <td><?= $a['quantity_assigned'] !== null ? (int)$a['quantity_assigned'] : '—' ?></td>
                            <td><?= format_date($a['deadline']) ?: '—' ?></td>
                            <td>
                                <?php if (Permission::hasAny(['clients.manage_services', 'clients.edit', 'clients.assign']) || (int)$svc['manager_id'] === Auth::id() || (int)$a['user_id'] === Auth::id()): ?>
                                <form method="post" action="<?= url('clients', ['action' => 'update_progress']) ?>" style="display:inline-flex;align-items:center;gap:4px;margin:0">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                                    <input type="number" name="quantity_completed" value="<?= (int)$a['quantity_completed'] ?>" min="0" style="width:65px;padding:3px 6px;font-size:12px;height:28px">
                                    <button class="btn btn-sm btn-primary" style="padding:2px 8px;font-size:11px;height:28px" title="Save progress">Save</button>
                                </form>
                                <?php else: ?>
                                <?= (int)$a['quantity_completed'] ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (Permission::hasAny(['clients.manage_services','clients.assign'])): ?>
                                <form method="post" action="<?= url('clients', ['action' => 'remove_assignment']) ?>" style="display:inline" data-confirm="Remove this assignment?">
                                    <?= Csrf::field() ?><input type="hidden" name="assignment_id" value="<?= $a['id'] ?>"><input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                                    <button class="btn btn-sm btn-link text-danger">Remove</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if (Permission::has('clients.manage_services')): ?>
                <details style="margin-top:8px"><summary class="small">Edit requirement</summary>
                <form method="post" action="<?= url('clients', ['action' => 'update_service']) ?>">
                    <?= Csrf::field() ?><input type="hidden" name="client_service_id" value="<?= $svc['id'] ?>"><input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                    <div class="form-row mt-2">
                        <div class="form-group" style="max-width:120px"><label>Required Qty</label><input type="number" name="quantity_required" value="<?= (int)$svc['quantity_required'] ?>" min="0"></div>
                        <div class="form-group"><label>Tenure</label>
                            <select name="tenure">
                                <option value="monthly" <?= $svc['tenure']==='monthly'?'selected':'' ?>>Monthly</option>
                                <option value="weekly" <?= $svc['tenure']==='weekly'?'selected':'' ?>>Weekly</option>
                                <option value="one_time" <?= $svc['tenure']==='one_time'?'selected':'' ?>>One-time</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Manager</label>
                            <select name="manager_id"><option value="">—</option>
                                <?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>" <?= $m['id']==$svc['manager_id']?'selected':'' ?>><?= e($m['name']) ?><?= $m['id'] === Auth::id() ? ' (YOU)' : '' ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Status</label>
                            <select name="status">
                                <option value="active" <?= $svc['status']==='active'?'selected':'' ?>>Active</option>
                                <option value="completed" <?= $svc['status']==='completed'?'selected':'' ?>>Completed</option>
                                <option value="paused" <?= $svc['status']==='paused'?'selected':'' ?>>Paused</option>
                            </select>
                        </div>
                    </div>
                    <?php if (!empty($svc['subcategories'])): ?>
                        <div class="form-group" style="background:var(--bg);padding:8px;border-radius:4px">
                            <label>Subcategory Quantities</label>
                            <div class="grid grid-3" style="gap:8px">
                                <?php foreach ($svc['subcategories'] as $sub): ?>
                                    <div>
                                        <label style="font-size:11px;font-weight:600;margin-bottom:2px"><?= e($sub['subcategory_name']) ?></label>
                                        <input type="number" name="subcategories[<?= $sub['subcategory_id'] ?>]" value="<?= (int)$sub['quantity_required'] ?>" min="0" class="form-control" style="padding:2px 4px;font-size:12px">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-primary">Save</button>
                        <button type="submit" formaction="<?= url('clients', ['action' => 'remove_service']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to completely remove this service and all its requirements from this client?')">Remove Service</button>
                    </div>
                </form>
                </details>
                <?php endif; ?>
            <?php else: ?>
                <div class="small">Your assignment: <?= $svc['quantity_assigned'] !== null ? (int)$svc['quantity_assigned'] : 'Not fixed' ?> <?= e($svc['unit_label']) ?></div>
                <form method="post" action="<?= url('clients', ['action' => 'update_progress']) ?>" class="form-row mt-2" style="align-items:flex-end">
                    <?= Csrf::field() ?><input type="hidden" name="assignment_id" value="<?= $svc['assignment_id'] ?>"><input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                    <div class="form-group" style="max-width:140px"><label>Update completed</label><input type="number" name="quantity_completed" value="<?= (int)$svc['my_completed'] ?>" min="0"></div>
                    <button class="btn btn-sm btn-primary">Update</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if (empty($services)): ?><p class="text-muted small">No services configured yet.</p><?php endif; ?>
</div>

<div class="card">
    <div class="card-title">Related Tasks <a href="<?= url('tasks', ['action' => 'create', 'client_id' => $client['id']]) ?>" class="btn btn-sm" style="float:right;">+ Add</a></div>
    <?php if (empty($tasks)): ?><p class="text-muted small">No tasks yet for this client.</p><?php else: ?>
    <div class="table-wrap responsive-table"><table>
        <thead><tr><th>Task</th><th>Assigned</th><th>Status</th><th>Deadline</th></tr></thead>
        <tbody>
        <?php foreach ($tasks as $t): $overdue = is_overdue($t['deadline'], $t['status']); ?>
            <tr>
                <td data-label="Task"><a href="<?= url('tasks', ['action' => 'view', 'id' => $t['id']]) ?>"><?= e($t['title']) ?></a></td>
                <td data-label="Assigned"><?= e($t['assigned_name'] ?? '—') ?></td>
                <td data-label="Status"><span class="badge badge-<?= status_badge_class($overdue ? 'overdue' : $t['status']) ?>"><?= $overdue ? 'Overdue' : e(humanize($t['status'])) ?></span></td>
                <td data-label="Deadline"><?= $t['deadline'] ? format_datetime($t['deadline']) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<!-- ============================================================= -->
<!-- REELS DELIVERABLES PIPELINE (LINKED TO CONTENT CALENDAR)       -->
<!-- ============================================================= -->
<?php
    $reels = $clientDeliverables['reels'] ?? [];
    $posts = $clientDeliverables['posts'] ?? [];
    $reelsReq = $clientDeliverables['reels_required'] ?? 0;
    $reelsComp = $clientDeliverables['reels_completed'] ?? 0;
    $postsReq = $clientDeliverables['posts_required'] ?? 0;
    $postsComp = $clientDeliverables['posts_completed'] ?? 0;
    
    $reelsPct = $reelsReq > 0 ? min(100, round(($reelsComp / $reelsReq) * 100)) : (count($reels) > 0 ? round(($reelsComp / count($reels)) * 100) : 0);
    $postsPct = $postsReq > 0 ? min(100, round(($postsComp / $postsReq) * 100)) : (count($posts) > 0 ? round(($postsComp / count($posts)) * 100) : 0);
?>

<div class="card mb-4" style="border-top:4px solid #7c3aed; box-shadow:0 2px 10px rgba(124, 58, 237, 0.08);">
    <div class="flex-between mb-3" style="flex-wrap:wrap; gap:10px;">
        <div>
            <div style="font-size:16px; font-weight:800; color:#7c3aed; display:flex; align-items:center; gap:8px;">
                <span>🎬 Reels Deliverables</span>
                <span class="badge" style="background:#ede9fe; color:#6d28d9; font-weight:700; font-size:11px;" id="client-reels-badge">
                    <?= $reelsComp ?> / <?= $reelsReq > 0 ? $reelsReq : count($reels) ?> Completed (<?= $reelsPct ?>%)
                </span>
            </div>
            <div class="text-muted small" style="margin-top:2px;">Track scripting, video editing, double-check review, and scheduled posting.</div>
        </div>
        <div class="btn-group">
            <button class="btn btn-sm btn-primary" onclick="openClientAddDeliverable('reel')" style="background:#7c3aed; border-color:#7c3aed; font-size:12px;">+ Add Reel</button>
            <a href="<?= url('content_calendar', ['client_id' => $client['id'], 'type' => 'reel']) ?>" class="btn btn-sm btn-secondary" style="font-size:12px;">View in Calendar &rarr;</a>
        </div>
    </div>

    <!-- Progress bar -->
    <div style="height:8px; background:#f1f5f9; border-radius:4px; overflow:hidden; margin-bottom:16px;">
        <div id="client-reels-progress" style="width:<?= $reelsPct ?>%; height:100%; background:linear-gradient(90deg, #7c3aed, #a855f7); border-radius:4px; transition:width 0.3s ease;"></div>
    </div>

    <?php if (empty($reels)): ?>
        <div style="background:var(--bg); border:1px dashed var(--border); border-radius:8px; padding:24px; text-align:center;">
            <div style="font-size:24px; margin-bottom:6px;">🎬</div>
            <strong style="display:block; font-size:14px; margin-bottom:4px;">No Reels Added Yet</strong>
            <p class="text-muted small" style="margin-bottom:12px;">Schedule short-form video deliverables for this client. They link into the Content Calendar automatically.</p>
            <button class="btn btn-sm btn-primary" onclick="openClientAddDeliverable('reel')" style="background:#7c3aed; border-color:#7c3aed;">+ Add First Reel</button>
        </div>
    <?php else: ?>
        <div class="table-wrap responsive-table">
            <table>
                <thead>
                    <tr>
                        <th>Reel Title / Concept</th>
                        <th>Editor</th>
                        <th>Post Date</th>
                        <th>Quality Review</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reels as $r): 
                        $isDone = in_array($r['status'], ['published', 'completed'], true);
                    ?>
                    <tr id="row-deliv-<?= $r['id'] ?>" style="<?= $isDone ? 'opacity:0.8; background:rgba(16, 185, 129, 0.04);' : '' ?>">
                        <td data-label="Title">
                            <strong style="color:var(--text);"><?= e($r['title']) ?></strong>
                            <?php if (!empty($r['drive_link'])): ?>
                                <a href="<?= e($r['drive_link']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:11px; margin-left:6px; color:#7c3aed; text-decoration:none; font-weight:600;">🔗 Drive Link</a>
                            <?php endif; ?>
                            <?php if (!empty($r['content'])): ?>
                                <div class="text-muted small" style="margin-top:2px;"><?= e(mb_strimwidth($r['content'], 0, 70, '...')) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($r['rectification_notes'])): ?>
                                <div style="font-size:11px; color:#e11d48; margin-top:2px;">⚠️ Rectify: <?= e($r['rectification_notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Editor"><?= e($r['assignee_name'] ?? '—') ?></td>
                        <td data-label="Post Date"><?= format_date($r['post_date']) ?></td>
                        <td data-label="Quality Review">
                            <?php if ($r['status'] === 'needs_rectification'): ?>
                                <span class="badge badge-danger" style="font-size:10px;">⚠️ Needs Rectification</span>
                            <?php elseif ($r['status'] === 'manager_review'): ?>
                                <span class="badge badge-info" style="font-size:10px;">Editor Checked ✓ &bull; In Manager Review</span>
                            <?php elseif (!empty($r['manager_reviewed_by']) || in_array($r['status'], ['scheduled', 'published', 'completed'], true)): ?>
                                <span class="badge badge-success" style="font-size:10px;">✓ Double-Checked</span>
                            <?php else: ?>
                                <span class="badge badge-warning" style="font-size:10px;">Pending Editor Check</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <span class="badge badge-<?= status_badge_class($r['status']) ?>" id="deliv-badge-<?= $r['id'] ?>"><?= e(humanize($r['status'])) ?></span>
                        </td>
                        <td data-label="Actions" style="text-align:right;">
                            <div class="btn-group" style="justify-content:flex-end;">
                                <button class="btn btn-sm <?= $isDone ? 'btn-secondary' : 'btn-success' ?>" style="font-size:11px; padding:3px 8px; font-weight:600;" onclick="toggleClientDeliverableStatus(<?= $r['id'] ?>)" id="btn-deliv-<?= $r['id'] ?>">
                                    <?= $isDone ? '↺ Mark Unfinished' : '✓ Mark Done' ?>
                                </button>
                                <a href="<?= url('content_calendar', ['client_id' => $client['id']]) ?>" class="btn btn-sm btn-secondary" style="font-size:11px; padding:3px 8px;">Calendar</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================= -->
<!-- POSTS & STATICS DELIVERABLES PIPELINE                         -->
<!-- ============================================================= -->
<div class="card mb-4" style="border-top:4px solid #0284c7; box-shadow:0 2px 10px rgba(2, 132, 199, 0.08);">
    <div class="flex-between mb-3" style="flex-wrap:wrap; gap:10px;">
        <div>
            <div style="font-size:16px; font-weight:800; color:#0284c7; display:flex; align-items:center; gap:8px;">
                <span>🖼️ Posts &amp; Statics Deliverables</span>
                <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:700; font-size:11px;" id="client-posts-badge">
                    <?= $postsComp ?> / <?= $postsReq > 0 ? $postsReq : count($posts) ?> Completed (<?= $postsPct ?>%)
                </span>
            </div>
            <div class="text-muted small" style="margin-top:2px;">Track graphic design, carousels, copy approval, and scheduled posting.</div>
        </div>
        <div class="btn-group">
            <button class="btn btn-sm btn-primary" onclick="openClientAddDeliverable('post')" style="background:#0284c7; border-color:#0284c7; font-size:12px;">+ Add Post</button>
            <a href="<?= url('content_calendar', ['client_id' => $client['id'], 'type' => 'post']) ?>" class="btn btn-sm btn-secondary" style="font-size:12px;">View in Calendar &rarr;</a>
        </div>
    </div>

    <!-- Progress bar -->
    <div style="height:8px; background:#f1f5f9; border-radius:4px; overflow:hidden; margin-bottom:16px;">
        <div id="client-posts-progress" style="width:<?= $postsPct ?>%; height:100%; background:linear-gradient(90deg, #0284c7, #38bdf8); border-radius:4px; transition:width 0.3s ease;"></div>
    </div>

    <?php if (empty($posts)): ?>
        <div style="background:var(--bg); border:1px dashed var(--border); border-radius:8px; padding:24px; text-align:center;">
            <div style="font-size:24px; margin-bottom:6px;">🖼️</div>
            <strong style="display:block; font-size:14px; margin-bottom:4px;">No Posts Added Yet</strong>
            <p class="text-muted small" style="margin-bottom:12px;">Schedule static graphic, carousel, or image deliverables for this client.</p>
            <button class="btn btn-sm btn-primary" onclick="openClientAddDeliverable('post')" style="background:#0284c7; border-color:#0284c7;">+ Add First Post</button>
        </div>
    <?php else: ?>
        <div class="table-wrap responsive-table">
            <table>
                <thead>
                    <tr>
                        <th>Post Title / Creative</th>
                        <th>Designer</th>
                        <th>Post Date</th>
                        <th>Quality Review</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $p): 
                        $isDone = in_array($p['status'], ['published', 'completed'], true);
                    ?>
                    <tr id="row-deliv-<?= $p['id'] ?>" style="<?= $isDone ? 'opacity:0.8; background:rgba(16, 185, 129, 0.04);' : '' ?>">
                        <td data-label="Title">
                            <strong style="color:var(--text);"><?= e($p['title']) ?></strong>
                            <?php if (!empty($p['drive_link'])): ?>
                                <a href="<?= e($p['drive_link']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:11px; margin-left:6px; color:#0284c7; text-decoration:none; font-weight:600;">🔗 Drive Link</a>
                            <?php endif; ?>
                            <?php if (!empty($p['content'])): ?>
                                <div class="text-muted small" style="margin-top:2px;"><?= e(mb_strimwidth($p['content'], 0, 70, '...')) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($p['rectification_notes'])): ?>
                                <div style="font-size:11px; color:#e11d48; margin-top:2px;">⚠️ Rectify: <?= e($p['rectification_notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Designer"><?= e($p['assignee_name'] ?? '—') ?></td>
                        <td data-label="Post Date"><?= format_date($p['post_date']) ?></td>
                        <td data-label="Quality Review">
                            <?php if ($p['status'] === 'needs_rectification'): ?>
                                <span class="badge badge-danger" style="font-size:10px;">⚠️ Needs Rectification</span>
                            <?php elseif ($p['status'] === 'manager_review'): ?>
                                <span class="badge badge-info" style="font-size:10px;">Editor Checked ✓ &bull; In Manager Review</span>
                            <?php elseif (!empty($p['manager_reviewed_by']) || in_array($p['status'], ['scheduled', 'published', 'completed'], true)): ?>
                                <span class="badge badge-success" style="font-size:10px;">✓ Double-Checked</span>
                            <?php else: ?>
                                <span class="badge badge-warning" style="font-size:10px;">Pending Editor Check</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <span class="badge badge-<?= status_badge_class($p['status']) ?>" id="deliv-badge-<?= $p['id'] ?>"><?= e(humanize($p['status'])) ?></span>
                        </td>
                        <td data-label="Actions" style="text-align:right;">
                            <div class="btn-group" style="justify-content:flex-end;">
                                <button class="btn btn-sm <?= $isDone ? 'btn-secondary' : 'btn-success' ?>" style="font-size:11px; padding:3px 8px; font-weight:600;" onclick="toggleClientDeliverableStatus(<?= $p['id'] ?>)" id="btn-deliv-<?= $p['id'] ?>">
                                    <?= $isDone ? '↺ Mark Unfinished' : '✓ Mark Done' ?>
                                </button>
                                <a href="<?= url('content_calendar', ['client_id' => $client['id']]) ?>" class="btn btn-sm btn-secondary" style="font-size:11px; padding:3px 8px;">Calendar</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-title">Related Proposals <a href="<?= url('proposals', ['action' => 'create', 'client_id' => $client['id']]) ?>" class="btn btn-sm" style="float:right;">+ Add</a></div>
    <?php if (empty($proposals)): ?><p class="text-muted small">No proposals yet for this client.</p><?php else: ?>
    <div class="table-wrap responsive-table"><table>
        <thead><tr><th>Title</th><th>Assigned</th><th>Status</th><th>Deadline</th></tr></thead>
        <tbody>
        <?php foreach ($proposals as $p): ?>
            <tr>
                <td data-label="Title"><a href="<?= url('proposals', ['action' => 'view', 'id' => $p['id']]) ?>"><?= e($p['title']) ?></a></td>
                <td data-label="Assigned"><?= e($p['assigned_name'] ?? '—') ?></td>
                <td data-label="Status"><span class="badge badge-<?= status_badge_class($p['status']) ?>"><?= e(humanize($p['status'])) ?></span></td>
                <td data-label="Deadline"><?= format_datetime($p['deadline_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<?php if ($fullAccess && !empty($timeline)): ?>
<div class="card">
    <div class="card-title">Activity History</div>
    <ul class="timeline">
        <?php foreach ($timeline as $t): ?>
            <li><strong><?= e($t['user_name'] ?? 'System') ?></strong> &mdash; <?= e($t['note'] ?: humanize($t['action'])) ?>
                <div class="timeline-meta"><?= format_datetime($t['created_at']) ?></div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($fullAccess && Permission::has('clients.manage_services')): ?>
<div class="modal-overlay" id="addServiceModal">
    <div class="modal">
        <span class="modal-close" data-modal-close>&times;</span>
        <div class="modal-title">Add Service to <?= e($client['name']) ?></div>
        <form method="post" action="<?= url('clients', ['action' => 'add_service']) ?>">
            <?= Csrf::field() ?><input type="hidden" name="client_id" value="<?= $client['id'] ?>">
            <div class="form-row">
                <div class="form-group"><label>Service</label>
                    <select name="service_id" id="addServiceSelect" required onchange="renderAddServiceSubcategories()">
                        <option value="">— Select Service —</option>
                        <?php foreach ($allServices as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['unit_label']) ?>)</option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Global Quantity <small>(optional)</small></label><input type="number" name="quantity_required" min="0" value="0" required></div>
            </div>
            
            <!-- Dynamic Subcategories Section -->
            <div id="addServiceSubcategories" style="background:var(--bg);padding:12px;border-radius:4px;margin-bottom:16px;display:none;"></div>

            <div class="form-row">
                <div class="form-group"><label>Tenure (Billing Cycle)</label>
                    <select name="tenure">
                        <option value="monthly">Monthly</option>
                        <option value="weekly">Weekly</option>
                        <option value="one_time">One-time / Fixed</option>
                    </select>
                </div>
                <div class="form-group"><label>Manager</label>
                    <select name="manager_id"><option value="">—</option><?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?><?= $m['id'] === Auth::id() ? ' (YOU)' : '' ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Start Date</label><input type="date" name="start_date"></div>
                <div class="form-group"><label>End Date</label><input type="date" name="end_date"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Assign Immediately To</label>
                    <select name="assignee_id"><option value="">— Don't Assign Yet —</option><?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?><?= $m['id'] === Auth::id() ? ' (YOU)' : '' ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <hr>
            <div class="form-group">
                <label>Scope of Work Details (e.g. Reels, Stories, Pages)</label>
                <div id="scope-builder">
                    <div class="form-row" style="margin-bottom:8px">
                        <div class="form-group" style="flex:1;margin:0"><input type="text" name="scope_keys[]" placeholder="Metric (e.g. Reels)" class="form-control"></div>
                        <div class="form-group" style="flex:1;margin:0 8px"><input type="text" name="scope_values[]" placeholder="Value (e.g. 15)" class="form-control"></div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm mt-2" onclick="addScopeRow()">+ Add Detail</button>
            </div>
            <div class="form-group"><label>Notes</label><textarea name="notes"></textarea></div>
            <button class="btn btn-primary">Add Service</button>
        </form>
        <script>
        const servicesData = <?= json_encode($allServices) ?>;
        function renderAddServiceSubcategories() {
            const sid = document.getElementById('addServiceSelect').value;
            const container = document.getElementById('addServiceSubcategories');
            container.innerHTML = '';
            if (!sid) { container.style.display = 'none'; return; }
            
            const svc = servicesData.find(s => s.id == sid);
            if (!svc || !svc.subcategories || svc.subcategories.length === 0) {
                container.style.display = 'none'; return;
            }
            
            container.style.display = 'block';
            let html = '<label>Subcategory Quantities</label><div class="grid grid-2" style="gap:12px">';
            svc.subcategories.forEach(sub => {
                html += `<div><label style="font-size:12px;font-weight:600">${sub.name}</label><input type="number" name="subcategories[${sub.id}]" value="0" min="0" class="form-control"></div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        }

        function addScopeRow() {
            const div = document.createElement('div');
            div.className = 'form-row';
            div.style.marginBottom = '8px';
            div.innerHTML = `
                <div class="form-group" style="flex:1;margin:0"><input type="text" name="scope_keys[]" placeholder="Metric (e.g. Reels)" class="form-control"></div>
                <div class="form-group" style="flex:1;margin:0 8px"><input type="text" name="scope_values[]" placeholder="Value (e.g. 15)" class="form-control"></div>
                <button type="button" class="btn btn-sm btn-danger" style="margin:0" onclick="this.parentElement.remove()">&times;</button>
            `;
            document.getElementById('scope-builder').appendChild(div);
        }
        </script>
    </div>
</div>

<div class="modal-overlay" id="addRequirementModal">
    <div class="modal">
        <span class="modal-close" data-modal-close>&times;</span>
        <div class="modal-title">Add Requirement to <span id="reqServiceName"></span></div>
        <form method="post" action="<?= url('clients', ['action' => 'add_requirement']) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
            <input type="hidden" name="client_service_id" id="reqClientServiceId" value="">
            <div class="form-group"><label>Requirement / Task Name</label><input type="text" name="requirement_name" placeholder="e.g. 5 Reels, Video Editing" required></div>
            <div class="form-row">
                <div class="form-group"><label>Assign To</label>
                    <select name="user_id" required><option value="">— Select —</option><?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?><?= $m['id'] === Auth::id() ? ' (YOU)' : '' ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group"><label>Quantity</label><input type="number" name="quantity_assigned" min="1"></div>
            </div>
            <div class="form-group"><label>Deadline</label><input type="date" name="deadline"></div>
            <div class="form-group"><label>Notes</label><textarea name="notes" placeholder="Specific details for the assignee..."></textarea></div>
            <button class="btn btn-primary">Assign Requirement</button>
        </form>
    </div>
</div>
<script>
function openAddRequirement(csId, svcName) {
    document.getElementById('reqClientServiceId').value = csId;
    document.getElementById('reqServiceName').innerText = svcName;
    document.getElementById('addRequirementModal').classList.add('show');
}
</script>
<?php endif; ?>

<!-- Client Add Deliverable Modal (Reels / Posts) -->
<div class="modal-overlay" id="clientAddDeliverableModal">
    <div class="modal" style="max-width:540px;">
        <span class="modal-close" onclick="closeClientAddDeliverable()">&times;</span>
        <div class="modal-title" id="clientDelivModalTitle">Schedule New Deliverable</div>
        <form method="post" action="<?= url('content_calendar', ['action' => 'store']) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
            <input type="hidden" name="return_to" value="client">
            
            <div class="form-row">
                <div class="form-group"><label>Deliverable Type *</label>
                    <select name="content_type" id="clientDelivType" required class="form-control">
                        <option value="reel">🎬 Reel (Short-Form Video)</option>
                        <option value="post">🖼️ Post / Static Graphic</option>
                        <option value="carousel">📑 Carousel</option>
                        <option value="story">📱 Story</option>
                    </select>
                </div>
                <div class="form-group"><label>Scheduled Post Date *</label>
                    <input type="date" name="post_date" value="<?= date('Y-m-d') ?>" required class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label>Title / Concept *</label>
                <input type="text" name="title" id="clientDelivTitle" placeholder="e.g. Testimonial Reel #02 or Special Offer Post" required class="form-control">
            </div>

            <div class="form-row">
                <div class="form-group"><label>Assignee (Editor / Creator)</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($managers as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['name']) ?><?= $m['id'] === Auth::id() ? ' (YOU)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Pipeline Stage</label>
                    <select name="status" class="form-control">
                        <option value="draft">1. Draft / Scripting</option>
                        <option value="pending_editor_check">2. Pending Editor Check</option>
                        <option value="manager_review">3. Pending Manager Review (Manav)</option>
                        <option value="scheduled">4. Scheduled</option>
                        <option value="published">5. Published &amp; Completed</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Google Drive / Video Asset Link</label>
                <input type="url" name="drive_link" placeholder="https://drive.google.com/..." class="form-control">
            </div>

            <div class="form-group">
                <label>Brief / Script Notes</label>
                <textarea name="content" rows="3" placeholder="Notes, hooks, editing guidelines..." class="form-control"></textarea>
            </div>

            <div class="flex-between" style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Add Deliverable &amp; Link to Calendar</button>
                <button type="button" class="btn" onclick="closeClientAddDeliverable()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openClientAddDeliverable(type) {
    document.getElementById('clientDelivType').value = type;
    document.getElementById('clientDelivModalTitle').innerText = (type === 'reel') ? '🎬 Add Reel Deliverable' : '🖼️ Add Post / Graphic Deliverable';
    document.getElementById('clientDelivTitle').placeholder = (type === 'reel') ? 'e.g. Product Demo Reel #04' : 'e.g. Infographic Carousel Post';
    document.getElementById('clientAddDeliverableModal').classList.add('show');
}

function closeClientAddDeliverable() {
    document.getElementById('clientAddDeliverableModal').classList.remove('show');
}

function toggleClientDeliverableStatus(id) {
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
            window.location.reload();
        } else {
            alert(data.error || 'Failed to update deliverable status.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error while updating status.');
    });
}
</script>
