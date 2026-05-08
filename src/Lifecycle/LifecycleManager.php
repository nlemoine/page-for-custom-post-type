<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Lifecycle;

use n5s\PageForCustomPostType\Admin\SettingsValidator;
use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\Core\RewriteManager;
use WP_Post;
use WP_Post_Type;

/**
 * Manages WordPress lifecycle hooks for options and posts.
 */
final class LifecycleManager
{
    public function __construct(
        private readonly Api $api,
        private readonly RewriteManager $rewriteManager,
        private readonly SettingsValidator $validator
    ) {
    }

    /**
     * Watch options for a post type.
     *
     * Called when a post type is registered to set up option hooks.
     */
    public function watchOptions(string $postType, WP_Post_Type $postTypeObject): void
    {
        if (!$this->api->shouldConsiderPostType($postTypeObject)) {
            return;
        }

        $optionName = $this->api->getOptionName($postType);
        $useSlugOptionName = $this->api->getUseSlugOptionName($postType);

        // Sanitize/validate hook
        add_filter(
            "sanitize_option_{$optionName}",
            $this->validator->validate(...),
            10,
            3
        );

        // Watch for changes on page selection
        add_action("update_option_{$optionName}", [$this, 'onOptionUpdate'], 10, 3);
        add_action("add_option_{$optionName}", [$this, 'onOptionAdd'], 10, 2);
        add_action("delete_option_{$optionName}", [$this, 'onOptionDelete'], 10);

        // Watch for changes on "use slug" checkbox
        add_action("update_option_{$useSlugOptionName}", [$this, 'onUseSlugOptionUpdate'], 10, 3);
        add_action("add_option_{$useSlugOptionName}", [$this, 'onUseSlugOptionAdd'], 10, 2);
    }

    /**
     * Handle option update.
     */
    public function onOptionUpdate(mixed $oldValue, mixed $newValue, string $name): void
    {
        if ($oldValue === $newValue) {
            return;
        }

        $this->onOptionChange($name, $newValue);
    }

    /**
     * Handle option add.
     */
    public function onOptionAdd(string $name, mixed $value): void
    {
        $this->onOptionChange($name, $value);
    }

    /**
     * Handle option delete.
     */
    public function onOptionDelete(string $name): void
    {
        $this->onOptionChange($name, null);
    }

    /**
     * Handle "use slug" option update.
     */
    public function onUseSlugOptionUpdate(mixed $oldValue, mixed $newValue, string $name): void
    {
        if ($oldValue === $newValue) {
            return;
        }

        $this->onUseSlugOptionChange($name);
    }

    /**
     * Handle "use slug" option add.
     */
    public function onUseSlugOptionAdd(string $name, mixed $value): void
    {
        $this->onUseSlugOptionChange($name);
    }

    /**
     * Handle "use slug" option change - flush rewrite rules.
     */
    private function onUseSlugOptionChange(string $name): void
    {
        $postType = $this->getPostTypeFromUseSlugOptionName($name);
        $this->rewriteManager->flushRewriteRules($postType);
    }

    /**
     * Extract post type from "use slug" option name.
     */
    private function getPostTypeFromUseSlugOptionName(string $name): string
    {
        $withoutPrefix = substr($name, \strlen(Api::OPTION_PREFIX));

        return substr($withoutPrefix, 0, -\strlen(Api::OPTION_SUFFIX_USE_SLUG));
    }

    /**
     * Handle page status transitions.
     *
     * Delete the setting if the assigned page is unpublished.
     */
    public function onTransitionPostStatus(string $newStatus, string $oldStatus, WP_Post $post): void
    {
        if ($post->post_type !== 'page') {
            return;
        }

        if ($newStatus === 'publish') {
            return;
        }

        $postType = $this->api->getPostTypeFromPageId($post->ID);

        if (!$postType) {
            return;
        }

        $this->deleteOption($postType);
    }

    /**
     * Handle page deletion or trash.
     */
    public function onDeletedPost(int $postId, ?WP_Post $post = null): void
    {
        if ($post === null) {
            $post = get_post($postId);
        }

        if (!$post instanceof WP_Post) {
            return;
        }

        if ($post->post_type !== 'page') {
            return;
        }

        $postType = $this->api->getPostTypeFromPageId($postId);

        if (!$postType) {
            return;
        }

        $this->deleteOption($postType);
    }

    /**
     * Handle page updates that affect the page permalink.
     *
     * Flushes rewrite rules when the slug or parent of an assigned page (or
     * any of its ancestors) changes, since either alters the page's URL.
     */
    public function onPageUpdated(int $postId, WP_Post $postAfter, WP_Post $postBefore): void
    {
        if ($postAfter->post_type !== 'page') {
            return;
        }

        if (
            $postAfter->post_name === $postBefore->post_name
            && $postAfter->post_parent === $postBefore->post_parent
        ) {
            return;
        }

        foreach ($this->api->getPageIds() as $postType => $assignedPageId) {
            if (
                $postId !== $assignedPageId
                && !\in_array($postId, get_post_ancestors($assignedPageId), true)
            ) {
                continue;
            }

            $this->rewriteManager->flushRewriteRules($postType);
        }
    }

    /**
     * Handle any option change.
     */
    private function onOptionChange(string $name, mixed $value): void
    {
        $postType = $this->getPostTypeFromOptionName($name);

        // Update the aggregated option
        $pageIds = (array) get_option(Api::OPTION_PAGE_IDS, []);
        $pageIds[$postType] = $value;

        update_option(Api::OPTION_PAGE_IDS, array_filter($pageIds));

        $this->rewriteManager->flushRewriteRules($postType);
    }

    /**
     * Delete option for a post type.
     */
    private function deleteOption(string $postType): void
    {
        delete_option($this->api->getOptionName($postType));
    }

    /**
     * Extract post type from option name.
     */
    private function getPostTypeFromOptionName(string $name): string
    {
        return substr($name, \strlen(Api::OPTION_PREFIX));
    }
}
