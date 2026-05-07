<?php

declare(strict_types=1);

namespace App\Cms\Command;

use MonkeysLegion\Cli\Console\Attributes\Command;
use MonkeysLegion\Cli\Console\Command as BaseCommand;
use PDO;

/**
 * PublishScheduledCommand — Publishes content whose scheduled date has passed.
 *
 * Run via: php bin/ml cms:publish-scheduled
 * Schedule via cron: * * * * * php /path/to/bin/ml cms:publish-scheduled
 */
#[Command(
    signature: 'cms:publish-scheduled',
    description: 'Publish content whose scheduled publish date has passed',
)]
final class PublishScheduledCommand extends BaseCommand
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $this->info('Checking for scheduled content...');

        $stmt = $this->pdo->prepare(
            "SELECT id, title, published_at
             FROM nodes
             WHERE status = 'scheduled'
               AND published_at IS NOT NULL
               AND published_at <= NOW()
               AND deleted_at IS NULL"
        );
        $stmt->execute();
        $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($nodes)) {
            $this->comment('No scheduled content due for publishing.');
            return self::SUCCESS;
        }

        $ids = array_column($nodes, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $update = $this->pdo->prepare(
            "UPDATE nodes SET status = 'published', updated_at = NOW() WHERE id IN ({$placeholders})"
        );
        $update->execute($ids);

        $count = $update->rowCount();

        // Log each published node
        foreach ($nodes as $node) {
            $this->info("  ✓ Published: \"{$node['title']}\" (ID: {$node['id']}, scheduled: {$node['published_at']})");
        }

        $this->info("Published {$count} node(s).");

        return self::SUCCESS;
    }
}
