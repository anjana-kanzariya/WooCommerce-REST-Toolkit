# WooCommerce REST Toolkit

A lightweight, extensible base layer for the WooCommerce REST API.

Provides reusable abstract controllers for custom post types and taxonomies — with built-in ACF integration, controlled meta exposure, and clean JSON schema support.

---

## Why?

WooCommerce's REST controllers are powerful but require repetitive boilerplate when exposing custom post types, taxonomies, ACF fields, or selective meta. This toolkit abstracts that into clean base classes you extend once and forget.

---

## Features

- Abstract base controller for custom post types (`ADX_REST_Base_Post_Controller`)
- Abstract base controller for custom taxonomies (`ADX_REST_Base_Terms_Controller`)
- Controlled meta exposure — you declare exactly which keys are exposed, nothing leaks
- ACF image and non-image field support with read + write handling
- `ADX_ACF_Meta_Sync` helper for syncing ACF field key references via REST writes
- `modified_after` / `modified_after_gmt` query params for products and orders
- Public meta appended to product/order responses (private `_` keys excluded)
- v2 + v3 namespace support out of the box
- Zero coupling — base classes have no dependencies beyond WooCommerce

---

## Requirements

- PHP 7.4+
- WordPress 5.6+
- WooCommerce 3.5+
- ACF (optional — gracefully skipped if not active)

---

## Installation

### Option 1 — Install as a Plugin

1. Install and activate WooCommerce
2. Upload and activate this plugin
3. Extend `ADX_REST_Base_Post_Controller` or `ADX_REST_Base_Terms_Controller`
4. Register your routes on `rest_api_init`

### Option 2 — Embed in an Existing Plugin or Theme

Copy only the files you need:

```
includes/base/class-adx-base-post-controller.php
includes/base/class-adx-base-terms-controller.php
includes/helpers/class-adx-acf-meta-sync.php   ← optional, ACF only
```

Load them manually:

```php
require_once __DIR__ . '/path/class-adx-base-post-controller.php';
require_once __DIR__ . '/path/class-adx-base-terms-controller.php';
```

No plugin activation needed. No coupling. Extend and go.

---

## Usage

### Custom Post Type Controller

```php
class My_Widget_Controller extends ADX_REST_Base_Post_Controller {

    protected $post_type = 'widget';

    public function __construct() {
        parent::__construct( 'wc/v3', 'widgets' );
    }

    // Declare exactly which meta keys are exposed — nothing else leaks
    protected function get_exposed_meta_keys() : array {
        return [ 'widget_colour', 'widget_size', 'widget_price' ];
    }

    // Add computed or ACF fields to the response
    protected function get_extra_fields( WP_Post $post, WP_REST_Request $request ) : array {
        return [
            'brand' => get_field( 'widget_brand', $post->ID ),
        ];
    }

    // Match the schema to your extra fields
    protected function get_extra_schema_properties() : array {
        return [
            'brand' => [ 'type' => 'string', 'context' => [ 'view', 'edit' ] ],
        ];
    }
}

// Register on rest_api_init
add_action( 'rest_api_init', function () {
    ( new My_Widget_Controller() )->register_routes();
} );
```

This registers:
```
GET  /wp-json/wc/v3/widgets
POST /wp-json/wc/v3/widgets
GET  /wp-json/wc/v3/widgets/{id}
PUT  /wp-json/wc/v3/widgets/{id}
DEL  /wp-json/wc/v3/widgets/{id}
```

---

### Custom Taxonomy Controller

```php
class My_Colour_Controller extends ADX_REST_Base_Terms_Controller {

    protected $taxonomy = 'product_colour';

    public function __construct() {
        parent::__construct( 'wc/v3', 'products/colours' );
    }

    // ACF image fields: ACF field name => response key
    protected function get_image_fields() : array {
        return [ 'colour_swatch' => 'swatch' ];
    }

    // Writable image fields: request key => ACF field name
    protected function get_writable_image_fields() : array {
        return [ 'swatch' => 'colour_swatch' ];
    }

    // Schema for extra fields
    protected function get_extra_schema_properties() : array {
        return [
            'swatch' => $this->image_schema_block( 'Colour swatch' ),
        ];
    }
}
```

---

### ACF Meta Sync

When writing meta via REST, ACF needs its hidden `_field_name` key records to exist. Use `ADX_ACF_Meta_Sync::sync()` in a filter:

```php
add_filter( 'woocommerce_rest_pre_insert_product_object', function ( $product, $request ) {
    return ADX_ACF_Meta_Sync::sync( 'product', $product, $request );
}, 10, 2 );
```

---

## File Structure

```
woocommerce-rest-toolkit/
├── woocommerce-rest-toolkit.php          ← Plugin bootstrap + hook registration
├── includes/
│   ├── base/
│   │   ├── class-adx-base-post-controller.php   ← Abstract post type controller
│   │   └── class-adx-base-terms-controller.php  ← Abstract taxonomy controller
│   ├── helpers/
│   │   └── class-adx-acf-meta-sync.php          ← ACF key sync helper
│   └── controllers/
│       ├── class-adx-example-post-controller.php
│       ├── class-adx-example-terms-controller.php
```

---

## Security

- All database queries use `$wpdb->prepare()` — no raw interpolation
- Meta exposure is opt-in via `get_exposed_meta_keys()` — private `_` keys never leak by accident
- Image uploads are validated with `wp_attachment_is_image()` before being saved
- Input is sanitized at the schema layer via `arg_options.sanitize_callback`

---

## License

MIT