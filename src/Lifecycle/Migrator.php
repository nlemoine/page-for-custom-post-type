<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Lifecycle;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;

/**
 * Runs one-shot data migrations on plugin upgrade.
 *
 * The current schema version is stored in the `pfcpt_db_version` option.
 * Migrations short-circuit once that option is at or above the target.
 */
final class Migrator
{
    private const DB_VERSION_OPTION = 'pfcpt_db_version';
    // x-release-please-start-version
    private const CURRENT_VERSION = '1.1.0';
    // x-release-please-end

    public function __construct(
        private readonly RewriteManager $rewriteManager
    ) {
    }

    public function migrate(): void
    {
        $installed = get_option(self::DB_VERSION_OPTION, '');
        $installed = \is_string($installed) ? $installed : '';

        if (version_compare($installed, self::CURRENT_VERSION, '>=')) {
            return;
        }

        if ($installed === '') {
            $this->migrateToOptInUseSlug();
        }

        // Fresh installs have nothing to flush: the rules are built from
        // scratch on the next request anyway.
        if ($installed !== '') {
            $this->flushRewriteRules();
        }

        update_option(self::DB_VERSION_OPTION, self::CURRENT_VERSION);
    }

    /**
     * Drop the rewrite rules and the cached page slugs on upgrade.
     *
     * Both outlive an upgrade, and a release can change either: the rules the
     * plugin registers for a post type, or the way a page slug is derived from
     * its permalink. The next request rebuilds them.
     */
    private function flushRewriteRules(): void
    {
        $mapping = get_option(Api::OPTION_PAGE_IDS, []);
        $postTypes = \is_array($mapping) ? array_keys($mapping) : [];

        foreach ($postTypes as $postType) {
            if (!\is_string($postType) || $postType === '') {
                continue;
            }

            $this->rewriteManager->flushRewriteRules($postType);
        }

        // Nothing mapped: the rules still have to go.
        delete_option('rewrite_rules');
    }

    /**
     * Pre-0.6.0 always used the assigned page's slug as the CPT URL base.
     * From 0.6.0 it's opt-in via the per-CPT use_slug option. Preserve the
     * old URLs for upgrading sites by enabling use_slug on every assigned
     * CPT that doesn't already have an explicit value.
     */
    private function migrateToOptInUseSlug(): void
    {
        $mapping = get_option(Api::OPTION_PAGE_IDS, []);
        if (!\is_array($mapping)) {
            return;
        }

        foreach (array_keys($mapping) as $postType) {
            if (!\is_string($postType) || $postType === '') {
                continue;
            }

            $optionName = Api::OPTION_PREFIX . $postType . Api::OPTION_SUFFIX_USE_SLUG;
            if (get_option($optionName) === false) {
                update_option($optionName, '1');
            }
        }
    }
}
