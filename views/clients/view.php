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
                <strong><?= e($svc['service_name']) ?></strong>
                <span class="small text-muted"><?= $completed ?> / <?= $required ?> <?= e($svc['unit_label']) ?></span>
            </div>
            <div class="progress" style="margin:8px 0"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>

            <?php if ($fullAccess): ?>
                <div class="small text-muted">Manager: <?= e(Database::scalar('SELECT name FROM users WHERE id=?', [$svc['manager_id']]) ?: '—') ?></div>
                <table style="margin-top:8px">
                    <thead><tr><th>Assigned To</th><th>Qty Assigned</th><th>Completed</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($svc['assignments'] as $a): ?>
                        <tr>
                            <td><?= e($a['user_name']) ?></td>
                            <td><?= $a['quantity_assigned'] !== null ? (int)$a['quantity_assigned'] : '—' ?></td>
                            <td><?= (int)$a['quantity_completed'] ?></td>
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
                <?php if (Permission::hasAny(['clients.manage_services','clients.assign'])): ?>
                <form method="post" action="<?= url('clients', ['action' => 'assign_employee']) ?>" class="form-row" style="margin-top:8px;align-items:flex-end">
                    <?= Csrf::field() ?><input type="hidden" name="client_service_id" value="<?= $svc['id'] ?>"><input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                    <div class="form-group"><label>Assign employee</label>
                        <select name="user_id"><option value="">— Select —</option>
                            <?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="max-width:120px"><label>Quantity</label><input type="number" name="quantity_assigned" min="0"></div>
                    <button class="btn btn-sm">Assign</button>
                </form>
                <?php endif; ?>
                <?php if (Permission::has('clients.manage_services')): ?>
                <details style="margin-top:8px"><summary class="small">Edit requirement</summary>
                <form method="post" action="<?= url('clients', ['action' => 'update_service']) ?>" class="form-row mt-2">
                    <?= Csrf::field() ?><input type="hidden" name="client_service_id" value="<?= $svc['id'] ?>"><input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                    <div class="form-group" style="max-width:120px"><label>Required Qty</label><input type="number" name="quantity_required" value="<?= (int)$svc['quantity_required'] ?>" min="0"></div>
                    <div class="form-group"><label>Manager</label>
                        <select name="manager_id"><option value="">—</option>
                            <?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>" <?= $m['id']==$svc['manager_id']?'selected':'' ?>><?= e($m['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Status</label>
                        <select name="status">
                            <option value="active" <?= $svc['status']==='active'?'selected':'' ?>>Active</option>
                            <option value="completed" <?= $svc['status']==='completed'?'selected':'' ?>>Completed</option>
                            <option value="paused" <?= $svc['status']==='paused'?'selected':'' ?>>Paused</option>
                        </select>
                    </div>
                    <button class="btn btn-sm btn-primary">Save</button>
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
    <div class="card-title">Related Tasks</div>
    <?php if (empty($tasks)): ?><p class="text-muted small">No tasks yet for this client.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Task</th><th>Assigned</th><th>Status</th><th>Deadline</th></tr></thead>
        <tbody>
        <?php foreach ($tasks as $t): $overdue = is_overdue($t['deadline'], $t['status']); ?>
            <tr>
                <td><a href="<?= url('tasks', ['action' => 'view', 'id' => $t['id']]) ?>"><?= e($t['title']) ?></a></td>
                <td><?= e($t['assigned_name'] ?? '—') ?></td>
                <td><span class="badge badge-<?= status_badge_class($overdue ? 'overdue' : $t['status']) ?>"><?= $overdue ? 'Overdue' : e(humanize($t['status'])) ?></span></td>
                <td><?= $t['deadline'] ? format_datetime($t['deadline']) : '—' ?></td>
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
            <div class="form-group"><label>Service</label>
                <select name="service_id" required><?php foreach ($allServices as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['unit_label']) ?>)</option><?php endforeach; ?></select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Quantity Required</label><input type="number" name="quantity_required" min="0" value="0" required></div>
                <div class="form-group"><label>Manager</label>
                    <select name="manager_id"><option value="">—</option><?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Start Date</label><input type="date" name="start_date"></div>
                <div class="form-group"><label>End Date</label><input type="date" name="end_date"></div>
            </div>
            <div class="form-group"><label>Notes</label><textarea name="notes"></textarea></div>
            <button class="btn btn-primary">Add Service</button>
        </form>
    </div>
</div>
<?php endif; ?>
