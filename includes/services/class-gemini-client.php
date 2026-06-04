<?php
/**
 * Google Gemini API Client (With Key Rotation).
 *
 * @package AIdoforyouMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIDOFORYOU_Gemini_Client {

    private array $api_keys;
    private string $api_base;

    public function __construct() {
        $keys_json = get_option( 'afy_meta_api_keys', '[]' );
        $keys_arr  = json_decode( $keys_json, true );
        
        $this->api_keys = is_array( $keys_arr ) ? array_filter( $keys_arr ) : array();

        // Fallback untuk key lama jika array kosong
        if ( empty( $this->api_keys ) ) {
            $old_key = get_option( 'afy_meta_gemini_api_key', '' );
            if ( ! empty( $old_key ) ) {
                $this->api_keys[] = $old_key;
            }
        }

        $base = get_option( 'afy_meta_gemini_api_base', '' );
        $this->api_base = ! empty( $base ) ? rtrim( $base, '/' ) : 'https://generativelanguage.googleapis.com';
    }

    public function is_configured(): bool {
        return count( $this->api_keys ) > 0;
    }

    public function get_default_model_id(): string {
        $config_json = get_option( 'afy_meta_models_config', '[]' );
        $models = json_decode( $config_json, true );
        if ( is_array( $models ) ) {
            foreach ( $models as $m ) {
                if ( ! empty( $m['default'] ) ) return $m['id'];
            }
            if ( ! empty( $models[0]['id'] ) ) return $models[0]['id'];
        }
        return 'gemini-flash-latest';
    }

    // Mengambil API Key spesifik berdasarkan Server Index
    public function get_api_key( int $index = 0 ): string {
        if ( empty( $this->api_keys ) ) return '';
        return $this->api_keys[ $index ] ?? $this->api_keys[0];
    }

    public function get_api_keys_count(): int {
        return count( $this->api_keys );
    }

    // Melakukan Exponential Backoff untuk 1 Server Spesifik (Hanya jika 503/Timeout)
    private function post_with_retry( string $url, array $args, int $max_retries = 3 ) {
        for ( $attempt = 0; $attempt <= $max_retries; $attempt++ ) {
            $response = wp_remote_post( $url, $args );

            if ( is_wp_error( $response ) ) {
                $err_code = $response->get_error_code();
                $err_msg  = strtolower( $response->get_error_message() );
                // Jika Timeout, coba retry
                if ( $err_code === 'http_request_failed' && ( strpos( $err_msg, 'curl error 28' ) !== false || strpos( $err_msg, 'timed out' ) !== false ) ) {
                    if ( $attempt < $max_retries ) {
                        usleep( 2000000 ); // Tunggu 2 detik
                        continue;
                    }
                }
                return $response; 
            }

            $http_code = wp_remote_retrieve_response_code( $response );
            $raw_body  = wp_remote_retrieve_body( $response );
            $data      = json_decode( $raw_body, true );

            $should_retry = false;
            $sleep_sec    = 0;

            if ( $http_code === 503 || $http_code === 500 || $http_code === 502 || $http_code === 504 ) {
                $should_retry = true;
                $sleep_sec    = pow( 2, $attempt + 1 ) + ( rand( 0, 1000 ) / 1000.0 );
            } elseif ( is_array( $data ) && isset( $data['error'] ) ) {
                $err_code = (int) ( $data['error']['code'] ?? 0 );
                $err_msg  = strtolower( $data['error']['message'] ?? '' );
                if ( $err_code === 503 || strpos( $err_msg, 'high demand' ) !== false || strpos( $err_msg, 'overloaded' ) !== false ) {
                    $should_retry = true;
                    $sleep_sec    = pow( 2, $attempt + 1 ) + ( rand( 0, 1000 ) / 1000.0 );
                }
            }

            // Catatan: Jika 429 (Quota Limit), kita TIDAK retry di sini. Kita lempar errornya agar Endpoint yang pindah Server!
            if ( $should_retry && $attempt < $max_retries ) {
                usleep( (int) ( $sleep_sec * 1000000 ) );
                continue;
            }

            return $response;
        }
        return $response;
    }

    public function test_connection( string $prompt, string $system_prompt = '', string $model_id = '' ): string|WP_Error {
        if ( empty( $model_id ) ) $model_id = $this->get_default_model_id();

        $api_key = $this->get_api_key( 0 ); // Tester selalu pakai Server 1
        $url = "{$this->api_base}/v1beta/models/{$model_id}:generateContent?key={$api_key}";
        
        $body = array( 'contents' => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ) );

        if ( ! empty( $system_prompt ) ) {
            $body['system_instruction'] = array( 'parts' => array( array( 'text' => $system_prompt ) ) );
        }

        $args = array(
            'headers' => array( 'Content-Type' => 'application/json', 'Host' => parse_url( $this->api_base, PHP_URL_HOST ) ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        );

        $response = $this->post_with_retry( $url, $args, 2 );

        if ( is_wp_error( $response ) ) return $response;

        $raw_body = wp_remote_retrieve_body( $response );
        $data     = json_decode( $raw_body, true );
        
        if ( ! is_array( $data ) ) return "⚠️ Non-JSON response.\nRaw:\n" . esc_html( $raw_body );
        if ( isset( $data['error'] ) ) return new WP_Error( 'gemini_error', $data['error']['message'] );

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? "No text generated.";
    }
	
    public function get_quick_keywords( string $file_path, string $mime_type, string $model_id = '' ): string|WP_Error {
        if ( empty( $model_id ) ) $model_id = $this->get_default_model_id();
        
        $api_key = $this->get_api_key( 0 ); // Pakai server utama
        $url = "{$this->api_base}/v1beta/models/{$model_id}:generateContent?key={$api_key}";
        
        $base64_data = base64_encode( file_get_contents( $file_path ) );
        $body = array(
            'generationConfig' => array(
                'responseMimeType' => 'text/plain',
                'maxOutputTokens'  => 90 //30
            ),
            'contents' => array(
                array(
                    'parts' => array(
                        array( 'inline_data' => array( 'mime_type' => $mime_type, 'data' => $base64_data ) ),
                        array( 'text' => "Identify all clearly visible commercially important subjects, objects, and supported mood or occasion concepts in this image for Adobe Stock search intent in 2 to 10 accurate descriptive words, include all major visible elements that materially affect search relevance, return only the words separated by single spaces, and never guess, omit, or name anything unclear." )
                    )
                )
            )
        );

        $args = array(
            'headers' => array( 'Content-Type' => 'application/json', 'Host' => parse_url( $this->api_base, PHP_URL_HOST ) ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30, //15
        );

        $response = $this->post_with_retry( $url, $args, 1 );
        if ( is_wp_error( $response ) ) return $response;

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'stock photo';
        
        return trim( str_replace( array( "\n", "\r", ",", "." ), " ", $text ) );
    }

    public function extract_metadata( string $file_path, string $mime_type, string $text_input, string $system_prompt, string $user_prompt = '', string $model_id = '', string $thinking_level = '', int $server_index = 0 ): string|WP_Error {
        if ( empty( $model_id ) ) $model_id = $this->get_default_model_id();

        $api_key = $this->get_api_key( $server_index );
        $url = "{$this->api_base}/v1beta/models/{$model_id}:generateContent?key={$api_key}";
        
        // FIX: Menambahkan market_reference_urls agar Gemini yang melakukan scraping
        $schema = array(
            'type' => 'OBJECT',
            'properties' => array(
                'reverse_prompt'         => array( 'type' => 'STRING' ),
                'commercial_positioning' => array( 'type' => 'STRING' ),
                'commercial_elasticity'  => array( 'type' => 'STRING' ),
                'media_type'             => array( 'type' => 'STRING' ),
                'filename'               => array( 'type' => 'STRING' ),
                'category'               => array( 'type' => 'STRING' ),
                'title'                  => array( 'type' => 'STRING' ),
                'keywords'               => array( 'type' => 'STRING' ),
                'market_reference_urls'  => array(
                    'type'  => 'ARRAY',
                    'items' => array( 'type' => 'STRING' )
                ),
                'variation_prompts'      => array(
                    'type'  => 'ARRAY',
                    'items' => array(
                        'type' => 'OBJECT',
                        'properties' => array(
                            'market_niche' => array( 'type' => 'STRING' ),
                            'rationale'    => array( 'type' => 'STRING' ),
                            'prompt'       => array( 'type' => 'STRING' )
                        ),
                        'required' => array( 'market_niche', 'rationale', 'prompt' )
                    )
                )
            ),
            'required' => array( 'reverse_prompt', 'commercial_positioning', 'commercial_elasticity', 'media_type', 'filename', 'category', 'title', 'keywords', 'market_reference_urls', 'variation_prompts' )
        );

        $parts = array();
        if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
            $base64_data = base64_encode( file_get_contents( $file_path ) );
            $text_instruction = "Here is the image. Extract the microstock metadata based on visual evidence.";
            if ( ! empty( $user_prompt ) ) $text_instruction .= "\n\nThe user provided this context/generation prompt: \"" . $user_prompt . "\".";
            $parts[] = array( 'inline_data' => array( 'mime_type' => $mime_type, 'data' => $base64_data ) );
            $parts[] = array( 'text' => $text_instruction );
        } else {
            $text_instruction = "Here is a text concept, keyword, or reverse prompt. Expand and analyze this text into full microstock metadata as if it were a real image. Concept: \"" . $text_input . "\"";
            if ( ! empty( $user_prompt ) ) $text_instruction .= "\n\nAdditional context from user: \"" . $user_prompt . "\".";
            $parts[] = array( 'text' => $text_instruction );
        }

        $body = array(
            'system_instruction' => array( 'parts' => array( array( 'text' => $system_prompt ) ) ),
            'generationConfig' => array( 
                'responseMimeType' => 'application/json',
                'responseSchema'   => $schema,
                'maxOutputTokens'  => 8192
            ),
            'contents' => array( array( 'parts' => $parts ) )
        );

        $media_res = get_option( 'afy_meta_media_resolution', 'MEDIA_RESOLUTION_HIGH' );
        if ( $media_res !== 'default' ) $body['generationConfig']['mediaResolution'] = $media_res;

        if ( ! empty( $thinking_level ) ) $body['generationConfig']['thinkingConfig'] = array( 'thinkingLevel' => $thinking_level );

        $tools = array();
        $tools[] = array( 'urlContext' => new stdClass() );
        if ( get_option( 'afy_meta_google_search', 'yes' ) === 'yes' ) $tools[] = array( 'googleSearch' => new stdClass() );
        $body['tools'] = $tools;

        $timeout_sec = (int) get_option( 'afy_meta_api_timeout', 120 );
        $max_retries = (int) get_option( 'afy_meta_max_retries', 3 );

        $args = array(
            'headers' => array( 'Content-Type' => 'application/json', 'Host' => parse_url( $this->api_base, PHP_URL_HOST ) ),
            'body'    => wp_json_encode( $body ),
            'timeout' => $timeout_sec,
        );

        $response = $this->post_with_retry( $url, $args, $max_retries );

        if ( is_wp_error( $response ) ) return $response;

        $http_code = wp_remote_retrieve_response_code( $response );
        $raw_body  = wp_remote_retrieve_body( $response );
        $data      = json_decode( $raw_body, true );
        
        if ( ! is_array( $data ) ) return new WP_Error( 'ai_error', "Non-JSON response from API.", array( 'http_code' => $http_code ) );
        if ( isset( $data['error'] ) ) return new WP_Error( 'gemini_error', $data['error']['message'], array( 'http_code' => $http_code ) );
        if ( $http_code >= 400 ) return new WP_Error( 'http_error', "HTTP Error {$http_code}", array( 'http_code' => $http_code ) );

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ( $text === null ) return new WP_Error( 'ai_error', 'No output text returned.', array( 'http_code' => $http_code ) );

        return $text;
    }
}