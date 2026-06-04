<?php
/**
 * Admin Settings for Microstock Metadata Addon.
 *
 * @package AIdoforyouMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIDOFORYOU_Metadata_Admin_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_submenu' ), 40 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function add_submenu(): void {
        add_submenu_page(
            'aidoforyou', 
            __( 'Metadata Settings', 'aidoforyou-metadata' ),
            __( 'Metadata Settings', 'aidoforyou-metadata' ),
            'manage_options',
            'aidoforyou-metadata',
            array( $this, 'render_page' )
        );
    }

    public function register_settings(): void {
        // API Keys Manager Array
        register_setting( 'aidoforyou_metadata_opts', 'afy_meta_api_keys', array( 'default' => '[]' ) );
        register_setting( 'aidoforyou_metadata_opts', 'afy_meta_gemini_api_base', array( 'sanitize_callback' => 'esc_url_raw', 'default' => '' ) );
        
        $default_models = '[{"id":"gemini-3.1-flash-lite","label":"Lite","premium":false,"default":true,"thinking":""},{"id":"gemini-3-flash-preview","label":"Flash","premium":false,"default":false,"thinking":""},{"id":"gemini-3.1-pro-preview","label":"Pro","premium":true,"default":false,"thinking":"high"}]';
        register_setting( 'aidoforyou_metadata_opts', 'afy_meta_models_config', array( 'default' => $default_models ) );
        
        register_setting( 'aidoforyou_metadata_opts', 'afy_meta_media_resolution', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'MEDIA_RESOLUTION_HIGH' ) );
        register_setting( 'aidoforyou_metadata_opts', 'afy_meta_google_search', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'yes' ) );
        
        register_setting( 'aidoforyou_metadata_opts', 'afy_meta_api_timeout', array( 'sanitize_callback' => 'absint', 'default' => 120 ) );
        register_setting( 'aidoforyou_metadata_opts', 'afy_meta_max_retries', array( 'sanitize_callback' => 'absint', 'default' => 3 ) );

        register_setting( 'aidoforyou_metadata_opts', 'afy_meta_system_prompt', 'sanitize_textarea_field' );
        register_setting( 'aidoforyou_metadata_opts', 'afy_meta_credit_cost', array( 'sanitize_callback' => 'absint', 'default' => 2 ) );
        register_setting( 'aidoforyou_metadata_opts', 'afy_meta_max_mb', array( 'sanitize_callback' => 'absint', 'default' => 5 ) );

        add_settings_section( 'afy_meta_api_section', __( 'AI API Configuration', 'aidoforyou-metadata' ), null, 'aidoforyou-metadata' );
        
        // Panggil UI API Keys Builder
        add_settings_field( 'afy_meta_api_keys', __( 'API Keys Manager (Rotation)', 'aidoforyou-metadata' ), array( $this, 'render_api_keys_builder' ), 'aidoforyou-metadata', 'afy_meta_api_section' );
        add_settings_field( 'afy_meta_gemini_api_base', __( 'API Base URL (Proxy)', 'aidoforyou-metadata' ), array( $this, 'render_text_field' ), 'aidoforyou-metadata', 'afy_meta_api_section', array( 'id' => 'afy_meta_gemini_api_base', 'default' => '', 'help' => 'Optional. Leave blank to bypass proxy.' ) );
        
        add_settings_field( 'afy_meta_media_resolution', __( 'Media Resolution', 'aidoforyou-metadata' ), array( $this, 'render_resolution_dropdown' ), 'aidoforyou-metadata', 'afy_meta_api_section' );
        add_settings_field( 'afy_meta_google_search', __( 'Google Search Grounding', 'aidoforyou-metadata' ), array( $this, 'render_google_search_toggle' ), 'aidoforyou-metadata', 'afy_meta_api_section' );
        
        add_settings_field( 'afy_meta_api_timeout', __( 'API Timeout', 'aidoforyou-metadata' ), array( $this, 'render_number_field' ), 'aidoforyou-metadata', 'afy_meta_api_section', array( 'id' => 'afy_meta_api_timeout', 'default' => 120, 'suffix' => 'Seconds' ) );
        add_settings_field( 'afy_meta_max_retries', __( 'Max Auto Retries', 'aidoforyou-metadata' ), array( $this, 'render_number_field' ), 'aidoforyou-metadata', 'afy_meta_api_section', array( 'id' => 'afy_meta_max_retries', 'default' => 3, 'suffix' => 'Times (Exponential Backoff)' ) );

        add_settings_field( 'afy_meta_models_config', __( 'AI Models Manager', 'aidoforyou-metadata' ), array( $this, 'render_models_builder' ), 'aidoforyou-metadata', 'afy_meta_api_section' );

        add_settings_section( 'afy_meta_rules_section', __( 'Pricing & Rules', 'aidoforyou-metadata' ), null, 'aidoforyou-metadata' );
        add_settings_field( 'afy_meta_credit_cost', __( 'Credit Cost Per Process', 'aidoforyou-metadata' ), array( $this, 'render_number_field' ), 'aidoforyou-metadata', 'afy_meta_rules_section', array( 'id' => 'afy_meta_credit_cost', 'default' => 2, 'suffix' => 'Credit(s)' ) );
        add_settings_field( 'afy_meta_max_mb', __( 'Max Upload Size (MB)', 'aidoforyou-metadata' ), array( $this, 'render_number_field' ), 'aidoforyou-metadata', 'afy_meta_rules_section', array( 'id' => 'afy_meta_max_mb', 'default' => 5, 'suffix' => 'MB' ) );
        add_settings_field( 'afy_meta_system_prompt', __( 'Master System Prompt', 'aidoforyou-metadata' ), array( $this, 'render_prompt_textarea' ), 'aidoforyou-metadata', 'afy_meta_rules_section' );
    }

    public function render_text_field( array $args ): void {
        $val  = get_option( $args['id'], $args['default'] ?? '' );
        $type = $args['type'] ?? 'text';
        printf( '<input type="%s" id="%s" name="%s" value="%s" class="regular-text" />', esc_attr( $type ), esc_attr( $args['id'] ), esc_attr( $args['id'] ), esc_attr( $val ) );
        if ( ! empty( $args['help'] ) ) echo '<p class="description">' . esc_html( $args['help'] ) . '</p>';
    }

    public function render_number_field( array $args ): void {
        $val = get_option( $args['id'], $args['default'] );
        printf( '<input type="number" id="%s" name="%s" value="%s" class="small-text" min="1" step="1" /> %s', esc_attr( $args['id'] ), esc_attr( $args['id'] ), esc_attr( $val ), esc_html( $args['suffix'] ) );
    }

    // FUNGSI BARU: UI Builder untuk API Keys Rotation
    public function render_api_keys_builder(): void {
        $json_val = get_option( 'afy_meta_api_keys', '[]' );
        
        // Fallback migrasi jika user masih menggunakan opsi lama afy_meta_gemini_api_key
        $old_key = get_option( 'afy_meta_gemini_api_key', '' );
        if ( $json_val === '[]' && ! empty( $old_key ) ) {
            $json_val = json_encode( array( $old_key ) );
        }
        ?>
        <div id="afy-apikeys-builder-wrap" style="background:#f8fafc; padding:15px; border:1px solid #cbd5e1; border-radius:8px; max-width: 600px;">
            <div id="afy-apikeys-rows" style="display:flex; flex-direction:column; gap:8px; margin-bottom:15px;"></div>
            <button type="button" id="afy-btn-add-apikey" class="button button-secondary">+ Add API Key</button>
            <input type="hidden" id="afy_meta_api_keys" name="afy_meta_api_keys" value="<?php echo esc_attr($json_val); ?>">
            <p class="description" style="margin-top:10px;">Provide multiple API Keys to enable <b>Auto Rotation</b>. If the first key hits a quota limit (Free Tier Limit), the system will seamlessly switch to the next key.</p>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('afy-apikeys-rows');
            const inputHidden = document.getElementById('afy_meta_api_keys');
            const btnAdd = document.getElementById('afy-btn-add-apikey');
            
            let keys = [];
            try { keys = JSON.parse(inputHidden.value); } catch(e) { keys = []; }
            if(!Array.isArray(keys)) keys = [];

            function renderRows() {
                container.innerHTML = '';
                keys.forEach((keyStr, index) => {
                    const row = document.createElement('div');
                    row.style.cssText = 'display:flex; gap:10px; align-items:center; background:#fff; padding:8px 12px; border:1px solid #e2e8f0; border-radius:6px;';
                    
                    row.innerHTML = `
                        <span style="font-weight:bold; color:#94a3b8; font-size:12px;">#${index+1}</span>
                        <input type="password" class="k-val" value="${keyStr}" placeholder="AI Studio API Key" style="flex:1;">
                        <button type="button" class="button k-remove" style="color:#d63638; border-color:#d63638;">X</button>
                    `;

                    row.querySelector('.k-val').addEventListener('input', (e) => { keys[index] = e.target.value; updateJSON(); });
                    row.querySelector('.k-remove').addEventListener('click', () => {
                        keys.splice(index, 1);
                        renderRows(); updateJSON();
                    });

                    container.appendChild(row);
                });
            }

            function updateJSON() { inputHidden.value = JSON.stringify(keys); }

            btnAdd.addEventListener('click', () => {
                keys.push('');
                renderRows(); updateJSON();
            });

            renderRows();
        });
        </script>
        <?php
    }

    public function render_resolution_dropdown(): void {
        $val = get_option( 'afy_meta_media_resolution', 'MEDIA_RESOLUTION_HIGH' );
        $options = array(
            'default'                 => 'Default (AI decides)',
            'MEDIA_RESOLUTION_LOW'    => 'Low Resolution (Faster, cheaper)',
            'MEDIA_RESOLUTION_MEDIUM' => 'Medium Resolution',
            'MEDIA_RESOLUTION_HIGH'   => 'High Resolution (Best for small details in Microstock)'
        );
        echo '<select id="afy_meta_media_resolution" name="afy_meta_media_resolution">';
        foreach ( $options as $key => $label ) {
            printf( '<option value="%s" %s>%s</option>', esc_attr( $key ), selected( $val, $key, false ), esc_html( $label ) );
        }
        echo '</select>';
    }

    public function render_google_search_toggle(): void {
        $val = get_option( 'afy_meta_google_search', 'yes' );
        echo '<label><input type="radio" name="afy_meta_google_search" value="yes" ' . checked($val, 'yes', false) . '> Enable</label><br>';
        echo '<label><input type="radio" name="afy_meta_google_search" value="no" ' . checked($val, 'no', false) . '> Disable</label>';
        echo '<p class="description">If enabled, the AI can search the internet in real-time to verify facts in your images.</p>';
    }

    public function render_models_builder(): void {
        $default_models = '[{"id":"gemini-3.1-flash-lite","label":"Lite","premium":false,"default":true,"thinking":""},{"id":"gemini-3-flash-preview","label":"Flash","premium":false,"default":false,"thinking":""},{"id":"gemini-3.1-pro-preview","label":"Pro","premium":true,"default":false,"thinking":"high"}]';
        $json_val = get_option( 'afy_meta_models_config', $default_models );
        ?>
        <div id="afy-model-builder-wrap" style="background:#f8fafc; padding:15px; border:1px solid #cbd5e1; border-radius:8px; max-width: 820px;">
            <div id="afy-model-rows" style="display:flex; flex-direction:column; gap:10px; margin-bottom:15px;"></div>
            <button type="button" id="afy-btn-add-model" class="button button-secondary">+ Add New Model</button>
            <input type="hidden" id="afy_meta_models_config" name="afy_meta_models_config" value="<?php echo esc_attr($json_val); ?>">
            <p class="description" style="margin-top:10px;">Configure the models available to your users.</p>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('afy-model-rows');
            const inputHidden = document.getElementById('afy_meta_models_config');
            const btnAdd = document.getElementById('afy-btn-add-model');
            
            let models = [];
            try { models = JSON.parse(inputHidden.value); } catch(e) { models = []; }
            if(!Array.isArray(models)) models = [];

            function renderRows() {
                container.innerHTML = '';
                models.forEach((m, index) => {
                    if(typeof m.thinking === 'undefined') m.thinking = ''; 
                    
                    const row = document.createElement('div');
                    row.style.cssText = 'display:flex; gap:10px; align-items:center; background:#fff; padding:10px; border:1px solid #e2e8f0; border-radius:6px; flex-wrap:wrap;';
                    
                    row.innerHTML = `
                        <label><span style="display:block;font-size:11px;color:#64748b;">Original ID</span><input type="text" class="m-id" value="${m.id}" placeholder="e.g. gemini-flash-latest" style="width:160px;"></label>
                        <label><span style="display:block;font-size:11px;color:#64748b;">Display Label</span><input type="text" class="m-label" value="${m.label}" placeholder="e.g. Pro" style="width:80px;"></label>
                        <label><span style="display:block;font-size:11px;color:#64748b;">Thinking (v3+)</span>
                            <select class="m-thinking" style="width:95px; font-size:13px; height:30px;">
                                <option value="" ${m.thinking === '' ? 'selected' : ''}>Default</option>
                                <option value="minimal" ${m.thinking === 'minimal' ? 'selected' : ''}>Minimal</option>
                                <option value="low" ${m.thinking === 'low' ? 'selected' : ''}>Low</option>
                                <option value="medium" ${m.thinking === 'medium' ? 'selected' : ''}>Medium</option>
                                <option value="high" ${m.thinking === 'high' ? 'selected' : ''}>High</option>
                            </select>
                        </label>
                        <label style="margin-top:14px; display:flex; align-items:center; gap:4px; font-size:12px; cursor:pointer;"><input type="checkbox" class="m-premium" ${m.premium ? 'checked' : ''}> Premium</label>
                        <label style="margin-top:14px; display:flex; align-items:center; gap:4px; font-size:12px; cursor:pointer;"><input type="radio" name="m_default" class="m-default" ${m.default ? 'checked' : ''}> Default</label>
                        <button type="button" class="button m-remove" style="margin-top:14px; color:#d63638; border-color:#d63638;">X</button>
                    `;

                    row.querySelector('.m-id').addEventListener('input', (e) => { m.id = e.target.value; updateJSON(); });
                    row.querySelector('.m-label').addEventListener('input', (e) => { m.label = e.target.value; updateJSON(); });
                    row.querySelector('.m-thinking').addEventListener('change', (e) => { m.thinking = e.target.value; updateJSON(); });
                    row.querySelector('.m-premium').addEventListener('change', (e) => { m.premium = e.target.checked; updateJSON(); });
                    row.querySelector('.m-default').addEventListener('change', (e) => {
                        models.forEach(mod => mod.default = false);
                        m.default = true;
                        updateJSON();
                    });
                    row.querySelector('.m-remove').addEventListener('click', () => {
                        models.splice(index, 1);
                        renderRows(); updateJSON();
                    });

                    container.appendChild(row);
                });
            }

            function updateJSON() { inputHidden.value = JSON.stringify(models); }

            btnAdd.addEventListener('click', () => {
                models.push({ id: '', label: 'New', premium: false, default: models.length === 0, thinking: '' });
                renderRows(); updateJSON();
            });

            renderRows();
        });
        </script>
        <?php
    }

    public function render_prompt_textarea(): void {
        $default_prompt = "";

        $val = get_option( 'afy_meta_system_prompt', $default_prompt );
        printf( '<textarea id="afy_meta_system_prompt" name="afy_meta_system_prompt" rows="18" style="width:100%%; font-family:monospace; font-size:13px;" placeholder="Paste your Master System Prompt here...">%s</textarea>', esc_textarea( $val ) );
        echo '<p class="description">Masukkan pedoman Microstock (System Prompt) Anda di sini. OUTPUT FORMAT (JSON Schema) telah dikunci secara dinamis di dalam API Backend.</p>';
    }

    public function enqueue_assets( $hook ) {
        if ( 'aidoforyou_page_aidoforyou-metadata' !== $hook ) return;
        wp_enqueue_script( 'afy-meta-admin', AIDOFORYOU_META_URL . 'assets/js/admin.js', array(), AIDOFORYOU_META_VERSION, true );
        wp_localize_script( 'afy-meta-admin', 'AFY_META_ADMIN', array( 'rest_url' => esc_url_raw( rest_url( 'aidoforyou-metadata/v1' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ) ) );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Microstock Metadata Settings', 'aidoforyou-metadata' ); ?></h1>
            <hr class="wp-header-end">
            <?php settings_errors(); ?>
            <div style="display: grid; grid-template-columns: 1fr 400px; gap: 20px; margin-top: 20px;">
                <div class="card" style="max-width: 100%; margin-top: 0; padding: 10px 20px 20px;">
                    <form method="post" action="options.php">
                        <?php settings_fields( 'aidoforyou_metadata_opts' ); do_settings_sections( 'aidoforyou-metadata' ); submit_button(); ?>
                    </form>
                </div>
                <div class="card" style="margin-top: 0; padding: 20px; background: #fff;">
                    <h2><?php esc_html_e( 'AI API Tester', 'aidoforyou-metadata' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Test the API connection. AI will automatically use the Default Model and follow your Master System Prompt above.', 'aidoforyou-metadata' ); ?></p>
                    <div style="margin-top: 15px;"><textarea id="afy-test-prompt" rows="3" style="width:100%;" placeholder="e.g. Please explain what microstock is in one sentence."></textarea></div>
                    <div style="margin-top: 15px;">
                        <button type="button" id="afy-btn-test-api" class="button button-secondary" style="width:100%;">
                            <span class="dashicons dashicons-format-chat" style="margin-top:4px;"></span> <?php esc_html_e( 'Send', 'aidoforyou-metadata' ); ?>
                        </button>
                    </div>
                    <div id="afy-test-result" style="margin-top:20px; padding:12px; background:#f6f7f7; border-left:4px solid #cbd5e1; display:none; white-space: pre-wrap; font-family: monospace; font-size:13px; max-height:300px; overflow-y:auto;"></div>
                </div>
            </div>
        </div>
        <?php
    }
}