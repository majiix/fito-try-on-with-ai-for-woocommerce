/**
 * Fito Try-On with AI - Admin Interactivity & Data Visualization
 */

(function ($) {
    'use strict';

    const FitoAdmin = {
        init: function () {
            this.initChart();
            this.bindProviderToggle();
            this.bindConnectionTest();
            this.bindAccessControl();
            this.bindLogInspection();
            this.bindLogHoverPreview();
            this.bindLogDeletion();
            this.bindModelSync();
            this.bindPromptsManager();
        },

        initChart: function () {
            const chartCanvas = document.getElementById('fitoAnalyticsChart');
            if (!chartCanvas || !window.Chart || !fitoAdminData.chartData) {
                return;
            }

            const cd = fitoAdminData.chartData;
            new Chart(chartCanvas, {
                type: 'line',
                data: {
                    labels: cd.labels,
                    datasets: [
                        {
                            label: 'Total Try-Ons',
                            data: cd.totals,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.08)',
                            borderWidth: 2.5,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                        },
                        {
                            label: 'Successful Try-Ons',
                            data: cd.successes,
                            borderColor: '#10b981',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            borderDash: [4, 4],
                            tension: 0.35,
                            pointRadius: 2,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 12,
                                font: { size: 12, family: '-apple-system, BlinkMacSystemFont, sans-serif' }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            });
        },

        bindProviderToggle: function () {
            const self = this;
            $('#fito_ai_provider').on('change', function () {
                const provider = $(this).val();
                $('.fito-provider-panel').hide();
                $('#fito-panel-' + provider).fadeIn(150);
                self.loadModelsWithSkeleton(false, provider);
            });
        },

        bindConnectionTest: function () {
            $(document).on('click', '.fito-btn-test-connection, #fito-btn-test-connection', function () {
                const $btn = $(this);
                const provider = $btn.data('provider') || $('#fito_ai_provider').val() || 'openrouter';
                const $panel = $btn.closest('.fito-provider-panel').length ? $btn.closest('.fito-provider-panel') : $('#fito-panel-' + provider);
                const $result = $panel.find('.fito-connection-test-result, #fito-connection-test-result');
                const $input = $panel.find('input[type="password"]');
                const key = $input.length && $input.val() ? $input.val().trim() : '';

                $btn.prop('disabled', true).text((fitoAdminData.i18n && fitoAdminData.i18n.testing) || 'Testing...');
                $result.removeClass('is-success is-error').hide();

                $.ajax({
                    url: fitoAdminData.restUrl + 'test-connection',
                    type: 'POST',
                    data: {
                        provider: provider,
                        api_key: key
                    },
                    headers: { 'X-WP-Nonce': fitoAdminData.nonce },
                    success: function (res) {
                        $btn.prop('disabled', false).text('Test Connection');
                        $result.addClass('is-success').html('<strong>✓ ' + res.message + '</strong>').fadeIn();
                        FitoAdmin.loadModelsWithSkeleton(true, provider);
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false).text('Test Connection');
                        const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Connection failed.';
                        $result.addClass('is-error').html('<strong>✕ ' + msg + '</strong>').fadeIn();
                    }
                });
            });
        },

        bindAccessControl: function () {
            $('#fito_access_level').on('change', function () {
                const isRestricted = $(this).val() === 'logged_in_only';
                if (isRestricted) {
                    $('#fito_row_login_url, #fito_row_login_notice, #fito_row_sample_image').fadeIn(150);
                    $('#fito-rl-label-guest, #fito-rl-desc-guest').hide();
                    $('#fito-rl-label-user, #fito-rl-desc-user').fadeIn(150);
                } else {
                    $('#fito_row_login_url, #fito_row_login_notice, #fito_row_sample_image').fadeOut(150);
                    $('#fito-rl-label-user, #fito-rl-desc-user').hide();
                    $('#fito-rl-label-guest, #fito-rl-desc-guest').fadeIn(150);
                }
            });

            $('#fito-btn-use-myaccount').on('click', function (e) {
                e.preventDefault();
                const url = $(this).data('url');
                if (url) {
                    $('#fito_login_url').val(url);
                }
            });

            let sampleMediaFrame;
            $('#fito-btn-upload-sample-image').on('click', function (e) {
                e.preventDefault();

                if (sampleMediaFrame) {
                    sampleMediaFrame.open();
                    return;
                }

                sampleMediaFrame = wp.media({
                    title: 'Select Sample Try-On Image',
                    button: { text: 'Use Sample Image' },
                    multiple: false,
                    library: { type: 'image' }
                });

                sampleMediaFrame.on('select', function () {
                    const attachment = sampleMediaFrame.state().get('selection').first().toJSON();
                    $('#fito_sample_image_id').val(attachment.id);
                    const previewUrl = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) || attachment.url;
                    $('#fito-sample-image-preview img').attr('src', previewUrl);
                    $('#fito-sample-image-preview, #fito-btn-remove-sample-image').show();
                });

                sampleMediaFrame.open();
            });

            $('#fito-btn-remove-sample-image').on('click', function (e) {
                e.preventDefault();
                $('#fito_sample_image_id').val('');
                $('#fito-sample-image-preview, #fito-btn-remove-sample-image').hide();
            });
        },

        bindLogInspection: function () {
            $(document).on('click', '.fito-inspect-btn', function () {
                const $btn = $(this);
                const id = $btn.data('id');
                const ip = $btn.data('ip');
                const error = $btn.data('error');
                const pTokens = $btn.data('prompt-tok');
                const cTokens = $btn.data('comp-tok');
                const userImg = $btn.data('user-img');
                const resultImg = $btn.data('result-img');

                const $row = $btn.closest('tr');
                const promptText = $row.find('.fito-log-raw-prompt').text().trim();
                const responseText = $row.find('.fito-log-raw-response').text().trim();

                let html = '';

                if (userImg || resultImg) {
                    html += '<div class="fito-modal-compare-grid">';
                    html += '  <div class="fito-modal-compare-col">';
                    html += '    <div class="fito-modal-compare-label">Before (User Photo)</div>';
                    if (userImg) {
                        html += '    <a href="' + userImg + '" target="_blank" class="fito-modal-compare-link"><img src="' + userImg + '" alt="Before" class="fito-modal-compare-img"><span class="fito-zoom-hint">View Full Size</span></a>';
                    } else {
                        html += '    <div class="fito-modal-no-img">No before image saved</div>';
                    }
                    html += '  </div>';
                    html += '  <div class="fito-modal-compare-col">';
                    html += '    <div class="fito-modal-compare-label">After (Try-On Result)</div>';
                    if (resultImg) {
                        html += '    <a href="' + resultImg + '" target="_blank" class="fito-modal-compare-link"><img src="' + resultImg + '" alt="After" class="fito-modal-compare-img"><span class="fito-zoom-hint">View Full Size</span></a>';
                    } else {
                        html += '    <div class="fito-modal-no-img">No result image saved</div>';
                    }
                    html += '  </div>';
                    html += '</div>';
                }

                const pTokNum = parseInt(pTokens, 10) || 0;
                const cTokNum = parseInt(cTokens, 10) || 0;

                const pTokDisplay = pTokNum > 0
                    ? pTokNum.toLocaleString() + ' tokens'
                    : '0 <span class="fito-muted" style="font-size:11px;font-style:italic;">(Per-image model, not token-billed)</span>';

                const cTokDisplay = cTokNum > 0
                    ? cTokNum.toLocaleString() + ' tokens'
                    : '0 <span class="fito-muted" style="font-size:11px;font-style:italic;">(Direct image output, not token-billed)</span>';

                const userName = $btn.attr('data-user-name') || '';
                const userUrl = $btn.attr('data-user-url') || '';
                const prodName = $btn.attr('data-prod-name') || '';
                const prodEdit = $btn.attr('data-prod-edit') || '';
                const prodView = $btn.attr('data-prod-view') || '';

                let userCell = $('<div>').text(userName || 'Guest').html();
                if (userUrl) {
                    userCell = '<a href="' + userUrl + '" target="_blank"><strong>' + userCell + '</strong></a>';
                }

                let prodCell = $('<div>').text(prodName || '-').html();
                let prodActions = [];
                if (prodEdit) {
                    prodActions.push('<a href="' + prodEdit + '" target="_blank">Edit Product</a>');
                }
                if (prodView) {
                    prodActions.push('<a href="' + prodView + '" target="_blank">View on Store</a>');
                }
                if (prodActions.length > 0) {
                    prodCell += '<div style="margin-top: 4px; font-size: 12px;">' + prodActions.join(' | ') + '</div>';
                }

                html += '<table class="form-table">';
                html += '<tr><th>Log ID:</th><td>#' + id + '</td></tr>';
                html += '<tr><th>User:</th><td>' + userCell + '</td></tr>';
                html += '<tr><th>Product:</th><td>' + prodCell + '</td></tr>';
                html += '<tr><th>Client IP:</th><td>' + ip + '</td></tr>';
                html += '<tr><th>Prompt Tokens:</th><td>' + pTokDisplay + '</td></tr>';
                html += '<tr><th>Completion Tokens:</th><td>' + cTokDisplay + '</td></tr>';
                if (error) {
                    html += '<tr><th>Error Details:</th><td style="color:#b91c1c;">' + error + '</td></tr>';
                }
                html += '</table>';

                const i18n = (typeof fitoAdminData !== 'undefined' && fitoAdminData.i18n) || {};
                const sentPromptTitle = i18n.sentPrompt || 'Sent AI Prompt';
                const recResponseTitle = i18n.receivedResponse || 'Received AI Response';
                const noPromptText = i18n.noPrompt || 'No sent prompt recorded for this log entry.';
                const noResponseText = i18n.noResponse || 'No AI response recorded for this log entry.';
                const copyBtnText = i18n.copy || 'Copy';

                // Sent AI Prompt Section
                html += '<div class="fito-log-detail-section">';
                html += '  <div class="fito-log-detail-header">';
                html += '    <h4 class="fito-log-detail-title">' + sentPromptTitle + '</h4>';
                if (promptText) {
                    html += '    <button type="button" class="button button-small fito-copy-btn" data-target="fito-log-prompt-code">' + copyBtnText + '</button>';
                }
                html += '  </div>';
                if (promptText) {
                    html += '  <pre class="fito-log-code-block"><code id="fito-log-prompt-code">' + $('<div>').text(promptText).html() + '</code></pre>';
                } else {
                    html += '  <p class="fito-muted fito-log-empty-note">' + noPromptText + '</p>';
                }
                html += '</div>';

                // Received AI Response Section
                const statusBadge = $row.find('.fito-badge').text().trim().toLowerCase();
                const effectiveResponse = responseText 
                    || (error ? JSON.stringify({ error: error }, null, 2) : (statusBadge === 'success' ? JSON.stringify({ status: 'success', note: 'Legacy log entry recorded before full JSON payload storage.' }, null, 2) : ''));
                html += '<div class="fito-log-detail-section">';
                html += '  <div class="fito-log-detail-header">';
                html += '    <h4 class="fito-log-detail-title">' + recResponseTitle + '</h4>';
                if (effectiveResponse) {
                    html += '    <button type="button" class="button button-small fito-copy-btn" data-target="fito-log-response-code">' + copyBtnText + '</button>';
                }
                html += '  </div>';
                if (effectiveResponse) {
                    html += '  <pre class="fito-log-code-block"><code id="fito-log-response-code">' + $('<div>').text(effectiveResponse).html() + '</code></pre>';
                } else {
                    html += '  <p class="fito-muted fito-log-empty-note">' + noResponseText + '</p>';
                }
                html += '</div>';

                $('#fito-log-modal-content').html(html);
                $('#fito-log-modal').fadeIn(150);
            });

            $(document).on('click', '.fito-copy-btn', function (e) {
                e.preventDefault();
                const $btn = $(this);
                const targetId = $btn.data('target');
                const targetEl = document.getElementById(targetId);
                const textToCopy = targetEl ? (targetEl.innerText || targetEl.textContent) : '';

                const i18n = (typeof fitoAdminData !== 'undefined' && fitoAdminData.i18n) || {};
                const copiedText = i18n.copied || 'Copied!';
                const originalText = $btn.text();

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(textToCopy).then(function () {
                        $btn.text(copiedText);
                        setTimeout(function () { $btn.text(originalText); }, 2000);
                    }).catch(function () {
                        fallbackCopy(textToCopy, $btn, copiedText, originalText);
                    });
                } else {
                    fallbackCopy(textToCopy, $btn, copiedText, originalText);
                }
            });

            function fallbackCopy(text, $btn, copiedText, originalText) {
                const $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(text).select();
                try {
                    document.execCommand('copy');
                    $btn.text(copiedText);
                    setTimeout(function () { $btn.text(originalText); }, 2000);
                } catch (err) {
                    // silent fallback
                }
                $temp.remove();
            }

            $('#fito-close-log-modal, #fito-log-modal').on('click', function (e) {
                if (e.target === this) {
                    $('#fito-log-modal').fadeOut(150);
                }
            });
        },

        bindLogHoverPreview: function () {
            let $preview = $('#fito-thumb-floating-preview');
            if (!$preview.length) {
                $preview = $(
                    '<div id="fito-thumb-floating-preview" class="fito-thumb-floating-preview">' +
                    '  <div class="fito-floating-preview-header">' +
                    '    <span class="fito-floating-preview-title"></span>' +
                    '    <span class="fito-floating-preview-hint">Click to open &nearr;</span>' +
                    '  </div>' +
                    '  <div class="fito-floating-preview-body">' +
                    '    <img src="" alt="Preview">' +
                    '  </div>' +
                    '</div>'
                ).appendTo('body');
            }

            $(document).on('mouseenter', '.fito-log-thumb', function () {
                const url = $(this).data('preview-url') || $(this).attr('href');
                if (!url) return;

                const title = $(this).data('preview-title') || $(this).attr('title') || 'Image Preview';
                $preview.find('.fito-floating-preview-title').text(title);
                $preview.find('img').attr('src', url);

                const rect = this.getBoundingClientRect();
                const previewWidth = 240;
                const previewHeight = 285;

                let top = rect.top + (rect.height / 2) - (previewHeight / 2);
                let left = rect.right + 12;

                // Position to the left of thumbnail if overflowing right edge
                if (left + previewWidth > window.innerWidth - 12) {
                    left = rect.left - previewWidth - 12;
                }

                // Keep inside vertical viewport
                if (top < 10) {
                    top = 10;
                } else if (top + previewHeight > window.innerHeight - 10) {
                    top = window.innerHeight - previewHeight - 10;
                }

                $preview.css({
                    top: top + 'px',
                    left: left + 'px'
                }).addClass('is-active');
            });

            $(document).on('mouseleave', '.fito-log-thumb', function () {
                $preview.removeClass('is-active');
            });

            $(window).on('scroll', function () {
                if ($preview.hasClass('is-active')) {
                    $preview.removeClass('is-active');
                }
            });
        },

        bindModelSync: function () {
            const self = this;
            const activeProvider = $('#fito_ai_provider').val() || 'openrouter';

            // Load models on page load for current provider
            if ($('.fito-provider-table, #fito-models-wrapper').length) {
                self.loadModelsWithSkeleton(false, activeProvider);
            }

            // Manual refresh button
            $('#fito-btn-sync-models').on('click', function () {
                const currentProvider = $('#fito_ai_provider').val() || 'openrouter';
                self.loadModelsWithSkeleton(true, currentProvider);
            });
        },

        loadModelsWithSkeleton: function (forceRefresh, provider) {
            const activeProvider = provider || $('#fito_ai_provider').val() || 'openrouter';
            const $btn = $('#fito-btn-sync-models');
            const $status = $('#fito-sync-models-status');
            const originalHtml = $btn.length ? $btn.html() : '';

            const $panel = $('#fito-panel-' + activeProvider).length ? $('#fito-panel-' + activeProvider) : $('#fito-models-wrapper').closest('.fito-card-body');
            const $wrapper = $panel.find('.fito-provider-table').length ? $panel.find('.fito-provider-table') : $('#fito-models-wrapper');
            const $primarySelect = $panel.find('select[id$="_primary_model"]');
            const $skeletons = $panel.find('.fito-primary-skeleton, #fito-primary-skeleton');
            const $selectWraps = $panel.find('.fito-primary-select-wrap, #fito-primary-select-wrap');

            // Show skeleton loaders, hide selects
            $skeletons.show();
            $selectWraps.hide();

            if ($btn.length && forceRefresh) {
                $btn.prop('disabled', true);
                $btn.find('span').text('Refreshing models...');
            }
            $status.hide();

            const savedPrimary = $wrapper.data('saved-primary') || $primarySelect.val() || '';

            const $keyInput = $panel.find('input[type="password"]');
            const currentKey = $keyInput.length && $keyInput.val() ? $keyInput.val().trim() : '';

            let requestUrl = fitoAdminData.restUrl + 'models?provider=' + encodeURIComponent(activeProvider) + '&refresh=' + (forceRefresh ? '1' : '0');
            if (currentKey) {
                requestUrl += '&api_key=' + encodeURIComponent(currentKey);
            }

            $.ajax({
                url: requestUrl,
                type: 'GET',
                headers: { 'X-WP-Nonce': fitoAdminData.nonce },
                success: function (res) {
                    if ($btn.length) {
                        $btn.prop('disabled', false).html(originalHtml);
                    }

                    if (res && res.success && res.models && res.models.length > 0) {
                        const imageGenModels = [];

                        res.models.forEach(function (m) {
                            if (m.type === 'image_gen') {
                                imageGenModels.push(m);
                            }
                        });

                        // Sort alphabetically by name
                        imageGenModels.sort(function (a, b) {
                            return a.name.localeCompare(b.name);
                        });

                        let genOptions = '';
                        imageGenModels.forEach(function (m) {
                            genOptions += '<option value="' + m.id + '">' + m.name + '</option>';
                        });

                        // Populate primary model
                        $primarySelect.html(genOptions).val(savedPrimary);
                        if (!$primarySelect.val() && imageGenModels.length > 0) {
                            $primarySelect.val(imageGenModels[0].id);
                        }

                        // Trigger event for extensions (e.g. Pro Addon to populate fallback model)
                        $(document).trigger('fito_models_loaded', [imageGenModels, activeProvider, $panel, genOptions]);

                        // Smoothly transition from skeleton to selects
                        $skeletons.fadeOut(150, function () {
                            $selectWraps.fadeIn(200);
                        });

                        if (forceRefresh) {
                            const providerTitle = $('#fito_ai_provider option:selected').text() || 'OpenRouter';
                            $status.text('✓ Cache cleared! ' + imageGenModels.length + ' models refreshed for ' + providerTitle + ' (cached for 24h).').fadeIn();
                            setTimeout(function () {
                                $status.fadeOut(300);
                            }, 4000);
                        }
                    } else {
                        $skeletons.hide();
                        $selectWraps.show();
                        $(document).trigger('fito_models_load_failed', [activeProvider, $panel]);
                    }
                },
                error: function () {
                    if ($btn.length) {
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                    $skeletons.hide();
                    $selectWraps.show();
                    $(document).trigger('fito_models_load_failed', [activeProvider, $panel]);
                }
            });
        },

        bindLogDeletion: function () {
            $('#fito-btn-clear-logs').on('click', function () {
                if (!confirm(fitoAdminData.i18n.confirmClear)) {
                    return;
                }

                $.ajax({
                    url: fitoAdminData.restUrl + 'logs?log_id=all',
                    type: 'DELETE',
                    headers: { 'X-WP-Nonce': fitoAdminData.nonce },
                    success: function () {
                        window.location.reload();
                    }
                });
            });
        },

        bindPromptsManager: function () {
            const $repeater = $('#fitoPromptsRepeater');
            if (!$repeater.length) {
                return;
            }

            function updateCategoryBadgesAndNotices() {
                $repeater.find('.fito-prompt-row').each(function () {
                    const $row = $(this);
                    const $checklist = $row.find('.fito-prompt-cat-checklist');
                    const $checked = $checklist.find('input[type="checkbox"]:checked');
                    const count = $checked.length;
                    const $badge = $row.find('.fito-prompt-cat-count');
                    $badge.attr('data-count', count);
                    const isClothing = $row.find('input[name*="[id]"]').val() === 'clothing';

                    if (count > 0) {
                        $badge.text(count === 1 ? '1 category connected' : count + ' categories connected');
                    } else if (isClothing) {
                        $badge.text('Default Fallback (All other categories)');
                    } else {
                        $badge.text('No categories connected');
                    }

                    const totalItems = $checklist.find('.fito-prompt-cat-item').length;
                    const visibleCount = $checklist.find('.fito-prompt-cat-item:not(.is-claimed-elsewhere):not(.is-search-hidden)').length;
                    let $notice = $checklist.find('.fito-cat-empty-notice');
                    if (!$notice.length) {
                        $notice = $('<p class="fito-cat-empty-notice" style="display:none;"></p>').appendTo($checklist);
                    }

                    if (totalItems > 0 && visibleCount === 0) {
                        const searchVal = ($row.find('.fito-prompt-cat-search').val() || '').trim();
                        $notice.text(searchVal ? 'No matching categories found.' : 'All available categories are connected to other prompts.').show();
                    } else {
                        $notice.hide();
                    }
                });
            }

            function syncExclusiveCategories() {
                const $rows = $repeater.find('.fito-prompt-row');
                const checkedOwners = {};

                $rows.each(function () {
                    const rowEl = this;
                    $(this).find('.fito-prompt-cat-checklist input[type="checkbox"]:checked').each(function () {
                        const catId = $(this).val();
                        if (!checkedOwners[catId]) {
                            checkedOwners[catId] = rowEl;
                        } else if (checkedOwners[catId] !== rowEl) {
                            $(this).prop('checked', false);
                        }
                    });
                });

                $rows.each(function () {
                    const thisRowEl = this;
                    $(this).find('.fito-prompt-cat-item').each(function () {
                        const $item = $(this);
                        const catId = $item.find('input[type="checkbox"]').val();
                        const ownerRowEl = checkedOwners[catId];
                        const isClaimedElsewhere = !!ownerRowEl && ownerRowEl !== thisRowEl;

                        if (isClaimedElsewhere) {
                            $item.addClass('is-claimed-elsewhere');
                        } else {
                            $item.removeClass('is-claimed-elsewhere');
                        }
                    });
                });

                updateCategoryBadgesAndNotices();
            }

            // Initial sync on page load
            syncExclusiveCategories();

            // 1. Reset System Prompt button
            $('#fito-btn-reset-system-prompt').on('click', function (e) {
                e.preventDefault();
                const defaultPrompt = $(this).data('default');
                if (defaultPrompt) {
                    $('#fito_tryon_system_prompt').val(defaultPrompt);
                }
            });

            // 1b. Revert Built-in Preset Prompt to Default
            $repeater.on('click', '.fito-revert-default-prompt', function (e) {
                e.preventDefault();
                const defaultPrompt = $(this).data('default');
                if (defaultPrompt) {
                    const $row = $(this).closest('.fito-prompt-row');
                    $row.find('.fito-prompt-textarea').val(defaultPrompt).focus();
                }
            });

            // 2. Expand / Collapse individual row
            $repeater.on('click', '.fito-prompt-toggle', function (e) {
                e.preventDefault();
                const $row = $(this).closest('.fito-prompt-row');
                $row.toggleClass('is-collapsed');
            });

            // 3. Delete custom prompt row
            $repeater.on('click', '.fito-prompt-delete:not(.disabled)', function (e) {
                e.preventDefault();
                const $row = $(this).closest('.fito-prompt-row');
                const title = $row.find('.fito-prompt-title-input').val() || 'this prompt';
                if (confirm('Are you sure you want to delete ' + title + '?')) {
                    $row.fadeOut(200, function () {
                        $(this).remove();
                        syncExclusiveCategories();
                    });
                }
            });

            // Listen for external category sync trigger (e.g. from Pro Addon)
            $(document).on('fito_sync_exclusive_categories', function () {
                syncExclusiveCategories();
            });

            // 4. Category filter/search per card
            $repeater.on('input', '.fito-prompt-cat-search', function () {
                const query = $(this).val().toLowerCase().trim();
                const $catsContainer = $(this).closest('.fito-prompt-field-cats');
                $catsContainer.find('.fito-prompt-cat-item').each(function () {
                    const text = $(this).find('.fito-prompt-cat-name').text().toLowerCase();
                    if (!query || text.indexOf(query) !== -1) {
                        $(this).removeClass('is-search-hidden');
                    } else {
                        $(this).addClass('is-search-hidden');
                    }
                });
                updateCategoryBadgesAndNotices();
            });

            // 7. Category checkbox toggle - synchronize mutual exclusivity across all cards
            $repeater.on('change', '.fito-prompt-cat-checklist input[type="checkbox"]', function () {
                syncExclusiveCategories();
            });

            // 7. Insert Placeholder tag into active prompt textarea
            $repeater.on('click', '.fito-insert-placeholder', function (e) {
                e.preventDefault();
                const tag = $(this).data('tag');
                const $textarea = $(this).closest('.fito-prompt-field-text').find('.fito-prompt-textarea');
                const domTextarea = $textarea[0];
                if (!domTextarea) return;

                const startPos = domTextarea.selectionStart;
                const endPos = domTextarea.selectionEnd;
                const val = domTextarea.value;

                domTextarea.value = val.substring(0, startPos) + tag + val.substring(endPos, val.length);
                domTextarea.focus();
                domTextarea.selectionStart = startPos + tag.length;
                domTextarea.selectionEnd = startPos + tag.length;
            });
        }
    };

    $(document).ready(function () {
        FitoAdmin.init();
    });

})(jQuery);
