<?php
/**
 * Public self-service enrolment: pick a branch + class, fill in the
 * student and guardian details, upload a bank deposit slip as proof of
 * payment. It goes into the branch's Enrolment Requests queue --
 * EnrollmentController::store() -- and waits for the admin/coordinator
 * to approve (which creates the account) or reject it.
 */
use App\Services\SiteContentService;

$branches = $branches ?? [];
$presetClassId = $presetClassId ?? null;
$presetInstituteId = $presetInstituteId ?? null;
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sc = new SiteContentService();
require __DIR__ . '/_site.php';
?>
<section class="ts-hero" style="padding:5.6rem 0 3rem;">
    <div class="ts-wrap">
        <div class="ts-eyebrow"><?= te('Enrol now') ?></div>
        <h1 style="max-width:22ch;"><?= te('Enrol in a class') ?></h1>
        <p><?= te('Pick a branch and class, fill in the details, and upload your bank deposit slip. The branch office reviews it and sets up the login.') ?></p>
    </div>
</section>

<section class="ts-section">
    <div class="ts-wrap">
        <?php if ($branches === []): ?>
            <p class="ts-muted"><?= te('Online enrolment opens soon. Please contact us through the Contact page.') ?></p>
        <?php else: ?>
        <form method="POST" action="<?= BASE_URL ?>/enroll" enctype="multipart/form-data" class="ts-card" style="max-width:760px; margin:0 auto; position:relative;">
            <?= csrfField() ?>
            <input type="hidden" name="started_at" value="<?= time() ?>">
            <div class="ts-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <h3 style="margin:0 0 1rem;"><?= te('Branch and class') ?></h3>
            <div class="ts-form">
                <div>
                    <label for="en-branch"><?= te('Branch') ?> *</label>
                    <select id="en-branch" name="institute_id" required>
                        <option value=""><?= te('Choose') ?></option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int) $b['id'] ?>" <?= $presetInstituteId === (int) $b['id'] ? 'selected' : '' ?>><?= te($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="en-class"><?= te('Class') ?> *</label>
                    <select id="en-class" name="class_id" required>
                        <option value=""><?= te('Choose a branch first') ?></option>
                    </select>
                </div>
            </div>

            <h3 style="margin:1.4rem 0 1rem;"><?= te('Student') ?></h3>
            <div class="ts-form">
                <div><label for="en-sname"><?= te("Student's name") ?> *</label><input id="en-sname" name="student_name" maxlength="255" required></div>
                <div><label for="en-dob"><?= te('Date of birth (optional)') ?></label><input id="en-dob" type="date" name="date_of_birth"></div>
            </div>

            <h3 style="margin:1.4rem 0 1rem;"><?= te('Guardian') ?></h3>
            <div class="ts-form">
                <div><label for="en-gname"><?= te('Guardian name') ?> *</label><input id="en-gname" name="guardian_name" maxlength="255" required></div>
                <div><label for="en-gphone"><?= te('Guardian phone') ?> *</label><input id="en-gphone" name="guardian_phone" inputmode="tel" required placeholder="07X XXX XXXX"></div>
                <div><label for="en-gemail"><?= te('Guardian email (optional)') ?></label><input id="en-gemail" type="email" name="guardian_email" maxlength="255"></div>
                <div style="grid-column:1/-1;"><label for="en-addr"><?= te('Address (optional)') ?></label><textarea id="en-addr" name="address" rows="2" maxlength="500"></textarea></div>
            </div>

            <h3 style="margin:1.4rem 0 1rem;"><?= te('Payment') ?></h3>
            <p class="ts-muted" style="margin:0 0 .8rem;"><?= te('Pay the class fee by bank deposit/transfer, then upload a photo or PDF of the slip. The branch office checks it before the login is created.') ?></p>
            <div class="ts-form">
                <div>
                    <label for="en-sch"><?= te('Payment type') ?></label>
                    <select id="en-sch" name="scholarship_type">
                        <option value="full_payment"><?= te('Full Payment') ?></option>
                        <option value="half_payment"><?= te('Half Payment (50%)') ?></option>
                        <option value="free_card"><?= te('Free Card (100%)') ?></option>
                    </select>
                </div>
                <div><label for="en-amount"><?= te('Amount paid (optional)') ?></label><input id="en-amount" type="number" step="0.01" min="0" name="amount" placeholder="LKR"></div>
                <div><label for="en-paidon"><?= te('Date paid (optional)') ?></label><input id="en-paidon" type="date" name="paid_on"></div>
                <div><label for="en-bank"><?= te('Bank (optional)') ?></label><input id="en-bank" name="bank_name" maxlength="100"></div>
                <div><label for="en-ref"><?= te('Reference number (optional)') ?></label><input id="en-ref" name="bank_reference" maxlength="100"></div>
                <div style="grid-column:1/-1;">
                    <label for="en-slip"><?= te('Bank deposit slip') ?> *</label>
                    <input id="en-slip" type="file" name="slip" accept="image/jpeg,image/png,application/pdf" required>
                    <div class="form-hint text-xs"><?= te('JPG, PNG or PDF, up to 5MB.') ?></div>
                </div>
            </div>

            <p class="ts-muted" style="margin:1rem 0;"><?= te('These details are only used to review your enrolment and set up the student/parent logins.') ?></p>
            <button type="submit" class="ts-btn ts-btn--blue"><?= te('Send enrolment request') ?></button>
        </form>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    var branchSel = document.getElementById('en-branch');
    var classSel = document.getElementById('en-class');
    if (!branchSel || !classSel) { return; }
    var presetClass = <?= json_encode($presetClassId) ?>;
    var labels = {
        choose: <?= json_encode(te('Choose')) ?>,
        choose_branch: <?= json_encode(te('Choose a branch first')) ?>,
        none: <?= json_encode(te('No classes at this branch yet.')) ?>,
        failed: <?= json_encode(te('Failed to load classes. Please try again.')) ?>
    };

    function loadClasses(instituteId, selectClassId) {
        if (!instituteId) {
            classSel.innerHTML = '<option value="">' + labels.choose_branch + '</option>';
            return;
        }
        classSel.innerHTML = '<option value="">' + labels.choose + '&hellip;</option>';
        fetch(<?= json_encode(BASE_URL) ?> + '/enroll/classes/' + instituteId)
            .then(function (r) { return r.json(); })
            .then(function (classes) {
                if (!classes.length) {
                    classSel.innerHTML = '<option value="">' + labels.none + '</option>';
                    return;
                }
                var html = '<option value="">' + labels.choose + '</option>';
                classes.forEach(function (c) {
                    var sel = selectClassId && String(selectClassId) === String(c.id) ? ' selected' : '';
                    html += '<option value="' + c.id + '"' + sel + '>' + c.class_name + ' (' + c.grade + ')</option>';
                });
                classSel.innerHTML = html;
            })
            .catch(function () {
                classSel.innerHTML = '<option value="">' + labels.failed + '</option>';
            });
    }

    branchSel.addEventListener('change', function () { loadClasses(this.value, null); });
    if (branchSel.value) { loadClasses(branchSel.value, presetClass); }
})();
</script>
