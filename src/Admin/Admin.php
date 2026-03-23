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

        $postTypeObject = get_post_type_object($postType);

        if (!$postTypeObject) {
            return $postStates;
        }

        $name = $this->api->getOptionName($postType);
        $labelName = is_string($postTypeObject->labels->name) ? $postTypeObject->labels->name : $postType;
        /* translators: %s: post type name */
        $postStates[$name] = esc_html(sprintf(__('%s page', 'pfcpt'), $labelName));

        return $postStates;
    }

    /**
     * Add archive link to admin bar.
     */
    public function addAdminBarArchiveLink(WP_Admin_Bar $adminBar): void
    {
        $currentScreen = get_current_screen();

        if (!$currentScreen || $currentScreen->base !== 'edit') {
            return;
        }

        $postTypeObject = get_post_type_object($currentScreen->post_type);

        if (!$postTypeObject) {
            return;
        }

        if (!$postTypeObject->public || !$postTypeObject->show_in_admin_bar) {
            return;
        }

        $archiveUrl = \n5s\PageForCustomPostType\get_page_url_for_custom_post_type($postTypeObject->name);

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
            $postTypeObject = get_post_type_object($postType);
            if (!$postTypeObject) {
                continue;
            }

            $editPostLink = get_edit_post_link($pageId);
            if (!$editPostLink) {
                continue;
            }

            $archivesLabel = is_string($postTypeObject->labels->archives) ? $postTypeObject->labels->archives : $postType;

            add_submenu_page(
                'edit.php?post_type=' . $postType,
                $archivesLabel,
                $archivesLabel,
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

        add_settings_section(
            'page_for_custom_post_type',
            __('Pages for post type', 'pfcpt'),
            '__return_false',
            'reading'
        );

        foreach ($postTypes as $postTypeObj) {
            $fieldId = $this->api->getOptionName($postTypeObj);
            $useSlugFieldId = $this->api->getUseSlugOptionName($postTypeObj);
            $value = $this->api->getPageIdFromPostType($postTypeObj->name);
            $useSlugValue = $this->api->shouldUsePageSlug($postTypeObj->name);

            register_setting('reading', $fieldId, [
                'type' => 'integer',
            ]);

            register_setting('reading', $useSlugFieldId, [
                'type' => 'boolean',
                'default' => false,
            ]);

            $labelName = is_string($postTypeObj->labels->name) ? $postTypeObj->labels->name : $postTypeObj->name;

            add_settings_field(
                $fieldId,
                $labelName,
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
        $value = is_numeric($args['value']) ? (int) $args['value'] : 0;
        $useSlugValue = (bool) $args['useSlugValue'];
        $postTypeName = $args['postType']->name;
        $defaultLabel = $this->getDefaultLabel($postTypeName);

        /** @var array{name?: string, id?: string, selected?: int|string, show_option_none?: string, exclude?: int[], echo?: bool|int} $dropdownArgs */
        $dropdownArgs = apply_filters('pfcpt/dropdown_page_args', [
            'name' => esc_attr($args['name']),
            'id' => esc_attr($args['name'] . '_dropdown'),
            'selected' => $value,
            'show_option_none' => $defaultLabel ?? __('— Select —', 'pfcpt'),
            'exclude' => $this->getExcludedPageIds(),
        ]);

        if (!is_array($dropdownArgs)) {
            return;
        }

        $dropdownArgs['echo'] = false;

        $dropdownId = esc_attr($args['name'] . '_dropdown');
        $checkboxWrapperId = esc_attr($args['name'] . '_use_slug_wrapper');

        $appendSlug = static function (string $title, \WP_Post $page): string {
            $permalink = get_permalink($page->ID);

            if ($permalink === false) {
                return $title;
            }

            return $title . ' (' . wp_make_link_relative($permalink) . ')';
        };
        add_filter('list_pages', $appendSlug, 10, 2);
        $dropdown = wp_dropdown_pages($dropdownArgs);
        remove_filter('list_pages', $appendSlug);
        ?>
        <?= $dropdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <div id="<?= esc_attr($checkboxWrapperId); ?>" style="margin-top: 0.5em;<?= $value > 0 ? '' : ' display: none;'; ?>">
            <label>
                <input
                    type="checkbox"
                    name="<?= esc_attr($args['useSlugName']); ?>"
                    value="1"
                    <?php checked($useSlugValue); ?>
                />
                <?php
                printf(
                    /* translators: %s: plural post type name */
                    esc_html__('Selected page slug replaces default "%s" post type slug', 'pfcpt'),
                    esc_html(mb_strtolower(is_string($args['postType']->labels->name) ? $args['postType']->labels->name : $args['postType']->name))
                );
                ?>
            </label>
            <p class="description">
                ⚠️
                <?php
                printf(
                    /* translators: %s: plural post type name */
                    esc_html__('Changing this option will alter all single "%s" URLs. This may affect SEO and existing links.', 'pfcpt'),
                    esc_html(mb_strtolower(is_string($args['postType']->labels->name) ? $args['postType']->labels->name : $args['postType']->name))
                );
                ?>
            </p>
        </div>
        <script>
        (function() {
            var dropdown = document.getElementById('<?= esc_js($dropdownId); ?>');
            var checkboxWrapper = document.getElementById('<?= esc_js($checkboxWrapperId); ?>');
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

        $frontPageOption = get_option('page_on_front');
        $frontPageId = is_numeric($frontPageOption) ? (int) $frontPageOption : 0;
        if ($frontPageId > 0) {
            $excluded[] = $frontPageId;
        }

        $postsPageOption = get_option('page_for_posts');
        $postsPageId = is_numeric($postsPageOption) ? (int) $postsPageOption : 0;
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
            return __('— No archive —', 'pfcpt');
        }

        global $wp_rewrite;

        if (!$wp_rewrite instanceof \WP_Rewrite) {
            return null;
        }

        // Get rewrite slug from args
        $rewriteArgs = is_array($originalArgs['rewrite'] ?? null) ? $originalArgs['rewrite'] : [];
        $rewriteSlug = is_string($rewriteArgs['slug'] ?? null) ? $rewriteArgs['slug'] : $postTypeName;
        $archiveSlug = $hasArchive === true ? $rewriteSlug : (is_string($hasArchive) ? $hasArchive : $postTypeName);

        $prefix = $wp_rewrite->root;
        if (!empty($rewriteArgs['with_front'])) {
            $prefix = substr($wp_rewrite->front, 1);
        }

        /* translators: %s: archive slug */
        return sprintf(__('— Default (/%s/) —', 'pfcpt'), $prefix . $archiveSlug);
    }
}
