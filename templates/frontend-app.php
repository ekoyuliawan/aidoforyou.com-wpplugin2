<?php
/**
 * Frontend App HTML Template.
 *
 * @package AIdoforyouMetadata
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$max_mb = (int) get_option('afy_meta_max_mb', 5);
$cost   = (int) get_option('afy_meta_credit_cost', 2);
?>
<div id="afy-meta-app">
    <div class="afy-meta-topbar">
        <div class="afy-meta-brand">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            AIdoforyou Metadata
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <div class="afy-meta-account-id-pill" style="font-size:12px; color:#64748b; background:#f1f5f9; padding:5px 12px; border-radius:999px; border:1px solid #e2e8f0; font-family:monospace; cursor:pointer;" title="Click to copy Account ID">
                ID: <span id="afy-meta-account-id-text">Loading...</span>
            </div>
            
            <div class="afy-meta-credits-pill">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <span id="afy-meta-credits-text"><?php esc_html_e( 'Checking credits...', 'aidoforyou-metadata' ); ?></span>
            </div>
        </div>
    </div>

    <div id="afy-meta-alert-box" class="afy-meta-alert" style="display:none;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <span id="afy-meta-alert-msg"></span>
    </div>

    <div id="afy-meta-state-upload" class="afy-meta-workspace afy-meta-fade-in" style="padding: 40px;">
        <h2 class="afy-meta-section-heading" style="border:none; text-align:center; font-size:1.4rem; margin-bottom:20px;">
            <?php esc_html_e( 'Generate Microstock Metadata with AI', 'aidoforyou-metadata' ); ?>
        </h2>
        
        <div class="afy-meta-tabs-wrap">
            <button type="button" class="afy-meta-tab-btn active" data-tab="image">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                By Image
            </button>
            <button type="button" class="afy-meta-tab-btn" data-tab="text">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                By Text
            </button>
        </div>

        <div id="afy-meta-area-image" class="afy-meta-tab-content active">
            <div id="afy-meta-dz" class="afy-meta-dropzone">
                <svg class="afy-meta-dz-icon" width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                <p class="afy-meta-dz-title"><?php esc_html_e( 'Click or drag an image here to analyze', 'aidoforyou-metadata' ); ?></p>
                <p class="afy-meta-dz-meta"><?php printf( esc_html__( 'Supports JPG, PNG, WEBP (Max %dMB)', 'aidoforyou-metadata' ), $max_mb ); ?></p>
                <input type="file" id="afy-meta-file-input" accept="image/jpeg, image/png, image/webp" style="display:none;" />
                <button type="button" class="afy-meta-btn afy-meta-btn-secondary" style="margin-top:10px;" onclick="document.getElementById('afy-meta-file-input').click();">
                    <?php esc_html_e( 'Browse Files', 'aidoforyou-metadata' ); ?>
                </button>
            </div>
        </div>

        <div id="afy-meta-area-text" class="afy-meta-tab-content" style="display:none;">
            <p style="text-align:center; color:#64748b; font-size:14px; margin-bottom:15px;">Paste a concept, keyword idea, or prompt to generate metadata.</p>
            <textarea id="afy-meta-text-input" class="afy-meta-input" rows="6" placeholder="e.g. A futuristic cyberpunk city at night with neon lights and flying cars..."></textarea>
            <button type="button" id="afy-meta-text-submit-btn" class="afy-meta-btn afy-meta-btn-primary afy-meta-btn-full" style="margin-top:15px; padding:12px;">
                Proceed
            </button>
        </div>
    </div>

    <!-- INTERNAL WORKSPACE GRID -->
    <div id="afy-meta-state-workspace" class="afy-meta-workspace" style="display:none; padding: 0; border:none; background:transparent; box-shadow:none;">
        <div class="afy-meta-main-grid">
            
            <!-- KOLOM KIRI (Smart Sticky) -->
            <div class="afy-meta-persistent-image-col afy-meta-workspace afy-meta-fade-in afy-meta-sticky-col">
                <!-- Preview Full untuk Gambar -->
                <div id="afy-meta-preview-image-wrap" class="afy-meta-thumb-wrap">
                    <img id="afy-meta-preview-img" src="" alt="Preview" />
                    <button type="button" class="afy-meta-thumb-remove afy-meta-cancel-btn" title="Remove">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                
                <!-- Preview Box untuk Teks -->
                <div id="afy-meta-preview-text-wrap" class="afy-meta-thumb-wrap" style="display:none; padding:20px; background:#f1f5f9; text-align:left;">
                    <div style="color:#10b981; margin-bottom:10px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    </div>
                    <p id="afy-meta-preview-text-snippet" style="font-size:13px; color:#475569; font-style:italic; line-height:1.5; margin:0 0 10px 0; overflow:hidden; display:-webkit-box; -webkit-line-clamp:5; -webkit-box-orient:vertical;"></p>
                    <button type="button" class="afy-meta-btn afy-meta-btn-secondary afy-meta-cancel-btn" style="width:100%; padding:6px; font-size:12px;">Edit Text</button>
                </div>

                <div class="afy-meta-image-info">
                    <p id="afy-meta-file-name" class="afy-meta-filename-text"></p>
                    <p class="afy-meta-image-tip"><?php esc_html_e( 'AI will expand this into Adobe Stock metadata.', 'aidoforyou-metadata' ); ?></p>
                </div>

                <!-- NEW: Reference Image Grid (Kiri & Vertikal) -->
                <div id="afy-meta-market-references-wrap" style="display:none; margin-top:20px; padding-top:20px; border-top:1px dashed #cbd5e1;">
                    <p style="font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.05em; margin:0 0 5px 0;">Market References</p>
                    <p id="afy-meta-search-query-text" style="font-size:13px; color:#10b981; font-weight:600; margin:0 0 12px 0; word-break:break-all;"></p>
                    
                    <!-- 1 Kolom Vertikal Grid -->
                    <div id="afy-meta-market-references-grid" style="display:grid; grid-template-columns: 1fr; gap:10px;">
                        <!-- Images injected via JS -->
                    </div>
                </div>
            </div>
            
            <!-- KOLOM KANAN -->
            <div class="afy-meta-dynamic-panel-col">
                <div id="afy-meta-panel-settings" class="afy-meta-workspace afy-meta-fade-in">
                    <h3 class="afy-meta-section-heading" style="border:none; margin-bottom:15px;"><?php esc_html_e( 'Extraction Settings', 'aidoforyou-metadata' ); ?></h3>
                    
                    <div class="afy-meta-field">
                        <label class="afy-meta-field-label"><?php esc_html_e( 'AI Model', 'aidoforyou-metadata' ); ?></label>
                        <div id="afy-meta-model-selection" class="afy-meta-model-group"></div>
                    </div>

                    <!-- STRATEGY SELECTION -->
                    <div class="afy-meta-field" style="margin-top:24px;">
                        <label class="afy-meta-field-label" style="display:flex; justify-content:space-between; align-items:center;">
                            Variation Strategy
                            <span class="afy-meta-hint" style="margin:0; font-size:11px;">Choose how AI targets the market</span>
                        </label>
                        
                        <div class="afy-meta-strategy-toggle">
                            <label class="afy-meta-strat-label">
                                <input type="radio" name="afy_strategy" value="dynamic" checked>
                                <span class="afy-meta-strat-btn">Dynamic (AI Logic)</span>
                            </label>
                            <label class="afy-meta-strat-label">
                                <input type="radio" name="afy_strategy" value="market">
                                <span class="afy-meta-strat-btn">Market-Proven (Data)</span>
                            </label>
                        </div>
                    </div>

                    <!-- MARKET-PROVEN SLIDER -->
                    <div id="afy-meta-market-slider-wrap" class="afy-meta-field" style="margin-top:12px; display:none; background:#f8fafc; padding:16px; border-radius:12px; border:1px solid #e2e8f0; animation: afy-meta-fade-down 0.2s ease-out;">
                        <label class="afy-meta-field-label" style="display:flex; justify-content:space-between; margin-bottom:12px; font-size:13px;">
                            <span>Target Variations</span>
                            <span id="afy-meta-slider-val-display" style="color:#10b981; font-weight:800;">2</span>
                        </label>
                        <div class="afy-meta-slider-container">
                            <input type="range" id="afy-meta-var-slider" min="2" max="6" step="2" value="2" class="afy-meta-range">
                            <div class="afy-meta-slider-ticks">
                                <span>2</span>
                                <span>4</span>
                                <span class="<?php echo is_user_logged_in() ? '' : 'afy-locked-tick'; ?>" title="<?php echo is_user_logged_in() ? '' : 'Login to unlock 6 variations'; ?>">
                                    6
                                    <?php if(!is_user_logged_in()): ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left:2px; vertical-align:-1px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <p class="afy-meta-hint" style="margin-top:12px; font-size:11px; text-align:center;">AI will analyze top-selling Adobe Stock data to generate these variants.</p>
                    </div>

                    <div class="afy-meta-field" style="margin-top:20px;">
                        <label class="afy-meta-field-label"><?php esc_html_e( 'Generation Prompt (Optional)', 'aidoforyou-metadata' ); ?></label>
                        <p class="afy-meta-hint"><?php esc_html_e( 'Did you generate this image with AI? Paste the prompt here to help the AI understand specific details.', 'aidoforyou-metadata' ); ?></p>
                        <textarea id="afy-meta-user-prompt" class="afy-meta-input" rows="4" placeholder="e.g. A hyper-realistic photo of a futuristic city..."></textarea>
                    </div>
                    
                    <div style="margin-top:28px;">
                        <button type="button" id="afy-meta-extract-btn" class="afy-meta-btn afy-meta-btn-primary afy-meta-btn-full afy-meta-btn-lg">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            <span id="afy-meta-btn-text"><?php printf( esc_html__( 'Generate Metadata (%d Credits)', 'aidoforyou-metadata' ), $cost ); ?></span>
                        </button>
                    </div>
                </div>

                <div id="afy-meta-panel-processing" class="afy-meta-workspace afy-meta-proc-wrap afy-meta-fade-in" style="display:none; text-align:center; padding: 80px 36px;">
                    <div class="afy-meta-spinner-ring"></div>
                    <h3 class="afy-meta-proc-title" style="display:flex; align-items:center; justify-content:center; gap:8px;">
                        <?php esc_html_e( 'Analyzing Pixels...', 'aidoforyou-metadata' ); ?>
                        <span id="afy-meta-server-indicator" style="font-size: 12px; font-weight: 700; color: #10b981; background: #ecfdf5; padding: 3px 10px; border-radius: 12px; border: 1px solid #a7f3d0;">Server 1</span>
                    </h3>
                    <p class="afy-meta-proc-desc" style="line-height: 1.5; margin-top:10px;"><?php esc_html_e( 'AI is generating perfectly formatted Adobe Stock metadata.', 'aidoforyou-metadata' ); ?></p>
                </div>

                <div id="afy-meta-panel-result" class="afy-meta-workspace afy-meta-fade-in" style="display:none; padding: 24px;">
                    <div class="afy-meta-result-header">
                        <div class="afy-meta-check-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </div>
                        <div>
                            <h3 class="afy-meta-result-title"><?php esc_html_e( 'Metadata Generated!', 'aidoforyou-metadata' ); ?></h3>
                            <p id="afy-meta-generation-info" style="font-size: 12px; color: #64748b; margin: 4px 0 0 0; font-weight: 500;"></p>
                        </div>
                        <div style="margin-left:auto;">
                            <button type="button" id="afy-meta-reset-btn" class="afy-meta-btn afy-meta-btn-secondary" style="padding: 7px 15px; font-size: .8rem;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                                <?php esc_html_e( 'Process Another', 'aidoforyou-metadata' ); ?>
                            </button>
                        </div>
                    </div>

                    <div class="afy-meta-blocks">
                        <div class="afy-meta-block">
                            <div class="afy-meta-block-header">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
                                <strong>Reverse Prompt</strong>
                                <button class="afy-meta-copy-icon-btn" data-target="afy-meta-res-reverse" title="Copy"></button>
                            </div>
                            <div style="padding: 12px; background: transparent;">
                                <textarea id="afy-meta-res-reverse" class="afy-meta-result-textarea" readonly style="padding:0; resize:none; overflow:hidden; font-size:13px;"></textarea>
                            </div>
                        </div>

                        <div class="afy-meta-block">
                            <div class="afy-meta-block-header">
                                <div>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b; margin-right:4px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                    <strong>Commercial Positioning</strong>
                                </div>
                            </div>
                            <div style="padding: 12px; background: transparent;">
                                <textarea id="afy-meta-res-commercial" class="afy-meta-result-textarea" readonly rows="1" style="padding:0; resize:none; overflow:hidden; font-size:13px;"></textarea>
                            </div>
                        </div>
                        
                        <div class="afy-meta-block">
                            <div class="afy-meta-block-header">
                                <div>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b; margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 16 16 12 12 8"></polyline><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                    <!-- ID ditambahkan agar label bisa diubah JS (Dynamic vs Market) -->
                                    <strong id="afy-meta-elasticity-label">Commercial Elasticity</strong>
                                </div>
                            </div>
                            <div style="padding: 12px; background: transparent;">
                                <textarea id="afy-meta-res-elasticity" class="afy-meta-result-textarea" readonly rows="1" style="padding:0; resize:none; overflow:hidden; font-size:13px;"></textarea>
                            </div>
                        </div>
                        
                        <div style="display:flex; gap:12px;">
                            <div class="afy-meta-block" style="flex:1;">
                                <div class="afy-meta-block-header">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                    <strong>Media Type</strong>
                                    <button class="afy-meta-copy-icon-btn" data-target="afy-meta-res-media" title="Copy"></button>
                                </div>
                                <div style="padding: 12px; background: transparent;">
                                    <textarea id="afy-meta-res-media" class="afy-meta-result-textarea" readonly rows="1" style="padding:0; resize:none; overflow:hidden; font-size:13px;"></textarea>
                                </div>
                            </div>

                            <div class="afy-meta-block" style="flex:1;">
                                <div class="afy-meta-block-header">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                    <strong>Category</strong>
                                    <button class="afy-meta-copy-icon-btn" data-target="afy-meta-res-category" title="Copy"></button>
                                </div>
                                <div style="padding: 12px; background: transparent;">
                                    <textarea id="afy-meta-res-category" class="afy-meta-result-textarea" readonly rows="1" style="padding:0; resize:none; overflow:hidden; font-size:13px;"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="afy-meta-block">
                            <div class="afy-meta-block-header">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="8" y2="18"></line><line x1="16" y1="14" x2="8" y2="14"></line></svg>
                                <strong>File Name</strong>
                                <button class="afy-meta-copy-icon-btn" data-target="afy-meta-res-filename" title="Copy"></button>
                            </div>
                            <div style="padding: 12px; background: transparent;">
                                <textarea id="afy-meta-res-filename" class="afy-meta-result-textarea" readonly rows="1" style="padding:0; resize:none; overflow:hidden;"></textarea>
                            </div>
                        </div>

                        <div class="afy-meta-block">
                            <div class="afy-meta-block-header">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                                <strong>Title</strong>
                                <button class="afy-meta-copy-icon-btn" data-target="afy-meta-res-title" title="Copy"></button>
                            </div>
                            <div style="padding: 12px; background: transparent;">
                                <textarea id="afy-meta-res-title" class="afy-meta-result-textarea" readonly style="padding:0; resize:none; overflow:hidden;"></textarea>
                            </div>
                        </div>

                        <div class="afy-meta-block">
                            <div class="afy-meta-block-header">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                                <strong>Keywords</strong>
                                <button class="afy-meta-copy-icon-btn" data-target="afy-meta-res-keywords" title="Copy"></button>
                            </div>
                            <div style="padding: 12px; background: transparent;">
                                <textarea id="afy-meta-res-keywords" class="afy-meta-result-textarea" readonly style="padding:0; resize:none; overflow:hidden;"></textarea>
                            </div>
                        </div>

                        <div id="afy-meta-res-variations-container" style="display: flex; flex-direction: column; gap: 12px;"></div>
                    </div>
                </div>

            </div>
        </div>

    </div>
    
    <!-- LIGHTBOX OVERLAY UNTUK ZOOM GAMBAR -->
    <div id="afy-meta-lightbox" class="afy-meta-modal-overlay" style="display:none; z-index:999999; cursor:zoom-out;">
        <img id="afy-meta-lightbox-img" src="" style="max-width:90vw; max-height:90vh; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.5);">
    </div>
	
	<div id="afy-meta-fallback-modal" class="afy-meta-modal-overlay" style="display:none;">
        <div class="afy-meta-modal-box">
            <div class="afy-meta-modal-glow"></div>
            <div class="afy-meta-modal-content">
                <div class="afy-meta-modal-icon-wrap">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </div>
                <h3 class="afy-meta-modal-title">Model Unavailable</h3>
                <p id="afy-meta-fallback-msg" class="afy-meta-modal-desc"></p>
                <div class="afy-meta-modal-offer">
                    <div class="afy-offer-text" style="margin-bottom: 12px;">Select an available alternative:</div>
                    <select id="afy-meta-fallback-select"></select>
                </div>
                <div class="afy-meta-modal-actions">
                    <button id="afy-meta-fallback-no" class="afy-meta-btn afy-meta-btn-secondary">Cancel</button>
                    <button id="afy-meta-fallback-yes" class="afy-meta-btn afy-meta-btn-primary">Switch & Retry</button>
                </div>
            </div>
        </div>
    </div>
	
</div>