<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Kernel;

$kernel = new Kernel(dirname(__DIR__));
$kernel->bootstrap();
$container = $kernel->getContainer();

/** @var \App\Modules\Core\Services\MenuService $menuService */
$menuService = $container->get(\App\Modules\Core\Services\MenuService::class);

$menu = $menuService->getMenuByName('admin');
if (!$menu) {
    die("Admin menu not found\n");
}

$items = $menuService->getMenuItems($menu->id, null, false); // false = show all, even unpublished if needed, or published

echo "Admin Menu Items:\n";
echo sprintf("%-5s %-30s %-10s %-10s %-10s\n", "ID", "Title", "ParentID", "Weight", "Depth");
echo str_repeat("-", 70) . "\n";

foreach ($items as $item) {
    echo sprintf("%-5d %-30s %-10s %-10d %-10d\n", 
        $item->id, 
        $item->title, 
        $item->parent_id ?? 'NULL', 
        $item->weight, 
        $item->depth
    );
}

// Check 'Content' children specifically
$contentItem = null;
foreach($items as $item) {
    if ($item->title === 'Content') $contentItem = $item;
}

if ($contentItem) {
    echo "\nContent Item ID: " . $contentItem->id . "\n";
    echo "Children of Content:\n";
    foreach($items as $item) {
        if ($item->parent_id === $contentItem->id) {
            echo " - " . $item->title . " (ID: $item->id)\n";
        }
    }
}
