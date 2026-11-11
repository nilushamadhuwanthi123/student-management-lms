<?php
/**
 * 1.1 Create User View — Admins, Coordinators, Staff, Teachers
 * Document 1 Page 2-3 Specifications
 */

$institutes = $institutes ?? [];
$classes    = $classes ?? [];
$roles      = $roles ?? [];
$roleColors = $roleColors ?? \User::ROLE_COLORS;
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Create New User') ?></h1>
        <p class="text-sm text-muted"><?= te('Register a new Administrator, Coordinator, Staff, or Teacher account') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/users" class="btn btn--ghost">
            &larr; <?= te('Back to Users') ?>
        </a>
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/users/create" enctype="multipart/form-data" id="createUserForm">
    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

    <!-- Auto Registration Banner (Document 1 Page 2) -->
    <div class="alert alert--info mb-3" style="display:flex; align-items:flex-start; gap:0.75rem; border-left:4px solid var(--primary);">
        <span style="font-size:1.25rem;">&#9432;</span>
        <div>
            <strong><?= te('Auto Registration Number:') ?></strong>
            <p style="margin:0.25rem 0 0 0; font-size:0.875rem;">
                <?= te('A unique Registration Number (e.g.') ?> <code>ADM20260001</code> / <code>TCH20260001</code>) <?= te('will be automatically generated and shown after saving. The user uses it to login and will set their own password on first login.') ?>
            </p>
        </div>
    </div>

    <!-- 1. Profile Photo -->
    <div class="card mb-3">
        <h3 class="mb-2"><?= te('1. Profile Photo') ?></h3>
        <div style="display:flex; align-items:center; gap:1.5rem; flex-wrap:wrap;">
            <div id="photoPreviewContainer" style="width:90px; height:90px; border-radius:50%; border:2px dashed var(--border); display:flex; align-items:center; justify-content:center; background:var(--surface-sunken); overflow:hidden;">
                <span id="photoPreviewText" style="font-size:0.75rem; color:var(--text-muted); text-align:center;"><?= te('Preview') ?></span>
                <img id="photoPreviewImg" src="" alt="" style="display:none; width:100%; height:100%; object-fit:cover;">
            </div>
            <div style="flex:1; min-width:240px;">
                <label class="form-label" for="profile_photo"><?= te('Upload Photo') ?></label>
                <input type="file" id="profile_photo" name="profile_photo" class="form-control" accept="image/jpeg,image/png,image/webp" onchange="previewUserPhoto(this)">
                <div class="form-hint text-xs"><?= te('Recommended size: 400x400 JPG or PNG. Max 2MB.') ?></div>
            </div>
        </div>
    </div>

    <!-- 2. Personal Details -->
    <div class="card mb-3">
        <h3 class="mb-2"><?= te('2. Personal Details') ?></h3>
        
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Role* -->
            <div class="form-group">
                <label class="form-label" for="userRole"><?= te('Role') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <select id="userRole" name="role" class="form-control" required onchange="handleRoleChange(this.value)">
                    <option value=""><?= te('-- Select Role --') ?></option>
                    <option value="admin"><?= te('Administrator (ADM)') ?></option>
                    <option value="coordinator"><?= te('Coordinator (COD)') ?></option>
                    <option value="staff"><?= te('Staff (STF)') ?></option>
                    <option value="teacher"><?= te('Teacher (TCH)') ?></option>
                </select>
                <div id="rolePrefixHint" class="form-hint text-xs mt-1" style="color:var(--primary); font-weight:600;">
                    <?= te('Select role to see assigned ID prefix.') ?>
                </div>
            </div>

            <!-- Full Name* -->
            <div class="form-group">
                <label class="form-label" for="full_name"><?= te('Full Name') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <input type="text" id="full_name" name="full_name" class="form-control" placeholder="e.g. Sunil Rathnayake" required>
            </div>

            <!-- Date of Birth -->
            <div class="form-group">
                <label class="form-label" for="date_of_birth"><?= te('Date of Birth') ?></label>
                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control">
            </div>

            <!-- Province -->
            <div class="form-group">
                <label class="form-label" for="province"><?= te('Province') ?></label>
                <input type="text" id="province" name="province" class="form-control" placeholder="e.g. Western Province">
            </div>

            <!-- District -->
            <div class="form-group">
                <label class="form-label" for="district"><?= te('District') ?></label>
                <input type="text" id="district" name="district" class="form-control" placeholder="e.g. Colombo">
            </div>
        </div>

        <!-- Address -->
        <div class="form-group mt-2">
            <label class="form-label" for="address"><?= te('Address') ?></label>
            <input type="text" id="address" name="address" class="form-control" placeholder="e.g. No. 45, Temple Road, Nugegoda">
        </div>

        <!-- Notes -->
        <div class="form-group mt-2">
            <label class="form-label" for="notes"><?= te('Notes') ?></label>
            <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="<?= te('Optional internal notes...') ?>"></textarea>
        </div>
    </div>

    <!-- 3. Contact Details -->
    <div class="card mb-3">
        <h3 class="mb-2"><?= te('3. Contact Details') ?></h3>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Email Address -->
            <div class="form-group">
                <label class="form-label" for="email"><?= te('Email Address') ?></label>
                <input type="email" id="email" name="email" class="form-control" placeholder="e.g. sunil.rathnayake@lms.lk">
                <div class="form-hint text-xs"><?= te('Used for notifications and login recovery.') ?></div>
            </div>

            <!-- Personal Number 1* -->
            <div class="form-group">
                <label class="form-label" for="phone1"><?= te('Personal Number 1') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <input type="tel" id="phone1" name="phone1" class="form-control" placeholder="e.g. +94771234567" required>
            </div>

            <!-- Personal Number 2 (optional) -->
            <div class="form-group">
                <label class="form-label" for="phone2"><?= te('Personal Number 2 (optional)') ?></label>
                <input type="tel" id="phone2" name="phone2" class="form-control" placeholder="e.g. +94112345678">
            </div>

            <!-- WhatsApp Number* -->
            <div class="form-group">
                <label class="form-label" for="whatsapp"><?= te('WhatsApp Number') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <input type="tel" id="whatsapp" name="whatsapp" class="form-control" placeholder="e.g. +94771234567" required>
            </div>

            <!-- Home Number (Emergency Contact) -->
            <div class="form-group">
                <label class="form-label" for="home_number"><?= te('Home Number (Emergency Contact)') ?></label>
                <input type="tel" id="home_number" name="home_number" class="form-control" placeholder="e.g. +94112853456">
            </div>
        </div>
    </div>

    <!-- 4. Academic Details -->
    <div class="card mb-3">
        <h3 class="mb-2"><?= te('4. Academic Details') ?></h3>

        <!-- Institute* -->
        <div class="form-group mb-3" style="max-width:500px;">
            <label class="form-label" for="instituteSelector"><?= te('Institute') ?> <span class="required" style="color:var(--danger);">*</span></label>
            <select id="instituteSelector" name="institute_id" class="form-control" required onchange="filterClassesByInstitute(this.value)">
                <option value=""><?= te('-- Select Active Institute --') ?></option>
                <?php foreach ($institutes as $inst): ?>
                    <option value="<?= (int) $inst['id'] ?>">
                        <?= htmlspecialchars($inst['name'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($inst['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-hint text-xs"><?= te('Shows all active Institutes created in Institute page.') ?></div>
        </div>

        <!-- Classes Checkboxes (Document 1 Page 3 Screenshot & OCR) -->
        <div class="form-group">
            <label class="form-label"><?= te('Classes (Check all classes user is attached to):') ?></label>
            <p class="text-xs text-muted mb-2"><?= te('Only showing active classes related to the selected institute above.') ?></p>

            <div id="classesCheckboxContainer" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:0.75rem; padding:1rem; background:var(--surface-sunken); border:1px solid var(--border); border-radius:var(--radius);">
                <?php if (!empty($classes)): ?>
                    <?php foreach ($classes as $cls): ?>
                        <div class="class-checkbox-item" data-institute-id="<?= (int) $cls['institute_id'] ?>" style="display:none; background:var(--surface); padding:0.75rem; border:1px solid var(--border); border-radius:var(--radius-sm);">
                            <label style="display:flex; align-items:flex-start; gap:0.6rem; cursor:pointer; margin:0;">
                                <input type="checkbox" name="classes[]" value="<?= (int) $cls['id'] ?>" style="margin-top:0.25rem;">
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
                <?php endif; ?>
                <div id="noClassesAlert" class="text-sm text-muted" style="grid-column:1/-1;">
                    <?= te('Please select an institute to view associated classes.') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Buttons -->
    <div class="d-flex justify-end gap-1 mb-4">
        <a href="<?= BASE_URL ?>/users" class="btn btn--ghost"><?= te('Cancel') ?></a>
        <button type="submit" class="btn btn--primary" style="padding:0.65rem 1.5rem;">
            <?= te('Save User Record') ?>
        </button>
    </div>
</form>

<script>
function handleRoleChange(role) {
    var hint = document.getElementById('rolePrefixHint');
    var prefixes = {
        'admin': 'ADM - User ID will be ADM2026XXXX',
        'coordinator': 'COD - User ID will be COD2026XXXX',
        'staff': 'STF - User ID will be STF2026XXXX',
        'teacher': 'TCH - User ID will be TCH2026XXXX'
    };
    if (prefixes[role]) {
        hint.textContent = prefixes[role];
    } else {
        hint.textContent = 'Select role to see assigned ID prefix.';
    }
}

function filterClassesByInstitute(instId) {
    var items = document.querySelectorAll('.class-checkbox-item');
    var noClassAlert = document.getElementById('noClassesAlert');
    var visibleCount = 0;

    items.forEach(function(item) {
        if (!instId || item.getAttribute('data-institute-id') === instId) {
            item.style.display = 'block';
            visibleCount++;
        } else {
            item.style.display = 'none';
            var chk = item.querySelector('input[type="checkbox"]');
            if (chk) chk.checked = false;
        }
    });

    if (visibleCount === 0) {
        noClassAlert.style.display = 'block';
        noClassAlert.textContent = instId ? 'No active classes found for this institute.' : 'Please select an institute to view associated classes.';
    } else {
        noClassAlert.style.display = 'none';
    }
}

function previewUserPhoto(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('photoPreviewImg');
            var txt = document.getElementById('photoPreviewText');
            img.src = e.target.result;
            img.style.display = 'block';
            txt.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
