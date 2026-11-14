<?php
/**
 * Public Events page -- every seminar/event the staff chose to show on
 * the website (SiteAdService/SeminarController already manage the
 * poster upload; this page is just the public listing). A separate nav
 * tab from "About", placed right after it.
 */
use App\Services\SiteContentService;

$events = $events ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sc = new SiteContentService();
require __DIR__ . '/_site.php';
?>
<section class="ts-hero" style="padding:5.6rem 0 3rem;">
    <div class="ts-wrap">
        <div class="ts-eyebrow"><?= $e($sc->get('events_eyebrow')) ?></div>
        <h1 style="max-width:22ch;"><?= $e($sc->get('events_title')) ?></h1>
        <p><?= $e($sc->get('events_text')) ?></p>
    </div>
</section>

<section class="ts-section">
    <div class="ts-wrap">
        <?php if ($events === []): ?>
            <p class="ts-muted"><?= $e($sc->get('events_empty_text')) ?></p>
        <?php else: ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:1.6rem;">
            <?php foreach ($events as $ev): ?>
                <?php $poster = \App\Services\SeminarService::coverSrc($ev['cover_image_url'] ?? null); ?>
                <a class="ts-card ts-reveal" href="<?= BASE_URL ?>/seminar/<?= (int) $ev['id'] ?>"
                   style="display:block; padding:0; overflow:hidden; text-decoration:none; color:inherit;">
                    <?php if ($poster !== ''): ?>
                        <div style="aspect-ratio:4/3; overflow:hidden; background:#EFF6FF;">
                            <img src="<?= $e($poster) ?>" alt="<?= $e($ev['title']) ?>" loading="lazy" style="width:100%; height:100%; object-fit:cover; display:block;">
                        </div>
                    <?php else: ?>
                        <div style="aspect-ratio:4/3; background:linear-gradient(135deg,#0A2A6B,#1D4ED8); display:flex; align-items:center; justify-content:center; padding:1rem;">
                            <span style="color:#fff; font-weight:700; font-size:1.05rem; text-align:center;"><?= $e($ev['title']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div style="padding:1.1rem 1.2rem;">
                        <div class="ts-muted" style="font-size:.82rem; font-weight:600; margin-bottom:.3rem;">
                            &#128197; <?= $e(lang_date('j M Y', (string) $ev['event_date'])) ?>
                            <?php if (!empty($ev['start_time'])): ?> &middot; <?= $e(date('g:i A', strtotime((string) $ev['start_time']))) ?><?php endif; ?>
                        </div>
                        <h3 style="margin:0 0 .3rem; font-size:1.05rem;"><?= $e($ev['title']) ?></h3>
                        <?php if (!empty($ev['venue']) || !empty($ev['branch_name'])): ?>
                            <div class="ts-muted" style="font-size:.85rem; margin-bottom:.4rem;">
                                &#128205; <?= $e(trim(($ev['venue'] ?? '') !== '' ? (string) $ev['venue'] : (string) ($ev['branch_name'] ?? ''))) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($ev['description'])): ?>
                            <p class="ts-muted" style="margin:0; font-size:.88rem; line-height:1.5;">
                                <?= $e(mb_strimwidth(strip_tags((string) $ev['description']), 0, 110, '...')) ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($ev['registration_required'])): ?>
                            <span class="badge badge--warning" style="margin-top:.6rem; display:inline-block;"><?= te('Registration required') ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
