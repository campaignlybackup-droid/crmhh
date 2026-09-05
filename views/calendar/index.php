<?php
$byDay = [];
foreach ($events as $ev) {
    $d = (int)date('j', strtotime($ev['start_datetime']));
    $byDay[$d][] = $ev;
}
$firstTs = strtotime("$year-$month-01");
$daysInMonth = (int)date('t', $firstTs);
$startWeekday = (int)date('w', $firstTs); // 0 = Sunday
?>
<div class="flex-between">
    <h1><?= date('F Y', $firstTs) ?></h1>
    <div class="btn-group">
        <?php if (!empty($users)): ?>
        <form method="get" style="display:inline">
            <input type="hidden" name="page" value="calendar"><input type="hidden" name="y" value="<?= $year ?>"><input type="hidden" name="m" value="<?= $month ?>">
            <select name="user_id" data-autosubmit onchange="this.form.submit()">
                <option value="">My Calendar</option>
                <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $viewUserId==$u['id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
        <a href="<?= url('calendar', ['y' => $prevYear, 'm' => $prevMonth]) ?>" class="btn btn-sm">&larr; Prev</a>
        <a href="<?= url('calendar', ['y' => date('Y'), 'm' => date('n')]) ?>" class="btn btn-sm">Today</a>
        <a href="<?= url('calendar', ['y' => $nextYear, 'm' => $nextMonth]) ?>" class="btn btn-sm">Next &rarr;</a>
        <button class="btn btn-sm btn-primary" data-modal-open="addEventModal">+ Add Event</button>
    </div>
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
?>
    <td class="wrap" style="vertical-align:top;min-width:130px">
        <strong><?= $day ?></strong>
        <?php foreach ($byDay[$day] ?? [] as $ev): ?>
            <div class="tag" style="display:block;margin:3px 0"><?= e($ev['title']) ?></div>
        <?php endforeach; ?>
    </td>
<?php endfor; ?>
</tr>
</tbody>
</table>
</div>

<div class="modal-overlay" id="addEventModal">
    <div class="modal">
        <span class="modal-close" data-modal-close>&times;</span>
        <div class="modal-title">Add Calendar Event</div>
        <form method="post" action="<?= url('calendar', ['action' => 'create']) ?>">
            <?= Csrf::field() ?>
            <div class="form-group"><label>Title *</label><input type="text" name="title" required></div>
            <div class="form-row">
                <div class="form-group"><label>Start *</label><input type="datetime-local" name="start_datetime" required></div>
                <div class="form-group"><label>End</label><input type="datetime-local" name="end_datetime"></div>
            </div>
            <div class="form-group"><label>Location</label><input type="text" name="location"></div>
            <div class="form-group"><label>Description</label><textarea name="description"></textarea></div>
            <button class="btn btn-primary">Add Event</button>
        </form>
    </div>
</div>
