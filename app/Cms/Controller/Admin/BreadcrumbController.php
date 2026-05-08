<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Breadcrumb\BreadcrumbConfig;
use App\Cms\Breadcrumb\BreadcrumbRepository;
use App\Cms\Content\ContentTypeManager;
use App\Cms\Taxonomy\TaxonomyRepository;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * BreadcrumbController — Admin UI for breadcrumb configuration.
 *
 * Provides a dashboard to enable/disable breadcrumbs per content type,
 * vocabulary, and globally, with configurable options like Home link,
 * separator, and JSON-LD output.
 */
final class BreadcrumbController
{
    public function __construct(
        private readonly Renderer              $renderer,
        private readonly BreadcrumbRepository   $breadcrumbRepo,
        private readonly ContentTypeManager     $typeManager,
        private readonly TaxonomyRepository     $taxonomyRepo,
        private readonly SessionManager         $session,
    ) {}

    /**
     * GET /admin/breadcrumbs — Configuration dashboard.
     */
    #[Route('GET', '/admin/breadcrumbs', name: 'admin.breadcrumbs.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $contentTypes  = $this->typeManager->getEnabled();
        $vocabularies  = $this->taxonomyRepo->findAllVocabularies();
        $allConfigs    = $this->breadcrumbRepo->findAll();

        // Index configs by entity_type:bundle for easy template lookup
        $configMap = [];
        foreach ($allConfigs as $cfg) {
            $configMap[$cfg->entity_type . ':' . $cfg->bundle] = $cfg;
        }

        // Global defaults
        $global = $configMap['global:*'] ?? new BreadcrumbConfig();

        // Separator options
        $separators = ['›', '/', '→', '»', '·', '|', '-'];

        return Response::html($this->renderer->render('breadcrumb.index', [
            'contentTypes' => $contentTypes,
            'vocabularies' => $vocabularies,
            'configMap'    => $configMap,
            'global'       => $global,
            'separators'   => $separators,
            'flashSuccess' => $this->session->getFlash('bc_success'),
            'flashError'   => $this->session->getFlash('bc_error'),
        ]));
    }

    /**
     * POST /admin/breadcrumbs/save — Save a specific config.
     */
    #[Route('POST', '/admin/breadcrumbs/save', name: 'admin.breadcrumbs.save')]
    public function save(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);

        $entityType = $body['entity_type'] ?? 'global';
        $bundle     = $body['bundle'] ?? '*';

        $config = new BreadcrumbConfig();
        $config->entity_type       = $entityType;
        $config->bundle            = $bundle;
        $config->enabled           = !empty($body['enabled']);
        $config->show_home         = !empty($body['show_home']);
        $config->show_current      = !empty($body['show_current']);
        $config->show_content_type = !empty($body['show_content_type']);
        $config->show_taxonomy     = !empty($body['show_taxonomy']);
        $config->separator         = $body['separator'] ?? '›';
        $config->json_ld           = !empty($body['json_ld']);

        $this->breadcrumbRepo->save($config);

        $label = match ($entityType) {
            'global'  => 'Global',
            'node'    => ucfirst($bundle),
            'term'    => ucfirst($bundle),
            'listing' => ucfirst($bundle) . ' listing',
            default   => $entityType,
        };

        $this->session->flash('bc_success', "{$label} breadcrumb settings saved.");
        return Response::redirect('/admin/breadcrumbs');
    }

    /**
     * POST /admin/breadcrumbs/{id}/delete — Reset a config to defaults.
     */
    #[Route('POST', '/admin/breadcrumbs/{id:\\d+}/delete', name: 'admin.breadcrumbs.delete')]
    public function delete(ServerRequestInterface $request, string $id): Response
    {
        $this->breadcrumbRepo->delete((int) $id);
        $this->session->flash('bc_success', 'Configuration reset to global defaults.');
        return Response::redirect('/admin/breadcrumbs');
    }

    // ── Private ─────────────────────────────────────────────────────────

    private function parseBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody() ?? [];
        if (empty($body)) {
            $stream = $request->getBody();
            $stream->rewind();
            $raw = $stream->getContents();
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }
        return $body;
    }
}
