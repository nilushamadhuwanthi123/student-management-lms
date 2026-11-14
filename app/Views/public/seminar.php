<?php
/**
 * Public single-seminar detail page (no login required).
 * Shows the poster, title, date/time, venue, and description.
 */
$e  = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$s  = $seminar;
$cover = \App\Services\SeminarService::coverSrc($s['cover_image_url'] ?? null);
$dt = !empty($s['event_date']) ? date('Y-m-d', strtotime($s['event_date'])) : '';
$day   = $dt ? date('l, j F Y', strtotime($dt)) : '';
$start = !empty($s['start_time']) ? date('g:i A', strtotime($s['start_time'])) : '';
$end   = !empty($s['end_time'])   ? date('g:i A', strtotime($s['end_time']))   : '';
?>
<style>
.sem-hero { max-width:900px; margin:2rem auto; padding:0 1rem; }
.sem-poster { width:100%; border-radius:14px; box-shadow:0 8px 32px rgba(0,0,0,.12); margin-bottom:1.5rem; }
.sem-title { font-size:1.8rem; font-weight:800; color:#1E293B; margin:0 0 .6rem; }
.sem-meta { display:flex; flex-wrap:wrap; gap:.8rem 1.5rem; margin-bottom:1.2rem; font-size:.95rem; color:#475569; }
.sem-meta span { display:inline-flex; align-items:center; gap:.35rem; }
.sem-desc { line-height:1.7; color:#334155; font-size:1rem; white-space:pre-line; }
.sem-back { display:inline-block; margin-bottom:1.2rem; color:#1D4ED8; font-weight:600; text-decoration:none; font-size:.95rem; }
.sem-back:hover { text-decoration:underline; }
.sem-badge { display:inline-block; background:#DBEAFE; color:#1E40AF; font-size:.78rem; font-weight:700; padding:.2rem .6rem; border-radius:6px; }
@media (min-width:700px) { .sem-hero { padding:0 2rem; } .sem-title { font-size:2.2rem; } }
</style>

<div class="sem-hero">
    <a href="<?= BASE_URL ?>/#seminars" class="sem-back">&larr; <?= te('Back to home') ?></a>

    <?php if ($cover): ?>
        <img src="<?= $e($cover) ?>" alt="<?= $e($s['title']) ?>" class="sem-poster" loading="eager">
    <?php endif; ?>

    <h1 class="sem-title"><?= $e($s['title']) ?></h1>

    <div class="sem-meta">
        <?php if ($day): ?>
            <span>&#128197; <?= te($day) ?></span>
        <?php endif; ?>
        <?php if ($start): ?>
            <span>&#128336; <?= $e($start) ?><?= $end ? ' &ndash; ' . $e($end) : '' ?></span>
        <?php endif; ?>
        <?php if (!empty($s['venue'])): ?>
            <span>&#128205; <?= $e($s['venue']) ?></span>
        <?php endif; ?>
        <?php if (!empty($s['institute_name'])): ?>
            <span class="sem-badge"><?= $e($s['institute_name']) ?></span>
        <?php endif; ?>
    </div>

    <?php if (!empty($s['description'])): ?>
        <div class="sem-desc"><?= $e($s['description']) ?></div>
    <?php endif; ?>

    <?php if (!empty($s['online_url'])): ?>
        <p style="margin-top:1.2rem;">
            <a href="<?= $e($s['online_url']) ?>" target="_blank" rel="noopener" class="ts-btn ts-btn--blue" style="padding:.6rem 1.2rem;">
                &#127760; <?= te('Join online') ?>
            </a>
        </p>
    <?php endif; ?>
</div>
