<?php
/**
 * A guardian's own account page.
 *
 * Variables: $parent (own parents row), $student (the linked child), $classes
 */
$parent  = $parent ?? [];
$student = $student ?? [];
$classes = $classes ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$initial = mb_strtoupper(mb_substr((string) ($parent['parent_name'] ?? 'P'), 0, 1));
?>
<style>
    .acct { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 1.25rem; align-items: start; }
    .acct__head { display: flex; gap: 1rem; align-items: center; }
    .acct__avatar { width: 64px; height: 64px; flex: 0 0 64px; border-radius: 50%; display: grid; place-items: center; font-size: 1.6rem; font-weight: 800; background: var(--primary-light); color: var(--primary); border: 2px solid var(--border); }
    .acct__rows { display: grid; gap: .1rem; margin-top: 1rem; }
    .acct__row { display: flex; justify-content: space-between; gap: 1rem; padding: .6rem 0; border-bottom: 1px dashed var(--divider); font-size: .92rem; }
    .acct__row:last-child { border-bottom: 0; }
    .acct__row span:first-child { color: var(--text-muted); }
    .acct__row span:last-child { font-weight: 600; text-align: right; overflow-wrap: anywhere; }
    .acct__classes { list-style: none; margin: .5rem 0 0; padding: 0; display: grid; gap: .5rem; }
    .acct__classes li { padding: .6rem .75rem; border-radius: 10px; background: var(--surface-sunken); font-size: .9rem; }
</style>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('My account') ?></h1>
        <p class="text-sm text-muted"><?= te('Your login details and the child linked to this account.') ?></p>
    </div>
</div>

<div class="acct">
    <div class="card" style="padding:1.25rem;">
        <div class="acct__head">
            <div class="acct__avatar" aria-hidden="true"><?= $e($initial) ?></div>
            <div>
                <div style="font-size:1.15rem; font-weight:700;"><?= $e($parent['parent_name'] ?? '') ?></div>
                <div class="text-sm text-muted"><?= te('Parent / guardian') ?></div>
            </div>
        </div>
        <div class="acct__rows">
            <div class="acct__row"><span><?= te('Login number') ?></span><span><?= $e($parent['phone1'] ?? '') ?></span></div>
            <?php if (!empty($parent['phone2'])): ?><div class="acct__row"><span><?= te('Other phone') ?></span><span><?= $e($parent['phone2']) ?></span></div><?php endif; ?>
            <?php if (!empty($parent['whatsapp'])): ?><div class="acct__row"><span>WhatsApp</span><span><?= $e($parent['whatsapp']) ?></span></div><?php endif; ?>
            <div class="acct__row"><span><?= te('Account') ?></span><span style="color:var(--success);"><?= te('Active') ?></span></div>
            <?php if (!empty($parent['password_changed_at'])): ?>
                <div class="acct__row"><span><?= te('Password last changed') ?></span><span><?= $e(lang_date('j M Y', (string) $parent['password_changed_at'])) ?></span></div>
            <?php endif; ?>
        </div>
        <div style="display:flex; gap:.5rem; flex-wrap:wrap; margin-top:1.1rem;">
            <a href="<?= BASE_URL ?>/change-password" class="btn btn--primary">&#128273; <?= te('Change password') ?></a>
        </div>
        <p class="text-xs text-muted" style="margin:.9rem 0 0;"><?= te('Your login number is how the office knows you. To change it, or a phone number above, please contact the branch office.') ?></p>
    </div>

    <div class="card" style="padding:1.25rem;">
        <h3 style="margin:0 0 .25rem; font-size:1.05rem;"><?= te('Linked child') ?></h3>
        <div class="acct__rows" style="margin-top:.5rem;">
            <div class="acct__row"><span><?= te('Name') ?></span><span><?= $e($student['full_name'] ?? '') ?></span></div>
            <div class="acct__row"><span><?= te('Student ID') ?></span><span style="font-family:monospace;"><?= $e($student['student_id_display'] ?? '') ?></span></div>
        </div>
        <?php if ($classes !== []): ?>
            <div class="text-sm text-muted" style="margin-top:.9rem;"><?= te('Classes') ?></div>
            <ul class="acct__classes">
                <?php foreach ($classes as $c): ?>
                    <li><strong><?= $e($c['class_name']) ?></strong><?php if (!empty($c['institute_name'])): ?><span class="text-muted"> &middot; <?= $e($c['institute_name']) ?></span><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-sm text-muted" style="margin-top:.9rem;"><?= te('Not enrolled in a class right now.') ?></p>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/family" class="btn btn--ghost" style="margin-top:1rem;"><?= te('My Child') ?> &rarr;</a>
    </div>
</div>
