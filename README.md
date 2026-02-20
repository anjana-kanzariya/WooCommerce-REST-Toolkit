# WooCommerce-REST-Toolkit
A lightweight extension layer for the WooCommerce REST API.

This toolkit provides reusable base controllers for custom post types and taxonomies, enabling structured REST schema control, filtered meta exposure, and optional ACF integration.

## Why?

WooCommerce’s REST controllers are powerful but often require repetitive boilerplate when exposing:
- Custom post types
- Custom taxonomies
- Selected meta fields
- ACF fields
- Custom response shaping

This toolkit abstracts that repetition into clean base classes. The toolkit is intentionally minimal and designed to be extended per project.

## Features
- Base REST controller for custom post types
- Base REST controller for custom taxonomies
- Controlled meta exposure (no accidental full meta dumps)
- Optional ACF field integration
- Clean filter hooks for response modification
- Schema extension support (coming soon)
- Compatible with WooCommerce REST v3

## Installation
```php
Install and activate WooCommerce.
Install and activate this plugin.
```
## Usage Options
### Option 1 — Install as a Plugin
- Install and activate WooCommerce
- Install this plugin
- Create a controller extending one of the base classes
- Register your routes

### Option 2 — Use Inside an Existing Plugin or Theme

If you prefer not to install this as a standalone plugin, you can include the base classes directly inside your project.

1. Copy the Base Classes

Copy:
```
includes/base/class-base-post-controller.php
includes/base/class-base-terms-controller.php
```
into your plugin or theme.

2. Include Them Manually
```php
require_once __DIR__ . '/path-to/class-base-post-controller.php';
require_once __DIR__ . '/path-to/class-base-terms-controller.php';
```

3. Extend as Needed
That’s it.
- No dependency injection.
- No plugin activation required.
- No coupling