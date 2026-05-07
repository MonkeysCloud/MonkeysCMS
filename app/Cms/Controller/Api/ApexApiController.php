<?php

declare(strict_types=1);

namespace App\Cms\Controller\Api;

use App\Cms\Apex\ApexService;
use App\Cms\Apex\ImageGenerationService;
use App\Cms\Apex\Tools\ContentTools;
use App\Cms\Apex\Tools\FieldTools;
use App\Cms\Apex\Tools\MosaicTools;
use App\Cms\Apex\Tools\SEOTools;
use App\Cms\Apex\Tools\TaxonomyTools;
use App\Cms\Content\ContentTypeManager;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ApexApiController — REST API endpoints for AI-powered CMS operations.
 *
 * All endpoints return JSON. Streaming endpoint uses SSE.
 * Requires authentication via the CMS auth middleware.
 */
#[RoutePrefix('/api/cms/apex')]
final class ApexApiController
{
    public function __construct(
        private readonly ApexService $apex,
        private readonly ImageGenerationService $imageService,
        private readonly ContentTypeManager $contentTypeManager,
    ) {}

    // ─── Status ─────────────────────────────────────────────────────────────

    /**
     * GET /api/cms/apex/status — Check if AI is configured and available.
     */
    #[Route('GET', '/status', name: 'api.apex.status')]
    public function status(): Response
    {
        $configured = $this->apex->isConfigured();
        $config = $this->apex->config();

        return Response::json([
            'enabled'    => $config->enabled,
            'configured' => $configured,
            'provider'   => $configured ? $config->providerLabel : null,
            'model'      => $configured ? $config->model : null,
            'features'   => $config->enabledFeatures,
            'image'      => [
                'configured' => $config->isImageConfigured,
                'provider'   => $config->isImageConfigured ? $config->imageProvider : null,
            ],
        ]);
    }

    // ─── Content Generation ─────────────────────────────────────────────────

    /**
     * POST /api/cms/apex/generate — Generate content from a prompt.
     */
    #[Route('POST', '/generate', name: 'api.apex.generate')]
    public function generate(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        if (!isset($body['prompt'])) {
            return Response::json(['error' => 'Missing required field: prompt'], 422);
        }

        $action = $body['action'] ?? 'generate';
        $format = $body['format'] ?? 'html';
        $contentType = $body['content_type'] ?? 'article';

        $systemPrompt = ContentTools::buildSystemPrompt($action, $contentType, $format);

        // Build the user prompt with context
        $prompt = $this->buildContentPrompt($body);

        try {
            $options = array_filter([
                'temperature' => isset($body['temperature']) ? (float) $body['temperature'] : null,
                'max_tokens'  => isset($body['max_tokens']) ? (int) $body['max_tokens'] : null,
            ], fn($v) => $v !== null && $v > 0);

            $response = $this->apex->generate($prompt, $systemPrompt, $body['model'] ?? null, $options);

            $this->logOperation($request, 'content.' . $action, $response);

            return Response::json([
                'content'    => $response->content,
                'model'      => $response->model,
                'tokens'     => [
                    'input'  => $response->usage->inputTokens ?? 0,
                    'output' => $response->usage->outputTokens ?? 0,
                    'total'  => $response->usage->totalTokens ?? 0,
                ],
            ]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/cms/apex/rewrite — Rewrite existing content.
     */
    #[Route('POST', '/rewrite', name: 'api.apex.rewrite')]
    public function rewrite(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        if (!isset($body['content'])) {
            return Response::json(['error' => 'Missing required field: content'], 422);
        }

        $tone = $body['tone'] ?? 'professional';
        $contentType = $body['content_type'] ?? 'article';
        $format = $body['format'] ?? 'html';

        $systemPrompt = ContentTools::buildSystemPrompt('rewrite', $contentType, $format);

        $prompt = "Rewrite the following content in a {$tone} tone:\n\n{$body['content']}";
        if (!empty($body['instructions'])) {
            $prompt .= "\n\nAdditional instructions: {$body['instructions']}";
        }

        try {
            $response = $this->apex->generate($prompt, $systemPrompt, $body['model'] ?? null);
            $this->logOperation($request, 'content.rewrite', $response);

            return Response::json([
                'content' => $response->content,
                'model'   => $response->model,
                'tokens'  => [
                    'input'  => $response->usage->inputTokens ?? 0,
                    'output' => $response->usage->outputTokens ?? 0,
                ],
            ]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/cms/apex/summarize — Summarize content.
     */
    #[Route('POST', '/summarize', name: 'api.apex.summarize')]
    public function summarize(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        if (!isset($body['content'])) {
            return Response::json(['error' => 'Missing required field: content'], 422);
        }

        $maxWords = (int) ($body['max_words'] ?? 150);
        $style = $body['style'] ?? 'paragraph';

        $systemPrompt = ContentTools::buildSystemPrompt('summarize', $body['content_type'] ?? 'article');
        $prompt = "Summarize this content in {$maxWords} words or less (style: {$style}):\n\n{$body['content']}";

        try {
            $response = $this->apex->generate($prompt, $systemPrompt, $body['model'] ?? null);
            $this->logOperation($request, 'content.summarize', $response);

            return Response::json([
                'content' => $response->content,
                'tokens'  => ['total' => $response->usage->totalTokens ?? 0],
            ]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/cms/apex/translate — Translate content.
     */
    #[Route('POST', '/translate', name: 'api.apex.translate')]
    public function translate(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        if (!isset($body['content']) || !isset($body['target_language'])) {
            return Response::json(['error' => 'Missing required fields: content, target_language'], 422);
        }

        $systemPrompt = ContentTools::buildSystemPrompt('translate', $body['content_type'] ?? 'article', $body['format'] ?? 'html');
        $prompt = "Translate to {$body['target_language']}:\n\n{$body['content']}";

        try {
            $response = $this->apex->generate($prompt, $systemPrompt, $body['model'] ?? null);
            $this->logOperation($request, 'content.translate', $response);

            return Response::json([
                'content' => $response->content,
                'tokens'  => ['total' => $response->usage->totalTokens ?? 0],
            ]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── SEO ────────────────────────────────────────────────────────────────

    /**
     * POST /api/cms/apex/seo/meta — Generate SEO metadata.
     */
    #[Route('POST', '/seo/meta', name: 'api.apex.seo.meta')]
    public function seoMeta(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        if (!isset($body['content'])) {
            return Response::json(['error' => 'Missing required field: content'], 422);
        }

        $results = [];

        // Generate meta title
        try {
            $titlePrompt = "Generate an SEO meta title for this content";
            if (!empty($body['focus_keyword'])) {
                $titlePrompt .= " (focus keyword: {$body['focus_keyword']})";
            }
            $titlePrompt .= ":\n\n" . mb_substr($body['content'], 0, 2000);

            $titleResponse = $this->apex->generate($titlePrompt, SEOTools::buildSystemPrompt('meta_title'));
            $results['meta_title'] = trim($titleResponse->content, '"\'');
        } catch (\Throwable $e) {
            $results['meta_title'] = null;
            $results['meta_title_error'] = $e->getMessage();
        }

        // Generate meta description
        try {
            $descPrompt = "Generate an SEO meta description for this content";
            if (!empty($body['focus_keyword'])) {
                $descPrompt .= " (focus keyword: {$body['focus_keyword']})";
            }
            $descPrompt .= ":\n\n" . mb_substr($body['content'], 0, 2000);

            $descResponse = $this->apex->generate($descPrompt, SEOTools::buildSystemPrompt('meta_description'));
            $results['meta_description'] = trim($descResponse->content, '"\'');
        } catch (\Throwable $e) {
            $results['meta_description'] = null;
            $results['meta_description_error'] = $e->getMessage();
        }

        // Generate keywords
        try {
            $kwPrompt = "Extract focus keywords from:\n\n" . mb_substr($body['content'], 0, 2000);
            $kwResponse = $this->apex->generate($kwPrompt, SEOTools::buildSystemPrompt('keywords'));
            $keywords = json_decode($kwResponse->content, true);
            $results['keywords'] = is_array($keywords) ? $keywords : [$kwResponse->content];
        } catch (\Throwable $e) {
            $results['keywords'] = [];
        }

        $this->logOperation($request, 'seo.meta', null);

        return Response::json($results);
    }

    /**
     * POST /api/cms/apex/seo/analyze — Analyze content for SEO.
     */
    #[Route('POST', '/seo/analyze', name: 'api.apex.seo.analyze')]
    public function seoAnalyze(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        if (!isset($body['content'])) {
            return Response::json(['error' => 'Missing required field: content'], 422);
        }

        $seoTitle = $body['title'] ?? 'N/A';
        $seoMeta = $body['meta_description'] ?? 'N/A';
        $seoKeyword = $body['focus_keyword'] ?? 'N/A';
        $seoContent = mb_substr($body['content'], 0, 4000);
        $prompt = "Analyze this content for SEO:\nTitle: {$seoTitle}\nMeta Description: {$seoMeta}\nFocus Keyword: {$seoKeyword}\n\nContent:\n{$seoContent}";

        try {
            $response = $this->apex->generate($prompt, SEOTools::buildSystemPrompt('analyze_seo'));
            $analysis = json_decode($response->content, true);

            if (!is_array($analysis)) {
                $analysis = ['raw' => $response->content];
            }

            $this->logOperation($request, 'seo.analyze', $response);
            return Response::json($analysis);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Taxonomy ───────────────────────────────────────────────────────────

    /**
     * POST /api/cms/apex/taxonomy/suggest — Suggest tags/categories for content.
     */
    #[Route('POST', '/taxonomy/suggest', name: 'api.apex.taxonomy.suggest')]
    public function taxonomySuggest(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        if (!isset($body['content'])) {
            return Response::json(['error' => 'Missing required field: content'], 422);
        }

        $existingTags = $body['existing_tags'] ?? '[]';
        $maxSuggestions = (int) ($body['max_suggestions'] ?? 10);

        $prompt = "Analyze this content and suggest relevant tags.\n";
        if ($existingTags !== '[]') {
            $prompt .= "Existing tags in the vocabulary: {$existingTags}\nPrefer matching existing tags when relevant.\n";
        }
        $prompt .= "Maximum {$maxSuggestions} suggestions.\n\nContent:\n" . mb_substr($body['content'], 0, 3000);

        try {
            $response = $this->apex->generate($prompt, TaxonomyTools::buildSystemPrompt('suggest_tags'));
            $suggestions = json_decode($response->content, true);

            if (!is_array($suggestions)) {
                $suggestions = [['name' => trim($response->content), 'confidence' => 0.5]];
            }

            $this->logOperation($request, 'taxonomy.suggest', $response);
            return Response::json(['suggestions' => $suggestions]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/cms/apex/taxonomy/generate-terms — Generate terms for a vocabulary.
     */
    #[Route('POST', '/taxonomy/generate-terms', name: 'api.apex.taxonomy.generate_terms')]
    public function taxonomyGenerateTerms(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        $vocabName = $body['vocabulary_name'] ?? 'General';
        $vocabDesc = $body['vocabulary_description'] ?? '';
        $count = min((int) ($body['count'] ?? 10), 50);
        $existingTerms = $body['existing_terms'] ?? '[]';
        $hierarchical = (bool) ($body['hierarchical'] ?? false);

        $prompt = "Generate {$count} taxonomy terms for a vocabulary called \"{$vocabName}\".";
        if ($vocabDesc) {
            $prompt .= "\nVocabulary description: {$vocabDesc}";
        }
        if ($existingTerms !== '[]') {
            $prompt .= "\nAlready existing terms (do NOT repeat these): {$existingTerms}";
        }
        if ($hierarchical) {
            $prompt .= "\nThis is a hierarchical vocabulary — organize terms with parent/child relationships where logical.";
            $prompt .= "\nReturn JSON array: [{\"name\": \"Term\", \"description\": \"Brief description\", \"parent\": null}, {\"name\": \"Child Term\", \"description\": \"...\", \"parent\": \"Parent Term Name\"}]";
        } else {
            $prompt .= "\nReturn JSON array: [{\"name\": \"Term\", \"description\": \"Brief description\"}]";
        }

        $systemPrompt = "You are a taxonomy expert. Generate meaningful, well-organized taxonomy terms for a CMS vocabulary. "
            . "Return ONLY valid JSON — an array of objects. Each term should have a clear, concise name (2-4 words max) "
            . "and a brief description (1 sentence). Terms should be diverse, relevant, and useful for content categorization.";

        try {
            $response = $this->apex->generate($prompt, $systemPrompt, $body['model'] ?? null);
            $raw = $response->content;

            // Extract everything between the first '[' and the last ']'
            $start = strpos($raw, '[');
            $end = strrpos($raw, ']');

            $terms = null;
            if ($start !== false && $end !== false && $end > $start) {
                $jsonString = substr($raw, $start, $end - $start + 1);
                $terms = json_decode($jsonString, true);
            }

            if (!is_array($terms)) {
                // Fallback attempt to decode the raw response
                $terms = json_decode($raw, true);
            }

            if (!is_array($terms)) {
                return Response::json([
                    'error' => 'Failed to parse AI response',
                    'json_error' => json_last_error_msg(),
                    'raw' => $raw
                ], 500);
            }

            $this->logOperation($request, 'taxonomy.generate_terms', $response);

            return Response::json([
                'terms' => $terms,
                'count' => count($terms),
                'tokens' => [
                    'input'  => $response->usage->inputTokens ?? 0,
                    'output' => $response->usage->outputTokens ?? 0,
                ],
            ]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Mosaic ─────────────────────────────────────────────────────────────

    /**
     * POST /api/cms/apex/mosaic/generate — Generate Mosaic layout.
     */
    #[Route('POST', '/mosaic/generate', name: 'api.apex.mosaic.generate')]
    public function mosaicGenerate(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        if (!isset($body['description'])) {
            return Response::json(['error' => 'Missing required field: description'], 422);
        }

        $sectionCount = (int) ($body['section_count'] ?? 4);
        $prompt = "Generate a {$sectionCount}-section Mosaic page layout for: {$body['description']}";
        if (!empty($body['content_type'])) {
            $prompt .= "\nContent type: {$body['content_type']}";
        }

        try {
            $response = $this->apex->generate($prompt, MosaicTools::buildSystemPrompt('page_layout'));
            $layout = json_decode($response->content, true);

            if (!is_array($layout)) {
                return Response::json(['error' => 'Failed to parse layout response', 'raw' => $response->content], 500);
            }

            $this->logOperation($request, 'mosaic.generate', $response);
            return Response::json(['layout' => $layout]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/cms/apex/mosaic/block — Generate content for a Mosaic block.
     */
    #[Route('POST', '/mosaic/block', name: 'api.apex.mosaic.block')]
    public function mosaicBlock(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        if (!isset($body['prompt']) || !isset($body['block_type'])) {
            return Response::json(['error' => 'Missing required fields: prompt, block_type'], 422);
        }

        $prompt = "Generate {$body['block_type']} block content: {$body['prompt']}";
        if (!empty($body['context'])) {
            $prompt .= "\nPage context: {$body['context']}";
        }

        try {
            $response = $this->apex->generate($prompt, MosaicTools::buildSystemPrompt('block_content'));
            $this->logOperation($request, 'mosaic.block', $response);

            return Response::json([
                'content'    => $response->content,
                'block_type' => $body['block_type'],
            ]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Fields ─────────────────────────────────────────────────────────────

    /**
     * POST /api/cms/apex/fields/fill — Auto-fill field values.
     */
    #[Route('POST', '/fields/fill', name: 'api.apex.fields.fill')]
    public function fieldsFill(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        if (!isset($body['field_label']) || !isset($body['field_type'])) {
            return Response::json(['error' => 'Missing required fields: field_label, field_type'], 422);
        }

        $prompt = "Generate content for field '{$body['field_label']}' (type: {$body['field_type']})";
        if (!empty($body['context'])) {
            $prompt .= "\nContext: {$body['context']}";
        }
        if (!empty($body['constraints'])) {
            $prompt .= "\nConstraints: {$body['constraints']}";
        }

        try {
            $response = $this->apex->generate($prompt, FieldTools::buildSystemPrompt('fill_field'));
            $this->logOperation($request, 'fields.fill', $response);

            return Response::json(['value' => trim($response->content, '"\'')]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/cms/apex/fields/extract — Extract fields from text.
     */
    #[Route('POST', '/fields/extract', name: 'api.apex.fields.extract')]
    public function fieldsExtract(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        if (!isset($body['text']) || !isset($body['field_definitions'])) {
            return Response::json(['error' => 'Missing required fields: text, field_definitions'], 422);
        }

        $prompt = "Extract field values from this text based on these field definitions:\n\nFields: {$body['field_definitions']}\n\nText:\n{$body['text']}";

        try {
            $response = $this->apex->generate($prompt, FieldTools::buildSystemPrompt('extract_fields'));
            $fields = json_decode($response->content, true);

            if (!is_array($fields)) {
                $fields = ['raw' => $response->content];
            }

            $this->logOperation($request, 'fields.extract', $response);
            return Response::json(['fields' => $fields]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Streaming ──────────────────────────────────────────────────────────

    /**
     * POST /api/cms/apex/stream — SSE streaming endpoint for real-time generation.
     */
    #[Route('POST', '/stream', name: 'api.apex.stream')]
    public function stream(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        if (!isset($body['prompt'])) {
            return Response::json(['error' => 'Missing required field: prompt'], 422);
        }

        $action = $body['action'] ?? 'generate';
        $contentType = $body['content_type'] ?? 'article';
        $format = $body['format'] ?? 'html';
        $systemPrompt = $body['system'] ?? ContentTools::buildSystemPrompt($action, $contentType, $format);

        $prompt = $this->buildContentPrompt($body);

        try {
            $stream = $this->apex->stream($prompt, $systemPrompt, $body['model'] ?? null);

            // Build SSE response
            $output = '';
            ob_start();
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            foreach ($stream as $chunk) {
                echo "data: " . json_encode([
                    'delta' => $chunk->delta,
                    'type'  => 'text',
                ]) . "\n\n";
                ob_flush();
                flush();
                $output .= $chunk->delta;
            }

            echo "data: " . json_encode([
                'type'    => 'done',
                'content' => $output,
            ]) . "\n\n";
            ob_flush();
            flush();

            $this->logOperation($request, 'stream.' . $action, null);

            // Return empty response since we've already sent SSE data
            return Response::html('');
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Costs ──────────────────────────────────────────────────────────────

    /**
     * GET /api/cms/apex/costs — Get cost/usage report.
     */
    #[Route('GET', '/costs', name: 'api.apex.costs')]
    public function costs(ServerRequestInterface $request): Response
    {
        $period = $request->getQueryParams()['period'] ?? 'month';

        try {
            $summary = $this->apex->getUsageSummary($period);
            $sessionReport = $this->apex->getCostReport();

            return Response::json([
                'database'  => $summary,
                'session'   => $sessionReport?->toArray(),
                'budget'    => [
                    'limit'     => $this->apex->config()->budgetLimit,
                    'used'      => $summary['total_cost'],
                    'remaining' => max(0, $this->apex->config()->budgetLimit - $summary['total_cost']),
                ],
            ]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Test Connection ────────────────────────────────────────────────────

    /**
     * POST /api/cms/apex/test — Test provider connection.
     */
    #[Route('POST', '/test', name: 'api.apex.test')]
    public function test(): Response
    {
        try {
            $result = $this->apex->testConnection();
            return Response::json($result, $result['success'] ? 200 : 500);
        } catch (\Throwable $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function parseBody(ServerRequestInterface $request): array
    {
        $body = json_decode((string) $request->getBody(), true);
        return is_array($body) ? $body : [];
    }

    /**
     * Build the user prompt from the request body.
     */
    private function buildContentPrompt(array $body): string
    {
        $prompt = $body['prompt'] ?? '';

        // Add content context if available
        if (!empty($body['title'])) {
            $prompt = "Title: {$body['title']}\n\n" . $prompt;
        }
        if (!empty($body['content']) && ($body['action'] ?? '') !== 'generate') {
            $prompt .= "\n\nExisting content:\n{$body['content']}";
        }
        if (!empty($body['instructions'])) {
            $prompt .= "\n\nInstructions: {$body['instructions']}";
        }
        if (!empty($body['tone'])) {
            $prompt .= "\n\nTone: {$body['tone']}";
        }
        if (!empty($body['length'])) {
            $prompt .= "\n\nTarget length: {$body['length']} words";
        }

        return $prompt;
    }

    /**
     * Log an AI operation to the database.
     */
    private function logOperation(ServerRequestInterface $request, string $operation, ?object $response): void
    {
        try {
            $userId = $request->getAttribute('user')?->id ?? null;
            $body = $this->parseBody($request);

            $this->apex->logUsage(
                operation: $operation,
                inputTokens: $response?->usage?->inputTokens ?? 0,
                outputTokens: $response?->usage?->outputTokens ?? 0,
                costUsd: 0.0, // TODO: Calculate from CostTracker
                userId: $userId,
                contentType: $body['content_type'] ?? null,
                nodeId: isset($body['node_id']) ? (int) $body['node_id'] : null,
            );
        } catch (\Throwable) {
            // Silently ignore logging failures
        }
    }

    // ─── Image Generation ───────────────────────────────────────────────────

    /**
     * POST /api/cms/apex/image/generate — Generate an AI image.
     */
    #[Route('POST', '/image/generate', name: 'api.apex.image.generate')]
    public function imageGenerate(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        if (!isset($body['prompt'])) {
            return Response::json(['error' => 'Missing required field: prompt'], 422);
        }

        try {
            $result = $this->imageService->generate($body['prompt'], [
                'size'           => $body['size'] ?? null,
                'quality'        => $body['quality'] ?? null,
                'style'          => $body['style'] ?? null,
                'enhance_prompt' => $body['enhance_prompt'] ?? true,
                'context'        => $body['context'] ?? '',
                'alt_text'       => $body['alt_text'] ?? null,
            ]);

            return Response::json($result);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Field Config ───────────────────────────────────────────────────────

    private function getDefaultActionsForType(string $type): array
    {
        return match ($type) {
            'string' => ['generate', 'rewrite'],
            'text', 'html', 'markdown' => ['generate', 'rewrite', 'summarize', 'expand', 'translate', 'grammar'],
            'image', 'gallery' => ['generate_image'],
            'slug' => ['generate_slug'],
            'taxonomy' => ['suggest_tags'],
            default => [],
        };
    }

    /**
     * GET /api/cms/apex/field-config/{contentType} — Get AI field configuration.
     */
    #[Route('GET', '/field-config/{contentType}', name: 'api.apex.field-config')]
    public function fieldConfig(ServerRequestInterface $request, string $contentType): Response
    {
        $config = $this->apex->config();

        if (!$config->enabled) {
            return Response::json(['enabled' => false, 'fields' => []]);
        }

        // Check content-type-specific AI config from settings
        $ctOverrides = $config->contentTypeOverrides[$contentType] ?? [];
        $ctEnabled = (bool) ($ctOverrides['enabled'] ?? true);

        if (!$ctEnabled) {
            return Response::json(['enabled' => false, 'fields' => []]);
        }

        // Get field mapping from content type settings
        $fieldConfig = $ctOverrides['fields'] ?? [];

        // Auto-discover AI-enabled fields if no explicit config exists
        if (empty($fieldConfig)) {
            $ctFields = $this->contentTypeManager->getFieldsFor($contentType);
            foreach ($ctFields as $field) {
                // Enable AI by default for supported field types
                if (in_array($field->field_type, ['string', 'text', 'html', 'markdown', 'image', 'gallery', 'slug', 'taxonomy'])) {
                    $fieldConfig[$field->machine_name] = [
                        'enabled' => true,
                        'actions' => $this->getDefaultActionsForType($field->field_type),
                    ];
                }
            }
        }
        $actions = [
            'string'    => ['generate', 'rewrite'],
            'text'      => ['generate', 'rewrite', 'summarize', 'expand', 'translate', 'grammar'],
            'html'      => ['generate', 'rewrite', 'summarize', 'expand', 'translate', 'grammar'],
            'markdown'  => ['generate', 'rewrite', 'summarize', 'expand', 'translate', 'grammar'],
            'image'     => ['generate_image', 'alt_text'],
            'gallery'   => ['generate_image'],
            'video'     => [],
            'select'    => ['generate_options'],
            'taxonomy'  => ['suggest_tags'],
            'slug'      => ['generate_slug'],
            'email'     => ['generate'],
            'url'       => [],
            'boolean'   => [],
            'integer'   => [],
            'float'     => [],
            'decimal'   => [],
            'date'      => [],
            'datetime'  => [],
        ];

        return Response::json([
            'enabled'  => true,
            'fields'   => $fieldConfig,
            'actions'  => $actions,
            'features' => $config->enabledFeatures,
        ]);
    }
}
