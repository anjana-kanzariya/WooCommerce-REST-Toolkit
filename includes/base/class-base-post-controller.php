<?php
defined('ABSPATH') || exit;

abstract class ADX_REST_Base_Post_Controller extends WC_REST_Posts_Controller {

    protected $namespace = 'wc/v3';

    public function __construct($post_type, $rest_base = null) {
        $this->post_type = $post_type;
        $this->rest_base = $rest_base ?: $post_type;
    }

    /**
     * Override this in child classes to control which meta keys are exposed.
     */
    protected function get_exposed_meta_keys() {
        return [];
    }

    /**
     * Optional ACF integration.
     */
    protected function get_acf_fields($post_id) {
        if (!function_exists('get_fields')) {
            return [];
        }

        $fields = get_fields($post_id);

        return is_array($fields) ? $fields : [];
    }

    /**
     * Filtered meta output.
     */
    protected function get_filtered_meta_data($post) {
        $allowed_keys = $this->get_exposed_meta_keys();

        if (empty($allowed_keys)) {
            return [];
        }

        $meta = get_post_meta($post->ID);
        $data = [];

        foreach ($allowed_keys as $key) {
            if (isset($meta[$key])) {
                $data[] = [
                    'key'   => $key,
                    'value' => maybe_unserialize($meta[$key][0]),
                ];
            }
        }

        return $data;
    }

    public function prepare_item_for_response($post, $request) {

        $data = [
            'id'        => $post->ID,
            'title'     => get_the_title($post),
            'slug'      => $post->post_name,
            'status'    => $post->post_status,
            'permalink' => get_permalink($post),
            'meta_data' => $this->get_filtered_meta_data($post),
            'acf'       => $this->get_acf_fields($post->ID),
        ];

        $context = $request['context'] ?? 'view';

        $data = $this->add_additional_fields_to_object($data, $request);
        $data = $this->filter_response_by_context($data, $context);

        $response = rest_ensure_response($data);

        return apply_filters(
            "mg_rest_prepare_{$this->post_type}",
            $response,
            $post,
            $request
        );
    }

    public function register_routes() {
        parent::register_routes();
    }
}