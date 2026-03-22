<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Polylang;

use n5s\PageForCustomPostType\Core\Api;

/**
 * Polylang Page Translation integration.
 *
 * Handles translation of PFCPT page IDs between languages,
 * with caching for performance.
 */
final class Translation
{
    public function registerHooks(): void
    {
        \add_filter('pfcpt/page_ids', [$this, 'filterTranslatedPageIds']);
        \add_filter('pfcpt/post_type_from_id/page_id', [$this, 'resolveDefaultLanguagePageId']);
    }

    /**
     * Filter page IDs to return translated versions.
     *
     * @param array<string, int> $pageIds
     * @return array<string, int>
     */
    public function filterTranslatedPageIds(array $pageIds): array
    {
        $currentLanguage = \pll_current_language();

        if (empty($currentLanguage)) {
            return $pageIds;
        }

        $cacheKey = $this->getCacheKey($currentLanguage);
        $pageIdsForCurrentLanguage = \get_transient($cacheKey);

        if (\is_array($pageIdsForCurrentLanguage)) {
            $result = [];

            foreach ($pageIdsForCurrentLanguage as $key => $value) {
                if (\is_string($key) && \is_int($value)) {
                    $result[$key] = $value;
                }
            }

            return $result;
        }

        $pageIdsForCurrentLanguage = \array_filter(
            \array_map(
                static function (int $id) use ($currentLanguage): ?int {
                    $postId = \pll_get_post($id, $currentLanguage);
                    return $postId > 0 ? $postId : null;
                },
                $pageIds
            )
        );

        \set_transient($cacheKey, $pageIdsForCurrentLanguage);

        return $pageIdsForCurrentLanguage;
    }

    /**
     * Resolve page ID to default language version when no current language is set.
     */
    public function resolveDefaultLanguagePageId(int $pageId): int
    {
        if (!empty(\pll_current_language())) {
            return $pageId;
        }

        $defaultLanguage = \pll_default_language();

        if (!$defaultLanguage) {
            return $pageId;
        }

        $defaultPageId = \pll_get_post($pageId, $defaultLanguage);

        if (!$defaultPageId) {
            return $pageId;
        }

        return $defaultPageId;
    }

    /**
     * Get cache key for translated page IDs.
     */
    public function getCacheKey(string $languageSlug): string
    {
        return Api::OPTION_PAGE_IDS . '_' . $languageSlug;
    }
}
