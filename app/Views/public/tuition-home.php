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
<section class="ts-hero">
    <div class="ts-wrap">
        <div class="ts-chips" style="margin:0 0 1rem;"><span class="ts-chip">&#129514; <?= te('Science') ?></span><span class="ts-chip"><?= te('Grades 6 - 11') ?></span><span class="ts-chip"><?= te('Sinhala and English') ?></span></div>
        <h1><?= te('Science classes for') ?> <span class="ts-hl"><?= te('Grades 6 to 11') ?></span></h1>
        <p>
            <?php if ($teacher): ?><?= te('Science with {name}.', ['name' => $teacher]) ?> <?php endif; ?>
            <?= te('Weekly classes at our branches, with lesson recordings, notes, online quizzes and a portal where parents can follow progress and fees.') ?>
        </p>
        <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
            <a href="#inquiry" class="ts-btn ts-btn--yellow"><?= te('Enquire about a class') ?></a>
            <a href="<?= BASE_URL ?>/login" class="ts-btn ts-btn--ghost">&#128100; <?= te('Student login') ?></a>
            <a href="<?= BASE_URL ?>/login" class="ts-btn ts-btn--ghost">&#128106; <?= te('Parent login') ?></a>
        </div>
        <?php if ($branches !== []): ?>
        <div class="ts-chips">
            <?php foreach ($branches as $b): ?><a class="ts-chip" style="color:#fff; text-decoration:none;" href="<?= BASE_URL ?>/branches/<?= (int) $b['id'] ?>">&#128205; <?= $e($b['name']) ?></a><?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="ts-section">
    <div class="ts-wrap">
        <h2><?= te('How the classes work') ?></h2>
        <p class="ts-lead"><?= te('Each grade meets every week at a branch. Between classes, students keep learning from home.') ?></p>
        <div class="ts-grid">
            <div class="ts-card"><div class="ts-icon">&#127979;</div><h3><?= te('Weekly classes') ?></h3><p class="ts-muted"><?= te('Regular classes by grade at each branch, following the school Science syllabus.') ?></p></div>
            <div class="ts-card"><div class="ts-icon">&#127909;</div><h3><?= te('Recordings and notes') ?></h3><p class="ts-muted"><?= te('Missed a class or need a second look? Recordings, notes and videos are posted for each month.') ?></p></div>
            <div class="ts-card"><div class="ts-icon">&#9997;</div><h3><?= te('Quizzes and homework') ?></h3><p class="ts-muted"><?= te('Timed online quizzes with marks, and homework the teacher marks with feedback.') ?></p></div>
            <div class="ts-card"><div class="ts-icon">&#128106;</div><h3><?= te('Parent portal') ?></h3><p class="ts-muted"><?= te("Parents see marks, attendance, progress, fees and receipts for their child.") ?></p></div>
            <?php if ($hasOnline): ?><div class="ts-card"><div class="ts-icon">&#127760;</div><h3><?= te('Online classes') ?></h3><p class="ts-muted"><?= te('Some classes also run online. The joining link is given privately to enrolled students.') ?></p></div><?php endif; ?>
            <div class="ts-card"><div class="ts-icon">&#127991;</div><h3><?= te('Sinhala or English') ?></h3><p class="ts-muted"><?= te('Students and parents can switch the portal between Sinhala and English.') ?></p></div>
        </div>
    </div>
</section>

<?php if ($branches !== []): ?>
<section class="ts-section ts-section--alt">
    <div class="ts-wrap">
        <h2><?= te('Our branches') ?></h2>
        <p class="ts-lead"><?= te('Choose the branch nearest to you.') ?></p>
        <div class="ts-grid">
            <?php foreach ($branches as $b): ?>
                <div class="ts-card">
                    <h3>&#128205; <?= $e($b['name']) ?></h3>
                    <?php if (!empty($b['location'])): ?><div class="ts-muted"><?= $e($b['location']) ?></div><?php endif; ?>
                    <?php if (!empty($b['phone1'])): ?><div class="ts-muted">&#128222; <a href="tel:<?= $e(preg_replace('/[^\d+]/', '', (string) $b['phone1'])) ?>"><?= $e($b['phone1']) ?></a></div><?php endif; ?>
                    <div class="ts-muted" style="margin-top:.35rem;"><?= te('{n} classes', ['n' => (int) $b['class_count']]) ?></div>
                    <a href="<?= BASE_URL ?>/branches/<?= (int) $b['id'] ?>" class="ts-btn ts-btn--line" style="margin-top:.7rem; padding:.45rem .9rem; font-size:.85rem;"><?= te('See timetable') ?></a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="ts-section" id="classes">
    <div class="ts-wrap">
        <h2><?= te('Classes by grade') ?></h2>
        <p class="ts-lead"><?= te('Days and times for every grade. Ask the branch for a seat.') ?> <a href="<?= BASE_URL ?>/timetable"><?= te('Full timetable') ?> &rarr;</a></p>
        <?php if ($byGrade === []): ?>
            <p class="ts-muted"><?= te('The timetable will appear here soon. Please send an inquiry below.') ?></p>
        <?php endif; ?>
        <?php foreach ($byGrade as $g => $list): ?>
            <h3 style="margin:1.4rem 0 .7rem;"><?= te('Grade {n}', ['n' => $g]) ?></h3>
            <div class="ts-grid"><?php foreach ($list as $c) { require __DIR__ . '/_class-card.php'; } ?></div>
        <?php endforeach; ?>
    </div>
</section>

<section class="ts-section ts-section--alt">
    <div class="ts-wrap ts-faq">
        <h2><?= te('Questions parents ask') ?></h2>
        <details><summary><?= te('How do I join a class?') ?></summary><p class="ts-muted"><?= te('Send the inquiry form below, or call the branch. The office registers the student and gives you the student ID for the portal.') ?></p></details>
        <details><summary><?= te('How are fees paid?') ?></summary><p class="ts-muted"><?= te('Fees are monthly. Pay at the branch office, or pay by bank and upload the slip in the portal. The office checks every slip before the payment is recorded, and a receipt is then available to download.') ?></p></details>
        <details><summary><?= te('What happens if a student misses a class?') ?></summary><p class="ts-muted"><?= te('Recordings and notes for the month are posted in the portal, so the lesson can be caught up at home.') ?></p></details>
        <details><summary><?= te('Can parents see how their child is doing?') ?></summary><p class="ts-muted"><?= te('Yes. Parents log in with their phone number to see marks, attendance, progress, homework and fees.') ?></p></details>
        <details><summary><?= te('Is the portal available in Sinhala?') ?></summary><p class="ts-muted"><?= te('Yes. Use the EN / Sinhala button at the top of the portal to switch.') ?></p></details>
    </div>
</section>

<section class="ts-section" id="inquiry">
    <div class="ts-wrap">
        <h2><?= te('Enquire about a class') ?></h2>
        <p class="ts-lead"><?= te('Leave your details and the branch office will call you back.') ?></p>
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
</section>
