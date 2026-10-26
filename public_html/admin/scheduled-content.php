<?php

declare(strict_types=1);

define('ADMIN_REQUIRED_CAPABILITY', 'content.manage');
require_once __DIR__ . '/incl/module-bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET', true, 405);
    exit;
}
$scope = (string)($_GET['scope'] ?? 'upcoming');
if (!in_array($scope, ['upcoming','overdue'], true)) $scope = 'upcoming';
$type = (string)($_GET['type'] ?? '');
if (!preg_match('/^(?:[a-z][a-z0-9_-]{0,63})?$/D', $type)) $type = '';
$action = (string)($_GET['action'] ?? '');
if (!in_array($action, ['','publish','unpublish'], true)) $action = '';
$allItems = [];
$typeOptions = ['page','post'];
$loadError = false;
$truncated = false;
$scopeCounts = ['upcoming' => 0, 'overdue' => 0];
try {
    $registry = new ScheduledContent();
    // Core tabs show the complete action count per scope, independent of the
    // optional type/action selectors. Only the selected scope fetches rows.
    $count = $registry->count($scope);
    $scopeCounts[$scope] = $count;
    $otherScope = $scope === 'upcoming' ? 'overdue' : 'upcoming';
    $scopeCounts[$otherScope] = $registry->count($otherScope);
    $allItems = $registry->items($scope, 2000);
    $truncated = $count > count($allItems);
    $typeOptions = array_values(array_unique(array_merge($typeOptions, array_column($allItems, 'type'), $type !== '' ? [$type] : [])));
    sort($typeOptions, SORT_STRING);
    if ($type !== '') $allItems = array_values(array_filter($allItems, static fn(array $item): bool => $item['type'] === $type));
    if ($action !== '') $allItems = array_values(array_filter($allItems, static fn(array $item): bool => $item['action_type'] === $action));
} catch (Throwable $e) {
    error_log('Scheduled content admin: ' . $e->getMessage());
    $loadError = true;
}
$total = count($allItems);
$paging = admin_crud_pagination($total);
$items = array_slice($allItems, $paging['offset'], $paging['perPage']);
admin_render('scheduled-content', [
    'user' => $auth->user(), 'activeNav' => 'dashboard',
    'scope' => $scope, 'type' => $type, 'action' => $action,
    'items' => $items, 'typeOptions' => $typeOptions, 'total' => $total, 'paging' => $paging,
    'loadError' => $loadError, 'truncated' => $truncated, 'scopeCounts' => $scopeCounts,
]);
