<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\PostType;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;
use WP_Post_Type;

/**
 * Modifies post type registration arguments for PFCPT.
 */
final class PostType
{
    /**
     * Store original post type args before modification.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $originalArgs = [];

    public function __construct(
        private readonly Api $api,
        private readonly RewriteManager $rewriteManager
    ) {
    }

    /**
     * Modify post type args before registration.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function updatePostTypeArgs(array $args, string $postType): array
    {
        // Don't modify built-in or non-public post types
        if ($this->shouldSkipPostType($args)) {
            return $args;
        }

        // Store original args only once (first call) for later reference (e.g., in admin dropdown)
        if (!isset($this->originalArgs[$postType])) {
            $this->originalArgs[$postType] = $args;
        }

        // Check if this post type has a page assigned (without language filters)
        $pageId = $this->api->getPageIdFromPostType($postType, false);

        if (!$pageId) {
            return $args;
        }

        // Only modify rewrite slug if the option is enabled
        if ($this->api->shouldUsePageSlug($postType)) {
            // Get the page slug (cached)
            $pageSlug = $this->rewriteManager->getCachedPageSlug($postType);

            if (\is_string($pageSlug)) {
                // Set page slug as rewrite slug
                $rewrite = $args['rewrite'] ?? [];
                if (!\is_array($rewrite)) {
                    $rewrite = [];
                }
                $rewrite['slug'] = $pageSlug;
                $args['rewrite'] = $rewrite;
            }
        }

        // Disable native archive (we use the page instead)
        $args['has_archive'] = false;

        return $args;
    }

    /**
     * Enable pagination rules for post type.
     */
    public function addPaginationRewriteTags(string $postType, WP_Post_Type $postTypeObject): void
    {
        if (!$this->api->shouldConsiderPostType($postTypeObject)) {
            return;
        }

        if (!$this->api->getPageIdFromPostType($postType, false)) {
            return;
        }

        $this->rewriteManager->addRewriteTags($postTypeObject);
    }

    /**
     * Get original args for a post type.
     *
     * @return array<string, mixed>|null
     */
    public function getOriginalArgs(string $postType): ?array
    {
        return $this->originalArgs[$postType] ?? null;
    }

    /**
     * Check if post type should be skipped.
     *
     * @param array<string, mixed> $args
     */
    private function shouldSkipPostType(array $args): bool
    {
        // Skip built-in post types
        if (!empty($args['_builtin'])) {
            return true;
        }

        // Skip non-public post types
        if (isset($args['public']) && !$args['public']) {
            return true;
        }

        // Skip non-publicly-queryable post types
        if (isset($args['publicly_queryable']) && !$args['publicly_queryable']) {
            return true;
        }

        return false;
    }
}
