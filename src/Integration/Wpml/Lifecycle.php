<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Wpml;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;

/**
 * WPML Lifecycle integration.
 *
 * Handles cache invalidation and rewrite rule flushing
 * when PFCPT pages or their translations change.
 */
final class Lifecycle
{
    public function __construct(
        private readonly Api $api,
        private readonly RewriteManager $rewriteManager,
        private readonly Translation $translation
    ) {
    }

    public function registerHooks(): void
    {
        \add_action('pfcpt/flush_rewrite_rules', [$this, 'flushCache']);
        \add_action('wpml_pro_translation_completed', [$this, 'onTranslationCompleted']);
        \add_action('icl_make_duplicate', [$this, 'onMakeDuplicate'], 10, 4);
    }

    /**
     * Flush WPML-related caches for all languages.
     */
    public function flushCache(): void
    {
        /** @var array<string, array<string, mixed>>|null $activeLanguages */
        $activeLanguages = \apply_filters('wpml_active_languages', null);

        if (!\is_array($activeLanguages)) {
            return;
        }

        foreach (\array_keys($activeLanguages) as $languageCode) {
            if (\is_string($languageCode)) {
                \delete_transient($this->translation->getCacheKey($languageCode));
            }
        }
    }

    /**
     * Handle WPML translation completion.
     */
    public function onTranslationCompleted(int $newPostId): void
    {
        $this->flushIfPfcptPage($newPostId);
    }

    /**
     * Handle WPML duplicate creation.
     */
    public function onMakeDuplicate(int $masterPostId, string $lang, mixed $postarr, int $duplicatePostId): void
    {
        $this->flushIfPfcptPage($masterPostId);
    }

    /**
     * Flush rewrite rules if the given post ID is a PFCPT page (or a translation of one).
     */
    private function flushIfPfcptPage(int $postId): void
    {
        /** @var string|null $defaultLanguage */
        $defaultLanguage = \apply_filters('wpml_default_language', null);

        if (!\is_string($defaultLanguage) || $defaultLanguage === '') {
            return;
        }

        /** @var int|null $defaultPageId */
        $defaultPageId = \apply_filters('wpml_object_id', $postId, 'page', true, $defaultLanguage);

        if (!\is_int($defaultPageId) || $defaultPageId <= 0) {
            return;
        }

        $pageIds = $this->api->getPageIds(false);

        if (!\in_array($defaultPageId, $pageIds, true)) {
            return;
        }

        $postType = \array_search($defaultPageId, $pageIds, true);

        if (!\is_string($postType)) {
            return;
        }

        $this->rewriteManager->flushRewriteRules($postType);
    }
}
