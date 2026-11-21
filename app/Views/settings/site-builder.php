<?php
/**
 * Site Builder overview.
 *
 * One place that says what can be changed about the public website and
 * where each thing lives. The pieces themselves are the existing
 * screens -- this does not duplicate them, it just stops the owner
 * hunting through Settings for "where do I change the colours".
 *
 * Variables: $slideCount, $adCount, $liveUrl
 * Layout: layouts/admin
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$slideCount = (int) ($slideCount ?? 0);
$adCount    = (int) ($adCount ?? 0);

$cards = [
    [
        'href'  => BASE_URL . '/settings/site-content',
        'icon'  => '&#127912;',
        'title' => t('Design & content'),
        'text'  => t('Ten ready-made styles, your own colours, the menu bar, lettering, corner roundness, and every piece of wording on the public pages.'),
        'tags'  => [t('Styles'), t('Colours'), t('Wording'), t('Show or hide')],
        'cta'   => t('Open'),
        'wide'  => true,
    ],
    [
        'href'  => BASE_URL . '/settings/hero-media',
        'icon'  => '&#128444;',
        'title' => t('Background photos'),
        'text'  => t('The photos and short videos behind the heading on the home page. Add as many as you like and set the order.'),
        'tags'  => [$slideCount === 1 ? t('1 in use') : t('{n} in use', ['n' => $slideCount])],
        'cta'   => t('Manage'),
    ],
    [
        'href'  => BASE_URL . '/settings/ads',
        'icon'  => '&#128226;',
        'title' => t('Announcements'),
        'text'  => t('The strip at the top of the home page, for a seminar, a notice or an offer.'),
        'tags'  => [$adCount === 1 ? t('1 in use') : t('{n} in use', ['n' => $adCount])],
        'cta'   => t('Manage'),
    ],
    [
        'href'  => BASE_URL . '/downloads/manage',
        'icon'  => '&#128229;',
        'title' => t('Downloads'),
        'text'  => t('Past papers, notes and other files shared on the public Downloads page, arranged into folders.'),
        'tags'  => [t('Folders')],
        'cta'   => t('Manage'),
    ],
    [
        'href'  => BASE_URL . '/seminars',
        'icon'  => '&#127891;',
        'title' => t('Seminars & events'),
        'text'  => t('What shows on the Events page and in the strip at the top of the home page.'),
        'tags'  => [t('Public')],
        'cta'   => t('Manage'),
    ],
    [
        'href'  => BASE_URL . '/settings/translations',
        'icon'  => '&#127760;',
        'title' => t('Wording & languages'),
        'text'  => t('Sinhala and English wording across the site, including anything you have written yourself.'),
        'tags'  => ['EN', 'සිංහල'],
        'cta'   => t('Open'),
    ],
];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Site Builder') ?></h1>
        <p class="text-sm text-muted"><?= te('Everything that changes how the public website looks and what it says. Changes here do not affect the admin system.') ?></p>
    </div>
    <a class="btn btn--secondary" href="<?= BASE_URL ?>/" target="_blank" rel="noopener"><?= te('View the site') ?> &#8599;</a>
</div>

<div class="sb-hint">
    <strong><?= te('Start with Design & content.') ?></strong>
    <?= te('Pick one of the ten styles, press Save changes, then open the site in another tab to see it. Nothing goes live until you save, so you can click through the styles freely.') ?>
</div>

<div class="sb-grid">
    <?php foreach ($cards as $c): ?>
        <a class="sb-card<?= !empty($c['wide']) ? ' sb-card--wide' : '' ?>" href="<?= $e($c['href']) ?>">
            <span class="sb-card__icon" aria-hidden="true"><?= $c['icon'] ?></span>
            <span class="sb-card__body">
                <span class="sb-card__title"><?= $e($c['title']) ?></span>
                <span class="sb-card__text"><?= $e($c['text']) ?></span>
                <span class="sb-card__tags">
                    <?php foreach ($c['tags'] as $tag): ?>
                        <span class="sb-tag"><?= $e($tag) ?></span>
                    <?php endforeach; ?>
                </span>
            </span>
            <span class="sb-card__cta"><?= $e($c['cta']) ?> &rarr;</span>
        </a>
    <?php endforeach; ?>
</div>

<style>
    .sb-hint { background: #F0F7FF; border: 1px solid #BFDBFE; border-left: 4px solid #1D4ED8;
               border-radius: 10px; padding: .85rem 1rem; margin-bottom: 1.25rem; font-size: .9rem; color: #1E3A5F; }
    .sb-hint strong { color: #15307E; }

    .sb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 1rem; }
    .sb-card { display: flex; flex-direction: column; gap: .75rem; padding: 1.1rem 1.15rem;
               background: var(--card-bg, #fff); border: 1px solid var(--border-color, #E2E8F0);
               border-radius: 14px; text-decoration: none; color: inherit; transition: border-color .15s ease, transform .15s ease; }
    .sb-card:hover { border-color: #1D4ED8; transform: translateY(-2px); }
    .sb-card--wide { grid-column: span 2; }
    @media (max-width: 760px) { .sb-card--wide { grid-column: span 1; } }

    .sb-card__icon { font-size: 1.7rem; line-height: 1; }
    .sb-card__body { display: grid; gap: .3rem; }
    .sb-card__title { font-weight: 700; font-size: 1.02rem; color: var(--text, #1C2538); }
    .sb-card__text { font-size: .86rem; color: #64748B; line-height: 1.5; }
    .sb-card__tags { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .35rem; }
    .sb-tag { font-size: .72rem; font-weight: 600; color: #15307E; background: #EFF4FF;
              border: 1px solid #DBE5FF; border-radius: 999px; padding: .15rem .55rem; }
    .sb-card__cta { margin-top: auto; font-size: .85rem; font-weight: 700; color: #1D4ED8; }
</style>
