<?php
function notif_url(array $n): ?string
{
    if (!$n['related_type'] || !$n['related_id']) return null;
    $map = ['lead' => 'leads', 'task' => 'tasks', 'client' => 'clients', 'leave' => 'leave'];
    $page = $map[$n['related_type']] ?? null;
    return $page ? url($page, ['action' => 'view', 'id' => $n['related_id']]) : null;
}
?>
<h1>Notifications</h1>
<div class="card">
<?php if (empty($rows)): ?>
    <p class="text-muted">You have no notifications.</p>
<?php endif; ?>
<ul class="timeline">
<?php foreach ($rows as $n): $link = notif_url($n); ?>
    <li>
        <strong><?= $link ? '<a href="'.e($link).'">'.e($n['title']).'</a>' : e($n['title']) ?></strong>
        <?php if ($n['message']): ?><div class="text-muted small"><?= e($n['message']) ?></div><?php endif; ?>
        <div class="timeline-meta"><?= format_datetime($n['created_at']) ?></div>
    </li>
<?php endforeach; ?>
</ul>
</div>
<?php render('partials/pagination', ['p' => $p]); ?>
