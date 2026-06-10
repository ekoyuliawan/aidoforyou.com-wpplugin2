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
    var USER_ID   = AFY_META_APP.user_id;

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
    var ACCOUNT_ID  = IS_LOGGED ? USER_ID : GUEST_TOKEN;

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
        if (!IS_LOGGED) headers['X-AIDOFORYOU-Token'] = GUEST_TOKEN;
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
	
	if (D.lightbox) D.lightbox.addEventListener('click', function() { hide(D.lightbox); });

    if (D.accountIdText) {
        var displayId = IS_LOGGED ? ACCOUNT_ID : String(ACCOUNT_ID).substring(0, 8) + '...';
        D.accountIdText.textContent = displayId;
        D.accountIdText.parentElement.addEventListener('click', function() {
            var tempInput = document.createElement("input");
            tempInput.value = ACCOUNT_ID; 
            document.body.appendChild(tempInput);
            tempInput.select(); document.execCommand("copy");
            document.body.removeChild(tempInput);
            var old = D.accountIdText.textContent;
            D.accountIdText.textContent = 'Copied!';
            D.accountIdText.parentElement.style.color = '#10b981';
            setTimeout(function(){ 
                D.accountIdText.textContent = old; D.accountIdText.parentElement.style.color = '#64748b';
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

    function renderMarketReferences(urls, searchQuery) {
        if (D.searchQuery && searchQuery) {
            D.searchQuery.textContent = 'Search Query: ' + searchQuery;
            show(D.refWrap); 
        }
        
        D.refGrid.innerHTML = '';
        
        if (!urls || urls.length === 0) {
            var p = document.createElement('p');
            p.textContent = 'AI is continuing metadata generation based on market logic.';
            p.style.fontSize = '12px'; p.style.color = '#94a3b8'; p.style.fontStyle = 'italic';
            D.refGrid.appendChild(p);
            return;
        }

        urls.forEach(function(url) {
            var img = document.createElement('img');
            img.src = META_REST + '/image-proxy?url=' + encodeURIComponent(url);
            img.className = 'afy-meta-ref-img-zoom';
            img.style.width = '100%'; img.style.height = 'auto'; img.style.objectFit = 'contain';
            img.style.borderRadius = '6px'; img.style.border = '1px solid #cbd5e1'; img.style.boxShadow = '0 2px 4px rgba(0,0,0,0.05)';
            
            img.onerror = function() { this.style.display = 'none'; };
            img.addEventListener('click', function() {
                if (D.lightbox && D.lightboxImg) { D.lightboxImg.src = img.src; show(D.lightbox); }
            });
            D.refGrid.appendChild(img);
        });
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
        if (!activeModelId && MODELS.length > 0 && (!MODELS[0].premium || IS_LOGGED)) activeModelId = MODELS[0].id;
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

            // FIX: Menggunakan penambahan CSS class agar layout flex-box pada Media Type & Category tidak hancur
            var imageOnlyBlocks = document.querySelectorAll('.afy-image-only-block');
            if (name === 'result') {
                if (activeTab === 'image') {
                    imageOnlyBlocks.forEach(function(el) { el.classList.remove('afy-meta-hidden'); });
                } else {
                    imageOnlyBlocks.forEach(function(el) { el.classList.add('afy-meta-hidden'); });
                }
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
            if (activeTab === 'image') { show(D.areaImage); hide(D.areaText); } else { hide(D.areaImage); show(D.areaText); }
        });
    });
	
    var activeStrategy = 'dynamic';
    var marketVarCount = '2';

    function getDynamicCost() {
        // Memaksa nilai dibaca murni sebagai angka bulat Basis-10
        var baseCostInt = parseInt(COST, 10) || 3;
        
        if (activeStrategy === 'market') {
            var variationsInt = parseInt(marketVarCount, 10) || 2;
            return baseCostInt + variationsInt;
        }
        return baseCostInt;
    }

    function updateCostUI() {
        var finalCost = getDynamicCost();
        var btnTextSpan = document.getElementById('afy-meta-btn-text');
        if (btnTextSpan) {
            btnTextSpan.textContent = 'Generate Metadata (' + finalCost + ' Credits)';
        }
        if (D.extractBtn) {
            D.extractBtn.disabled = (currentCredits < finalCost);
        }
    }

    if (D.stratRadios.length > 0) {
        D.stratRadios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                activeStrategy = this.value;
                if (activeStrategy === 'market') show(D.marketWrap); else hide(D.marketWrap);
                updateCostUI(); // Update credit text
            });
        });
    }

    if (D.varSlider) {
        D.varSlider.addEventListener('input', function() {
            var val = parseInt(this.value, 10);
            if (!IS_LOGGED && val === 6) {
                this.value = 4; showError('Please login/register to unlock 6 Market-Proven variations.');
                setTimeout(hideError, 4000); val = 4;
            }
            marketVarCount = val.toString();
            if(D.sliderValDisp) D.sliderValDisp.textContent = val;
            updateCostUI(); // Update credit text real-time
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

    // FIX: Validasi Panjang Teks (1-5 kata, harus mengandung kata)
    function handleText() {
        var txt = D.textInputRaw.value.trim();
        if (!txt) { showError('Please enter a keyword phrase.'); return; }
        
        if (txt.length < 3) {
            showError('Your keyword is too short. Please provide a valid word (at least 3 characters).'); return;
        }

        if (!/[a-zA-Z]/.test(txt)) {
            showError('Please provide a valid keyword containing letters.'); return;
        }

        var wordCount = txt.split(/\s+/).length;
        if (wordCount < 1 || wordCount > 5) {
            showError('Please provide a specific keyword phrase (1 to 5 words) for accurate market targeting.'); return;
        }
        
        hide(D.prevImgWrap); show(D.prevTextWrap);
        D.prevTextSnip.textContent = '"' + txt + '"';
        setText(D.fileName, 'Target Keyword');
        
        hideError();
        if (D.extractBtn) D.extractBtn.disabled = (currentCredits < COST);
        setState('settings');
    }

    if (D.fileInput) D.fileInput.addEventListener('change', function (e) { if (e.target.files && e.target.files[0]) handleFile(e.target.files[0]); });
    if (D.textSubmitBtn) D.textSubmitBtn.addEventListener('click', handleText);

    if (D.dz) {
        D.dz.addEventListener('click', function (e) { if (e.target !== D.fileInput && e.target.tagName !== 'BUTTON') D.fileInput.click(); });
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
        hideError(); hide(D.refWrap);
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
                targetEl.select(); document.execCommand('copy');
                btn.innerHTML = iconCheck; btn.classList.add('afy-copied');
                setTimeout(function() { btn.innerHTML = iconCopy; btn.classList.remove('afy-copied'); }, 2000);
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
                opt.value = model.id; opt.textContent = model.label;
                selectEl.appendChild(opt);
            });
            
            var newBtnYes = btnYes.cloneNode(true);
            var newBtnNo = btnNo.cloneNode(true);
            btnYes.parentNode.replaceChild(newBtnYes, btnYes);
            btnNo.parentNode.replaceChild(newBtnNo, btnNo);

            newBtnYes.addEventListener('click', function() {
                modal.style.display = 'none';
                if (onConfirm) onConfirm(document.getElementById('afy-meta-fallback-select').value);
            });
            
            newBtnNo.addEventListener('click', function() {
                modal.style.display = 'none';
                if (onCancel) onCancel();
            });
            modal.style.display = 'flex';
        }

        D.extractBtn.addEventListener('click', function () {
            if (activeTab === 'image' && !selectedFile) return;
            
            // FIX: Validate using the dynamic cost!
            var currentDynamicCost = getDynamicCost();
            if (currentCredits < currentDynamicCost) { showError('Not enough credits.'); return; }
            
            if (!activeModelId) { showError('Please select a valid AI model.'); return; }

            hideError();
            setState('processing');

            function performExtraction(modelToUse, failedModels, serverIndex) {
                if (!failedModels) failedModels = [];
                if (typeof serverIndex === 'undefined') serverIndex = 0;
                
                var srvInd = document.getElementById('afy-meta-server-indicator');
                if (srvInd) srvInd.textContent = 'Server ' + (serverIndex + 1);

                var progressTextEl = document.querySelector('.afy-meta-proc-desc'); 
                var progressInterval = null;

                var fd = new FormData();
                if (activeTab === 'image') fd.append('image', selectedFile);
                else fd.append('text_input', D.textInputRaw.value.trim());
                
                fd.append('model', modelToUse);
                fd.append('failed_models', JSON.stringify(failedModels));
                fd.append('server_index', serverIndex); 
				fd.append('strategy', activeStrategy);
                if (activeStrategy === 'market') fd.append('market_count', marketVarCount);
                var extraPrompt = D.userPrompt ? D.userPrompt.value.trim() : '';
                if (extraPrompt) fd.append('prompt', extraPrompt);

                function runExtractRequest() {
                    return fetch(META_REST + '/extract', { method: 'POST', headers: apiHeaders(), body: fd })
                        .then(function (r) { 
                            var contentType = r.headers.get('content-type');
                            if (!contentType || contentType.indexOf('application/json') === -1) {
                                return r.text().then(function() { throw new Error("Server timeout during metadata extraction. Please try again or use a faster model like Lite."); });
                            }
                            return r.json().then(function (d) { return { ok: r.ok, data: d }; }); 
                        })
                        .then(function (res) {
                            if (progressInterval) clearInterval(progressInterval);
                            if (!res.ok) throw new Error(res.data.message || 'Extraction failed.');

                            if (res.data.code === 'switch_server') {
                                performExtraction(modelToUse, failedModels, res.data.next_server_index);
                                return;
                            }

                            if (res.data.code === 'fallback_required') {
                                showFallbackModal(
                                    res.data.message, res.data.available_fallbacks, 
                                    function(userSelectedModelId) { 
                                        activeModelId = userSelectedModelId;
                                        document.querySelectorAll('.afy-meta-model-card').forEach(function(c) {
                                            c.classList.remove('active');
                                            var radio = c.querySelector('input[type="radio"]');
                                            if (radio && radio.value === activeModelId) { c.classList.add('active'); radio.checked = true; }
                                        });
                                        setState('processing'); performExtraction(activeModelId, res.data.failed_models || [], 0); 
                                    }, 
                                    function() { setState('settings'); showError("Process cancelled."); }
                                );
                                return; 
                            }

                            if (res.data.code !== 0) throw new Error(res.data.message || 'Extraction failed.');
                            
                            updateCredits(res.data.credits);
                            var infoEl = document.getElementById('afy-meta-generation-info');
                            if (infoEl) infoEl.textContent = 'Generated on ' + res.data.generated_at + ' using ' + res.data.model_label + ' (' + res.data.server_label + ')';

                            var aiText = res.data.metadata || '{}';
                            setState('result');
                            
                            try {
                                var parsed = parseRobustJSON(aiText);
                                
                                if (D.elasticityLbl) {
                                    D.elasticityLbl.textContent = (activeStrategy === 'market') ? 'Commercial Elasticity (Market-Proven)' : 'Commercial Elasticity (AI Logic)';
                                }

                                // FIX: Mengembalikan Defensive Programming (if checks) untuk mencegah Null TypeError
                                if (D.resReverse)    D.resReverse.value    = parsed.reverse_prompt || 'N/A';
                                if (D.resCommercial) D.resCommercial.value = parsed.commercial_positioning || 'N/A';
                                if (D.resElasticity) D.resElasticity.value = parsed.commercial_elasticity || 'N/A';
                                if (D.resMedia)      D.resMedia.value      = parsed.media_type || 'N/A';
                                if (D.resFilename)   D.resFilename.value   = parsed.filename || 'N/A';
                                if (D.resCategory)   D.resCategory.value   = parsed.category || 'N/A';
                                
                                // Membersihkan titik di akhir Title
                                if (D.resTitle) {
                                    D.resTitle.value = parsed.title ? parsed.title.replace(/\.+$/, '').trim() : 'N/A';
                                }
                                
                                // FIX: Regex untuk memastikan selalu ada spasi setelah koma, lalu hapus koma ganda/koma di akhir
                                if (D.resKeywords) {
                                    if (parsed.keywords) {
                                        var cleanKw = parsed.keywords
                                            .replace(/,\s*/g, ', ') // Paksa setiap koma diikuti tepat satu spasi
                                            .replace(/,\s*$/, '')   // Hapus koma (dan spasi) jika ada di paling akhir kalimat
                                            .trim();
                                        D.resKeywords.value = cleanKw;
                                    } else {
                                        D.resKeywords.value = 'N/A';
                                    }
                                }
                                
                                var statics = [D.resReverse, D.resCommercial, D.resElasticity, D.resMedia, D.resFilename, D.resCategory, D.resTitle, D.resKeywords];
                                statics.forEach(function(el) { if(el) autoExpandTextarea(el); });

                                var finalOutputUrls = parsed.market_reference_urls || [];
                                if (finalOutputUrls.length > 0) {
                                    renderMarketReferences(finalOutputUrls, res.data.search_query);
                                } else if (activeStrategy !== 'market') {
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

                                            var row = document.createElement('div'); row.className = 'afy-meta-block';
                                            var txtId = 'afy-meta-var-textarea-' + index;
                                            var htmlContent = '<div class="afy-meta-block-header"><strong>' + marketNiche + '</strong><button class="afy-meta-copy-icon-btn afy-dyn-btn" data-target="' + txtId + '" title="Copy">' + iconCopy + '</button></div>';
                                            htmlContent += '<div style="padding: 12px; background: transparent;">';
                                            if (rationale) htmlContent += '<p style="margin: 0 0 8px 0; font-size: 13px; color: #64748b; line-height: 1.5;"><strong>Rationale:</strong> ' + rationale + '</p>';
                                            htmlContent += '<textarea id="' + txtId + '" readonly class="afy-meta-result-textarea" style="padding:0; resize:none; overflow:hidden;"></textarea></div>';
                                            
                                            row.innerHTML = htmlContent; D.varContainer.appendChild(row);

                                            var ta = document.getElementById(txtId);
                                            if (ta) { ta.value = promptText; setTimeout(function() { autoExpandTextarea(ta); }, 50); }
                                        });

                                        D.varContainer.querySelectorAll('.afy-dyn-btn').forEach(function(btn) {
                                            btn.addEventListener('click', function() {
                                                var targetEl = document.getElementById(btn.getAttribute('data-target'));
                                                if (targetEl) {
                                                    targetEl.select(); document.execCommand('copy');
                                                    btn.innerHTML = iconCheck; btn.classList.add('afy-copied');
                                                    setTimeout(function() { btn.innerHTML = iconCopy; btn.classList.remove('afy-copied'); }, 2000);
                                                }
                                            });
                                        });
                                    } else {
                                        D.varContainer.innerHTML = '<p style="margin:0; color:#94a3b8; font-size:13px;">No suggestions generated.</p>';
                                    }
                                }
                            } catch(e) {
                                if (D.resReverse) {
                                    D.resReverse.value = "Failed to parse JSON. Raw AI text:\n" + aiText;
                                    autoExpandTextarea(D.resReverse);
                                }
                                if (D.resCommercial) D.resCommercial.value = ''; 
                                if (D.resMedia) D.resMedia.value = '';
                                if (D.resElasticity) D.resElasticity.value = '';
                                if (D.resFilename) D.resFilename.value = ''; 
                                if (D.resCategory) D.resCategory.value = ''; 
                                if (D.resTitle) D.resTitle.value = ''; 
                                if (D.resKeywords) D.resKeywords.value = '';
                                if (D.varContainer) D.varContainer.innerHTML = '';
                                hide(D.refWrap);
                            }
                        })
                        .catch(function (err) { 
                            if (progressInterval) clearInterval(progressInterval);
                            setState('settings'); showError(err.message); 
                        });
                }

                if (activeStrategy === 'market') {
                    if (progressTextEl) progressTextEl.textContent = "AI analyzing keyword/image...";
                    
                    var fd1 = new FormData();
                    if (activeTab === 'image') fd1.append('image', selectedFile); else fd1.append('text_input', D.textInputRaw.value.trim());
                    fd1.append('model', modelToUse);
                    fd1.append('server_index', serverIndex); 
                    fd1.append('action_type', 'analyze_keyword');

                    fetch(META_REST + '/extract', { method: 'POST', headers: apiHeaders(), body: fd1 })
                    .then(function(r) { 
                        var ct = r.headers.get('content-type');
                        if (!ct || ct.indexOf('application/json') === -1) {
                            return r.text().then(function() { throw new Error("Server timeout during Stage 1. Please try again or use a faster model."); });
                        }
                        return r.json(); 
                    })
                    .then(function(res1) {
                        var keyword = res1.search_query || '';
                        
                        if (!keyword || keyword.trim().toLowerCase() === 'stock photo') {
                            throw new Error("AI failed to visually analyze the input. Please try again or switch to another Model.");
                        }
                        
                        if (D.searchQuery) { 
                            D.searchQuery.textContent = 'Search Query: ' + keyword; 
                            show(D.refWrap); 
                        }
                        
                        if (progressTextEl) progressTextEl.textContent = "AI searching Adobe Stock on Google...";
                        
                        var fd2 = new FormData();
                        fd2.append('action_type', 'search_market_ai');
                        fd2.append('search_query', keyword);
                        fd2.append('market_count', marketVarCount);
                        fd2.append('model', modelToUse);
                        fd2.append('server_index', serverIndex);

                        return fetch(META_REST + '/extract', { method: 'POST', headers: apiHeaders(), body: fd2 })
                        .then(function(r) { 
                            var ct = r.headers.get('content-type');
                            if (!ct || ct.indexOf('application/json') === -1) {
                                return r.text().then(function() { throw new Error("Server timeout during Stage 2. Please try again or use a faster model."); });
                            }
                            return r.json(); 
                        })
                        .then(function(res2) {
                            var finalUrls = res2.urls || [];
                            renderMarketReferences(finalUrls, keyword);
                            fd.append('market_urls', JSON.stringify(finalUrls));
                            fd.append('search_query', keyword);
                            
                            if (progressTextEl) progressTextEl.textContent = "AI generating final metadata...";
                            runExtractRequest(); 
                        });
                    })
                    .catch(function(err) {
                        if (progressInterval) clearInterval(progressInterval);
                        setState('settings'); 
                        showError(err.message || "Failed to communicate with AI Server. Please try again.");
                    });
                } else {
                    if (progressTextEl) {
                        progressTextEl.style.transition = "opacity 0.2s ease"; 
                        var steps = ["Preparing data securely...", "Applying commercial AI logic...", "Structuring final JSON metadata...", "Finalizing response..."];
                        var stepIdx = 0;
                        progressTextEl.textContent = steps[stepIdx];
                        progressInterval = setInterval(function() {
                            stepIdx++;
                            if (stepIdx < steps.length) {
                                progressTextEl.style.opacity = 0; setTimeout(function() { progressTextEl.textContent = steps[stepIdx]; progressTextEl.style.opacity = 1; }, 200);
                            } else clearInterval(progressInterval);
                        }, 3500);
                    }
                    runExtractRequest();
                }
            }

            performExtraction(activeModelId, [], 0);
        });
    }
}());