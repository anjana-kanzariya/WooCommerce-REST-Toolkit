<?php
defined('ABSPATH') || exit;

abstract class ADX_REST_Base_Terms_Controller extends WC_REST_Terms_Controller {

    protected $namespace = 'wc/v3';

    public function __construct($taxonomy, $rest_base = null) {
        $this->taxonomy  = $taxonomy;
        $this->rest_base = $rest_base ?: $taxonomy;
    }

    protected function get_exposed_meta_keys() {
        return [];
    }

    protected function get_filtered_meta_data($term) {

        $allowed_keys = $this->get_exposed_meta_keys();

        if (empty($allowed_keys)) {
            return [];
        }

        $meta = get_term_meta($term->term_id);
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

    public function prepare_item_for_response($item, $request) {

        $data = [
            'id'          => $item->term_id,
            'name'        => $item->name,
            'slug'        => $item->slug,
            'description' => $item->description,
            'count'       => $item->count,
            'meta_data'   => $this->get_filtered_meta_data($item),
        ];

        $context = $request['context'] ?? 'view';

        $data = $this->add_additional_fields_to_object($data, $request);
        $data = $this->filter_response_by_context($data, $context);

        $response = rest_ensure_response($data);
        $response->add_links($this->prepare_links($item, $request));

        return apply_filters(
            "mg_rest_prepare_{$this->taxonomy}",
            $response,
            $item,
            $request
        );
    }

    public function register_routes() {
        parent::register_routes();
    }
}