<?php
/**
 * Plugin Name:  WooCommerce REST Toolkit
 * Description:  Extensible REST API base layer for WooCommerce. Provides reusable
 *               controllers for custom post types and taxonomies with ACF integration,
 *               controlled meta exposure, and clean schema support.
 * Version:      2.0.0
 * Author:       Anjana Kanzariya
 * License:      MIT
 * Requires PHP: 7.4
 *
 * @package WooCommerce_REST_Toolkit
 */

defined( 'ABSPATH' ) || exit;

// -----------------------------------------------------------------------
// WooCommerce must be active
// -----------------------------------------------------------------------
add_action( 'plugins_loaded', function () {

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'WooCommerce REST Toolkit requires WooCommerce to be installed and active.', 'woocommerce' )
                . '</p></div>';
        } );
        return;
    }

    // -----------------------------------------------------------------------
    // Load base classes
    // -----------------------------------------------------------------------
    require_once plugin_dir_path( __FILE__ ) . 'includes/base/class-adx-base-post-controller.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/base/class-adx-base-terms-controller.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/helpers/class-adx-acf-meta-sync.php';

    // -----------------------------------------------------------------------
    // Load example controllers (for reference only — not auto-registered)
    // Copy, rename, and register your own controllers from these examples.
    // -----------------------------------------------------------------------
    require_once plugin_dir_path( __FILE__ ) . 'includes/controllers/class-adx-example-post-controller.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/controllers/class-adx-example-terms-controller.php';

    // -----------------------------------------------------------------------
    // Register your controllers here on rest_api_init.
    // See the example controllers in includes/controllers/ for usage.
    // -----------------------------------------------------------------------
    // add_action( 'rest_api_init', function () {
    //     ( new ADX_REST_Example_Post_Controller( 'wc/v3', 'your-resource' ) )->register_routes();
    //     ( new ADX_REST_Example_Terms_Controller( 'wc/v3', 'products/your-taxonomy' ) )->register_routes();
    // } );

    // -----------------------------------------------------------------------
    // ACF meta sync on product and order write operations
    // -----------------------------------------------------------------------
    add_filter( 'woocommerce_rest_pre_insert_product_object', function ( $product, $request ) {
        return ADX_ACF_Meta_Sync::sync( 'product', $product, $request );
    }, 10, 2 );

    add_filter( 'woocommerce_rest_pre_insert_shop_order', function ( $order, $request ) {
        return ADX_ACF_Meta_Sync::sync( 'shop_order', $order, $request );
    }, 10, 2 );

    // -----------------------------------------------------------------------
    // Expose public meta on product and order responses
    // -----------------------------------------------------------------------
    add_filter( 'woocommerce_rest_prepare_product_object', 'adx_append_public_meta_to_response', 10, 3 );
    add_filter( 'woocommerce_rest_prepare_shop_order_object', 'adx_append_public_meta_to_response', 10, 3 );

    // -----------------------------------------------------------------------
    // modified_after / modified_after_gmt query params for products + orders
    // -----------------------------------------------------------------------
    add_filter( 'rest_product_collection_params', 'adx_register_modified_after_param' );
    add_filter( 'rest_shop_order_collection_params', 'adx_register_modified_after_param' );
    add_filter( 'woocommerce_rest_product_object_query', 'adx_apply_modified_after_query', 10, 2 );
    add_filter( 'woocommerce_rest_shop_order_object_query', 'adx_apply_modified_after_query', 10, 2 );

} );

// -----------------------------------------------------------------------
// Public meta exposure on product / order responses
// -----------------------------------------------------------------------

/**
 * Append all non-private meta keys to product/order REST responses.
 * Private keys (prefixed with '_') are excluded.
 *
 * @param WP_REST_Response $response REST response.
 * @param WC_Data          $object   WooCommerce object.
 * @param WP_REST_Request  $request  Request object.
 * @return WP_REST_Response
 */
function adx_append_public_meta_to_response(
    WP_REST_Response $response,
    $object,
    WP_REST_Request $request
) : WP_REST_Response {
    global $wpdb;

    $post_id = (int) $object->get_id();

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT meta_key, meta_value, meta_id FROM {$wpdb->postmeta} WHERE post_id = %d",
            $post_id
        )
    );

    if ( empty( $results ) ) {
        return $response;
    }

    $data      = $response->get_data();
    $existing  = array_column( $data['meta_data'] ?? [], 'key' );

    foreach ( $results as $row ) {
        // Skip private meta and anything already in the response
        if ( '_' === substr( $row->meta_key, 0, 1 ) ) {
            continue;
        }

        if ( in_array( $row->meta_key, $existing, true ) ) {
            continue;
        }

        $data['meta_data'][] = [
            'id'    => (int) $row->meta_id,
            'key'   => $row->meta_key,
            'value' => maybe_unserialize( $row->meta_value ),
        ];
    }

    $response->set_data( $data );

    return $response;
}

// -----------------------------------------------------------------------
// modified_after query param support
// -----------------------------------------------------------------------

/**
 * Register modified_after and modified_after_gmt collection params.
 *
 * @param array $params Existing collection params.
 * @return array
 */
function adx_register_modified_after_param( array $params ) : array {
    $params['modified_after'] = [
        'description' => __( 'Limit results to items modified after a given ISO8601 date.', 'woocommerce' ),
        'type'        => 'string',
        'format'      => 'date-time',
    ];

    $params['modified_after_gmt'] = [
        'description' => __( 'Limit results to items modified after a given ISO8601 date (GMT).', 'woocommerce' ),
        'type'        => 'string',
        'format'      => 'date-time',
    ];

    return $params;
}

/**
 * Apply modified_after / modified_after_gmt to WP_Query args.
 *
 * @param array           $args    WP_Query args.
 * @param WP_REST_Request $request Request object.
 * @return array
 */
function adx_apply_modified_after_query( array $args, WP_REST_Request $request ) : array {
    if ( isset( $request['modified_after'] ) && ! isset( $request['after'] ) ) {
        $args['date_query'] = [ [
            'column'  => 'post_modified',
            'compare' => '>',
            'after'   => sanitize_text_field( $request['modified_after'] ),
        ] ];
    }

    if ( isset( $request['modified_after_gmt'] ) && ! isset( $request['after_gmt'] ) ) {
        $args['date_query'] = [ [
            'column'  => 'post_modified_gmt',
            'compare' => '>',
            'after'   => sanitize_text_field( $request['modified_after_gmt'] ),
        ] ];
    }

    return $args;
}