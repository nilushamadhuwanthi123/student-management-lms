<?php
/**
 * Inside one class, for one month: the material and the quizzes.
 *
 * When the month is locked the controller sends no content at all, so
 * this page has nothing to leak — it only has to explain the lock.
 */

$class     = $class ?? [];
$gate      = $gate ?? ['allowed' => false, 'message' => '', 'amount_due' => null];
$materials = $materials ?? [];
$quizzes   = $quizzes ?? [];
$months    = $months ?? [];
$month     = $month ?? date('Y-m-01');
$types     = $types ?? [];

$classId = (int) ($class['id'] ?? 0);
$typeIcon = ['recording' => '&#127909;', 'reading' => '&#128196;', 'video' => '&#127916;', 'link' => '&#128279;'];
$now = date('Y-m-d H:i:s');
$monthLabel = lang_date('F Y', $month);

// The fee service bakes the month into its English message, which no
// dictionary could match, so the sentence is rebuilt from the reason.
$gateMessage = match ($gate['reason'] ?? '') {
    'overdue'       => t('Your {month} fee is overdue. Settle it to reopen this class.', ['month' => $monthLabel]),
    'unpaid'        => t('Your {month} fee is not settled yet.', ['month' => $monthLabel]),
    'not_generated' => t('The {month} fee has not been issued yet. This class opens once it is.', ['month' => $monthLabel]),
    'not_enrolled'  => t('You are not enrolled in this class.'),
    default         => (string) ($gate['message'] ?? ''),
};
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= htmlspecialchars($class['class_name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-muted">
            <?= htmlspecialchars($class['grade'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            <?php if (!empty($class['teacher_name'])): ?>
                &middot; <?= htmlspecialchars($class['teacher_name'], ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/my/classes" class="btn btn--ghost">&larr; <?= te('My Classes') ?></a>
        <form method="GET" action="<?= BASE_URL ?>/my/classes/<?= $classId ?>" style="display:inline-flex; gap:.5rem;">
            <select name="month" class="form-control" style="width:auto;" onchange="this.form.submit()">
                <?php foreach ($months as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $value === $month ? 'selected' : '' ?>>
                        <?= htmlspecialchars(lang_date('F Y', (string) $value), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php if (!$gate['allowed']): ?>
    <div class="card" style="padding:3rem 1.5rem; text-align:center; border-color:var(--warning);">
        <div style="font-size:2.75rem;">&#128274;</div>
        <h3 style="margin:1rem 0 .5rem;"><?= te('{month} is locked', ['month' => $monthLabel]) ?></h3>
        <p style="margin:0 auto 1rem; max-width:44ch; color:var(--text-secondary);">
            <?= htmlspecialchars($gateMessage, ENT_QUOTES, 'UTF-8') ?>
        </p>

        <?php if ($gate['amount_due'] !== null && $gate['amount_due'] > 0): ?>
            <div style="display:inline-block; padding:.75rem 1.25rem; background:var(--warning-light); border-radius:var(--radius); margin-bottom:1.25rem;">
                <div class="text-sm text-muted"><?= te('Amount due') ?></div>
                <div style="font-size:1.5rem; font-weight:700; color:var(--warning);">
                    Rs. <?= number_format((float) $gate['amount_due'], 2) ?>
                </div>
            </div>
        <?php endif; ?>

        <p class="text-sm text-muted" style="margin:0;">
            <?= te('Once the office records your payment this class opens again, with everything posted for the month.') ?>
        </p>
        <?php if ($gate['amount_due'] !== null && $gate['amount_due'] > 0): ?>
            <a href="<?= BASE_URL ?>/my/payments" class="btn btn--primary" style="margin-top:1rem;"><?= te('Paid at the bank? Upload the slip') ?></a>
        <?php endif; ?>
    </div>
<?php else: ?>

    <?php
    // Online classes: the meeting link is shown only here, to an enrolled
    // student whose fee for the current month is settled.
    $mode = (string) ($class['delivery_mode'] ?? 'physical');
    if (in_array($mode, ['online', 'hybrid'], true) && !empty($class['meeting_url']) && $month === date('Y-m-01')): ?>
        <div class="card" style="margin-bottom:1.25rem; border-color:var(--primary); display:flex; gap:1rem; align-items:center; justify-content:space-between; flex-wrap:wrap; padding:1rem 1.15rem;">
            <div>
                <div style="font-weight:700;">&#127909; <?= $mode === 'online' ? te('This is an online class') : te('This class also runs online') ?></div>
                <div class="text-sm text-muted"><?= te('Please do not share this link. It is for enrolled students only.') ?></div>
            </div>
            <a href="<?= htmlspecialchars((string) $class['meeting_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn--primary"><?= te('Join the online class') ?></a>
        </div>
    <?php endif; ?>

    <!-- Quizzes first: they have deadlines, material does not -->
    <?php if ($quizzes !== []): ?>
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card__header"><h3 style="margin:0;"><?= te('Quizzes') ?></h3></div>
            <div class="card__body" style="display:grid; gap:1rem;">
                <?php foreach ($quizzes as $q):
                    $isOpen = $now >= $q['opens_at'] && $now <= $q['closes_at'];
                    $isDone = $q['best_attempt_id'] !== null;
                    $isLive = $q['live_attempt_id'] !== null;
                    $used = (int) $q['attempts_used'];
                    $canSit = $isOpen && ($isLive || $used < (int) $q['max_attempts']);
                ?>
                    <div style="border:1px solid var(--border); border-radius:var(--radius); padding:1rem;
                                display:flex; justify-content:space-between; gap:1rem; align-items:center; flex-wrap:wrap;">
                        <div style="flex:1; min-width:200px;">
                            <div style="font-weight:600;"><?= htmlspecialchars($q['title'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-sm text-muted">
                                <?= te('{n} questions', ['n' => (int) $q['question_count']]) ?>
                                &middot; <?= te('{n} marks', ['n' => rtrim(rtrim(number_format((float) $q['total_marks'], 2), '0'), '.')]) ?>
                                <?php if (!empty($q['time_limit_minutes'])): ?>
                                    &middot; <?= te('{n} min', ['n' => (int) $q['time_limit_minutes']]) ?>
                                <?php endif; ?>
                            </div>
                            <div class="text-sm" style="margin-top:.25rem; color:<?= $isOpen ? 'var(--success)' : 'var(--text-muted)' ?>;">
                                <?php if ($now < $q['opens_at']): ?>
                                    <?= te('Opens {when}', ['when' => lang_date('j M, g:i a', (string) $q['opens_at'])]) ?>
                                <?php elseif ($isOpen): ?>
                                    <?= te('Closes {when}', ['when' => lang_date('j M, g:i a', (string) $q['closes_at'])]) ?>
                                <?php else: ?>
                                    <?= te('Closed {when}', ['when' => lang_date('j M', (string) $q['closes_at'])]) ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="text-align:right;">
                            <?php if ($isDone): ?>
                                <div style="font-size:1.35rem; font-weight:700; color:var(--primary);">
                                    <?= rtrim(rtrim(number_format((float) $q['best_percentage'], 1), '0'), '.') ?>%
                                </div>
                                <a href="<?= BASE_URL ?>/my/quizzes/<?= (int) $q['best_attempt_id'] ?>/result"
                                   class="btn btn--ghost btn--sm"><?= te('See result') ?></a>
                            <?php elseif ($isLive): ?>
                                <a href="<?= BASE_URL ?>/my/quizzes/<?= (int) $q['id'] ?>/sit"
                                   class="btn btn--primary"><?= te('Resume') ?></a>
                            <?php elseif ($canSit): ?>
                                <a href="<?= BASE_URL ?>/my/quizzes/<?= (int) $q['id'] ?>/sit"
                                   class="btn btn--primary"><?= te('Start') ?></a>
                            <?php elseif ($now < $q['opens_at']): ?>
                                <span class="text-sm text-muted"><?= te('Not open yet') ?></span>
                            <?php else: ?>
                                <span class="text-sm text-muted"><?= te('Missed') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card__header">
            <h3 style="margin:0;"><?= te('{month} material', ['month' => $monthLabel]) ?></h3>
        </div>

        <?php if ($materials === []): ?>
            <div style="padding:2.5rem 1.5rem; text-align:center;">
                <div style="font-size:2rem;">&#128230;</div>
                <p class="text-sm text-muted" style="margin:.75rem 0 0;">
                    <?= te('Nothing posted for this month yet.') ?>
                </p>
            </div>
        <?php else: ?>
            <div class="card__body" style="display:grid; gap:.875rem;">
                <?php foreach ($materials as $m): ?>
                    <a href="<?= BASE_URL ?>/my/materials/<?= (int) $m['id'] ?>/open"
                       <?= !empty($m['external_url']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                       style="display:flex; gap:1rem; align-items:center; padding:1rem;
                              border:1px solid var(--border); border-radius:var(--radius);
                              text-decoration:none; color:inherit;">
                        <div style="font-size:1.75rem; line-height:1;">
                            <?= $typeIcon[$m['material_type']] ?? '&#128196;' ?>
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:600;">
                                <?= htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if (!empty($m['description'])): ?>
                                <div class="text-sm text-muted">
                                    <?= htmlspecialchars($m['description'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                            <div class="text-sm text-muted" style="margin-top:.25rem;">
                                <?= te((string) ($types[$m['material_type']] ?? '')) ?>
                                <?php if (!empty($m['duration_minutes'])): ?>
                                    &middot; <?= te('{n} min', ['n' => (int) $m['duration_minutes']]) ?>
                                <?php endif; ?>
                                <?php if (!empty($m['lesson_date'])): ?>
                                    &middot; <?= htmlspecialchars(lang_date('j M', (string) $m['lesson_date']), ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-sm" style="color:<?= !empty($m['viewed_at']) ? 'var(--success)' : 'var(--primary)' ?>; white-space:nowrap;">
                            <?= !empty($m['viewed_at']) ? '&#10003; ' . te('opened') : te('Open') . ' &rarr;' ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
