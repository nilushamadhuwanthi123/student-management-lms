<?php
/**
 * The guardian's one screen: how the child is doing, and what is owed.
 *
 * Ordered the way a parent asks: how are they doing, have they been
 * going, how did the last tests go, what do we owe.
 */

$student    = $student ?? [];
$classes    = $classes ?? [];
$overall    = $overall ?? [];
$recent     = $recent ?? [];
$attendance = $attendance ?? [];

$toneColour = static fn (string $tone): string => match ($tone) {
    'success' => 'var(--success)',
    'info'    => 'var(--primary)',
    'warning' => 'var(--warning)',
    'danger'  => 'var(--danger)',
    default   => 'var(--text-muted)',
};

$owed = 0.0;
foreach ($classes as $c) {
    if (!$c['fee']['allowed'] && $c['fee']['amount_due'] !== null) {
        $owed += (float) $c['fee']['amount_due'];
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-muted">
            <?= htmlspecialchars($student['student_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            &middot; <?= count($classes) ?> class<?= count($classes) === 1 ? '' : 'es' ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/family/marks" class="btn btn--ghost">All marks</a>
        <a href="<?= BASE_URL ?>/family/fees" class="btn btn--ghost">Fees</a>
    </div>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; margin-bottom:1.5rem;">
    <div class="card" style="padding:1.25rem;">
        <div class="text-sm text-muted">Overall</div>
        <div style="font-size:1.6rem; font-weight:700; margin-top:.25rem; color:<?= $toneColour($overall['tone'] ?? 'muted') ?>;">
            <?= htmlspecialchars($overall['level'] ?? 'Not started', ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>
    <div class="card" style="padding:1.25rem;">
        <div class="text-sm text-muted">Quiz average</div>
        <div style="font-size:1.6rem; font-weight:700; margin-top:.25rem;">
            <?= !empty($overall['has_data']) && $overall['marks']['quizzes_taken'] > 0
                ? rtrim(rtrim(number_format((float) $overall['marks']['average_percentage'], 1), '0'), '.') . '%'
                : '&mdash;' ?>
        </div>
    </div>
    <div class="card" style="padding:1.25rem;">
        <div class="text-sm text-muted">Attendance</div>
        <div style="font-size:1.6rem; font-weight:700; margin-top:.25rem; color:var(--success);">
            <?= !empty($overall['has_data']) && $overall['attendance']['sessions_recorded'] > 0
                ? rtrim(rtrim(number_format((float) $overall['attendance']['rate'], 1), '0'), '.') . '%'
                : '&mdash;' ?>
        </div>
    </div>
    <div class="card" style="padding:1.25rem; <?= $owed > 0 ? 'border-color:var(--warning);' : '' ?>">
        <div class="text-sm text-muted">Outstanding this month</div>
        <div style="font-size:1.6rem; font-weight:700; margin-top:.25rem; color:<?= $owed > 0 ? 'var(--warning)' : 'var(--success)' ?>;">
            <?= $owed > 0 ? 'Rs. ' . number_format($owed, 2) : 'Settled' ?>
        </div>
    </div>
</div>

<?php if (!empty($overall['summary'])): ?>
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card__body" style="padding:1.25rem;">
        <?= htmlspecialchars($overall['summary'], ENT_QUOTES, 'UTF-8') ?>
    </div>
</div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:1.5rem;">

    <div class="card">
        <div class="card__header"><h3 style="margin:0;">Classes</h3></div>
        <div class="card__body" style="display:grid; gap:.875rem;">
            <?php foreach ($classes as $c): $p = $c['progress']; ?>
                <div style="border:1px solid var(--border); border-radius:var(--radius); padding:.875rem 1rem;">
                    <div style="display:flex; justify-content:space-between; gap:.75rem;">
                        <div style="font-weight:600;"><?= htmlspecialchars($c['class_name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <span class="badge" style="white-space:nowrap; background:var(--surface-sunken); color:<?= $toneColour($p['tone']) ?>;">
                            <?= htmlspecialchars($p['level'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <div class="text-sm text-muted" style="margin-top:.35rem;">
                        <?= htmlspecialchars($c['institute_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        &middot; Rs. <?= number_format((float) $c['monthly_fee'], 2) ?>/month
                    </div>
                    <div class="text-sm" style="margin-top:.35rem; color:<?= $c['fee']['allowed'] ? 'var(--success)' : 'var(--warning)' ?>;">
                        <?= htmlspecialchars($c['fee']['message'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="display:grid; gap:1.5rem; align-content:start;">
        <div class="card">
            <div class="card__header"><h3 style="margin:0;">Recent marks</h3></div>
            <?php if ($recent === []): ?>
                <div style="padding:1.5rem; text-align:center;" class="text-sm text-muted">No quizzes sat yet.</div>
            <?php else: ?>
                <div class="card__body" style="display:grid; gap:.6rem;">
                    <?php foreach ($recent as $r): ?>
                        <div style="display:flex; justify-content:space-between; gap:1rem; align-items:center;">
                            <div>
                                <div style="font-weight:600; font-size:.925rem;">
                                    <?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="text-sm text-muted">
                                    <?= htmlspecialchars($r['class_name'], ENT_QUOTES, 'UTF-8') ?>
                                    &middot; <?= $r['submitted_at'] ? date('j M', strtotime($r['submitted_at'])) : '' ?>
                                </div>
                            </div>
                            <strong style="color:<?= (float) $r['percentage'] >= 50 ? 'var(--success)' : 'var(--danger)' ?>;">
                                <?= rtrim(rtrim(number_format((float) $r['percentage'], 1), '0'), '.') ?>%
                            </strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card__header"><h3 style="margin:0;">Recent attendance</h3></div>
            <?php if ($attendance === []): ?>
                <div style="padding:1.5rem; text-align:center;" class="text-sm text-muted">Nothing recorded yet.</div>
            <?php else: ?>
                <div class="card__body" style="display:grid; gap:.5rem;">
                    <?php foreach ($attendance as $a):
                        $colour = match ($a['status']) {
                            'present' => 'var(--success)',
                            'late'    => 'var(--warning)',
                            default   => 'var(--danger)',
                        };
                    ?>
                        <div style="display:flex; justify-content:space-between; gap:1rem;" class="text-sm">
                            <span>
                                <?= date('j M', strtotime($a['date'])) ?>
                                <span class="text-muted">&middot; <?= htmlspecialchars($a['class_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                            <strong style="color:<?= $colour ?>; text-transform:capitalize;"><?= htmlspecialchars($a['status'], ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
