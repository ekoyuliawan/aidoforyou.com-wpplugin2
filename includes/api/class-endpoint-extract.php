<?php
/**
 * Metadata Extraction Endpoint Controller.
 *
 * @package AIdoforyouMetadata
 * * Commit Notes:
 * - Dynamically omit irrelevant JSON Schema rules when executing "By Keyword" (No Image) to save tokens and prevent hallucination.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIDOFORYOU_Endpoint_Extract extends AIDOFORYOU_Metadata_Endpoint_Base {

    public function handle_request( WP_REST_Request $request ) {
        @set_time_limit( 0 );

        if ( ! $this->client->is_configured() ) {
            return new WP_Error( 'no_config', __( 'Metadata service is not configured.', 'aidoforyou-metadata' ), array( 'status' => 503 ) );
        }

        $identifier = $this->get_identifier( $request );
        if ( ! $identifier ) {
            return new WP_Error( 'bad_token', __( 'Invalid session token.', 'aidoforyou-metadata' ), array( 'status' => 401 ) );
        }

        $action_type  = sanitize_text_field( $request->get_param( 'action_type' ) );
        if ( empty( $action_type ) ) $action_type = 'extract';
        
        $server_index = (int) $request->get_param( 'server_index' );

        $requested_model = sanitize_text_field( $request->get_param( 'model' ) );
        $config_json = get_option( 'afy_meta_models_config', '[]' );
        $models = json_decode( $config_json, true );
        
        $selected_model_data = null;
        if ( is_array( $models ) ) {
            foreach ( $models as $m ) {
                if ( $m['id'] === $requested_model ) { $selected_model_data = $m; break; }
            }
        }

        if ( ! $selected_model_data ) {
            return new WP_Error( 'invalid_model', __( 'The selected AI model is invalid or unavailable.', 'aidoforyou-metadata' ), array( 'status' => 400 ) );
        }

        $strategy     = sanitize_text_field( $request->get_param( 'strategy' ) ); 
        $market_count = (int) $request->get_param( 'market_count' ); 
        if ( ! in_array( $market_count, array( 2, 4, 6 ) ) ) $market_count = 2;

        $files = $request->get_file_params();
        $text_input = sanitize_textarea_field( $request->get_param( 'text_input' ) );
        
        $has_image = ( ! empty( $files['image'] ) && UPLOAD_ERR_OK === (int) $files['image']['error'] );
        $has_text  = ! empty( $text_input );

        if ( ! $has_image && ! $has_text ) {
            return new WP_Error( 'empty_input', __( 'Please provide an image or text input.', 'aidoforyou-metadata' ), array( 'status' => 400 ) );
        }

        $file_path = '';
        $mime_type = '';

        if ( $has_image ) {
            $max_bytes = (int) get_option( 'afy_meta_max_mb', 5 ) * 1024 * 1024;
            if ( (int) $files['image']['size'] > $max_bytes ) {
                return new WP_Error( 'file_too_large', __( 'Image exceeds the maximum allowed file size.', 'aidoforyou-metadata' ), array( 'status' => 400 ) );
            }
            $file_path = $files['image']['tmp_name'];
            if ( function_exists( 'finfo_open' ) ) {
                $finfo = finfo_open( FILEINFO_MIME_TYPE );
                $mime_type = finfo_file( $finfo, $file_path );
                finfo_close( $finfo );
            } else {
                $mime_type = $files['image']['type'] ?? 'image/jpeg';
            }
        }

        // ==========================================
        // ACTION 1: Get Keyword via AI
        // ==========================================
        if ( $action_type === 'analyze_keyword' ) {
            $search_query = 'stock photo';
            if ( $has_image ) {
                $ai_query = $this->client->get_quick_keywords( $file_path, $mime_type, '', $selected_model_data['id'], $server_index );
                if ( ! is_wp_error( $ai_query ) && ! empty( $ai_query ) && strtolower( $ai_query ) !== 'stock photo' ) {
                    $search_query = $ai_query;
                }
            } elseif ( $has_text ) {
                // Bypass AI jika inputnya sudah berupa Keyword 2-5 kata
                $search_query = $text_input;
            }
            return rest_ensure_response( array( 'code' => 0, 'search_query' => $search_query ) );
        }

        // ==========================================
        // ACTION 2: Search Market URLs via AI
        // ==========================================
        if ( $action_type === 'search_market_ai' ) {
            $search_query = sanitize_text_field( $request->get_param( 'search_query' ) );
            $urls = $this->client->get_market_urls_via_ai( $search_query, $market_count, $selected_model_data['id'], $server_index );
            return rest_ensure_response( array( 'code' => 0, 'urls' => $urls ) );
        }

        // ==========================================
        // ACTION 3: Final Extraction
        // ==========================================
        if ( ! empty( $selected_model_data['premium'] ) && get_current_user_id() === 0 ) {
            return new WP_Error( 'premium_locked', __( 'You must register/log in to use Premium models.', 'aidoforyou-metadata' ), array( 'status' => 403 ) );
        }

        if ( $rate_err = $this->check_rate_limit( $identifier ) ) return $rate_err;

        // --- FIX: Dynamic Pricing Implementation ---
        $base_cost   = (int) get_option( 'afy_meta_credit_cost', 3 ); // Pastikan di setting admin diatur ke 3
        $credit_cost = $base_cost;
        
        if ( $strategy === 'market' ) {
            // Formula: Base Cost + Target Variations
            $credit_cost = $base_cost + $market_count;
        }

        if ( $this->credits->get( $identifier ) < $credit_cost ) {
            return new WP_Error( 'insufficient_credits', sprintf( __( 'You need at least %d credit(s) to process this action.', 'aidoforyou-metadata' ), $credit_cost ), array( 'status' => 403 ) );
        }

        $failed_models_json = $request->get_param( 'failed_models' );
        $failed_models      = ! empty( $failed_models_json ) ? json_decode( wp_unslash( $failed_models_json ), true ) : array();
        if ( ! is_array( $failed_models ) ) $failed_models = array();
        
        $user_generation_prompt = sanitize_textarea_field( $request->get_param( 'prompt' ) );
        $user_rules = get_option( 'afy_meta_system_prompt', '' );

        // OPTIMASI: Susun aturan instruksi format secara dinamis (Image vs Keyword Only)
        $hardcoded_json_instruction = "\n\nYou MUST respond entirely in the requested JSON schema format.\n" .
        "- 'commercial_positioning' must be 1 short sentence stating likely buyer type and use case.\n";
        
        if ( $has_image ) {
            $hardcoded_json_instruction .= "- 'reverse_prompt' must contain your visual analysis acting as a prompt.\n" .
            "- 'media_type' must be 'Photos' or 'Illustrations'.\n" .
            "- 'filename' must be standard lowercase snake_case.\n" .
            "- 'category' must strictly map to Adobe Stock categories.\n" .
            "- 'title' must be a concise searchable title.\n" .
            "- 'keywords' must be comma-separated.\n";
        }

        if ( $strategy === 'market' ) {
            $search_query_frontend = sanitize_text_field( $request->get_param( 'search_query' ) );
            $pre_fetched_urls_json = $request->get_param( 'market_urls' );
            $pre_fetched_urls = ! empty( $pre_fetched_urls_json ) ? json_decode( wp_unslash( $pre_fetched_urls_json ), true ) : array();
            
            if ( empty( $pre_fetched_urls ) ) {
                $adobe_url = 'https://stock.adobe.com/search/images?filters[content_type:photo]=1&filters[content_type:illustration]=1&filters[content_type:zip_vector]=1&k=' . urlencode( $search_query_frontend ) . '&order=nb_downloads';
                
                $hardcoded_json_instruction .= "- 'commercial_elasticity' must be a short string stating: \"Used {$market_count} top-selling references from Adobe Stock to generate variations.\"\n" .
                "- 'market_reference_urls' MUST contain exactly {$market_count} direct image URLs (e.g., https://t4.ftcdn.net/...) extracted from the Adobe Stock search results.\n" .
                "- 'variation_prompts' must contain EXACTLY {$market_count} objects.";
                
                $user_generation_prompt .= "\n\n[MARKET-PROVEN REFERENCES]\nPlease use your googleSearch or urlContext tool to visit this Adobe Stock Search URL:\n{$adobe_url}\n\nAnalyze the top {$market_count} image results on that page. Extract their direct image URLs into the `market_reference_urls` array, and write variation prompts that strictly mimic the exact commercial composition, lighting, and layout of those best-selling images. Make slight safe adjustments to subjects/colors to avoid 1:1 duplication.";
            } else {
                $urls_str = wp_json_encode( $pre_fetched_urls );
                
                $hardcoded_json_instruction .= "- 'commercial_elasticity' must be a short string stating: \"Used {$market_count} top-selling references from Adobe Stock to generate variations.\"\n" .
                "- 'market_reference_urls' MUST EXACTLY RETURN this array without modification: {$urls_str}.\n" .
                "- 'variation_prompts' must contain EXACTLY {$market_count} objects.";
                
                $user_generation_prompt .= "\n\n[MARKET REFERENCE AWARENESS]\nThe market research is completed. The top {$market_count} image URLs for the query '{$search_query_frontend}' are:\n{$urls_str}\n\nCRITICAL INSTRUCTION: You MUST use your 'urlContext' or 'googleSearch' tool to visit and visually inspect these exact image URLs. Analyze their specific commercial composition, camera angles, colors, and lighting. Then, construct your {$market_count} variation prompts to closely mimic these best-selling images. Make only slight, safe adjustments to avoid exact duplication, but ensure the core visual aesthetic is preserved.";
            }
        } else {
            $hardcoded_json_instruction .= "- 'commercial_elasticity' must be a short string stating the number of variations generated (1 to 7) and the justification based on the image's versatility.\n" .
            "- 'market_reference_urls' must be an empty array [].\n" .
            "- 'variation_prompts' must contain 1 to 7 objects depending on the commercial elasticity.";
            
            $user_rules .= "\n\nDYNAMIC VARIATION STRATEGY (COMMERCIAL ELASTICITY)\nEvaluate the Commercial Elasticity of the image. Generate between 1 to 7 distinct variation prompts targeting different buyer segments.";
        }

        $final_system_instruction = $user_rules . $hardcoded_json_instruction;
        $thinking_level = ! empty( $selected_model_data['thinking'] ) ? $selected_model_data['thinking'] : '';

        $ai_response = $this->client->extract_metadata( $file_path, $mime_type, $text_input, $final_system_instruction, $user_generation_prompt, $selected_model_data['id'], $thinking_level, $server_index );

        if ( $has_image ) { @unlink( $file_path ); }

        if ( is_wp_error( $ai_response ) ) {
            $err_code  = $ai_response->get_error_code(); 
            $err_data  = $ai_response->get_error_data();
            $http_code = $err_data['http_code'] ?? 0;
            $err_msg   = strtolower( $ai_response->get_error_message() );
            
            $is_busy    = ( $http_code === 503 || strpos( $err_msg, 'high demand' ) !== false || strpos( $err_msg, 'overloaded' ) !== false );
            $is_quota   = ( $http_code === 429 || strpos( $err_msg, 'quota' ) !== false || strpos( $err_msg, 'exceeded' ) !== false );
            $is_timeout = ( $err_code === 'http_request_failed' && ( strpos( $err_msg, 'curl error 28' ) !== false || strpos( $err_msg, 'timed out' ) !== false ) );

            if ( $is_busy || $is_quota || $is_timeout ) {
                $total_keys = $this->client->get_api_keys_count();
                if ( $server_index + 1 < $total_keys ) {
                    return rest_ensure_response( array( 'code' => 'switch_server', 'next_server_index' => $server_index + 1 ) );
                }
                
                $failed_models[] = $selected_model_data['id'];
                $available_fallbacks = array();
                foreach ( $models as $m ) {
                    if ( in_array( $m['id'], $failed_models ) ) continue;
                    if ( ! empty( $m['premium'] ) && get_current_user_id() === 0 ) continue;
                    $available_fallbacks[] = array( 'id' => $m['id'], 'label' => $m['label'] );
                }
                
                if ( count( $available_fallbacks ) > 0 ) {
                    if ( $is_quota ) $reason_text = 'has exceeded its quota limit';
                    elseif ( $is_timeout ) $reason_text = 'is taking too long to respond (Server Timeout)';
                    else $reason_text = 'is currently experiencing high demand';

                    return rest_ensure_response( array(
                        'code'                => 'fallback_required',
                        'message'             => sprintf( __( 'The AI Model (%s) %s.', 'aidoforyou-metadata' ), $selected_model_data['label'], $reason_text ),
                        'available_fallbacks' => $available_fallbacks,
                        'failed_models'       => $failed_models
                    ) );
                } else {
                    return new WP_Error( 'ai_exhausted', __( 'All available AI models and servers are currently overloaded or out of quota. Please try again later.', 'aidoforyou-metadata' ), array( 'status' => 400 ) );
                }
            }
            return new WP_Error( 'ai_error', $ai_response->get_error_message(), array( 'status' => 400 ) );
        }

        $this->credits->deduct( $identifier, $credit_cost );

        return rest_ensure_response( array(
            'code'           => 0,
            'credits'        => $this->credits->get( $identifier ),
            'metadata'       => $ai_response,
            'search_query'   => $search_query_frontend ?? '', 
            'model_label'    => $selected_model_data['label'],
            'server_label'   => 'Server ' . ($server_index + 1),
            'generated_at'   => current_time( 'M j, Y - H:i:s' )
        ) );
    }
}