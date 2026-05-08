<?php

declare(strict_types=1);

namespace App\Cms\Controller\JsonApi;

use App\Cms\Api\JsonApiFormatter;
use App\Cms\Menu\MenuRepository;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MenuJsonApiController — Public JSON:API for menus.
 */
#[RoutePrefix('/api/v1/menus')]
final class MenuJsonApiController
{
    private readonly JsonApiFormatter $jsonApi;

    public function __construct(
        private readonly MenuRepository $menuRepo,
    ) {
        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/api/v1';
        $this->jsonApi = new JsonApiFormatter($baseUrl);
    }

    #[Route('GET', '/', name: 'api.v1.menus.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $menus = $this->menuRepo->findAll();

        $data = array_map(fn($m) => [
            'id' => $m->id,
            'attributes' => $m->toArray(),
        ], $menus);

        return Response::json($this->jsonApi->collection('menus', $data));
    }

    #[Route('GET', '/{name:[a-z0-9_-]+}', name: 'api.v1.menus.show')]
    public function show(ServerRequestInterface $request, string $name): Response
    {
        $menu = $this->menuRepo->findByName($name);

        if (!$menu) {
            return Response::json($this->jsonApi->error(404, 'Not Found', "Menu '{$name}' does not exist."), 404);
        }

        $attributes = $menu->toArray();

        // Include nested items as included resources
        $included = [];
        $this->flattenItems($menu->items, $included);

        $relationships = [];
        if ($menu->items) {
            $relationships['items'] = array_map(fn($i) => ['type' => 'menu_items', 'id' => $i->id], $menu->items);
        }

        $response = $this->jsonApi->resource('menus', $menu->id, $attributes, $relationships);

        if ($included) {
            $response['included'] = $included;
        }

        return Response::json($response);
    }

    private function flattenItems(array $items, array &$included): void
    {
        foreach ($items as $item) {
            $data = $item->toArray();
            $children = $data['children'] ?? [];
            unset($data['children']);

            $included[] = [
                'type' => 'menu_items',
                'id' => (string) $item->id,
                'attributes' => $data,
            ];

            if ($item->children) {
                $this->flattenItems($item->children, $included);
            }
        }
    }
}
