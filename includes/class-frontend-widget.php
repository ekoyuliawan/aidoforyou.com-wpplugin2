<?php
/**
 * Frontend Widget and Shortcode Handler.
 *
 * @package AIdoforyouMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIDOFORYOU_Metadata_Frontend_Widget {

    public function __construct() {
        add_shortcode( 'aidoforyou_metadata', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_proxy_endpoint' ) );
    }

    // Mendaftarkan API Image Stream Proxy untuk menerobos CORS
    public function register_proxy_endpoint(): void {
        register_rest_route( 'aidoforyou-metadata/v1', '/image-proxy', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_image_proxy' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function handle_image_proxy( WP_REST_Request $request ) {
        $url = esc_url_raw( $request->get_param( 'url' ) );
        
        // --- SECURITY FIX: Strict Host Validation to prevent SSRF ---
        $parsed_url = wp_parse_url( $url );
        $host       = isset( $parsed_url['host'] ) ? strtolower( $parsed_url['host'] ) : '';

        // Allow only exact matches for ftcdn.net or its valid subdomains
        $allowed_domain = 'ftcdn.net';
        $is_valid_host  = ( $host === $allowed_domain || substr( $host, -strlen( '.' . $allowed_domain ) ) === '.' . $allowed_domain );

        if ( ! $is_valid_host ) {
            return new WP_Error( 'forbidden', 'Unauthorized domain. Proxy strictly allows only Adobe Stock assets.', array( 'status' => 403 ) );
        }
        // ------------------------------------------------------------

        $res = wp_remote_get( $url, array( 'timeout' => 10 ) );
        if ( is_wp_error( $res ) ) return $res;

        $type = wp_remote_retrieve_header( $res, 'content-type' );
        header( 'Content-Type: ' . ( $type ? $type : 'image/jpeg' ) );
        header( 'Cache-Control: public, max-age=86400' ); // Cache for 1 day
        echo wp_remote_retrieve_body( $res );
        exit; // Terminate WP execution for pure streaming
    }

    public function register_assets(): void {
        wp_register_style( 'aidoforyou-meta-style', AIDOFORYOU_META_URL . 'assets/css/style.css', array(), AIDOFORYOU_META_VERSION );
        wp_register_script( 'aidoforyou-meta-app', AIDOFORYOU_META_URL . 'assets/js/app.js', array(), AIDOFORYOU_META_VERSION, true );

        $default_models = '[{"id":"gemini-3.1-flash-lite","label":"Lite","premium":false,"default":true,"thinking":""},{"id":"gemini-3-flash-preview","label":"Flash","premium":false,"default":false,"thinking":""},{"id":"gemini-3.1-pro-preview","label":"Pro","premium":true,"default":false,"thinking":"high"}]';
        $config_json    = get_option( 'afy_meta_models_config', $default_models );
        $models         = json_decode( $config_json, true );

        wp_localize_script( 'aidoforyou-meta-app', 'AFY_META_APP', array(
            'core_rest'    => esc_url_raw( rest_url( 'aidoforyou/v1' ) ),
            'meta_rest'    => esc_url_raw( rest_url( 'aidoforyou-metadata/v1' ) ),
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'max_mb'       => (int) get_option( 'afy_meta_max_mb', 5 ),
            'cost'         => (int) get_option( 'afy_meta_credit_cost', 2 ),
            'models'       => is_array( $models ) ? $models : array(),
            'is_logged_in' => is_user_logged_in(),
            'user_id'      => get_current_user_id()
        ) );
    }

    public function render_shortcode(): string {
        wp_enqueue_style( 'aidoforyou-meta-style' );
        wp_enqueue_script( 'aidoforyou-meta-app' );

        ob_start();
        include AIDOFORYOU_META_DIR . 'templates/frontend-app.php';
        return ob_get_clean();
    }
}