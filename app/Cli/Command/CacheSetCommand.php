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
 * CacheSetCommand - Set a value in cache
 * 
 * Usage:
 *   php monkeys cache:set user:123 "John Doe"
 *   php monkeys cache:set config:debug true --ttl=3600
 *   php monkeys cache:set user:data '{"name":"John"}' --store=redis
 */
#[Command(
    name: 'cache:set',
    description: 'Set a value in the cache'
)]
class CacheSetCommand
{
    public function __construct(
        private readonly CacheManager $cacheManager,
    ) {}

    #[Argument(name: 'key', description: 'Cache key')]
    public string $key;

    #[Argument(name: 'value', description: 'Value to store')]
    public string $value;

    #[Option(name: 'ttl', shortcut: 't', description: 'TTL in seconds (0 = forever)')]
    public int $ttl = 3600;

    #[Option(name: 'store', shortcut: 's', description: 'Cache store to use')]
    public ?string $store = null;

    #[Option(name: 'json', shortcut: 'j', description: 'Parse value as JSON')]
    public bool $json = false;

    public function __invoke(Input $input, Output $output): int
    {
        try {
            $store = $this->store 
                ? $this->cacheManager->store($this->store)
                : $this->cacheManager->store();

            // Parse value
            $value = $this->value;
            
            // Auto-detect JSON
            if ($this->json || $this->looksLikeJson($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }
            
            // Handle boolean strings
            if ($value === 'true') {
                $value = true;
            } elseif ($value === 'false') {
                $value = false;
            } elseif ($value === 'null') {
                $value = null;
            } elseif (is_numeric($value) && !str_contains($value, '.')) {
                $value = (int) $value;
            } elseif (is_numeric($value)) {
                $value = (float) $value;
            }

            if ($this->ttl === 0) {
                $store->forever($this->key, $value);
                $output->writeln("<success>✓ Key '{$this->key}' stored forever</success>");
            } else {
                $store->set($this->key, $value, $this->ttl);
                $output->writeln("<success>✓ Key '{$this->key}' stored with TTL of {$this->ttl} seconds</success>");
            }

            return 0;
        } catch (\Exception $e) {
            $output->writeln("<e>✗ Error: " . $e->getMessage() . "</e>");
            return 1;
        }
    }

    private function looksLikeJson(string $value): bool
    {
        $value = trim($value);
        return (str_starts_with($value, '{') && str_ends_with($value, '}'))
            || (str_starts_with($value, '[') && str_ends_with($value, ']'));
    }
}
