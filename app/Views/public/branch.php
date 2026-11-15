<?php
/** One public branch page. */
$b = $branch ?? [];
$classes = $classes ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_site.php';
?>
<section class="ts-hero" style="padding:3rem 0 2.4rem;">
    <div class="ts-wrap">
        <a href="<?= BASE_URL ?>/timetable" style="color:#fff; opacity:.85; text-decoration:none;">&larr; <?= te('All classes') ?></a>
        <h1 style="margin-top:.6rem;">&#128205; <?= te($b['name']) ?></h1>
        <?php if (!empty($b['location'])): ?><p><?= te($b['location']) ?></p><?php endif; ?>
        <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
            <?php if (!empty($b['phone1'])): ?><a class="ts-btn ts-btn--yellow" href="tel:<?= $e(preg_replace('/[^\d+]/', '', (string) $b['phone1'])) ?>">&#128222; <?= $e($b['phone1']) ?></a><?php endif; ?>
            <?php if (!empty($b['location'])): ?><a class="ts-btn ts-btn--ghost" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode((string) $b['location']) ?>">&#128506; <?= te('Open in Maps') ?></a><?php endif; ?>
            <a class="ts-btn ts-btn--ghost" href="<?= BASE_URL ?>/?branch=<?= (int) $b['id'] ?>#inquiry"><?= te('Enquire about a class') ?></a>
        </div>
    </div>
</section>
<section class="ts-section">
    <div class="ts-wrap">
        <h2><?= te('Timetable') ?></h2>
        <?php if ($classes === []): ?>
            <p class="ts-muted"><?= te('The timetable for this branch will appear here soon.') ?></p>
        <?php else: ?>
            <div class="ts-grid"><?php foreach ($classes as $c) { require __DIR__ . '/_class-card.php'; } ?></div>
        <?php endif; ?>
    </div>
</section>
