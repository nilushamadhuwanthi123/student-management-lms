<?php
/** A parent's read-only view of their child's class recordings. */
$recordings = $recordings ?? [];
$childName = $childName ?? '';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Class Recordings') ?></h1>
        <p class="text-sm text-muted"><?= $e($childName) ?> &middot; <?= te('{n} recordings', ['n' => count($recordings)]) ?></p>
    </div>
</div>

<?php if ($recordings === []): ?>
    <div class="card"><div style="padding:3rem 1.5rem; text-align:center;">
        <div style="font-size:2.5rem;">&#127909;</div>
        <p class="text-muted" style="margin:.5rem 0 0;"><?= te('No recordings posted yet.') ?></p>
    </div></div>
<?php else: ?>
<div style="display:grid; gap:.75rem;">
    <?php foreach ($recordings as $r): ?>
        <div class="card" style="display:block; padding:1rem 1.15rem; <?= $r['locked'] ? 'border-color:var(--warning);' : '' ?>">
            <div style="display:flex; justify-content:space-between; gap:.75rem; flex-wrap:wrap;">
                <div>
                    <div style="font-weight:700;"><?= $e($r['title']) ?></div>
                    <div class="text-sm text-muted">
                        <?= $e($r['class_name']) ?>
                        <?php if (!empty($r['recording_date'])): ?> &middot; <?= $e(lang_date('j M Y', (string) $r['recording_date'])) ?><?php endif; ?>
                    </div>
                </div>
                <?php if ($r['locked']): ?>
                    <span class="text-sm" style="color:var(--warning);">&#128274; <?= te('Locked until the fee is settled') ?></span>
                <?php else: ?>
                    <a href="<?= $e($r['video_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn--primary btn--sm"><?= te('Watch') ?></a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
