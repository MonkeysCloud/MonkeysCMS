<?php

declare(strict_types=1);

namespace App\Cms\Composer\Layout;

/**
 * LayoutRegistry - Collects layouts from all providers
 * 
 * This registry aggregates row layouts from core and modules,
 * making all layouts available to the composer editor.
 */
class LayoutRegistry
{
    /** @var LayoutProviderInterface[] */
    private array $providers = [];

    /** @var array|null Cached layouts */
    private ?array $cachedLayouts = null;

    /**
     * Register a layout provider
     */
    public function registerProvider(LayoutProviderInterface $provider): void
    {
        $this->providers[$provider->getProviderId()] = $provider;
        $this->cachedLayouts = null; // Invalidate cache
    }

    /**
     * Get all registered layouts
     * 
     * @return array<array{id: string, label: string, widths: array, columns: int, provider: string}>
     */
    public function getLayouts(): array
    {
        if ($this->cachedLayouts !== null) {
            return $this->cachedLayouts;
        }

        $layouts = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->getLayouts() as $layout) {
                $layout['provider'] = $provider->getProviderId();
                $layout['columns'] = count($layout['widths'] ?? []);
                $layouts[] = $layout;
            }
        }

        $this->cachedLayouts = $layouts;
        return $layouts;
    }

    /**
     * Get a specific layout by ID
     */
    public function getLayout(string $id): ?array
    {
        foreach ($this->getLayouts() as $layout) {
            if ($layout['id'] === $id) {
                return $layout;
            }
        }
        return null;
    }

    /**
     * Check if a layout exists
     */
    public function hasLayout(string $id): bool
    {
        return $this->getLayout($id) !== null;
    }

    /**
     * Get all provider IDs
     */
    public function getProviderIds(): array
    {
        return array_keys($this->providers);
    }
}
