<?php
/** Notices for a student or a parent. */
$notices = $notices ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="page-header"><div><h1 class="page-header__title"><?= te('Notices') ?></h1>
    <p class="text-sm text-muted"><?= te('Announcements from your classes and branch.') ?></p></div></div>
<?php if ($notices === []): ?>
    <div class="card"><div style="padding:3rem 1.5rem; text-align:center;" class="text-sm text-muted"><?= te('No notices right now.') ?></div></div>
<?php endif; ?>
<div style="display:grid; gap:.85rem; max-width:860px;">
<?php foreach ($notices as $n): ?>
    <article class="card" style="padding:1rem 1.15rem; border-left:4px solid var(--accent);">
        <h3 style="margin:0; font-size:1.05rem;"><?= $e($n['title']) ?></h3>
        <div class="text-xs text-muted" style="margin:.2rem 0 .6rem;">
            <?= $e($n['audience'] === 'class' ? $n['class_name'] : ($n['audience'] === 'branch' ? $n['institute_name'] : t('All branches'))) ?>
            &middot; <?= $e(lang_date('l j M Y', (string) ($n['publish_at'] ?? $n['created_at']))) ?>
        </div>
        <div style="white-space:pre-wrap; overflow-wrap:anywhere;"><?= $e($n['body']) ?></div>
    </article>
<?php endforeach; ?>
</div>
