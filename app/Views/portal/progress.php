<?php
/**
 * "Where am I?"
 *
 * The three components are shown separately rather than only as one
 * number, because the number alone does not tell a student what to do
 * differently. The summary line names the weakest of the three.
 */

$overall = $overall ?? [];
$classes = $classes ?? [];

$toneColour = static fn (string $tone): string => match ($tone) {
    'success' => 'var(--success)',
    'info'    => 'var(--primary)',
    'warning' => 'var(--warning)',
    'danger'  => 'var(--danger)',
    default   => 'var(--text-muted)',
};

/** A bar is easier to compare against than a bare percentage. */
$bar = static function (string $label, float $value, string $colour): string {
    $width = max(0, min(100, $value));
    return '<div style="margin-bottom:.875rem;">'
        . '<div style="display:flex; justify-content:space-between; font-size:.875rem; margin-bottom:.25rem;">'
        . '<span style="color:var(--text-secondary);">' . $label . '</span>'
        . '<strong>' . rtrim(rtrim(number_format($value, 1), '0'), '.') . '%</strong></div>'
        . '<div style="height:8px; background:var(--surface-sunken); border-radius:999px; overflow:hidden;">'
        . '<div style="height:100%; width:' . $width . '%; background:' . $colour . '; border-radius:999px;"></div>'
        . '</div></div>';
};
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">My progress</h1>
        <p class="text-sm text-muted">Across quizzes, attendance and the material you have opened</p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/my/classes" class="btn btn--ghost">My classes</a>
        <a href="<?= BASE_URL ?>/my/marks" class="btn btn--ghost">My marks</a>
    </div>
</div>

<div class="card" style="margin-bottom:1.5rem;">
    <div class="card__body" style="display:grid; grid-template-columns:minmax(0,220px) minmax(0,1fr); gap:2rem; align-items:center;">
        <div style="text-align:center;">
            <div style="font-size:3.25rem; font-weight:800; line-height:1; color:<?= $toneColour($overall['tone'] ?? 'muted') ?>;">
                <?= rtrim(rtrim(number_format((float) ($overall['score'] ?? 0), 1), '0'), '.') ?>
            </div>
            <div style="margin-top:.5rem; font-weight:700; font-size:1.1rem; color:<?= $toneColour($overall['tone'] ?? 'muted') ?>;">
                <?= htmlspecialchars($overall['level'] ?? 'Not started', ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="text-sm text-muted" style="margin-top:.25rem;">out of 100</div>
        </div>

        <div>
            <p style="margin:0 0 1.25rem; color:var(--text-secondary);">
                <?= htmlspecialchars($overall['summary'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <?php if (!empty($overall['has_data'])): ?>
                <?= $bar('Quiz marks', (float) $overall['marks']['average_percentage'], 'var(--primary)') ?>
                <?= $bar('Attendance', (float) $overall['attendance']['rate'], 'var(--success)') ?>
                <?= $bar('Material opened', (float) $overall['engagement']['rate'], 'var(--accent)') ?>

                <div class="text-sm text-muted" style="margin-top:1rem;">
                    Weighted: marks 60%, attendance 25%, material 15%.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($overall['has_data'])): ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:1rem; margin-bottom:1.5rem;">
        <?php
        $tiles = [
            ['Quizzes sat', (int) $overall['marks']['quizzes_taken']],
            ['Best mark', $overall['marks']['best_percentage'] !== null ? $overall['marks']['best_percentage'] . '%' : '&mdash;'],
            ['Sessions attended', $overall['attendance']['attended'] . ' / ' . $overall['attendance']['sessions_recorded']],
            ['Items opened', $overall['engagement']['materials_viewed'] . ' / ' . $overall['engagement']['materials_total']],
        ];
        foreach ($tiles as [$label, $value]): ?>
            <div class="card" style="padding:1rem;">
                <div class="text-sm text-muted"><?= $label ?></div>
                <div style="font-size:1.5rem; font-weight:700; margin-top:.25rem;"><?= $value ?></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card__header"><h3 style="margin:0;">By class</h3></div>

    <?php if ($classes === []): ?>
        <div style="padding:2rem; text-align:center;" class="text-sm text-muted">
            No classes yet.
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th class="text-right">Quiz average</th>
                        <th class="text-right">Attendance</th>
                        <th class="text-right">Material</th>
                        <th>Level</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($classes as $c): $p = $c['progress']; ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($c['class_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-sm text-muted"><?= htmlspecialchars($c['grade'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td class="text-right">
                            <?= $p['marks']['quizzes_taken'] > 0
                                ? rtrim(rtrim(number_format((float) $p['marks']['average_percentage'], 1), '0'), '.') . '%'
                                : '&mdash;' ?>
                        </td>
                        <td class="text-right">
                            <?= $p['attendance']['sessions_recorded'] > 0
                                ? rtrim(rtrim(number_format((float) $p['attendance']['rate'], 1), '0'), '.') . '%'
                                : '&mdash;' ?>
                        </td>
                        <td class="text-right">
                            <?= $p['engagement']['materials_total'] > 0
                                ? $p['engagement']['materials_viewed'] . ' / ' . $p['engagement']['materials_total']
                                : '&mdash;' ?>
                        </td>
                        <td>
                            <span class="badge" style="background:var(--surface-sunken); color:<?= $toneColour($p['tone']) ?>;">
                                <?= htmlspecialchars($p['level'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
