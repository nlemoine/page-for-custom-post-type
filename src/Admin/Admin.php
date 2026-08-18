<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Admin;

use n5s\PageForCustomPostType\Core\Api;
use n5s\PageForCustomPostType\PostType\PostType;
use WP_Admin_Bar;
use WP_Post;
use WP_Post_Type;
use WP_Screen;

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
     * Enqueue the Quick Edit warning on the pages list screen when at least
     * one page is assigned to a CPT with `use_slug` enabled.
     *
     * The script wraps `inlineEditPost.save` to surface a `confirm()` dialog
     * if the user changes the slug of a protected page via Quick Edit.
     */
    public function enqueueQuickEditAssets(string $hook): void
    {
        if ($hook !== 'edit.php') {
            return;
        }

        $screen = get_current_screen();
        if (!$screen instanceof WP_Screen || $screen->post_type !== 'page') {
            return;
        }

        $protected = $this->getProtectedPages();
        if (empty($protected)) {
            return;
        }

        $pluginRoot = \dirname(__DIR__, 2);
        $assetFile = $pluginRoot . '/build/admin/quick-edit-warning/index.asset.php';
        $asset = file_exists($assetFile)
            ? require $assetFile
            : ['dependencies' => [], 'version' => false];

        $deps = \is_array($asset['dependencies'] ?? null) ? $asset['dependencies'] : [];
        if (!\in_array('inline-edit-post', $deps, true)) {
            $deps[] = 'inline-edit-post';
        }

        wp_enqueue_script(
            'pfcpt-quick-edit-warning',
            plugins_url('build/admin/quick-edit-warning/index.js', $pluginRoot . '/plugin.php'),
            $deps,
            \is_string($asset['version'] ?? null) ? $asset['version'] : false,
            true
        );

        wp_add_inline_script(
            'pfcpt-quick-edit-warning',
            'const pfcptQuickEdit = ' . wp_json_encode(['protectedPages' => $protected]) . ';',
            'before'
        );

        wp_set_script_translations('pfcpt-quick-edit-warning', 'pfcpt', $pluginRoot . '/languages');
    }

    /**
     * Build the map of pages that should trigger the slug-change warning.
     *
     * @return array<int, string> page ID => plural CPT label
     */
    private function getProtectedPages(): array
    {
        $protected = [];

        foreach ($this->api->getPageIds() as $cpt => $pageId) {
            if (!$this->api->shouldUsePageSlug($cpt)) {
                continue;
            }

            $obj = get_post_type_object($cpt);
            if (!$obj instanceof WP_Post_Type) {
                continue;
            }

            $label = \is_string($obj->labels->name) ? $obj->labels->name : $cpt;
            $protected[(int) $pageId] = $label;
        }

        return $protected;
    }

    /**
     * Enqueue the block editor warning when editing a page that's used as
     * the URL base for a custom post type.
     *
     * Only enqueues when all conditions match:
     * - editing a published or draft page (post_type === 'page')
     * - the page is assigned to a CPT in the page-for-CPT mapping
     * - the matching `_use_slug` option is enabled
     * - the page has a non-empty slug (skip new drafts)
     */
    public function enqueueBlockEditorAssets(): void
    {
        $post = get_post();

        if (!$post instanceof WP_Post || $post->post_type !== 'page') {
            return;
        }

        if ($post->post_name === '') {
            return;
        }

        $cpt = $this->api->getPostTypeFromPageId($post->ID);

        if ($cpt === null) {
            return;
        }

        if (!$this->api->shouldUsePageSlug($cpt)) {
            return;
        }

        $pluginRoot = \dirname(__DIR__, 2);
        $assetFile = $pluginRoot . '/build/editor/slug-warning/index.asset.php';
        $asset = file_exists($assetFile)
            ? require $assetFile
            : ['dependencies' => [], 'version' => false];

        wp_enqueue_script(
            'pfcpt-slug-warning',
            plugins_url('build/editor/slug-warning/index.js', $pluginRoot . '/plugin.php'),
            \is_array($asset['dependencies'] ?? null) ? $asset['dependencies'] : [],
            \is_string($asset['version'] ?? null) ? $asset['version'] : false,
            true
        );

        $postTypeObject = get_post_type_object($cpt);
        $label = $postTypeObject instanceof WP_Post_Type && \is_string($postTypeObject->labels->name)
            ? $postTypeObject->labels->name
            : $cpt;

        wp_add_inline_script(
            'pfcpt-slug-warning',
            'const pfcptSlugWarning = ' . wp_json_encode([
                'postTypeLabel' => $label,
                'postTypeName' => $cpt,
            ]) . ';',
            'before'
        );

        wp_set_script_translations('pfcpt-slug-warning', 'pfcpt', $pluginRoot . '/languages');
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
        $labelName = \is_string($postTypeObject->labels->name) ? $postTypeObject->labels->name : $postType;
        /* translators: %s: post type name */
        $postStates[$name] = esc_html(\sprintf(__('%s page', 'pfcpt'), $labelName));

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
        $pageId = get_option('page_for_posts');
        $postTypeObject = get_post_type_object('post');
        if ($postTypeObject && $pageId) {
            $postType = 'post';
            $editPostLink = get_edit_post_link($pageId);
            if ($editPostLink) {
                $archivesLabel = \is_string($postTypeObject->labels->archives) ? $postTypeObject->labels->archives : $postType;

                add_submenu_page(
                    'edit.php',
                    $archivesLabel,
                    $archivesLabel,
                    'edit_pages',
                    $editPostLink
                );
            }
        }

        foreach ($this->api->getPageIds() as $postType => $pageId) {
            $postTypeObject = get_post_type_object($postType);
            if (!$postTypeObject) {
                continue;
            }

            $editPostLink = get_edit_post_link($pageId);
            if (!$editPostLink) {
                continue;
            }

            $archivesLabel = \is_string($postTypeObject->labels->archives) ? $postTypeObject->labels->archives : $postType;

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

            $labelName = \is_string($postTypeObj->labels->name) ? $postTypeObj->labels->name : $postTypeObj->name;

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

        if (!\is_array($dropdownArgs)) {
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
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() returns pre-escaped HTML.
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
                    esc_html(mb_strtolower(\is_string($args['postType']->labels->name) ? $args['postType']->labels->name : $args['postType']->name))
                );
                ?>
            </label>
            <p class="description">
                ⚠️
                <?php
                printf(
                    /* translators: %s: plural post type name */
                    esc_html__('Changing this option will alter all single "%s" URLs. This may affect SEO and existing links.', 'pfcpt'),
                    esc_html(mb_strtolower(\is_string($args['postType']->labels->name) ? $args['postType']->labels->name : $args['postType']->name))
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
        $rewriteArgs = \is_array($originalArgs['rewrite'] ?? null) ? $originalArgs['rewrite'] : [];
        $rewriteSlug = \is_string($rewriteArgs['slug'] ?? null) ? $rewriteArgs['slug'] : $postTypeName;
        $archiveSlug = $hasArchive === true ? $rewriteSlug : (\is_string($hasArchive) ? $hasArchive : $postTypeName);

        $prefix = $wp_rewrite->root;
        if (!empty($rewriteArgs['with_front'])) {
            $prefix = substr($wp_rewrite->front, 1);
        }

        /* translators: %s: archive slug */
        return \sprintf(__('— Default (/%s/) —', 'pfcpt'), $prefix . $archiveSlug);
    }
}
