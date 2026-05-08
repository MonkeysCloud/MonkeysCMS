<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use MonkeysLegion\Schedule\Contracts\ScheduleDriver;
use MonkeysLegion\Schedule\Driver\NullSchedule;
use MonkeysLegion\Schedule\Discovery\AttributeScanner;
use MonkeysLegion\Schedule\Schedule;
use MonkeysLegion\Schedule\ScheduleManager;
use MonkeysLegion\Database\Cache\Contracts\CacheInterface;

/**
 * ScheduleProvider — DI definitions for the MonkeysLegion Schedule package.
 *
 * Registers the ScheduleDriver, AttributeScanner, ScheduleManager, and Schedule
 * so the CronService can list and execute tasks.
 */
final class ScheduleProvider
{
    public static function getDefinitions(): array
    {
        return [
            // Default to NullSchedule driver (no Redis/DB needed)
            ScheduleDriver::class => fn($c): ScheduleDriver => new NullSchedule(),

            // AttributeScanner — discovers #[Scheduled] attributes in app/
            AttributeScanner::class => fn($c): AttributeScanner => new AttributeScanner(
                scanPaths: ['app'],
                baseRoot: defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3),
            ),

            // ScheduleManager — core manager with debug mode for attribute scanning
            ScheduleManager::class => function ($c): ScheduleManager {
                $cache = null;
                try {
                    $cache = $c->get(CacheInterface::class);
                } catch (\Throwable) {
                    // Cache not configured — that's fine, we'll use debug mode
                }

                return new ScheduleManager(
                    cache: $cache,
                    scanner: $c->get(AttributeScanner::class),
                    driver: $c->get(ScheduleDriver::class),
                    logger: null,
                    debugMode: true, // Always scan attributes (no cache dependency)
                );
            },

            // Schedule — the public-facing API
            Schedule::class => fn($c): Schedule => new Schedule(
                $c->get(ScheduleManager::class),
            ),
        ];
    }
}
