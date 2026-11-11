<?php
/**
 * 1.3 Create Class View
 * Document 1 Page 5-6 Specifications
 */

$institutes = $institutes ?? [];
$teachers   = $teachers ?? [];
$staff      = $staff ?? [];
$daysOfWeek = $daysOfWeek ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$grades     = $grades ?? [];
$mediums    = $mediums ?? ['Sinhala', 'English', 'Tamil'];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Create New Class') ?></h1>
        <p class="text-sm text-muted"><?= te('Configure academic curriculum, teacher assignment, venue, and fee structure') ?></p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes" class="btn btn--ghost">
            &larr; <?= te('Back to Classes') ?>
        </a>
    </div>
</div>

<div class="card" style="max-width:900px;">
    <form method="POST" action="<?= BASE_URL ?>/classes/create">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

        <!-- Basic Class Information -->
        <h3 class="mb-2"><?= te('1. Class Information') ?></h3>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Class Name* -->
            <div class="form-group">
                <label class="form-label" for="className"><?= te('Class Name') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <input type="text" id="className" name="class_name" class="form-control" placeholder="<?= te('e.g. 2026 A/L Combined Maths - Batch A') ?>" required>
            </div>

            <!-- Subject* -->
            <div class="form-group">
                <label class="form-label" for="subject"><?= te('Subject') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <input type="text" id="subject" name="subject" class="form-control" placeholder="<?= te('e.g. Combined Mathematics') ?>" required>
            </div>

            <!-- Medium -->
            <div class="form-group">
                <label class="form-label" for="medium"><?= te('Medium') ?></label>
                <select id="medium" name="medium" class="form-control">
                    <?php foreach ($mediums as $m): ?>
                        <option value="<?= $m ?>"><?= te($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Grade -->
            <div class="form-group">
                <label class="form-label" for="grade"><?= te('Grade') ?></label>
                <select id="grade" name="grade" class="form-control">
                    <?php foreach ($grades as $g): ?>
                        <option value="<?= $g ?>"><?= te($g) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Venue & Staffing -->
        <h3 class="mb-2 mt-4 pt-3" style="border-top:1px solid var(--border);"><?= te('2. Venue & Staffing') ?></h3>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Institute (DDL) -->
            <div class="form-group">
                <label class="form-label" for="instituteId"><?= te('Institute') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <select id="instituteId" name="institute_id" class="form-control" required>
                    <option value=""><?= te('-- Select Institute --') ?></option>
                    <?php foreach ($institutes as $inst): ?>
                        <option value="<?= (int) $inst['id'] ?>">
                            <?= htmlspecialchars($inst['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Room -->
            <div class="form-group">
                <label class="form-label" for="room"><?= te('Room / Hall') ?></label>
                <input type="text" id="room" name="room" class="form-control" placeholder="<?= te('e.g. Hall A1 / Online') ?>">
            </div>

            <!-- Delivery -->
            <div class="form-group">
                <label class="form-label" for="delivery_mode"><?= te('Delivery') ?></label>
                <select id="delivery_mode" name="delivery_mode" class="form-control">
                    <option value="physical" ><?= te('Physical (at the branch)') ?></option>
                    <option value="online" ><?= te('Online') ?></option>
                    <option value="hybrid" ><?= te('Both (physical and online)') ?></option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="meeting_url"><?= te('Online meeting link (private)') ?></label>
                <input type="url" id="meeting_url" name="meeting_url" class="form-control" placeholder="https://zoom.us/j/..." value="">
                <div class="form-hint text-xs"><?= te('Shown only to enrolled students whose fee is settled. Never on public pages.') ?></div>
            </div>
            <div class="form-group">
                <label class="form-label" for="capacity"><?= te('Capacity (optional)') ?></label>
                <input type="number" id="capacity" name="capacity" class="form-control" min="0" max="65000" value="">
            </div>

            <!-- Teacher (DDL) -->
            <div class="form-group">
                <label class="form-label" for="teacherId"><?= te('Teacher') ?></label>
                <select id="teacherId" name="teacher_id" class="form-control">
                    <option value=""><?= te('-- Assign Teacher --') ?></option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int) $t['id'] ?>">
                            <?= htmlspecialchars($t['full_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($t['user_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Staff Member (DDL) -->
            <div class="form-group">
                <label class="form-label" for="staffId"><?= te('Staff Member') ?></label>
                <select id="staffId" name="staff_id" class="form-control">
                    <option value=""><?= te('-- Assign Staff Member --') ?></option>
                    <?php foreach ($staff as $sf): ?>
                        <option value="<?= (int) $sf['id'] ?>">
                            <?= htmlspecialchars($sf['full_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($sf['user_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Schedule -->
        <h3 class="mb-2 mt-4 pt-3" style="border-top:1px solid var(--border);"><?= te('3. Schedule & Timing') ?></h3>
        
        <!-- Schedule Days (Select multiple days from Monday to Sunday) -->
        <div class="form-group mb-3">
            <label class="form-label"><?= te('Schedule Days (Select active days):') ?></label>
            <div style="display:flex; flex-wrap:wrap; gap:0.75rem; margin-top:0.35rem;">
                <?php foreach ($daysOfWeek as $day): ?>
                    <label style="display:flex; align-items:center; gap:0.4rem; padding:0.4rem 0.75rem; background:var(--surface-sunken); border:1px solid var(--border); border-radius:var(--radius-sm); cursor:pointer;">
                        <input type="checkbox" name="schedule_days[]" value="<?= $day ?>">
                        <span style="font-size:0.875rem; font-weight:600;"><?= te($day) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Start Time -->
            <div class="form-group">
                <label class="form-label" for="startTime"><?= te('Start Time') ?></label>
                <input type="time" id="startTime" name="start_time" class="form-control">
            </div>

            <!-- End Time -->
            <div class="form-group">
                <label class="form-label" for="endTime"><?= te('End Time') ?></label>
                <input type="time" id="endTime" name="end_time" class="form-control">
            </div>
        </div>

        <!-- Fees & Revenue Split -->
        <h3 class="mb-2 mt-4 pt-3" style="border-top:1px solid var(--border);"><?= te('4. Fees & Revenue') ?></h3>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Monthly Fee (Rs.)* -->
            <div class="form-group">
                <label class="form-label" for="monthlyFee"><?= te('Monthly Fee (Rs.)') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <input type="number" step="0.01" id="monthlyFee" name="monthly_fee" class="form-control" placeholder="e.g. 3500.00" required>
            </div>

            <!-- Teacher Revenue Split (%) -->
            <div class="form-group">
                <label class="form-label" for="split"><?= te('Teacher Revenue Split (%)') ?></label>
                <input type="number" step="0.01" min="0" max="100" id="split" name="teacher_revenue_split" class="form-control" placeholder="e.g. 70.00">
                <div class="form-hint text-xs"><?= te('Percentage of collected fee allocated to teacher.') ?></div>
            </div>

            <!-- Class Status -->
            <div class="form-group">
                <label class="form-label" for="classStatus"><?= te('Class Status') ?></label>
                <select id="classStatus" name="status" class="form-control">
                    <option value="active" selected><?= te('Active') ?></option>
                    <option value="inactive"><?= te('Inactive') ?></option>
                </select>
            </div>
        </div>

        <!-- Notes -->
        <div class="form-group mt-3">
            <label class="form-label" for="notes"><?= te('Notes') ?></label>
            <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="<?= te('Course syllabus notes, prerequisites...') ?>"></textarea>
        </div>

        <div class="d-flex justify-end gap-1 mt-4 pt-2" style="border-top:1px solid var(--border);">
            <a href="<?= BASE_URL ?>/classes" class="btn btn--ghost"><?= te('Cancel') ?></a>
            <button type="submit" class="btn btn--primary">
                <?= te('Create Class') ?>
            </button>
        </div>
    </form>
</div>
