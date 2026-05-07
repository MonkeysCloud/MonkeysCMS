<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\I18n\LanguageService;
use App\Cms\I18n\TranslationService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * LanguageController — Admin dashboard for multilingual module.
 *
 * All mutating endpoints return JSON (CMS.fetch pattern).
 */
#[RoutePrefix('/admin/languages')]
final class LanguageController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly LanguageService $languages,
        private readonly TranslationService $translations,
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // Pages
    // ═══════════════════════════════════════════════════════════════════════

    #[Route('GET', '/', name: 'admin::languages.index')]
    public function index(): Response
    {
        $allLangs = $this->languages->getAll();
        $enabled = $this->languages->getEnabled();
        $isModuleEnabled = $this->languages->isEnabled();

        // Build coverage stats for each enabled non-default language
        $coverage = [];
        $defaultCode = $this->languages->getDefaultCode();
        foreach ($enabled as $lang) {
            if ($lang->code === $defaultCode) continue;
            $coverage[$lang->code] = [
                'label' => $lang->native,
                'flag'  => $lang->flagEmoji,
                'nodes' => $this->translations->getCoverage('node', $lang->code),
                'terms' => $this->translations->getCoverage('term', $lang->code),
            ];
        }

        return Response::html($this->renderer->render('languages.index', [
            'title'     => 'Languages',
            'languages' => $allLangs,
            'enabled'   => $enabled,
            'coverage'  => $coverage,
            'isModuleEnabled' => $isModuleEnabled,
            'defaultCode'     => $defaultCode,
        ]));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // JSON API
    // ═══════════════════════════════════════════════════════════════════════

    #[Route('POST', '/toggle-module', name: 'admin::languages.toggleModule')]
    public function toggleModule(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        $enabled = filter_var($body['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->languages->setEnabled($enabled);
        return Response::json(['success' => true, 'enabled' => $enabled]);
    }

    #[Route('POST', '/{code}/enable', name: 'admin::languages.enable')]
    public function enable(ServerRequestInterface $request, string $code): Response
    {
        try {
            $this->languages->enable($code);
            return Response::json(['success' => true]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('POST', '/{code}/disable', name: 'admin::languages.disable')]
    public function disable(ServerRequestInterface $request, string $code): Response
    {
        try {
            $this->languages->disable($code);
            return Response::json(['success' => true]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('POST', '/{code}/default', name: 'admin::languages.setDefault')]
    public function setDefault(ServerRequestInterface $request, string $code): Response
    {
        try {
            $this->languages->setDefault($code);
            return Response::json(['success' => true]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('POST', '/reorder', name: 'admin::languages.reorder')]
    public function reorder(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        $weights = $body['weights'] ?? [];
        if (!is_array($weights)) {
            return Response::json(['success' => false, 'error' => 'Invalid weights'], 422);
        }
        $this->languages->reorder($weights);
        return Response::json(['success' => true]);
    }

    #[Route('GET', '/coverage', name: 'admin::languages.coverage')]
    public function coverage(): Response
    {
        $enabled = $this->languages->getEnabled();
        $defaultCode = $this->languages->getDefaultCode();
        $data = [];

        foreach ($enabled as $lang) {
            if ($lang->code === $defaultCode) continue;
            $data[$lang->code] = [
                'label' => $lang->native,
                'flag'  => $lang->flagEmoji,
                'nodes' => $this->translations->getCoverage('node', $lang->code),
                'terms' => $this->translations->getCoverage('term', $lang->code),
            ];
        }

        return Response::json(['success' => true, 'coverage' => $data]);
    }

    // ── Internals ────────────────────────────────────────────────────────

    private function parseBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody() ?? [];
        if (empty($body)) {
            $stream = $request->getBody();
            $stream->rewind();
            $decoded = json_decode($stream->getContents(), true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }
        return $body;
    }
}
