<?php
/**
 * 1.2 Register Institute View
 * Document 1 Page 5 Specifications:
 * Institute Name*, Coordinator (DDL), Location*, Phone No 1, Phone No 2, Notes, Status (DDL), Created Date
 */

$coordinators = $coordinators ?? [];
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title">Register New Institute</h1>
        <p class="text-sm text-muted">Add a new campus, education center, or branch to the LMS network</p>
    </div>
    <div class="page-header__actions">
        <a href="/institutes" class="btn btn--ghost">
            &larr; Back to Institutes
        </a>
    </div>
</div>

<div class="card" style="max-width:800px;">
    <form method="POST" action="/institutes/create">
        <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>

        <!-- Institute Name* -->
        <div class="form-group mb-2">
            <label class="form-label" for="instName">Institute Name <span class="required" style="color:var(--danger);">*</span></label>
            <input type="text" id="instName" name="name" class="form-control" placeholder="e.g. Sepala Institute - Pannipitiya" required>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
            <!-- Coordinator (DDL - data flow from created coordinators) -->
            <div class="form-group">
                <label class="form-label" for="coordinator">Assigned Coordinator (DDL)</label>
                <select id="coordinator" name="coordinator_id" class="form-control">
                    <option value="">-- Select Coordinator (Optional) --</option>
                    <?php foreach ($coordinators as $c): ?>
                        <option value="<?= (int) $c['id'] ?>">
                            <?= htmlspecialchars($c['full_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($c['user_id_display'] ?? '', ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint text-xs">Shows active coordinators created in User Management.</div>
            </div>

            <!-- Status (DDL: Active / Inactive) -->
            <div class="form-group">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="active" selected>Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <!-- Location* -->
        <div class="form-group mt-2">
            <label class="form-label" for="location">Location / Address <span class="required" style="color:var(--danger);">*</span></label>
            <input type="text" id="location" name="location" class="form-control" placeholder="e.g. No. 120, High Level Road, Pannipitiya" required>
        </div>

        <!-- Phone 1 & Phone 2 -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;" class="mt-2">
            <div class="form-group">
                <label class="form-label" for="phone1">Phone No 1</label>
                <input type="tel" id="phone1" name="phone1" class="form-control" placeholder="e.g. +94112853456">
            </div>
            <div class="form-group">
                <label class="form-label" for="phone2">Phone No 2</label>
                <input type="tel" id="phone2" name="phone2" class="form-control" placeholder="e.g. +94112853457">
            </div>
        </div>

        <!-- Notes -->
        <div class="form-group mt-2">
            <label class="form-label" for="notes">Notes</label>
            <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Optional details, campus facilities, notes..."></textarea>
        </div>

        <div class="d-flex justify-end gap-1 mt-4 pt-2" style="border-top:1px solid var(--border);">
            <a href="/institutes" class="btn btn--ghost">Cancel</a>
            <button type="submit" class="btn btn--primary">
                Save Institute
            </button>
        </div>
    </form>
</div>
