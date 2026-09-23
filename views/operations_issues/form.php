<div style="max-width: 650px; margin: 0 auto;">
    <div class="flex-between" style="margin-bottom: 20px;">
        <h1 style="margin:0;">Log Operational Error</h1>
        <a href="<?= url('operations_issues') ?>" class="btn">Back to Log</a>
    </div>

    <div class="card">
        <form method="post" action="<?= url('operations_issues', ['action' => 'store']) ?>">
            <?= Csrf::field() ?>
            
            <div class="form-group">
                <label>What is the Issue? *</label>
                <input type="text" name="issue_title" required placeholder="e.g. Video delivered without client color grade revision" class="form-control">
            </div>

            <div class="form-group">
                <label>Issue Description &amp; What Went Wrong</label>
                <textarea name="description" rows="4" placeholder="Describe the error in detail, what was missed, and required fix..." class="form-control"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Who is Responsible? *</label>
                    <select name="responsible_id" required class="form-control">
                        <option value="">— Select Responsible Person —</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Severity Level</label>
                    <select name="severity" class="form-control">
                        <option value="minor">Minor (Internal hiccup)</option>
                        <option value="medium" selected>Medium (Standard error)</option>
                        <option value="major">Major (Client noticed / revision delay)</option>
                        <option value="critical">Critical (Client escalation / direct risk)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Deduction Amount (₹)</label>
                    <input type="number" step="0.01" name="deduction_amount" placeholder="0.00" value="0.00" class="form-control">
                    <small class="help-text">Amount to be deducted/penalized for this mistake.</small>
                </div>
                <div class="form-group">
                    <label>Deduction Type</label>
                    <select name="deduction_type" class="form-control">
                        <option value="salary_deduction">Salary Deduction</option>
                        <option value="penalty_points">Penalty Points</option>
                        <option value="warning_no_deduction">Warning Only (No Deduction)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Associated Client (Optional)</label>
                    <select name="client_id" class="form-control">
                        <option value="">— None / Internal —</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Target Rectification Date</label>
                    <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="form-control">
                    <small class="help-text" style="color:var(--danger)">If delayed past this date, founder is auto-escalated.</small>
                </div>
            </div>

            <div style="background:var(--bg-hover); padding:12px; border-radius:var(--radius); margin-bottom:16px;">
                <small style="color:var(--muted); display:block;">
                    <strong>Automatic Notifications:</strong> Submitting this will automatically notify the assigned person and immediately alert the Founder/Owner so quality remains high.
                </small>
            </div>

            <div class="flex-between">
                <button type="submit" class="btn btn-primary">Log Error &amp; Notify Founder</button>
                <a href="<?= url('operations_issues') ?>" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>
