<?php
/**
 * About the classes and the teacher. Facts only from the database.
 */
use App\Services\PublicSite;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$teacher = PublicSite::teacherName();
$facts = PublicSite::facts();
$branches = PublicSite::branches();
$range = PublicSite::gradeRange();
require __DIR__ . '/_site.php';
?>
<section class="ts-hero" style="padding:6.2rem 0 3.6rem;">
    <div class="ts-wrap">
        <div class="ts-eyebrow"><?= te('About the classes') ?></div>
        <h1 style="max-width:22ch;"><?= $teacher ? te('Science with {name}', ['name' => $teacher]) : te('Science classes') ?></h1>
        <p><?= te('Science for Grades {r} at {n} branches, in class every week and online between lessons.', ['r' => $range, 'n' => (int) $facts['branches']]) ?></p>
    </div>
</section>

<?php if ($teacher): ?>
<?php if (\App\Services\PublicSite::isOn('show_about_teacher')): ?>
<section class="ts-section">
    <div class="ts-wrap ts-teacher ts-reveal">
        <?php
        $teacherPhoto = PublicSite::content('teacher_photo_image');
        $teacherIsVideo = $teacherPhoto !== '' && \App\Services\SiteContentService::isVideoPath($teacherPhoto);
        ?>
        <div class="ts-teacher__img" style="aspect-ratio:4/5;">
            <?php if ($teacherIsVideo): ?>
                <video src="<?= BASE_URL . $e($teacherPhoto) ?>" width="1600" height="2000" style="width:100%; height:100%; object-fit:cover; object-position:50% 18%;" autoplay muted loop playsinline aria-label="<?= $e(t('{name} teaching a Science lesson', ['name' => $teacher])) ?>"></video>
            <?php else: ?>
                <img src="<?= $teacherPhoto !== '' ? BASE_URL . $e($teacherPhoto) : BASE_URL . '/assets/img/teacher-smartboard.jpg' ?>" width="1600" height="2000" loading="lazy" style="object-position:50% 18%;" alt="<?= $e(t('{name} teaching a Science lesson', ['name' => $teacher])) ?>">
            <?php endif; ?>
        </div>
        <div>
            <div class="ts-kicker"><?= $e(PublicSite::content('home_teacher_kicker')) ?></div>
            <h2><?= $e($teacher) ?></h2>
            <div class="ts-lead ts-rich"><?= PublicSite::content('home_teacher_text', ['name' => $teacher, 'r' => $range, 'n' => (int) $facts['branches']]) ?></div>
            <ul class="ts-ticks">
                <?php foreach (['home_teacher_point_1', 'home_teacher_point_2', 'home_teacher_point_3', 'home_teacher_point_4'] as $pointKey): ?>
                    <?php $point = trim(PublicSite::content($pointKey)); ?>
                    <?php if ($point !== ''): ?><li><?= $e($point) ?></li><?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (\App\Services\PublicSite::isOn('show_about_inclass')): ?>
<section class="ts-section ts-section--alt">
    <div class="ts-wrap ts-teacher ts-reveal" style="grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);">
        <div>
            <div class="ts-kicker"><?= te('In class') ?></div>
            <h2><?= te('Learning together, every week') ?></h2>
            <div class="ts-lead ts-rich"><?= PublicSite::content('about_in_class_text') ?></div>
            <a href="<?= BASE_URL ?>/timetable" class="ts-btn ts-btn--blue"><?= te('See the timetable') ?></a>
        </div>
        <?php $classImage = PublicSite::content('about_class_image'); ?>
        <div class="ts-teacher__img"><img src="<?= $classImage !== '' ? BASE_URL . $e($classImage) : BASE_URL . '/assets/img/teacher-classroom.jpg' ?>" width="2048" height="1365" loading="lazy" alt="<?= $e(t('{name} with her students in class', ['name' => $teacher])) ?>"></div>
    </div>
</section>
<?php endif; ?>
<?php endif; ?>

<?php $extraText = PublicSite::content('about_extra_text'); if (trim($extraText) !== ''): ?>
<section class="ts-section">
    <div class="ts-wrap ts-reveal" style="max-width:70ch;">
        <div class="ts-lead ts-rich"><?= $extraText ?></div>
    </div>
</section>
<?php endif; ?>

<?php if ($branches !== []): ?>
<?php if (\App\Services\PublicSite::isOn('show_about_branches')): ?>
<section class="ts-section">
    <div class="ts-wrap">
        <div class="ts-kicker"><?= te('Branches') ?></div>
        <h2><?= te('Our branches') ?></h2>
        <div class="ts-grid" style="margin-top:1rem;">
            <?php foreach ($branches as $b): ?>
                <a class="ts-card ts-reveal" style="position:relative; text-decoration:none; padding:0; overflow:hidden;" href="<?= BASE_URL ?>/branches/<?= (int) $b['id'] ?>">
                    <?php $__aboutVideo = \Institute::videoHref($b['video_url'] ?? null); ?>
                    <?php if ($__aboutVideo !== ''): ?>
                        <span style="position:absolute; top:.7rem; right:.7rem; z-index:2; display:flex; align-items:center; gap:.3rem; background:rgba(10,20,50,.6); backdrop-filter:blur(6px); color:#fff; font-size:.72rem; font-weight:600; padding:.3rem .6rem; border-radius:999px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                            <?= te('Watch video') ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($b['photo'])): ?>
                        <div style="aspect-ratio:16/9; overflow:hidden; background:#EFF6FF;">
                            <img src="<?= BASE_URL . $e($b['photo']) ?>" alt="<?= te($b['name']) ?>" loading="lazy" style="width:100%; height:100%; object-fit:cover; display:block;">
                        </div>
                    <?php endif; ?>
                    <div style="padding:1.15rem 1.2rem;">
                        <h3><?= te($b['name']) ?></h3>
                        <?php if (!empty($b['location'])): ?><div class="ts-muted"><?= te($b['location']) ?></div><?php endif; ?>
                        <?php if (!empty($b['public_description'])): ?><p class="ts-muted" style="margin:.4rem 0 0;"><?= te($b['public_description']) ?></p><?php endif; ?>
                        <div class="ts-muted" style="margin-top:.35rem;"><?= te('{n} classes', ['n' => (int) $b['class_count']]) ?> &rarr;</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php endif; ?>

<?php if (\App\Services\PublicSite::isOn('show_about_inquiry')): ?>
<section class="ts-section" style="padding-top:0;">
    <div class="ts-wrap">
        <div class="ts-cta ts-reveal">
            <div><h2><?= te('Would you like your child to join?') ?></h2><p><?= te('Send an inquiry and the branch office will call you back.') ?></p></div>
            <a href="<?= BASE_URL ?>/#inquiry" class="ts-btn ts-btn--yellow"><?= te('Join a class') ?></a>
        </div>
    </div>
</section>
<?php endif; ?>
<noscript><style>.ts-reveal { opacity: 1; transform: none; }</style></noscript>
