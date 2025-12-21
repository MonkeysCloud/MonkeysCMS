<?php

declare(strict_types=1);

namespace App\Cli\Command;

use MonkeysLegion\Cache\CacheManager;
use MonkeysLegion\Cli\Attribute\Command;
use MonkeysLegion\Cli\Attribute\Option;
use MonkeysLegion\Cli\IO\Input;
use MonkeysLegion\Cli\IO\Output;

/**
 * CacheClearCommand - Clear cache stores
 *
 * Usage:
 *   php monkeys cache:clear              # Clear default store
 *   php monkeys cache:clear --store=redis  # Clear specific store
 *   php monkeys cache:clear --tags=users,posts  # Clear by tags
 *   php monkeys cache:clear --all        # Clear all stores
 */
#[Command(
    name: 'cache:clear',
    description: 'Clear the application cache'
)]
class CacheClearCommand
{
    public function __construct(
        private readonly CacheManager $cacheManager,
    ) {
    }

    #[Option(name: 'store', shortcut: 's', description: 'Cache store to clear')]
    public ?string $store = null;

    #[Option(name: 'tags', shortcut: 't', description: 'Comma-separated tags to clear')]
    public ?string $tags = null;

    #[Option(name: 'all', shortcut: 'a', description: 'Clear all cache stores')]
    public bool $all = false;

    public function __invoke(Input $input, Output $output): int
    {
        $output->writeln('<info>Clearing cache...</info>');

        try {
            if ($this->tags) {
                // Clear by tags
                $tagList = array_map('trim', explode(',', $this->tags));
                $this->cacheManager->tags($tagList)->clear();
                $output->writeln("<success>✓ Cache cleared for tags: " . implode(', ', $tagList) . "</success>");
            } elseif ($this->all) {
                // Clear all stores
                $stores = ['file', 'redis', 'memcached', 'array'];
                foreach ($stores as $storeName) {
                    try {
                        $this->cacheManager->store($storeName)->clear();
                        $output->writeln("<success>✓ Store '{$storeName}' cleared</success>");
                    } catch (\Exception $e) {
                        $output->writeln("<warning>⚠ Store '{$storeName}' skipped: " . $e->getMessage() . "</warning>");
                    }
                }
            } else {
                // Clear specific or default store
                $storeName = $this->store ?? 'default';
                $store = $this->store
                    ? $this->cacheManager->store($this->store)
                    : $this->cacheManager->store();

                $store->clear();
                $output->writeln("<success>✓ Cache store '{$storeName}' cleared successfully</success>");
            }

            return 0;
        } catch (\Exception $e) {
            $output->writeln("<error>✗ Failed to clear cache: " . $e->getMessage() . "</error>");
            return 1;
        }
    }
}
