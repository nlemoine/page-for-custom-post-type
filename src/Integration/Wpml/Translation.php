<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Wpml;

use n5s\PageForCustomPostType\Core\Api;

/**
 * WPML Page Translation integration.
 *
 * Handles translation of PFCPT page IDs between languages,
 * with caching for performance.
 */
final class Translation
{
    private const CACHE_TTL = HOUR_IN_SECONDS;

    public function registerHooks(): void
    {
        add_filter('pfcpt/page_ids', [$this, 'filterTranslatedPageIds']);
        add_filter('pfcpt/post_type_from_id/page_id', [$this, 'resolveDefaultLanguagePageId']);
    }

    /**
     * Filter page IDs to return translated versions.
     *
     * @param array<string, int> $pageIds
     * @return array<string, int>
     */
    public function filterTranslatedPageIds(array $pageIds): array
    {
        if (empty($pageIds)) {
            return $pageIds;
        }

        $currentLanguage = $this->getCurrentLanguage();
        $defaultLanguage = $this->getDefaultLanguage();

        // No language context or same as default - return original
        if ($currentLanguage === null || $currentLanguage === $defaultLanguage) {
            return $pageIds;
        }

        // Check cache
        $cacheKey = $this->getCacheKey($currentLanguage);
        $cached = get_transient($cacheKey);

        if (is_array($cached) && !empty($cached)) {
            return $this->validatePageIds($cached);
        }

        // Build translated page IDs
        $translatedPageIds = $this->translatePageIds($pageIds, $currentLanguage);

        // Cache result
        if (!empty($translatedPageIds)) {
            set_transient($cacheKey, $translatedPageIds, self::CACHE_TTL);
        }

        return $translatedPageIds;
    }

    /**
     * Resolve page ID to default language version when no current language is set.
     */
    public function resolveDefaultLanguagePageId(int $pageId): int
    {
        $currentLanguage = $this->getCurrentLanguage();

        // Language is set, no need to resolve
        if ($currentLanguage !== null) {
            return $pageId;
        }

        $defaultLanguage = $this->getDefaultLanguage();
        if ($defaultLanguage === null) {
            return $pageId;
        }

        /** @var int|null $defaultPageId */
        $defaultPageId = apply_filters('wpml_object_id', $pageId, 'page', true, $defaultLanguage);

        return (is_int($defaultPageId) && $defaultPageId > 0) ? $defaultPageId : $pageId;
    }

    /**
     * Get cache key for translated page IDs.
     */
    public function getCacheKey(string $languageSlug): string
    {
        return Api::OPTION_PAGE_IDS . '_' . $languageSlug;
    }

    /**
     * Get the current WPML language.
     */
    private function getCurrentLanguage(): ?string
    {
        /** @var string|null $lang */
        $lang = apply_filters('wpml_current_language', null);

        return is_string($lang) && $lang !== '' ? $lang : null;
    }

    /**
     * Get the default WPML language.
     */
    private function getDefaultLanguage(): ?string
    {
        /** @var string|null $lang */
        $lang = apply_filters('wpml_default_language', null);

        return is_string($lang) && $lang !== '' ? $lang : null;
    }

    /**
     * Translate page IDs to the target language.
     *
     * @param array<string, int> $pageIds
     * @return array<string, int>
     */
    private function translatePageIds(array $pageIds, string $targetLanguage): array
    {
        $translated = [];

        foreach ($pageIds as $postType => $id) {
            /** @var int|null $translatedId */
            $translatedId = apply_filters('wpml_object_id', $id, 'page', false, $targetLanguage);

            // Use translated ID if found, otherwise fall back to original
            $translated[$postType] = (is_int($translatedId) && $translatedId > 0) ? $translatedId : $id;
        }

        return $translated;
    }

    /**
     * Validate cached page IDs array structure.
     *
     * @param array<mixed, mixed> $cached
     * @return array<string, int>
     */
    private function validatePageIds(array $cached): array
    {
        $result = [];

        foreach ($cached as $key => $value) {
            if (is_string($key) && is_int($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
