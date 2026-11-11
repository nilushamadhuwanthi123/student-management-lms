<?php
/**
 * Class Recordings, as the teacher sees them.
 *
 * Deliberately its own page, not a tab inside Class material -- the
 * client asked for recordings to live apart from homework, quizzes,
 * assignments and general material.
 */

$class      = $class ?? [];
$recordings = $recordings ?? [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= te('Class Recordings') ?></h1>
        <p class="text-sm text-muted">
            <?= $e($class['class_name'] ?? '') ?>
            <?php if (!empty($class['institute_name'])): ?>
                &middot; <?= $e($class['institute_name']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header__actions">
        <a href="<?= BASE_URL ?>/classes" class="btn btn--ghost">&larr; <?= te('Classes') ?></a>
        <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/recordings/create" class="btn btn--primary">
            + <?= te('Add a recording') ?>
        </a>
    </div>
</div>

<div class="card">
    <?php if ($recordings === []): ?>
        <div style="padding:3rem 1.5rem; text-align:center;">
            <div style="font-size:2.5rem;">&#127909;</div>
            <h4 style="margin:.75rem 0 .35rem;"><?= te('No recordings posted yet') ?></h4>
            <p class="text-sm text-muted" style="margin:0 0 1.25rem;">
                <?= te('Paste a YouTube or Google Drive link, and the class will see it here.') ?>
            </p>
            <a href="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/recordings/create" class="btn btn--primary">
                <?= te('Add the first recording') ?>
            </a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= te('Recording') ?></th>
                        <th><?= te('Topic') ?></th>
                        <th><?= te('Lesson date') ?></th>
                        <th><?= te('Status') ?></th>
                        <th class="text-right"><?= te('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recordings as $r): ?>
                    <tr<?= $r['status'] === 'draft' ? ' style="opacity:.65;"' : '' ?>>
                        <td>
                            <div style="font-weight:600;"><?= $e($r['title']) ?></div>
                            <?php if (!empty($r['description'])): ?>
                                <div class="text-sm text-muted" style="max-width:46ch;"><?= $e($r['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm">
                            <?= !empty($r['topic_title']) ? $e(lang_current() === 'si' && !empty($r['topic_title_si']) ? $r['topic_title_si'] : $r['topic_title']) : '&mdash;' ?>
                        </td>
                        <td class="text-sm">
                            <?= !empty($r['recording_date']) ? date('j M Y', strtotime((string) $r['recording_date'])) : '&mdash;' ?>
                        </td>
                        <td>
                            <span class="badge" style="background:<?= $r['status'] === 'published' ? 'var(--success-light); color:var(--success)' : 'var(--warning-light); color:var(--warning)' ?>;">
                                <?= $r['status'] === 'published' ? te('Published') : te('Draft') ?>
                            </span>
                        </td>
                        <td class="text-right">
                            <a href="<?= $e($r['video_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn--ghost btn--sm"><?= te('Preview') ?></a>

                            <?php if ($r['status'] === 'draft'): ?>
                                <form method="POST" action="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/recordings/<?= (int) $r['id'] ?>/publish" style="display:inline;">
                                    <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                    <button type="submit" class="btn btn--ghost btn--sm"><?= te('Publish') ?></button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" action="<?= BASE_URL ?>/classes/<?= (int) $class['id'] ?>/recordings/<?= (int) $r['id'] ?>/archive"
                                  style="display:inline;" data-confirm="<?= te('Take this down? Students will stop seeing it.') ?>">
                                <?php if (function_exists('csrfField')): ?><?= csrfField() ?><?php endif; ?>
                                <button type="submit" class="btn btn--ghost btn--sm" style="color:var(--danger);"><?= te('Take down') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
