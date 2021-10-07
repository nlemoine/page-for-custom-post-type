<?php

namespace HelloNico\PageForCustomPostType\Integrations\ACF;

use ACF_Location_Page_Type;

class Location_Page_Type extends ACF_Location_Page_Type
{
    private $pfcpt;

    public function initialize()
    {
        parent::initialize();
        $this->pfcpt = \HelloNico\PageForCustomPostType\Plugin::get_instance();
    }

    /**
     * @inheritDoc
     */
    public function match($rule, $screen, $field_group)
    {
        $match = parent::match($rule, $screen, $field_group);
        if ($match) {
            return $match;
        }
        // Check screen args.
        if (isset($screen['post_id'])) {
            $post_id = $screen['post_id'];
        } else {
            return false;
        }

        // Get post.
        $post = \get_post($post_id);
        if (!$post) {
            return false;
        }

        $page_ids = $this->pfcpt->get_page_ids();
        if (empty($page_ids)) {
            return $match;
        }

        foreach ($page_ids as $post_type => $page_id) {
            if ($rule['value'] === $post_type . '_page') {
                $result = ($page_id === $post->ID);
                break;
            }
        }

        if (!isset($result)) {
            return false;
        }

        // Reverse result for "!=" operator.
        if ($rule['operator'] === '!=') {
            return !$result;
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function get_values($rule)
    {
        $values = parent::get_values($rule);
        $post_types = \array_keys($this->pfcpt->get_page_ids());
        if (empty($post_types)) {
            return $values;
        }

        foreach ($post_types as $post_type) {
            $post_type_object = \get_post_type_object($post_type);
            $values[$post_type . '_page'] = $post_type_object->labels->archives;
        }

        return $values;
    }
}
