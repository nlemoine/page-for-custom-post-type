<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Polylang;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;
use WP_Post;

/**
 * Polylang Lifecycle integration.
 *
 * Handles cache invalidation and rewrite rule flushing
 * when PFCPT pages or their translations change.
 */
final class Lifecycle
{
    public function __construct(
        private readonly Api $api,
        private readonly RewriteManager $rewriteManager
    ) {
    }

    public function registerHooks(): void
    {
        add_action('pfcpt/flush_rewrite_rules', [$this, 'flushCache']);
        add_action('pll_save_post', [$this, 'onPostSave'], 10, 3);
    }

    /**
     * Flush Polylang-related caches.
     */
    public function flushCache(): void
    {
        $languagesList = pll_languages_list();

        if (!empty($languagesList)) {
            foreach ($languagesList as $languageSlug) {
                $cacheKey = Api::OPTION_PAGE_IDS . '_' . $languageSlug;
                delete_transient($cacheKey);
            }
        }

        delete_transient('pll_translated_slugs');
    }

    /**
     * Handle post save to flush cache when translations change.
     *
     * @param array<string, int> $translations
     */
    public function onPostSave(int $_postId, WP_Post $_post, array $translations): void
    {
        $defaultLanguage = pll_default_language();

        if (!isset($translations[$defaultLanguage])) {
            return;
        }

        $defaultPageForPostTypeId = $translations[$defaultLanguage];
        $pageIds = $this->api->getPageIds(false);

        if (!\in_array($defaultPageForPostTypeId, $pageIds, true)) {
            return;
        }

        $postType = array_search($defaultPageForPostTypeId, $pageIds, true);

        if (!\is_string($postType)) {
            return;
        }

        $this->rewriteManager->flushRewriteRules($postType);
    }
}
