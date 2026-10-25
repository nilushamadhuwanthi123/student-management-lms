<?php
/** Compose a notice. */
$institutes = $institutes ?? [];
$classes = $classes ?? [];
$isGlobal = !empty($isGlobal);
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="page-header">
    <div><h1 class="page-header__title"><?= te('New notice') ?></h1></div>
    <div class="page-header__actions"><a href="<?= BASE_URL ?>/notices" class="btn btn--ghost">&larr; <?= te('Notice board') ?></a></div>
</div>
<form method="POST" action="<?= BASE_URL ?>/notices/create" class="card" style="max-width:760px;">
    <?= csrfField() ?>
    <div class="card__body" style="display:grid; gap:1rem;">
        <div class="form-group"><label class="form-label" for="title"><?= te('Title') ?> *</label>
            <input type="text" id="title" name="title" class="form-control" maxlength="255" required></div>
        <div class="form-group"><label class="form-label" for="body"><?= te('Message') ?> *</label>
            <textarea id="body" name="body" class="form-control" rows="6" required></textarea></div>
        <div class="form-group">
            <label class="form-label" for="audience"><?= te('Where') ?></label>
            <select id="audience" name="audience" class="form-control" onchange="document.getElementById('pickBranch').style.display=this.value==='branch'?'':'none'; document.getElementById('pickClass').style.display=this.value==='class'?'':'none';">
                <option value="class"><?= te('One class') ?></option>
                <?php if ($institutes !== []): ?><option value="branch"><?= te('A whole branch') ?></option><?php endif; ?>
                <?php if ($isGlobal): ?><option value="all"><?= te('All branches') ?></option><?php endif; ?>
            </select>
        </div>
        <div class="form-group" id="pickClass"><label class="form-label" for="class_id"><?= te('Class') ?></label>
            <select id="class_id" name="class_id" class="form-control"><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= $e($c['class_name']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group" id="pickBranch" style="display:none;"><label class="form-label" for="institute_id"><?= te('Branch') ?></label>
            <select id="institute_id" name="institute_id" class="form-control"><?php foreach ($institutes as $i): ?><option value="<?= (int) $i['id'] ?>"><?= $e($i['name']) ?></option><?php endforeach; ?></select></div>
        <div>
            <div class="form-label"><?= te('Who should see it') ?></div>
            <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                <label><input type="checkbox" name="for_students" value="1" checked> <?= te('Students') ?></label>
                <label><input type="checkbox" name="for_parents" value="1" checked> <?= te('Parents') ?></label>
                <label><input type="checkbox" name="for_staff" value="1"> <?= te('Staff') ?></label>
            </div>
        </div>
        <div class="form-group" style="max-width:300px;"><label class="form-label" for="publish_at"><?= te('Publish at (leave empty for now)') ?></label>
            <input type="datetime-local" id="publish_at" name="publish_at" class="form-control"></div>
        <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
            <button type="submit" name="action" value="publish" class="btn btn--primary"><?= te('Publish') ?></button>
            <button type="submit" name="action" value="draft" class="btn btn--ghost"><?= te('Save as draft') ?></button>
        </div>
    </div>
</form>
