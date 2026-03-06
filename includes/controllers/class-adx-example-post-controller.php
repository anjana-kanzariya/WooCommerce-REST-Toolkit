<?php
/**
 * Example: Custom Post Type Controller
 *
 * Copy this file, rename the class, set $post_type, and implement
 * the methods you need. Everything else is inherited from the base.
 *
 * @package WooCommerce_REST_Toolkit
 */

defined( 'ABSPATH' ) || exit;

class ADX_REST_Example_Post_Controller extends ADX_REST_Base_Post_Controller {

    /**
     * Set this to your custom post type slug.
     *
     * @var string
     */
    protected $post_type = 'your_post_type';

    /**
     * @param string $namespace e.g. 'wc/v3'
     * @param string $rest_base e.g. 'your-resource'
     */
    public function __construct( string $namespace = 'wc/v3', string $rest_base = 'your-resource' ) {
        parent::__construct( $namespace, $rest_base );
    }

    /**
     * Declare which meta keys are exposed in the response.
     * Only keys listed here will appear — nothing else leaks.
     *
     * @return string[]
     */
    protected function get_exposed_meta_keys() : array {
        return [
            'your_meta_key',
            'another_meta_key',
        ];
    }

    /**
     * Add extra fields to the response — ACF, computed values, relationships, etc.
     * Return an associative array of key => value.
     *
     * @param WP_Post         $post
     * @param WP_REST_Request $request
     * @return array
     */
    protected function get_extra_fields( WP_Post $post, WP_REST_Request $request ) : array {
        return [
            // 'acf_field' => get_field( 'acf_field_name', $post->ID ),
            // 'computed'  => some_function( $post->ID ),
        ];
    }

    /**
     * Declare schema properties for any fields added in get_extra_fields().
     *
     * @return array
     */
    protected function get_extra_schema_properties() : array {
        return [
            // 'acf_field' => [ 'type' => 'string', 'context' => [ 'view', 'edit' ] ],
        ];
    }
}

// -----------------------------------------------------------------------------
// Register routes — hook this into rest_api_init in your plugin or theme.
// -----------------------------------------------------------------------------
//
// add_action( 'rest_api_init', function () {
//     ( new ADX_REST_Example_Post_Controller( 'wc/v3', 'your-resource' ) )->register_routes();
//
//     // To support both v2 and v3:
//     ( new ADX_REST_Example_Post_Controller( 'wc/v2', 'your-resource' ) )->register_routes();
//     ( new ADX_REST_Example_Post_Controller( 'wc/v3', 'your-resource' ) )->register_routes();
// } );