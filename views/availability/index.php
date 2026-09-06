<?php
$byDate = [];
foreach ($entries as $e) { $byDate[$e['date']] = $e; }
$firstTs = strtotime("$year-$month-01");
$daysInMonth = (int)date('t', $firstTs);
$startWeekday = (int)date('w', $firstTs);
$statusColors = ['available' => 'success', 'busy' => 'warning', 'meeting' => 'primary', 'unavailable' => 'danger'];
?>
<div class="flex-between">
    <h1><?= date('F Y', $firstTs) ?> &mdash; Founder Availability</h1>
    <div class="btn-group">
        <a href="<?= url('availability', ['y' => $prevYear, 'm' => $prevMonth]) ?>" class="btn btn-sm">&larr; Prev</a>
        <a href="<?= url('availability', ['y' => date('Y'), 'm' => date('n')]) ?>" class="btn btn-sm">Today</a>
        <a href="<?= url('availability', ['y' => $nextYear, 'm' => $nextMonth]) ?>" class="btn btn-sm">Next &rarr;</a>
    </div>
</div>
<p class="text-muted small">All team members can see the Founder's availability so they know the best time to reach out or schedule meetings.</p>

<div style="margin: 24px 0;">
    <!-- Google Calendar Appointment Scheduling begin -->
    <iframe src="https://calendar.google.com/calendar/appointments/AcZssZ0TibWzR9plALFSAtpMHGxECfIdW_Yty0s296c=?gv=true" style="border: 0" width="100%" height="600" frameborder="0"></iframe>
    <!-- end Google Calendar Appointment Scheduling --> 
</div>

<div class="table-wrap">
<table>
<thead><tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr></thead>
<tbody>
<tr>
<?php for ($i = 0; $i < $startWeekday; $i++): ?><td style="background:#fafbfc"></td><?php endfor; ?>
<?php for ($day = 1; $day <= $daysInMonth; $day++):
    $cellCol = ($startWeekday + $day - 1) % 7;
    if ($cellCol === 0 && $day !== 1) echo '</tr><tr>';
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $entry = $byDate[$dateStr] ?? null;
?>
    <td class="wrap" style="vertical-align:top;min-width:130px">
        <strong><?= $day ?></strong>
        <?php if ($entry): ?>
            <div><span class="badge badge-<?= $statusColors[$entry['status']] ?? 'secondary' ?>"><?= e(humanize($entry['status'])) ?></span></div>
            <?php if ($entry['note']): ?><div class="small text-muted"><?= e($entry['note']) ?></div><?php endif; ?>
        <?php endif; ?>
        <?php if (Permission::has('availability.manage')): ?>
            <button class="btn btn-sm" style="margin-top:4px;padding:2px 6px" data-modal-open="setAvail<?= $dateStr ?>">Set</button>
            <div class="modal-overlay" id="setAvail<?= $dateStr ?>">
                <div class="modal" style="max-width:360px">
                    <span class="modal-close" data-modal-close>&times;</span>
                    <div class="modal-title">Availability for <?= format_date($dateStr) ?></div>
                    <form method="post" action="<?= url('availability', ['action' => 'set']) ?>">
                        <?= Csrf::field() ?><input type="hidden" name="date" value="<?= $dateStr ?>">
                        <div class="form-group"><label>Status</label>
                            <select name="status">
                                <?php foreach (['available','busy','meeting','unavailable'] as $s): ?>
                                    <option value="<?= $s ?>" <?= ($entry['status'] ?? '')===$s?'selected':'' ?>><?= e(humanize($s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Note</label><input type="text" name="note" value="<?= e($entry['note'] ?? '') ?>"></div>
                        <button class="btn btn-primary btn-sm">Save</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </td>
<?php endfor; ?>
</tr>
</tbody>
</table>
</div>
