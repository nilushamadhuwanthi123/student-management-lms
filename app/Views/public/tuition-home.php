<?php
/**
 * Public home page for the Science tuition classes.
 *
 * Everything factual on this page comes from the database (branches the
 * super admin has published, their active classes, the teacher's name).
 * Nothing here claims numbers, results or testimonials.
 */
$branches = $branches ?? [];
$classes = $classes ?? [];
$teacher = $teacher ?? null;
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

// Wording the super admin can change from Settings > Website Content. $sc
// returns a plain line, already translated and escaped, ready for a heading
// or a button; $scr returns a sanitised richtext passage to echo as HTML.
$sc = static fn (string $key, array $params = []): string
    => htmlspecialchars(\App\Services\PublicSite::content($key, $params), ENT_QUOTES, 'UTF-8');
$scr = static fn (string $key, array $params = []): string
    => \App\Services\PublicSite::content($key, $params);

$pickGrade = preg_match('/^(6|7|8|9|10|11)$/', (string) ($_GET['grade'] ?? '')) ? (string) $_GET['grade'] : '';
$pickBranch = (int) ($_GET['branch'] ?? 0);
$byGrade = [];
foreach ($classes as $c) {
    $byGrade[(int) preg_replace('/\D+/', '', (string) $c['grade'])][] = $c;
}
ksort($byGrade);
$hasOnline = (bool) array_filter($classes, static fn (array $c): bool => ($c['delivery_mode'] ?? 'physical') !== 'physical');
require __DIR__ . '/_site.php';
?>
<?php
$facts = \App\Services\PublicSite::facts();
$isSi = function_exists('lang_current') && lang_current() === 'si';
$mediumLabel = $facts['media'] !== [] ? implode(' / ', array_map(static fn ($m) => t($m), $facts['media'])) : '';
?>
<?php
// Background slides: photos/videos uploaded from Settings > Homepage
// Background (hero_media, migration 035) if the super admin has added
// any, otherwise the four original fixed photos -- so an untouched
// install looks exactly as it always did.
$heroMediaRows = (new \App\Services\HeroMediaService())->active();
if ($heroMediaRows !== []) {
    $bgSlides = array_map(static fn (array $m): array => [
        'type' => $m['media_type'],
        'src'  => BASE_URL . $e($m['file_path']),
        'pos'  => $e($m['object_position']),
    ], $heroMediaRows);
} else {
    $defaultSlides = [
        ['teacher-board.jpg', '70% 35%'],
        ['students-experiment.jpg', '50% 35%'],
        ['science-lab.jpg', '60% 45%'],
        ['students-exam.jpg', '50% 45%'],
    ];
    $defaultSlides = array_values(array_filter($defaultSlides, static fn ($sl) => is_file(BASE_PATH . '/public/assets/img/' . $sl[0])));
    $bgSlides = array_map(static fn (array $sl): array => [
        'type' => 'image',
        'src'  => BASE_URL . '/assets/img/' . $e($sl[0]),
        'pos'  => $e($sl[1]),
    ], $defaultSlides);
}
?>
<section class="ts-hero">
    <?php if ($bgSlides !== []): ?>
    <div class="ts-hero-bg" id="ts-hero-bg" aria-hidden="true">
        <?php foreach ($bgSlides as $i => $slide): ?>
            <?php if ($slide['type'] === 'video'): ?>
                <video class="ts-bg-slide<?= $i === 0 ? ' is-on' : '' ?>" src="<?= $slide['src'] ?>" style="object-position:<?= $slide['pos'] ?>;" autoplay muted loop playsinline></video>
            <?php else: ?>
                <img class="ts-bg-slide<?= $i === 0 ? ' is-on' : '' ?>" src="<?= $slide['src'] ?>" style="object-position:<?= $slide['pos'] ?>;" alt="" decoding="async"<?= $i === 0 ? ' fetchpriority="high"' : '' ?>>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (\App\Services\PublicSite::isOn('hero_float_enabled')): ?>
    <?php
    // Which icons, how big and how faint all come from the CMS now;
    // the markup for each one lives in HeroIconLibrary so adding a
    // choice does not mean editing this page.
    $__icons = \App\Services\HeroIconLibrary::chosen(\App\Services\PublicSite::content('hero_float_icons'));
    $__iSize = (int) \App\Services\PublicSite::num('hero_float_size');
    $__iFade = (int) \App\Services\PublicSite::num('hero_float_opacity');
    ?>
    <div class="ts-float" aria-hidden="true" style="--ts-float-scale: <?= $__iSize / 100 ?>; --ts-float-fade: <?= $__iFade / 100 ?>;">
        <?php foreach ($__icons as $__k): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= \App\Services\HeroIconLibrary::ICONS[$__k][1] ?></svg>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (count($bgSlides) > 1): ?>
    <div class="ts-bg-dots" role="group" aria-label="<?= te('Photos from the classes') ?>">
        <?php foreach ($bgSlides as $i => $sl): ?><button type="button" aria-label="<?= te('Photo {n}', ['n' => $i + 1]) ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>"></button><?php endforeach; ?>
    </div>
    <script>
    (function () {
        var box = document.getElementById('ts-hero-bg');
        if (!box) { return; }
        var slides = box.querySelectorAll('.ts-bg-slide'), dots = document.querySelectorAll('.ts-bg-dots button'), cur = 0, timer = null,
            still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        function show(n) {
            slides[cur].classList.remove('is-on'); dots[cur].setAttribute('aria-current', 'false');
            cur = (n + slides.length) % slides.length;
            slides[cur].classList.add('is-on'); dots[cur].setAttribute('aria-current', 'true');
        }
        function play() { if (!still && !timer) { timer = setInterval(function () { show(cur + 1); }, <?= (int) \App\Services\PublicSite::num('hero_interval_ms') ?>); } }
        function stop() { clearInterval(timer); timer = null; }
        dots.forEach(function (d, i) { d.addEventListener('click', function () { stop(); show(i); play(); }); });
        document.addEventListener('visibilitychange', function () { document.hidden ? stop() : play(); });
        play();
    })();
    </script>
    <?php endif; ?>
    <svg class="ts-wave" viewBox="0 0 1440 70" preserveAspectRatio="none" aria-hidden="true"><path d="M0 40c240 30 480 30 720 10s480-30 720 0v20H0z"/></svg>
    <div class="ts-wrap ts-hero-grid">
        <div>
            <?php
            $adEvents = \App\Services\PublicSite::homeAds(6);

            // An ad the office designed here is a block with its own
            // colours and a button; the rotating strip is one line of
            // text. Putting a designed ad through the strip would throw
            // the design away, so they are drawn separately, above it.
            $adDesigned = [];
            foreach ($adEvents as $k => $adItem) {
                if (($adItem['design'] ?? '') !== '') {
                    $adDesigned[] = $adItem['design'];
                    unset($adEvents[$k]);
                }
            }
            $adEvents = array_values($adEvents);
            ?>

            <?php if ($adDesigned !== [] && \App\Services\PublicSite::isOn('show_seminar_banner')): ?>
                <style><?= \App\Services\SiteAdDesign::css() ?></style>
                <div class="ts-ad-designed" aria-label="<?= te('Announcements') ?>">
                    <?php foreach ($adDesigned as $adHtml): ?>
                        <?= $adHtml ?>
                    <?php endforeach; ?>
                </div>
                <style>.ts-ad-designed { display:grid; gap:.6rem; margin-bottom:.9rem; }</style>
            <?php endif; ?>

            <?php if ($adEvents !== []): ?>
            <?php if (\App\Services\PublicSite::isOn('show_seminar_banner')): ?>
            <div class="ts-ad" id="ts-ad" aria-label="<?= te('Announcements') ?>">
                <span class="ts-ad__label"><?= te('Upcoming') ?></span>
                <span class="ts-ad__rot" id="ts-ad-rot">
                    <?php foreach ($adEvents as $i => $ad): ?>
                        <a class="ts-ad__item<?= $i === 0 ? ' is-on' : '' ?>" href="<?= $e($ad['href']) ?>"<?= $i === 0 ? '' : ' aria-hidden="true" tabindex="-1"' ?>>
                            <?php if ($ad['thumb'] !== ''): ?><img class="ts-ad__thumb" src="<?= $e($ad['thumb']) ?>" alt="" loading="lazy" onerror="this.remove();"><?php endif; ?>
                            <span class="ts-ad__text"><?= $e($ad['title']) ?><?php if ($ad['subtitle'] !== ''): ?><small><?= $e($ad['subtitle']) ?></small><?php endif; ?></span>
                        </a>
                    <?php endforeach; ?>
                </span>
                <span class="ts-ad__go" aria-hidden="true">&rarr;</span>
            </div>
            <?php if (count($adEvents) > 1): ?>
            <script>
            (function () {
                var box = document.getElementById('ts-ad-rot');
                if (!box || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
                var items = box.querySelectorAll('.ts-ad__item'), cur = 0, timer = null;
                function next() {
                    items[cur].classList.remove('is-on'); items[cur].setAttribute('aria-hidden', 'true'); items[cur].setAttribute('tabindex', '-1');
                    cur = (cur + 1) % items.length;
                    items[cur].classList.add('is-on'); items[cur].removeAttribute('aria-hidden'); items[cur].removeAttribute('tabindex');
                }
                function play() { if (!timer) { timer = setInterval(next, 4000); } }
                function stop() { clearInterval(timer); timer = null; }
                box.parentNode.addEventListener('mouseenter', stop);
                box.parentNode.addEventListener('mouseleave', play);
                box.parentNode.addEventListener('focusin', stop);
                box.parentNode.addEventListener('focusout', play);
                document.addEventListener('visibilitychange', function () { document.hidden ? stop() : play(); });
                play();
            })();
            </script>
            <?php endif; ?>
            <?php endif; ?>
            <?php endif; ?>
            <div class="ts-eyebrow"><?= te('Science classes') ?> &middot; <?= te('Grades {r}', ['r' => \App\Services\PublicSite::gradeRange()]) ?></div>
            <?php $teacherFirst = $teacher ? trim((string) strtok($teacher, ' ')) : null; ?>
            <h1>
                <?php if ($teacher): ?>
                    <?php if ($isSi): ?>
                        <span class="ts-name"><?= $e($teacher) ?> <span class="ts-honor"><?= te('Miss') ?></span></span>
                        <span><?= te('with Science') ?></span>
                    <?php else: ?>
                        <span><?= te('Learn Science with') ?></span>
                        <span class="ts-name"><?= $e($teacher) ?> <span class="ts-honor"><?= te('Miss') ?></span></span>
                    <?php endif; ?>
                <?php else: ?>
                    <?= te('Science classes for') ?> <span class="ts-name"><?= te('Grades 6 to 11') ?></span>
                <?php endif; ?>
            </h1>
            <?php if (\App\Services\PublicSite::isOn('show_hero_text')): ?>
            <div class="ts-rich"><?= \App\Services\PublicSite::content('home_hero_text', ['r' => \App\Services\PublicSite::gradeRange()]) ?></div>
            <?php endif; ?>
            <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
                <a href="#inquiry" class="ts-btn ts-btn--yellow"><?= $sc('home_hero_cta_1') ?></a>
                <a href="<?= BASE_URL ?>/timetable" class="ts-btn ts-btn--ghost"><?= $sc('home_hero_cta_2') ?></a>
            </div>
            <div class="ts-chips">
                <a class="ts-chip" style="color:#fff; text-decoration:none;" href="<?= BASE_URL ?>/login"><svg width="15" height="15" style="vertical-align:-2px; margin-right:.3rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><?= te('Student login') ?></a>
                <a class="ts-chip" style="color:#fff; text-decoration:none;" href="<?= BASE_URL ?>/login"><svg width="15" height="15" style="vertical-align:-2px; margin-right:.3rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="7" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21v-1a6 6 0 0 1 12 0v1"/><path d="M15 21v-1a4 4 0 0 1 6-3.5"/></svg><?= te('Parent login') ?></a>
            </div>
        </div>
        <?php if ($teacher): ?>
        <div class="ts-photo">
            <svg class="ts-photo__ring" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="47" fill="none" stroke="currentColor" stroke-width="1.2" stroke-dasharray="2 6" stroke-linecap="round"/></svg>
            <?php
            $teacherPhoto = \App\Services\PublicSite::content('teacher_photo_image');
            $teacherIsVideo = $teacherPhoto !== '' && \App\Services\SiteContentService::isVideoPath($teacherPhoto);
            ?>
            <div class="ts-photo__frame">
                <?php if ($teacherIsVideo): ?>
                    <video src="<?= BASE_URL . $e($teacherPhoto) ?>" width="1600" height="2000" autoplay muted loop playsinline aria-label="<?= $e(t('{name} teaching a Science lesson', ['name' => $teacher])) ?>"></video>
                <?php else: ?>
                    <img src="<?= $teacherPhoto !== '' ? BASE_URL . $e($teacherPhoto) : BASE_URL . '/assets/img/teacher-smartboard.jpg' ?>" width="1600" height="2000" alt="<?= $e(t('{name} teaching a Science lesson', ['name' => $teacher])) ?>" fetchpriority="high">
                <?php endif; ?>
            </div>
            <div class="ts-photo__badge"><?= te('Grades {r}', ['r' => \App\Services\PublicSite::gradeRange()]) ?></div>
            <div class="ts-photo__tag">
                <div class="ts-photo__dot"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 10 12 5 2 10l10 5z"/><path d="M6 12v5c3 2.5 9 2.5 12 0v-5"/></svg></div>
                <div><strong><?= $e($teacher) ?> <span class="ts-honor ts-honor--dark"><?= te('Miss') ?></span></strong><span><?= te('Science teacher') ?></span></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php $events = \App\Services\PublicSite::upcomingSeminars(6); ?>
<?php if ($events !== []): ?>
<section class="ts-section ts-events" id="events" aria-labelledby="events-title">
    <div class="ts-wrap">
        <div class="ts-kicker">&#128227; <?= te("What's on") ?></div>
        <h2 id="events-title"><?= te('Upcoming seminars & events') ?></h2><div class="ts-title-bar"></div>
        <div class="ts-events__grid<?= count($events) === 1 ? ' ts-events__grid--one' : '' ?>">
            <?php foreach ($events as $ev):
                $poster = \App\Services\SeminarService::coverSrc($ev['cover_image_url'] ?? null);
                $evTs = strtotime((string) $ev['event_date']);
                $where = $ev['target_scope'] === 'all' || ($ev['target_scope'] === 'grade' && empty($ev['branch_name']))
                    ? t('All branches') : (string) $ev['branch_name'];
                $forGrade = $ev['target_scope'] === 'grade' ? (string) $ev['grade'] : ($ev['target_scope'] === 'class' ? (string) $ev['class_grade'] : '');
                $desc = trim((string) ($ev['description'] ?? ''));
            ?>
            <article class="ts-event ts-reveal">
                <div class="ts-event__media<?= $poster === '' ? ' ts-event__media--plain' : '' ?>">
                    <?php if ($poster !== ''): ?>
                        <img src="<?= $e($poster) ?>" alt="<?= $e($ev['title']) ?>" loading="lazy" onerror="var p=this.parentNode; this.remove(); if (p) { p.classList.add('ts-event__media--plain'); }">
                    <?php endif; ?>
                    <svg class="ts-event__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3h6"/><path d="M10 3v6L4.5 18.5A2 2 0 0 0 6.2 21.5h11.6a2 2 0 0 0 1.7-3L14 9V3"/><path d="M7.5 15h9"/></svg>
                    <div class="ts-event__date" aria-hidden="true">
                        <span class="ts-event__day"><?= $e(date('j', $evTs)) ?></span>
                        <span class="ts-event__mon"><?= $e(lang_date('M', (string) $ev['event_date'])) ?></span>
                    </div>
                    <?php if ($forGrade !== ''): ?><span class="ts-event__tag"><?= $e(t($forGrade)) ?></span><?php endif; ?>
                </div>
                <div class="ts-event__body">
                    <h3><?= $e($ev['title']) ?></h3>
                    <ul class="ts-event__meta">
                        <li>&#128197; <?= $e(lang_date('l, j F Y', (string) $ev['event_date'])) ?><?php if (!empty($ev['start_time'])): ?> &middot; <?= $e(site_time($ev['start_time'])) ?><?php if (!empty($ev['end_time'])): ?> &ndash; <?= $e(site_time($ev['end_time'])) ?><?php endif; ?><?php endif; ?></li>
                        <?php if (!empty($ev['venue'])): ?><li>&#128205; <?= $e($ev['venue']) ?></li><?php endif; ?>
                        <li>&#127979; <?= $e($where) ?></li>
                    </ul>
                    <?php if ($desc !== ''): ?><p class="ts-event__desc"><?= $e($desc) ?></p><?php endif; ?>
                    <div class="ts-event__actions">
                        <a href="#inquiry" class="ts-btn ts-btn--blue"><?= te('Ask about this') ?></a>
                        <?php if ((int) $ev['registration_required'] === 1): ?>
                            <a href="<?= BASE_URL ?>/login" class="ts-btn ts-btn--line"><?= te('Students: register in the portal') ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($teacher): ?>
<section class="ts-section" id="teacher">
    <div class="ts-wrap ts-teacher ts-reveal">
        <div class="ts-teacher__img">
            <img src="<?= BASE_URL ?>/assets/img/teacher-classroom.jpg" width="2048" height="1365" loading="lazy" alt="<?= $e(t('{name} with her students in class', ['name' => $teacher])) ?>">
            <?php if ($facts['branches'] > 0): ?>
            <div class="ts-teacher__badge"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.3-7-12a7 7 0 0 1 14 0c0 5.7-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/></svg><?= (int) $facts['branches'] ?> <?= te('branches') ?></div>
            <?php endif; ?>
        </div>
        <div>
            <div class="ts-kicker"><?= $sc('home_teacher_kicker') ?></div>
            <h2><?= $e($teacher) ?></h2>
            <div class="ts-lead ts-rich" style="margin-bottom:.4rem;"><?= $scr('home_teacher_text', ['name' => $teacher, 'r' => \App\Services\PublicSite::gradeRange(), 'n' => (int) $facts['branches']]) ?></div>
            <ul class="ts-ticks">
                <?php foreach (['home_teacher_point_1', 'home_teacher_point_2', 'home_teacher_point_3', 'home_teacher_point_4'] as $pointKey): ?>
                    <?php $point = $sc($pointKey); ?>
                    <?php if ($point !== ''): ?><li><?= $point ?></li><?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <a href="<?= BASE_URL ?>/about" class="ts-btn ts-btn--line"><?= te('About the classes') ?> &rarr;</a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (\App\Services\PublicSite::isOn('show_how_section')): ?>
<section class="ts-section ts-section--alt" id="how">
    <div class="ts-wrap">
        <div class="ts-kicker"><?= te('How it works') ?></div>
        <h2><?= $sc('home_how_title') ?></h2><div class="ts-title-bar"></div>
        <p class="ts-lead"><?= $sc('home_how_text') ?></p>
        <div class="ts-grid">
            <?php
            // The cards, their wording and whether each is shown all come
            // from the CMS now; the icons live in HomeFeatureCards. A card
            // listed as conditional stays hidden unless the institute
            // really does that thing, however the switch is set -- the
            // Online classes card must not advertise classes nobody runs.
            $__conds = ['hasOnline' => !empty($hasOnline)];
            foreach (\App\Services\HomeFeatureCards::CARDS as $__ck => $__card):
                $__cond = \App\Services\HomeFeatureCards::CONDITIONAL[$__ck] ?? null;
                if ($__cond !== null && empty($__conds[$__cond])) { continue; }
                if (!\App\Services\PublicSite::isOn(\App\Services\HomeFeatureCards::showKey($__ck))) { continue; }
            ?>
                <div class="ts-card ts-reveal">
                    <div class="ts-icon ts-icon--<?= $e($__card[0]) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $__card[3] ?></svg>
                    </div>
                    <h3><?= $sc(\App\Services\HomeFeatureCards::titleKey($__ck)) ?></h3>
                    <p class="ts-muted"><?= $sc(\App\Services\HomeFeatureCards::textKey($__ck)) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="ts-section" id="join-steps">
    <div class="ts-wrap">
        <div class="ts-kicker"><?= te('Joining') ?></div>
        <h2><?= $sc('home_join_title') ?></h2><div class="ts-title-bar"></div>
        <div class="ts-steps" style="margin-top:1.8rem;">
            <?php foreach ([1, 2, 3, 4] as $stepNo): ?>
                <?php if (!\App\Services\PublicSite::isOn('show_step_' . $stepNo)) { continue; } ?>
                <?php $stepTitle = $sc('home_step_' . $stepNo . '_title'); ?>
                <?php $stepText = $sc('home_step_' . $stepNo . '_text'); ?>
                <?php if ($stepTitle === '' && $stepText === '') { continue; } ?>
                <div class="ts-step ts-reveal">
                    <?php if ($stepTitle !== ''): ?><h3><?= $stepTitle ?></h3><?php endif; ?>
                    <?php if ($stepText !== ''): ?><p class="ts-muted"><?= $stepText ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<?php $posters = \App\Services\PublicSite::posterAds(4); ?>
<?php if ($posters !== []): ?>
<section class="ts-section ts-posters" id="posters" aria-labelledby="posters-title">
    <div class="ts-wrap">
        <h2 id="posters-title"><?= $sc('home_posters_title') ?></h2><div class="ts-title-bar"></div>
        <?php $postersText = $sc('home_posters_text'); ?>
        <?php if ($postersText !== ''): ?><p class="ts-lead"><?= $postersText ?></p><?php endif; ?>
        <div class="ts-posters__grid<?= count($posters) === 1 ? ' ts-posters__grid--one' : '' ?>">
            <?php foreach ($posters as $poster): ?>
                <?php $tag = $poster['href'] !== null ? 'a' : 'div'; ?>
                <<?= $tag ?> class="ts-poster ts-reveal"<?= $poster['href'] !== null ? ' href="' . $e($poster['href']) . '" target="_blank" rel="noopener noreferrer"' : '' ?>>
                    <img src="<?= $e($poster['image']) ?>" alt="<?= $e($poster['title']) ?>" loading="lazy">
                    <span class="ts-poster__cap"><?= $e($poster['title']) ?></span>
                </<?= $tag ?>>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($branches !== []): ?>
<section class="ts-section ts-section--alt">
    <div class="ts-wrap">
        <h2><?= $sc('home_branches_title') ?></h2><div class="ts-title-bar"></div>
        <p class="ts-lead"><?= $sc('home_branches_text') ?></p>
        <div class="ts-grid">
            <?php foreach ($branches as $b): ?>
                <div class="ts-card ts-branch ts-reveal">
                    <div class="ts-branch__top"><span>&#128205; <?= te('Branch') ?></span><h3><?= te($b['name']) ?></h3></div>
                    <div class="ts-branch__body">
                        <?php if (!empty($b['location'])): ?><div class="ts-muted"><?= te($b['location']) ?></div><?php endif; ?>
                        <div class="ts-muted"><?= te('{n} classes', ['n' => (int) $b['class_count']]) ?></div>
                        <div class="ts-branch__actions">
                            <?php if (!empty($b['phone1'])): ?><a href="<?= $e(\App\Services\PublicSite::telHref($b['phone1'])) ?>" class="ts-btn ts-btn--blue" style="padding:.5rem 1rem; font-size:.85rem;">&#128222; <?= te('Call') ?></a><?php endif; ?>
                            <a href="<?= BASE_URL ?>/branches/<?= (int) $b['id'] ?>" class="ts-btn ts-btn--line" style="padding:.5rem 1rem; font-size:.85rem;"><?= te('See timetable') ?></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
// A short teaser only -- the full grade-by-grade timetable with tabs now
// lives on its own page (PublicController::classesLanding(), /our-classes)
// so it isn't shown twice. This counts real published classes/grades so
// the numbers are never invented.
$__classCount = count($classes);
$__gradeCount = count($byGrade);
?>
<section class="ts-section ts-section--alt" id="classes">
    <div class="ts-wrap">
        <div class="ts-cta">
            <div>
                <h2><?= $sc('home_classes_title') ?></h2>
                <p>
                    <?= $sc('home_classes_text') ?>
                    <?php if ($__classCount > 0): ?>
                        <br><span style="opacity:.85; font-size:.9rem;">
                            <?= te('{classes} classes across {grades} grades, {branches} branches.', [
                                'classes'  => $__classCount,
                                'grades'   => $__gradeCount,
                                'branches' => count($branches),
                            ]) ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?= BASE_URL ?>/our-classes" class="ts-btn ts-btn--yellow"><?= te('View all classes') ?> &rarr;</a>
        </div>
    </div>
</section>

<?php if (!empty($seminars)): ?>
<section class="ts-section" id="seminars">
    <div class="ts-wrap">
        <h2><?= te('Upcoming Seminars') ?></h2><div class="ts-title-bar"></div>
        <p class="ts-lead"><?= te('Special seminars and workshops for students and parents') ?></p>
        <div class="ts-grid">
            <?php foreach ($seminars as $sem): ?>
                <?php $cover = \App\Services\SeminarService::coverSrc($sem['cover_image_url'] ?? null); ?>
                <a href="<?= BASE_URL ?>/seminar/<?= (int) $sem['id'] ?>" class="ts-card ts-reveal" style="text-decoration:none; color:inherit; display:flex; flex-direction:column;">
                    <?php if ($cover): ?>
                        <img src="<?= htmlspecialchars($cover, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($sem['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" style="width:100%; border-radius:12px 12px 0 0; aspect-ratio:16/9; object-fit:cover;">
                    <?php else: ?>
                        <div style="width:100%; aspect-ratio:16/9; background:linear-gradient(135deg,#1D4ED8,#172554); border-radius:12px 12px 0 0; display:grid; place-items:center; color:#fff; font-size:2.5rem;">&#127891;</div>
                    <?php endif; ?>
                    <div style="padding:1rem; flex:1; display:flex; flex-direction:column;">
                        <h3 style="margin:0 0 .4rem; font-size:1.05rem; color:#1E293B;"><?= htmlspecialchars($sem['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></h3>
                        <?php if (!empty($sem['event_date'])): ?>
                            <div style="font-size:.85rem; color:#64748B; margin-bottom:.3rem;">&#128197; <?= date('l, j M Y', strtotime($sem['event_date'])) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($sem['venue'])): ?>
                            <div style="font-size:.85rem; color:#64748B;">&#128205; <?= htmlspecialchars($sem['venue'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <div style="margin-top:auto; padding-top:.8rem;">
                            <span style="color:#1D4ED8; font-weight:600; font-size:.9rem;"><?= te('View details') ?> &rarr;</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="ts-section">
    <div class="ts-wrap ts-faq">
        <h2><?= $sc('home_faq_title') ?></h2><div class="ts-title-bar"></div>
        <?php foreach ([1, 2, 3, 4, 5] as $qNo): ?>
            <?php $question = $sc('home_faq_' . $qNo . '_q'); ?>
            <?php $answer = $scr('home_faq_' . $qNo . '_a'); ?>
            <?php if ($question === '' || trim(strip_tags($answer)) === '') { continue; } ?>
            <details>
                <summary><?= $question ?></summary>
                <div class="ts-muted ts-rich"><?= $answer ?></div>
            </details>
        <?php endforeach; ?>
    </div>
</section>

<section class="ts-section ts-section--alt" id="inquiry">
    <div class="ts-wrap">
        <div class="ts-join ts-reveal">
            <div class="ts-join__side">
                <?php if ($teacher): ?><div class="ts-join__avatar"><img src="<?= BASE_URL ?>/assets/img/teacher-smartboard.jpg" width="96" height="96" loading="lazy" alt="<?= $e($teacher) ?>"></div><?php endif; ?>
                <div class="ts-kicker" style="color:#FDE68A;"><?= te('Join a class') ?></div>
                <h2 style="margin:0 0 .5rem;"><?= $sc('home_inquiry_title') ?></h2>
                <p style="opacity:.9;"><?= $sc('home_inquiry_text') ?></p>
                <ul class="ts-ticks">
                    <?php foreach (['home_inquiry_point_1', 'home_inquiry_point_2', 'home_inquiry_point_3'] as $pointKey): ?>
                        <?php $point = $sc($pointKey); ?>
                        <?php if ($point !== ''): ?><li><?= $point ?></li><?php endif; ?>
                    <?php endforeach; ?>
                </ul>
                <?php foreach ($branches as $b): if (!empty($b['phone1'])): ?>
                    <div style="font-size:.9rem; margin-top:.3rem;"><strong><?= te($b['name']) ?></strong> &middot; <a href="<?= $e(\App\Services\PublicSite::telHref($b['phone1'])) ?>"><?= $e($b['phone1']) ?></a></div>
                <?php endif; endforeach; ?>
            </div>
            <div class="ts-join__form">
        <?php if ($branches === []): ?>
            <p class="ts-muted"><?= te('Online inquiries open soon. Please contact us through the Contact page.') ?></p>
        <?php else: ?>
        <form method="POST" action="<?= BASE_URL ?>/inquiry" class="ts-card" style="position:relative;">
            <?= csrfField() ?>
            <input type="hidden" name="started_at" value="<?= time() ?>">
            <div class="ts-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="ts-form">
                <div><label for="iq-parent"><?= te('Your name') ?> *</label><input id="iq-parent" name="parent_name" maxlength="255" required autocomplete="name"></div>
                <div><label for="iq-phone"><?= te('Phone number') ?> *</label><input id="iq-phone" name="phone" inputmode="tel" required placeholder="07X XXX XXXX" autocomplete="tel"></div>
                <div><label for="iq-email"><?= te('Email (optional)') ?></label><input id="iq-email" type="email" name="email" maxlength="255" autocomplete="email"></div>
                <div><label for="iq-student"><?= te("Student's name") ?></label><input id="iq-student" name="student_name" maxlength="255"></div>
                <div><label for="iq-grade"><?= te('Grade') ?> *</label>
                    <select id="iq-grade" name="grade" required><option value=""><?= te('Choose') ?></option>
                        <?php foreach (range(6, 11) as $g): ?><option value="<?= $g ?>" <?= $pickGrade === (string) $g ? 'selected' : '' ?>><?= te('Grade {n}', ['n' => $g]) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="iq-branch"><?= te('Branch') ?> *</label>
                    <select id="iq-branch" name="branch_id" required><option value=""><?= te('Choose') ?></option>
                        <?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= $pickBranch === (int) $b['id'] ? 'selected' : '' ?>><?= te($b['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div style="grid-column:1/-1;"><label for="iq-msg"><?= te('Anything we should know? (optional)') ?></label><textarea id="iq-msg" name="message" rows="3" maxlength="2000"></textarea></div>
            </div>
            <p class="ts-muted" style="margin:.8rem 0;"><?= te('We use these details only to contact you about classes.') ?></p>
            <button type="submit" class="ts-btn ts-btn--blue"><?= te('Send inquiry') ?></button>
        </form>
        <?php endif; ?>
    
            </div>
        </div>
    </div>
</section>

<?= "" ?><?php $__p = ''; foreach ($branches as $b) { if (!empty($b['phone1'])) { $__p = (string) $b['phone1']; break; } } ?>
<div class="ts-mbar">
    <a href="#inquiry" class="ts-btn ts-btn--yellow" style="padding:.65rem 1rem;"><?= te('Join a class') ?></a>
    <?php if ($__p !== ''): ?><a href="<?= $e(\App\Services\PublicSite::telHref($__p)) ?>" class="ts-btn ts-btn--blue" style="padding:.65rem 1rem;">&#128222; <?= te('Call') ?></a><?php endif; ?>
</div>
<noscript><style>.ts-reveal { opacity: 1; transform: none; }</style></noscript>
