<?php

declare(strict_types=1);

namespace App\Cms\Apex;

use MonkeysLegion\Apex\AI;
use MonkeysLegion\Apex\Contract\ProviderInterface;
use MonkeysLegion\Apex\Cost\CostReport;
use MonkeysLegion\Apex\Cost\CostTracker;
use MonkeysLegion\Apex\Cost\PricingRegistry;
use MonkeysLegion\Apex\DTO\Response;
use MonkeysLegion\Apex\Provider\Anthropic\AnthropicProvider;
use MonkeysLegion\Apex\Provider\DeepSeek\DeepSeekProvider;
use MonkeysLegion\Apex\Provider\Google\GoogleProvider;
use MonkeysLegion\Apex\Provider\Groq\GroqProvider;
use MonkeysLegion\Apex\Provider\Mistral\MistralProvider;
use MonkeysLegion\Apex\Provider\Ollama\OllamaProvider;
use MonkeysLegion\Apex\Provider\OpenAI\OpenAIProvider;
use MonkeysLegion\Apex\Provider\OpenAICompatible\GenericProvider;
use MonkeysLegion\Apex\Schema\Schema;
use MonkeysLegion\Apex\Streaming\TextStream;
use MonkeysLegion\DI\Attributes\Singleton;
use PDO;

/**
 * ApexService — Singleton AI orchestration service for MonkeysCMS.
 *
 * Bridges the monkeyslegion-apex package with the CMS settings system.
 * Lazy-initializes the AI facade — no overhead unless AI is actually used.
 */
#[Singleton]
final class ApexService
{
    private ?AI $ai = null;
    private ?ApexConfig $config = null;
    private ?CostTracker $costTracker = null;
    private ?PricingRegistry $pricingRegistry = null;

    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ─── Configuration ──────────────────────────────────────────────────────

    /**
     * Load the current configuration from the settings table.
     */
    public function config(): ApexConfig
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $stmt = $this->pdo->prepare(
            'SELECT `key`, `value`, `type` FROM settings WHERE `group` = :group'
        );
        $stmt->execute(['group' => 'apex']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $this->castValue($row['value'], $row['type']);
        }

        $this->config = ApexConfig::fromSettings($settings);
        return $this->config;
    }

    /**
     * Save configuration to the settings table.
     */
    public function saveConfig(ApexConfig $config): void
    {
        $upsert = $this->pdo->prepare(
            'INSERT INTO settings (`group`, `key`, `value`, `type`, `autoload`)
             VALUES (:group, :key, :value, :type, 1)
             ON DUPLICATE KEY UPDATE `value` = :value2, `type` = :type2'
        );

        foreach ($config->toSettings() as $key => $value) {
            $type = $this->detectType($value);
            $val = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            $upsert->execute([
                'group' => 'apex', 'key' => $key,
                'value' => $val, 'type' => $type,
                'value2' => $val, 'type2' => $type,
            ]);
        }

        // Reset cached instances to pick up new config
        $this->config = $config;
        $this->ai = null;
    }

    /**
     * Whether the AI service is configured and ready to use.
     */
    public function isConfigured(): bool
    {
        return $this->config()->isValid;
    }

    // ─── AI Facade ──────────────────────────────────────────────────────────

    /**
     * Get the configured AI instance (lazy-initialized).
     */
    public function ai(): AI
    {
        if ($this->ai !== null) {
            return $this->ai;
        }

        $config = $this->config();
        if (!$config->isValid) {
            throw new \RuntimeException('Apex AI is not configured. Please set up a provider and API key in Settings → AI Assistant.');
        }

        $provider = $this->createProvider($config);
        $this->pricingRegistry = new PricingRegistry();
        $this->costTracker = new CostTracker($this->pricingRegistry);

        $this->ai = new AI(
            provider: $provider,
            costTracker: $this->costTracker,
        );

        return $this->ai;
    }

    /**
     * Generate a text response.
     */
    public function generate(
        string $prompt,
        ?string $system = null,
        ?string $model = null,
        array $options = [],
    ): Response {
        $config = $this->config();
        $system ??= $config->systemPrompt;
        $model ??= $config->model;

        $options['max_tokens'] ??= $config->maxTokens;
        $options['temperature'] ??= $config->temperature;

        return $this->ai()->generate($prompt, $system, $model, $options);
    }

    /**
     * Stream a text response via SSE.
     */
    public function stream(
        string $prompt,
        ?string $system = null,
        ?string $model = null,
        array $options = [],
    ): TextStream {
        $config = $this->config();
        $system ??= $config->systemPrompt;
        $model ??= $config->model;

        $options['max_tokens'] ??= $config->maxTokens;
        $options['temperature'] ??= $config->temperature;

        return $this->ai()->stream($prompt, $system, $model, $options);
    }

    /**
     * Extract structured data from text.
     *
     * @template T of Schema
     * @param class-string<T> $schema
     * @return T
     */
    public function extract(string $schema, string $input, ?string $model = null): Schema
    {
        return $this->ai()->extract($schema, $input, $model);
    }

    // ─── Cost Tracking ──────────────────────────────────────────────────────

    /**
     * Get the current session cost report.
     */
    public function getCostReport(): ?CostReport
    {
        if ($this->costTracker === null) {
            return null;
        }
        return CostReport::generate($this->costTracker->all());
    }

    /**
     * Log a usage record to the database.
     */
    public function logUsage(
        string $operation,
        int $inputTokens,
        int $outputTokens,
        float $costUsd,
        ?int $userId = null,
        ?string $contentType = null,
        ?int $nodeId = null,
        array $metadata = [],
    ): void {
        $config = $this->config();

        $stmt = $this->pdo->prepare(
            'INSERT INTO apex_usage_log
             (user_id, operation, provider, model, input_tokens, output_tokens, cost_usd, content_type, node_id, metadata, created_at)
             VALUES (:user_id, :operation, :provider, :model, :input_tokens, :output_tokens, :cost_usd, :content_type, :node_id, :metadata, NOW())'
        );
        $stmt->execute([
            'user_id'       => $userId,
            'operation'     => $operation,
            'provider'      => $config->provider,
            'model'         => $config->model,
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd'      => $costUsd,
            'content_type'  => $contentType,
            'node_id'       => $nodeId,
            'metadata'      => json_encode($metadata),
        ]);
    }

    /**
     * Get usage summary from the database.
     *
     * @return array{total_cost: float, total_requests: int, by_operation: array, by_model: array}
     */
    public function getUsageSummary(?string $period = 'month'): array
    {
        $where = match ($period) {
            'day'   => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)',
            'week'  => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)',
            'month' => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)',
            'year'  => 'created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)',
            default => '1=1',
        };

        // Total summary
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) AS total_requests,
                    COALESCE(SUM(cost_usd), 0) AS total_cost,
                    COALESCE(SUM(input_tokens), 0) AS total_input_tokens,
                    COALESCE(SUM(output_tokens), 0) AS total_output_tokens
             FROM apex_usage_log WHERE {$where}"
        );
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        // By operation
        $stmt = $this->pdo->query(
            "SELECT operation, COUNT(*) AS count, SUM(cost_usd) AS cost
             FROM apex_usage_log WHERE {$where}
             GROUP BY operation ORDER BY cost DESC"
        );
        $byOperation = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // By model
        $stmt = $this->pdo->query(
            "SELECT model, COUNT(*) AS count, SUM(cost_usd) AS cost
             FROM apex_usage_log WHERE {$where}
             GROUP BY model ORDER BY cost DESC"
        );
        $byModel = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total_cost'         => (float) $summary['total_cost'],
            'total_requests'     => (int) $summary['total_requests'],
            'total_input_tokens' => (int) $summary['total_input_tokens'],
            'total_output_tokens' => (int) $summary['total_output_tokens'],
            'by_operation'       => $byOperation,
            'by_model'           => $byModel,
        ];
    }

    /**
     * Test the connection to the configured provider.
     *
     * @return array{success: bool, message: string, model: string, latency_ms: float}
     */
    public function testConnection(): array
    {
        $start = microtime(true);

        try {
            $response = $this->ai()->generate(
                'Reply with exactly: OK',
                system: 'Respond with a single word only.',
                options: ['max_tokens' => 10, 'temperature' => 0],
            );

            $latency = (microtime(true) - $start) * 1000;

            return [
                'success'    => true,
                'message'    => 'Connection successful! Model responded: ' . trim($response->content),
                'model'      => $response->model ?: $this->config()->model,
                'latency_ms' => round($latency, 1),
            ];
        } catch (\Throwable $e) {
            return [
                'success'    => false,
                'message'    => 'Connection failed: ' . $e->getMessage(),
                'model'      => $this->config()->model,
                'latency_ms' => round((microtime(true) - $start) * 1000, 1),
            ];
        }
    }

    /**
     * Get the provider name for display purposes.
     */
    public function getProviderName(): string
    {
        return $this->config()->providerLabel;
    }

    // ─── Provider Factory ───────────────────────────────────────────────────

    /**
     * Create the correct provider instance based on configuration.
     */
    private function createProvider(ApexConfig $config): ProviderInterface
    {
        return match ($config->provider) {
            'anthropic' => new AnthropicProvider(
                apiKey: $config->apiKey,
                model: $config->model,
                baseUrl: $config->baseUrl ?: 'https://api.anthropic.com',
            ),
            'openai' => new OpenAIProvider(
                apiKey: $config->apiKey,
                model: $config->model,
                baseUrl: $config->baseUrl ?: 'https://api.openai.com/v1',
            ),
            'google' => new GoogleProvider(
                apiKey: $config->apiKey,
                model: $config->model,
                baseUrl: $config->baseUrl ?: null,
            ),
            'deepseek' => new DeepSeekProvider(
                apiKey: $config->apiKey,
                model: $config->model,
            ),
            'mistral' => new MistralProvider(
                apiKey: $config->apiKey,
                model: $config->model,
            ),
            'groq' => new GroqProvider(
                apiKey: $config->apiKey,
                model: $config->model,
            ),
            'ollama' => new OllamaProvider(
                model: $config->model,
                baseUrl: $config->baseUrl ?: 'http://localhost:11434',
            ),
            'generic' => new GenericProvider(
                apiKey: $config->apiKey,
                baseUrl: $config->baseUrl,
                model: $config->model,
                providerName: 'custom',
            ),
            default => new OpenAIProvider(
                apiKey: $config->apiKey,
                model: $config->model,
            ),
        };
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'float'   => (float) $value,
            'boolean' => $value === '1' || $value === 'true',
            'json'    => json_decode($value, true) ?? [],
            default   => $value,
        };
    }

    private function detectType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value)  => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default          => 'string',
        };
    }
}
