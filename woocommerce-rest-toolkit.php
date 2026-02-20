<?php
/**
 * Plugin Name: WooCommerce REST Toolkit
 * Description: Extensible REST API toolkit for WooCommerce custom post types and taxonomies.
 * Version: 1.0.0
 * Author: Anjana
 */

defined('ABSPATH') || exit;

add_action('plugins_loaded', function () {

    if (!class_exists('WooCommerce')) {
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'includes/base/class-base-post-controller.php';
    require_once plugin_dir_path(__FILE__) . 'includes/base/class-base-terms-controller.php';

});