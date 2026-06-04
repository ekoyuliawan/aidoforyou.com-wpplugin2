/* global AFY_META_ADMIN */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var btn    = document.getElementById('afy-btn-test-api');
        var input  = document.getElementById('afy-test-prompt');
        var output = document.getElementById('afy-test-result');

        if (!btn || !input || !output) return;

        btn.addEventListener('click', function() {
            var promptText = input.value.trim();
            if (!promptText) {
                alert('Please enter a test prompt first.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> Connecting...';
            output.style.display = 'none';
            output.textContent = '';
            output.style.borderLeftColor = '#cbd5e1';

            fetch(AFY_META_ADMIN.rest_url + '/test-connection', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': AFY_META_ADMIN.nonce
                },
                body: JSON.stringify({ prompt: promptText })
            })
            .then(function(res) {
                // Pengecekan ekstra: Jika respon bukan JSON, tangkap HTML-nya
                var contentType = res.headers.get('content-type');
                if (!contentType || contentType.indexOf('application/json') === -1) {
                    return res.text().then(function(text) {
                        throw new Error('Server memblokir request. HTML Response diterima: ' + text.substring(0, 80) + '...');
                    });
                }
                return res.json().then(function(data) {
                    return { ok: res.ok, data: data };
                });
            })
            .then(function(result) {
                btn.disabled = false;
                btn.innerHTML = '<span class="dashicons dashicons-admin-network" style="margin-top:4px;"></span> Send Ping to Gemini';
                output.style.display = 'block';

                if (!result.ok) {
                    output.style.borderLeftColor = '#d63638';
                    output.textContent = '❌ API Error: ' + (result.data.message || 'Unknown API error');
                } else {
                    output.style.borderLeftColor = '#059669';
                    output.textContent = '✅ Gemini Response:\n\n' + result.data.response;
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.innerHTML = '<span class="dashicons dashicons-admin-network" style="margin-top:4px;"></span> Send Ping to Gemini';
                output.style.display = 'block';
                output.style.borderLeftColor = '#d63638';
                output.textContent = '❌ Connection failed: ' + err.message;
            });
        });
    });
}());