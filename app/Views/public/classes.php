<?php
/** Public class timetable with grade and branch filters. */
$branches = $branches ?? [];
$classes = $classes ?? [];
$grade = $grade ?? null;
$branch = $branch ?? null;
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_site.php';
?>
<section class="ts-section">
    <div class="ts-wrap">
        <h1 style="font-size:clamp(1.6rem,3.6vw,2.3rem); margin:0 0 .4rem;"><?= te('Class timetable') ?></h1>
        <p class="ts-lead"><?= te('Science, Grades 6 to 11. Filter by grade or branch.') ?></p>
        <form method="GET" action="<?= BASE_URL ?>/timetable" class="ts-form" style="max-width:640px; margin-bottom:1.4rem;">
            <div><label for="f-grade"><?= te('Grade') ?></label><select id="f-grade" name="grade" onchange="this.form.submit()"><option value=""><?= te('All grades') ?></option>
                <?php foreach (range(6, 11) as $g): ?><option value="<?= $g ?>" <?= $grade === (string) $g ? 'selected' : '' ?>><?= te('Grade {n}', ['n' => $g]) ?></option><?php endforeach; ?></select></div>
            <div><label for="f-branch"><?= te('Branch') ?></label><select id="f-branch" name="branch" onchange="this.form.submit()"><option value=""><?= te('All branches') ?></option>
                <?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= $branch === (int) $b['id'] ? 'selected' : '' ?>><?= te($b['name']) ?></option><?php endforeach; ?></select></div>
            <noscript><button type="submit" class="ts-btn ts-btn--blue"><?= te('Show') ?></button></noscript>
        </form>
        <?php if ($classes === []): ?>
            <p class="ts-muted"><?= te('No classes match. Try another grade or branch.') ?></p>
        <?php else: ?>
            <div class="ts-grid"><?php foreach ($classes as $c) { require __DIR__ . '/_class-card.php'; } ?></div>
        <?php endif; ?>
        <p style="margin-top:1.6rem;"><a href="<?= BASE_URL ?>/#inquiry" class="ts-btn ts-btn--blue"><?= te('Enquire about a class') ?></a></p>
    </div>
</section>
