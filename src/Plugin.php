<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType;

use n5s\PageForCustomPostType\Admin\Admin;
use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;
use n5s\PageForCustomPostType\Frontend\Handler;
use n5s\PageForCustomPostType\Frontend\QueryFilter;
use n5s\PageForCustomPostType\Integration\Autodescription;
use n5s\PageForCustomPostType\Integration\IntegrationInterface;
use n5s\PageForCustomPostType\Integration\Polylang;
use n5s\PageForCustomPostType\Integration\WordPressSeo;
use n5s\PageForCustomPostType\Integration\Wpml;
use n5s\PageForCustomPostType\Lifecycle\LifecycleManager;
use n5s\PageForCustomPostType\PostType\PostType;

/**
 * Main plugin class - thin orchestrator that wires everything together.
 */
final class Plugin
{
    // Legacy constants for backward compatibility
    /** @deprecated Use Api::QUERY_VAR_IS_PFCPT instead */
    public const QUERY_VAR_IS_PFCPT = Api::QUERY_VAR_IS_PFCPT;

    /** @deprecated Use Api::OPTION_PREFIX instead */
    public const OPTION_PREFIX = Api::OPTION_PREFIX;

    /** @deprecated Use Api::OPTION_PAGE_IDS instead */
    public const OPTION_PAGE_IDS = Api::OPTION_PAGE_IDS;

    private static ?self $instance = null;

    private Container $container;

    private bool $initialized = false;

    private function __construct()
    {
        $this->container = new Container();
    }

    /**
     * Get singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize the plugin by registering hooks.
     *
     * This method is separate from the constructor to avoid side effects
     * during object instantiation and to make testing easier.
     */
    public function init(): self
    {
        if ($this->initialized) {
            return $this;
        }

        $this->registerHooks();
        $this->initialized = true;

        return $this;
    }

    /**
     * Get the container.
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the API service.
     */
    public function getApi(): Api
    {
        return $this->container->get(Api::class);
    }

    /**
     * Register all WordPress hooks.
     */
    private function registerHooks(): void
    {
        $this->registerAdminHooks();
        $this->registerFrontendHooks();
        $this->registerCommonHooks();
    }

    /**
     * Register admin-only hooks.
     */
    private function registerAdminHooks(): void
    {
        if (!is_admin()) {
            return;
        }

        $admin = $this->container->get(Admin::class);

        add_action('admin_menu', [$admin, 'addPostTypeSubmenus']);
        add_action('admin_init', [$admin, 'addReadingSettings']);
        add_action('admin_bar_menu', [$admin, 'addAdminBarArchiveLink'], 80);
        add_filter('display_post_states', [$admin, 'displayPostStates'], 100, 2);
    }

    /**
     * Register frontend-only hooks.
     */
    private function registerFrontendHooks(): void
    {
        if (is_admin()) {
            return;
        }

        $handler = $this->container->get(Handler::class);
        $queryFilter = $this->container->get(QueryFilter::class);

        add_action('parse_query', [$handler, 'withQueryProperties'], 1);
        add_filter('posts_where', [$queryFilter, 'filterPostsWhere'], 10, 2);
        add_filter('wp_nav_menu_objects', [$queryFilter, 'withCurrentAncestor'], 10, 2);
    }

    /**
     * Register hooks that apply to both admin and frontend.
     */
    private function registerCommonHooks(): void
    {
        $lifecycle = $this->container->get(LifecycleManager::class);
        $postType = $this->container->get(PostType::class);

        // Post type registration hooks
        add_filter('register_post_type_args', [$postType, 'updatePostTypeArgs'], 10, 2);
        add_action('registered_post_type', [$postType, 'addPaginationRewriteTags'], 10, 2);

        // Option lifecycle hooks (watch for each post type)
        add_action('registered_post_type', [$lifecycle, 'watchOptions'], 10, 2);

        // Post lifecycle hooks
        add_action('transition_post_status', [$lifecycle, 'onTransitionPostStatus'], 10, 3);
        add_action('delete_post', [$lifecycle, 'onDeletedPost']);
        add_action('wp_trash_post', [$lifecycle, 'onDeletedPost']);
        add_action('post_updated', [$lifecycle, 'onPageUpdated'], 10, 3);

        // Template redirect
        add_action('template_redirect', [$this, 'onTemplateRedirect']);
    }

    /**
     * Handle template redirect.
     */
    public function onTemplateRedirect(): void
    {
        $api = $this->container->get(Api::class);

        if (!$api->isPageForCustomPostType()) {
            return;
        }

        do_action('pfcpt/template_redirect');
    }

    /**
     * Get all registered integration class names.
     *
     * @return list<class-string<IntegrationInterface>>
     */
    public function getIntegrations(): array
    {
        return [
            Polylang\Polylang::class,
            WordPressSeo\WordPressSeo::class,
            Wpml\Wpml::class,
            Autodescription\Autodescription::class,
        ];
    }

    // =========================================================================
    // Legacy compatibility methods (delegate to Api/RewriteManager)
    // =========================================================================

    /**
     * @deprecated Use getApi()->isPageForCustomPostType() instead
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function is_page_for_custom_post_type(?string $postType = null): bool
    {
        return $this->getApi()->isPageForCustomPostType($postType);
    }

    /**
     * @deprecated Use getApi()->isQueryPageForCustomPostType() instead
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function is_query_page_for_custom_post_type(): bool
    {
        return $this->getApi()->isQueryPageForCustomPostType();
    }

    /**
     * @deprecated Use getApi()->getPostTypeFromPageId() instead
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function get_post_type_from_page_id(int $pageId): ?string
    {
        return $this->getApi()->getPostTypeFromPageId($pageId);
    }

    /**
     * @deprecated Use getApi()->getPageIdFromPostType() instead
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function get_page_id_from_post_type(string $postType, bool $applyFilters = true): ?int
    {
        return $this->getApi()->getPageIdFromPostType($postType, $applyFilters);
    }

    /**
     * @deprecated Use getApi()->getPageIds() instead
     *
     * @return array<string, int>
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function get_page_ids(bool $applyFilters = true): array
    {
        return $this->getApi()->getPageIds($applyFilters);
    }

    /**
     * @deprecated Use getApi()->getOptionName() instead
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function get_option_name(string $postType): string
    {
        return $this->getApi()->getOptionName($postType);
    }

    /**
     * @deprecated Use RewriteManager::getPageSlug() instead
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function get_page_slug(int $pageId): ?string
    {
        return $this->container->get(RewriteManager::class)->getPageSlug($pageId);
    }

    /**
     * @deprecated Use RewriteManager::flushRewriteRules() instead
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function flush_rewrite_rules(string $postType): void
    {
        $this->container->get(RewriteManager::class)->flushRewriteRules($postType);
    }

    /**
     * @deprecated Use getInstance() instead
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function get_instance(): self
    {
        return self::getInstance();
    }
}
