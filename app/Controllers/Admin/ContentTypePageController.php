<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\ContentTypes\ContentTypeManager;
use App\Cms\Auth\SessionManager;
use App\Modules\Core\Services\MenuService;
use App\Cms\Fields\FieldType;
use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\Widget\WidgetRegistry;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Assets\AssetManager;
use MonkeysLegion\Template\MLView;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\Response\JsonResponse;

/**
 * ContentTypePageController - Admin UI for managing content types (page views)
 * 
 * Provides page-based admin views at /admin/structure/content-types
 * Mirrors the BlockTypeController pattern.
 */
class ContentTypePageController extends BaseAdminController
{
    public function __construct(
        MLView $view,
        MenuService $menuService,
        SessionManager $session,
        AssetManager $assetManager,
        private readonly ContentTypeManager $contentTypeManager,
        private readonly WidgetRegistry $widgetRegistry,
        private readonly ConnectionInterface $connection,
    ) {
        parent::__construct($view, $menuService, $session);
        $this->setAssetManager($assetManager);
    }

    /**
     * List all content types
     */
    #[Route('GET', '/admin/structure/content-types')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $types = $this->contentTypeManager->getTypes();

        return $this->render('admin/structure/content_types/index', [
            'types' => $types,
            'title' => 'Content Types',
        ]);
    }

    /**
     * Content management dashboard
     * Shows available content types and recent content items
     * Supports search, filtering, and sorting
     */
    #[Route('GET', '/admin/content')]
    public function contentIndex(ServerRequestInterface $request): ResponseInterface
    {
        $types = $this->contentTypeManager->getTypes();
        
        // Get query parameters for filtering/search/sorting
        $queryParams = $request->getQueryParams();
        $search = trim($queryParams['search'] ?? '');
        $filterType = $queryParams['type'] ?? '';
        $filterStatus = $queryParams['status'] ?? '';
        $sortBy = $queryParams['sort'] ?? 'created_at';
        $sortDir = strtoupper($queryParams['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        
        // Validate sort column
        $allowedSortColumns = ['title', 'created_at', 'updated_at', 'status'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }
        
        // Get all content items with filters
        $recentContent = [];
        $pdo = $this->connection->pdo();
        
        // Build user lookup (cache user names)
        $users = [];
        try {
            $stmt = $pdo->query("SELECT id, display_name, email FROM users");
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $users[$row['id']] = $row['display_name'] ?: $row['email'];
            }
        } catch (\Exception $e) {
            // Users table might not exist
        }
        
        // Filter types if a specific type is selected
        $typesToQuery = !empty($filterType) && isset($types[$filterType]) 
            ? [$filterType => $types[$filterType]] 
            : $types;
        
        foreach ($typesToQuery as $typeId => $typeData) {
            $tableName = 'content_' . $typeId;
            
            // Check if table exists before querying
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
                if ($stmt->rowCount() === 0) {
                    continue; // Table doesn't exist, skip this type
                }
                
                // Build query with filters
                $where = [];
                $params = [];
                
                if (!empty($search)) {
                    $where[] = "(title LIKE ? OR slug LIKE ?)";
                    $params[] = "%{$search}%";
                    $params[] = "%{$search}%";
                }
                
                if (!empty($filterStatus)) {
                    $where[] = "status = ?";
                    $params[] = $filterStatus;
                }
                
                $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
                
                // Get items from this content type
                $sql = "SELECT id, uuid, title, slug, status, created_at, updated_at, author_id 
                        FROM {$tableName} {$whereClause} 
                        ORDER BY {$sortBy} {$sortDir} 
                        LIMIT 50";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                foreach ($items as $item) {
                    $authorId = $item['author_id'] ?? null;
                    $recentContent[] = [
                        'id' => $item['id'],
                        'uuid' => $item['uuid'],
                        'title' => $item['title'],
                        'slug' => $item['slug'],
                        'status' => $item['status'] ?? 'draft',
                        'type' => $typeId,
                        'type_label' => $typeData['label'] ?? ucfirst($typeId),
                        'created_at' => $item['created_at'],
                        'updated_at' => $item['updated_at'],
                        'author_id' => $authorId,
                        'user_name' => $authorId && isset($users[$authorId]) ? $users[$authorId] : 'Unknown',
                        'edit_url' => '/admin/content/' . $typeId . '/' . $item['id'] . '/edit',
                        'view_url' => '/admin/content/' . $typeId,
                    ];
                }
            } catch (\Exception $e) {
                // Table doesn't exist or query failed - skip
                continue;
            }
        }
        
        // Sort all content if multiple types were queried
        if (empty($filterType)) {
            usort($recentContent, function($a, $b) use ($sortBy, $sortDir) {
                $aVal = $a[$sortBy] ?? '';
                $bVal = $b[$sortBy] ?? '';
                
                if ($sortBy === 'created_at' || $sortBy === 'updated_at') {
                    $aVal = strtotime($aVal);
                    $bVal = strtotime($bVal);
                }
                
                $cmp = $aVal <=> $bVal;
                return $sortDir === 'DESC' ? -$cmp : $cmp;
            });
        }
        
        // Limit results
        $recentContent = array_slice($recentContent, 0, 50);

        return $this->render('admin/content/index', [
            'types' => $types,
            'recentContent' => $recentContent,
            'title' => 'Content',
            'filters' => [
                'search' => $search,
                'type' => $filterType,
                'status' => $filterStatus,
                'sort' => $sortBy,
                'dir' => $sortDir,
            ],
        ]);
    }

    /**
     * Bulk operations on content items
     */
    #[Route('POST', '/admin/content/bulk')]
    public function contentBulk(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $action = $data['action'] ?? '';
        $items = $data['items'] ?? [];
        
        if (empty($action) || empty($items)) {
            return new RedirectResponse('/admin/content');
        }
        
        $pdo = $this->connection->pdo();
        $processed = 0;
        
        foreach ($items as $item) {
            // Format: type:id
            if (!str_contains($item, ':')) continue;
            [$type, $id] = explode(':', $item, 2);
            
            $tableName = 'content_' . $type;
            
            try {
                switch ($action) {
                    case 'publish':
                        $stmt = $pdo->prepare("UPDATE {$tableName} SET status = 'published', updated_at = NOW() WHERE id = ?");
                        $stmt->execute([(int)$id]);
                        $processed++;
                        break;
                        
                    case 'unpublish':
                        $stmt = $pdo->prepare("UPDATE {$tableName} SET status = 'draft', updated_at = NOW() WHERE id = ?");
                        $stmt->execute([(int)$id]);
                        $processed++;
                        break;
                        
                    case 'delete':
                        $stmt = $pdo->prepare("DELETE FROM {$tableName} WHERE id = ?");
                        $stmt->execute([(int)$id]);
                        $processed++;
                        break;
                }
            } catch (\Exception $e) {
                // Skip items that fail
                continue;
            }
        }
        
        return new RedirectResponse('/admin/content?bulk=' . $action . '&count=' . $processed);
    }

    /**
     * List content items of a specific type
     */
    #[Route('GET', '/admin/content/{type}')]
    public function contentList(ServerRequestInterface $request, string $type): ResponseInterface
    {
        // Debug logging to app log file
        $logFile = (defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd()) . '/var/logs/app-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] DEBUG: contentList called for type: {$type}, method: " . $request->getMethod() . "\n", FILE_APPEND);
        
        $contentType = $this->contentTypeManager->getType($type);
        if (!$contentType) {
            return new RedirectResponse('/admin/content');
        }

        $entity = $contentType['entity'];
        $tableName = $entity->getContentTableName();
        
        // Get content items
        $items = [];
        try {
            $pdo = $this->connection->pdo();
            $stmt = $pdo->query("SELECT * FROM {$tableName} ORDER BY created_at DESC LIMIT 100");
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        return $this->render('admin/content/list', [
            'type' => $contentType,
            'type_id' => $type,
            'items' => $items,
            'title' => $contentType['label_plural'] ?? $contentType['label'] . 's',
            'add_url' => '/admin/content/' . $type . '/add',
        ]);
    }

    /**
     * Create new content of a specific type (GET shows form, POST saves)
     */
    #[Route(['GET', 'POST'], '/admin/content/{type}/add')]
    public function contentCreate(ServerRequestInterface $request, string $type): ResponseInterface
    {
        // Debug logging at start
        $logFile = (defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd()) . '/var/logs/app-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] DEBUG: contentCreate called for type: {$type}, method: " . $request->getMethod() . "\n", FILE_APPEND);
        
        $contentType = $this->contentTypeManager->getType($type);
        if (!$contentType) {
            return new RedirectResponse('/admin/content');
        }
        
        // Handle POST - save the content
        if ($request->getMethod() === 'POST') {
            return $this->handleContentStore($request, $type, $contentType);
        }

        // Get fields for this content type
        $fieldsData = $contentType['fields'] ?? [];
        
        // Check for body format override
        $bodyFormat = $request->getQueryParams()['body_format'] ?? null;
        
        // If we have a body format field, we need to handle the body widget
        $hasBodyFormat = false;
        foreach ($fieldsData as $fieldData) {
            if (($fieldData['machine_name'] ?? '') === 'body_format') {
                $hasBodyFormat = true;
                if (!$bodyFormat) {
                    $bodyFormat = $fieldData['default_value'] ?? 'html';
                }
                break;
            }
        }

        // Render all fields
        $renderedFields = [];
        foreach ($fieldsData as $fieldData) {
            $fieldDef = FieldDefinition::fromArray($fieldData);
            
            // Dynamic body widget based on format
            if ($fieldDef->machine_name === 'body' && $hasBodyFormat && $bodyFormat) {
                switch ($bodyFormat) {
                    case 'markdown':
                        // TODO: Implement markdown widget, fallback to textarea for now
                        $fieldDef->widget = 'textarea'; 
                        break;
                    case 'plain':
                        $fieldDef->widget = 'textarea';
                        break;
                    case 'html':
                    default:
                        $fieldDef->widget = 'wysiwyg';
                        break;
                }
            }

            // Set value for body_format if we are forcing it
            $value = $fieldDef->default_value;
            if ($fieldDef->machine_name === 'body_format' && $bodyFormat) {
                $value = $bodyFormat;
            }
            
            $result = $this->widgetRegistry->renderField($fieldDef, $value, RenderContext::create());
            $this->assets->mergeCollection($result->getAssets());
            
            $renderedFields[$fieldDef->machine_name] = [
                'label' => $fieldDef->name,
                'machine_name' => $fieldDef->machine_name,
                'html' => $result->getHtml(),
                'weight' => $fieldDef->weight
            ];
        }
        
        // Sort by weight
        uasort($renderedFields, fn($a, $b) => $a['weight'] <=> $b['weight']);

        // Fetch users for author dropdown
        $users = [];
        try {
            $pdo = $this->connection->pdo();
            $stmt = $pdo->query("SELECT id, display_name, email FROM users WHERE status = 'active' ORDER BY display_name, email");
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Silent fail
        }

        // Get current user ID and name
        $cmsAuth = \App\Cms\Auth\AuthServiceProvider::getCmsAuthService();
        $currentUser = $cmsAuth->user();
        $currentUserId = $currentUser?->id ?? null;
        $currentUserName = $currentUser ? ($currentUser->display_name ?? $currentUser->email ?? 'Unknown') : '';

        return $this->render('admin/content/create', [
            'type' => $contentType,
            'type_id' => $type,
            'renderedFields' => $renderedFields,
            'title' => 'Create ' . $contentType['label'],
            'action' => '/admin/content/' . $type . '/add',
            'cancel_url' => '/admin/content',
            'users' => $users,
            'current_user_id' => $currentUserId,
            'current_user_name' => $currentUserName,
        ]);

    }

    /**
     * Handle storing new content (called from contentCreate for POST)
     */
    private function handleContentStore(ServerRequestInterface $request, string $type, array $contentType): ResponseInterface
    {
        // Debug logging to app log file
        $logFile = (defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd()) . '/var/logs/app-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] DEBUG: handleContentStore called for type: {$type}\n", FILE_APPEND);

        $data = (array) $request->getParsedBody();
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] DEBUG: Received data keys: " . json_encode(array_keys($data)) . "\n", FILE_APPEND);
        $entity = $contentType['entity'];
        
        try {
            // Get field definitions
            $fieldsData = $contentType['fields'] ?? [];
            
            // Build column data
            $columns = ['uuid', 'title', 'slug', 'created_at', 'updated_at'];
            $placeholders = [':uuid', ':title', ':slug', ':created_at', ':updated_at'];
            $values = [
                'uuid' => sprintf('%s-%s-%s-%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6))),
                'title' => $data['title'] ?? '',
                'slug' => !empty($data['slug']) ? $data['slug'] : $this->slugify($data['title'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            // Add publishable fields if applicable
            if ($contentType['publishable'] ?? false) {
                $columns[] = 'status';
                $placeholders[] = ':status';
                $values['status'] = $data['status'] ?? 'draft';
                
                if (!empty($data['published_at'])) {
                    $columns[] = 'published_at';
                    $placeholders[] = ':published_at';
                    $values['published_at'] = $data['published_at'];
                }
            }

            // Add author if applicable - read from form or use current user
            if ($contentType['has_author'] ?? true) {
                $columns[] = 'author_id';
                $placeholders[] = ':author_id';
                // Use form value if provided, otherwise use current logged-in user
                $authorId = !empty($data['author_id']) ? (int)$data['author_id'] : null;
                if ($authorId === null) {
                    $cmsAuth = \App\Cms\Auth\AuthServiceProvider::getCmsAuthService();
                    $currentUser = $cmsAuth->user();
                    $authorId = $currentUser?->id ?? null;
                }
                $values['author_id'] = $authorId;
            }

            // Add custom field values
            foreach ($fieldsData as $fieldData) {
                $machineName = $fieldData['machine_name'] ?? '';
                if ($machineName && isset($data[$machineName])) {
                    $columns[] = $machineName;
                    $placeholders[] = ':' . $machineName;
                    $value = $data[$machineName];
                    
                    // Handle JSON fields
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    
                    $values[$machineName] = $value;
                }
            }

            // Insert into content table
            $tableName = $entity->getContentTableName();
            $pdo = $this->connection->pdo();
            
            // Ensure content table exists (auto-create if missing)
            $this->ensureContentTableExists($pdo, $type, $contentType, $columns);
            
            $sql = "INSERT INTO {$tableName} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);

            return new RedirectResponse('/admin/content/' . $type);
        } catch (\Exception $e) {
            // Log the error
            $logFile = (defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd()) . '/var/logs/app-' . date('Y-m-d') . '.log';
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR in handleContentStore: " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            
            // Re-render form with error
            $fieldsData = $contentType['fields'] ?? [];
            $renderedFields = [];
            foreach ($fieldsData as $fieldData) {
                $fieldDef = FieldDefinition::fromArray($fieldData);
                $value = $data[$fieldDef->machine_name] ?? $fieldDef->default_value;
                $result = $this->widgetRegistry->renderField($fieldDef, $value, RenderContext::create());
                $this->assets->mergeCollection($result->getAssets());
                $renderedFields[$fieldDef->machine_name] = [
                    'label' => $fieldDef->name,
                    'machine_name' => $fieldDef->machine_name,
                    'html' => $result->getHtml(),
                    'weight' => $fieldDef->weight
                ];
            }
            uasort($renderedFields, fn($a, $b) => $a['weight'] <=> $b['weight']);

            return $this->render('admin/content/create', [
                'type' => $contentType,
                'type_id' => $type,
                'renderedFields' => $renderedFields,
                'title' => 'Create ' . $contentType['label'],
                'action' => '/admin/content/' . $type . '/add',
                'cancel_url' => '/admin/content',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Edit content of a specific type (GET shows form, POST saves)
     */
    #[Route(['GET', 'POST'], '/admin/content/{type}/{id}/edit')]
    public function contentEdit(ServerRequestInterface $request, string $type, string $id): ResponseInterface
    {
        $contentType = $this->contentTypeManager->getType($type);
        if (!$contentType) {
            return new RedirectResponse('/admin/content');
        }
        
        $entity = $contentType['entity'];
        $tableName = $entity->getContentTableName();
        $pdo = $this->connection->pdo();
        
        // Fetch the content item
        try {
            $stmt = $pdo->prepare("SELECT * FROM {$tableName} WHERE id = ? OR slug = ?");
            $stmt->execute([$id, $id]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$item) {
                return new RedirectResponse('/admin/content/' . $type);
            }
        } catch (\Exception $e) {
            return new RedirectResponse('/admin/content/' . $type);
        }
        
        // Handle POST - update the content
        if ($request->getMethod() === 'POST') {
            return $this->handleContentUpdate($request, $type, $contentType, $item);
        }
        
        // GET - show edit form
        $fieldsData = $contentType['fields'] ?? [];
        $renderedFields = [];
        
        foreach ($fieldsData as $fieldData) {
            $fieldDef = FieldDefinition::fromArray($fieldData);
            // Get value from the item or use default
            $value = $item[$fieldDef->machine_name] ?? $fieldDef->default_value;
            
            $result = $this->widgetRegistry->renderField($fieldDef, $value, RenderContext::create());
            $this->assets->mergeCollection($result->getAssets());
            $renderedFields[$fieldDef->machine_name] = [
                'label' => $fieldDef->name,
                'machine_name' => $fieldDef->machine_name,
                'html' => $result->getHtml(),
                'weight' => $fieldDef->weight
            ];
        }
        
        // Sort by weight
        uasort($renderedFields, fn($a, $b) => $a['weight'] <=> $b['weight']);

        return $this->render('admin/content/edit', [
            'type' => $contentType,
            'type_id' => $type,
            'item' => $item,
            'renderedFields' => $renderedFields,
            'title' => 'Edit ' . ($item['title'] ?? $contentType['label']),
            'action' => '/admin/content/' . $type . '/' . $item['id'] . '/edit',
            'cancel_url' => '/admin/content/' . $type,
        ]);
    }
    
    /**
     * Get widget name for body format
     */
    private function getWidgetForBodyFormat(string $format): string
    {
        return match ($format) {
            'markdown' => 'markdown_editor',
            'plain' => 'textarea',
            default => 'wysiwyg',
        };
    }

    /**
     * Handle content update (called from contentEdit for POST)
     */
    private function handleContentUpdate(ServerRequestInterface $request, string $type, array $contentType, array $item): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $entity = $contentType['entity'];
        $tableName = $entity->getContentTableName();
        
        try {
            $fieldsData = $contentType['fields'] ?? [];
            
            // Build update query
            $updates = [
                'title = ?',
                'slug = ?',
                'updated_at = ?',
            ];
            $values = [
                $data['title'] ?? $item['title'],
                !empty($data['slug']) ? $this->slugify($data['slug']) : $item['slug'],
                date('Y-m-d H:i:s'),
            ];
            
            // Status and publishing
            if ($entity->publishable) {
                $updates[] = 'status = ?';
                $values[] = $data['status'] ?? 'draft';
                
                if (isset($data['published_at']) && !empty($data['published_at'])) {
                    $updates[] = 'published_at = ?';
                    $values[] = $data['published_at'];
                }
            }
            
            // Custom fields
            foreach ($fieldsData as $fieldData) {
                $fieldDef = FieldDefinition::fromArray($fieldData);
                $fieldName = $fieldDef->machine_name;
                
                if (isset($data[$fieldName])) {
                    $updates[] = "{$fieldName} = ?";
                    $fieldValue = $data[$fieldName];
                    if (is_array($fieldValue)) {
                        $fieldValue = json_encode($fieldValue);
                    }
                    $values[] = $fieldValue;
                }
            }
            
            // Add the ID for WHERE clause
            $values[] = $item['id'];
            
            $sql = "UPDATE {$tableName} SET " . implode(', ', $updates) . " WHERE id = ?";
            
            $pdo = $this->connection->pdo();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);

            return new RedirectResponse('/admin/content/' . $type);
        } catch (\Exception $e) {
            // Log the error
            $logFile = (defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd()) . '/var/logs/app-' . date('Y-m-d') . '.log';
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR in handleContentUpdate: " . $e->getMessage() . "\n", FILE_APPEND);
            
            // Re-render form with error
            $fieldsData = $contentType['fields'] ?? [];
            $renderedFields = [];
            foreach ($fieldsData as $fieldData) {
                $fieldDef = FieldDefinition::fromArray($fieldData);
                $value = $data[$fieldDef->machine_name] ?? $item[$fieldDef->machine_name] ?? $fieldDef->default_value;
                $result = $this->widgetRegistry->renderField($fieldDef, $value, RenderContext::create());
                $this->assets->mergeCollection($result->getAssets());
                $renderedFields[$fieldDef->machine_name] = [
                    'label' => $fieldDef->name,
                    'machine_name' => $fieldDef->machine_name,
                    'html' => $result->getHtml(),
                    'weight' => $fieldDef->weight
                ];
            }
            uasort($renderedFields, fn($a, $b) => $a['weight'] <=> $b['weight']);

            return $this->render('admin/content/edit', [
                'type' => $contentType,
                'type_id' => $type,
                'item' => $item,
                'renderedFields' => $renderedFields,
                'title' => 'Edit ' . ($item['title'] ?? $contentType['label']),
                'action' => '/admin/content/' . $type . '/' . $item['id'] . '/edit',
                'cancel_url' => '/admin/content/' . $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete content item - handles both GET (redirect) and POST (actual delete)
     */
    #[Route('GET', '/admin/content/{type}/{id}/delete')]
    #[Route('POST', '/admin/content/{type}/{id}/delete')]
    public function contentDelete(ServerRequestInterface $request, string $type, string $id): ResponseInterface
    {
        $contentType = $this->contentTypeManager->getType($type);
        if (!$contentType) {
            return new RedirectResponse('/admin/content');
        }

        // GET request - redirect to content list (delete should be done via POST from modal)
        if ($request->getMethod() === 'GET') {
            return new RedirectResponse('/admin/content');
        }

        $entity = $contentType['entity'];
        $tableName = $entity->getContentTableName();
        
        try {
            $pdo = $this->connection->pdo();
            
            // Verify the item exists
            $stmt = $pdo->prepare("SELECT id, title FROM {$tableName} WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$item) {
                return new RedirectResponse('/admin/content');
            }
            
            // Delete the item
            $stmt = $pdo->prepare("DELETE FROM {$tableName} WHERE id = ?");
            $stmt->execute([$id]);
            
            // Log the deletion
            $logFile = (defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd()) . '/var/logs/app-' . date('Y-m-d') . '.log';
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Content deleted: {$type}/{$id} - " . ($item['title'] ?? 'Untitled') . "\n", FILE_APPEND);
            
            return new RedirectResponse('/admin/content');
        } catch (\Exception $e) {
            // Log the error
            $logFile = (defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd()) . '/var/logs/app-' . date('Y-m-d') . '.log';
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR in contentDelete: " . $e->getMessage() . "\n", FILE_APPEND);
            
            return new RedirectResponse('/admin/content');
        }
    }

    /**
     * Slugify a string
     */
    private function slugify(string $text): string
    {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text) ?: strtolower($text);
        $text = strtolower($text);
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    /**
     * Create content type form
     */
    #[Route('GET', '/admin/structure/content-types/create')]
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        return $this->render('admin/structure/content_types/form', [
            'type' => null,
            'title' => 'Create Content Type',
            'action' => '/admin/structure/content-types',
            'method' => 'POST'
        ]);
    }

    /**
     * Store new content type
     */
    #[Route('POST', '/admin/structure/content-types')]
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        
        try {
            $this->contentTypeManager->createDatabaseType($data);
            return new RedirectResponse('/admin/structure/content-types');
        } catch (\Exception $e) {
            return $this->render('admin/structure/content_types/form', [
                'type' => $data,
                'error' => $e->getMessage(),
                'title' => 'Create Content Type',
                'action' => '/admin/structure/content-types',
                'method' => 'POST'
            ]);
        }
    }

    /**
     * Edit content type form
     */
    #[Route('GET', '/admin/structure/content-types/{id}/edit')]
    public function edit(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/content-types');
        }

        // Convert to array-like structure compatible with form
        $formData = [
            'label' => $type['label'],
            'type_id' => $type['id'],
            'label_plural' => $type['label_plural'] ?? $type['label'] . 's',
            'description' => $type['description'] ?? '',
            'icon' => $type['icon'] ?? '📄',
            'publishable' => $type['publishable'] ?? true,
            'revisionable' => $type['revisionable'] ?? false,
            'translatable' => $type['translatable'] ?? false,
            'has_author' => $type['has_author'] ?? true,
            'has_taxonomy' => $type['has_taxonomy'] ?? true,
            'has_media' => $type['has_media'] ?? true,
            'title_field' => $type['title_field'] ?? 'title',
            'slug_field' => $type['slug_field'] ?? 'slug',
            'url_pattern' => $type['url_pattern'] ?? null,
            'enabled' => $type['enabled'] ?? true,
            'composer_enabled' => $type['composer_enabled'] ?? false,
            'composer_default' => $type['composer_default'] ?? false,
        ];

        return $this->render('admin/structure/content_types/form', [
            'type' => $formData,
            'is_edit' => true,
            'title' => 'Edit Content Type',
            'action' => '/admin/structure/content-types/' . $id,
            'method' => 'POST'
        ]);
    }

    /**
     * Update content type
     */
    #[Route('POST', '/admin/structure/content-types/{id}')]
    public function update(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $type = $this->contentTypeManager->getType($id);
        
        if (!$type || $type['source'] !== 'database') {
            return new RedirectResponse('/admin/structure/content-types');
        }

        try {
            $this->contentTypeManager->updateDatabaseType($type['entity']->id, $data);
            return new RedirectResponse('/admin/structure/content-types');
        } catch (\Exception $e) {
            return $this->render('admin/structure/content_types/form', [
                'type' => $data,
                'is_edit' => true,
                'error' => $e->getMessage(),
                'title' => 'Edit Content Type',
                'action' => '/admin/structure/content-types/' . $id,
                'method' => 'POST'
            ]);
        }
    }
    
    /**
     * Delete content type
     */
    #[Route('POST', '/admin/structure/content-types/{id}/delete')]
    public function destroy(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if ($type && $type['source'] === 'database') {
            $dropTable = (bool) ($request->getParsedBody()['drop_table'] ?? false);
            $this->contentTypeManager->deleteDatabaseType($type['entity']->id, $dropTable);
        }
        
        return new RedirectResponse('/admin/structure/content-types');
    }

    /**
     * Manage fields for a content type
     */
    #[Route('GET', '/admin/structure/content-types/{id}/fields')]
    public function fields(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/content-types');
        }

        return $this->render('admin/structure/content_types/fields', [
            'type' => $type,
            'fields' => $type['fields'],
            'base_url' => '/admin/structure/content-types/' . $id,
            'add_url' => '/admin/structure/content-types/' . $id . '/fields/add',
        ]);
    }

    /**
     * Manage form display settings (field ordering)
     */
    #[Route('GET', '/admin/structure/content-types/{id}/form-display')]
    public function formDisplay(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/content-types');
        }

        // Get fields and inject system fields
        $fields = $type['fields'];
        $weights = $type['entity']->settings['form_weights'] ?? [];



        // Prepare fields for sorting
        foreach ($fields as $key => &$field) {
            $field['machine_name'] = $field['machine_name'] ?? $key;
            $field['weight'] = $weights[$field['machine_name']] ?? $field['weight'] ?? 0;
        }
        unset($field);

        // Sort by weight
        uasort($fields, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

        return $this->render('admin/structure/content_types/form_display', [
            'type' => $type,
            'fields' => $fields,
            'base_url' => '/admin/structure/content-types/' . $id,
        ]);
    }

    /**
     * Save form display settings
     */
    #[Route('POST', '/admin/structure/content-types/{id}/form-display')]
    public function saveFormDisplay(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        $params = $request->getParsedBody();

        if ($type && $type['source'] === 'database' && !empty($params['weights'])) {
            try {
                // Save weights in settings
                $entity = $type['entity'];
                $settings = $entity->settings;
                $settings['form_weights'] = $params['weights'];
                $this->contentTypeManager->updateDatabaseType($entity->id, ['settings' => $settings]);
                
                if ($this->isXhrRequest($request)) {
                    return new JsonResponse(['status' => 'success', 'message' => 'Weights saved']);
                }
            } catch (\Exception $e) {
                if ($this->isXhrRequest($request)) {
                    return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
                }
            }
        }
        
        return new RedirectResponse('/admin/structure/content-types/' . $id . '/form-display');
    }

    /**
     * Check if request is HTMX/XHR
     * Detects: HX-Request header (HTMX), X-Requested-With (XHR), or JSON Content-Type
     */
    private function isXhrRequest(ServerRequestInterface $request): bool
    {
        return $request->hasHeader('HX-Request')
            || strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest' 
            || str_contains($request->getHeaderLine('Content-Type'), 'application/json');
    }

    /**
     * Manage display settings
     */
    #[Route('GET', '/admin/structure/content-types/{id}/display')]
    public function display(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/content-types');
        }

        $fields = $type['fields'];
        $weights = $type['entity']->settings['display_weights'] ?? [];

        // Add system fields
        $fields['title'] = [
            'label' => 'Title',
            'widget' => 'string',
            'weight' => $weights['title'] ?? -10,
            'machine_name' => 'title'
        ];



        foreach ($fields as $key => &$field) {
            $field['machine_name'] = $field['machine_name'] ?? $key;
            $field['weight'] = $weights[$field['machine_name']] ?? $field['weight'] ?? 0;
        }
        unset($field);

        uasort($fields, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

        return $this->render('admin/structure/content_types/display', [
            'type' => $type,
            'fields' => $fields,
            'base_url' => '/admin/structure/content-types/' . $id,
        ]);
    }

    /**
     * Save display settings
     */
    #[Route('POST', '/admin/structure/content-types/{id}/display')]
    public function saveDisplay(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        $params = $request->getParsedBody();

        if ($type && $type['source'] === 'database' && !empty($params['weights'])) {
            try {
                $entity = $type['entity'];
                $settings = $entity->settings;
                $settings['display_weights'] = $params['weights'];
                $this->contentTypeManager->updateDatabaseType($entity->id, ['settings' => $settings]);
                
                if ($this->isXhrRequest($request)) {
                    return new JsonResponse(['status' => 'success', 'message' => 'Weights saved']);
                }
            } catch (\Exception $e) {
                if ($this->isXhrRequest($request)) {
                    return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
                }
            }
        }

        return new RedirectResponse('/admin/structure/content-types/' . $id . '/display');
    }
    
    /**
     * Add field form
     */
    #[Route('GET', '/admin/structure/content-types/{id}/fields/add')]
    public function addField(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/content-types');
        }

        return $this->render('admin/structure/content_types/add_field', [
            'type' => $type,
            'grouped_types' => FieldType::getGrouped(),
            'action' => '/admin/structure/content-types/' . $id . '/fields',
            'cancel_url' => '/admin/structure/content-types/' . $id . '/fields',
        ]);
    }

    /**
     * Store new field
     */
    #[Route('POST', '/admin/structure/content-types/{id}/fields')]
    public function storeField(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $type = $this->contentTypeManager->getType($id);

        if (!$type || $type['source'] !== 'database') {
            return new RedirectResponse('/admin/structure/content-types');
        }

        try {
            $this->contentTypeManager->addFieldToType($type['entity']->id, $data);
            return new RedirectResponse('/admin/structure/content-types/' . $id . '/fields');
        } catch (\Exception $e) {
            return $this->render('admin/structure/content_types/add_field', [
                'type' => $type,
                'grouped_types' => FieldType::getGrouped(),
                'error' => $e->getMessage(),
                'action' => '/admin/structure/content-types/' . $id . '/fields',
                'cancel_url' => '/admin/structure/content-types/' . $id . '/fields',
            ]);
        }
    }

    /**
     * Delete field
     */
    #[Route('POST', '/admin/structure/content-types/{id}/fields/{fieldName}/delete')]
    public function deleteField(ServerRequestInterface $request, string $id, string $fieldName): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        
        if ($type && $type['source'] === 'database') {
            $dropColumn = (bool) ($request->getParsedBody()['drop_column'] ?? false);
            $this->contentTypeManager->removeFieldFromType($type['entity']->id, $fieldName, $dropColumn);
        }
        
        return new RedirectResponse('/admin/structure/content-types/' . $id . '/fields');
    }

    /**
     * Edit field form
     */
    #[Route('GET', '/admin/structure/content-types/{id}/fields/{fieldName}/edit')]
    public function editField(ServerRequestInterface $request, string $id, string $fieldName): ResponseInterface
    {
        $type = $this->contentTypeManager->getType($id);
        if (!$type) {
            return new RedirectResponse('/admin/structure/content-types');
        }

        $fields = $type['fields'];
        if (!isset($fields[$fieldName])) {
            return new RedirectResponse('/admin/structure/content-types/' . $id . '/fields');
        }

        return $this->render('admin/structure/content_types/edit_field', [
            'type' => $type,
            'field' => $fields[$fieldName],
            'machine_name' => $fieldName,
            'grouped_types' => FieldType::getGrouped(),
            'action' => '/admin/structure/content-types/' . $id . '/fields/' . $fieldName . '/update',
            'cancel_url' => '/admin/structure/content-types/' . $id . '/fields',
        ]);
    }

    /**
     * Update field
     */
    #[Route('POST', '/admin/structure/content-types/{id}/fields/{fieldName}/update')]
    public function updateField(ServerRequestInterface $request, string $id, string $fieldName): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $type = $this->contentTypeManager->getType($id);

        if (!$type || $type['source'] !== 'database') {
            return new RedirectResponse('/admin/structure/content-types');
        }

        try {
            // Process settings JSON
            if (isset($data['settings_json'])) {
                $decoded = json_decode($data['settings_json'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data['settings'] = $decoded;
                } else {
                    throw new \InvalidArgumentException('Invalid JSON in Settings field: ' . json_last_error_msg());
                }
            }

            // ContentTypeManager doesn't have updateFieldOnType, we need to add/re-add field
            // For now, remove and add field with new data
            $this->contentTypeManager->removeFieldFromType($type['entity']->id, $fieldName, false);
            $data['machine_name'] = $fieldName;
            $this->contentTypeManager->addFieldToType($type['entity']->id, $data);
            return new RedirectResponse('/admin/structure/content-types/' . $id . '/fields');
        } catch (\Exception $e) {
            $fields = $type['fields'];
            return $this->render('admin/structure/content_types/edit_field', [
                'type' => $type,
                'field' => $fields[$fieldName] ?? [],
                'machine_name' => $fieldName,
                'grouped_types' => FieldType::getGrouped(),
                'error' => $e->getMessage(),
                'action' => '/admin/structure/content-types/' . $id . '/fields/' . $fieldName . '/update',
                'cancel_url' => '/admin/structure/content-types/' . $id . '/fields',
            ]);
        }
    }

    /**
     * Ensure content table exists, auto-create if missing
     */
    private function ensureContentTableExists(\PDO $pdo, string $type, array $contentType, array $columns): void
    {
        $entity = $contentType['entity'];
        $tableName = $entity->getContentTableName();
        
        // Check if table exists
        try {
            $pdo->query("SELECT 1 FROM {$tableName} LIMIT 1");
            return; // Table exists
        } catch (\PDOException $e) {
            // Table doesn't exist, create it
        }
        
        // Build CREATE TABLE SQL dynamically based on content type settings
        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (\n";
        $sql .= "    id INT AUTO_INCREMENT PRIMARY KEY,\n";
        $sql .= "    uuid VARCHAR(36) NOT NULL UNIQUE,\n";
        $sql .= "    title VARCHAR(255) NOT NULL,\n";
        $sql .= "    slug VARCHAR(255) NOT NULL UNIQUE,\n";

        // Add fields from content type
        foreach ($contentType['fields'] ?? [] as $field) {
            $machineName = $field['machine_name'] ?? '';
            $fieldType = $field['field_type'] ?? 'string';
            
            if (!$machineName) continue;
            
            $sqlType = match($fieldType) {
                'html', 'markdown', 'text', 'textarea' => 'LONGTEXT',
                'integer', 'int' => 'INT',
                'decimal', 'float' => 'DECIMAL(10,2)',
                'boolean', 'bool' => 'TINYINT(1) DEFAULT 0',
                'date' => 'DATE',
                'datetime' => 'DATETIME',
                'json', 'array' => 'JSON',
                default => 'VARCHAR(255)',
            };
            
            $sql .= "    {$machineName} {$sqlType},\n";
        }

        // Add standard fields
        if ($contentType['publishable'] ?? true) {
            $sql .= "    status VARCHAR(20) DEFAULT 'draft',\n";
            $sql .= "    published_at DATETIME,\n";
        }

        if ($contentType['has_author'] ?? true) {
            $sql .= "    author_id INT,\n";
        }

        $sql .= "    created_at DATETIME NOT NULL,\n";
        $sql .= "    updated_at DATETIME NOT NULL,\n";

        // Indexes
        $sql .= "    INDEX idx_status (status),\n";
        $sql .= "    INDEX idx_created (created_at)";

        if ($contentType['has_author'] ?? true) {
            $sql .= ",\n    INDEX idx_author (author_id)";
        }

        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $pdo->exec($sql);
        
        // Log table creation
        $logFile = (defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd()) . '/var/logs/app-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] INFO: Auto-created content table: {$tableName}\n", FILE_APPEND);
    }
}
