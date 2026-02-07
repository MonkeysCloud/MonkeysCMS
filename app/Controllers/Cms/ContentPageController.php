<?php

declare(strict_types=1);

namespace App\Controllers\Cms;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use MonkeysLegion\Template\Renderer;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use App\Cms\ContentTypes\ContentTypeManager;
use App\Cms\Theme\FieldRenderer;

/**
 * Frontend controller for displaying published content
 */
class ContentPageController
{
    private Renderer $renderer;
    private ConnectionInterface $connection;
    private ContentTypeManager $contentTypeManager;

    public function __construct(
        Renderer $renderer,
        ConnectionInterface $connection,
        ContentTypeManager $contentTypeManager
    ) {
        $this->renderer = $renderer;
        $this->connection = $connection;
        $this->contentTypeManager = $contentTypeManager;
    }

    /**
     * View content by slug (catch-all route for frontend content)
     * This should be registered last to not interfere with other routes
     */
    #[Route('GET', '/{slug}')]
    public function viewBySlug(ServerRequestInterface $request, string $slug): ResponseInterface
    {
        // Don't handle admin routes
        if (str_starts_with($slug, 'admin') || str_starts_with($slug, 'api')) {
            return new HtmlResponse('Not Found', 404);
        }
        
        // Try to find content by slug across all content types
        $types = $this->contentTypeManager->getTypes();
        $pdo = $this->connection->pdo();
        
        foreach ($types as $typeId => $typeData) {
            $tableName = 'content_' . $typeId;
            
            try {
                // Check if table exists
                $stmt = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
                if ($stmt->rowCount() === 0) {
                    continue;
                }
                
                // Look for content with this slug
                $stmt = $pdo->prepare("SELECT * FROM {$tableName} WHERE slug = ?");
                $stmt->execute([$slug]);
                $item = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($item) {
                    return $this->renderContent($item, $typeId, $typeData);
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Not found
        return new HtmlResponse($this->render404(), 404);
    }
    
    /**
     * View content by type and slug
     */
    #[Route('GET', '/content/{type}/{slug}')]
    public function viewByTypeAndSlug(ServerRequestInterface $request, string $type, string $slug): ResponseInterface
    {
        $contentType = $this->contentTypeManager->getType($type);
        if (!$contentType) {
            return new HtmlResponse($this->render404(), 404);
        }
        
        $entity = $contentType['entity'];
        $tableName = $entity->getContentTableName();
        $pdo = $this->connection->pdo();
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM {$tableName} WHERE slug = ?");
            $stmt->execute([$slug]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($item) {
                return $this->renderContent($item, $type, $contentType);
            }
        } catch (\Exception $e) {
            // Table doesn't exist or error
        }
        
        return new HtmlResponse($this->render404(), 404);
    }
    
    /**
     * Render content with all fields
     */
    private function renderContent(array $item, string $typeId, array $contentType): ResponseInterface
    {
        // Only show published content on frontend (unless preview mode)
        if (($item['status'] ?? 'draft') !== 'published') {
            // Check for preview token in URL - for now, allow drafts for testing
            // return new HtmlResponse($this->render404(), 404);
        }
        
        // Prepare fields for display
        $fields = [];
        $fieldsConfig = $contentType['fields'] ?? [];
        
        foreach ($fieldsConfig as $fieldData) {
            $machineName = $fieldData['machine_name'] ?? '';
            if (empty($machineName)) {
                continue;
            }
            
            $value = $item[$machineName] ?? null;
            if ($value === null) {
                continue;
            }
            
            // Format field for display
            $fields[$machineName] = [
                'label' => $fieldData['name'] ?? ucfirst(str_replace('_', ' ', $machineName)),
                'value' => $this->formatFieldValue($value, $fieldData['type'] ?? 'text', $fieldData),
                'raw_value' => $value,
                'type' => $fieldData['type'] ?? 'text',
            ];
        }
        
        // Create FieldRenderer for templates
        $fieldRenderer = FieldRenderer::fromContent($item, $contentType);
        
        // Prepare template data
        $data = [
            'title' => $item['title'] ?? 'Untitled',
            'item' => $item,
            'fields' => $fields,
            'fieldRenderer' => $fieldRenderer,  // Global field rendering helper
            'type' => $contentType,
            'type_id' => $typeId,
            'created_at' => $item['created_at'] ?? null,
            'updated_at' => $item['updated_at'] ?? null,
            'status' => $item['status'] ?? 'draft',
            'meta' => [
                'description' => strip_tags(substr($item['field_body'] ?? '', 0, 160)),
                'og_title' => $item['title'] ?? '',
            ],
        ];
        
        // Try to render with type-specific template first, fall back to generic
        $templateName = 'content/' . $typeId;
        try {
            $html = $this->renderer->render($templateName, $data);
        } catch (\Exception $e) {
            // Fall back to generic content template
            try {
                $html = $this->renderer->render('content/view', $data);
            } catch (\Exception $e2) {
                // Ultimate fallback
                $html = $this->renderFallbackContent($data);
            }
        }
        
        return new HtmlResponse($html);
    }
    
    /**
     * Format a field value for display
     */
    private function formatFieldValue(mixed $value, string $type, array $fieldData): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        
        return match ($type) {
            'wysiwyg', 'html', 'rich_text' => (string) $value, // Already HTML
            'markdown' => $this->parseMarkdown((string) $value),
            'textarea', 'text', 'string' => nl2br(htmlspecialchars((string) $value)),
            'number', 'integer', 'float' => (string) $value,
            'boolean' => $value ? 'Yes' : 'No',
            'date' => date('F j, Y', strtotime((string) $value)),
            'datetime' => date('F j, Y g:i A', strtotime((string) $value)),
            'email' => '<a href="mailto:' . htmlspecialchars((string) $value) . '">' . htmlspecialchars((string) $value) . '</a>',
            'url', 'link' => '<a href="' . htmlspecialchars((string) $value) . '" target="_blank">' . htmlspecialchars((string) $value) . '</a>',
            'image' => '<img src="' . htmlspecialchars((string) $value) . '" alt="" class="max-w-full h-auto">',
            default => is_array($value) ? json_encode($value) : htmlspecialchars((string) $value),
        };
    }
    
    /**
     * Simple Markdown parser (basic conversion)
     */
    private function parseMarkdown(string $text): string
    {
        // Basic markdown conversion
        $text = htmlspecialchars($text);
        
        // Headers
        $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $text);
        
        // Bold and italic
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        
        // Links
        $text = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $text);
        
        // Paragraphs
        $text = '<p>' . str_replace("\n\n", '</p><p>', $text) . '</p>';
        $text = str_replace("\n", '<br>', $text);
        
        return $text;
    }
    
    /**
     * Render fallback content when no template exists
     */
    private function renderFallbackContent(array $data): string
    {
        $title = htmlspecialchars($data['title'] ?? 'Content');
        $body = $data['fields']['field_body']['value'] ?? $data['item']['field_body'] ?? '';
        $created = $data['created_at'] ? date('F j, Y', strtotime($data['created_at'])) : '';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <link rel="stylesheet" href="/css/app.css">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; line-height: 1.6; color: #374151; background: #f3f4f6; }
        .container { max-width: 800px; margin: 0 auto; padding: 2rem; }
        .content-card { background: white; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 2rem; margin-top: 2rem; }
        h1 { font-size: 2rem; font-weight: bold; color: #111827; margin-bottom: 0.5rem; }
        .meta { color: #6b7280; font-size: 0.875rem; margin-bottom: 2rem; }
        .content { font-size: 1.125rem; }
        .content p { margin-bottom: 1rem; }
        .back-link { display: inline-block; margin-bottom: 1rem; color: #3b82f6; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <a href="/" class="back-link">&larr; Back to Home</a>
        <article class="content-card">
            <header>
                <h1>{$title}</h1>
                <div class="meta">Published {$created}</div>
            </header>
            <div class="content">
                {$body}
            </div>
        </article>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Render 404 page
     */
    private function render404(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f3f4f6; }
        .error { text-align: center; }
        h1 { font-size: 6rem; font-weight: bold; color: #d1d5db; margin: 0; }
        p { color: #6b7280; margin-top: 1rem; }
        a { color: #3b82f6; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="error">
        <h1>404</h1>
        <p>The page you're looking for doesn't exist.</p>
        <p><a href="/">Go back home</a></p>
    </div>
</body>
</html>
HTML;
    }
}
