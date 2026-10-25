<?php
/**
 * Optional topic picker for a class's grade.
 * Needs $class (array with 'id'); optional $topicSelected (int|null), $topicFieldId.
 */
$__topics = (new \App\Services\TopicService())->forClass((int) ($class['id'] ?? 0));
$__sel = (int) ($topicSelected ?? 0);
$__fid = $topicFieldId ?? 'topic_id';
?>
<div class="form-group">
    <label class="form-label" for="<?= htmlspecialchars($__fid, ENT_QUOTES, 'UTF-8') ?>"><?= te('Topic (unit)') ?></label>
    <?php if ($__topics === []): ?>
        <p class="text-sm text-muted" style="margin:0;">
            <?= te('No topics for this grade yet.') ?>
            <?php if (in_array(session_get('user_role'), ['super_admin', 'admin', 'teacher'], true)): ?>
                <a href="<?= BASE_URL ?>/topics"><?= te('Add the units') ?></a>
            <?php endif; ?>
        </p>
    <?php else: ?>
        <select id="<?= htmlspecialchars($__fid, ENT_QUOTES, 'UTF-8') ?>" name="topic_id" class="form-control">
            <option value=""><?= te('No topic') ?></option>
            <?php foreach ($__topics as $__t): ?>
                <option value="<?= (int) $__t['id'] ?>" <?= (int) $__t['id'] === $__sel ? 'selected' : '' ?>><?= htmlspecialchars(\App\Services\TopicService::label($__t), ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-text"><?= te('Tagging work by unit lets the learning-gaps page show which units the class finds hard.') ?></div>
    <?php endif; ?>
</div>
