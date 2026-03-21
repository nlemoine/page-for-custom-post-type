<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Integration\WordPressSeo;

use n5s\PageForCustomPostType\Core\Api;

/**
 * Yoast SEO Indexables integration.
 *
 * Handles page type detection for Yoast's indexable system,
 * ensuring PFCPT pages are correctly identified.
 *
 * @see https://github.com/Yoast/wordpress-seo/pull/18222
 */
final class Indexables
{
    public function __construct(
        private readonly Api $api
    ) {
    }

    public function registerHooks(): void
    {
        \add_action('wp', [$this, 'configurePageDetection']);
    }

    /**
     * Configure Yoast to correctly detect PFCPT pages.
     */
    public function configurePageDetection(): void
    {
        if (!$this->api->isQueryPageForCustomPostType()) {
            return;
        }

        \add_filter('wpseo_frontend_page_type_simple_page_id', static fn (): int => \get_queried_object_id());

        /**
         * Trick Yoast SEO for_current_page logic which determines the current indexable.
         *
         * @see \Yoast\WP\SEO\Repositories\Indexable_Repository::for_current_page
         */
        if (\get_option('show_on_front') === 'page') {
            \add_filter('pre_option_show_on_front', static function (mixed $value): mixed {
                $bt = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);

                if (
                    isset($bt[3]['file'])
                    && \str_ends_with($bt[3]['file'], 'wordpress-seo/src/helpers/current-page-helper.php')
                ) {
                    return null;
                }

                return $value;
            });
        }
    }
}
