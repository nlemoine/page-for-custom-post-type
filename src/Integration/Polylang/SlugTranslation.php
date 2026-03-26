<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\Polylang;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;
use PLL_Language;

/**
 * Polylang Slug Translation integration.
 *
 * Translates post type slugs to match the translated PFCPT page slugs.
 */
final class SlugTranslation
{
    public function __construct(
        private readonly Api $api,
        private readonly RewriteManager $rewriteManager
    ) {
    }

    public function registerHooks(): void
    {
        \add_filter('pll_translated_slugs', [$this, 'translateSlugs'], 10, 2);
    }

    /**
     * Translate slugs for Polylang.
     *
     * @param array<string, array<string, mixed>> $slugs
     * @return array<string, array<string, mixed>>
     */
    //phpcs:ignore SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
    public function translateSlugs(array $slugs, PLL_Language $language): array
    {
        $defaultLanguage = \pll_default_language();

        if ($language->slug === $defaultLanguage) {
            return $slugs;
        }

        $pageIds = $this->api->getPageIds(false);
        $postTypes = \array_keys($pageIds);

        foreach ($slugs as $postType => $postTypeSlugs) {
            if (!\in_array($postType, $postTypes, true)) {
                continue;
            }

            if (!isset($postTypeSlugs['translations'])) {
                continue;
            }

            $translations = $postTypeSlugs['translations'];

            if (!\is_array($translations)) {
                continue;
            }

            foreach (\array_keys($translations) as $lang) {
                if (!\is_string($lang)) {
                    continue;
                }

                if ($lang === $defaultLanguage) {
                    continue;
                }

                $pageId = \pll_get_post($pageIds[$postType], $lang);

                if (empty($pageId)) {
                    continue;
                }

                $pageSlug = $this->rewriteManager->getPageSlug($pageId);

                if (!$pageSlug) {
                    continue;
                }

                if (\is_array($slugs[$postType]['translations'] ?? null)) {
                    $slugs[$postType]['translations'][$lang] = \substr($pageSlug, \strlen($lang . '/'));
                }
            }
        }

        return $slugs;
    }
}
