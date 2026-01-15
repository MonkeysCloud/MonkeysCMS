<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Kernel;
use App\Modules\Core\Entities\MenuItem;

$kernel = new Kernel(dirname(__DIR__));
$kernel->bootstrap();
/** @var \Psr\Container\ContainerInterface $container */
$container = $kernel->getContainer();

/** @var \App\Modules\Core\Services\MenuService $menuService */
$menuService = $container->get(\App\Modules\Core\Services\MenuService::class);
$pdo = $container->get(PDO::class);

echo "Starting menu update...\n";

// 1. Get Admin Menu
$adminMenu = $menuService->getMenuByName('admin');
if (!$adminMenu) {
    die("Error: Admin menu not found.\n");
}
$menuId = $adminMenu->id;
echo "Found Admin Menu ID: $menuId\n";

// 2. Find 'Structure' item
$stmt = $pdo->prepare("SELECT id FROM menu_items WHERE menu_id = ? AND title = 'Structure'");
$stmt->execute([$menuId]);
$structureItem = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$structureItem) {
    echo "Warning: 'Structure' menu item not found. Creating it...\n";
    // Assuming structure item code if it didn't exist, but it should based on previous search
    // For now, let's bail if it's missing as it implies a different state than expected
    die("Error: 'Structure' menu item not found.\n");
}
$structureId = $structureItem['id'];
echo "Found Structure Item ID: $structureId\n";

// 3. Add 'Block Types' to Structure
// Check if it already exists to avoid duplicates
$stmt = $pdo->prepare("SELECT id FROM menu_items WHERE menu_id = ? AND url = '/admin/structure/block-types'");
$stmt->execute([$menuId]);
$blockTypesItem = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$blockTypesItem) {
    $newItem = new MenuItem();
    $newItem->menu_id = $menuId;
    $newItem->parent_id = $structureId;
    $newItem->title = 'Block Types';
    $newItem->url = '/admin/structure/block-types';
    $newItem->icon = '🧱';
    $newItem->weight = 10;
    $newItem->is_published = true;
    
    $menuService->saveMenuItem($newItem);
    echo "Created 'Block Types' menu item.\n";
} else {
    echo "'Block Types' menu item already exists.\n";
}

// 4. Find 'Content' item
$stmt = $pdo->prepare("SELECT id FROM menu_items WHERE menu_id = ? AND title = 'Content'");
$stmt->execute([$menuId]);
$contentItem = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contentItem) {
    die("Error: 'Content' menu item not found.\n");
}
$contentId = $contentItem['id'];
echo "Found Content Item ID: $contentId\n";

// 5. Move 'Blocks' to Content
$stmt = $pdo->prepare("SELECT * FROM menu_items WHERE menu_id = ? AND title = 'Blocks'");
$stmt->execute([$menuId]);
$blocksItemData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($blocksItemData) {
    $blocksItem = new MenuItem();
    $blocksItem->hydrate($blocksItemData);
    
    if ($blocksItem->parent_id !== $contentId) {
        $blocksItem->parent_id = $contentId;
        $menuService->saveMenuItem($blocksItem);
        echo "Moved 'Blocks' item to 'Content'.\n";
    } else {
        echo "'Blocks' item is already under 'Content'.\n";
    }
} else {
    echo "Warning: 'Blocks' menu item not found.\n";
}

$menuService->clearCache();
echo "Menu update complete.\n";
