<?php
/**
 * Plugin Name: AIdoforyou Microstock Metadata (Addon)
 * Description: AI-powered microstock metadata extractor using Gemini Vision. Requires AIdoforyou Credit Manager. Use [aidoforyou_metadata].
 * Version:      1.0.1
 * Author:       AIdoforyou
 * Text Domain:  aidoforyou-metadata
 * Requires PHP: 8.0
 * License:      GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AIDOFORYOU_META_VERSION', '1.0.1' );
define( 'AIDOFORYOU_META_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIDOFORYOU_META_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'aidoforyou_metadata_plugins_loaded' );

function aidoforyou_metadata_plugins_loaded() {
    if ( ! function_exists( 'aidoforyou' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>AIdoforyou Microstock Metadata</strong> requires the <strong>AIdoforyou Credit Manager (Core)</strong> plugin to be installed and activated.</p></div>';
        } );
        return;
    }

    require_once AIDOFORYOU_META_DIR . 'includes/class-metadata-admin-settings.php';
    require_once AIDOFORYOU_META_DIR . 'includes/api/class-metadata-rest-router.php';
    require_once AIDOFORYOU_META_DIR . 'includes/class-frontend-widget.php';

    if ( is_admin() ) {
        new AIDOFORYOU_Metadata_Admin_Settings();
    }

    $router = new AIDOFORYOU_Metadata_REST_Router();
    add_action( 'rest_api_init', array( $router, 'register_routes' ) );
}

// FIX: Pendaftaran Shortcode harus di-hook ke 'init' agar dikenali WordPress saat merender halaman
add_action( 'init', 'aidoforyou_metadata_register_shortcode' );
function aidoforyou_metadata_register_shortcode() {
    if ( function_exists( 'aidoforyou' ) && class_exists('AIDOFORYOU_Metadata_Frontend_Widget') ) {
        new AIDOFORYOU_Metadata_Frontend_Widget();
    }
}