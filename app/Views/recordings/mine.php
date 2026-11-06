<?php
/** A student's class recordings, across their enrolled classes. */
$recordings = $recordings ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Class Recordings') ?></h1>
        <p class="text-sm text-muted"><?= te('{n} recordings', ['n' => count($recordings)]) ?></p>
    </div>
</div>

<?php if ($recordings === []): ?>
    <div class="card"><div style="padding:3rem 1.5rem; text-align:center;">
        <div style="font-size:2.5rem;">&#127909;</div>
        <p class="text-muted" style="margin:.5rem 0 0;"><?= te('No recordings posted yet. They will appear here as soon as your teacher adds one.') ?></p>
    </div></div>
<?php else: ?>
<div style="display:grid; gap:.75rem;">
    <?php foreach ($recordings as $r): ?>
        <?php if ($r['locked']): ?>
            <div class="card" style="display:block; padding:1rem 1.15rem; border-color:var(--warning);">
                <div style="display:flex; justify-content:space-between; gap:.75rem; flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:700;"><?= $e($r['title']) ?></div>
                        <div class="text-sm text-muted">
                            <?= $e($r['class_name']) ?>
                            <?php if (!empty($r['recording_date'])): ?> &middot; <?= $e(lang_date('j M Y', (string) $r['recording_date'])) ?><?php endif; ?>
                        </div>
                    </div>
                    <span class="text-sm" style="color:var(--warning);">&#128274; <?= te('Locked until the fee is settled') ?></span>
                </div>
            </div>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/my/recordings/<?= (int) $r['id'] ?>/open" target="_blank" rel="noopener noreferrer"
               class="card" style="display:block; padding:1rem 1.15rem; text-decoration:none; color:inherit;">
                <div style="display:flex; justify-content:space-between; gap:.75rem; flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:700;">&#9654; <?= $e($r['title']) ?></div>
                        <div class="text-sm text-muted">
                            <?= $e($r['class_name']) ?>
                            <?php if (!empty($r['recording_date'])): ?> &middot; <?= $e(lang_date('j M Y', (string) $r['recording_date'])) ?><?php endif; ?>
                        </div>
                        <?php if (!empty($r['description'])): ?>
                            <div class="text-sm text-muted" style="margin-top:.25rem;"><?= $e($r['description']) ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="btn btn--primary btn--sm"><?= te('Watch') ?></span>
                </div>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
