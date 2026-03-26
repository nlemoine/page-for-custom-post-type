<?php

declare(strict_types=1);

namespace n5s\PageForCustomPostType\Admin;

use n5s\PageForCustomPostType\Core\Api;
use WP_Post_Type;

/**
 * Validates settings for page-to-post-type assignments.
 */
final class SettingsValidator
{
    /**
     * Validate a page assignment option.
     *
     * @param mixed $value The new value
     * @param string $name The option name
     * @param mixed $originalValue The original value
     * @return mixed The validated value or original on error
     */
    public function validate(mixed $value, string $name, mixed $originalValue): mixed
    {
        if (empty($value)) {
            return $value;
        }

        if (!\is_numeric($value)) {
            $this->addError($name, \__('Invalid page ID', 'pfcpt'));
            return $this->fallbackValue($name);
        }

        $value = (int) $value;

        $postType = $this->getPostTypeFromOptionName($name);
        $postTypeObject = \get_post_type_object($postType);
        if (!$postTypeObject instanceof WP_Post_Type) {
            return $value;
        }

        // Check post status
        $pageStatus = \get_post_status($value);

        if ($pageStatus !== 'publish') {
            $labelName = \is_string($postTypeObject->labels->name) ? $postTypeObject->labels->name : $postType;
            $this->addError($name, \sprintf(
                /* translators: 1: post type name, 2: page title */
                \__('Page for %1$s post type (%2$s) is not published', 'pfcpt'),
                $labelName,
                \get_the_title($value)
            ));
            return $this->fallbackValue($name);
        }

        $value = (int) $value;

        // Check for page ID used twice
        if ($this->isDuplicatePageId($value, $name)) {
            $labelNameDup = \is_string($postTypeObject->labels->name) ? $postTypeObject->labels->name : $postType;
            $this->addError($name, \sprintf(
                /* translators: 1: post type name, 2: page title */
                \__('Page for %1$s post type (%2$s) is already used', 'pfcpt'),
                $labelNameDup,
                \get_the_title($value)
            ));
            return $this->fallbackValue($name);
        }

        return \absint($value);
    }

    /**
     * Check if a page ID is already used by another post type.
     */
    private function isDuplicatePageId(int $value, string $currentOptionName): bool
    {
        // Get all page_for_* values from POST, excluding current option
        // Nonce verified by Settings API before sanitize callback.
        $otherPageIds = \array_filter(
            $_POST, // phpcs:ignore WordPress.Security.NonceVerification.Missing
            static fn (string $k): bool => \str_starts_with($k, Api::OPTION_PREFIX) && $k !== $currentOptionName,
            \ARRAY_FILTER_USE_KEY
        );

        $pageIds = \array_map(
            static fn (mixed $v): ?int => \is_numeric($v) ? (int) $v : null,
            $otherPageIds
        );

        $oldOptionValue = \get_option($currentOptionName);
        $oldValue = \is_numeric($oldOptionValue) ? (int) $oldOptionValue : 0;

        return \in_array($value, \array_filter($pageIds), true) && $value !== $oldValue;
    }

    /**
     * Add a settings error.
     */
    private function addError(string $name, string $message): void
    {
        \add_settings_error($name, "invalid_{$name}", $message, 'error');
    }

    /**
     * Get fallback value on validation error.
     */
    private function fallbackValue(string $name): mixed
    {
        $oldOptionValue = \get_option($name);
        $oldValue = \is_numeric($oldOptionValue) ? (int) $oldOptionValue : 0;

        return !empty($oldValue) ? $oldValue : null;
    }

    /**
     * Extract post type from option name.
     */
    private function getPostTypeFromOptionName(string $name): string
    {
        return \substr($name, \strlen(Api::OPTION_PREFIX));
    }
}
