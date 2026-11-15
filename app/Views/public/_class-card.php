<?php /** @var array<string, mixed> $c */ $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); ?>
<div class="ts-card ts-class ts-g<?= (int) preg_replace('/\D+/', '', (string) $c['grade']) ?>">
    <div style="margin-bottom:.45rem;">
        <span class="ts-badge ts-badge--grade"><?= $e(t((string) $c['grade'])) ?></span>
        <?php if (($c['delivery_mode'] ?? 'physical') !== 'physical'): ?>
            <span class="ts-badge ts-badge--online"><?= $c['delivery_mode'] === 'online' ? te('Online') : te('Physical + online') ?></span>
        <?php else: ?>
            <span class="ts-badge"><?= te('Physical class') ?></span>
        <?php endif; ?>
    </div>
    <h3><?= te($c['class_name']) ?></h3>
    <div class="ts-muted">&#128205; <?= $e($c['branch_name']) ?><?= !empty($c['room']) ? ' &middot; ' . $e($c['room']) : '' ?></div>
    <div class="ts-muted">&#128197; <?= $e(site_days($c['schedule_days'])) ?><?= !empty($c['start_time']) ? ' &middot; ' . $e(site_time($c['start_time'])) . (!empty($c['end_time']) ? ' - ' . $e(site_time($c['end_time'])) : '') : '' ?></div>
    <?php if (!empty($c['teacher_name'])): ?><div class="ts-muted">&#128105;&#8205;&#127979; <?= te($c['teacher_name']) ?></div><?php endif; ?>
    <div style="margin-top:.6rem; display:flex; justify-content:space-between; align-items:center; gap:.5rem; flex-wrap:wrap;">
        <strong style="color:#1D4ED8;"><?= $c['public_fee'] !== null ? te('Rs. {n} a month', ['n' => number_format((float) $c['public_fee'], 0)]) : te('Ask about fees') ?></strong>
        <div style="display:flex; gap:.4rem; flex-wrap:wrap;">
            <a class="ts-btn ts-btn--line" style="padding:.4rem .85rem; font-size:.82rem;" href="<?= BASE_URL ?>/?grade=<?= $e((string) preg_replace('/\D+/', '', (string) $c['grade'])) ?>&branch=<?= (int) $c['institute_id'] ?>#inquiry"><?= te('Enquire') ?></a>
            <a class="ts-btn ts-btn--blue" style="padding:.4rem .85rem; font-size:.82rem;" href="<?= BASE_URL ?>/enroll?class=<?= (int) $c['id'] ?>"><?= te('Enrol now') ?></a>
        </div>
    </div>
</div>
