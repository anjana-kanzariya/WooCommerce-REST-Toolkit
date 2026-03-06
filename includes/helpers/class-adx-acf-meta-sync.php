<?php
/**
 * ADX_ACF_Meta_Sync
 *
 * Handles syncing ACF field keys when meta is saved via the REST API.
 * WooCommerce REST API sends raw meta values — ACF needs its hidden `_field_name`
 * key records to be present so it recognises the field. This class handles that.
 *
 * Usage:
 *   ADX_ACF_Meta_Sync::sync( 'product', $product_object, $request );
 *
 * @package WooCommerce_REST_Toolkit
 */

defined( 'ABSPATH' ) || exit;

class ADX_ACF_Meta_Sync {

    /**
     * Sync ACF field key records for a given post type after REST meta update.
     *
     * @param string          $post_type Post type slug e.g. 'product'.
     * @param WC_Data|WP_Post $object    WooCommerce data object or WP_Post.
     * @param WP_REST_Request $request   REST request object.
     * @return WC_Data|WP_Post The original object, unmodified (for filter chaining).
     */
    public static function sync( string $post_type, $object, WP_REST_Request $request ) {
        if ( ! isset( $request['meta_data'] ) || ! is_array( $request['meta_data'] ) ) {
            return $object;
        }

        $post_id = isset( $request['id'] ) ? (int) $request['id'] : 0;

        if ( ! $post_id ) {
            return $object;
        }

        foreach ( $request['meta_data'] as $meta ) {
            if ( empty( $meta['key'] ) ) {
                continue;
            }

            $meta_key   = sanitize_key( $meta['key'] );
            $meta_value = $meta['value'] ?? '';

            // Save the raw meta value
            update_post_meta( $post_id, $meta_key, $meta_value );

            // Find the ACF field definition for this meta key
            $acf_field = self::find_acf_field( $post_type, $meta_key );

            if ( ! $acf_field ) {
                continue;
            }

            if ( $acf_field['type'] === 'repeater' ) {
                self::sync_repeater_field( $post_id, $acf_field, $meta_value, $request['meta_data'] );
            } else {
                // Register ACF's hidden key reference
                update_post_meta( $post_id, '_' . $acf_field['name'], $acf_field['key'] );
            }
        }

        return $object;
    }

    /**
     * Find an ACF field definition by meta key for a given post type.
     * Uses $wpdb->prepare() — no raw interpolation.
     *
     * @param string $post_type Post type slug.
     * @param string $meta_key  Meta key to look up.
     * @return array|null ACF field array or null if not found.
     */
    private static function find_acf_field( string $post_type, string $meta_key ) : ?array {
        global $wpdb;

        $post_type_length = strlen( $post_type );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT mt1.meta_key, mt1.meta_value
                FROM {$wpdb->posts}
                INNER JOIN {$wpdb->postmeta}
                    ON ( {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id )
                INNER JOIN {$wpdb->postmeta} AS mt1
                    ON ( {$wpdb->posts}.ID = mt1.post_id )
                WHERE 1=1
                    AND (
                        {$wpdb->postmeta}.meta_key = 'rule'
                        AND {$wpdb->postmeta}.meta_value LIKE %s
                    )
                    AND mt1.meta_value LIKE %s
                    AND {$wpdb->posts}.post_type = 'acf'
                    AND {$wpdb->posts}.post_status = 'publish'
                GROUP BY {$wpdb->posts}.ID
                ORDER BY {$wpdb->posts}.post_date DESC
                LIMIT 1",
                '%s:9:"post_type";s:' . $post_type_length . ':"' . $wpdb->esc_like( $post_type ) . '";%',
                '%:"' . $wpdb->esc_like( $meta_key ) . '";%'
            )
        );

        if ( ! $row || ! function_exists( 'get_field_object' ) ) {
            return null;
        }

        $field = get_field_object( $row->meta_key );

        return is_array( $field ) ? $field : null;
    }

    /**
     * Sync ACF repeater field key references.
     *
     * @param int    $post_id    Post ID.
     * @param array  $field      ACF field definition.
     * @param mixed  $meta_value Meta value (row count for repeater).
     * @param array  $meta_data  Full meta_data array from request.
     */
    private static function sync_repeater_field( int $post_id, array $field, $meta_value, array $meta_data ) : void {
        // Only register repeater key reference if there are rows
        if ( (int) $meta_value <= 0 ) {
            return;
        }

        update_post_meta( $post_id, '_' . $field['name'], $field['key'] );

        $sub_fields = $field['sub_fields'] ?? [];
        $field_keys = array_column( $sub_fields, 'name' );
        $prefix     = $field['name'] . '_';

        foreach ( $meta_data as $sub_meta ) {
            $sub_key = $sub_meta['key'] ?? '';

            // Skip the parent field itself
            if ( $sub_key === $field['name'] ) {
                continue;
            }

            // Must start with parent field name prefix e.g. "repeater_0_subfield"
            if ( strpos( $sub_key, $prefix ) !== 0 ) {
                continue;
            }

            // Parse key: field_name_rowindex_subfield_name
            $parts = explode( '_', $sub_key );

            if ( count( $parts ) < 4 ) {
                continue;
            }

            // Sub-field name is parts[2]_parts[3]
            $sub_field_name = $parts[2] . '_' . $parts[3];
            $index          = array_search( $sub_field_name, $field_keys, true );

            if ( false !== $index ) {
                update_post_meta( $post_id, '_' . $sub_key, $sub_fields[ $index ]['key'] );
            }
        }
    }
}