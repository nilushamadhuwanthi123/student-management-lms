<?php
/**
 * Reusable Pagination Component
 *
 * Variables:
 *   $currentPage  - Current active page (1-indexed)
 *   $totalPages   - Total number of pages
 *   $baseUrl      - Base URL for page links (e.g. "/students?search=x")
 */

$currentPage = (int) ($currentPage ?? 1);
$totalPages  = (int) ($totalPages ?? 1);
$baseUrl     = $baseUrl ?? '';
// Callers pass a site path such as "/students?x=1"; the site may live in a
// sub-folder (XAMPP: /student-management-lms), so prefix it once here.
if (defined('BASE_URL') && BASE_URL !== '' && str_starts_with($baseUrl, '/') && !str_starts_with($baseUrl, BASE_URL . '/')) {
    $baseUrl = BASE_URL . $baseUrl;
}

if ($totalPages <= 1) return;

// Build page URL helper
$pageUrl = function (int $page) use ($baseUrl): string {
    $separator = str_contains($baseUrl, '?') ? '&' : '?';
    return htmlspecialchars($baseUrl . $separator . 'page=' . $page, ENT_QUOTES, 'UTF-8');
};

// Calculate visible page range (show max 7 pages)
$maxVisible = 7;
$startPage = max(1, $currentPage - (int) floor($maxVisible / 2));
$endPage   = min($totalPages, $startPage + $maxVisible - 1);
if ($endPage - $startPage < $maxVisible - 1) {
    $startPage = max(1, $endPage - $maxVisible + 1);
}
?>

<div class="pagination__info">
    <?= te('Page {current} of {total}', ['current' => $currentPage, 'total' => $totalPages]) ?>
</div>

<nav aria-label="Pagination">
    <ul class="pagination">
        <!-- Previous -->
        <li>
            <?php if ($currentPage > 1): ?>
                <a href="<?= $pageUrl($currentPage - 1) ?>" class="pagination__item" aria-label="Previous page">
                    &laquo; <?= te('Prev') ?>
                </a>
            <?php else: ?>
                <span class="pagination__item disabled">&laquo; <?= te('Prev') ?></span>
            <?php endif; ?>
        </li>

        <!-- First page + ellipsis -->
        <?php if ($startPage > 1): ?>
            <li><a href="<?= $pageUrl(1) ?>" class="pagination__item">1</a></li>
            <?php if ($startPage > 2): ?>
                <li><span class="pagination__item disabled">&hellip;</span></li>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Page numbers -->
        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <li>
                <?php if ($i === $currentPage): ?>
                    <span class="pagination__item active" aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= $pageUrl($i) ?>" class="pagination__item"><?= $i ?></a>
                <?php endif; ?>
            </li>
        <?php endfor; ?>

        <!-- Last page + ellipsis -->
        <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?>
                <li><span class="pagination__item disabled">&hellip;</span></li>
            <?php endif; ?>
            <li><a href="<?= $pageUrl($totalPages) ?>" class="pagination__item"><?= $totalPages ?></a></li>
        <?php endif; ?>

        <!-- Next -->
        <li>
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= $pageUrl($currentPage + 1) ?>" class="pagination__item" aria-label="Next page">
                    <?= te('Next') ?> &raquo;
                </a>
            <?php else: ?>
                <span class="pagination__item disabled"><?= te('Next') ?> &raquo;</span>
            <?php endif; ?>
        </li>
    </ul>
</nav>
