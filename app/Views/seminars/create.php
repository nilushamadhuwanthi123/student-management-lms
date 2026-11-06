<?php
/** Compose a seminar/event. */
$institutes = $institutes ?? [];
$classes = $classes ?? [];
$grades = $grades ?? [];
$isGlobal = !empty($isGlobal);
$canTargetGrade = !empty($canTargetGrade);
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="page-header">
    <div><h1 class="page-header__title"><?= te('New seminar') ?></h1></div>
    <div class="page-header__actions"><a href="<?= BASE_URL ?>/seminars" class="btn btn--ghost">&larr; <?= te('Seminars & Events') ?></a></div>
</div>
<form method="POST" action="<?= BASE_URL ?>/seminars/create" class="card" style="max-width:760px;">
    <?= csrfField() ?>
    <div class="card__body" style="display:grid; gap:1rem;">
        <div class="form-group"><label class="form-label" for="title"><?= te('Title') ?> *</label>
            <input type="text" id="title" name="title" class="form-control" maxlength="255" required></div>
        <div class="form-group"><label class="form-label" for="description"><?= te('Description') ?></label>
            <textarea id="description" name="description" class="form-control" rows="5"></textarea></div>
        <div class="form-row form-row--2">
            <div class="form-group"><label class="form-label" for="event_date"><?= te('Date') ?> *</label>
                <input type="date" id="event_date" name="event_date" class="form-control" required></div>
            <div class="form-group">
                <label class="form-label"><?= te('Time (optional)') ?></label>
                <div style="display:flex; gap:.5rem;">
                    <input type="time" name="start_time" class="form-control">
                    <input type="time" name="end_time" class="form-control">
                </div>
            </div>
        </div>
        <div class="form-row form-row--2">
            <div class="form-group"><label class="form-label" for="venue"><?= te('Venue (optional)') ?></label>
                <input type="text" id="venue" name="venue" class="form-control" maxlength="255" placeholder="<?= te('e.g. Main Hall, Pannipitiya branch') ?>"></div>
            <div class="form-group"><label class="form-label" for="online_url"><?= te('Online link (optional)') ?></label>
                <input type="url" id="online_url" name="online_url" class="form-control" placeholder="https://"></div>
        </div>
        <div class="form-group"><label class="form-label" for="cover_image_url"><?= te('Cover image link (optional)') ?></label>
            <input type="url" id="cover_image_url" name="cover_image_url" class="form-control" placeholder="https://"></div>
        <div class="form-group">
            <label class="form-label" for="target_scope"><?= te('Who this is for') ?></label>
            <select id="target_scope" name="target_scope" class="form-control" onchange="
                document.getElementById('pickBranch').style.display=(this.value==='branch'||this.value==='grade')?'':'none';
                document.getElementById('pickGrade').style.display=this.value==='grade'?'':'none';
                document.getElementById('pickClass').style.display=this.value==='class'?'':'none';">
                <option value="class"><?= te('One class') ?></option>
                <?php if ($canTargetGrade && $grades !== []): ?><option value="grade"><?= te('One grade (any branch, or just one)') ?></option><?php endif; ?>
                <?php if ($institutes !== []): ?><option value="branch"><?= te('A whole branch') ?></option><?php endif; ?>
                <?php if ($isGlobal): ?><option value="all"><?= te('All branches') ?></option><?php endif; ?>
            </select>
        </div>
        <div class="form-group" id="pickClass"><label class="form-label" for="class_id"><?= te('Class') ?></label>
            <select id="class_id" name="class_id" class="form-control"><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= $e($c['class_name']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group" id="pickGrade" style="display:none;"><label class="form-label" for="grade"><?= te('Grade') ?></label>
            <select id="grade" name="grade" class="form-control"><?php foreach ($grades as $g): ?><option value="<?= $e($g) ?>"><?= $e($g) ?></option><?php endforeach; ?></select></div>
        <div class="form-group" id="pickBranch" style="display:none;"><label class="form-label" for="institute_id"><?= te('Branch (leave as "every branch" when targeting a grade across all of them)') ?></label>
            <select id="institute_id" name="institute_id" class="form-control">
                <option value="0"><?= te('Every branch') ?></option>
                <?php foreach ($institutes as $i): ?><option value="<?= (int) $i['id'] ?>"><?= $e($i['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div>
            <div class="form-label"><?= te('Who should see it') ?></div>
            <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                <label><input type="checkbox" name="for_students" value="1" checked> <?= te('Students') ?></label>
                <label><input type="checkbox" name="for_parents" value="1" checked> <?= te('Parents') ?></label>
                <label><input type="checkbox" name="for_staff" value="1"> <?= te('Staff') ?></label>
            </div>
        </div>
        <div>
            <label><input type="checkbox" name="registration_required" value="1"> <?= te('Requires registration (people can RSVP from their own seminars list)') ?></label>
        </div>
        <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
            <button type="submit" name="action" value="publish" class="btn btn--primary"><?= te('Publish') ?></button>
            <button type="submit" name="action" value="draft" class="btn btn--ghost"><?= te('Save as draft') ?></button>
        </div>
    </div>
</form>
