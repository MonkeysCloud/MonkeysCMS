<?php

declare(strict_types=1);

namespace App\Cms\Search;

use App\Cms\Content\ContentEntity;

/**
 * SearchIndexer — Hooks content lifecycle events to update the search index.
 *
 * Automatically re-indexes content when it's created, updated, or deleted.
 * Should be called from ContentRepository after persist/delete operations.
 */
final class SearchIndexer
{
    public function __construct(
        private readonly SearchManager $manager,
    ) {}

    /**
     * Index or update a content entity.
     */
    public function onContentSaved(ContentEntity $entity): void
    {
        if ($entity->id === null) {
            return;
        }

        $doc = [
            'id' => $entity->id,
            'title' => $entity->title,
            'body' => strip_tags($entity->body ?? ''),
            'summary' => $entity->summary ?? '',
            'slug' => $entity->slug,
            'content_type' => $entity->content_type,
            'status' => $entity->status,
            'language' => $entity->language ?? 'en',
            'author_id' => $entity->author_id,
            'published_at' => $entity->published_at?->format('c'),
            'created_at' => $entity->created_at?->format('c'),
            'updated_at' => $entity->updated_at?->format('c'),
        ];

        try {
            $this->manager->indexContent($doc);
        } catch (\Throwable) {
            // Silently ignore index failures — search is non-critical
        }
    }

    /**
     * Remove a content entity from the index.
     */
    public function onContentDeleted(int $id): void
    {
        try {
            $this->manager->removeContent($id);
        } catch (\Throwable) {
            // Silently ignore
        }
    }

    /**
     * Re-index content when status changes (e.g. draft → published).
     */
    public function onStatusChanged(ContentEntity $entity): void
    {
        $this->onContentSaved($entity);
    }
}
