<?php
/**
 * Syllabus units per grade. Variables: $topics, $usage, $grade, $subject, $subjects
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Topics') ?></h1>
        <p class="text-sm text-muted"><?= te('Your units for each grade. Tag notes, quizzes, questions and homework with them to see which units the class finds hard.') ?></p>
    </div>
</div>

<form method="GET" action="<?= BASE_URL ?>/topics" class="card mb-3"><div class="card__body" style="display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-end;">
    <div class="form-group" style="margin:0;">
        <label class="form-label" for="grade"><?= te('Grade') ?></label>
        <select id="grade" name="grade" class="form-control">
            <?php for ($g = 6; $g <= 13; $g++): ?>
                <option value="<?= $g ?>" <?= $g === $grade ? 'selected' : '' ?>><?= te('Grade {n}', ['n' => $g]) ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label" for="subject"><?= te('Subject') ?></label>
        <select id="subject" name="subject" class="form-control">
            <?php foreach ($subjects as $s): ?>
                <option value="<?= $h($s) ?>" <?= $s === $subject ? 'selected' : '' ?>><?= $h($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn--secondary"><?= te('Show') ?></button>
</div></form>

<div class="card mb-3"><div class="card__body">
    <h2 style="font-size:1rem; margin:0 0 .75rem;"><?= te('Add a unit') ?> &middot; <?= te('Grade {n}', ['n' => $grade]) ?> <?= $h($subject) ?></h2>
    <form method="POST" action="<?= BASE_URL ?>/topics" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; align-items:end;">
        <?= csrfField() ?>
        <input type="hidden" name="grade" value="<?= (int) $grade ?>">
        <input type="hidden" name="subject" value="<?= $h($subject) ?>">
        <div class="form-group" style="margin:0;"><label class="form-label" for="unit_no"><?= te('Unit no.') ?></label>
            <input type="number" id="unit_no" name="unit_no" min="1" max="99" class="form-control"></div>
        <div class="form-group" style="margin:0;"><label class="form-label" for="title"><?= te('Unit name (English)') ?> *</label>
            <input type="text" id="title" name="title" maxlength="255" required class="form-control"></div>
        <div class="form-group" style="margin:0;"><label class="form-label" for="title_si"><?= te('Unit name (Sinhala)') ?></label>
            <input type="text" id="title_si" name="title_si" maxlength="255" class="form-control"></div>
        <button type="submit" class="btn btn--primary"><?= te('Add') ?></button>
    </form>
</div></div>

<div class="card"><div class="card__body" style="overflow-x:auto;">
<?php if ($topics === []): ?>
    <p class="text-muted" style="margin:0;"><?= te('No units for this grade yet. Add them above, in the order you teach them.') ?></p>
<?php else: ?>
    <table class="table">
        <thead><tr><th><?= te('Unit no.') ?></th><th><?= te('Unit name (English)') ?></th><th><?= te('Unit name (Sinhala)') ?></th><th><?= te('Tagged items') ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($topics as $t): $fid = 'topic-edit-' . (int) $t['id']; ?>
            <tr style="<?= $t['status'] === 'archived' ? 'opacity:.55;' : '' ?>">
                <td style="width:90px;"><input type="number" name="unit_no" min="1" max="99" value="<?= $h($t['unit_no']) ?>" class="form-control" form="<?= $fid ?>" aria-label="<?= te('Unit no.') ?>"></td>
                <td><input type="text" name="title" maxlength="255" value="<?= $h($t['title']) ?>" required class="form-control" form="<?= $fid ?>" aria-label="<?= te('Unit name (English)') ?>"></td>
                <td><input type="text" name="title_si" maxlength="255" value="<?= $h($t['title_si']) ?>" class="form-control" form="<?= $fid ?>" aria-label="<?= te('Unit name (Sinhala)') ?>"></td>
                <td><?= (int) ($usage[(int) $t['id']] ?? 0) ?></td>
                <td style="white-space:nowrap;">
                    <form method="POST" action="<?= BASE_URL ?>/topics/<?= (int) $t['id'] ?>/edit" id="<?= $fid ?>" style="display:inline;">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn--ghost btn--sm"><?= te('Save') ?></button>
                    </form>
                    <form method="POST" action="<?= BASE_URL ?>/topics/<?= (int) $t['id'] ?>/archive" style="display:inline;">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn--ghost btn--sm"><?= $t['status'] === 'active' ? te('Hide') : te('Restore') ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div></div>
