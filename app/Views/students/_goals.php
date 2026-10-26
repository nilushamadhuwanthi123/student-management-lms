<?php
/**
 * Staff view of a student's learning goals, as the "Goals" tab panel in
 * Student 360. Included from students/show.php, inside the existing
 * .s360-tabs / .s360-panel pattern.
 *
 * Expects $studentGoals (GoalService::forStudent), $goalOptions
 * (GoalService::formOptions) and $goalStudentId.
 */
$sg = $studentGoals ?? [];
$go = $goalOptions ?? ['classes' => [], 'topics' => []];
$gid = (int) ($goalStudentId ?? 0);
$me = (int) session_get('user_id');
$hh = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$num = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
$typeName = ['quiz_average' => 'Quiz average', 'attendance' => 'Attendance', 'homework' => 'Homework handed in', 'topic' => 'A Science unit'];
?>
<?php if (!empty($sg)): ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr><th><?= te('Goal') ?></th><th><?= te('Target') ?></th><th><?= te('Now') ?></th><th><?= te('By') ?></th><th><?= te('Set by') ?></th><th><?= te('Status') ?></th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($sg as $g): ?>
                    <tr>
                        <td><?= te($typeName[$g['goal_type']] ?? $g['goal_type']) ?><?= !empty($g['topic_title']) ? ' &middot; ' . $hh($g['topic_title']) : '' ?><?= !empty($g['class_name']) ? ' &middot; ' . $hh($g['class_name']) : '' ?></td>
                        <td><?= $num($g['target_value']) ?>%</td>
                        <td><?= $g['value'] !== null ? $num($g['value']) . '%' : '&ndash;' ?></td>
                        <td class="text-sm"><?= $hh(date('M d, Y', strtotime((string) $g['period_end']))) ?></td>
                        <td class="text-sm"><?= $g['set_by_type'] === 'student' ? te('Student') : $hh($g['set_by_name'] ?? '') ?></td>
                        <td>
                            <span class="badge badge--<?= match ($g['status']) { 'achieved' => 'success', 'missed' => 'neutral', default => 'info' } ?>">
                                <?= te(match ($g['status']) { 'achieved' => 'Achieved', 'missed' => 'Not reached', default => 'In progress' }) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($g['status'] === 'active' && $g['set_by_type'] === 'user' && (int) $g['set_by_id'] === $me): ?>
                                <form method="post" action="<?= BASE_URL ?>/students/<?= $gid ?>/goals/<?= (int) $g['id'] ?>/remove" style="margin:0;">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn--ghost btn--sm"><?= te('Remove') ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-state__icon" style="font-size:2rem;">&#127919;</div>
        <div class="empty-state__title"><?= te('No Goals Yet') ?></div>
        <div class="empty-state__text"><?= te('Goals you set appear on the student\'s home page with live progress. The student can also set up to three of their own.') ?></div>
    </div>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>/students/<?= $gid ?>/goals"
      style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.6rem; margin-top:1.25rem; align-items:end;">
    <?= csrfField() ?>
    <div class="form-group" style="margin:0;">
        <label class="form-label" for="sg-type"><?= te('Goal') ?></label>
        <select id="sg-type" name="goal_type" class="form-control" required>
            <?php foreach ($typeName as $k => $label): if ($k === 'topic' && $go['topics'] === []) { continue; } ?>
                <option value="<?= $k ?>"><?= te($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label" for="sg-target"><?= te('Target %') ?></label>
        <input id="sg-target" type="number" name="target_value" min="1" max="100" value="75" class="form-control" required>
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label" for="sg-end"><?= te('By') ?></label>
        <input id="sg-end" type="date" name="period_end" class="form-control" required min="<?= date('Y-m-d') ?>"
               max="<?= date('Y-m-d', strtotime('+6 months')) ?>" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label" for="sg-class"><?= te('Class') ?></label>
        <select id="sg-class" name="class_id" class="form-control">
            <option value=""><?= te('All classes') ?></option>
            <?php foreach ($go['classes'] as $c): ?><option value="<?= (int) $c['id'] ?>"><?= $hh($c['class_name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php if ($go['topics'] !== []): ?>
        <div class="form-group" style="margin:0;">
            <label class="form-label" for="sg-topic"><?= te('Unit') ?></label>
            <select id="sg-topic" name="topic_id" class="form-control">
                <option value=""><?= te('Only for a unit goal') ?></option>
                <?php foreach ($go['topics'] as $tp): ?>
                    <option value="<?= (int) $tp['id'] ?>"><?= te('Grade {n}', ['n' => (int) $tp['grade']]) ?> &middot; <?= $tp['unit_no'] ? (int) $tp['unit_no'] . '. ' : '' ?><?= $hh($tp['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="form-group" style="margin:0; grid-column:1 / -1;">
        <label class="form-label" for="sg-note"><?= te('Note for the student (optional)') ?></label>
        <input id="sg-note" type="text" name="note" maxlength="255" class="form-control" placeholder="<?= te('e.g. Let\'s aim for this before the term test') ?>">
    </div>
    <div><button type="submit" class="btn btn--primary btn--sm"><?= te('Set goal') ?></button></div>
</form>
