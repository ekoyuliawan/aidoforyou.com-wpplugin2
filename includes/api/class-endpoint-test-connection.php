<?php
/**
 * Endpoint for testing Gemini API connection.
 *
 * @package AIdoforyouMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIDOFORYOU_Endpoint_Test_Connection extends AIDOFORYOU_Metadata_Endpoint_Base {

    public function handle_request( WP_REST_Request $request ) {
        if ( ! $this->client->is_configured() ) {
            return new WP_Error( 'no_api_key', __( 'Gemini API Key is not configured in settings.', 'aidoforyou-metadata' ), array( 'status' => 400 ) );
        }

        $prompt = sanitize_text_field( $request->get_param( 'prompt' ) );
        if ( empty( $prompt ) ) {
            return new WP_Error( 'empty_prompt', __( 'Prompt is required.', 'aidoforyou-metadata' ), array( 'status' => 400 ) );
        }

        // Ambil System Prompt dari pengaturan agar tester mematuhi aturan Microstock
        $system_prompt = get_option( 'afy_meta_system_prompt', '' );

        // Teruskan System Prompt ke dalam fungsi pengujian
        $response = $this->client->test_connection( $prompt, $system_prompt );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', $response->get_error_message(), array( 'status' => 400 ) );
        }

        return rest_ensure_response( array(
            'code'     => 0,
            'response' => $response
        ) );
    }
}