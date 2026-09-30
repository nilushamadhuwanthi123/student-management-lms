<?php
/**
 * Breadcrumb Component
 *
 * Expects: $breadcrumbs — array of [label, url] pairs.
 * The last item is the current page (rendered without a link).
 */
?>

<?php if (!empty($breadcrumbs)): ?>
<nav aria-label="Breadcrumb">
    <ol class="breadcrumbs">
        <?php foreach ($breadcrumbs as $i => $crumb): ?>
            <?php $isLast = ($i === count($breadcrumbs) - 1); ?>
            <li>
                <?php if ($isLast): ?>
                    <span class="current"><?= htmlspecialchars($crumb[0], ENT_QUOTES, 'UTF-8') ?></span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($crumb[1], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($crumb[0], ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>
