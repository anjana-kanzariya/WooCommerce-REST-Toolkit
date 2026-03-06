<?php
/**
 * ADX_REST_Base_Post_Controller
 *
 * Abstract base class for custom post type REST controllers.
 * Extend this class to register your own custom post type endpoints.
 *
 * Usage:
 *   class My_Controller extends ADX_REST_Base_Post_Controller {
 *       protected $post_type = 'my_post_type';
 *       protected function get_exposed_meta_keys() { return ['my_key']; }
 *   }
 *
 * @package WooCommerce_REST_Toolkit
 */

defined( 'ABSPATH' ) || exit;

abstract class ADX_REST_Base_Post_Controller extends WC_REST_Posts_Controller {

    /**
     * API namespace. Override in constructor or child class.
     *
     * @var string
     */
    protected $namespace = 'wc/v3';

    /**
     * REST route base. Defaults to post_type if not set.
     *
     * @var string
     */
    protected $rest_base = '';

    /**
     * Constructor.
     *
     * @param string $namespace API namespace e.g. 'wc/v3'.
     * @param string $rest_base Route base e.g. 'my-resource'.
     */
    public function __construct( string $namespace = 'wc/v3', string $rest_base = '' ) {
        $this->namespace = $namespace;
        $this->rest_base = $rest_base ?: $this->post_type;
    }

    // -------------------------------------------------------------------------
    // Overridable methods — implement in child classes
    // -------------------------------------------------------------------------

    /**
     * Define which meta keys are exposed in the response.
     * Override in child class to control meta exposure.
     *
     * @return string[]
     */
    protected function get_exposed_meta_keys() : array {
        return [];
    }

    /**
     * Define extra fields to include in the response beyond base fields.
     * Override in child class.
     *
     * Example:
     *   return [
     *       'my_field' => get_post_meta( $post->ID, 'my_field', true ),
     *   ];
     *
     * @param WP_Post         $post    Post object.
     * @param WP_REST_Request $request Request object.
     * @return array
     */
    protected function get_extra_fields( WP_Post $post, WP_REST_Request $request ) : array {
        return [];
    }

    /**
     * Return schema properties for extra fields.
     * Override when using get_extra_fields() so schema stays accurate.
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
     * @param WP_Post         $post    Post object.
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function prepare_item_for_response( $post, $request ) : WP_REST_Response {

        $data = [
            'id'        => (int) $post->ID,
            'name'      => $post->post_title,
            'slug'      => $post->post_name,
            'permalink' => get_permalink( $post ),
            'status'    => $post->post_status,
            'meta_data' => $this->get_filtered_meta_data( $post ),
        ];

        // Merge any extra fields defined in child class
        $extra = $this->get_extra_fields( $post, $request );
        if ( ! empty( $extra ) ) {
            $data = array_merge( $data, $extra );
        }

        $context = $request['context'] ?? 'view';
        $data    = $this->add_additional_fields_to_object( $data, $request );
        $data    = $this->filter_response_by_context( $data, $context );

        $response = rest_ensure_response( $data );

        return apply_filters( "adx_rest_prepare_{$this->post_type}", $response, $post, $request );
    }

    /**
     * Prepare item for database insert/update.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_Post|WP_Error
     */
    public function prepare_item_for_database( $request ) {
        $id   = (int) ( $request['id'] ?? 0 );
        $post = $id ? get_post( $id ) : new WP_Post( (object) [] );

        if ( ! $post ) {
            return new WP_Error(
                'adx_rest_invalid_id',
                __( 'Invalid post ID.', 'woocommerce' ),
                [ 'status' => 404 ]
            );
        }

        if ( isset( $request['name'] ) ) {
            $post->post_title = sanitize_text_field( $request['name'] );
        }

        if ( isset( $request['slug'] ) ) {
            $post->post_name = sanitize_title( $request['slug'] );
        }

        if ( isset( $request['status'] ) ) {
            $allowed = array_keys( get_post_statuses() );
            if ( in_array( $request['status'], $allowed, true ) ) {
                $post->post_status = $request['status'];
            }
        }

        if ( class_exists( 'ADX_ACF_Meta_Sync' ) ) {
            ADX_ACF_Meta_Sync::sync( $this->post_type, $post, $request );
        }

        return apply_filters( "adx_rest_pre_insert_{$this->post_type}", $post, $request );
    }

    // -------------------------------------------------------------------------
    // Meta handling
    // -------------------------------------------------------------------------

    /**
     * Get filtered meta data — only exposes keys declared in get_exposed_meta_keys().
     * Uses $wpdb->prepare() for all queries.
     *
     * @param WP_Post $post Post object.
     * @return array
     */
    protected function get_filtered_meta_data( WP_Post $post ) : array {
        global $wpdb;

        $allowed_keys = $this->get_exposed_meta_keys();

        if ( empty( $allowed_keys ) ) {
            return [];
        }

        $post_id      = (int) $post->ID;
        $placeholders = implode( ', ', array_fill( 0, count( $allowed_keys ), '%s' ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query   = $wpdb->prepare(
            "SELECT meta_key, meta_value, meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ({$placeholders})",
            array_merge( [ $post_id ], $allowed_keys )
        );
        $results = $wpdb->get_results( $query );

        if ( empty( $results ) ) {
            return [];
        }

        $data = [];
        foreach ( $results as $row ) {
            $data[] = [
                'id'    => (int) $row->meta_id,
                'key'   => $row->meta_key,
                'value' => maybe_unserialize( $row->meta_value ),
            ];
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // ACF integration
    // -------------------------------------------------------------------------

    /**
     * Get ACF fields for a post. Returns empty array if ACF not active.
     * Only call this if you need it — it fetches all fields.
     * For selective ACF fields, use get_field() directly in get_extra_fields().
     *
     * @param int $post_id Post ID.
     * @return array
     */
    protected function get_acf_fields( int $post_id ) : array {
        if ( ! function_exists( 'get_fields' ) ) {
            return [];
        }

        $fields = get_fields( $post_id );

        return is_array( $fields ) ? $fields : [];
    }

    // -------------------------------------------------------------------------
    // Schema
    // -------------------------------------------------------------------------

    /**
     * Get item schema.
     *
     * @return array
     */
    public function get_item_schema() : array {
        $schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => $this->post_type,
            'type'       => 'object',
            'properties' => array_merge(
                [
                    'id'        => [
                        'description' => __( 'Unique identifier for the resource.', 'woocommerce' ),
                        'type'        => 'integer',
                        'context'     => [ 'view', 'edit' ],
                        'readonly'    => true,
                    ],
                    'name'      => [
                        'description' => __( 'Post title.', 'woocommerce' ),
                        'type'        => 'string',
                        'context'     => [ 'view', 'edit' ],
                        'arg_options' => [ 'sanitize_callback' => 'sanitize_text_field' ],
                    ],
                    'slug'      => [
                        'description' => __( 'Post slug.', 'woocommerce' ),
                        'type'        => 'string',
                        'context'     => [ 'view', 'edit' ],
                        'arg_options' => [ 'sanitize_callback' => 'sanitize_title' ],
                    ],
                    'permalink' => [
                        'description' => __( 'Post URL.', 'woocommerce' ),
                        'type'        => 'string',
                        'format'      => 'uri',
                        'context'     => [ 'view', 'edit' ],
                        'readonly'    => true,
                    ],
                    'status'    => [
                        'description' => __( 'Post status.', 'woocommerce' ),
                        'type'        => 'string',
                        'context'     => [ 'view', 'edit' ],
                        'enum'        => array_keys( get_post_statuses() ),
                    ],
                    'meta_data' => [
                        'description' => __( 'Meta data.', 'woocommerce' ),
                        'type'        => 'array',
                        'context'     => [ 'view', 'edit' ],
                        'items'       => [
                            'type'       => 'object',
                            'properties' => [
                                'id'    => [
                                    'description' => __( 'Meta ID.', 'woocommerce' ),
                                    'type'        => 'integer',
                                    'context'     => [ 'view', 'edit' ],
                                    'readonly'    => true,
                                ],
                                'key'   => [
                                    'description' => __( 'Meta key.', 'woocommerce' ),
                                    'type'        => 'string',
                                    'context'     => [ 'view', 'edit' ],
                                ],
                                'value' => [
                                    'description' => __( 'Meta value.', 'woocommerce' ),
                                    'type'        => 'mixed',
                                    'context'     => [ 'view', 'edit' ],
                                ],
                            ],
                        ],
                    ],
                ],
                $this->get_extra_schema_properties()
            ),
        ];

        return $this->add_additional_fields_schema( $schema );
    }

    // -------------------------------------------------------------------------
    // Routes
    // -------------------------------------------------------------------------

    /**
     * Register REST routes.
     * Follows WooCommerce route conventions. Override to add extra routes.
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_items' ],
                    'permission_callback' => [ $this, 'get_items_permissions_check' ],
                    'args'                => $this->get_collection_params(),
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_item' ],
                    'permission_callback' => [ $this, 'create_item_permissions_check' ],
                    'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                'args' => [
                    'id' => [
                        'description' => __( 'Unique identifier for the resource.', 'woocommerce' ),
                        'type'        => 'integer',
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_item' ],
                    'permission_callback' => [ $this, 'get_item_permissions_check' ],
                    'args'                => [ 'context' => $this->get_context_param( [ 'default' => 'view' ] ) ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_item' ],
                    'permission_callback' => [ $this, 'update_item_permissions_check' ],
                    'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_item' ],
                    'permission_callback' => [ $this, 'delete_item_permissions_check' ],
                    'args'                => [
                        'force' => [
                            'default'     => false,
                            'description' => __( 'Whether to bypass trash and force deletion.', 'woocommerce' ),
                            'type'        => 'boolean',
                        ],
                    ],
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );
    }
}