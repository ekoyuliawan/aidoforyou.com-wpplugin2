<?php
/**
 * Google Gemini API Client (With Key Rotation).
 *
 * @package AIdoforyouMetadata
 * * Commit Notes:
 * - Removed verbose descriptions from JSON Schema to prevent Instruction Override / Attention Dilution.
 * - Restored strict structural-only schema so the AI properly respects the global System Prompt.
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

        if ( empty( $this->api_keys ) ) {
            $old_key = get_option( 'afy_meta_gemini_api_key', '' );
            if ( ! empty( $old_key ) ) $this->api_keys[] = $old_key;
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
        return 'gemini-1.5-flash-latest';
    }

    public function get_api_key( int $index = 0 ): string {
        if ( empty( $this->api_keys ) ) return '';
        return $this->api_keys[ $index ] ?? $this->api_keys[0];
    }

    public function get_api_keys_count(): int {
        return count( $this->api_keys );
    }

    private function post_with_retry( string $url, array $args, int $max_retries = 3 ) {
        for ( $attempt = 0; $attempt <= $max_retries; $attempt++ ) {
            $response = wp_remote_post( $url, $args );

            if ( is_wp_error( $response ) ) {
                $err_code = $response->get_error_code();
                $err_msg  = strtolower( $response->get_error_message() );
                if ( $err_code === 'http_request_failed' && ( strpos( $err_msg, 'curl error 28' ) !== false || strpos( $err_msg, 'timed out' ) !== false ) ) {
                    if ( $attempt < $max_retries ) { usleep( 2000000 ); continue; }
                }
                return $response; 
            }

            $http_code = wp_remote_retrieve_response_code( $response );
            $raw_body  = wp_remote_retrieve_body( $response );
            $data      = json_decode( $raw_body, true );
            $should_retry = false;
            $sleep_sec    = 0;

            if ( $http_code === 503 || $http_code === 500 || $http_code === 502 || $http_code === 504 ) {
                $should_retry = true; $sleep_sec = pow( 2, $attempt + 1 ) + ( rand( 0, 1000 ) / 1000.0 );
            } elseif ( is_array( $data ) && isset( $data['error'] ) ) {
                $err_code = (int) ( $data['error']['code'] ?? 0 );
                $err_msg  = strtolower( $data['error']['message'] ?? '' );
                if ( $err_code === 503 || strpos( $err_msg, 'high demand' ) !== false || strpos( $err_msg, 'overloaded' ) !== false ) {
                    $should_retry = true; $sleep_sec = pow( 2, $attempt + 1 ) + ( rand( 0, 1000 ) / 1000.0 );
                }
            }

            if ( $should_retry && $attempt < $max_retries ) {
                usleep( (int) ( $sleep_sec * 1000000 ) ); continue;
            }
            return $response;
        }
        return $response;
    }

    public function test_connection( string $prompt, string $system_prompt = '', string $model_id = '' ): string|WP_Error {
        if ( empty( $model_id ) ) $model_id = $this->get_default_model_id();
        $api_key = $this->get_api_key( 0 );
        $url = "{$this->api_base}/v1beta/models/{$model_id}:generateContent?key={$api_key}";
        
        $body = array( 'contents' => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ) );
        if ( ! empty( $system_prompt ) ) $body['system_instruction'] = array( 'parts' => array( array( 'text' => $system_prompt ) ) );

        $args = array( 'headers' => array( 'Content-Type' => 'application/json', 'Host' => parse_url( $this->api_base, PHP_URL_HOST ) ), 'body' => wp_json_encode( $body ), 'timeout' => 30 );
        $response = $this->post_with_retry( $url, $args, 2 );

        if ( is_wp_error( $response ) ) return $response;
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) return "⚠️ Non-JSON response.";
        if ( isset( $data['error'] ) ) return new WP_Error( 'gemini_error', $data['error']['message'] );

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? "No text generated.";
    }

    public function get_quick_keywords( string $file_path, string $mime_type, string $text_input = '', string $model_id = '', int $start_server_index = 0 ): string|WP_Error {
        if ( empty( $model_id ) ) $model_id = $this->get_default_model_id();
        
        $schema = array(
            'type' => 'OBJECT',
            'properties' => array(
                'query_words' => array(
                    'type' => 'ARRAY',
                    'items' => array( 'type' => 'STRING' ),
                    'description' => 'Extract EXACTLY 2 to 5 highly descriptive words detailing the main subject, setting, and color/mood.'
                )
            ),
            'required' => array( 'query_words' )
        );

        $parts = array();
        if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
            $base64_data = base64_encode( file_get_contents( $file_path ) );
            $parts[] = array( 'inline_data' => array( 'mime_type' => $mime_type, 'data' => $base64_data ) );
            $parts[] = array( 'text' => "Analyze this image to create a concise Adobe Stock search query using 2 to 5 specific words." );
        } else {
            $parts[] = array( 'text' => "Summarize this concept into a concise 2 to 5 word Adobe Stock search query: \"{$text_input}\"" );
        }

        $body = array(
            'generationConfig' => array( 'responseMimeType' => 'application/json', 'responseSchema' => $schema, 'temperature' => 0.4 ),
            'contents' => array( array( 'parts' => $parts ) )
        );

        $args = array( 'headers' => array( 'Content-Type' => 'application/json', 'Host' => parse_url( $this->api_base, PHP_URL_HOST ) ), 'body' => wp_json_encode( $body ), 'timeout' => 45 );
        $total_keys = $this->get_api_keys_count();
        $last_error = new WP_Error( 'api_error', 'Unknown error' );

        for ( $i = 0; $i < max( 1, $total_keys ); $i++ ) {
            $actual_index = ( $start_server_index + $i ) % max( 1, $total_keys );
            $api_key = $this->get_api_key( $actual_index );
            $url = "{$this->api_base}/v1beta/models/{$model_id}:generateContent?key={$api_key}";
            
            $response = $this->post_with_retry( $url, $args, 1 );

            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                $data = json_decode( wp_remote_retrieve_body( $response ), true );
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                $parsed = json_decode( $text, true );
                
                if ( ! empty( $parsed['query_words'] ) && is_array( $parsed['query_words'] ) ) {
                    return strtolower( implode( ' ', $parsed['query_words'] ) );
                }
            } else {
                $last_error = $response;
            }
        }
        return $last_error;
    }

    public function get_market_urls_via_ai( string $search_query, int $count, string $model_id = '', int $start_server_index = 0 ): array {
        if ( empty( $model_id ) ) $model_id = $this->get_default_model_id();

        $prompt = "You are an AI research assistant. Please use your googleSearch or urlContext tool to search EXACTLY for this query:\n\nsite:stock.adobe.com/images \"{$search_query}\"\n\nFrom the search results, find {$count} direct image CDN URLs. These URLs must contain 'ftcdn.net'. Output ONLY a raw JSON array of these string URLs. Do not add markdown.";

        $tools = array();
        $tools[] = array( 'urlContext' => new stdClass() );
        if ( get_option( 'afy_meta_google_search', 'yes' ) === 'yes' ) $tools[] = array( 'googleSearch' => new stdClass() );

        $body = array(
            'contents' => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
            'tools' => $tools
        );

        $args = array( 'headers' => array( 'Content-Type' => 'application/json', 'Host' => parse_url( $this->api_base, PHP_URL_HOST ) ), 'body' => wp_json_encode( $body ), 'timeout' => 45 );
        $total_keys = $this->get_api_keys_count();

        for ( $i = 0; $i < max( 1, $total_keys ); $i++ ) {
            $actual_index = ( $start_server_index + $i ) % max( 1, $total_keys );
            $api_key = $this->get_api_key( $actual_index );
            $url = "{$this->api_base}/v1beta/models/{$model_id}:generateContent?key={$api_key}";

            $response = $this->post_with_retry( $url, $args, 1 );
            
            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                $data = json_decode( wp_remote_retrieve_body( $response ), true );
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                
                preg_match_all('/https:\/\/(?:t\d+|as[1-2])\.ftcdn\.net\/(?:jpg|v2)\/[a-zA-Z0-9_\-\.\/]+/i', $text, $matches);
                $valid_urls = array_unique( $matches[0] ?? array() );
                if ( ! empty( $valid_urls ) ) {
                    return array_slice( array_values( $valid_urls ), 0, $count );
                }
            }
        }
        return array();
    }

    public function extract_metadata( string $file_path, string $mime_type, string $text_input, string $system_prompt, string $user_prompt = '', string $model_id = '', string $thinking_level = '', int $server_index = 0 ): string|WP_Error {
        if ( empty( $model_id ) ) $model_id = $this->get_default_model_id();

        $api_key = $this->get_api_key( $server_index );
        $url = "{$this->api_base}/v1beta/models/{$model_id}:generateContent?key={$api_key}";
        
        // FIX: Removed bloated descriptions to prevent System Prompt amnesia.
        // We rely entirely on the Master System Prompt for business logic and JS for formatting failsafes.
        $schema = array(
            'type' => 'OBJECT',
            'properties' => array(
                'commercial_positioning' => array( 'type' => 'STRING' ),
                'commercial_elasticity'  => array( 'type' => 'STRING' ),
                'market_reference_urls'  => array( 'type' => 'ARRAY', 'items' => array( 'type' => 'STRING' ) ),
                'variation_prompts'      => array(
                    'type'  => 'ARRAY',
                    'items' => array(
                        'type' => 'OBJECT',
                        'properties' => array( 'market_niche' => array( 'type' => 'STRING' ), 'rationale' => array( 'type' => 'STRING' ), 'prompt' => array( 'type' => 'STRING' ) ),
                        'required' => array( 'market_niche', 'rationale', 'prompt' )
                    )
                )
            ),
            'required' => array( 'commercial_positioning', 'commercial_elasticity', 'market_reference_urls', 'variation_prompts' )
        );

        $parts = array();
        if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
            $schema['properties']['reverse_prompt'] = array( 'type' => 'STRING' );
            $schema['properties']['media_type']     = array( 'type' => 'STRING' );
            $schema['properties']['filename']       = array( 'type' => 'STRING' );
            $schema['properties']['category']       = array( 'type' => 'STRING' );
            $schema['properties']['title']          = array( 'type' => 'STRING' );
            $schema['properties']['keywords']       = array( 'type' => 'STRING' );
            
            $schema['required'] = array( 'reverse_prompt', 'commercial_positioning', 'commercial_elasticity', 'media_type', 'filename', 'category', 'title', 'keywords', 'market_reference_urls', 'variation_prompts' );

            $base64_data = base64_encode( file_get_contents( $file_path ) );
            $text_instruction = "Here is the image. Extract the microstock metadata based on visual evidence.";
            if ( ! empty( $user_prompt ) ) $text_instruction .= "\n\nThe user provided this context/generation prompt: \"" . $user_prompt . "\".";
            $parts[] = array( 'inline_data' => array( 'mime_type' => $mime_type, 'data' => $base64_data ) );
            $parts[] = array( 'text' => $text_instruction );
        } else {
            $text_instruction = "Analyze this keyword for Adobe Stock commercial variations. Keyword: \"" . $text_input . "\"";
            if ( ! empty( $user_prompt ) ) $text_instruction .= "\n\nAdditional context from user: \"" . $user_prompt . "\".";
            $parts[] = array( 'text' => $text_instruction );
        }

        $body = array(
            'system_instruction' => array( 'parts' => array( array( 'text' => $system_prompt ) ) ),
            'generationConfig' => array( 'responseMimeType' => 'application/json', 'responseSchema' => $schema, 'maxOutputTokens' => 8192 ),
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

        $args = array( 'headers' => array( 'Content-Type' => 'application/json', 'Host' => parse_url( $this->api_base, PHP_URL_HOST ) ), 'body' => wp_json_encode( $body ), 'timeout' => $timeout_sec );
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