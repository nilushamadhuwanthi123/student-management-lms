<?php
/**
 * Audit - Workflow History View
 *
 * Variables: $history, $entityId, $entityType, $pageTitle
 */

// If $history is provided, format it as $workflows for the unified workflow history component
$workflows = $history ?? $workflows ?? [];
$filters = $filters ?? [
    'entity_id'   => $entityId ?? '',
    'entity_type' => $entityType ?? '',
];
$currentPage = $currentPage ?? 1;
$totalPages = $totalPages ?? 1;

include __DIR__ . '/../workflows/history.php';
