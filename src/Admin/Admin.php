<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Admin;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\PostType\PostType;
use WP_Admin_Bar;
use WP_Post;
use WP_Post_Type;

/**
 * Handles admin UI: settings, menus, and post states.
 */
final class Admin
{
    public function __construct(
        private readonly Api $api,
        private readonly PostType $postType
    ) {
    }

    /**
     * Add an indicator to show if a page is set as a post type archive.
     *
     * @param string[] $postStates
     * @return string[]
     */
    public function displayPostStates(array $postStates, WP_Post $post): array
    {
        if ($post->post_type !== 'page') {
            return $postStates;
        }

        $postType = $this->api->getPostTypeFromPageId($post->ID);

        if (!$postType) {
            return $postStates;
        }

        $postTypeObject = \get_post_type_object($postType);

        if (!$postTypeObject) {
            return $postStates;
        }

        $name = $this->api->getOptionName($postType);
        /* translators: %s: post type name */
        $postStates[$name] = \esc_html(\sprintf(\__('%s page', 'pfcpt'), $postTypeObject->labels->name));

        return $postStates;
    }

    /**
     * Add archive link to admin bar.
     */
    public function addAdminBarArchiveLink(WP_Admin_Bar $adminBar): void
    {
        $currentScreen = \get_current_screen();

        if (!$currentScreen || $currentScreen->base !== 'edit') {
            return;
        }

        $postTypeObject = \get_post_type_object($currentScreen->post_type);

        if (!$postTypeObject) {
            return;
        }

        if (!$postTypeObject->public || !$postTypeObject->show_in_admin_bar) {
            return;
        }

        $archiveUrl = \get_page_url_for_custom_post_type($postTypeObject->name);

        if (!$archiveUrl) {
            return;
        }

        $adminBar->add_menu([
            'id' => 'archive',
            'title' => $postTypeObject->labels->view_items,
            'href' => $archiveUrl,
            'meta' => [
                'target' => '_blank',
            ],
        ]);
    }

    /**
     * Add submenu link to archive under each post type.
     */
    public function addPostTypeSubmenus(): void
    {
        foreach ($this->api->getPageIds() as $postType => $pageId) {
            $postTypeObject = \get_post_type_object($postType);
            if (!$postTypeObject) {
                continue;
            }

            $editPostLink = \get_edit_post_link($pageId);
            if (!$editPostLink) {
                continue;
            }

            \add_submenu_page(
                'edit.php?post_type=' . $postType,
                $postTypeObject->labels->archives,
                $postTypeObject->labels->archives,
                'edit_pages',
                $editPostLink
            );
        }
    }

    /**
     * Add option to Settings > Reading.
     */
    public function addReadingSettings(): void
    {
        $postTypes = $this->api->getPostTypes();

        \add_settings_section(
            'page_for_custom_post_type',
            \__('Pages for post type', 'pfcpt'),
            '__return_false',
            'reading'
        );

        foreach ($postTypes as $postTypeObj) {
            $fieldId = $this->api->getOptionName($postTypeObj);
            $useSlugFieldId = $this->api->getUseSlugOptionName($postTypeObj);
            $value = $this->api->getPageIdFromPostType($postTypeObj->name);
            $useSlugValue = $this->api->shouldUsePageSlug($postTypeObj->name);

            \register_setting('reading', $fieldId, [
                'type' => 'integer',
            ]);

            \register_setting('reading', $useSlugFieldId, [
                'type' => 'boolean',
                'default' => false,
            ]);

            \add_settings_field(
                $fieldId,
                $postTypeObj->labels->name,
                $this->renderPageDropdown(...),
                'reading',
                'page_for_custom_post_type',
                [
                    'name' => $fieldId,
                    'useSlugName' => $useSlugFieldId,
                    'postType' => $postTypeObj,
                    'value' => $value,
                    'useSlugValue' => $useSlugValue,
                    'label_for' => $fieldId . '_dropdown',
                ]
            );
        }
    }

    /**
     * Render the dropdown for selecting a page.
     *
     * @param array{name: string, useSlugName: string, postType: WP_Post_Type, value: mixed, useSlugValue: mixed, label_for: string} $args
     */
    private function renderPageDropdown(array $args): void
    {
        $value = (int) $args['value'];
        $useSlugValue = (bool) $args['useSlugValue'];
        $postTypeName = $args['postType']->name;
        $defaultLabel = $this->getDefaultLabel($postTypeName);

        $dropdownArgs = \apply_filters('pfcpt/dropdown_page_args', [
            'name' => \esc_attr($args['name']),
            'id' => \esc_attr($args['name'] . '_dropdown'),
            'selected' => $value,
            'show_option_none' => $defaultLabel ?? \__('— Select —', 'pfcpt'),
            'exclude' => $this->getExcludedPageIds(),
        ]);

        $dropdownArgs['echo'] = false;

        $dropdownId = \esc_attr($args['name'] . '_dropdown');
        $checkboxWrapperId = \esc_attr($args['name'] . '_use_slug_wrapper');

        $appendSlug = static fn (string $title, \WP_Post $page): string => $title . ' (/' . $page->post_name . '/)';
        \add_filter('list_pages', $appendSlug, 10, 2);
        $dropdown = \wp_dropdown_pages($dropdownArgs);
        \remove_filter('list_pages', $appendSlug);
        ?>
        <?= $dropdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <div id="<?= \esc_attr($checkboxWrapperId); ?>" style="margin-top: 0.5em;<?= $value > 0 ? '' : ' display: none;'; ?>">
            <label>
                <input
                    type="checkbox"
                    name="<?= \esc_attr($args['useSlugName']); ?>"
                    value="1"
                    <?php \checked($useSlugValue); ?>
                />
                <?php
                \printf(
                    /* translators: %s: plural post type name */
                    \esc_html__('Use page slug as base URL for single %s', 'pfcpt'),
                    \esc_html(\mb_strtolower($args['postType']->labels->name))
                );
                ?>
            </label>
            <p class="description">
                ⚠️
                <?php
                \esc_html_e(
                    'Changing this option will modify all single post URLs. This may affect SEO and existing links.',
                    'pfcpt'
                );
                ?>
            </p>
        </div>
        <script>
        (function() {
            var dropdown = document.getElementById('<?= \esc_js($dropdownId); ?>');
            var checkboxWrapper = document.getElementById('<?= \esc_js($checkboxWrapperId); ?>');
            dropdown.addEventListener('change', function() {
                checkboxWrapper.style.display = dropdown.value > 0 ? '' : 'none';
            });
        })();
        </script>
        <?php
    }

    /**
     * Get page IDs to exclude from the dropdown.
     *
     * Excludes the static front page and page for posts.
     *
     * @return int[]
     */
    private function getExcludedPageIds(): array
    {
        $excluded = [];

        $frontPageId = (int) \get_option('page_on_front');
        if ($frontPageId > 0) {
            $excluded[] = $frontPageId;
        }

        $postsPageId = (int) \get_option('page_for_posts');
        if ($postsPageId > 0) {
            $excluded[] = $postsPageId;
        }

        return $excluded;
    }

    /**
     * Get the default label for the dropdown based on original post type args.
     */
    private function getDefaultLabel(string $postTypeName): ?string
    {
        $originalArgs = $this->postType->getOriginalArgs($postTypeName);

        if (empty($originalArgs)) {
            return null;
        }

        // Read has_archive directly from args (don't rely on WP_Post_Type defaults)
        $hasArchive = $originalArgs['has_archive'] ?? false;

        if (!$hasArchive) {
            return \__('— No archive —', 'pfcpt');
        }

        /** @var \WP_Rewrite */
        global $wp_rewrite;

        // Get rewrite slug from args
        $rewriteSlug = $originalArgs['rewrite']['slug'] ?? $postTypeName;
        $archiveSlug = $hasArchive === true ? $rewriteSlug : $hasArchive;

        $prefix = $wp_rewrite->root;
        if ($originalArgs['rewrite']['with_front'] ?? null) {
            $prefix = \substr($wp_rewrite->front, 1);
        }

        /* translators: %s: archive slug */
        return \sprintf(\__('— Default (/%s/) —', 'pfcpt'), $prefix . $archiveSlug);
    }
}
