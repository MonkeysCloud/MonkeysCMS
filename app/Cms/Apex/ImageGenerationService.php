<?php

declare(strict_types=1);

namespace App\Cms\Apex;

use MonkeysLegion\DI\Attributes\Singleton;
use Psr\Log\LoggerInterface;

/**
 * ImageGenerationService — Multi-provider AI image generation.
 *
 * Supports OpenAI (DALL-E 3 / GPT Image 1), Google (Imagen 3 via Vertex),
 * and Stability AI (Stable Diffusion 3).
 *
 * Generated images are saved to the CMS uploads directory and registered
 * in the media library. Costs are tracked in the apex_usage_log.
 */
#[Singleton]
final class ImageGenerationService
{
    private const array PROVIDER_ENDPOINTS = [
        'openai'    => 'https://api.openai.com/v1/images/generations',
        'google'    => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:predict',
        'stability' => 'https://api.stability.ai/v2beta/stable-image/generate/sd3',
    ];

    private const array PROVIDER_COSTS = [
        'openai' => [
            'dall-e-3'    => ['1024x1024' => 0.040, '1024x1792' => 0.080, '1792x1024' => 0.080],
            'gpt-image-1' => ['1024x1024' => 0.040, '1024x1536' => 0.060, '1536x1024' => 0.060],
        ],
        'google' => [
            'imagen-3.0-generate-002' => ['1024x1024' => 0.040],
        ],
        'stability' => [
            'sd3-large'  => ['1024x1024' => 0.065],
            'sd3-medium' => ['1024x1024' => 0.035],
        ],
    ];

    public function __construct(
        private readonly ApexService $apex,
        private readonly LoggerInterface $logger,
        private readonly \PDO $pdo,
    ) {}

    /**
     * Generate an image from a text prompt.
     *
     * @param string $prompt       The image generation prompt
     * @param array  $options      Override default settings (size, quality, style, enhance_prompt)
     * @return array{media_id: int, url: string, alt_text: string, prompt_used: string, cost: float}
     * @throws \RuntimeException If generation or upload fails
     */
    public function generate(string $prompt, array $options = []): array
    {
        $config = $this->apex->config();

        if (!$config->isImageConfigured) {
            throw new \RuntimeException('Image generation is not configured. Enable it in AI Settings.');
        }

        $provider = $config->imageProvider;
        $apiKey = $config->imageApiKey;
        $model = $config->imageModel;
        $size = $options['size'] ?? $config->imageSettings['size'] ?? '1024x1024';
        $quality = $options['quality'] ?? $config->imageSettings['quality'] ?? 'standard';
        $style = $options['style'] ?? $config->imageSettings['style'] ?? 'natural';
        $enhancePrompt = (bool) ($options['enhance_prompt'] ?? true);

        // Optionally enhance the prompt using the text LLM
        $promptUsed = $prompt;
        if ($enhancePrompt && $config->isValid) {
            $promptUsed = $this->enhancePrompt($prompt, $options['context'] ?? '');
        }

        // Generate image via provider API
        $imageData = match ($provider) {
            'openai'    => $this->generateOpenAI($apiKey, $model, $promptUsed, $size, $quality, $style),
            'google'    => $this->generateGoogle($apiKey, $model, $promptUsed, $size),
            'stability' => $this->generateStability($apiKey, $model, $promptUsed, $size),
            default     => throw new \RuntimeException("Unsupported image provider: {$provider}"),
        };

        // Save to uploads directory
        $savedPath = $this->saveImage($imageData['binary'], $imageData['format'] ?? 'png');

        // Generate alt text using the text LLM
        $altText = $options['alt_text'] ?? $this->generateAltText($prompt);

        // Register in media library
        $mediaId = $this->registerMedia($savedPath, $altText, $prompt);

        // Calculate and log cost
        $cost = self::PROVIDER_COSTS[$provider][$model][$size] ?? 0.04;
        $this->logImageCost($cost, $provider, $model, $size);

        $publicUrl = str_replace(
            realpath(dirname(__DIR__, 3) . '/public') ?: '',
            '',
            realpath($savedPath) ?: $savedPath,
        );

        return [
            'media_id'    => $mediaId,
            'url'         => $publicUrl,
            'alt_text'    => $altText,
            'prompt_used' => $promptUsed,
            'cost'        => $cost,
        ];
    }

    /**
     * Enhance user prompt via text LLM for better image results.
     */
    private function enhancePrompt(string $prompt, string $context = ''): string
    {
        try {
            $systemPrompt = 'You are an expert at writing image generation prompts. '
                . 'Given a short description, expand it into a detailed, specific prompt '
                . 'optimized for AI image generation. Keep it under 200 words. '
                . 'Focus on visual details: lighting, composition, style, colors. '
                . 'Return ONLY the enhanced prompt, nothing else.';

            $userPrompt = "Enhance this image prompt: \"{$prompt}\"";
            if ($context) {
                $userPrompt .= "\nContext: {$context}";
            }

            $response = $this->apex->generate($userPrompt, $systemPrompt);
            return trim($response->content) ?: $prompt;
        } catch (\Throwable $e) {
            $this->logger->warning('Prompt enhancement failed, using original', ['error' => $e->getMessage()]);
            return $prompt;
        }
    }

    /**
     * Generate image via OpenAI DALL-E / GPT Image API.
     *
     * @return array{binary: string, format: string}
     */
    private function generateOpenAI(string $apiKey, string $model, string $prompt, string $size, string $quality, string $style): array
    {
        $payload = [
            'model'   => $model,
            'prompt'  => $prompt,
            'n'       => 1,
            'size'    => $size,
            'response_format' => 'b64_json',
        ];

        if ($model === 'dall-e-3') {
            $payload['quality'] = $quality;
            $payload['style'] = $style;
        }

        $response = $this->httpPost(
            self::PROVIDER_ENDPOINTS['openai'],
            $payload,
            ['Authorization' => "Bearer {$apiKey}"],
        );

        $data = json_decode($response, true);
        if (!isset($data['data'][0]['b64_json'])) {
            $error = $data['error']['message'] ?? 'Unknown error';
            throw new \RuntimeException("OpenAI image generation failed: {$error}");
        }

        return [
            'binary' => base64_decode($data['data'][0]['b64_json']),
            'format' => 'png',
        ];
    }

    /**
     * Generate image via Google Imagen (Vertex AI).
     *
     * @return array{binary: string, format: string}
     */
    private function generateGoogle(string $apiKey, string $model, string $prompt, string $size): array
    {
        $url = str_replace('{model}', $model, self::PROVIDER_ENDPOINTS['google'])
            . "?key={$apiKey}";

        $payload = [
            'instances' => [
                ['prompt' => $prompt],
            ],
            'parameters' => [
                'sampleCount' => 1,
                'aspectRatio' => $this->sizeToAspectRatio($size),
            ],
        ];

        $response = $this->httpPost($url, $payload);
        $data = json_decode($response, true);

        if (!isset($data['predictions'][0]['bytesBase64Encoded'])) {
            $error = $data['error']['message'] ?? 'Unknown error';
            throw new \RuntimeException("Google Imagen generation failed: {$error}");
        }

        return [
            'binary' => base64_decode($data['predictions'][0]['bytesBase64Encoded']),
            'format' => 'png',
        ];
    }

    /**
     * Generate image via Stability AI.
     *
     * @return array{binary: string, format: string}
     */
    private function generateStability(string $apiKey, string $model, string $prompt, string $size): array
    {
        $url = self::PROVIDER_ENDPOINTS['stability'];

        $boundary = bin2hex(random_bytes(16));
        $body = "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"prompt\"\r\n\r\n{$prompt}\r\n"
            . "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"model\"\r\n\r\n{$model}\r\n"
            . "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"output_format\"\r\n\r\npng\r\n"
            . "--{$boundary}--\r\n";

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Authorization: Bearer {$apiKey}\r\nContent-Type: multipart/form-data; boundary={$boundary}\r\nAccept: application/json",
                'content' => $body,
                'timeout' => 120,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new \RuntimeException('Stability AI request failed');
        }

        $data = json_decode($response, true);
        if (!isset($data['image'])) {
            $error = $data['message'] ?? 'Unknown error';
            throw new \RuntimeException("Stability AI generation failed: {$error}");
        }

        return [
            'binary' => base64_decode($data['image']),
            'format' => 'png',
        ];
    }

    /**
     * Save image binary to the uploads directory.
     */
    private function saveImage(string $binary, string $format): string
    {
        $uploadsDir = dirname(__DIR__, 3) . '/public/uploads/' . date('Y/m');
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $filename = 'ai-' . bin2hex(random_bytes(8)) . '.' . $format;
        $path = $uploadsDir . '/' . $filename;

        if (file_put_contents($path, $binary) === false) {
            throw new \RuntimeException("Failed to save generated image to: {$path}");
        }

        return $path;
    }

    /**
     * Generate alt text for the image using the text LLM.
     */
    private function generateAltText(string $prompt): string
    {
        try {
            $response = $this->apex->generate(
                "Write a concise, descriptive alt text (max 125 characters) for an image described as: \"{$prompt}\"",
                'Return ONLY the alt text, no quotes, no explanation.',
            );
            return mb_substr(trim($response->content, "\"' "), 0, 125);
        } catch (\Throwable) {
            // Fallback to prompt-based alt text
            return mb_substr($prompt, 0, 125);
        }
    }

    /**
     * Register the generated image in the CMS media library.
     */
    private function registerMedia(string $filePath, string $altText, string $prompt): int
    {
        $filename = basename($filePath);
        $filesize = filesize($filePath) ?: 0;
        $publicPath = '/uploads/' . date('Y/m') . '/' . $filename;

        // Get image dimensions
        $imageInfo = @getimagesize($filePath);
        $width = $imageInfo[0] ?? 0;
        $height = $imageInfo[1] ?? 0;

        $stmt = $this->pdo->prepare(
            'INSERT INTO media (filename, path, alt_text, mime_type, size, width, height, metadata, created_at, updated_at)
             VALUES (:filename, :path, :alt_text, :mime, :size, :width, :height, :metadata, NOW(), NOW())'
        );

        $stmt->execute([
            'filename' => $filename,
            'path'     => $publicPath,
            'alt_text' => $altText,
            'mime'     => 'image/png',
            'size'     => $filesize,
            'width'    => $width,
            'height'   => $height,
            'metadata' => json_encode([
                'ai_generated'  => true,
                'prompt'        => $prompt,
                'provider'      => $this->apex->config()->imageProvider,
                'model'         => $this->apex->config()->imageModel,
                'generated_at'  => date('c'),
            ]),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Log image generation cost to apex_usage_log.
     */
    private function logImageCost(float $cost, string $provider, string $model, string $size): void
    {
        try {
            $this->apex->logUsage(
                operation: 'image.generate',
                inputTokens: 0,
                outputTokens: 0,
                costUsd: $cost,
                contentType: null,
                nodeId: null,
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to log image generation cost', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Convert pixel size to aspect ratio string.
     */
    private function sizeToAspectRatio(string $size): string
    {
        return match ($size) {
            '1024x1024' => '1:1',
            '1024x1792', '1024x1536' => '9:16',
            '1792x1024', '1536x1024' => '16:9',
            default => '1:1',
        };
    }

    /**
     * HTTP POST helper using cURL.
     */
    private function httpPost(string $url, array $payload, array $headers = []): string
    {
        $ch = curl_init($url);

        $defaultHeaders = ['Content-Type: application/json'];
        foreach ($headers as $key => $value) {
            $defaultHeaders[] = "{$key}: {$value}";
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $defaultHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("HTTP request failed: {$error}");
        }

        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $message = $errorData['error']['message'] ?? $errorData['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException("Image API error: {$message}");
        }

        return $response;
    }
}
