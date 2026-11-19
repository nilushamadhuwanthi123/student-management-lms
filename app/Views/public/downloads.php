<?php
/**
 * Public Downloads page: past papers, notes and other files the teacher
 * or super admin has shared as an external link (see DownloadService).
 *
 * Shown as folders (one per "category" the office typed when adding the
 * item, e.g. "Grade 10 Papers"), each a collapsible <details> a student
 * taps open to see its files -- easier to scan than one long flat list,
 * especially once there are papers for several grades mixed together.
 * Items with no folder land together under "Other files" at the end.
 *
 * A category can be typed with "/" to nest folders, e.g.
 * "Grade 11/Science/1st Term" -- DownloadService::activeGrouped() turns
 * that into a tree (each node: category, items, children), rendered
 * here recursively so any depth of folder/sub-folder works the same way.
 *
 * Pulls in _site.php for the shared ts-* styles (hero gradient, wrap,
 * cards, grid) -- without it this page rendered with none of the site's
 * CSS at all, just plain unstyled text butted against the left edge.
 */
use App\Services\SiteContentService;

$folders = $folders ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sc = new SiteContentService();
require __DIR__ . '/_site.php';

$countTree = static function (array $nodes) use (&$countTree): int {
    $n = 0;
    foreach ($nodes as $node) {
        $n += count($node['items']) + $countTree($node['children']);
    }
    return $n;
};
$totalItems = $countTree($folders);

/**
 * A YouTube link's video id, or null for anything else (a Drive link,
 * a plain PDF, etc.) -- lets a YouTube item show a thumbnail + play
 * button card instead of a plain file row, the way e-thaksalawa does,
 * without needing a separate "type" field: any youtube.com/youtu.be
 * link typed into the same "Link" box is enough.
 */
$youTubeId = static function (string $url): ?string {
    if (preg_match('#^https?://(?:www\.)?(?:youtube\.com/(?:watch\?v=|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})#i', trim($url), $m)) {
        return $m[1];
    }
    return null;
};

/** Renders one folder (and, recursively, its sub-folders) as a <details>. */
$renderFolder = static function (array $folder, int $depth, bool $openDefault) use (&$renderFolder, $e, $countTree, $youTubeId): void {
    $label = $folder['category'] !== null ? $folder['category'] : t('Other files');
    $count = count($folder['items']) + $countTree($folder['children']);
    ?>
    <details class="ts-dl-folder ts-reveal ts-dl-folder--d<?= min($depth, 2) ?>" <?= $openDefault ? 'open' : '' ?>>
        <summary class="ts-dl-folder__head">
            <span class="ts-dl-folder__icon" aria-hidden="true">&#128193;</span>
            <span class="ts-dl-folder__name"><?= $e($label) ?></span>
            <?php if (\App\Services\PublicSite::isOn('downloads_show_counts')): ?>
                <span class="ts-dl-folder__count"><?= te('{n} files', ['n' => $count]) ?></span>
            <?php endif; ?>
        </summary>
        <div class="ts-dl-folder__body">
            <?php foreach ($folder['children'] as $child): ?>
                <?php $renderFolder($child, $depth + 1, \App\Services\PublicSite::content('downloads_open_mode') === 'all'); ?>
            <?php endforeach; ?>
            <?php
                $videoItems = [];
                $fileItems = [];
                foreach ($folder['items'] as $it) {
                    if ($youTubeId((string) $it['drive_url']) !== null) {
                        $videoItems[] = $it;
                    } else {
                        $fileItems[] = $it;
                    }
                }
            ?>
            <?php if ($videoItems !== []): ?>
                <div class="ts-dl-videos">
                    <?php foreach ($videoItems as $it): ?>
                        <?php $vid = $youTubeId((string) $it['drive_url']); ?>
                        <a class="ts-dl-video" href="<?= $e($it['drive_url']) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="ts-dl-video__thumb">
                                <img src="https://img.youtube.com/vi/<?= $e($vid) ?>/hqdefault.jpg" alt="" loading="lazy">
                                <span class="ts-dl-video__play" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="#fff"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                            </span>
                            <span class="ts-dl-video__title"><?= $e($it['title']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php foreach ($fileItems as $it): ?>
                <a class="ts-dl-item" href="<?= $e($it['drive_url']) ?>" target="_blank" rel="noopener noreferrer">
                    <span class="ts-dl-item__icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 11v6"/><path d="m9 14 3 3 3-3"/></svg>
                    </span>
                    <span class="ts-dl-item__title"><?= $e($it['title']) ?></span>
                    <span class="ts-dl-item__open"><?= te('Open') ?> &rarr;</span>
                </a>
            <?php endforeach; ?>
        </div>
    </details>
    <?php
};
?>
<section class="ts-hero" style="padding:5.6rem 0 3rem;">
    <div class="ts-wrap">
        <div class="ts-eyebrow"><?= $e($sc->get('downloads_eyebrow')) ?></div>
        <h1 style="max-width:22ch;"><?= $e($sc->get('downloads_title')) ?></h1>
        <p><?= $e($sc->get('downloads_text')) ?></p>
    </div>
</section>

<section class="ts-section">
    <div class="ts-wrap" style="max-width:860px;">
        <?php if ($totalItems === 0): ?>
            <p class="ts-muted"><?= $e($sc->get('downloads_empty_text')) ?></p>
        <?php else: ?>
        <div class="ts-dl-folders">
            <?php
            // Set in the CMS: with two or three folders, opening them all
            // saves a tap; with twenty, all closed is far easier to scan.
            $openMode = \App\Services\PublicSite::content('downloads_open_mode');
            ?>
            <?php foreach ($folders as $i => $folder): ?>
                <?php $renderFolder($folder, 0, $openMode === 'all' || ($openMode !== 'none' && $i === 0)); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
.ts-dl-folders { display: grid; gap: 1rem; }
.ts-dl-folder {
    background: var(--surface, #fff); border: 1px solid var(--border, #E2E8F0); border-radius: 16px;
    overflow: hidden; transition: border-color .2s ease, box-shadow .2s ease;
    box-shadow: 0 1px 2px rgba(15,23,42,.04);
}
.ts-dl-folder:hover { border-color: #BFDBFE; }
.ts-dl-folder[open] { border-color: #1D4ED8; box-shadow: 0 20px 38px -26px rgba(29,78,216,.35); }
.ts-dl-folder__head {
    list-style: none; cursor: pointer; display: flex; align-items: center; gap: .85rem;
    padding: 1.1rem 1.25rem; font-weight: 700; user-select: none;
}
.ts-dl-folder__head::-webkit-details-marker { display: none; }
.ts-dl-folder__icon {
    font-size: 1.35rem; flex: 0 0 auto; width: 44px; height: 44px; border-radius: 12px;
    display: grid; place-items: center; background: linear-gradient(135deg, #FEF3C7, #FDE68A);
    transition: transform .2s ease;
}
.ts-dl-folder[open] .ts-dl-folder__icon { transform: scale(1.06); }
.ts-dl-folder__name { flex: 1; min-width: 0; color: #1E293B; font-size: 1.02rem; }
.ts-dl-folder__count { font-size: .76rem; font-weight: 700; color: #475569; background: #F1F5F9; border-radius: 999px; padding: .25rem .7rem; flex: 0 0 auto; }
.ts-dl-folder__head::after {
    content: '+'; flex: 0 0 28px; height: 28px; border-radius: 50%; display: grid; place-items: center;
    background: #EFF6FF; color: #1D4ED8; font-weight: 800; font-size: 1.1rem; transition: background .2s ease, color .2s ease, transform .2s ease;
}
.ts-dl-folder[open] .ts-dl-folder__head::after { content: '\2212'; background: #1D4ED8; color: #fff; transform: rotate(180deg); }
.ts-dl-folder__body { padding: .1rem .9rem .9rem; display: grid; gap: .5rem; border-top: 1px solid #F1F5F9; margin: 0 .35rem; padding-top: .75rem; }
/* Sub-folders (category typed as "Parent/Child/..."): nested one level further in, slightly
   smaller so the hierarchy reads at a glance; depth caps visually at 2 so very deep paths
   don't collapse into an unreadable sliver. */
.ts-dl-folder .ts-dl-folder { margin-top: .15rem; box-shadow: none; }
.ts-dl-folder--d1 { border-color: #E2E8F0; }
.ts-dl-folder--d1 .ts-dl-folder__head { padding: .85rem 1rem; font-size: .95rem; }
.ts-dl-folder--d1 .ts-dl-folder__icon { width: 36px; height: 36px; font-size: 1.1rem; background: linear-gradient(135deg, #DBEAFE, #BFDBFE); }
.ts-dl-folder--d2 .ts-dl-folder__head { padding: .7rem .9rem; font-size: .9rem; }
.ts-dl-folder--d2 .ts-dl-folder__icon { width: 32px; height: 32px; font-size: 1rem; background: linear-gradient(135deg, #E0E7FF, #C7D2FE); }
.ts-dl-item {
    display: flex; align-items: center; gap: .8rem; padding: .8rem .9rem; border-radius: 12px;
    text-decoration: none; color: inherit; background: #F8FAFC; border: 1px solid transparent;
    transition: background .15s ease, border-color .15s ease, transform .15s ease;
}
.ts-dl-item:hover { background: #EFF6FF; border-color: #BFDBFE; transform: translateX(2px); }
.ts-dl-item__icon { flex: 0 0 auto; display: grid; place-items: center; width: 38px; height: 38px; border-radius: 10px; background: #fff; box-shadow: 0 1px 3px rgba(15,23,42,.08); }
.ts-dl-item__title { flex: 1; min-width: 0; font-weight: 600; color: #1E293B; overflow-wrap: break-word; }
.ts-dl-item__open { flex: 0 0 auto; font-size: .78rem; font-weight: 700; color: #1D4ED8; white-space: nowrap; }
/* YouTube items (any youtube.com/youtu.be link): a thumbnail + play-button card,
   like e-thaksalawa's video tiles, instead of a plain file row. */
.ts-dl-videos { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: .7rem; margin-bottom: .5rem; }
.ts-dl-video { display: block; text-decoration: none; color: inherit; border-radius: 12px; overflow: hidden; background: #F8FAFC; border: 1px solid transparent; transition: border-color .15s ease, transform .15s ease; }
.ts-dl-video:hover { border-color: #BFDBFE; transform: translateY(-2px); }
.ts-dl-video__thumb { position: relative; display: block; aspect-ratio: 16/9; background: #0A2A6B; overflow: hidden; }
.ts-dl-video__thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ts-dl-video__play { position: absolute; inset: 0; display: grid; place-items: center; background: rgba(15,23,42,.18); }
.ts-dl-video__play svg { width: 46px; height: 46px; padding: 12px; border-radius: 50%; background: rgba(220,38,38,.92); box-shadow: 0 4px 14px rgba(0,0,0,.35); }
.ts-dl-video__title { display: block; padding: .6rem .7rem; font-weight: 600; font-size: .86rem; color: #1E293B; line-height: 1.35; }
@media (max-width: 480px) { .ts-dl-videos { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); } }
@media (max-width: 480px) {
    .ts-dl-folder__head { padding: .9rem 1rem; gap: .6rem; }
    .ts-dl-folder__icon { width: 38px; height: 38px; font-size: 1.15rem; }
    .ts-dl-item__open { display: none; }
}
</style>
