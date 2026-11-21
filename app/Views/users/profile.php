<?php
/**
 * User Profile View
 *
 * Variables: $user, $pageTitle
 */

$user = $user ?? [];
$role = $user['role'] ?? 'user';
$roleBadge = match ($role) {
    'super_admin' => 'badge--primary',
    'admin'       => 'badge--info',
    'coordinator' => 'badge--success',
    'staff'       => 'badge--warning',
    'teacher'     => 'badge--secondary',
    default       => 'badge--neutral',
};
?>

<div class="breadcrumb mb-2">
    <a href="<?= BASE_URL ?>/dashboard"><?= te('Dashboard') ?></a>
    <span class="breadcrumb__sep">/</span>
    <span><?= te('My Profile') ?></span>
</div>

<div class="page-header mb-3">
    <h1 class="page-header__title"><?= te($pageTitle ?? 'My Profile') ?></h1>
    <p class="text-sm text-muted"><?= te('View your account details and security settings.') ?></p>
</div>

<div class="d-flex flex-wrap gap-3" style="align-items:flex-start;">
    <!-- Profile Card -->
    <div style="flex:0 0 300px; min-width:280px;">
        <div class="profile-card">
            <?php if (!empty($user['profile_photo'])): ?>
                <img src="<?= htmlspecialchars($user['profile_photo'], ENT_QUOTES, 'UTF-8') ?>" alt="Profile" class="profile-card__photo">
            <?php else: ?>
                <div class="avatar avatar--xl" style="margin:0 auto 1rem;">
                    <?= strtoupper(mb_substr($user['full_name'] ?? 'U', 0, 1)) ?>
                </div>
            <?php endif; ?>

            <div class="profile-card__name"><?= htmlspecialchars($user['full_name'] ?? te('User'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="profile-card__role"><?= te('ID:') ?> <?= htmlspecialchars($user['user_id_display'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
            <span class="badge <?= $roleBadge ?> mt-1"><?= te(ucwords(str_replace('_', ' ', $role))) ?></span>

            <div style="margin-top:1.5rem;" class="text-xs text-muted">
                <?= !empty($user['created_at']) ? htmlspecialchars(t('Member since {date}', ['date' => date('M d, Y', strtotime($user['created_at']))]), ENT_QUOTES, 'UTF-8') : te('Member since -') ?>
            </div>
        </div>
    </div>

    <!-- Details Card -->
    <div style="flex:1; min-width:320px;">
        <div class="card mb-3">
            <h3 style="margin-bottom:1rem; font-size:1.15rem;"><?= te('Contact & Personal Information') ?></h3>
            <ul class="profile-card__details">
                <li><span class="label"><?= te('Full Name') ?></span><span class="value"><?= htmlspecialchars($user['full_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                <li><span class="label"><?= te('Email Address') ?></span><span class="value"><?= htmlspecialchars($user['email'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                <li><span class="label"><?= te('Primary Phone') ?></span><span class="value"><?= htmlspecialchars($user['phone1'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                <li><span class="label"><?= te('Secondary Phone') ?></span><span class="value"><?= htmlspecialchars($user['phone2'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                <li><span class="label"><?= te('WhatsApp') ?></span><span class="value"><?= htmlspecialchars($user['whatsapp'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                <li><span class="label"><?= te('Home Number') ?></span><span class="value"><?= htmlspecialchars($user['home_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                <li><span class="label"><?= te('Date of Birth') ?></span><span class="value"><?= htmlspecialchars($user['date_of_birth'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                <li><span class="label"><?= te('Address') ?></span><span class="value"><?= htmlspecialchars($user['address'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></li>
                <li><span class="label"><?= te('District / Province') ?></span><span class="value"><?= htmlspecialchars(($user['district'] ?? '') . ' / ' . ($user['province'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></li>
            </ul>
        </div>

        <div class="card">
            <h3 style="margin-bottom:1rem; font-size:1.15rem;"><?= te('Account Security') ?></h3>
            <p class="text-sm text-muted mb-2"><?= te('Need to update your login credentials or reset your password?') ?></p>
            <a href="<?= BASE_URL ?>/first-login-password" class="btn btn--secondary btn--sm"><?= te('Change Password') ?></a>
        </div>
    </div>
</div>
