<?php

declare(strict_types=1);

namespace App\Cli\Command;

use App\Modules\Core\Services\MenuService;
use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;

#[CommandAttr('menu:seed', 'Seed default admin menus')]
final class SeedMenusCommand extends Command
{
    public function __construct(
        private readonly MenuService $menuService,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $this->info('Seeding Admin Menu...');

        try {
            // Check if admin menu exists
            $adminMenu = $this->menuService->getMenuByName('admin');

            if ($adminMenu) {
                $this->info('Admin menu already exists. Skipping creation.');
            } else {
                $this->info('Creating "admin" menu...');
                $menu = new \App\Modules\Core\Entities\Menu();
                $menu->name = 'Administration';
                $menu->machine_name = 'admin';
                $menu->description = 'Main administration menu';
                $menu->location = 'admin_sidebar';
                
                $this->menuService->saveMenu($menu);
                $this->info('✅ Admin menu created.');
                
                // Add Dashboard Item
                $this->info('Adding Dashboard item...');
                $dashboard = new \App\Modules\Core\Entities\MenuItem();
                $dashboard->menu_id = $menu->id;
                $dashboard->title = 'Dashboard';
                $dashboard->url = '/admin/dashboard';
                $dashboard->link_type = 'custom';
                $dashboard->weight = -50;
                $dashboard->icon = 'dashboard'; 
                
                $this->menuService->saveMenuItem($dashboard);
                
                // Add Menus Management Item
                $this->info('Adding Menus item...');
                $menusItem = new \App\Modules\Core\Entities\MenuItem();
                $menusItem->menu_id = $menu->id;
                $menusItem->title = 'Menus';
                $menusItem->url = '/admin/menus';
                $menusItem->link_type = 'custom';
                $menusItem->weight = 0;
                
                $this->menuService->saveMenuItem($menusItem);
            }

            $this->info('✅ Menu seeding completed successfully.');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to seed menu: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
