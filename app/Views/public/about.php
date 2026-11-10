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
<section class="ts-section">
    <div class="ts-wrap ts-teacher ts-reveal">
        <?php $teacherPhoto = PublicSite::content('teacher_photo_image'); ?>
        <div class="ts-teacher__img" style="aspect-ratio:4/5;"><img src="<?= $teacherPhoto !== '' ? BASE_URL . $e($teacherPhoto) : BASE_URL . '/assets/img/teacher-smartboard.jpg' ?>" width="1600" height="2000" loading="lazy" style="object-position:50% 18%;" alt="<?= $e(t('{name} teaching a Science lesson', ['name' => $teacher])) ?>"></div>
        <div>
            <div class="ts-kicker"><?= te('Your teacher') ?></div>
            <h2><?= $e($teacher) ?></h2>
            <p class="ts-lead"><?= te('{name} teaches Science for Grades {r} at {n} branches, following the school syllabus step by step.', ['name' => $teacher, 'r' => $range, 'n' => (int) $facts['branches']]) ?></p>
            <ul class="ts-ticks">
                <li><?= te('Lessons with smart-board presentations') ?></li>
                <li><?= te('Recordings and notes for every month, to revise at home') ?></li>
                <li><?= te('Quizzes, homework and past papers, marked with feedback') ?></li>
                <li><?= te('Parents can follow marks, attendance and fees in the portal') ?></li>
            </ul>
        </div>
    </div>
</section>

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

<?php $extraText = PublicSite::content('about_extra_text'); if (trim($extraText) !== ''): ?>
<section class="ts-section">
    <div class="ts-wrap ts-reveal" style="max-width:70ch;">
        <div class="ts-lead ts-rich"><?= $extraText ?></div>
    </div>
</section>
<?php endif; ?>

<?php if ($branches !== []): ?>
<section class="ts-section">
    <div class="ts-wrap">
        <div class="ts-kicker"><?= te('Branches') ?></div>
        <h2><?= te('Our branches') ?></h2>
        <div class="ts-grid" style="margin-top:1rem;">
            <?php foreach ($branches as $b): ?>
                <a class="ts-card ts-reveal" style="text-decoration:none;" href="<?= BASE_URL ?>/branches/<?= (int) $b['id'] ?>">
                    <h3><?= $e($b['name']) ?></h3>
                    <?php if (!empty($b['location'])): ?><div class="ts-muted"><?= $e($b['location']) ?></div><?php endif; ?>
                    <div class="ts-muted" style="margin-top:.35rem;"><?= te('{n} classes', ['n' => (int) $b['class_count']]) ?> &rarr;</div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="ts-section" style="padding-top:0;">
    <div class="ts-wrap">
        <div class="ts-cta ts-reveal">
            <div><h2><?= te('Would you like your child to join?') ?></h2><p><?= te('Send an inquiry and the branch office will call you back.') ?></p></div>
            <a href="<?= BASE_URL ?>/#inquiry" class="ts-btn ts-btn--yellow"><?= te('Join a class') ?></a>
        </div>
    </div>
</section>
<noscript><style>.ts-reveal { opacity: 1; transform: none; }</style></noscript>
