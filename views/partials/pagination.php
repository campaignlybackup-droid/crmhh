<?php if ($p['totalPages'] <= 1) return; ?>
<div class="pagination">
    <?php $qs = query_string_without(['p']); ?>
    <?php for ($i = 1; $i <= $p['totalPages']; $i++): ?>
        <?php if ($i === $p['page']): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="?<?= $qs ?>&p=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    <span class="text-muted small" style="align-self:center;margin-left:8px"><?= $p['totalRows'] ?> total</span>
</div>
