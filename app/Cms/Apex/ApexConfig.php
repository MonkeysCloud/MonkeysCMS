<?php

declare(strict_types=1);

namespace App\Cms\Apex;

/**
 * ApexConfig — PHP 8.4 value object for AI configuration.
 *
 * Resolves configuration from the `settings` table with `.env` fallback.
 * Uses PHP 8.4 property hooks for computed properties.
 */
final class ApexConfig
{
    /** @var array<string, string> Provider display names */
    public const array PROVIDERS = [
        'anthropic' => 'Anthropic (Claude)',
        'openai' => 'OpenAI (GPT)',
        'google' => 'Google (Gemini)',
        'deepseek' => 'DeepSeek',
        'mistral' => 'Mistral',
        'groq' => 'Groq',
        'ollama' => 'Ollama (Local)',
        'generic' => 'Custom OpenAI-Compatible',
    ];

    /** @var array<string, array<string, string>> Default models per provider */
    public const array MODELS = [
        'anthropic' => [
            'claude-sonnet-4' => 'Claude Sonnet 4 (Balanced)',
            'claude-opus-4' => 'Claude Opus 4 (Most capable)',
            'claude-haiku-4' => 'Claude Haiku 4 (Fastest)',
        ],
        'openai' => [
            'gpt-4.1' => 'GPT-4.1 (Flagship)',
            'gpt-4.1-mini' => 'GPT-4.1 Mini (Cost efficient)',
            'gpt-4.1-nano' => 'GPT-4.1 Nano (Fastest)',
            'o3' => 'o3 (Reasoning)',
            'o4-mini' => 'o4-mini (Fast Reasoning)',
        ],
        'google' => [
            'gemini-2.5-flash' => 'Gemini 2.5 Flash (Fast)',
            'gemini-2.5-pro' => 'Gemini 2.5 Pro (Advanced)',
        ],
        'deepseek' => [
            'deepseek-chat' => 'DeepSeek Chat',
            'deepseek-reasoner' => 'DeepSeek Reasoner',
        ],
        'mistral' => [
            'mistral-large-latest' => 'Mistral Large',
            'mistral-medium-latest' => 'Mistral Medium',
            'mistral-small-latest' => 'Mistral Small',
            'codestral-latest' => 'Codestral',
        ],
        'groq' => [
            'llama-3.3-70b-versatile' => 'Llama 3.3 70B',
            'llama-3.1-8b-instant' => 'Llama 3.1 8B (Instant)',
            'mixtral-8x7b-32768' => 'Mixtral 8x7B',
            'gemma2-9b-it' => 'Gemma 2 9B',
        ],
        'ollama' => [
            'llama3' => 'Llama 3',
            'mistral' => 'Mistral',
            'codellama' => 'Code Llama',
            'phi-3' => 'Phi-3',
        ],
        'generic' => [
            'custom' => 'Custom Model',
        ],
    ];

    /** @var array<string, string> Feature descriptions */
    public const array FEATURES = [
        'content_generation' => 'Content Generation (generate, rewrite, summarize, expand, translate)',
        'seo_assistant' => 'SEO Assistant (meta titles, descriptions, keywords, analysis)',
        'taxonomy_suggest' => 'Taxonomy Suggestions (auto-tag, categorize content)',
        'mosaic_ai' => 'Mosaic AI (generate layouts, block content)',
        'field_assistance' => 'Field Assistance (auto-fill, extract structured data)',
        'image_generation' => 'Image Generation (AI-generated images for media fields)',
        'image_analysis' => 'Image Analysis (alt text, captions — requires vision model)',
    ];

    /** @var array<string, string> Providers that support image generation */
    public const array IMAGE_PROVIDERS = [
        'openai' => 'OpenAI (DALL-E 3 / GPT Image 1)',
        'google' => 'Google (Imagen 3 via Vertex AI)',
        'stability' => 'Stability AI (Stable Diffusion 3)',
    ];

    /** @var array<string, array<string, string>> Image models per provider */
    public const array IMAGE_MODELS = [
        'openai' => [
            'dall-e-3' => 'DALL-E 3',
            'gpt-image-1' => 'GPT Image 1',
        ],
        'google' => [
            'imagen-3.0-generate-002' => 'Imagen 3',
        ],
        'stability' => [
            'sd3-large' => 'Stable Diffusion 3 Large',
            'sd3-medium' => 'Stable Diffusion 3 Medium',
        ],
    ];

    public function __construct(
        public bool $enabled = false,
        public string $provider = 'openai',
        public string $apiKey = '',
        public string $model = 'gpt-4.1',
        public string $baseUrl = '',
        public float $temperature = 0.7,
        public int $maxTokens = 4096,
        public string $systemPrompt = 'You are an AI assistant for a CMS. Help create, edit, and optimize content. Be concise, professional, and follow the user\'s instructions precisely.',
        public array $enabledFeatures = [
            'content_generation' => true,
            'seo_assistant' => true,
            'taxonomy_suggest' => true,
            'mosaic_ai' => true,
            'field_assistance' => true,
            'image_generation' => false,
            'image_analysis' => false,
        ],
        public float $budgetLimit = 100.00,
        public float $alertThreshold = 0.8,
        public array $guardrails = [
            'pii_detection' => true,
            'prompt_injection' => true,
            'toxicity_filter' => false,
            'max_output_words' => 5000,
        ],
        public array $contentTypeOverrides = [],
        public string $imageProvider = 'openai',
        public string $imageApiKey = '',
        public string $imageModel = 'dall-e-3',
        public array $imageSettings = [
            'size' => '1024x1024',
            'quality' => 'standard',
            'style' => 'natural',
        ],
    ) {
    }

    /** Whether the config has minimum valid settings */
    public bool $isValid {
        get => $this->enabled && $this->provider !== '' && ($this->apiKey !== '' || $this->provider === 'ollama');
    }

    /** Whether image generation is configured */
    public bool $isImageConfigured {
        get => $this->isFeatureEnabled('image_generation')
        && $this->imageProvider !== ''
        && $this->imageApiKey !== '';
    }

    /** Whether a specific feature is enabled */
    public function isFeatureEnabled(string $feature): bool
    {
        return (bool) ($this->enabledFeatures[$feature] ?? false);
    }

    /** Whether a feature is enabled for a specific content type */
    public function isFeatureEnabledFor(string $feature, string $contentType): bool
    {
        // Check content-type-specific overrides first
        $override = $this->contentTypeOverrides[$contentType][$feature] ?? null;
        if ($override !== null) {
            return (bool) $override;
        }

        return $this->isFeatureEnabled($feature);
    }

    /** Get available models for the current provider */
    public array $availableModels {
        get => self::MODELS[$this->provider] ?? [];
    }

    /** Get the provider display name */
    public string $providerLabel {
        get => self::PROVIDERS[$this->provider] ?? $this->provider;
    }

    /**
     * Build config from settings rows.
     *
     * @param array<string, mixed> $settings Key-value pairs from `settings` table (group=apex)
     */
    public static function fromSettings(array $settings): self
    {
        return new self(
            enabled: (bool) ($settings['enabled'] ?? false),
            provider: (string) ($settings['provider'] ?? $_ENV['APEX_PROVIDER'] ?? 'openai'),
            apiKey: (string) ($settings['api_key'] ?? $_ENV['APEX_API_KEY'] ?? ''),
            model: (string) ($settings['model'] ?? $_ENV['APEX_MODEL'] ?? 'gpt-4.1'),
            baseUrl: (string) ($settings['base_url'] ?? $_ENV['APEX_BASE_URL'] ?? ''),
            temperature: (float) ($settings['temperature'] ?? 0.7),
            maxTokens: (int) ($settings['max_tokens'] ?? 4096),
            systemPrompt: (string) ($settings['system_prompt'] ?? 'You are an AI assistant for a CMS. Help create, edit, and optimize content. Be concise, professional, and follow the user\'s instructions precisely.'),
            enabledFeatures: is_string($settings['features'] ?? null)
            ? (json_decode($settings['features'], true) ?? [])
            : ($settings['features'] ?? [
                'content_generation' => true,
                'seo_assistant' => true,
                'taxonomy_suggest' => true,
                'mosaic_ai' => true,
                'field_assistance' => true,
                'image_generation' => false,
                'image_analysis' => false,
            ]),
            budgetLimit: (float) ($settings['budget_limit'] ?? 100.00),
            alertThreshold: (float) ($settings['alert_threshold'] ?? 0.8),
            guardrails: is_string($settings['guardrails'] ?? null)
            ? (json_decode($settings['guardrails'], true) ?? [])
            : ($settings['guardrails'] ?? []),
            contentTypeOverrides: is_string($settings['content_type_overrides'] ?? null)
            ? (json_decode($settings['content_type_overrides'], true) ?? [])
            : ($settings['content_type_overrides'] ?? []),
            imageProvider: (string) ($settings['image_provider'] ?? 'openai'),
            imageApiKey: (string) ($settings['image_api_key'] ?? ''),
            imageModel: (string) ($settings['image_model'] ?? 'dall-e-3'),
            imageSettings: is_string($settings['image_settings'] ?? null)
            ? (json_decode($settings['image_settings'], true) ?? [])
            : ($settings['image_settings'] ?? ['size' => '1024x1024', 'quality' => 'standard', 'style' => 'natural']),
        );
    }

    /**
     * Serialize to settings table format.
     *
     * @return array<string, mixed>
     */
    public function toSettings(): array
    {
        return [
            'enabled' => $this->enabled ? '1' : '0',
            'provider' => $this->provider,
            'api_key' => $this->apiKey,
            'model' => $this->model,
            'base_url' => $this->baseUrl,
            'temperature' => (string) $this->temperature,
            'max_tokens' => (string) $this->maxTokens,
            'system_prompt' => $this->systemPrompt,
            'features' => json_encode($this->enabledFeatures),
            'budget_limit' => (string) $this->budgetLimit,
            'alert_threshold' => (string) $this->alertThreshold,
            'guardrails' => json_encode($this->guardrails),
            'content_type_overrides' => json_encode($this->contentTypeOverrides),
            'image_provider' => $this->imageProvider,
            'image_api_key' => $this->imageApiKey,
            'image_model' => $this->imageModel,
            'image_settings' => json_encode($this->imageSettings),
        ];
    }
}
