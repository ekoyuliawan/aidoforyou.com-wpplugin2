/* global AFY_META_APP */
(function () {
    'use strict';

    var CORE_REST = AFY_META_APP.core_rest;
    var META_REST = AFY_META_APP.meta_rest;
    var NONCE     = AFY_META_APP.nonce;
    var MAX_MB    = AFY_META_APP.max_mb || 5;
    var COST      = AFY_META_APP.cost || 2;
    var MODELS    = AFY_META_APP.models || [];
    var IS_LOGGED = AFY_META_APP.is_logged_in;
    var USER_ID   = AFY_META_APP.user_id; // Dari WP Logged In User

    function getGuestToken() {
        try {
            var t = localStorage.getItem('afy_guest_token');
            if (!t || !/^[a-zA-Z0-9]{32}$/.test(t)) {
                t = crypto.randomUUID().replace(/-/g, '');
                localStorage.setItem('afy_guest_token', t);
            }
            return t;
        } catch (e) { return crypto.randomUUID().replace(/-/g, ''); }
    }

    var GUEST_TOKEN = getGuestToken();
    var ACCOUNT_ID  = IS_LOGGED ? USER_ID : GUEST_TOKEN; // ID Unik untuk UI

    var currentCredits = 0;
    var selectedFile = null;
    var activeModelId = '';
    var activeTab = 'image';

    function q(id)         { return document.getElementById(id); }
    function setText(el,t) { if (el) el.textContent = t; }
    function show(el)      { if (el) el.style.display = ''; }
    function hide(el)      { if (el) el.style.display = 'none'; }
    function showFlex(el)  { if (el) el.style.display = 'flex'; }
    
    function apiHeaders() { 
        var headers = { 'X-WP-Nonce': NONCE };
        if (!IS_LOGGED) {
            headers['X-AIDOFORYOU-Token'] = GUEST_TOKEN;
        }
        return headers;
    }

    var D = {
        accountIdText:q('afy-meta-account-id-text'),
        creditsText:  q('afy-meta-credits-text'),
        alertBox:     q('afy-meta-alert-box'),
        alertMsg:     q('afy-meta-alert-msg'),
        
        sUpload:      q('afy-meta-state-upload'),
        sWorkspace:   q('afy-meta-state-workspace'), 
        pSettings:    q('afy-meta-panel-settings'),
        pProcessing:  q('afy-meta-panel-processing'),
        pResult:      q('afy-meta-panel-result'),
        
        tabBtns:      document.querySelectorAll('.afy-meta-tab-btn'),
        areaImage:    q('afy-meta-area-image'),
        areaText:     q('afy-meta-area-text'),
        textInputRaw: q('afy-meta-text-input'),
        textSubmitBtn:q('afy-meta-text-submit-btn'),

        dz:           q('afy-meta-dz'),
        fileInput:    q('afy-meta-file-input'),
        
        prevImgWrap:  q('afy-meta-preview-image-wrap'),
        prevImg:      q('afy-meta-preview-img'),
        prevTextWrap: q('afy-meta-preview-text-wrap'),
        prevTextSnip: q('afy-meta-preview-text-snippet'),
        fileName:     q('afy-meta-file-name'),
		
		refWrap:      q('afy-meta-market-references-wrap'),
        refGrid:      q('afy-meta-market-references-grid'),
        searchQuery:  q('afy-meta-search-query-text'),
        lightbox:     q('afy-meta-lightbox'),
        lightboxImg:  q('afy-meta-lightbox-img'),
        
        userPrompt:   q('afy-meta-user-prompt'),
        modelGroup:   q('afy-meta-model-selection'),
		
		stratRadios:  document.querySelectorAll('input[name="afy_strategy"]'),
        marketWrap:   q('afy-meta-market-slider-wrap'),
        varSlider:    q('afy-meta-var-slider'),
        sliderValDisp:q('afy-meta-slider-val-display'),
        
        cancelBtns:   document.querySelectorAll('.afy-meta-cancel-btn'),
        extractBtn:   q('afy-meta-extract-btn'),
        resetBtn:     q('afy-meta-reset-btn'),
        
        resReverse:   q('afy-meta-res-reverse'),
        resCommercial:q('afy-meta-res-commercial'),
		elasticityLbl:q('afy-meta-elasticity-label'),
        resElasticity:q('afy-meta-res-elasticity'),
        resMedia:     q('afy-meta-res-media'),
        resFilename:  q('afy-meta-res-filename'),
        resCategory:  q('afy-meta-res-category'),
        resTitle:     q('afy-meta-res-title'),
        resKeywords:  q('afy-meta-res-keywords'),
        varContainer: q('afy-meta-res-variations-container')
    };
	
	if (D.lightbox) {
        D.lightbox.addEventListener('click', function() {
            hide(D.lightbox);
        });
    }

    // --- TAMPILKAN ACCOUNT ID ---
    if (D.accountIdText) {
        // Tampilkan 8 karakter pertama jika Guest, tampilkan penuh jika angka (WP User ID)
        var displayId = IS_LOGGED ? ACCOUNT_ID : String(ACCOUNT_ID).substring(0, 8) + '...';
        D.accountIdText.textContent = displayId;
        
        // Fungsi Click-to-Copy pada Pill Box
        D.accountIdText.parentElement.addEventListener('click', function() {
            var tempInput = document.createElement("input");
            tempInput.value = ACCOUNT_ID; // Copy ID Penuh
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand("copy");
            document.body.removeChild(tempInput);
            
            var old = D.accountIdText.textContent;
            D.accountIdText.textContent = 'Copied!';
            D.accountIdText.parentElement.style.color = '#10b981';
            setTimeout(function(){ 
                D.accountIdText.textContent = old; 
                D.accountIdText.parentElement.style.color = '#64748b';
            }, 1500);
        });
    }

    function autoExpandTextarea(el) {
        if (!el) return;
        el.style.height = 'auto'; 
        var newHeight = el.scrollHeight;
        if (newHeight > 0) el.style.height = newHeight + 'px'; 
    }

    function parseRobustJSON(str) {
        var clean = str.replace(/^```(json)?|```$/gi, '').trim();
        var start = clean.indexOf('{');
        if (start === -1) throw new Error("No JSON object found in response.");
        clean = clean.substring(start);
        
        while (clean.length > 0) {
            try { return JSON.parse(clean); } catch (e) {
                var lastBrace = clean.lastIndexOf('}');
                if (lastBrace === -1 || lastBrace === 0) break;
                clean = clean.substring(0, lastBrace).trim();
                var newLastBrace = clean.lastIndexOf('}');
                if (newLastBrace !== -1) { clean = clean.substring(0, newLastBrace + 1); } else { break; }
            }
        }
        return JSON.parse(str.replace(/^```(json)?|```$/gi, '').trim());
    }

    function renderModels() {
        if (!D.modelGroup) return;
        D.modelGroup.innerHTML = '';
        
        MODELS.forEach(function(m) {
            var isLocked = m.premium && !IS_LOGGED;
            if (m.default && !isLocked && !activeModelId) activeModelId = m.id;
            
            var labelEl = document.createElement('label');
            labelEl.className = 'afy-meta-model-card' + (isLocked ? ' locked' : '') + (activeModelId === m.id ? ' active' : '');
            
            var inputHtml = '<input type="radio" name="ai_model" value="' + m.id + '" ' + (isLocked ? 'disabled' : '') + (activeModelId === m.id ? ' checked' : '') + '>';
            var nameHtml = '<span class="afy-meta-model-name">' + m.label + '</span>';
            var iconHtml = isLocked ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>' : '';
            
            labelEl.innerHTML = inputHtml + nameHtml + iconHtml;
            
            if (!isLocked) {
                labelEl.addEventListener('click', function() {
                    activeModelId = m.id;
                    document.querySelectorAll('.afy-meta-model-card').forEach(function(c) { c.classList.remove('active'); });
                    labelEl.classList.add('active');
                });
            }
            D.modelGroup.appendChild(labelEl);
        });
        
        if (!activeModelId && MODELS.length > 0 && (!MODELS[0].premium || IS_LOGGED)) {
            activeModelId = MODELS[0].id;
        }
    }
    renderModels();

    function setState(name) {
        if (name === 'upload') {
            show(D.sUpload); hide(D.sWorkspace);
            D.sUpload.classList.remove('afy-meta-fade-in'); void D.sUpload.offsetWidth; D.sUpload.classList.add('afy-meta-fade-in');
        } else {
            hide(D.sUpload); showFlex(D.sWorkspace); 
            hide(D.pSettings); hide(D.pProcessing); hide(D.pResult);
            var activePanel = (name === 'settings') ? D.pSettings : (name === 'processing' ? D.pProcessing : D.pResult);
            if (activePanel) {
                show(activePanel);
                activePanel.classList.remove('afy-meta-fade-in'); void activePanel.offsetWidth; activePanel.classList.add('afy-meta-fade-in');
            }
        }
    }

    function showError(msg) { setText(D.alertMsg, msg); showFlex(D.alertBox); }
    function hideError()    { hide(D.alertBox); }

    function updateCredits(n) {
        currentCredits = n;
        setText(D.creditsText, n + ' Credit' + (n === 1 ? '' : 's') + ' Remaining');
        if (D.extractBtn) D.extractBtn.disabled = (n < COST);
    }

    fetch(CORE_REST + '/credits', { headers: apiHeaders() })
        .then(function (r) { return r.json(); })
        .then(function (d) { updateCredits(d.credits || 0); })
        .catch(function () { setText(D.creditsText, '? Credits'); });

    D.tabBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            D.tabBtns.forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            activeTab = btn.getAttribute('data-tab');
            
            if (activeTab === 'image') {
                show(D.areaImage); hide(D.areaText);
            } else {
                hide(D.areaImage); show(D.areaText);
            }
        });
    });
	
	// --- STRATEGY & SLIDER LOGIC ---
    var activeStrategy = 'dynamic';
    var marketVarCount = '2';

    if (D.stratRadios.length > 0) {
        D.stratRadios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                activeStrategy = this.value;
                if (activeStrategy === 'market') {
                    show(D.marketWrap);
                } else {
                    hide(D.marketWrap);
                }
            });
        });
    }

    if (D.varSlider) {
        D.varSlider.addEventListener('input', function() {
            var val = parseInt(this.value, 10);
            
            // Logika Penguncian untuk Guest
            if (!IS_LOGGED && val === 6) {
                this.value = 4; // Kembalikan paksa ke 4
                showError('Please login/register to unlock 6 Market-Proven variations.');
                setTimeout(hideError, 4000); // Hilangkan error setelah 4 detik
                val = 4;
            }
            
            marketVarCount = val.toString();
            if(D.sliderValDisp) D.sliderValDisp.textContent = val;
        });
    }
	
    function handleFile(f) {
        if (!f) return;
        if (f.size > MAX_MB * 1024 * 1024) { showError('File is too large.'); return; }
        selectedFile = f;
        
        show(D.prevImgWrap); hide(D.prevTextWrap);
        if (D.prevImg) D.prevImg.src = URL.createObjectURL(f);
        setText(D.fileName, f.name);
        
        hideError();
        if (D.extractBtn) D.extractBtn.disabled = (currentCredits < COST);
        setState('settings');
    }

    // FIX: Validasi Panjang Teks (Setidaknya 3 kata dan 10 karakter)
    function handleText() {
        var txt = D.textInputRaw.value.trim();
        if (!txt) { 
            showError('Please enter a concept or prompt.'); 
            return; 
        }
        
        var wordCount = txt.split(/\s+/).length;
        if (txt.length < 10 || wordCount < 3) {
            showError('Your text is too short. Please provide a more descriptive concept (at least 3 words) for accurate metadata generation.');
            return;
        }
        
        hide(D.prevImgWrap); show(D.prevTextWrap);
        D.prevTextSnip.textContent = '"' + txt + '"';
        setText(D.fileName, 'Text Concept');
        
        hideError();
        if (D.extractBtn) D.extractBtn.disabled = (currentCredits < COST);
        setState('settings');
    }

    if (D.fileInput) D.fileInput.addEventListener('change', function (e) { if (e.target.files && e.target.files[0]) handleFile(e.target.files[0]); });
    if (D.textSubmitBtn) D.textSubmitBtn.addEventListener('click', handleText);

    if (D.dz) {
        D.dz.addEventListener('click', function (e) { if (e.target !== D.fileInput && e.target.tagName !== 'BUTTON') { D.fileInput.click(); } });
        D.dz.addEventListener('dragover',  function (e) { e.preventDefault(); D.dz.classList.add('over'); });
        D.dz.addEventListener('dragleave', function ()  { D.dz.classList.remove('over'); });
        D.dz.addEventListener('drop',      function (e) {
            e.preventDefault(); D.dz.classList.remove('over');
            var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (f) handleFile(f);
        });
    }

    function resetApp() {
        selectedFile = null;
        if (D.fileInput) D.fileInput.value = '';
        if (D.userPrompt) D.userPrompt.value = '';
        hideError();
        setState('upload');
    }

    D.cancelBtns.forEach(function(btn) { btn.addEventListener('click', resetApp); });
    if (D.resetBtn) D.resetBtn.addEventListener('click', resetApp);

    var iconCopy = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
    var iconCheck = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';

    document.querySelectorAll('.afy-meta-copy-icon-btn').forEach(function(btn) {
        btn.innerHTML = iconCopy;
        btn.addEventListener('click', function() {
            var targetId = btn.getAttribute('data-target');
            var targetEl = document.getElementById(targetId);
            if (targetEl) {
                targetEl.select();
                document.execCommand('copy');
                btn.innerHTML = iconCheck;
                btn.classList.add('afy-copied');
                setTimeout(function() { 
                    btn.innerHTML = iconCopy; 
                    btn.classList.remove('afy-copied');
                }, 2000);
            }
        });
    });

    if (D.extractBtn) {
        function showFallbackModal(message, availableModels, onConfirm, onCancel) {
            var modal = document.getElementById('afy-meta-fallback-modal');
            var msgEl = document.getElementById('afy-meta-fallback-msg');
            var selectEl = document.getElementById('afy-meta-fallback-select');
            var btnYes = document.getElementById('afy-meta-fallback-yes');
            var btnNo = document.getElementById('afy-meta-fallback-no');

            if (!modal) return; 
            
            msgEl.textContent = message;
            
            selectEl.innerHTML = '';
            availableModels.forEach(function(model) {
                var opt = document.createElement('option');
                opt.value = model.id;
                opt.textContent = model.label;
                selectEl.appendChild(opt);
            });
            
            var newBtnYes = btnYes.cloneNode(true);
            var newBtnNo = btnNo.cloneNode(true);
            btnYes.parentNode.replaceChild(newBtnYes, btnYes);
            btnNo.parentNode.replaceChild(newBtnNo, btnNo);

            newBtnYes.addEventListener('click', function() {
                modal.style.display = 'none';
                if (onConfirm) {
                    var selectedId = document.getElementById('afy-meta-fallback-select').value;
                    onConfirm(selectedId);
                }
            });
            
            newBtnNo.addEventListener('click', function() {
                modal.style.display = 'none';
                if (onCancel) onCancel();
            });

            modal.style.display = 'flex';
        }

        D.extractBtn.addEventListener('click', function () {
            if (activeTab === 'image' && !selectedFile) return;
            if (currentCredits < COST) { showError('Not enough credits.'); return; }
            if (!activeModelId) { showError('Please select a valid AI model.'); return; }

            hideError();
            setState('processing');

            // FUNGSI EKSTRAKSI REKURSIF (Dengan Server Index Tracking)
            function performExtraction(modelToUse, failedModels, serverIndex) {
                if (!failedModels) failedModels = [];
                if (typeof serverIndex === 'undefined') serverIndex = 0;
                
                // Update Keterangan Server di UI (Misal: Server 1, Server 2)
                var srvInd = document.getElementById('afy-meta-server-indicator');
                if (srvInd) srvInd.textContent = 'Server ' + (serverIndex + 1);

                var fd = new FormData();
                if (activeTab === 'image') {
                    fd.append('image', selectedFile);
                } else {
                    fd.append('text_input', D.textInputRaw.value.trim());
                }
                
                fd.append('model', modelToUse);
                fd.append('failed_models', JSON.stringify(failedModels));
                fd.append('server_index', serverIndex); // Kirim index API Key yang ingin digunakan
				
				fd.append('strategy', activeStrategy);
                if (activeStrategy === 'market') {
                    fd.append('market_count', marketVarCount);
                }
                
                var extraPrompt = D.userPrompt ? D.userPrompt.value.trim() : '';
                if (extraPrompt) { fd.append('prompt', extraPrompt); }

                fetch(META_REST + '/extract', { method: 'POST', headers: apiHeaders(), body: fd })
                    .then(function (r) { 
                        var contentType = r.headers.get('content-type');
                        if (!contentType || contentType.indexOf('application/json') === -1) {
                            return r.text().then(function(txt) {
                                throw new Error("Network timeout or server error. The server took too long to respond. Please try again.");
                            });
                        }
                        return r.json().then(function (d) { return { ok: r.ok, data: d }; }); 
                    })
                    .then(function (res) {
                        if (!res.ok) throw new Error(res.data.message || 'Extraction failed.');

                        // --- LOGIKA ROTASI API KEY (SERVER) ---
                        if (res.data.code === 'switch_server') {
                            // Coba lagi dengan Index Server berikutnya, model tetap sama
                            performExtraction(modelToUse, failedModels, res.data.next_server_index);
                            return;
                        }

                        // --- LOGIKA DOWNGRADE MODEL (Jika semua server habis) ---
                        if (res.data.code === 'fallback_required') {
                            showFallbackModal(
                                res.data.message, 
                                res.data.available_fallbacks, 
                                function(userSelectedModelId) { 
                                    activeModelId = userSelectedModelId;
                                    var newFailedState = res.data.failed_models || [];
                                    
                                    document.querySelectorAll('.afy-meta-model-card').forEach(function(c) {
                                        c.classList.remove('active');
                                        var radio = c.querySelector('input[type="radio"]');
                                        if (radio && radio.value === activeModelId) {
                                            c.classList.add('active');
                                            radio.checked = true;
                                        }
                                    });
                                    
                                    setState('processing'); 
                                    // Panggil model baru, dan ULANGI dari Server 1 (Index 0)
                                    performExtraction(activeModelId, newFailedState, 0); 
                                }, 
                                function() {
                                    setState('settings');
                                    showError("Process cancelled. Please try again later.");
                                }
                            );
                            return; 
                        }

                        if (res.data.code !== 0) throw new Error(res.data.message || 'Extraction failed.');
                        
                        updateCredits(res.data.credits);
                        
                        var infoEl = document.getElementById('afy-meta-generation-info');
                        if (infoEl) {
                            infoEl.textContent = 'Generated on ' + res.data.generated_at + ' using ' + res.data.model_label + ' (' + res.data.server_label + ')';
                        }

                        var aiText = res.data.metadata || '{}';
                        setState('result');
                        
                        try {
                            var parsed = parseRobustJSON(aiText);
                            
                            // UBAH LABEL ELASTICITY
                            if (D.elasticityLbl) {
                                D.elasticityLbl.textContent = (activeStrategy === 'market') ? 'Commercial Elasticity (Market-Proven)' : 'Commercial Elasticity (AI Logic)';
                            }

                            D.resReverse.value    = parsed.reverse_prompt || 'N/A';
                            D.resCommercial.value = parsed.commercial_positioning || 'N/A';
                            if(D.resElasticity) D.resElasticity.value = parsed.commercial_elasticity || 'N/A';
                            D.resMedia.value      = parsed.media_type || 'N/A';
                            D.resFilename.value   = parsed.filename || 'N/A';
                            D.resCategory.value   = parsed.category || 'N/A';
                            D.resTitle.value      = parsed.title || 'N/A';
                            D.resKeywords.value   = parsed.keywords || 'N/A';
                            
                            var statics = [D.resReverse, D.resCommercial, D.resElasticity, D.resMedia, D.resFilename, D.resCategory, D.resTitle, D.resKeywords];
                            statics.forEach(function(el) { if(el) autoExpandTextarea(el); });

                            var refUrls = parsed.market_reference_urls || [];
                            
                            if (refUrls.length > 0) {
                                if (D.searchQuery && res.data.search_query) {
                                    D.searchQuery.textContent = 'Search Query: ' + res.data.search_query;
                                }
                                
                                D.refGrid.innerHTML = '';
                                refUrls.forEach(function(url) {
                                    var img = document.createElement('img');
                                    // Panggil Endpoint Proxy untuk bypass CORS
                                    img.src = META_REST + '/image-proxy?url=' + encodeURIComponent(url);
                                    img.className = 'afy-meta-ref-img-zoom';
                                    img.style.width = '100%';
                                    img.style.height = 'auto'; 
                                    img.style.objectFit = 'contain';
                                    img.style.borderRadius = '6px';
                                    img.style.border = '1px solid #cbd5e1';
                                    img.style.boxShadow = '0 2px 4px rgba(0,0,0,0.05)';
                                    
                                    // FIX: Sembunyikan gambar jika AI berhalusinasi memberi URL palsu
                                    img.onerror = function() {
                                        this.style.display = 'none';
                                    };
                                    
                                    // Klik untuk Zoom (Lightbox)
                                    img.addEventListener('click', function() {
                                        if (D.lightbox && D.lightboxImg) {
                                            D.lightboxImg.src = img.src;
                                            show(D.lightbox);
                                        }
                                    });

                                    D.refGrid.appendChild(img);
                                });
                                show(D.refWrap);
                            } else {
                                hide(D.refWrap);
                            }
                            
                            if (D.varContainer) {
                                D.varContainer.innerHTML = '';
                                var variations = parsed.variation_prompts || [];

                                if (Array.isArray(variations) && variations.length > 0) {
                                    variations.slice(0, 7).forEach(function(vObj, index) {
                                        var marketNiche = vObj.market_niche || ('Variation ' + (index + 1));
                                        var rationale = vObj.rationale || '';
                                        var promptText = vObj.prompt || '';
                                        
                                        if (!promptText) return;

                                        var row = document.createElement('div');
                                        row.className = 'afy-meta-block';
                                        
                                        var txtId = 'afy-meta-var-textarea-' + index;
                                        var htmlContent = '';
                                        
                                        htmlContent += '<div class="afy-meta-block-header">';
                                        htmlContent += '<strong>' + marketNiche + '</strong>';
                                        htmlContent += '<button class="afy-meta-copy-icon-btn afy-dyn-btn" data-target="' + txtId + '" title="Copy">' + iconCopy + '</button>';
                                        htmlContent += '</div>';

                                        htmlContent += '<div style="padding: 12px; background: transparent;">';
                                        if (rationale) {
                                            htmlContent += '<p style="margin: 0 0 8px 0; font-size: 13px; color: #64748b; line-height: 1.5;"><strong>Rationale:</strong> ' + rationale + '</p>';
                                        }
                                        htmlContent += '<textarea id="' + txtId + '" readonly class="afy-meta-result-textarea" style="padding:0; resize:none; overflow:hidden;"></textarea>';
                                        htmlContent += '</div>';

                                        row.innerHTML = htmlContent;
                                        D.varContainer.appendChild(row);

                                        var ta = document.getElementById(txtId);
                                        if (ta) {
                                            ta.value = promptText;
                                            setTimeout(function() { autoExpandTextarea(ta); }, 50);
                                        }
                                    });

                                    D.varContainer.querySelectorAll('.afy-dyn-btn').forEach(function(btn) {
                                        btn.addEventListener('click', function() {
                                            var targetId = btn.getAttribute('data-target');
                                            var targetEl = document.getElementById(targetId);
                                            if (targetEl) {
                                                targetEl.select();
                                                document.execCommand('copy');
                                                btn.innerHTML = iconCheck;
                                                btn.classList.add('afy-copied');
                                                setTimeout(function() { 
                                                    btn.innerHTML = iconCopy; 
                                                    btn.classList.remove('afy-copied');
                                                }, 2000);
                                            }
                                        });
                                    });
                                } else {
                                    D.varContainer.innerHTML = '<p style="margin:0; color:#94a3b8; font-size:13px;">No suggestions generated.</p>';
                                }
                            }
                            
                        } catch(e) {
                            D.resReverse.value = "Failed to parse JSON. Raw AI text:\n" + aiText;
                            autoExpandTextarea(D.resReverse);
                            D.resCommercial.value = ''; D.resMedia.value = '';
                            if(D.resElasticity) D.resElasticity.value = '';
                            D.resFilename.value = ''; D.resCategory.value = ''; D.resTitle.value = ''; D.resKeywords.value = '';
                            if (D.varContainer) D.varContainer.innerHTML = '';
                            hide(D.refWrap);
                        }
                    })
                    .catch(function (err) { 
                        setState('settings'); 
                        showError(err.message); 
                    });
            }

            // Eksekusi pertama kali menggunakan Server 1 (index 0) dan belum ada model yang gagal
            performExtraction(activeModelId, [], 0);
        });
    }
}());