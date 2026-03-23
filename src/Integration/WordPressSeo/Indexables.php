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
        add_action('wp', [$this, 'configurePageDetection']);
    }

    /**
     * Configure Yoast to correctly detect PFCPT pages.
     */
    public function configurePageDetection(): void
    {
        if (!$this->api->isQueryPageForCustomPostType()) {
            return;
        }

        add_filter('wpseo_frontend_page_type_simple_page_id', static fn (): int => get_queried_object_id());

        /**
         * Trick Yoast SEO for_current_page logic which determines the current indexable.
         *
         * Yoast's get_simple_page_id() checks is_posts_page() before reaching the
         * wpseo_frontend_page_type_simple_page_id filter. Since Handler sets is_home
         * and is_posts_page to true, Yoast returns page_for_posts (the blog page ID)
         * instead of our PFCPT page ID. Filtering page_for_posts directly would fix
         * Yoast but break nav menu highlighting and other consumers.
         *
         * The only targeted fix is intercepting show_on_front specifically when called
         * from Yoast's Current_Page_Helper, so is_posts_page() returns false and
         * execution falls through to our filter.
         *
         * @see \Yoast\WP\SEO\Helpers\Current_Page_Helper::is_posts_page
         * @see \Yoast\WP\SEO\Helpers\Current_Page_Helper::get_simple_page_id
         * @see \Yoast\WP\SEO\Repositories\Indexable_Repository::for_current_page
         */
        if (get_option('show_on_front') === 'page') {
            add_filter('pre_option_show_on_front', static function (mixed $value): mixed {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Scoped to Yoast's helper; no alternative without broader side effects.
                $bt = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 4);

                if (
                    isset($bt[3]['file'])
                    && str_ends_with($bt[3]['file'], 'wordpress-seo/src/helpers/current-page-helper.php')
                ) {
                    return null;
                }

                return $value;
            });
        }
    }
}
