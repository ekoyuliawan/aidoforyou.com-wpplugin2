<?php
/**
 * REST API Router for Metadata Addon.
 *
 * @package AIdoforyouMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIDOFORYOU_Metadata_REST_Router {

    private AIDOFORYOU_Gemini_Client $client;

    public function __construct() {
        // Load Services
        require_once AIDOFORYOU_META_DIR . 'includes/services/class-gemini-client.php';
        $this->client = new AIDOFORYOU_Gemini_Client();
        
        // Load API Endpoints
        require_once AIDOFORYOU_META_DIR . 'includes/api/class-metadata-endpoint-base.php';
        require_once AIDOFORYOU_META_DIR . 'includes/api/class-endpoint-test-connection.php';
        require_once AIDOFORYOU_META_DIR . 'includes/api/class-endpoint-extract.php';
    }

    public function register_routes() {
        // Namespace unik untuk addon ini
        $ns = 'aidoforyou-metadata/v1';

        $ep_test = new AIDOFORYOU_Endpoint_Test_Connection( $this->client );
        register_rest_route( $ns, '/test-connection', array(
            'methods'             => 'POST',
            'callback'            => array( $ep_test, 'handle_request' ),
            'permission_callback' => array( $ep_test, 'require_admin' )
        ) );

        $ep_extract = new AIDOFORYOU_Endpoint_Extract( $this->client );
        register_rest_route( $ns, '/extract', array(
            'methods'             => 'POST',
            'callback'            => array( $ep_extract, 'handle_request' ),
            'permission_callback' => '__return_true'
        ) );
    }
}