<?php
/**
 * Class material, as the teacher sees it.
 *
 * Grouped by month because that is the unit the fee gate works in: a
 * student who paid for October sees October's shelf. Showing the month
 * prominently keeps the teacher's mental model and the student's the
 * same one.
 */

$class     = $class ?? [];
$materials = $materials ?? [];
$months    = $months ?? [];
$counts    = $counts ?? [];
$types     = $types ?? [];
$month     = $month ?? date('Y-m-01');

$typeIcon = [
    'recording' => '&#127909;',
    'reading'   => '&#128196;',
    'video'     => '&#127916;',
    'link'      => '&#128279;',
];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Class material') ?></h1>
        <p class="text-sm text-muted">
            <?= htmlspecialchars($class['class_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            <?php if (!empty($class['institute_name'])): ?>
                &middot; <?= htmlspecialchars($class['institute_name'], ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes" class="btn btn--ghost">&larr; <?= te('Classes') ?></a>
    </div>
</div>

<?php
/*
 * One "Post material" button asked the teacher to decode a Type
 * dropdown before she could start, and then showed her a link box and
 * an upload box together with a paragraph explaining which to use.
 * Four jobs behind one door. They are four doors now, each saying what
 * it is for, and each opens the form already set to that kind.
 */
$mkUrl = BASE_URL . '/classes/' . (int) $class['id'] . '/materials/create?month=' . urlencode($month);
$postKinds = [
    ['recording', '&#127909;', t('Lesson recording'),  t('The Zoom recording of a class you taught.')],
    ['reading',   '&#128196;', t('Notes or a paper'),  t('A PDF or a photo: tutes, notes, a past paper.')],
    ['video',     '&#128250;', t('Lab or lesson video'), t('An experiment you filmed, or any other video.')],
    ['link',      '&#128279;', t('A link'),            t('Anything already on the web that they should read or watch.')],
];
?>
<div class="mk-pick">
    <div class="mk-pick__lead"><?= te('What are you posting?') ?></div>
    <div class="mk-pick__grid">
        <?php foreach ($postKinds as [$kind, $icon, $label, $hint]): ?>
            <a class="mk-card" href="<?= $mkUrl ?>&amp;type=<?= $kind ?>">
                <span class="mk-card__icon" aria-hidden="true"><?= $icon ?></span>
                <span class="mk-card__label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="mk-card__hint"><?= htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- What is on the shelf overall -->
<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:1.5rem;">
    <?php foreach ($types as $key => $label): ?>
        <div class="card" style="padding:1rem;">
            <div style="font-size:1.5rem; line-height:1;"><?= $typeIcon[$key] ?? '&#128196;' ?></div>
            <div style="font-size:1.75rem; font-weight:700; color:var(--primary); margin-top:.35rem;">
                <?= (int) ($counts[$key] ?? 0) ?>
            </div>
            <div class="text-sm text-muted"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card__header" style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
        <div>
            <h3 style="margin:0;"><?= date('F Y', strtotime($month)) ?></h3>
            <p class="text-sm text-muted" style="margin:.25rem 0 0;">
                <?= te("Students who have settled this month's fee can open everything below.") ?>
            </p>
        </div>

        <form method="GET" action="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/materials"
              style="display:flex; gap:.5rem; align-items:center;">
            <label class="form-label" for="month" style="margin:0;"><?= te('Month') ?></label>
            <select id="month" name="month" class="form-control" style="width:auto;"
                    onchange="this.form.submit()">
                <?php foreach ($months as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                        <?= $value === $month ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn--ghost btn--sm"><?= te('Go') ?></button></noscript>
        </form>
    </div>

    <?php if ($materials === []): ?>
        <div style="padding:3rem 1.5rem; text-align:center;">
            <div style="font-size:2.5rem;">&#128230;</div>
            <h4 style="margin:.75rem 0 .35rem;"><?= htmlspecialchars(t('Nothing posted for {month} yet', ['month' => date('F', strtotime($month))]), ENT_QUOTES, 'UTF-8') ?></h4>
            <p class="text-sm text-muted" style="margin:0 0 1.25rem;">
                <?= te('Post the lesson recording, the notes and any video you want the class to have.') ?>
            </p>
            <p class="text-sm text-muted" style="margin:0;"><?= te('Pick one of the four above to start.') ?></p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= te('Item') ?></th>
                        <th><?= te('Type') ?></th>
                        <th><?= te('Lesson date') ?></th>
                        <th class="text-right"><?= te('Opened by') ?></th>
                        <th><?= te('Status') ?></th>
                        <th class="text-right"><?= te('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($materials as $m): ?>
                    <tr<?= $m['status'] === 'draft' ? ' style="opacity:.65;"' : '' ?>>
                        <td>
                            <div style="font-weight:600;">
                                <?= $typeIcon[$m['material_type']] ?? '' ?>
                                <?= htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if (!empty($m['description'])): ?>
                                <div class="text-sm text-muted" style="max-width:46ch;">
                                    <?= htmlspecialchars($m['description'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($m['duration_minutes'])): ?>
                                <div class="text-sm text-muted"><?= (int) $m['duration_minutes'] ?> min</div>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm"><?= htmlspecialchars($types[$m['material_type']] ?? $m['material_type'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-sm">
                            <?= !empty($m['lesson_date']) ? date('j M Y', strtotime($m['lesson_date'])) : '&mdash;' ?>
                        </td>
                        <td class="text-right">
                            <strong><?= (int) ($m['view_count'] ?? 0) ?></strong>
                            <span class="text-sm text-muted"><?= te('student') ?><?= (int) ($m['view_count'] ?? 0) === 1 ? '' : 's' ?></span>
                        </td>
                        <td>
                            <span class="badge" style="background:<?= $m['status'] === 'published' ? 'var(--success-light); color:var(--success)' : 'var(--warning-light); color:var(--warning)' ?>;">
                                <?= te(ucfirst($m['status'])) ?>
                            </span>
                        </td>
                        <td class="text-right">
                            <?php if (!empty($m['external_url'])): ?>
                                <a href="<?= htmlspecialchars($m['external_url'], ENT_QUOTES, 'UTF-8') ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   class="btn btn--ghost btn--sm"><?= te('Open') ?></a>
                            <?php elseif (!empty($m['file_path'])): ?>
                                <a href="<?= BASE_URL ?>/materials/<?= (int) $m['id'] ?>/download"
                                   class="btn btn--ghost btn--sm"><?= te('Download') ?></a>
                            <?php endif; ?>

                            <form method="POST"
                                  action="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/materials/<?= (int) $m['id'] ?>/archive"
                                  style="display:inline;"
                                  data-confirm="<?= te('Take this down? Students will stop seeing it.') ?>">
                                <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                <button type="submit" class="btn btn--ghost btn--sm" style="color:var(--danger);">
                                    <?= te('Take down') ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
    .mk-pick { margin-bottom:1.5rem; }
    .mk-pick__lead { font-size:.72rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase;
                     color:#94A3B8; margin-bottom:.5rem; }
    .mk-pick__grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(185px,1fr)); gap:.7rem; }
    .mk-card { display:grid; gap:.2rem; padding:.85rem .9rem; text-decoration:none; color:inherit;
               background:var(--card-bg,#fff); border:1px solid var(--border-color,#E2E8F0);
               border-radius:12px; transition:border-color .15s ease, transform .15s ease; }
    .mk-card:hover { border-color:var(--primary,#1D4ED8); transform:translateY(-2px); }
    .mk-card__icon { font-size:1.4rem; line-height:1; }
    .mk-card__label { font-weight:700; font-size:.92rem; }
    .mk-card__hint { font-size:.76rem; color:#64748B; line-height:1.45; }
</style>
