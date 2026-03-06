<?php
/**
 * Example: Custom Taxonomy Controller
 *
 * Copy this file, rename the class, set $taxonomy, and implement
 * the methods you need. Everything else is inherited from the base.
 *
 * @package WooCommerce_REST_Toolkit
 */

defined( 'ABSPATH' ) || exit;

class ADX_REST_Example_Terms_Controller extends ADX_REST_Base_Terms_Controller {

    /**
     * Set this to your taxonomy slug.
     *
     * @var string
     */
    protected $taxonomy = 'your_taxonomy';

    /**
     * @param string $namespace e.g. 'wc/v3'
     * @param string $rest_base e.g. 'products/your-taxonomy'
     */
    public function __construct( string $namespace = 'wc/v3', string $rest_base = 'products/your-taxonomy' ) {
        parent::__construct( $namespace, $rest_base );
    }

    /**
     * ACF image fields to read in the response.
     * Map: ACF field name => response key.
     *
     * @return array
     */
    protected function get_image_fields() : array {
        return [
            // 'acf_field_name' => 'response_key',
        ];
    }

    /**
     * ACF non-image fields to read in the response.
     * Map: ACF field name => response key.
     *
     * @return array
     */
    protected function get_acf_fields() : array {
        return [
            // 'acf_field_name' => 'response_key',
        ];
    }

    /**
     * ACF image fields for write operations (PUT/POST).
     * Map: request key => ACF field name.
     *
     * @return array
     */
    protected function get_writable_image_fields() : array {
        return [
            // 'response_key' => 'acf_field_name',
        ];
    }

    /**
     * ACF non-image fields for write operations (PUT/POST).
     * Map: request key => ACF field name.
     *
     * @return array
     */
    protected function get_writable_acf_fields() : array {
        return [
            // 'response_key' => 'acf_field_name',
        ];
    }

    /**
     * Add extra fields beyond ACF — relationships, computed values, etc.
     * Return an associative array of key => value.
     *
     * @param WP_Term         $item
     * @param WP_REST_Request $request
     * @return array
     */
    protected function get_extra_fields( WP_Term $item, WP_REST_Request $request ) : array {
        return [
            // 'related_ids' => get_term_meta( $item->term_id, 'related_ids', true ),
        ];
    }

    /**
     * Declare schema properties for fields added via get_image_fields(),
     * get_acf_fields(), and get_extra_fields().
     *
     * Use $this->image_schema_block( 'Label' ) for image fields.
     *
     * @return array
     */
    protected function get_extra_schema_properties() : array {
        return [
            // 'response_key' => $this->image_schema_block( 'My Image' ),
            // 'acf_field'    => [ 'type' => 'string', 'context' => [ 'view', 'edit' ] ],
            // 'related_ids'  => [ 'type' => 'array',  'context' => [ 'view', 'edit' ] ],
        ];
    }
}

// -----------------------------------------------------------------------------
// Register routes — hook this into rest_api_init in your plugin or theme.
// -----------------------------------------------------------------------------
//
// add_action( 'rest_api_init', function () {
//     ( new ADX_REST_Example_Terms_Controller( 'wc/v3', 'products/your-taxonomy' ) )->register_routes();
//
//     // To support both v2 and v3:
//     ( new ADX_REST_Example_Terms_Controller( 'wc/v2', 'products/your-taxonomy-v2' ) )->register_routes();
//     ( new ADX_REST_Example_Terms_Controller( 'wc/v3', 'products/your-taxonomy' ) )->register_routes();
// } );