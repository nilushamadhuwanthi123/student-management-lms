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
        <h1 class="page-header__title">Create New Class</h1>
        <p class="text-sm text-muted">Configure academic curriculum, teacher assignment, venue, and fee structure</p>
    </div>
    <div class="page-header__actions">
        <a href="/classes" class="btn btn--ghost">
            &larr; Back to Classes
        </a>
    </div>
</div>

<div class="card" style="max-width:900px;">
    <form method="POST" action="/classes/create">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

        <!-- Basic Class Information -->
        <h3 class="mb-2">1. Class Information</h3>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Class Name* -->
            <div class="form-group">
                <label class="form-label" for="className">Class Name <span class="required" style="color:var(--danger);">*</span></label>
                <input type="text" id="className" name="class_name" class="form-control" placeholder="e.g. 2026 A/L Combined Maths - Batch A" required>
            </div>

            <!-- Subject* -->
            <div class="form-group">
                <label class="form-label" for="subject">Subject <span class="required" style="color:var(--danger);">*</span></label>
                <input type="text" id="subject" name="subject" class="form-control" placeholder="e.g. Combined Mathematics" required>
            </div>

            <!-- Medium -->
            <div class="form-group">
                <label class="form-label" for="medium">Medium</label>
                <select id="medium" name="medium" class="form-control">
                    <?php foreach ($mediums as $m): ?>
                        <option value="<?= $m ?>"><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Grade -->
            <div class="form-group">
                <label class="form-label" for="grade">Grade</label>
                <select id="grade" name="grade" class="form-control">
                    <?php foreach ($grades as $g): ?>
                        <option value="<?= $g ?>"><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Venue & Staffing -->
        <h3 class="mb-2 mt-4 pt-3" style="border-top:1px solid var(--border);">2. Venue & Staffing</h3>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Institute (DDL) -->
            <div class="form-group">
                <label class="form-label" for="instituteId">Institute <span class="required" style="color:var(--danger);">*</span></label>
                <select id="instituteId" name="institute_id" class="form-control" required>
                    <option value="">-- Select Institute --</option>
                    <?php foreach ($institutes as $inst): ?>
                        <option value="<?= (int) $inst['id'] ?>">
                            <?= htmlspecialchars($inst['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Room -->
            <div class="form-group">
                <label class="form-label" for="room">Room / Hall</label>
                <input type="text" id="room" name="room" class="form-control" placeholder="e.g. Hall A1 / Online">
            </div>

            <!-- Teacher (DDL) -->
            <div class="form-group">
                <label class="form-label" for="teacherId">Teacher</label>
                <select id="teacherId" name="teacher_id" class="form-control">
                    <option value="">-- Assign Teacher --</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int) $t['id'] ?>">
                            <?= htmlspecialchars($t['full_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($t['user_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Staff Member (DDL) -->
            <div class="form-group">
                <label class="form-label" for="staffId">Staff Member</label>
                <select id="staffId" name="staff_id" class="form-control">
                    <option value="">-- Assign Staff Member --</option>
                    <?php foreach ($staff as $sf): ?>
                        <option value="<?= (int) $sf['id'] ?>">
                            <?= htmlspecialchars($sf['full_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($sf['user_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Schedule -->
        <h3 class="mb-2 mt-4 pt-3" style="border-top:1px solid var(--border);">3. Schedule & Timing</h3>
        
        <!-- Schedule Days (Select multiple days from Monday to Sunday) -->
        <div class="form-group mb-3">
            <label class="form-label">Schedule Days (Select active days):</label>
            <div style="display:flex; flex-wrap:wrap; gap:0.75rem; margin-top:0.35rem;">
                <?php foreach ($daysOfWeek as $day): ?>
                    <label style="display:flex; align-items:center; gap:0.4rem; padding:0.4rem 0.75rem; background:var(--surface-sunken); border:1px solid var(--border); border-radius:var(--radius-sm); cursor:pointer;">
                        <input type="checkbox" name="schedule_days[]" value="<?= $day ?>">
                        <span style="font-size:0.875rem; font-weight:600;"><?= $day ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Start Time -->
            <div class="form-group">
                <label class="form-label" for="startTime">Start Time</label>
                <input type="time" id="startTime" name="start_time" class="form-control">
            </div>

            <!-- End Time -->
            <div class="form-group">
                <label class="form-label" for="endTime">End Time</label>
                <input type="time" id="endTime" name="end_time" class="form-control">
            </div>
        </div>

        <!-- Fees & Revenue Split -->
        <h3 class="mb-2 mt-4 pt-3" style="border-top:1px solid var(--border);">4. Fees & Revenue</h3>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Monthly Fee (Rs.)* -->
            <div class="form-group">
                <label class="form-label" for="monthlyFee">Monthly Fee (Rs.) <span class="required" style="color:var(--danger);">*</span></label>
                <input type="number" step="0.01" id="monthlyFee" name="monthly_fee" class="form-control" placeholder="e.g. 3500.00" required>
            </div>

            <!-- Teacher Revenue Split (%) -->
            <div class="form-group">
                <label class="form-label" for="split">Teacher Revenue Split (%)</label>
                <input type="number" step="0.01" min="0" max="100" id="split" name="teacher_revenue_split" class="form-control" placeholder="e.g. 70.00">
                <div class="form-hint text-xs">Percentage of collected fee allocated to teacher.</div>
            </div>

            <!-- Class Status -->
            <div class="form-group">
                <label class="form-label" for="classStatus">Class Status</label>
                <select id="classStatus" name="status" class="form-control">
                    <option value="active" selected>Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <!-- Notes -->
        <div class="form-group mt-3">
            <label class="form-label" for="notes">Notes</label>
            <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Course syllabus notes, prerequisites..."></textarea>
        </div>

        <div class="d-flex justify-end gap-1 mt-4 pt-2" style="border-top:1px solid var(--border);">
            <a href="/classes" class="btn btn--ghost">Cancel</a>
            <button type="submit" class="btn btn--primary">
                Create Class
            </button>
        </div>
    </form>
</div>
