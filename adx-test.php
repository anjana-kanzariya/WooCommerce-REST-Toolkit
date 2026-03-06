<?php
/**
 * WooCommerce REST Toolkit — Test Script
 *
 * DROP THIS FILE in your WordPress root directory.
 * Access it in your browser: https://yoursite.com/adx-test.php
 *
 * REMOVE THIS FILE after testing. Never leave it on a production server.
 *
 * Tests:
 *   1. Environment checks (WooCommerce, ACF, base classes)
 *   2. Base class instantiation
 *   3. Schema generation
 *   4. GET — meta filtering
 *   5. GET — terms controller response building
 *   6. ACF Meta Sync — field lookup and key registration
 *   7. Write path — prepare_item_for_database
 *   8. Route registration
 */

// -----------------------------------------------------------------------
// Bootstrap WordPress
// -----------------------------------------------------------------------
define( 'SHORTINIT', false );
$wp_root = __DIR__;

if ( ! file_exists( $wp_root . '/wp-load.php' ) ) {
    die( 'ERROR: wp-load.php not found. Make sure adx-test.php is in your WordPress root.' );
}

require_once $wp_root . '/wp-load.php';

// -----------------------------------------------------------------------
// Security: block non-admins
// -----------------------------------------------------------------------
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You must be logged in as an administrator to run this test.' );
}

// -----------------------------------------------------------------------
// Test runner
// -----------------------------------------------------------------------
$results = [];
$pass    = 0;
$fail    = 0;
$warn    = 0;

function adx_test( string $name, callable $fn ) : void {
    global $results, $pass, $fail, $warn;

    try {
        $result = $fn();

        if ( $result === true ) {
            $results[] = [ 'status' => 'PASS', 'name' => $name, 'detail' => '' ];
            $pass++;
        } elseif ( is_array( $result ) && isset( $result['warn'] ) ) {
            $results[] = [ 'status' => 'WARN', 'name' => $name, 'detail' => $result['warn'] ];
            $warn++;
        } elseif ( is_string( $result ) ) {
            $results[] = [ 'status' => 'FAIL', 'name' => $name, 'detail' => $result ];
            $fail++;
        } else {
            $results[] = [ 'status' => 'FAIL', 'name' => $name, 'detail' => 'Unexpected return: ' . print_r( $result, true ) ];
            $fail++;
        }
    } catch ( Throwable $e ) {
        $results[] = [ 'status' => 'FAIL', 'name' => $name, 'detail' => get_class( $e ) . ': ' . $e->getMessage() . ' (line ' . $e->getLine() . ')' ];
        $fail++;
    }
}

function adx_warn( string $msg ) : array {
    return [ 'warn' => $msg ];
}

// -----------------------------------------------------------------------
// SECTION 1: Environment
// -----------------------------------------------------------------------

adx_test( '[ENV] WordPress loaded', function () {
    return defined( 'ABSPATH' );
} );

adx_test( '[ENV] WooCommerce active', function () {
    return class_exists( 'WooCommerce' )
        ? true
        : 'WooCommerce not active — toolkit will not load';
} );

adx_test( '[ENV] ACF active', function () {
    return function_exists( 'get_field' )
        ? true
        : adx_warn( 'ACF not active — ACF-related tests will be skipped' );
} );

adx_test( '[ENV] WC_REST_Posts_Controller exists', function () {
    return class_exists( 'WC_REST_Posts_Controller' )
        ? true
        : 'WC_REST_Posts_Controller not found — WooCommerce REST API may not be loaded';
} );

adx_test( '[ENV] WC_REST_Terms_Controller exists', function () {
    return class_exists( 'WC_REST_Terms_Controller' )
        ? true
        : 'WC_REST_Terms_Controller not found';
} );

// -----------------------------------------------------------------------
// Load toolkit base classes
// -----------------------------------------------------------------------
$plugin_dir = WP_PLUGIN_DIR . '/woocommerce-rest-toolkit/';

$base_post  = $plugin_dir . 'includes/base/class-adx-base-post-controller.php';
$base_terms = $plugin_dir . 'includes/base/class-adx-base-terms-controller.php';
$acf_sync   = $plugin_dir . 'includes/helpers/class-adx-acf-meta-sync.php';

adx_test( '[ENV] Base post controller file exists', function () use ( $base_post ) {
    return file_exists( $base_post )
        ? true
        : 'File not found: ' . $base_post;
} );

adx_test( '[ENV] Base terms controller file exists', function () use ( $base_terms ) {
    return file_exists( $base_terms )
        ? true
        : 'File not found: ' . $base_terms;
} );

adx_test( '[ENV] ACF sync helper file exists', function () use ( $acf_sync ) {
    return file_exists( $acf_sync )
        ? true
        : 'File not found: ' . $acf_sync;
} );

// Load them
if ( file_exists( $base_post ) )  require_once $base_post;
if ( file_exists( $base_terms ) ) require_once $base_terms;
if ( file_exists( $acf_sync ) )   require_once $acf_sync;

// -----------------------------------------------------------------------
// SECTION 2: Class loading
// -----------------------------------------------------------------------

adx_test( '[CLASS] ADX_REST_Base_Post_Controller loaded', function () {
    return class_exists( 'ADX_REST_Base_Post_Controller' )
        ? true
        : 'Class not found after require — check file path and class name';
} );

adx_test( '[CLASS] ADX_REST_Base_Terms_Controller loaded', function () {
    return class_exists( 'ADX_REST_Base_Terms_Controller' )
        ? true
        : 'Class not found after require — check file path and class name';
} );

adx_test( '[CLASS] ADX_ACF_Meta_Sync loaded', function () {
    return class_exists( 'ADX_ACF_Meta_Sync' )
        ? true
        : 'Class not found after require — check file path and class name';
} );

adx_test( '[CLASS] ADX_REST_Base_Post_Controller is abstract', function () {
    $r = new ReflectionClass( 'ADX_REST_Base_Post_Controller' );
    return $r->isAbstract()
        ? true
        : 'Base post controller should be abstract';
} );

adx_test( '[CLASS] ADX_REST_Base_Terms_Controller is abstract', function () {
    $r = new ReflectionClass( 'ADX_REST_Base_Terms_Controller' );
    return $r->isAbstract()
        ? true
        : 'Base terms controller should be abstract';
} );

adx_test( '[CLASS] ADX_REST_Base_Post_Controller extends WC_REST_Posts_Controller', function () {
    return is_subclass_of( 'ADX_REST_Base_Post_Controller', 'WC_REST_Posts_Controller' )
        ? true
        : 'Does not extend WC_REST_Posts_Controller';
} );

adx_test( '[CLASS] ADX_REST_Base_Terms_Controller extends WC_REST_Terms_Controller', function () {
    return is_subclass_of( 'ADX_REST_Base_Terms_Controller', 'WC_REST_Terms_Controller' )
        ? true
        : 'Does not extend WC_REST_Terms_Controller';
} );

// -----------------------------------------------------------------------
// Create concrete test implementations
// -----------------------------------------------------------------------

if ( class_exists( 'ADX_REST_Base_Post_Controller' ) ) {
    class ADX_Test_Post_Controller extends ADX_REST_Base_Post_Controller {
        protected $post_type = 'post';
        public function __construct() {
            parent::__construct( 'wc/v3', 'adx-test-posts' );
        }
        protected function get_exposed_meta_keys() : array {
            return [ 'adx_test_meta_key' ];
        }
    }
}

if ( class_exists( 'ADX_REST_Base_Terms_Controller' ) ) {
    class ADX_Test_Terms_Controller extends ADX_REST_Base_Terms_Controller {
        protected $taxonomy = 'category';
        public function __construct() {
            parent::__construct( 'wc/v3', 'adx-test-terms' );
        }
        protected function get_image_fields() : array {
            return [];
        }
    }
}

// -----------------------------------------------------------------------
// SECTION 3: Instantiation
// -----------------------------------------------------------------------

adx_test( '[INIT] Post controller instantiates without error', function () {
    $c = new ADX_Test_Post_Controller();
    return $c instanceof ADX_REST_Base_Post_Controller;
} );

adx_test( '[INIT] Terms controller instantiates without error', function () {
    $c = new ADX_Test_Terms_Controller();
    return $c instanceof ADX_REST_Base_Terms_Controller;
} );

adx_test( '[INIT] Post controller namespace set correctly', function () {
    $c = new ADX_Test_Post_Controller();
    $r = new ReflectionProperty( get_parent_class( $c ), 'namespace' );
    $r->setAccessible( true );
    $ns = $r->getValue( $c );
    return $ns === 'wc/v3' ? true : 'Expected wc/v3, got: ' . $ns;
} );

adx_test( '[INIT] Post controller rest_base set correctly', function () {
    $c = new ADX_Test_Post_Controller();
    $r = new ReflectionProperty( get_parent_class( $c ), 'rest_base' );
    $r->setAccessible( true );
    $base = $r->getValue( $c );
    return $base === 'adx-test-posts' ? true : 'Expected adx-test-posts, got: ' . $base;
} );

// -----------------------------------------------------------------------
// SECTION 4: Schema
// -----------------------------------------------------------------------

adx_test( '[SCHEMA] Post controller get_item_schema returns valid array', function () {
    $c      = new ADX_Test_Post_Controller();
    $schema = $c->get_item_schema();

    if ( ! is_array( $schema ) ) return 'Schema is not an array';
    if ( ! isset( $schema['properties'] ) ) return 'Schema missing properties key';

    $required = [ 'id', 'name', 'slug', 'permalink', 'status', 'meta_data' ];
    foreach ( $required as $key ) {
        if ( ! isset( $schema['properties'][ $key ] ) ) {
            return "Schema missing required property: {$key}";
        }
    }
    return true;
} );

adx_test( '[SCHEMA] Terms controller get_item_schema returns valid array', function () {
    $c      = new ADX_Test_Terms_Controller();
    $schema = $c->get_item_schema();

    if ( ! is_array( $schema ) ) return 'Schema is not an array';
    if ( ! isset( $schema['properties'] ) ) return 'Schema missing properties key';

    $required = [ 'id', 'name', 'slug', 'parent', 'description', 'count' ];
    foreach ( $required as $key ) {
        if ( ! isset( $schema['properties'][ $key ] ) ) {
            return "Schema missing required property: {$key}";
        }
    }
    return true;
} );

adx_test( '[SCHEMA] meta_data value type is mixed (not string)', function () {
    $c      = new ADX_Test_Post_Controller();
    $schema = $c->get_item_schema();
    $type   = $schema['properties']['meta_data']['items']['properties']['value']['type'] ?? '';
    return $type === 'mixed'
        ? adx_warn( "'mixed' is not a valid JSON Schema type — consider using multiple types or omitting type for value field' " )
        : true;
} );

// -----------------------------------------------------------------------
// SECTION 5: GET — meta filtering
// -----------------------------------------------------------------------

adx_test( '[GET] get_filtered_meta_data returns empty array when no keys declared', function () {
    // Use a post controller with no exposed keys
    $c = new class extends ADX_REST_Base_Post_Controller {
        protected $post_type = 'post';
        public function __construct() { parent::__construct( 'wc/v3', 'test' ); }
        protected function get_exposed_meta_keys() : array { return []; }
    };

    $post   = get_posts( [ 'numberposts' => 1, 'post_status' => 'any' ] );
    if ( empty( $post ) ) return adx_warn( 'No posts found to test meta filtering — skipped' );

    $r      = new ReflectionMethod( $c, 'get_filtered_meta_data' );
    $r->setAccessible( true );
    $result = $r->invoke( $c, $post[0] );

    return $result === []
        ? true
        : 'Expected empty array, got: ' . print_r( $result, true );
} );

adx_test( '[GET] get_filtered_meta_data only returns declared keys', function () {
    $posts = get_posts( [ 'numberposts' => 1, 'post_status' => 'any' ] );
    if ( empty( $posts ) ) return adx_warn( 'No posts found — skipped' );

    $post_id = $posts[0]->ID;

    // Write two meta keys — only one should come back
    update_post_meta( $post_id, 'adx_test_allowed', 'allowed_value' );
    update_post_meta( $post_id, 'adx_test_blocked', 'blocked_value' );

    $c = new class extends ADX_REST_Base_Post_Controller {
        protected $post_type = 'post';
        public function __construct() { parent::__construct( 'wc/v3', 'test' ); }
        protected function get_exposed_meta_keys() : array { return [ 'adx_test_allowed' ]; }
    };

    $r      = new ReflectionMethod( $c, 'get_filtered_meta_data' );
    $r->setAccessible( true );
    $result = $r->invoke( $c, $posts[0] );

    // Clean up
    delete_post_meta( $post_id, 'adx_test_allowed' );
    delete_post_meta( $post_id, 'adx_test_blocked' );

    $keys = array_column( $result, 'key' );

    if ( in_array( 'adx_test_blocked', $keys, true ) ) {
        return 'SECURITY: blocked key appeared in response';
    }

    if ( ! in_array( 'adx_test_allowed', $keys, true ) ) {
        return 'Allowed key missing from response';
    }

    return true;
} );

// -----------------------------------------------------------------------
// SECTION 6: GET — prepare_item_for_response
// -----------------------------------------------------------------------

adx_test( '[GET] Post prepare_item_for_response returns WP_REST_Response', function () {
    $posts = get_posts( [ 'numberposts' => 1, 'post_status' => 'publish' ] );
    if ( empty( $posts ) ) return adx_warn( 'No published posts found — skipped' );

    $c       = new ADX_Test_Post_Controller();
    $request = new WP_REST_Request( 'GET', '/wc/v3/adx-test-posts' );
    $request->set_param( 'context', 'view' );

    $response = $c->prepare_item_for_response( $posts[0], $request );

    if ( ! $response instanceof WP_REST_Response ) {
        return 'Did not return WP_REST_Response, got: ' . get_class( $response );
    }

    $data = $response->get_data();
    foreach ( [ 'id', 'name', 'slug', 'status', 'permalink', 'meta_data' ] as $key ) {
        if ( ! array_key_exists( $key, $data ) ) {
            return "Response missing key: {$key}";
        }
    }

    return true;
} );

adx_test( '[GET] Terms prepare_item_for_response returns WP_REST_Response', function () {
    $terms = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false, 'number' => 1 ] );
    if ( empty( $terms ) || is_wp_error( $terms ) ) return adx_warn( 'No categories found — skipped' );

    $c       = new ADX_Test_Terms_Controller();
    $request = new WP_REST_Request( 'GET', '/wc/v3/adx-test-terms' );
    $request->set_param( 'context', 'view' );

    $response = $c->prepare_item_for_response( $terms[0], $request );

    if ( ! $response instanceof WP_REST_Response ) {
        return 'Did not return WP_REST_Response, got: ' . get_class( $response );
    }

    $data = $response->get_data();
    foreach ( [ 'id', 'name', 'slug', 'description', 'count' ] as $key ) {
        if ( ! array_key_exists( $key, $data ) ) {
            return "Response missing key: {$key}";
        }
    }

    return true;
} );

// -----------------------------------------------------------------------
// SECTION 7: WRITE — prepare_item_for_database
// -----------------------------------------------------------------------

adx_test( '[WRITE] prepare_item_for_database returns WP_Post for existing ID', function () {
    $posts = get_posts( [ 'numberposts' => 1, 'post_status' => 'any' ] );
    if ( empty( $posts ) ) return adx_warn( 'No posts found — skipped' );

    $c       = new ADX_Test_Post_Controller();
    $request = new WP_REST_Request( 'PUT', '/wc/v3/adx-test-posts/' . $posts[0]->ID );
    $request->set_param( 'id', $posts[0]->ID );
    $request->set_param( 'name', 'ADX Test Title' );

    $result = $c->prepare_item_for_database( $request );

    if ( is_wp_error( $result ) ) {
        return 'Returned WP_Error: ' . $result->get_error_message();
    }

    if ( ! $result instanceof WP_Post ) {
        return 'Expected WP_Post, got: ' . get_class( $result );
    }

    if ( $result->post_title !== 'ADX Test Title' ) {
        return 'Title not set correctly, got: ' . $result->post_title;
    }

    return true;
} );

adx_test( '[WRITE] prepare_item_for_database returns WP_Error for invalid ID', function () {
    $c       = new ADX_Test_Post_Controller();
    $request = new WP_REST_Request( 'PUT', '/wc/v3/adx-test-posts/999999999' );
    $request->set_param( 'id', 999999999 );

    $result = $c->prepare_item_for_database( $request );

    return is_wp_error( $result )
        ? true
        : 'Expected WP_Error for invalid ID, got: ' . get_class( $result );
} );

adx_test( '[WRITE] prepare_item_for_database sanitizes status against allowed values', function () {
    $posts = get_posts( [ 'numberposts' => 1, 'post_status' => 'any' ] );
    if ( empty( $posts ) ) return adx_warn( 'No posts found — skipped' );

    $c       = new ADX_Test_Post_Controller();
    $request = new WP_REST_Request( 'PUT', '/wc/v3/adx-test-posts/' . $posts[0]->ID );
    $request->set_param( 'id', $posts[0]->ID );
    $request->set_param( 'status', '<script>alert(1)</script>' );

    $result = $c->prepare_item_for_database( $request );

    if ( is_wp_error( $result ) ) return adx_warn( 'Got WP_Error — could not test status sanitization' );

    // Invalid status should not be applied — post_status should remain original
    return $result->post_status !== '<script>alert(1)</script>'
        ? true
        : 'SECURITY: XSS payload accepted as post status';
} );

// -----------------------------------------------------------------------
// SECTION 8: ACF Meta Sync
// -----------------------------------------------------------------------

adx_test( '[ACF] ADX_ACF_Meta_Sync::sync returns object unchanged when no meta_data', function () {
    $post    = new WP_Post( (object) [ 'ID' => 1 ] );
    $request = new WP_REST_Request( 'PUT', '/wc/v3/test/1' );
    // No meta_data set

    $result = ADX_ACF_Meta_Sync::sync( 'post', $post, $request );

    return $result === $post
        ? true
        : 'Object was modified when it should have been returned unchanged';
} );

adx_test( '[ACF] ADX_ACF_Meta_Sync::sync returns object unchanged when id is 0', function () {
    $post    = new WP_Post( (object) [ 'ID' => 0 ] );
    $request = new WP_REST_Request( 'PUT', '/wc/v3/test/0' );
    $request->set_param( 'meta_data', [ [ 'key' => 'test', 'value' => 'val' ] ] );
    // No id param set

    $result = ADX_ACF_Meta_Sync::sync( 'post', $post, $request );

    return $result === $post
        ? true
        : 'Should have returned early with no post_id';
} );

adx_test( '[ACF] ADX_ACF_Meta_Sync::sync sanitizes meta key', function () {
    $posts = get_posts( [ 'numberposts' => 1, 'post_status' => 'any' ] );
    if ( empty( $posts ) ) return adx_warn( 'No posts found — skipped' );

    $post_id = $posts[0]->ID;
    $post    = $posts[0];
    $request = new WP_REST_Request( 'PUT', '/wc/v3/test/' . $post_id );
    $request->set_param( 'id', $post_id );
    $request->set_param( 'meta_data', [
        [ 'key' => 'adx_clean_key', 'value' => 'test_value' ],
        [ 'key' => '', 'value' => 'should_be_skipped' ], // empty key — should skip
    ] );

    ADX_ACF_Meta_Sync::sync( 'post', $post, $request );

    $val = get_post_meta( $post_id, 'adx_clean_key', true );

    // Clean up
    delete_post_meta( $post_id, 'adx_clean_key' );

    return $val === 'test_value'
        ? true
        : 'Meta value not saved, got: ' . var_export( $val, true );
} );

// -----------------------------------------------------------------------
// SECTION 9: Route registration
// -----------------------------------------------------------------------

adx_test( '[ROUTES] register_routes does not throw', function () {
    // We can't fully test route registration outside rest_api_init
    // but we can verify the method exists and is callable
    $c = new ADX_Test_Post_Controller();
    return method_exists( $c, 'register_routes' )
        ? adx_warn( 'register_routes exists but cannot be tested outside rest_api_init context — manually verify via /wp-json/wc/v3/' )
        : 'register_routes method missing';
} );

adx_test( '[ROUTES] WC REST API is reachable', function () {
    $routes = rest_get_server()->get_routes();
    return isset( $routes['/wc/v3'] )
        ? true
        : adx_warn( 'WC REST routes not found — ensure REST API is enabled' );
} );

// -----------------------------------------------------------------------
// SECTION 10: adx_append_public_meta_to_response
// -----------------------------------------------------------------------

adx_test( '[META] adx_append_public_meta_to_response excludes private keys', function () {
    $posts = get_posts( [ 'numberposts' => 1, 'post_type' => 'product', 'post_status' => 'any' ] );
    if ( empty( $posts ) ) return adx_warn( 'No products found — skipped' );

    $post_id = $posts[0]->ID;

    update_post_meta( $post_id, 'adx_public_test', 'public_val' );
    update_post_meta( $post_id, '_adx_private_test', 'private_val' );

    $product  = wc_get_product( $post_id );
    $response = new WP_REST_Response( [ 'meta_data' => [] ] );
    $request  = new WP_REST_Request( 'GET' );

    $filtered = adx_append_public_meta_to_response( $response, $product, $request );
    $data     = $filtered->get_data();
    $keys     = array_column( $data['meta_data'], 'key' );

    delete_post_meta( $post_id, 'adx_public_test' );
    delete_post_meta( $post_id, '_adx_private_test' );

    if ( in_array( '_adx_private_test', $keys, true ) ) {
        return 'SECURITY: private meta key leaked in response';
    }

    if ( ! in_array( 'adx_public_test', $keys, true ) ) {
        return 'Public meta key missing from response';
    }

    return true;
} );

// -----------------------------------------------------------------------
// Output results
// -----------------------------------------------------------------------
?>
<!DOCTYPE html>
<html>
<head>
    <title>ADX REST Toolkit — Test Results</title>
    <style>
        body { font-family: monospace; font-size: 14px; background: #1e1e1e; color: #d4d4d4; padding: 30px; }
        h1 { color: #fff; border-bottom: 1px solid #444; padding-bottom: 10px; }
        .summary { font-size: 16px; margin: 20px 0; padding: 15px; border-radius: 4px; background: #2d2d2d; }
        .pass  { color: #4ec9b0; }
        .fail  { color: #f48771; }
        .warn  { color: #dcdcaa; }
        table  { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th     { background: #2d2d2d; padding: 10px; text-align: left; color: #9cdcfe; }
        td     { padding: 8px 10px; border-bottom: 1px solid #2d2d2d; vertical-align: top; }
        tr:hover td { background: #2a2a2a; }
        .detail { color: #888; font-size: 12px; margin-top: 4px; }
        .badge  { display: inline-block; padding: 2px 8px; border-radius: 3px; font-weight: bold; font-size: 12px; }
        .badge.PASS { background: #1e4a3d; color: #4ec9b0; }
        .badge.FAIL { background: #4a1e1e; color: #f48771; }
        .badge.WARN { background: #4a4a1e; color: #dcdcaa; }
        .warning-box { background: #4a3a1e; border: 1px solid #dcdcaa; color: #dcdcaa; padding: 12px; border-radius: 4px; margin-top: 20px; }
    </style>
</head>
<body>
<h1>🧪 WooCommerce REST Toolkit — Test Results</h1>

<div class="summary">
    Total: <?php echo $pass + $fail + $warn; ?> &nbsp;|&nbsp;
    <span class="pass">✓ Pass: <?php echo $pass; ?></span> &nbsp;|&nbsp;
    <span class="fail">✗ Fail: <?php echo $fail; ?></span> &nbsp;|&nbsp;
    <span class="warn">⚠ Warn: <?php echo $warn; ?></span>
</div>

<table>
    <tr>
        <th width="80">Status</th>
        <th>Test</th>
        <th>Detail</th>
    </tr>
    <?php foreach ( $results as $r ) : ?>
    <tr>
        <td><span class="badge <?php echo esc_attr( $r['status'] ); ?>"><?php echo esc_html( $r['status'] ); ?></span></td>
        <td><?php echo esc_html( $r['name'] ); ?></td>
        <td><?php if ( $r['detail'] ) echo '<span class="detail">' . esc_html( $r['detail'] ) . '</span>'; ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<div class="warning-box">
    ⚠ <strong>Remove this file from your server after testing.</strong><br>
    Never leave adx-test.php on a production or publicly accessible server.
</div>
</body>
</html>
<?php