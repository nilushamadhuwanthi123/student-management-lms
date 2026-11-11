<?php
/**
 * 1.1 Edit User View — Admins, Coordinators, Staff, Teachers
 */

$user            = $user ?? [];
$institutes      = $institutes ?? [];
$classes         = $classes ?? [];
$assignedClasses = $assignedClasses ?? [];
$roles           = $roles ?? [];
$roleColors      = $roleColors ?? \User::ROLE_COLORS;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Edit User') ?> - <?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-muted"><?= te('User ID:') ?> <strong><?= htmlspecialchars($user['user_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong> &bull; <?= te('Role:') ?> <strong><?= te(ucfirst($user['role'] ?? '')) ?></strong></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/users" class="btn btn--ghost">
            &larr; <?= te('Back to Users') ?>
        </a>
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/users/<?= (int) $user['id'] ?>/edit" enctype="multipart/form-data">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

    <!-- 1. Profile Photo -->
    <div class="card mb-3">
        <h3 class="mb-2"><?= te('1. Profile Photo') ?></h3>
        <div style="display:flex; align-items:center; gap:1.5rem; flex-wrap:wrap;">
            <div style="width:90px; height:90px; border-radius:50%; border:2px solid var(--border); overflow:hidden; background:var(--surface-sunken);">
                <?php if (!empty($user['profile_photo'])): ?>
                    <img id="editPhotoPreview" src="<?= htmlspecialchars($user['profile_photo'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <div id="editPhotoPreviewPlaceholder" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:700; color:var(--primary);">
                        <?= strtoupper(mb_substr($user['full_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <img id="editPhotoPreview" src="" alt="" style="display:none; width:100%; height:100%; object-fit:cover;">
                <?php endif; ?>
            </div>
            <div style="flex:1; min-width:240px;">
                <label class="form-label" for="profile_photo"><?= te('Change Photo') ?></label>
                <input type="file" id="profile_photo" name="profile_photo" class="form-control" accept="image/jpeg,image/png,image/webp" onchange="previewEditPhoto(this)">
                <div class="form-hint text-xs"><?= te('Leave empty to keep existing photo.') ?></div>
            </div>
        </div>
    </div>

    <!-- 2. Personal Details -->
    <div class="card mb-3">
        <h3 class="mb-2"><?= te('2. Personal Details') ?></h3>
        
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Full Name -->
            <div class="form-group">
                <label class="form-label" for="full_name"><?= te('Full Name') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <input type="text" id="full_name" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <!-- Date of Birth -->
            <div class="form-group">
                <label class="form-label" for="date_of_birth"><?= te('Date of Birth') ?></label>
                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($user['date_of_birth'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <!-- Status -->
            <div class="form-group">
                <label class="form-label" for="status"><?= te('Account Status') ?></label>
                <select id="status" name="status" class="form-control">
                    <option value="active" <?= ($user['status'] ?? '') === 'active' ? 'selected' : '' ?>><?= te('Active') ?></option>
                    <option value="pending_first_login" <?= ($user['status'] ?? '') === 'pending_first_login' ? 'selected' : '' ?>><?= te('Pending first login') ?></option>
                    <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>><?= te('Inactive') ?></option>
                </select>
            </div>

            <!-- Province -->
            <div class="form-group">
                <label class="form-label" for="province"><?= te('Province') ?></label>
                <input type="text" id="province" name="province" class="form-control" value="<?= htmlspecialchars($user['province'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <!-- District -->
            <div class="form-group">
                <label class="form-label" for="district"><?= te('District') ?></label>
                <input type="text" id="district" name="district" class="form-control" value="<?= htmlspecialchars($user['district'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <!-- Address -->
        <div class="form-group mt-2">
            <label class="form-label" for="address"><?= te('Address') ?></label>
            <input type="text" id="address" name="address" class="form-control" value="<?= htmlspecialchars($user['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <!-- Notes -->
        <div class="form-group mt-2">
            <label class="form-label" for="notes"><?= te('Notes') ?></label>
            <textarea id="notes" name="notes" class="form-control" rows="2"><?= htmlspecialchars($user['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>

    <!-- 3. Contact Details -->
    <div class="card mb-3">
        <h3 class="mb-2"><?= te('3. Contact Details') ?></h3>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Email -->
            <div class="form-group">
                <label class="form-label" for="email"><?= te('Email Address') ?></label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <!-- Personal Number 1 -->
            <div class="form-group">
                <label class="form-label" for="phone1"><?= te('Personal Number 1') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <input type="tel" id="phone1" name="phone1" class="form-control" value="<?= htmlspecialchars($user['phone1'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <!-- Personal Number 2 -->
            <div class="form-group">
                <label class="form-label" for="phone2"><?= te('Personal Number 2 (optional)') ?></label>
                <input type="tel" id="phone2" name="phone2" class="form-control" value="<?= htmlspecialchars($user['phone2'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <!-- WhatsApp Number -->
            <div class="form-group">
                <label class="form-label" for="whatsapp"><?= te('WhatsApp Number') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <input type="tel" id="whatsapp" name="whatsapp" class="form-control" value="<?= htmlspecialchars($user['whatsapp'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <!-- Home Number -->
            <div class="form-group">
                <label class="form-label" for="home_number"><?= te('Home Number (Emergency Contact)') ?></label>
                <input type="tel" id="home_number" name="home_number" class="form-control" value="<?= htmlspecialchars($user['home_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>
    </div>

    <!-- Branch membership: decides which branch's students, classes,
         payments and reports this account can see. Super admin only. -->
    <?php $assignedBranches = $assignedBranches ?? []; ?>
    <?php if (!empty($canEditBranches)): ?>
    <div class="card mb-3">
        <h3 class="mb-2"><?= te('Branches') ?></h3>
        <p class="text-sm text-muted" style="margin:0 0 .75rem;">
            <?= te('Admins, coordinators and staff see only the branches ticked here. Teachers are limited to the classes they teach.') ?>
        </p>
        <input type="hidden" name="branches_submitted" value="1">
        <div style="display:flex; flex-wrap:wrap; gap:.6rem;">
            <?php foreach ($institutes as $inst): ?>
                <label style="display:flex; gap:.45rem; align-items:center; padding:.5rem .8rem; border:1px solid var(--border); border-radius:var(--radius-sm); cursor:pointer;">
                    <input type="checkbox" name="branches[]" value="<?= (int) $inst['id'] ?>" <?= in_array((int) $inst['id'], $assignedBranches, true) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($inst['name'], ENT_QUOTES, 'UTF-8') ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php elseif ($assignedBranches !== []): ?>
    <div class="card mb-3">
        <h3 class="mb-2"><?= te('Branches') ?></h3>
        <div class="text-sm">
            <?= htmlspecialchars(implode(', ', array_map(static fn (array $i): string => $i['name'], array_filter($institutes, static fn (array $i): bool => in_array((int) $i['id'], $assignedBranches, true)))), ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 4. Academic Details (Attached Classes) -->
    <div class="card mb-3">
        <h3 class="mb-2"><?= te('4. Attached Classes') ?></h3>

        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:0.75rem; padding:1rem; background:var(--surface-sunken); border:1px solid var(--border); border-radius:var(--radius);">
            <?php if (!empty($classes)): ?>
                <?php foreach ($classes as $cls): 
                    $isAssigned = in_array((int) $cls['id'], array_map('intval', $assignedClasses), true);
                ?>
                    <div style="background:var(--surface); padding:0.75rem; border:1px solid var(--border); border-radius:var(--radius-sm);">
                        <label style="display:flex; align-items:flex-start; gap:0.6rem; cursor:pointer; margin:0;">
                            <input type="checkbox" name="classes[]" value="<?= (int) $cls['id'] ?>" <?= $isAssigned ? 'checked' : '' ?> style="margin-top:0.25rem;">
                            <div>
                                <div style="font-weight:600; font-size:0.875rem;">
                                    <?= htmlspecialchars($cls['class_name'] ?? $cls['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="text-xs text-muted">
                                    <?= htmlspecialchars($cls['subject'] ?? '', ENT_QUOTES, 'UTF-8') ?> &bull; <?= htmlspecialchars($cls['grade'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        </label>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-sm text-muted"><?= te('No classes available.') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Submit Buttons -->
    <div class="d-flex justify-between items-center mb-4">
        <a href="<?= BASE_URL ?>/users/<?= (int) $user['id'] ?>/id-card" target="_blank" class="btn btn--secondary">
            <?= te('Print ID Card') ?>
        </a>
        <div class="d-flex gap-1">
            <a href="<?= BASE_URL ?>/users" class="btn btn--ghost"><?= te('Cancel') ?></a>
            <button type="submit" class="btn btn--primary">
                <?= te('Save Changes') ?>
            </button>
        </div>
    </div>
</form>

<?php if ((int) $user['id'] !== (int) session_get('user_id') && in_array(session_get('user_role'), ['super_admin', 'admin'], true)
          && ($user['role'] !== 'super_admin' || session_get('user_role') === 'super_admin')): ?>
<div class="card" style="margin-top:1rem;"><div class="card__body">
    <h2 style="font-size:1rem; margin:0 0 .5rem;"><?= te('Forgotten password') ?></h2>
    <p class="text-sm text-muted"><?= te('Confirm who is asking first (in person, or by calling the phone number on file). The code is shown once and works for 30 minutes.') ?></p>
    <form method="POST" action="<?= BASE_URL ?>/users/<?= (int) $user['id'] ?>/reset-code">
        <?= csrfField() ?>
        <button type="submit" class="btn btn--secondary btn--sm"><?= te('Issue a password reset code') ?></button>
    </form>
</div></div>
<?php endif; ?>

<script>
function previewEditPhoto(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('editPhotoPreview');
            var ph = document.getElementById('editPhotoPreviewPlaceholder');
            img.src = e.target.result;
            img.style.display = 'block';
            if (ph) ph.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
