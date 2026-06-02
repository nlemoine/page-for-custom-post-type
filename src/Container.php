<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType;

use n5s\PageForCustomPostType\Admin\Admin;
use n5s\PageForCustomPostType\Admin\SettingsValidator;
use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;
use n5s\PageForCustomPostType\Frontend\Handler;
use n5s\PageForCustomPostType\Frontend\QueryFilter;
use n5s\PageForCustomPostType\Integration\AdvancedCustomFields;
use n5s\PageForCustomPostType\Integration\Autodescription;
use n5s\PageForCustomPostType\Integration\Polylang;
use n5s\PageForCustomPostType\Integration\WordPressSeo;
use n5s\PageForCustomPostType\Integration\Wpml;
use n5s\PageForCustomPostType\Lifecycle\LifecycleManager;
use n5s\PageForCustomPostType\Lifecycle\Migrator;
use n5s\PageForCustomPostType\PostType\PostType;
use WP_Query;
use wpdb;

/**
 * Dependency injection container.
 */
final class Container
{
    /** @var array<class-string, object> */
    private array $services = [];

    /** @var array<class-string, callable(): object> */
    private array $factories;

    public function __construct()
    {
        $this->factories = [
            // WP
            wpdb::class => static function (): wpdb {
                $wpdb = $GLOBALS['wpdb'];
                if (!$wpdb instanceof wpdb) {
                    throw new \RuntimeException('Global $wpdb is not available.');
                }
                return $wpdb;
            },
            WP_Query::class => static function (): WP_Query {
                $wpQuery = $GLOBALS['wp_query'] ?? null;
                if (!$wpQuery instanceof WP_Query) {
                    throw new \RuntimeException('Global $wp_query is not available.');
                }
                return $wpQuery;
            },

            // Core services (no dependencies)
            Api::class => static fn (): Api => new Api(),

            // Services with single dependency
            RewriteManager::class => fn (): RewriteManager => new RewriteManager(
                $this->get(Api::class)
            ),

            SettingsValidator::class => static fn (): SettingsValidator => new SettingsValidator(),

            Migrator::class => static fn (): Migrator => new Migrator(),

            Handler::class => fn (): Handler => new Handler(
                $this->get(Api::class)
            ),

            QueryFilter::class => fn (): QueryFilter => new QueryFilter(
                $this->get(Api::class),
                $this->get(wpdb::class)
            ),

            // Services with multiple dependencies
            PostType::class => fn (): PostType => new PostType(
                $this->get(Api::class),
                $this->get(RewriteManager::class)
            ),

            LifecycleManager::class => fn (): LifecycleManager => new LifecycleManager(
                $this->get(Api::class),
                $this->get(RewriteManager::class),
                $this->get(SettingsValidator::class)
            ),

            Admin::class => fn (): Admin => new Admin(
                $this->get(Api::class),
                $this->get(PostType::class)
            ),

            // Polylang integrations
            Polylang\UrlTranslation::class => static fn (): Polylang\UrlTranslation => new Polylang\UrlTranslation(),

            Polylang\Translation::class => static fn (): Polylang\Translation => new Polylang\Translation(),

            Polylang\SlugTranslation::class => fn (): Polylang\SlugTranslation => new Polylang\SlugTranslation(
                $this->get(Api::class),
                $this->get(RewriteManager::class)
            ),

            Polylang\Admin::class => static fn (): Polylang\Admin => new Polylang\Admin(),

            Polylang\Lifecycle::class => fn (): Polylang\Lifecycle => new Polylang\Lifecycle(
                $this->get(Api::class),
                $this->get(RewriteManager::class)
            ),

            // WPML integrations
            Wpml\Translation::class => static fn (): Wpml\Translation => new Wpml\Translation(),

            Wpml\UrlTranslation::class => fn (): Wpml\UrlTranslation => new Wpml\UrlTranslation(
                $this->get(Api::class)
            ),

            Wpml\Admin::class => static fn (): Wpml\Admin => new Wpml\Admin(),

            Wpml\Lifecycle::class => fn (): Wpml\Lifecycle => new Wpml\Lifecycle(
                $this->get(Api::class),
                $this->get(RewriteManager::class),
                $this->get(Wpml\Translation::class)
            ),

            Wpml\Wpml::class => fn (): Wpml\Wpml => new Wpml\Wpml(
                $this->get(Wpml\Translation::class),
                $this->get(Wpml\UrlTranslation::class),
                $this->get(Wpml\Admin::class),
                $this->get(Wpml\Lifecycle::class)
            ),

            // WordPressSeo integrations
            WordPressSeo\Schema::class => fn (): WordPressSeo\Schema => new WordPressSeo\Schema(
                $this->get(Api::class)
            ),

            WordPressSeo\Breadcrumbs::class => fn (): WordPressSeo\Breadcrumbs => new WordPressSeo\Breadcrumbs(
                $this->get(Api::class)
            ),

            WordPressSeo\Indexables::class => fn (): WordPressSeo\Indexables => new WordPressSeo\Indexables(
                $this->get(Api::class)
            ),

            // Integration composites
            AdvancedCustomFields\AdvancedCustomFields::class => fn (): AdvancedCustomFields\AdvancedCustomFields => new AdvancedCustomFields\AdvancedCustomFields(
                $this->get(Api::class)
            ),
            Polylang\Polylang::class => fn (): Polylang\Polylang => new Polylang\Polylang(
                $this->get(Polylang\UrlTranslation::class),
                $this->get(Polylang\Translation::class),
                $this->get(Polylang\SlugTranslation::class),
                $this->get(Polylang\Admin::class),
                $this->get(Polylang\Lifecycle::class)
            ),

            WordPressSeo\WordPressSeo::class => fn (): WordPressSeo\WordPressSeo => new WordPressSeo\WordPressSeo(
                $this->get(WordPressSeo\Schema::class),
                $this->get(WordPressSeo\Breadcrumbs::class),
                $this->get(WordPressSeo\Indexables::class)
            ),

            // Autodescription (The SEO Framework) integrations
            Autodescription\QueryType::class => fn (): Autodescription\QueryType => new Autodescription\QueryType(
                $this->get(Api::class)
            ),

            Autodescription\Breadcrumbs::class => fn (): Autodescription\Breadcrumbs => new Autodescription\Breadcrumbs(
                $this->get(Api::class)
            ),

            Autodescription\Autodescription::class => fn (): Autodescription\Autodescription => new Autodescription\Autodescription(
                $this->get(Autodescription\QueryType::class),
                $this->get(Autodescription\Breadcrumbs::class)
            ),
        ];
    }

    /**
     * Get a service by class name.
     *
     * @template T of object
     * @param class-string<T> $name
     * @return T
     */
    public function get(string $name): object
    {
        if (!isset($this->services[$name])) {
            if (!isset($this->factories[$name])) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new \InvalidArgumentException("Unknown service: {$name}");
            }

            $this->services[$name] = $this->factories[$name]();
        }

        /** @var T */
        return $this->services[$name];
    }

    /**
     * Check if a service exists.
     *
     * @param class-string $name
     */
    public function has(string $name): bool
    {
        return isset($this->factories[$name]);
    }
}
