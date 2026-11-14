<?php
/**
 * Manage the public Downloads page: super admin or teacher.
 * Variables: $items (every row, newest first)
 */
$items = $items ?? [];
$categories = $categories ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<datalist id="downloadCategoryList">
    <?php foreach ($categories as $cat): ?><option value="<?= $e($cat) ?>"><?php endforeach; ?>
</datalist>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Downloads') ?></h1>
        <p class="text-sm text-muted"><?= te('Past papers, notes and other files the teacher or super admin has shared.') ?> <?= te('Files are not uploaded here -- paste a Google Drive (or similar) share link.') ?> <?= te('Give items the same "Folder" name (e.g. "Grade 10 Papers") to group them into one folder on the public page -- leave it blank and the item sits under "Other files". Type a "/" to nest folders, e.g. "Grade 11/Science/1st Term" makes a Science sub-folder inside Grade 11, with a 1st Term sub-folder inside that -- name folders however you like, any depth.') ?></p>
    </div>
</div>

<?php if ($items !== []): ?>
<div class="card mb-3" style="padding:0;">
    <?php foreach ($items as $it): ?>
        <div style="display:flex; gap:1rem; align-items:center; padding:.9rem 1.1rem; border-top:1px solid var(--border-color,#eee);">
            <div style="flex:1; min-width:0;">
                <div style="font-weight:600;">
                    <?= $e($it['title']) ?>
                    <?php if (!empty($it['category'])): ?><span class="badge badge--neutral" style="font-weight:500;">&#128193; <?= $e($it['category']) ?></span><?php endif; ?>
                </div>
                <div class="text-xs text-muted" style="word-break:break-all;"><?= $e($it['drive_url']) ?></div>
            </div>
            <span class="badge <?= $it['is_active'] ? 'badge--success' : 'badge--neutral' ?>"><?= $it['is_active'] ? te('Active') : te('Inactive') ?></span>
            <div class="d-flex gap-1">
                <form method="POST" action="<?= BASE_URL ?>/downloads/<?= (int) $it['id'] ?>/toggle" style="margin:0;">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn--ghost btn--xs"><?= $it['is_active'] ? te('Deactivate') : te('Activate') ?></button>
                </form>
                <button type="button" class="btn btn--ghost btn--xs" onclick="document.getElementById('editDl<?= (int) $it['id'] ?>').style.display='block'; this.closest('div').style.display='none';"><?= te('Edit') ?></button>
                <form method="POST" action="<?= BASE_URL ?>/downloads/<?= (int) $it['id'] ?>/delete" style="margin:0;" data-confirm="<?= $e(t('Delete {title}? This cannot be undone.', ['title' => $it['title']])) ?>">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn--danger btn--xs"><?= te('Delete') ?></button>
                </form>
            </div>
        </div>
        <div id="editDl<?= (int) $it['id'] ?>" style="display:none; padding:1rem 1.1rem; border-top:1px dashed var(--border-color,#ddd); background:var(--surface-sunken);">
            <form method="POST" action="<?= BASE_URL ?>/downloads/<?= (int) $it['id'] ?>/update">
                <?= csrfField() ?>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:.75rem;">
                    <div class="form-group">
                        <label class="form-label"><?= te('Title') ?></label>
                        <input type="text" name="title" class="form-control" value="<?= $e($it['title']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= te('Folder') ?></label>
                        <input type="text" name="category" class="form-control" list="downloadCategoryList" value="<?= $e($it['category'] ?? '') ?>" placeholder="<?= $e(t('e.g. Grade 10 Papers, or Grade 11/Science/1st Term')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= te('Link') ?></label>
                        <input type="url" name="drive_url" class="form-control" value="<?= $e($it['drive_url']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= te('Order (lower shows first)') ?></label>
                        <input type="number" name="sort_order" class="form-control" value="<?= (int) $it['sort_order'] ?>">
                    </div>
                </div>
                <label style="display:flex; align-items:center; gap:.4rem; margin:.75rem 0;">
                    <input type="checkbox" name="is_active" value="1" <?= $it['is_active'] ? 'checked' : '' ?>>
                    <?= te('Active') ?>
                </label>
                <button type="submit" class="btn btn--primary btn--sm"><?= te('Save changes') ?></button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-top:0;"><?= te('Add a download') ?></h3>
    <form method="POST" action="<?= BASE_URL ?>/downloads/manage">
        <?= csrfField() ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:.75rem;">
            <div class="form-group">
                <label class="form-label"><?= te('Title') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <input type="text" name="title" class="form-control" placeholder="<?= te('e.g. Grade 10 term test papers') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label"><?= te('Folder') ?></label>
                <input type="text" name="category" class="form-control" list="downloadCategoryList" placeholder="<?= te('e.g. Grade 10 Papers, or Grade 11/Science/1st Term') ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= te('Link') ?> <span class="required" style="color:var(--danger);">*</span></label>
                <input type="url" name="drive_url" class="form-control" placeholder="https://drive.google.com/..." required>
            </div>
            <div class="form-group">
                <label class="form-label"><?= te('Order (lower shows first)') ?></label>
                <input type="number" name="sort_order" class="form-control" value="0">
            </div>
        </div>
        <label style="display:flex; align-items:center; gap:.4rem; margin:.75rem 0;">
            <input type="checkbox" name="is_active" value="1" checked>
            <?= te('Active') ?>
        </label>
        <button type="submit" class="btn btn--primary"><?= te('Add download') ?></button>
    </form>
</div>
