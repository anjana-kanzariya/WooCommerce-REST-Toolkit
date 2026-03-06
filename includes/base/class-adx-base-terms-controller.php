<?php
/**
 * ADX_REST_Base_Terms_Controller
 *
 * Abstract base class for custom taxonomy REST controllers.
 * Extend this class to register your own taxonomy endpoints.
 *
 * Usage:
 *   class My_Terms_Controller extends ADX_REST_Base_Terms_Controller {
 *       protected $taxonomy = 'my_taxonomy';
 *       protected function get_image_fields() { return ['my_acf_field' => 'response_key']; }
 *   }
 *
 * @package WooCommerce_REST_Toolkit
 */

defined( 'ABSPATH' ) || exit;

abstract class ADX_REST_Base_Terms_Controller extends WC_REST_Terms_Controller {

    /**
     * API namespace.
     *
     * @var string
     */
    protected $namespace = 'wc/v3';

    /**
     * REST route base.
     *
     * @var string
     */
    protected $rest_base = '';

    /**
     * Constructor.
     *
     * @param string $namespace API namespace e.g. 'wc/v3'.
     * @param string $rest_base Route base e.g. 'products/my-taxonomy'.
     */
    public function __construct( string $namespace = 'wc/v3', string $rest_base = '' ) {
        $this->namespace = $namespace;
        $this->rest_base = $rest_base ?: $this->taxonomy;
    }

    // -------------------------------------------------------------------------
    // Overridable methods — implement in child classes
    // -------------------------------------------------------------------------

    /**
     * ACF image fields to include in response.
     * Map ACF field name => response key.
     *
     * Example:
     *   return ['featured_image' => 'image', 'ad_image' => 'ad_image'];
     *
     * @return array
     */
    protected function get_image_fields() : array {
        return [];
    }

    /**
     * ACF non-image fields to include in response.
     * Map ACF field name => response key.
     *
     * Example:
     *   return ['brand_logo' => 'logo'];
     *
     * @return array
     */
    protected function get_acf_fields() : array {
        return [];
    }

    /**
     * ACF image fields for write operations (update).
     * Map request key => ACF field name.
     *
     * Example:
     *   return ['image' => 'featured_image'];
     *
     * @return array
     */
    protected function get_writable_image_fields() : array {
        return [];
    }

    /**
     * ACF non-image fields for write operations (update).
     * Map request key => ACF field name.
     *
     * Example:
     *   return ['logo' => 'brand_logo'];
     *
     * @return array
     */
    protected function get_writable_acf_fields() : array {
        return [];
    }

    /**
     * Extra data to merge into the response.
     * Override in child class for custom logic beyond ACF fields.
     *
     * @param WP_Term         $item    Term object.
     * @param WP_REST_Request $request Request object.
     * @return array
     */
    protected function get_extra_fields( WP_Term $item, WP_REST_Request $request ) : array {
        return [];
    }

    /**
     * Extra schema properties for the item schema.
     * Override when using get_extra_fields().
     *
     * @return array
     */
    protected function get_extra_schema_properties() : array {
        return [];
    }

    // -------------------------------------------------------------------------
    // Core response building
    // -------------------------------------------------------------------------

    /**
     * Prepare item for response.
     *
     * @param WP_Term         $item    Term object.
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function prepare_item_for_response( $item, $request ) : WP_REST_Response {

        $term_id = (int) $item->term_id;
        $acf_key = $this->taxonomy . '_' . $term_id;

        $data = [
            'id'          => $term_id,
            'name'        => $item->name,
            'slug'        => $item->slug,
            'parent'      => (int) $item->parent,
            'description' => $item->description,
            'count'       => (int) $item->count,
        ];

        // ACF image fields
        foreach ( $this->get_image_fields() as $acf_field => $response_key ) {
            $data[ $response_key ] = $this->build_image_response( $acf_field, $acf_key );
        }

        // ACF non-image fields
        foreach ( $this->get_acf_fields() as $acf_field => $response_key ) {
            $data[ $response_key ] = function_exists( 'get_field' )
                ? get_field( $acf_field, $acf_key, false )
                : null;
        }

        // Extra fields from child class
        $extra = $this->get_extra_fields( $item, $request );
        if ( ! empty( $extra ) ) {
            $data = array_merge( $data, $extra );
        }

        $context = $request['context'] ?? 'view';
        $data    = $this->add_additional_fields_to_object( $data, $request );
        $data    = $this->filter_response_by_context( $data, $context );

        $response = rest_ensure_response( $data );
        $response->add_links( $this->prepare_links( $item, $request ) );

        return apply_filters( "adx_rest_prepare_{$this->taxonomy}", $response, $item, $request );
    }

    // -------------------------------------------------------------------------
    // Write handling
    // -------------------------------------------------------------------------

    /**
     * Update term meta fields.
     * Handles ACF image and non-image fields automatically.
     * Override to add custom write logic.
     *
     * @param WP_Term         $term    Term object.
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error
     */
    protected function update_term_meta_fields( $term, $request ) {
        $id      = (int) $term->term_id;
        $acf_key = $this->taxonomy . '_' . $id;

        if ( ! function_exists( 'update_field' ) ) {
            return true;
        }

        // Non-image ACF fields
        foreach ( $this->get_writable_acf_fields() as $req_key => $acf_field ) {
            if ( isset( $request[ $req_key ] ) ) {
                update_field( $acf_field, $request[ $req_key ], $acf_key );
            }
        }

        // Image ACF fields
        foreach ( $this->get_writable_image_fields() as $req_key => $acf_field ) {
            if ( ! isset( $request[ $req_key ] ) ) {
                continue;
            }

            $result = $this->handle_image_field_update( $acf_field, $acf_key, $request[ $req_key ] );

            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Image helpers
    // -------------------------------------------------------------------------

    /**
     * Build image response array for an ACF image field.
     *
     * @param string $acf_field ACF field name.
     * @param string $acf_key   ACF post key (taxonomy_termid).
     * @return array|null
     */
    protected function build_image_response( string $acf_field, string $acf_key ) : ?array {
        if ( ! function_exists( 'get_field' ) ) {
            return null;
        }

        $image_id = get_field( $acf_field, $acf_key, false );

        if ( ! $image_id ) {
            return null;
        }

        $attachment = get_post( (int) $image_id );

        if ( ! $attachment ) {
            return null;
        }

        return [
            'id'                => (int) $image_id,
            'date_created'      => wc_rest_prepare_date_response( $attachment->post_date ),
            'date_created_gmt'  => wc_rest_prepare_date_response( $attachment->post_date_gmt ),
            'date_modified'     => wc_rest_prepare_date_response( $attachment->post_modified ),
            'date_modified_gmt' => wc_rest_prepare_date_response( $attachment->post_modified_gmt ),
            'src'               => wp_get_attachment_url( (int) $image_id ),
            'title'             => get_the_title( $attachment ),
            'alt'               => (string) get_post_meta( (int) $image_id, '_wp_attachment_image_alt', true ),
        ];
    }

    /**
     * Handle uploading or assigning an image to an ACF field.
     *
     * @param string $acf_field ACF field name.
     * @param string $acf_key   ACF post key.
     * @param array  $img       Request image data with 'id' or 'src'.
     * @return true|WP_Error
     */
    protected function handle_image_field_update( string $acf_field, string $acf_key, array $img ) {
        $image_id = 0;

        if ( empty( $img['id'] ) && ! empty( $img['src'] ) ) {
            $upload = wc_rest_upload_image_from_url( esc_url_raw( $img['src'] ) );

            if ( is_wp_error( $upload ) ) {
                return $upload;
            }

            $image_id = wc_rest_set_uploaded_image_as_attachment( $upload );
        } else {
            $image_id = absint( $img['id'] ?? 0 );
        }

        if ( $image_id && wp_attachment_is_image( $image_id ) ) {
            update_field( $acf_field, $image_id, $acf_key );

            if ( ! empty( $img['alt'] ) ) {
                update_post_meta( $image_id, '_wp_attachment_image_alt', wc_clean( $img['alt'] ) );
            }

            if ( ! empty( $img['title'] ) ) {
                wp_update_post( [
                    'ID'         => $image_id,
                    'post_title' => wc_clean( $img['title'] ),
                ] );
            }
        } else {
            delete_field( $acf_field, $acf_key );
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Schema
    // -------------------------------------------------------------------------

    /**
     * Image block schema definition. Use in get_extra_schema_properties().
     *
     * @param string $label Human-readable label prefix.
     * @return array
     */
    protected function image_schema_block( string $label ) : array {
        return [
            'description' => "{$label} image data.",
            'type'        => 'object',
            'context'     => [ 'view', 'edit' ],
            'properties'  => [
                'id'                => [ 'type' => 'integer', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
                'date_created'      => [ 'type' => 'date-time', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
                'date_created_gmt'  => [ 'type' => 'date-time', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
                'date_modified'     => [ 'type' => 'date-time', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
                'date_modified_gmt' => [ 'type' => 'date-time', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
                'src'               => [ 'type' => 'string', 'format' => 'uri', 'context' => [ 'view', 'edit' ] ],
                'title'             => [ 'type' => 'string', 'context' => [ 'view', 'edit' ] ],
                'alt'               => [ 'type' => 'string', 'context' => [ 'view', 'edit' ] ],
            ],
        ];
    }

    /**
     * Base item schema — includes standard term fields.
     * Merge get_extra_schema_properties() for child-specific fields.
     *
     * @return array
     */
    public function get_item_schema() : array {
        $schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => $this->taxonomy,
            'type'       => 'object',
            'properties' => array_merge(
                [
                    'id'          => [
                        'description' => __( 'Unique identifier for the resource.', 'woocommerce' ),
                        'type'        => 'integer',
                        'context'     => [ 'view', 'edit' ],
                        'readonly'    => true,
                    ],
                    'name'        => [
                        'description' => __( 'Term name.', 'woocommerce' ),
                        'type'        => 'string',
                        'context'     => [ 'view', 'edit' ],
                        'arg_options' => [ 'sanitize_callback' => 'sanitize_text_field' ],
                    ],
                    'slug'        => [
                        'description' => __( 'Term slug.', 'woocommerce' ),
                        'type'        => 'string',
                        'context'     => [ 'view', 'edit' ],
                        'arg_options' => [ 'sanitize_callback' => 'sanitize_title' ],
                    ],
                    'parent'      => [
                        'description' => __( 'Parent term ID.', 'woocommerce' ),
                        'type'        => 'integer',
                        'context'     => [ 'view', 'edit' ],
                    ],
                    'description' => [
                        'description' => __( 'Term description.', 'woocommerce' ),
                        'type'        => 'string',
                        'context'     => [ 'view', 'edit' ],
                        'arg_options' => [ 'sanitize_callback' => 'wp_filter_post_kses' ],
                    ],
                    'count'       => [
                        'description' => __( 'Number of published products.', 'woocommerce' ),
                        'type'        => 'integer',
                        'context'     => [ 'view', 'edit' ],
                        'readonly'    => true,
                    ],
                ],
                $this->get_extra_schema_properties()
            ),
        ];

        return $this->add_additional_fields_schema( $schema );
    }
}