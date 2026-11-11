<?php
/** Compose or edit a seminar/event. */
$institutes = $institutes ?? [];
$classes = $classes ?? [];
$grades = $grades ?? [];
$isGlobal = !empty($isGlobal);
$canTargetGrade = !empty($canTargetGrade);
$isEdit = !empty($isEdit);
$canEditAudience = $isEdit ? !empty($canEditAudience) : true;
$seminar = $seminar ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$v = static fn (string $key, string $default = '') => $e($seminar[$key] ?? $default);
$checked = static fn (string $key) => ($isEdit ? !empty($seminar[$key]) : in_array($key, ['for_students', 'for_parents'], true)) ? ' checked' : '';
$action = $isEdit ? (BASE_URL . '/seminars/' . (int) $seminar['id'] . '/edit') : (BASE_URL . '/seminars/create');
?>
<div class="page-header">
    <div><h1 class="page-header__title"><?= $isEdit ? te('Edit seminar') : te('New seminar') ?></h1></div>
    <div class="page-header__actions"><a href="<?= BASE_URL ?>/seminars" class="btn btn--ghost">&larr; <?= te('Seminars & Events') ?></a></div>
</div>
<form method="POST" action="<?= $action ?>" class="card" style="max-width:760px;" enctype="multipart/form-data">
    <?= csrfField() ?>
    <div class="card__body" style="display:grid; gap:1rem;">
        <div class="form-group"><label class="form-label" for="title"><?= te('Title') ?> *</label>
            <input type="text" id="title" name="title" class="form-control" maxlength="255" required value="<?= $v('title') ?>"></div>
        <div class="form-group"><label class="form-label" for="description"><?= te('Description') ?></label>
            <textarea id="description" name="description" class="form-control" rows="5"><?= $v('description') ?></textarea></div>
        <div class="form-row form-row--2">
            <div class="form-group"><label class="form-label" for="event_date"><?= te('Date') ?> *</label>
                <input type="date" id="event_date" name="event_date" class="form-control" required value="<?= $v('event_date') ?>"></div>
            <div class="form-group">
                <label class="form-label"><?= te('Time (optional)') ?></label>
                <div style="display:flex; gap:.5rem;">
                    <input type="time" name="start_time" class="form-control" aria-label="<?= te('Start time') ?>" value="<?= $e(substr((string) ($seminar['start_time'] ?? ''), 0, 5)) ?>">
                    <input type="time" name="end_time" class="form-control" aria-label="<?= te('End time') ?>" value="<?= $e(substr((string) ($seminar['end_time'] ?? ''), 0, 5)) ?>">
                </div>
            </div>
        </div>
        <div class="form-row form-row--2">
            <div class="form-group"><label class="form-label" for="venue"><?= te('Venue (optional)') ?></label>
                <input type="text" id="venue" name="venue" class="form-control" maxlength="255" placeholder="<?= te('e.g. Main Hall, Pannipitiya branch') ?>" value="<?= $v('venue') ?>"></div>
            <div class="form-group"><label class="form-label" for="online_url"><?= te('Online link (optional)') ?></label>
                <input type="url" id="online_url" name="online_url" class="form-control" placeholder="https://" value="<?= $v('online_url') ?>"></div>
        </div>
        <?php $coverSrc = \App\Services\SeminarService::coverSrc($seminar['cover_image_url'] ?? null); ?>
        <div class="form-group">
            <label class="form-label" for="cover_image_file"><?= te('Poster (optional)') ?></label>
            <?php if ($coverSrc !== ''): ?>
                <img src="<?= $e($coverSrc) ?>" alt="" style="display:block; max-width:260px; max-height:160px; object-fit:cover; border-radius:10px; margin-bottom:.5rem; border:1px solid var(--border);" onerror="this.style.display='none';">
            <?php endif; ?>
            <input type="file" id="cover_image_file" name="cover_image_file" class="form-control" accept="image/jpeg,image/png,image/webp">
            <div class="form-hint"><?= te('Upload a JPG, PNG or WEBP up to 3MB, or paste an image link below. A wide picture (about 16:9) looks best on the home page.') ?></div>
            <input type="text" inputmode="url" id="cover_image_url" name="cover_image_url" class="form-control" style="margin-top:.5rem;" placeholder="https://" aria-label="<?= te('Image link') ?>" value="<?= $v('cover_image_url') ?>">
        </div>
        <div class="form-group"><label class="form-label" for="max_capacity"><?= te('Capacity (optional -- leave blank for unlimited)') ?></label>
            <input type="number" id="max_capacity" name="max_capacity" class="form-control" min="1" step="1" placeholder="<?= te('e.g. 60') ?>" value="<?= $e($seminar['max_capacity'] ?? '') ?>">
            <div class="form-hint"><?= te('Once full, new registrations join a waiting list and are added automatically if a spot opens up.') ?></div></div>

        <?php if ($canEditAudience): ?>
        <div class="form-group">
            <label class="form-label" for="target_scope"><?= te('Who this is for') ?></label>
            <select id="target_scope" name="target_scope" class="form-control" onchange="
                document.getElementById('pickBranch').style.display=(this.value==='branch'||this.value==='grade')?'':'none';
                document.getElementById('pickGrade').style.display=this.value==='grade'?'':'none';
                document.getElementById('pickClass').style.display=this.value==='class'?'':'none';">
                <option value="class"<?= ($seminar['target_scope'] ?? 'class') === 'class' ? ' selected' : '' ?>><?= te('One class') ?></option>
                <?php if ($canTargetGrade && $grades !== []): ?><option value="grade"<?= ($seminar['target_scope'] ?? '') === 'grade' ? ' selected' : '' ?>><?= te('One grade (any branch, or just one)') ?></option><?php endif; ?>
                <?php if ($institutes !== []): ?><option value="branch"<?= ($seminar['target_scope'] ?? '') === 'branch' ? ' selected' : '' ?>><?= te('A whole branch') ?></option><?php endif; ?>
                <?php if ($isGlobal): ?><option value="all"<?= ($seminar['target_scope'] ?? '') === 'all' ? ' selected' : '' ?>><?= te('All branches') ?></option><?php endif; ?>
            </select>
        </div>
        <div class="form-group" id="pickClass" style="<?= ($seminar['target_scope'] ?? 'class') === 'class' ? '' : 'display:none;' ?>"><label class="form-label" for="class_id"><?= te('Class') ?></label>
            <select id="class_id" name="class_id" class="form-control"><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= ($seminar['class_id'] ?? null) == $c['id'] ? ' selected' : '' ?>><?= $e($c['class_name']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group" id="pickGrade" style="<?= ($seminar['target_scope'] ?? '') === 'grade' ? '' : 'display:none;' ?>"><label class="form-label" for="grade"><?= te('Grade') ?></label>
            <select id="grade" name="grade" class="form-control"><?php foreach ($grades as $g): ?><option value="<?= $e($g) ?>"<?= ($seminar['grade'] ?? '') === $g ? ' selected' : '' ?>><?= $e($g) ?></option><?php endforeach; ?></select></div>
        <div class="form-group" id="pickBranch" style="<?= in_array(($seminar['target_scope'] ?? ''), ['branch', 'grade'], true) ? '' : 'display:none;' ?>"><label class="form-label" for="institute_id"><?= te('Branch (leave as "every branch" when targeting a grade across all of them)') ?></label>
            <select id="institute_id" name="institute_id" class="form-control">
                <option value="0"><?= te('Every branch') ?></option>
                <?php foreach ($institutes as $i): ?><option value="<?= (int) $i['id'] ?>"<?= ($seminar['institute_id'] ?? null) == $i['id'] ? ' selected' : '' ?>><?= $e($i['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div>
            <div class="form-label"><?= te('Who should see it') ?></div>
            <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                <label><input type="checkbox" name="for_students" value="1"<?= $checked('for_students') ?>> <?= te('Students') ?></label>
                <label><input type="checkbox" name="for_parents" value="1"<?= $checked('for_parents') ?>> <?= te('Parents') ?></label>
                <label><input type="checkbox" name="for_staff" value="1"<?= $checked('for_staff') ?>> <?= te('Staff') ?></label>
            </div>
        </div>
        <?php else: ?>
        <div class="form-hint"><?= te('Who this is for and who should see it can no longer be changed -- it has already been published and announced.') ?></div>
        <?php endif; ?>

        <div>
            <label><input type="checkbox" name="registration_required" value="1"<?= $checked('registration_required') ?>> <?= te('Requires registration (people can RSVP from their own seminars list)') ?></label>
        </div>
        <div style="border:1px dashed var(--border-strong, #B4C5E0); border-radius:12px; padding:.8rem 1rem;">
            <label style="font-weight:600;"><input type="checkbox" name="show_on_website" value="1"<?= ($isEdit ? !empty($seminar['show_on_website']) : session_get('user_role') === 'super_admin') ? ' checked' : '' ?>> &#128227; <?= te('Show on the website home page') ?></label>
            <div class="form-hint" style="margin-top:.35rem;"><?= te('Once published, anyone visiting the website sees the title, date, time, venue, branch, poster and description until the day of the event. The online link is never shown there.') ?></div>
        </div>
        <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
            <?php if ($isEdit): ?>
                <button type="submit" class="btn btn--primary"><?= te('Save changes') ?></button>
            <?php else: ?>
                <button type="submit" name="action" value="publish" class="btn btn--primary"><?= te('Publish') ?></button>
                <button type="submit" name="action" value="draft" class="btn btn--ghost"><?= te('Save as draft') ?></button>
            <?php endif; ?>
        </div>
    </div>
</form>
