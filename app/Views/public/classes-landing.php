<?php
/**
 * Classes landing page -- built to the user's hand-drawn wireframe: one
 * repeated row per branch of [photo] beside a "My Classes" button, with
 * the description underneath, which takes a visitor straight into that
 * branch's own class grid at /branches/{id} (PublicController::branch()).
 *
 * Photo, description and video (link OR uploaded file) are all CMS-managed
 * from Institutes > Edit (super admin or that branch's own admin) -- see
 * InstituteController::store()/update() and the Institute model. The
 * hero wording (eyebrow/heading/intro/empty-state) is CMS-managed from
 * Settings > Website Content (SiteContentService, group "classes").
 *
 * Layout: .ts-branch-row (defined in _site.php) is a 2-column grid on
 * desktop (large photo + info) that collapses to a single stacked column
 * on tablet and phone (<=880px), so it adjusts cleanly across all three
 * screen sizes instead of relying on a fixed pixel min-width.
 */
use App\Services\SiteContentService;

$branches = $branches ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sc = new SiteContentService();
require __DIR__ . '/_site.php';
?>
<section class="ts-hero" style="padding:5.6rem 0 3rem;">
    <div class="ts-wrap">
        <div class="ts-eyebrow"><?= $e($sc->get('classes_eyebrow')) ?></div>
        <h1 style="max-width:22ch;"><?= $e($sc->get('classes_title')) ?></h1>
        <p><?= $e($sc->get('classes_text')) ?></p>
    </div>
</section>

<section class="ts-section">
    <div class="ts-wrap" style="max-width:1480px;">
        <?php if ($branches === []): ?>
            <p class="ts-muted"><?= $e($sc->get('classes_empty_text')) ?></p>
        <?php else: ?>
        <div style="display:grid; gap:2rem;">
            <?php foreach ($branches as $i => $b): ?>
                <div class="ts-reveal ts-branch-row">
                    <!-- Branch photo (left on desktop, stacked on top on tablet/phone) -->
                    <div class="ts-card ts-branch-photo">
                        <div class="ts-branch-num"><?= (int) $i + 1 ?></div>
                        <?php $__video = \Institute::videoHref($b['video_url'] ?? null); ?>
                        <?php if ($__video !== ''): ?>
                            <a href="<?= $e($__video) ?>" target="_blank" rel="noopener" class="ts-branch-video">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                                <?= te('Watch video') ?>
                            </a>
                        <?php endif; ?>
                        <?php
                            $__bs = in_array($b['banner_size'] ?? '', ['small', 'medium', 'large'], true) ? $b['banner_size'] : 'medium';
                        ?>
                        <?php if (!empty($b['photo'])): ?>
                            <div class="ts-branch-media ts-branch-media--<?= $e($__bs) ?>">
                                <img src="<?= BASE_URL . $e($b['photo']) ?>" alt="<?= te($b['name']) ?>" loading="lazy">
                            </div>
                        <?php else: ?>
                            <div class="ts-branch-media ts-branch-media--<?= $e($__bs) ?>" style="background:linear-gradient(135deg,#0A2A6B,#1D4ED8); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:1.3rem; text-align:center; padding:0 1rem;">
                                <?= te($b['name']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Branch info + button -->
                    <div>
                        <div style="font-weight:700; font-size:1.05rem; margin-bottom:.15rem;"><?= (int) $i + 1 ?>. <?= te($b['name']) ?></div>
                        <?php if (!empty($b['location'])): ?><div class="ts-muted" style="margin-bottom:.65rem; font-size:.85rem;">&#128205; <?= te($b['location']) ?></div><?php endif; ?>
                        <p class="ts-muted" style="margin:0 0 .85rem;">
                            <?= !empty($b['public_description']) ? te($b['public_description']) : te('{n} classes at this branch, physical and online.', ['n' => (int) $b['class_count']]) ?>
                        </p>
                        <a class="ts-btn ts-btn--blue" href="<?= BASE_URL ?>/branches/<?= (int) $b['id'] ?>"><?= te('My Classes') ?> &rarr;</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
