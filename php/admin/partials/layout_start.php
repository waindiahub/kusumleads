<?php
$layoutConfig = $layoutConfig ?? [];
$pageLayout = $pageLayout ?? [];

$mainClassNames = ['page-wrapper'];
$contentClassNames = ['page-content'];

if (!empty($layoutConfig['mainClass'])) {
    $mainClassNames[] = $layoutConfig['mainClass'];
}
if (!empty($layoutConfig['contentClass'])) {
    $contentClassNames[] = $layoutConfig['contentClass'];
}
if (!empty($pageLayout['mainClass'])) {
    $mainClassNames[] = $pageLayout['mainClass'];
}
if (!empty($pageLayout['contentClass'])) {
    $contentClassNames[] = $pageLayout['contentClass'];
}

$mainClass = htmlspecialchars(trim(implode(' ', $mainClassNames)), ENT_QUOTES, 'UTF-8');
$contentClass = htmlspecialchars(trim(implode(' ', $contentClassNames)), ENT_QUOTES, 'UTF-8');
?>
<?php include __DIR__ . '/../navbar.php'; ?>
<div class="app-shell" id="appShell">
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <main class="<?= $mainClass ?>" role="main">
        <div class="<?= $contentClass ?>">

