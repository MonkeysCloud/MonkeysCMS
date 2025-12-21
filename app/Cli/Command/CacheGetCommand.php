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
 * CacheGetCommand - Get a value from cache
 *
 * Usage:
 *   php monkeys cache:get user:123
 *   php monkeys cache:get user:123 --store=redis
 *   php monkeys cache:get user:123 --format=json
 */
#[Command(
    name: 'cache:get',
    description: 'Get a value from the cache'
)]
class CacheGetCommand
{
    public function __construct(
        private readonly CacheManager $cacheManager,
    ) {
    }

    #[Argument(name: 'key', description: 'Cache key to retrieve')]
    public string $key;

    #[Option(name: 'store', shortcut: 's', description: 'Cache store to use')]
    public ?string $store = null;

    #[Option(name: 'format', shortcut: 'f', description: 'Output format (text, json, var_dump)')]
    public string $format = 'text';

    public function __invoke(Input $input, Output $output): int
    {
        try {
            $store = $this->store
                ? $this->cacheManager->store($this->store)
                : $this->cacheManager->store();

            $value = $store->get($this->key);

            if ($value === null) {
                $output->writeln("<warning>Key '{$this->key}' not found in cache</warning>");
                return 1;
            }

            $output->writeln("<info>Key:</info> {$this->key}");
            $output->writeln("<info>Value:</info>");

            switch ($this->format) {
                case 'json':
                    $output->writeln(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    break;

                case 'var_dump':
                    ob_start();
                    var_dump($value);
                    $output->writeln(ob_get_clean());
                    break;

                case 'text':
                default:
                    if (is_array($value) || is_object($value)) {
                        $output->writeln(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    } else {
                        $output->writeln((string) $value);
                    }
                    break;
            }

            return 0;
        } catch (\Exception $e) {
            $output->writeln("<e>✗ Error: " . $e->getMessage() . "</e>");
            return 1;
        }
    }
}
