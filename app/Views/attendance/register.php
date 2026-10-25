<?php
/**
 * Class register. Variables: $classes, $class, $date, $students, $alertsLive
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'none' => 0];
foreach ($students as $s) { $counts[$s['status'] ?? 'none'] = ($counts[$s['status'] ?? 'none'] ?? 0) + 1; }
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Class register') ?></h1>
        <p class="text-sm text-muted"><?= te('Students who scanned in are already marked present. Mark the rest, then save.') ?></p>
    </div>
    <div class="page-header__actions"><a href="<?= BASE_URL ?>/attendance" class="btn btn--ghost"><?= te('Attendance') ?></a></div>
</div>

<form method="GET" action="<?= BASE_URL ?>/attendance/register" class="card mb-3"><div class="card__body" style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:flex-end;">
    <div class="form-group" style="margin:0; min-width:220px;"><label class="form-label" for="class_id"><?= te('Class') ?></label>
        <select id="class_id" name="class_id" class="form-control" required>
            <option value=""><?= te('Choose') ?></option>
            <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $class && (int) $class['id'] === (int) $c['id'] ? 'selected' : '' ?>><?= $h($c['class_name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="form-group" style="margin:0;"><label class="form-label" for="date"><?= te('Date') ?></label>
        <input type="date" id="date" name="date" value="<?= $h($date) ?>" max="<?= date('Y-m-d') ?>" class="form-control"></div>
    <button type="submit" class="btn btn--secondary"><?= te('Open register') ?></button>
</div></form>

<?php if ($class): ?>
    <?php if (!$alertsLive): ?>
        <div class="alert alert--info"><?= te('This is an older date: saving updates the record but does not alert families.') ?></div>
    <?php else: ?>
        <div class="alert alert--info"><?= te('Each student marked absent sends one alert to their family (portal notice, and a WhatsApp message prepared for the office to send).') ?></div>
    <?php endif; ?>

    <?php if ($students === []): ?>
        <div class="card"><div class="card__body"><p class="text-muted" style="margin:0;"><?= te('No students are enrolled in this class.') ?></p></div></div>
    <?php else: ?>
    <form method="POST" action="<?= BASE_URL ?>/attendance/register" class="card">
        <?= csrfField() ?>
        <input type="hidden" name="class_id" value="<?= (int) $class['id'] ?>">
        <input type="hidden" name="date" value="<?= $h($date) ?>">
        <div class="card__body" style="overflow-x:auto;">
            <p class="text-sm" style="margin-top:0;"><?= $h($class['class_name']) ?> &middot; <?= $h(lang_date('l, j F Y', $date)) ?> &middot;
                <?= te('Present') ?> <?= $counts['present'] ?>, <?= te('Late') ?> <?= $counts['late'] ?>, <?= te('Absent') ?> <?= $counts['absent'] ?>, <?= te('Not marked') ?> <?= $counts['none'] ?></p>
            <div style="margin-bottom:.75rem;"><button type="button" class="btn btn--ghost btn--sm" id="mark-rest-absent"><?= te('Mark everyone not marked as absent') ?></button></div>
            <table class="table">
                <thead><tr><th><?= te('Student') ?></th><th><?= te('Present') ?></th><th><?= te('Late') ?></th><th><?= te('Absent') ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($students as $s): $st = $s['status']; $id = (int) $s['id']; ?>
                    <tr data-unmarked="<?= $st === null ? '1' : '0' ?>">
                        <td><strong><?= $h($s['full_name']) ?></strong><br><span class="text-xs text-muted"><?= $h($s['student_id_display']) ?></span></td>
                        <?php foreach (['present', 'late', 'absent'] as $opt): ?>
                            <td><input type="radio" name="status[<?= $id ?>]" value="<?= $opt ?>" <?= $st === $opt ? 'checked' : '' ?> aria-label="<?= $h($s['full_name']) ?>: <?= te(ucfirst($opt)) ?>"></td>
                        <?php endforeach; ?>
                        <td><?php if ((int) $s['alerted'] > 0): ?><span class="badge badge--warning"><?= te('Family alerted') ?></span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn--primary"><?= te('Save register') ?></button>
        </div>
    </form>
    <script>
    document.getElementById('mark-rest-absent').addEventListener('click', function () {
        document.querySelectorAll('tr[data-unmarked="1"]').forEach(function (row) {
            var r = row.querySelector('input[value="absent"]');
            if (r && !row.querySelector('input:checked')) { r.checked = true; }
        });
    });
    </script>
    <?php endif; ?>
<?php endif; ?>
