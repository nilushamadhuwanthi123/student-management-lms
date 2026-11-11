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
$bgSlides = [
    ['teacher-board.jpg', '70% 35%'],
    ['students-experiment.jpg', '50% 35%'],
    ['science-lab.jpg', '60% 45%'],
    ['students-exam.jpg', '50% 45%'],
];
$bgSlides = array_values(array_filter($bgSlides, static fn ($sl) => is_file(BASE_PATH . '/public/assets/img/' . $sl[0])));
?>
<section class="ts-hero">
    <?php if ($bgSlides !== []): ?>
    <div class="ts-hero-bg" id="ts-hero-bg" aria-hidden="true">
        <?php foreach ($bgSlides as $i => [$file, $pos]): ?>
            <img class="ts-bg-slide<?= $i === 0 ? ' is-on' : '' ?>" src="<?= BASE_URL ?>/assets/img/<?= $e($file) ?>" style="object-position:<?= $e($pos) ?>;" alt="" decoding="async"<?= $i === 0 ? ' fetchpriority="high"' : '' ?>>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="ts-float" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="1.6"/><ellipse cx="12" cy="12" rx="10" ry="4"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(120 12 12)"/></svg><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3h6"/><path d="M10 3v6L4.5 18.5A2 2 0 0 0 6.2 21.5h11.6a2 2 0 0 0 1.7-3L14 9V3"/><path d="M7.5 15h9"/></svg><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3c0 6 10 6 10 12s-10 6-10 6"/><path d="M17 3c0 6-10 6-10 12s10 6 10 6"/><path d="M8 7h8M8 17h8"/></svg><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18h8"/><path d="M3 22h18"/><path d="M14 22a7 7 0 1 0 0-14h-1"/><path d="M9 14h2"/><path d="M9 12a2 2 0 0 1-2-2V6h6v4a2 2 0 0 1-2 2z"/><path d="M12 6V3a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v3"/></svg><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v6"/><path d="m4.9 4.9 4.2 4.2"/><path d="M2 12h6"/><circle cx="12" cy="12" r="4"/><path d="M16 12h6"/><path d="m14.9 14.9 4.2 4.2"/><path d="M12 16v6"/></svg></div>
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
        function play() { if (!still && !timer) { timer = setInterval(function () { show(cur + 1); }, 5000); } }
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
            <div class="ts-eyebrow"><?= te('Science classes') ?> &middot; <?= te('Grades {r}', ['r' => \App\Services\PublicSite::gradeRange()]) ?></div>
            <h1>
                <?php if ($teacher): ?>
                    <?php if ($isSi): ?>
                        <span class="ts-name"><?= $e($teacher) ?> <?= te('teacher (honorific)') ?></span>
                        <span><?= te('with Science') ?></span>
                    <?php else: ?>
                        <span><?= te('Learn Science with') ?></span>
                        <span class="ts-name"><?= $e($teacher) ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <?= te('Science classes for') ?> <span class="ts-name"><?= te('Grades 6 to 11') ?></span>
                <?php endif; ?>
            </h1>
            <div class="ts-rich"><?= \App\Services\PublicSite::content('home_hero_text', ['r' => \App\Services\PublicSite::gradeRange()]) ?></div>
            <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
                <a href="#inquiry" class="ts-btn ts-btn--yellow"><?= te('Join a class') ?></a>
                <a href="<?= BASE_URL ?>/timetable" class="ts-btn ts-btn--ghost"><?= te('See the timetable') ?></a>
            </div>
            <div class="ts-chips">
                <a class="ts-chip" style="color:#fff; text-decoration:none;" href="<?= BASE_URL ?>/login"><svg width="15" height="15" style="vertical-align:-2px; margin-right:.3rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><?= te('Student login') ?></a>
                <a class="ts-chip" style="color:#fff; text-decoration:none;" href="<?= BASE_URL ?>/login"><svg width="15" height="15" style="vertical-align:-2px; margin-right:.3rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="7" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21v-1a6 6 0 0 1 12 0v1"/><path d="M15 21v-1a4 4 0 0 1 6-3.5"/></svg><?= te('Parent login') ?></a>
            </div>
        </div>
        <?php if ($teacher): ?>
        <div class="ts-photo">
            <?php $teacherPhoto = \App\Services\PublicSite::content('teacher_photo_image'); ?>
            <div class="ts-photo__frame">
                <img src="<?= $teacherPhoto !== '' ? BASE_URL . $e($teacherPhoto) : BASE_URL . '/assets/img/teacher-smartboard.jpg' ?>" width="1600" height="2000" alt="<?= $e(t('{name} teaching a Science lesson', ['name' => $teacher])) ?>" fetchpriority="high">
            </div>
            <div class="ts-photo__badge"><?= te('Grades {r}', ['r' => \App\Services\PublicSite::gradeRange()]) ?></div>
            <div class="ts-photo__tag">
                <div class="ts-photo__dot"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 10 12 5 2 10l10 5z"/><path d="M6 12v5c3 2.5 9 2.5 12 0v-5"/></svg></div>
                <div><strong><?= $e($teacher) ?></strong><span><?= te('Science teacher') ?></span></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="ts-stats">
    <div class="ts-wrap">
        <div class="ts-stats__grid">
            <?php if ($facts['branches'] > 0): ?><div class="ts-stat"><div class="ts-stat__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.3-7-12a7 7 0 0 1 14 0c0 5.7-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/></svg></div><div><div class="ts-stat__value"><?= (int) $facts['branches'] ?></div><div class="ts-stat__label"><?= te('branches') ?></div></div></div><?php endif; ?>
            <div class="ts-stat"><div class="ts-stat__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 10 12 5 2 10l10 5z"/><path d="M6 12v5c3 2.5 9 2.5 12 0v-5"/></svg></div><div><div class="ts-stat__value"><?= $e(\App\Services\PublicSite::gradeRange()) ?></div><div class="ts-stat__label"><?= te('grades') ?></div></div></div>
            <?php if ($facts['classes'] > 0): ?><div class="ts-stat"><div class="ts-stat__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div><div><div class="ts-stat__value"><?= (int) $facts['classes'] ?></div><div class="ts-stat__label"><?= te('weekly classes') ?></div></div></div><?php endif; ?>
            <?php if ($mediumLabel !== ''): ?><div class="ts-stat"><div class="ts-stat__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M8 7h8M8 11h6"/></svg></div><div><div class="ts-stat__value" style="font-size:1.05rem;"><?= $e($mediumLabel) ?></div><div class="ts-stat__label"><?= te('medium') ?></div></div></div><?php endif; ?>
        </div>
    </div>
</section>

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
            <div class="ts-kicker"><?= te('Your teacher') ?></div>
            <h2><?= $e($teacher) ?></h2>
            <p class="ts-lead" style="margin-bottom:.4rem;"><?= te('{name} teaches Science for Grades {r} at {n} branches, following the school syllabus step by step.', ['name' => $teacher, 'r' => \App\Services\PublicSite::gradeRange(), 'n' => (int) $facts['branches']]) ?></p>
            <ul class="ts-ticks">
                <li><?= te('Lessons with smart-board presentations') ?></li>
                <li><?= te('Recordings and notes for every month, to revise at home') ?></li>
                <li><?= te('Quizzes, homework and past papers, marked with feedback') ?></li>
                <li><?= te('Parents can follow marks, attendance and fees in the portal') ?></li>
            </ul>
            <a href="<?= BASE_URL ?>/about" class="ts-btn ts-btn--line"><?= te('About the classes') ?> &rarr;</a>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="ts-section ts-section--alt" id="how">
    <div class="ts-wrap">
        <div class="ts-kicker"><?= te('How it works') ?></div>
        <h2><?= te('How the classes work') ?></h2><div class="ts-title-bar"></div>
        <p class="ts-lead"><?= te('Each grade meets every week at a branch. Between classes, students keep learning from home.') ?></p>
        <div class="ts-grid">
            <div class="ts-card ts-reveal"><div class="ts-icon ts-icon--c1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/></svg></div><h3><?= te('Weekly classes') ?></h3><p class="ts-muted"><?= te('Regular classes by grade at each branch, following the school Science syllabus.') ?></p></div>
            <div class="ts-card ts-reveal"><div class="ts-icon ts-icon--c2"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m10 9 5 3-5 3z"/></svg></div><h3><?= te('Recordings and notes') ?></h3><p class="ts-muted"><?= te('Missed a class or need a second look? Recordings, notes and videos are posted for each month.') ?></p></div>
            <div class="ts-card ts-reveal"><div class="ts-icon ts-icon--c3"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></div><h3><?= te('Quizzes and homework') ?></h3><p class="ts-muted"><?= te('Timed online quizzes with marks, and homework the teacher marks with feedback.') ?></p></div>
            <div class="ts-card ts-reveal"><div class="ts-icon ts-icon--c4"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></svg></div><h3><?= te('Past papers') ?></h3><p class="ts-muted"><?= te('Term test and model papers for each grade, with marking schemes released after the class has tried them.') ?></p></div>
            <div class="ts-card ts-reveal"><div class="ts-icon ts-icon--c5"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="7" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21v-1a6 6 0 0 1 12 0v1"/><path d="M15 21v-1a4 4 0 0 1 6-3.5"/></svg></div><h3><?= te('Parent portal') ?></h3><p class="ts-muted"><?= te("Parents see marks, attendance, progress, fees and receipts for their child.") ?></p></div>
            <div class="ts-card ts-reveal"><div class="ts-icon ts-icon--c6"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg></div><h3><?= te('Absence alerts') ?></h3><p class="ts-muted"><?= te('If a child is marked absent, the family gets an alert in the parent portal.') ?></p></div>
            <?php if ($hasOnline): ?><div class="ts-card ts-reveal"><div class="ts-icon ts-icon--c7"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18"/></svg></div><h3><?= te('Online classes') ?></h3><p class="ts-muted"><?= te('Some classes also run online. The joining link is given privately to enrolled students.') ?></p></div><?php endif; ?>
            <div class="ts-card ts-reveal"><div class="ts-icon ts-icon--c1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h9"/><path d="M8.5 3v2c0 5-3 8-5 9"/><path d="M6 9c1 2.5 3 4 6 5"/><path d="m13 20 4-9 4 9"/><path d="M14.5 17h5"/></svg></div><h3><?= te('Sinhala or English') ?></h3><p class="ts-muted"><?= te('Students and parents can switch the portal between Sinhala and English.') ?></p></div>
        </div>
    </div>
</section>

<section class="ts-section" id="join-steps">
    <div class="ts-wrap">
        <div class="ts-kicker"><?= te('Joining') ?></div>
        <h2><?= te('Four steps to join') ?></h2><div class="ts-title-bar"></div>
        <div class="ts-steps" style="margin-top:1.8rem;">
            <div class="ts-step ts-reveal"><h3><?= te('Send an inquiry') ?></h3><p class="ts-muted"><?= te('Fill in the form below or call the branch nearest to you.') ?></p></div>
            <div class="ts-step ts-reveal"><h3><?= te('Register at the office') ?></h3><p class="ts-muted"><?= te('The office registers your child and gives the student ID and a first password.') ?></p></div>
            <div class="ts-step ts-reveal"><h3><?= te('Come to class every week') ?></h3><p class="ts-muted"><?= te('Weekly class at the branch, with notes, recordings and quizzes online.') ?></p></div>
            <div class="ts-step ts-reveal"><h3><?= te('Follow the progress') ?></h3><p class="ts-muted"><?= te('Parents log in with their phone number to see marks, attendance and fees.') ?></p></div>
        </div>
    </div>
</section>


<?php if ($branches !== []): ?>
<section class="ts-section ts-section--alt">
    <div class="ts-wrap">
        <h2><?= te('Our branches') ?></h2><div class="ts-title-bar"></div>
        <p class="ts-lead"><?= te('Choose the branch nearest to you.') ?></p>
        <div class="ts-grid">
            <?php foreach ($branches as $b): ?>
                <div class="ts-card ts-branch ts-reveal">
                    <div class="ts-branch__top"><span>&#128205; <?= te('Branch') ?></span><h3><?= $e($b['name']) ?></h3></div>
                    <div class="ts-branch__body">
                        <?php if (!empty($b['location'])): ?><div class="ts-muted"><?= $e($b['location']) ?></div><?php endif; ?>
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

<section class="ts-section ts-section--alt" id="classes">
    <div class="ts-wrap">
        <h2><?= te('Classes by grade') ?></h2><div class="ts-title-bar"></div>
        <p class="ts-lead"><?= te('Days and times for every grade. Ask the branch for a seat.') ?> <a href="<?= BASE_URL ?>/timetable"><?= te('Full timetable') ?> &rarr;</a></p>
        <?php if ($byGrade === []): ?>
            <p class="ts-muted"><?= te('The timetable will appear here soon. Please send an inquiry below.') ?></p>
        <?php endif; ?>
        <?php if (count($byGrade) > 1): ?>
        <div class="ts-tabs" role="tablist" aria-label="<?= te('Grade') ?>">
            <?php $first = true; foreach ($byGrade as $g => $list): ?>
                <button type="button" class="ts-tab ts-g<?= (int) $g ?>" role="tab" id="tab-g<?= (int) $g ?>" aria-controls="panel-g<?= (int) $g ?>" aria-selected="<?= $first ? 'true' : 'false' ?>"><?= te('Grade {n}', ['n' => $g]) ?></button>
            <?php $first = false; endforeach; ?>
        </div>
        <?php endif; ?>
        <?php $first = true; foreach ($byGrade as $g => $list): ?>
            <div class="ts-grade-panel ts-g<?= (int) $g ?>" role="tabpanel" id="panel-g<?= (int) $g ?>" aria-labelledby="tab-g<?= (int) $g ?>" data-first="<?= $first ? '1' : '0' ?>">
                <h3 style="margin:1rem 0 .7rem;" class="ts-panel-title"><?= te('Grade {n}', ['n' => $g]) ?></h3>
                <div class="ts-grid"><?php foreach ($list as $c) { require __DIR__ . '/_class-card.php'; } ?></div>
            </div>
        <?php $first = false; endforeach; ?>
        <script>
        (function () {
            var tabs = document.querySelectorAll('.ts-tab');
            if (!tabs.length) { return; }
            var panels = document.querySelectorAll('.ts-grade-panel');
            panels.forEach(function (p) { p.hidden = p.getAttribute('data-first') !== '1'; var t = p.querySelector('.ts-panel-title'); if (t) { t.hidden = true; } });
            tabs.forEach(function (t) {
                t.addEventListener('click', function () {
                    tabs.forEach(function (x) { x.setAttribute('aria-selected', x === t ? 'true' : 'false'); });
                    panels.forEach(function (p) { p.hidden = p.id !== t.getAttribute('aria-controls'); });
                });
            });
        })();
        </script>
    </div>
</section>

<section class="ts-section">
    <div class="ts-wrap ts-faq">
        <h2><?= te('Questions parents ask') ?></h2><div class="ts-title-bar"></div>
        <details><summary><?= te('How do I join a class?') ?></summary><p class="ts-muted"><?= te('Send the inquiry form below, or call the branch. The office registers the student and gives you the student ID for the portal.') ?></p></details>
        <details><summary><?= te('How are fees paid?') ?></summary><p class="ts-muted"><?= te('Fees are monthly. Pay at the branch office, or pay by bank and upload the slip in the portal. The office checks every slip before the payment is recorded, and a receipt is then available to download.') ?></p></details>
        <details><summary><?= te('What happens if a student misses a class?') ?></summary><p class="ts-muted"><?= te('Recordings and notes for the month are posted in the portal, so the lesson can be caught up at home.') ?></p></details>
        <details><summary><?= te('Can parents see how their child is doing?') ?></summary><p class="ts-muted"><?= te('Yes. Parents log in with their phone number to see marks, attendance, progress, homework and fees.') ?></p></details>
        <details><summary><?= te('Is the portal available in Sinhala?') ?></summary><p class="ts-muted"><?= te('Yes. Use the EN / Sinhala button at the top of the portal to switch.') ?></p></details>
    </div>
</section>

<section class="ts-section ts-section--alt" id="inquiry">
    <div class="ts-wrap">
        <div class="ts-join ts-reveal">
            <div class="ts-join__side">
                <?php if ($teacher): ?><div class="ts-join__avatar"><img src="<?= BASE_URL ?>/assets/img/teacher-smartboard.jpg" width="96" height="96" loading="lazy" alt="<?= $e($teacher) ?>"></div><?php endif; ?>
                <div class="ts-kicker" style="color:#FDE68A;"><?= te('Join a class') ?></div>
                <h2 style="margin:0 0 .5rem;"><?= te('Enquire about a class') ?></h2>
                <p style="opacity:.9;"><?= te('Leave your details and the branch office will call you back.') ?></p>
                <ul class="ts-ticks">
                    <li><?= te('Weekly classes by grade') ?></li>
                    <li><?= te('Recordings, notes and quizzes online') ?></li>
                    <li><?= te('A parent login to follow progress') ?></li>
                </ul>
                <?php foreach ($branches as $b): if (!empty($b['phone1'])): ?>
                    <div style="font-size:.9rem; margin-top:.3rem;"><strong><?= $e($b['name']) ?></strong> &middot; <a href="<?= $e(\App\Services\PublicSite::telHref($b['phone1'])) ?>"><?= $e($b['phone1']) ?></a></div>
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
                        <?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= $pickBranch === (int) $b['id'] ? 'selected' : '' ?>><?= $e($b['name']) ?></option><?php endforeach; ?>
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
