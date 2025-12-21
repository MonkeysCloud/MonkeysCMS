<?php

declare(strict_types=1);

namespace App\Cli\Command;

use MonkeysLegion\Cache\CacheManager;
use MonkeysLegion\Cli\Attribute\Command;
use MonkeysLegion\Cli\Attribute\Argument;
use MonkeysLegion\Cli\Attribute\Option;
use MonkeysLegion\Cli\IO\Input;
use MonkeysLegion\Cli\IO\Output;

/**
 * CacheForgetCommand - Delete keys from cache
 * 
 * Usage:
 *   php monkeys cache:forget user:123
 *   php monkeys cache:forget user:123,user:456
 *   php monkeys cache:forget user:123 --store=redis
 */
#[Command(
    name: 'cache:forget',
    description: 'Delete one or more keys from the cache'
)]
class CacheForgetCommand
{
    public function __construct(
        private readonly CacheManager $cacheManager,
    ) {}

    #[Argument(name: 'keys', description: 'Cache key(s) to delete (comma-separated for multiple)')]
    public string $keys;

    #[Option(name: 'store', shortcut: 's', description: 'Cache store to use')]
    public ?string $store = null;

    public function __invoke(Input $input, Output $output): int
    {
        try {
            $store = $this->store 
                ? $this->cacheManager->store($this->store)
                : $this->cacheManager->store();

            $keys = array_map('trim', explode(',', $this->keys));
            $deleted = 0;
            $failed = 0;

            foreach ($keys as $key) {
                if (empty($key)) {
                    continue;
                }

                if ($store->delete($key)) {
                    $output->writeln("<success>✓ Deleted: {$key}</success>");
                    $deleted++;
                } else {
                    $output->writeln("<warning>⚠ Not found or failed: {$key}</warning>");
                    $failed++;
                }
            }

            $output->writeln("");
            $output->writeln("<info>Summary: {$deleted} deleted, {$failed} failed/not found</info>");

            return $failed > 0 ? 1 : 0;
        } catch (\Exception $e) {
            $output->writeln("<e>✗ Error: " . $e->getMessage() . "</e>");
            return 1;
        }
    }
}
