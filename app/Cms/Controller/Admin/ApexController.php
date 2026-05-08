<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Apex\ApexConfig;
use App\Cms\Apex\ApexService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ApexController — Admin UI for AI Assistant configuration.
 */
#[RoutePrefix('/admin/ai')]
final class ApexController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly ApexService $apex,
    ) {}

    /**
     * GET /admin/ai — Settings dashboard.
     */
    #[Route('GET', '/', name: 'admin::apex.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $config = $this->apex->config();
        $flash = $request->getAttribute('flash', []);

        // Get usage summary (silently handle missing table)
        $usage = ['total_cost' => 0, 'total_requests' => 0, 'by_operation' => [], 'by_model' => []];
        try {
            $usage = $this->apex->getUsageSummary('month');
        } catch (\Throwable) {}

        return Response::html($this->renderer->render('admin::apex.index', [
            'title'          => 'AI Assistant',
            'config'         => $config,
            'providers'      => ApexConfig::PROVIDERS,
            'models'         => ApexConfig::MODELS,
            'features'       => ApexConfig::FEATURES,
            'imageProviders' => ApexConfig::IMAGE_PROVIDERS,
            'imageModels'    => ApexConfig::IMAGE_MODELS,
            'usage'          => $usage,
            'flash'          => $flash,
        ]));
    }

    /**
     * POST /admin/ai/settings — Save provider settings.
     */
    #[Route('POST', '/settings', name: 'admin::apex.save')]
    public function save(ServerRequestInterface $request): Response
    {
        $post = $request->getParsedBody();

        $config = new ApexConfig(
            enabled: isset($post['enabled']),
            provider: $post['provider'] ?? 'openai',
            apiKey: $post['api_key'] ?? '',
            model: $post['model'] ?? 'gpt-4.1',
            baseUrl: $post['base_url'] ?? '',
            temperature: (float) ($post['temperature'] ?? 0.7),
            maxTokens: (int) ($post['max_tokens'] ?? 4096),
            systemPrompt: $post['system_prompt'] ?? '',
            enabledFeatures: [
                'content_generation' => isset($post['feature_content_generation']),
                'seo_assistant'      => isset($post['feature_seo_assistant']),
                'taxonomy_suggest'   => isset($post['feature_taxonomy_suggest']),
                'mosaic_ai'          => isset($post['feature_mosaic_ai']),
                'field_assistance'   => isset($post['feature_field_assistance']),
                'image_generation'   => isset($post['feature_image_generation']),
                'image_analysis'     => isset($post['feature_image_analysis']),
            ],
            budgetLimit: (float) ($post['budget_limit'] ?? 100.00),
            alertThreshold: (float) ($post['alert_threshold'] ?? 0.8),
            guardrails: [
                'pii_detection'    => isset($post['guard_pii_detection']),
                'prompt_injection' => isset($post['guard_prompt_injection']),
                'toxicity_filter'  => isset($post['guard_toxicity_filter']),
                'max_output_words' => (int) ($post['guard_max_output_words'] ?? 5000),
            ],
            imageProvider: $post['image_provider'] ?? 'openai',
            imageApiKey: $post['image_api_key'] ?? '',
            imageModel: $post['image_model'] ?? 'dall-e-3',
            imageSettings: [
                'size'    => $post['image_size'] ?? '1024x1024',
                'quality' => $post['image_quality'] ?? 'standard',
                'style'   => $post['image_style'] ?? 'natural',
            ],
        );

        $this->apex->saveConfig($config);

        return Response::redirect('/admin/ai?saved=1');
    }
}
