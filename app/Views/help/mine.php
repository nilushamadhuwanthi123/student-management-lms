<?php
/** A student's or parent's conversations, and the forms to start one. */
$threads = $threads ?? [];
$classes = $classes ?? [];
$who = $who ?? 'student';
$base = BASE_URL . ($base ?? '/my/help');
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tone = static fn (string $s): string => match ($s) { 'answered' => 'var(--success)', 'resolved', 'closed' => 'var(--text-muted)', 'in_progress' => 'var(--primary)', default => 'var(--warning)' };
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Help & questions') ?></h1>
        <p class="text-sm text-muted"><?= $who === 'student' ? te('Ask your teacher about a lesson, or ask the office for help.') : te('Ask the office about enrolment, payments, schedules or access.') ?></p>
    </div>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr)); gap:1.25rem; align-items:start;">
    <form method="POST" action="<?= $base ?>" enctype="multipart/form-data" class="card">
        <?= csrfField() ?>
        <div class="card__header"><h3 style="margin:0;"><?= te('Start a conversation') ?></h3></div>
        <div class="card__body" style="display:grid; gap:.85rem;">
            <?php if ($who === 'student'): ?>
            <div class="form-group">
                <label class="form-label" for="kind"><?= te('Who should answer?') ?></label>
                <select id="kind" name="kind" class="form-control" onchange="document.getElementById('classPick').style.display = this.value === 'question' ? '' : 'none';">
                    <option value="question"><?= te('My teacher (a question about a lesson)') ?></option>
                    <option value="support"><?= te('The office (account, fees, schedules)') ?></option>
                </select>
            </div>
            <div class="form-group" id="classPick">
                <label class="form-label" for="class_id"><?= te('Class') ?></label>
                <select id="class_id" name="class_id" class="form-control">
                    <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= $e($c['class_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label class="form-label" for="subject"><?= te('Subject') ?></label>
                <input type="text" id="subject" name="subject" class="form-control" maxlength="255" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="body"><?= te('Message') ?></label>
                <textarea id="body" name="body" class="form-control" rows="5" required></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="attachment"><?= te('Attach a photo or PDF (up to 5 MB)') ?></label>
                <input type="file" id="attachment" name="attachment" class="form-control" accept=".pdf,.png,.jpg,.jpeg">
            </div>
            <div><button type="submit" class="btn btn--primary"><?= te('Send') ?></button></div>
        </div>
    </form>

    <div class="card">
        <div class="card__header"><h3 style="margin:0;"><?= te('Your conversations') ?></h3></div>
        <?php if ($threads === []): ?>
            <div class="card__body text-sm text-muted"><?= te('Nothing yet.') ?></div>
        <?php else: ?>
        <div class="card__body" style="display:grid; gap:.5rem;">
            <?php foreach ($threads as $t): ?>
                <a href="<?= $base ?>/<?= (int) $t['id'] ?>" style="display:flex; justify-content:space-between; gap:.75rem; padding:.7rem .85rem; border:1px solid var(--border); border-radius:var(--radius); text-decoration:none; color:inherit;">
                    <div style="min-width:0;">
                        <div style="font-weight:600; overflow-wrap:anywhere;"><?= $e($t['subject']) ?></div>
                        <div class="text-xs text-muted"><?= $t['kind'] === 'question' ? te('Teacher') . ' &middot; ' . $e($t['class_name'] ?? '') : te('Office') ?> &middot; <?= $e(lang_date('j M', (string) ($t['last_reply_at'] ?? $t['created_at']))) ?></div>
                    </div>
                    <span class="text-xs" style="font-weight:700; color:<?= $tone((string) $t['status']) ?>; white-space:nowrap;"><?= te(ucfirst(str_replace('_', ' ', (string) $t['status']))) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
