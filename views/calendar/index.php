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
                <option value="all" <?= $viewUserId === 'all' ? 'selected' : '' ?>>Full Agency / Team</option>
                <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $viewUserId == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
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
    $dayEvents = $byDay[$day] ?? [];
    $hasEvents = count($dayEvents) > 0;
?>
    <td class="wrap" style="vertical-align:top;min-width:130px; cursor:<?= $hasEvents ? 'pointer' : 'default' ?>; transition:background 0.2s;" <?= $hasEvents ? 'onclick="openDayModal('.$day.')" onmouseover="this.style.background=\'var(--bg-hover)\'" onmouseout="this.style.background=\'none\'"' : '' ?>>
        <strong><?= $day ?></strong>
        <div style="margin-top:8px;">
        <?php foreach ($dayEvents as $idx => $ev): if($idx >= 4) { echo '<div class="small text-muted">+ '.(count($dayEvents)-4).' more</div>'; break; } ?>
            <?php 
                $color = 'var(--primary)';
                if ($ev['event_type'] === 'deadline') $color = 'var(--warning)';
                if ($ev['event_type'] === 'followup') $color = 'var(--success)';
            ?>
            <div class="tag" style="display:block;margin:3px 0; background:<?= $color ?>; color:#fff; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; border:none;">
                <?= date('g:ia', strtotime($ev['start_datetime'])) ?> - <?= e($ev['title']) ?>
            </div>
        <?php endforeach; ?>
        </div>
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

<div class="modal-overlay" id="dayDetailModal">
    <div class="modal" style="max-width:600px">
        <span class="modal-close" data-modal-close>&times;</span>
        <div class="modal-title" id="dayModalTitle">Events</div>
        <div id="dayModalBody" style="max-height:60vh; overflow-y:auto; padding-right:12px;"></div>
    </div>
</div>

<script>
const eventsByDay = <?= json_encode($byDay) ?>;
const currentYear = <?= $year ?>;
const currentMonth = <?= $month ?>;

function openDayModal(day) {
    const events = eventsByDay[day] || [];
    if (!events.length) return;
    
    document.getElementById('dayModalTitle').innerText = 'Schedule for ' + currentYear + '-' + currentMonth.toString().padStart(2, '0') + '-' + day.toString().padStart(2, '0');
    let html = '';
    
    events.forEach(ev => {
        let icon = '🕒';
        if (ev.event_type === 'deadline') icon = '🎯';
        if (ev.event_type === 'followup') icon = '📞';
        
        let link = '';
        if (ev.related_type === 'task') link = `<a href="<?= url('tasks') ?>?search=${ev.related_id}" class="btn btn-sm" style="padding:2px 8px; font-size:12px;">View Task</a>`;
        if (ev.related_type === 'lead') link = `<a href="<?= url('leads') ?>?search=${ev.related_id}" class="btn btn-sm" style="padding:2px 8px; font-size:12px;">View Lead</a>`;
        
        const time = new Date(ev.start_datetime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        html += `
        <div class="card" style="margin-bottom:12px; padding:16px; border-left: 4px solid var(--primary);">
            <div class="flex-between">
                <div>
                    <h3 style="margin:0 0 8px 0; font-size:1.1rem;">${icon} ${ev.title}</h3>
                    <div class="text-muted small"><strong>Time:</strong> ${time} ${ev.location ? '| <strong>Location:</strong> '+ev.location : ''}</div>
                </div>
                <div>${link}</div>
            </div>
            ${ev.description ? '<div style="margin-top:12px; font-size:0.95rem;">'+ev.description.replace(/\\n/g, '<br>')+'</div>' : ''}
        </div>`;
    });
    
    document.getElementById('dayModalBody').innerHTML = html;
    document.getElementById('dayDetailModal').classList.add('active');
}
</script>
