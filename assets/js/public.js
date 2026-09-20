/**
 * Fito Try-On with AI - Public Frontend Widget & Comparison Engine
 */

(function ($) {
    'use strict';

    const fitoData = window.fitoTryonData || {};

    const FitoApp = {
        currentProductId: 0,
        currentVariationId: 0,
        currentImageId: 0,
        currentProductTitle: '',
        currentProductImage: '',
        userImageBlob: null,
        userImageBase64: '',
        cameraStream: null,
        pollInterval: null,
        pollCount: 0,
        maxPolls: 60,
        messageTimer: null,
        messageIndex: 0,
        currentJobToken: '',
        resultImageUrl: '',
        userImageUrl: '',
        historyStatePushed: false,

        init: function () {
            this.getOrCreateDeviceId();
            this.bindEvents();
            this.watchWooCommerceVariations();
            this.initGalleryButtons();
            this.initCustomImageButtons();
            this.initSliderEvents();
            this.preventAccidentalDrop();
        },

        getOrCreateDeviceId: function () {
            let deviceId = '';
            try {
                deviceId = localStorage.getItem('fito_device_id') || '';
            } catch (e) {}

            if (!deviceId) {
                const match = document.cookie.match(/(?:^|;\s*)fito_device_uuid=([^;]+)/);
                if (match && match[1]) {
                    deviceId = decodeURIComponent(match[1]);
                }
            }

            if (!deviceId) {
                if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                    deviceId = window.crypto.randomUUID();
                } else {
                    deviceId = 'dev_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
                }
            }

            try {
                localStorage.setItem('fito_device_id', deviceId);
            } catch (e) {}

            try {
                const expires = new Date();
                expires.setTime(expires.getTime() + (30 * 24 * 60 * 60 * 1000));
                document.cookie = 'fito_device_uuid=' + encodeURIComponent(deviceId) + '; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
            } catch (e) {}

            return deviceId;
        },

        bindEvents: function () {
            const self = this;

            // Open modal trigger
            $(document).on('click', '.fito-tryon-btn', function (e) {
                e.preventDefault();
                if (fitoData && fitoData.isProductAllowed === false && !$(this).hasClass('fito-tryon-btn--archive')) {
                    return;
                }
                const $btn = $(this);
                const clickedProdId = parseInt($btn.data('product-id'), 10) || 0;

                // Reset state when opening for a different product
                if (clickedProdId !== self.currentProductId) {
                    self.currentProductId = clickedProdId;
                    self.resultImageUrl = '';
                    self.userImageUrl = '';
                    self.currentJobToken = '';
                    self.currentImageId = 0;
                }

                self.currentProductTitle = $btn.data('product-title') || '';
                self.currentProductImage = $btn.data('product-image') || '';
                self.currentImageId = parseInt($btn.data('image-id'), 10) || 0;

                // Determine variation ID
                const btnVarId = parseInt($btn.data('variation-id'), 10);
                if (btnVarId) {
                    self.currentVariationId = btnVarId;
                } else if ($btn.hasClass('fito-tryon-btn--archive')) {
                    self.currentVariationId = 0;
                } else {
                    const formVarId = parseInt($('form.variations_form input[name="variation_id"]').val(), 10);
                    self.currentVariationId = formVarId || 0;
                }

                self.openModal();
            });

            // Close modal triggers
            $(document).on('click', '.fito-modal-close, .fito-modal-backdrop, #fito-btn-done, #fito-btn-login-close', function (e) {
                e.preventDefault();
                self.closeModal();
            });

            // ESC key to close or exit fullscreen
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    if ($('#fito-slider-stage').hasClass('fito-is-fullscreen') || document.fullscreenElement) {
                        self.exitFullscreen();
                        e.stopPropagation();
                        return;
                    }
                    if ($('#fito-tryon-modal').hasClass('is-open')) {
                        self.closeModal();
                    }
                }
            });

            // Fullscreen triggers
            $(document).on('click', '#fito-stage-fullscreen-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.toggleFullscreen();
            });

            $(document).on('click', '#fito-exit-fullscreen-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.exitFullscreen();
            });

            // Browser fullscreen change listeners
            document.addEventListener('fullscreenchange', function () {
                if (!document.fullscreenElement) {
                    $('#fito-slider-stage').removeClass('fito-is-fullscreen');
                }
            });
            document.addEventListener('webkitfullscreenchange', function () {
                if (!document.webkitFullscreenElement) {
                    $('#fito-slider-stage').removeClass('fito-is-fullscreen');
                }
            });

            // Browser / Mobile back button to close modal
            $(window).on('popstate', function () {
                if (self.historyStatePushed && $('#fito-tryon-modal').hasClass('is-open')) {
                    self.historyStatePushed = false;
                    self.closeModal(false);
                }
            });

            // Prevent clicks on the file input from bubbling back up to #fito-dropzone
            $(document).on('click', '#fito-file-input', function (e) {
                e.stopPropagation();
            });

            // File input triggers
            $(document).on('click', '#fito-btn-browse', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const input = document.getElementById('fito-file-input');
                if (input) {
                    input.click();
                }
            });

            $(document).on('click', '#fito-dropzone', function (e) {
                if ($(e.target).is('#fito-file-input') || $(e.target).closest('button').length) {
                    return;
                }
                const input = document.getElementById('fito-file-input');
                if (input) {
                    input.click();
                }
            });

            $(document).on('change', '#fito-file-input', function () {
                if (this.files && this.files[0]) {
                    self.handleSelectedFile(this.files[0]);
                }
            });

            // Drag & Drop on dropzone
            $(document).on('dragover dragenter', '#fito-dropzone', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('is-dragover');
            });

            $(document).on('dragleave dragend drop', '#fito-dropzone', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('is-dragover');
            });

            $(document).on('drop', '#fito-dropzone', function (e) {
                const dt = e.originalEvent && e.originalEvent.dataTransfer;
                if (dt && dt.files && dt.files[0]) {
                    self.handleSelectedFile(dt.files[0]);
                }
            });

            // Camera toggle and snapshot
            $(document).on('click', '#fito-btn-camera', function (e) {
                e.preventDefault();
                self.startCamera();
            });

            $(document).on('click', '#fito-btn-camera-close', function (e) {
                e.preventDefault();
                self.stopCamera();
                if (self.userImageBase64) {
                    $('#fito-crop-container').show();
                } else {
                    $('#fito-dropzone').show();
                }
            });

            $(document).on('click', '#fito-btn-capture', function (e) {
                e.preventDefault();
                self.captureCameraSnapshot();
            });

            // Change Photo / Retake
            $(document).on('click', '#fito-crop-change, #fito-btn-retry', function (e) {
                e.preventDefault();
                self.resetToUpload();
            });

            // Consent check validation
            $(document).on('change', '#fito-consent-checkbox', function () {
                self.validateSubmitButton();
            });

            // Generate Button Click
            $(document).on('click', '#fito-btn-submit', function (e) {
                e.preventDefault();
                self.submitTryOn();
            });

            // Result Toolbar Actions
            $(document).on('click', '#fito-btn-download', function (e) {
                e.preventDefault();
                self.downloadResult();
            });

            $(document).on('click', '#fito-btn-share', function (e) {
                e.preventDefault();
                self.shareResult();
            });


            $(document).on('click', '#fito-btn-save-account', function (e) {
                e.preventDefault();
                self.saveToAccount();
            });
        },

        preventAccidentalDrop: function () {
            window.addEventListener('dragover', function (e) {
                e.preventDefault();
            }, false);
            window.addEventListener('drop', function (e) {
                if (!$(e.target).closest('#fito-dropzone').length) {
                    e.preventDefault();
                }
            }, false);
        },

        watchWooCommerceVariations: function () {
            if (fitoData && fitoData.isProductAllowed === false) {
                return;
            }
            const self = this;
            $(document).on('show_variation', '.variations_form', function (event, variation) {
                if (variation && variation.variation_id) {
                    self.currentVariationId = variation.variation_id;
                    const varImg = (variation.image && (variation.image.full_src || variation.image.src)) || '';
                    if (varImg) {
                        self.currentProductImage = varImg;
                        self.currentImageId = variation.image_id || 0;
                        $('.fito-tryon-btn--single').data('product-image', varImg);
                        $('.fito-tryon-btn--single').attr('data-product-image', varImg);
                        $('.fito-tryon-btn--single').data('variation-id', variation.variation_id);
                        $('.fito-tryon-btn--single').attr('data-variation-id', variation.variation_id);
                    }

                    // Update dedicated variation button if present
                    const $varBtnWrap = $('#fito-variation-tryon-wrapper');
                    if ($varBtnWrap.length) {
                        const $varBtn = $('#fito-variation-tryon-btn');
                        $varBtn.data('variation-id', variation.variation_id);
                        $varBtn.attr('data-variation-id', variation.variation_id);
                        if (varImg) {
                            $varBtn.data('product-image', varImg);
                            $varBtn.attr('data-product-image', varImg);
                        }
                        if (variation.image_id) {
                            $varBtn.data('image-id', variation.image_id);
                            $varBtn.attr('data-image-id', variation.image_id);
                        }

                        let varTitle = self.currentProductTitle;
                        if (variation.attributes) {
                            const attrs = Object.values(variation.attributes).filter(Boolean);
                            if (attrs.length) {
                                varTitle += ' - ' + attrs.join(', ');
                            }
                        }
                        $varBtn.data('product-title', varTitle);
                        $varBtn.attr('data-product-title', varTitle);
                        $varBtnWrap.slideDown(200);
                    }
                }
            });

            $(document).on('reset_data', '.variations_form', function () {
                self.currentVariationId = 0;
                self.currentImageId = 0;
                $('#fito-variation-tryon-wrapper').slideUp(150);
            });
        },

        initGalleryButtons: function () {
            if (!fitoData || fitoData.isProductAllowed === false || fitoData.galleryButtons !== 'yes') {
                return;
            }
            const self = this;
            const $gallery = $('.woocommerce-product-gallery');
            if (!$gallery.length) {
                return;
            }

            const prodId = self.currentProductId || parseInt($('.fito-tryon-btn').first().data('product-id'), 10) || 0;
            const prodTitle = self.currentProductTitle || $('.fito-tryon-btn').first().data('product-title') || '';
            const btnLabel = (fitoData.i18n && fitoData.i18n.tryOnGallery) || 'Try On This Look';

            $gallery.find('.woocommerce-product-gallery__image').each(function () {
                const $slide = $(this);
                if ($slide.find('.fito-tryon-btn--gallery').length) {
                    return;
                }
                const $img = $slide.find('img');
                const imgSrc = $img.attr('data-large_image') || $img.attr('src') || '';
                if (!imgSrc) {
                    return;
                }

                $slide.css('position', 'relative');
                const $btn = $('<button type="button" class="fito-tryon-btn fito-tryon-btn--gallery">' +
                    '<svg class="fito-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                    '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>' +
                    '</svg><span>' + btnLabel + '</span></button>');

                $btn.attr('data-product-id', prodId)
                    .attr('data-product-title', prodTitle)
                    .attr('data-product-image', imgSrc);
                $slide.append($btn);
            });
        },

        initCustomImageButtons: function () {
            if (!fitoData || fitoData.isProductAllowed === false) {
                return;
            }
            const isSingleProduct = $('body').hasClass('single-product') || $('.single-product, form.cart, .woocommerce-product-gallery').length > 0;
            if (!isSingleProduct) {
                return;
            }

            const rawSelectors = fitoData.customImageSelectors;
            if (!rawSelectors || typeof rawSelectors !== 'string' || !rawSelectors.trim()) {
                return;
            }

            const self = this;
            const selectors = rawSelectors.split(/[\r\n,]+/)
                .map(function (s) { return s.trim(); })
                .filter(function (s) { return s.length > 0; });

            if (!selectors.length) {
                return;
            }

            const combinedSelector = selectors.join(', ');
            const btnLabel = (fitoData.i18n && fitoData.i18n.tryOnImage) || 'Try On This Look';

            const attachBadges = function () {
                const prodId = self.currentProductId || parseInt($('.fito-tryon-btn--single, .fito-tryon-btn').first().data('product-id'), 10) || parseInt($('[name="add-to-cart"]').val(), 10) || 0;
                const prodTitle = self.currentProductTitle || $('.fito-tryon-btn--single, .fito-tryon-btn').first().data('product-title') || $('h1.product_title, .product_title').first().text().trim() || '';

                let $elements;
                try {
                    $elements = $(combinedSelector);
                } catch (err) {
                    return;
                }

                $elements.each(function () {
                    const $el = $(this);
                    if ($el.hasClass('fito-has-tryon-badge') || $el.closest('.fito-modal, .fito-modal-dialog, #fito-tryon-modal').length) {
                        return;
                    }

                    let $img = $el.is('img') ? $el : $el.find('img').first();
                    if (!$img.length) {
                        return;
                    }

                    const imgSrc = $img.attr('data-large_image') || $img.attr('data-src') || $img.attr('src') || '';
                    if (!imgSrc || imgSrc.indexOf('data:image/svg') === 0) {
                        return;
                    }

                    $el.addClass('fito-has-tryon-badge');

                    let $container = $el;
                    if ($el.is('img')) {
                        let $parent = $el.parent();
                        if ($parent.hasClass('fito-custom-image-wrapper')) {
                            $container = $parent;
                        } else if ($parent.children().length === 1 && !$parent.is('body, html')) {
                            $container = $parent;
                            $container.addClass('fito-custom-image-wrapper');
                        } else {
                            $el.wrap('<div class="fito-custom-image-wrapper"></div>');
                            $container = $el.parent();
                        }
                    } else {
                        $container.addClass('fito-custom-image-wrapper');
                    }

                    if ($container.find('.fito-tryon-btn--overlay').length) {
                        return;
                    }

                    const $btn = $('<button type="button" class="fito-tryon-btn fito-tryon-btn--overlay" aria-label="' + btnLabel + '">' +
                        '<span class="fito-btn-icon-spark">' +
                        '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                        '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>' +
                        '</svg>' +
                        '</span>' +
                        '<span class="fito-overlay-text">' + btnLabel + '</span>' +
                        '</button>');

                    $btn.attr('data-product-id', prodId)
                        .attr('data-product-title', prodTitle)
                        .attr('data-product-image', imgSrc);

                    $container.append($btn);
                });
            };

            attachBadges();

            if (window.MutationObserver) {
                const observer = new MutationObserver(function () {
                    attachBadges();
                });
                const targetNode = document.querySelector('.single-product, .site-main, main, #main, .product, body');
                if (targetNode) {
                    observer.observe(targetNode, { childList: true, subtree: true });
                }
            }
        },

        openModal: function () {
            const $m = $('#fito-tryon-modal');

            if (!this.historyStatePushed && window.history && typeof window.history.pushState === 'function') {
                try {
                    window.history.pushState({ fitoModalOpen: true }, '', window.location.href);
                    this.historyStatePushed = true;
                } catch (err) {
                    // Ignore restricted iframe environments
                }
            }

            if (this.currentProductImage) {
                $('#fito-loading-prod-thumb').attr('src', this.currentProductImage).show();
            } else {
                $('#fito-loading-prod-thumb').hide();
            }

            $('body').addClass('fito-modal-open');
            $m.attr('aria-hidden', 'false').show();
            setTimeout(function () {
                $m.addClass('is-open');
            }, 10);

            if (window.fitoTryonData && window.fitoTryonData.requireLogin && !window.fitoTryonData.isUserLoggedIn) {
                $('.fito-modal-body').hide();
                $('.fito-step-login-required').show();
            } else if (!this.resultImageUrl) {
                this.resetToUpload();
            } else {
                $('.fito-modal-body').hide();
                $('.fito-step-result').show();
                this.setSliderPosition(50);
            }
        },

        closeModal: function (triggerHistoryBack) {
            const shouldPop = (triggerHistoryBack !== false) && this.historyStatePushed;
            this.historyStatePushed = false;

            if (shouldPop && window.history && typeof window.history.back === 'function') {
                try {
                    window.history.back();
                } catch (err) {
                    // Ignore navigation errors
                }
            }

            const $m = $('#fito-tryon-modal');
            $m.removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('fito-modal-open');
            this.exitFullscreen();
            this.stopCamera();
            this.stopPolling();
            setTimeout(function () {
                $m.hide();
            }, 300);
        },

        resetToUpload: function () {
            this.exitFullscreen();
            this.stopPolling();
            this.userImageBlob = null;
            this.userImageBase64 = '';
            this.resultImageUrl = '';
            this.userImageUrl = '';
            this.currentJobToken = '';

            $('#fito-file-input').val('');
            $('#fito-dropzone').show();
            $('#fito-camera-container').hide();
            $('#fito-crop-container').hide();
            $('#fito-crop-image').attr('src', '');
            $('#fito-crop-preview-wrapper').css('aspect-ratio', '');
            $('#fito-slider-stage, #fito-slider-container').css('aspect-ratio', '');

            const saveLabel = (fitoData.i18n && fitoData.i18n.saveAccount) || 'Save to My Account';
            $('#fito-save-text').text(saveLabel);
            $('#fito-btn-save-account').prop('disabled', false);

            this.validateSubmitButton();

            if (window.fitoTryonData && window.fitoTryonData.requireLogin && !window.fitoTryonData.isUserLoggedIn) {
                $('.fito-modal-body').hide();
                $('.fito-step-login-required').show();
                return;
            }

            $('.fito-modal-body').hide();
            $('.fito-step-upload').show();
        },

        handleSelectedFile: function (file) {
            if (!file || !file.type || !file.type.match(/^image\/(jpeg|png|webp)$/i)) {
                const err = (fitoData.i18n && fitoData.i18n.invalidImage) || 'Please upload a valid JPG, PNG, or WebP image.';
                alert(err);
                return;
            }

            if (file.size > 10 * 1024 * 1024) {
                const err = (fitoData.i18n && fitoData.i18n.fileTooLarge) || 'Image file size must be 10MB or less.';
                alert(err);
                return;
            }

            this.userImageBlob = file;
            this.userImageBase64 = '';
            const reader = new FileReader();
            const self = this;
            reader.onload = function (e) {
                self.userImageBase64 = e.target.result;
                self.showPreview(self.userImageBase64);
            };
            reader.readAsDataURL(file);
        },

        showPreview: function (dataUrl) {
            this.stopCamera();
            $('#fito-dropzone').hide();
            $('#fito-camera-container').hide();
            $('#fito-crop-image').attr('src', dataUrl);

            // Display uncropped photo matching natural aspect ratio
            const tempImg = new Image();
            tempImg.onload = function () {
                if (tempImg.naturalWidth && tempImg.naturalHeight) {
                    const aspect = tempImg.naturalWidth / tempImg.naturalHeight;
                    $('#fito-crop-preview-wrapper').css('aspect-ratio', aspect);
                }
            };
            tempImg.src = dataUrl;

            $('#fito-crop-container').show();
            this.validateSubmitButton();
        },

        startCamera: function () {
            const self = this;
            const video = document.getElementById('fito-camera-stream');

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert((fitoData.i18n && fitoData.i18n.cameraError) || 'Camera not supported.');
                return;
            }

            navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false
            }).then(function (stream) {
                self.cameraStream = stream;
                if (video) {
                    video.srcObject = stream;
                    const playPromise = video.play();
                    if (playPromise !== undefined) {
                        playPromise.catch(function () {});
                    }
                }
                $('#fito-dropzone').hide();
                $('#fito-crop-container').hide();
                $('#fito-camera-container').show();
            }).catch(function () {
                alert((fitoData.i18n && fitoData.i18n.cameraError) || 'Unable to access camera.');
            });
        },

        stopCamera: function () {
            if (this.cameraStream) {
                this.cameraStream.getTracks().forEach(function (track) {
                    track.stop();
                });
                this.cameraStream = null;
            }
            const video = document.getElementById('fito-camera-stream');
            if (video) {
                video.srcObject = null;
            }
            $('#fito-camera-container').hide();
        },

        captureCameraSnapshot: function () {
            const video = document.getElementById('fito-camera-stream');
            if (!video || !video.videoWidth) return;

            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
            this.userImageBlob = null;
            this.userImageBase64 = dataUrl;
            this.showPreview(dataUrl);
        },

        validateSubmitButton: function () {
            const hasPhoto = Boolean(this.userImageBlob || this.userImageBase64);
            const hasConsent = $('#fito-consent-checkbox').is(':checked');
            $('#fito-btn-submit').prop('disabled', !(hasPhoto && hasConsent));
        },

        submitTryOn: function () {
            const self = this;
            const hasConsent = $('#fito-consent-checkbox').is(':checked');

            if (!this.userImageBlob && !this.userImageBase64) {
                alert((fitoData.i18n && fitoData.i18n.needPhoto) || 'Please upload or capture a photo first.');
                return;
            }

            if (!hasConsent) {
                alert((fitoData.i18n && fitoData.i18n.needConsent) || 'Please accept the privacy consent before continuing.');
                return;
            }

            $('#fito-btn-submit').prop('disabled', true);

            if (this.userImageBase64) {
                $('#fito-loading-user-thumb').attr('src', this.userImageBase64);
            }

            $('.fito-modal-body').hide();
            $('.fito-step-loading').show();
            this.startLoadingProgress();

            const formData = new FormData();
            if (this.userImageBlob) {
                const fileName = this.userImageBlob.name || 'photo.jpg';
                formData.append('photo', this.userImageBlob, fileName);
            } else if (this.userImageBase64) {
                formData.append('image_base64', this.userImageBase64);
            }

            formData.append('product_id', this.currentProductId);
            if (this.currentVariationId) {
                formData.append('variation_id', this.currentVariationId);
            }
            if (this.currentProductImage) {
                formData.append('product_image_url', this.currentProductImage);
            }
            if (this.currentImageId) {
                formData.append('product_image_id', this.currentImageId);
            }
            formData.append('consent', '1');
            const deviceId = this.getOrCreateDeviceId();
            formData.append('device_id', deviceId);

            $.ajax({
                url: fitoData.restUrl + 'submit',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-WP-Nonce': fitoData.nonce,
                    'X-Fito-Device-ID': deviceId
                },
                success: function (res) {
                    if (res && res.success) {
                        self.currentJobToken = res.token || '';
                        if (res.status === 'completed' && res.result_image_url) {
                            self.finishSuccess(res);
                        } else if (res.token) {
                            self.startPolling(res.token);
                        }
                    } else {
                        self.handleError(res ? res.message : (fitoData.i18n && fitoData.i18n.genericError));
                    }
                },
                error: function (xhr) {
                    let msg = (fitoData.i18n && fitoData.i18n.genericError) || 'Something went wrong.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            const parsed = JSON.parse(xhr.responseText);
                            if (parsed && parsed.message) {
                                msg = parsed.message;
                            }
                        } catch (e) {
                            // Non-JSON response
                        }
                    }
                    self.handleError(msg);
                }
            });
        },

        startLoadingProgress: function () {
            const self = this;
            let currentPct = 15;
            $('#fito-progress-bar').css('width', currentPct + '%');

            const msgs = (fitoData.statusMessages && fitoData.statusMessages.length > 0)
                ? fitoData.statusMessages
                : ['Warming up AI engine...'];

            self.messageIndex = 0;
            $('#fito-rotating-message').text(msgs[0]);

            clearInterval(self.messageTimer);
            self.messageTimer = setInterval(function () {
                self.messageIndex = (self.messageIndex + 1) % msgs.length;
                $('#fito-rotating-message').text(msgs[self.messageIndex]);

                if (currentPct < 85) {
                    currentPct += 10;
                    $('#fito-progress-bar').css('width', currentPct + '%');
                }
            }, 2500);
        },

        startPolling: function (token) {
            const self = this;
            this.stopPolling();
            this.pollCount = 0;

            this.pollInterval = setInterval(function () {
                self.pollCount++;
                if (self.pollCount > self.maxPolls) {
                    self.stopPolling();
                    const timeoutMsg = (fitoData.i18n && fitoData.i18n.timeoutError) || 'Generation timed out. Please try again.';
                    self.handleError(timeoutMsg);
                    return;
                }

                $.ajax({
                    url: fitoData.restUrl + 'status/' + encodeURIComponent(token),
                    type: 'GET',
                    headers: {
                        'X-WP-Nonce': fitoData.nonce
                    },
                    success: function (res) {
                        if (res && res.success) {
                            if (res.status === 'completed') {
                                self.finishSuccess(res);
                            } else if (res.status === 'failed') {
                                self.handleError(res.message || (fitoData.i18n && fitoData.i18n.genericError));
                            } else if (res.status === 'processing') {
                                if (res.progress && res.progress > 15) {
                                    $('#fito-progress-bar').css('width', Math.min(95, res.progress) + '%');
                                }
                                if (res.message) {
                                    $('#fito-rotating-message').text(res.message);
                                }
                            }
                        }
                    },
                    error: function (xhr) {
                        if (xhr.status === 404 || xhr.status === 400) {
                            self.stopPolling();
                            const msg = (xhr.responseJSON && xhr.responseJSON.message) || (fitoData.i18n && fitoData.i18n.genericError);
                            self.handleError(msg);
                        }
                    }
                });
            }, 2000);
        },

        stopPolling: function () {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
            if (this.messageTimer) {
                clearInterval(this.messageTimer);
                this.messageTimer = null;
            }
        },

        finishSuccess: function (res) {
            const self = this;
            this.stopPolling();
            $('#fito-progress-bar').css('width', '100%');

            this.resultImageUrl = res.result_image_url;
            this.userImageUrl = res.user_image_url || this.userImageBase64;

            $('#fito-slider-img-before').attr('src', this.userImageUrl);
            $('#fito-slider-img-after').attr('src', this.resultImageUrl);

            // Dynamically fit slider stage to natural image proportions without cropping
            const resultImg = new Image();
            resultImg.onload = function () {
                if (resultImg.naturalWidth && resultImg.naturalHeight) {
                    const aspect = resultImg.naturalWidth / resultImg.naturalHeight;
                    $('#fito-slider-stage, #fito-slider-container').css('aspect-ratio', aspect);
                }
            };
            resultImg.src = this.resultImageUrl;

            $('#fito-slider-container').show();

            setTimeout(function () {
                $('.fito-modal-body').hide();
                $('.fito-step-result').show();
                self.setSliderPosition(50);
            }, 300);
        },

        handleError: function (message) {
            this.stopPolling();
            const errMsg = message || (fitoData.i18n && fitoData.i18n.genericError) || 'An error occurred.';
            alert(errMsg);
            this.logClientError(errMsg);
            this.resetToUpload();
        },

        logClientError: function (errorMessage) {
            try {
                const deviceId = this.getOrCreateDeviceId();
                $.ajax({
                    url: fitoData.restUrl + 'log-client-error',
                    type: 'POST',
                    data: {
                        product_id: this.currentProductId || 0,
                        variation_id: this.currentVariationId || 0,
                        token: this.currentJobToken || '',
                        error_message: errorMessage,
                        user_image_url: this.userImageUrl || ''
                    },
                    headers: {
                        'X-WP-Nonce': fitoData.nonce,
                        'X-Fito-Device-ID': deviceId
                    }
                });
            } catch (err) {
                // Non-blocking error reporting
            }
        },

        initSliderEvents: function () {
            const self = this;
            const container = document.getElementById('fito-slider-container');
            if (!container) return;

            let isDragging = false;

            function getClientX(e) {
                if (e.touches && e.touches.length > 0) {
                    return e.touches[0].clientX;
                }
                if (e.changedTouches && e.changedTouches.length > 0) {
                    return e.changedTouches[0].clientX;
                }
                return e.clientX;
            }

            function updateSlider(e) {
                const rect = container.getBoundingClientRect();
                if (!rect.width) return;
                const clientX = getClientX(e);
                if (typeof clientX !== 'number') return;
                let pct = ((clientX - rect.left) / rect.width) * 100;
                pct = Math.max(0, Math.min(100, pct));
                self.setSliderPosition(pct);
            }

            function onStart(e) {
                isDragging = true;
                updateSlider(e);
            }

            function onMove(e) {
                if (!isDragging) return;
                if (e.cancelable) {
                    e.preventDefault();
                }
                updateSlider(e);
            }

            function onEnd() {
                isDragging = false;
            }

            container.addEventListener('mousedown', onStart);
            container.addEventListener('touchstart', onStart, { passive: true });

            window.addEventListener('mousemove', onMove);
            window.addEventListener('touchmove', onMove, { passive: false });

            window.addEventListener('mouseup', onEnd);
            window.addEventListener('touchend', onEnd);
            window.addEventListener('touchcancel', onEnd);
        },

        setSliderPosition: function (percent) {
            const isRTL = document.documentElement.dir === 'rtl' || document.body.classList.contains('rtl');
            const afterEl = document.getElementById('fito-slider-after');
            const handleEl = document.getElementById('fito-slider-handle');

            if (afterEl && handleEl) {
                let clipValue;
                if (isRTL) {
                    clipValue = 'polygon(' + percent + '% 0, 100% 0, 100% 100%, ' + percent + '% 100%)';
                } else {
                    clipValue = 'polygon(0 0, ' + percent + '% 0, ' + percent + '% 100%, 0 100%)';
                }
                afterEl.style.clipPath = clipValue;
                afterEl.style.webkitClipPath = clipValue;
                handleEl.style.left = percent + '%';
            }
        },

        downloadResult: function () {
            if (!this.resultImageUrl) return;
            const filename = 'fito-tryon-' + (this.currentProductId || 'result') + '.jpg';

            fetch(this.resultImageUrl)
                .then(function (response) {
                    if (!response.ok) throw new Error('Network error');
                    return response.blob();
                })
                .then(function (blob) {
                    const blobUrl = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = blobUrl;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    setTimeout(function () {
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(blobUrl);
                    }, 200);
                })
                .catch(function () {
                    window.open(this.resultImageUrl, '_blank');
                }.bind(this));
        },

        shareResult: function () {
            if (!this.resultImageUrl) return;

            if (navigator.share) {
                navigator.share({
                    title: this.currentProductTitle,
                    text: 'See how this looks on me with virtual try-on',
                    url: this.resultImageUrl
                }).catch(function () {});
            } else if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(this.resultImageUrl).then(function () {
                    alert((fitoData.i18n && fitoData.i18n.shareCopied) || 'Link copied to clipboard');
                }).catch(function () {
                    prompt('Copy link:', this.resultImageUrl);
                }.bind(this));
            } else {
                prompt('Copy link:', this.resultImageUrl);
            }
        },

        saveToAccount: function () {
            const self = this;
            if (!this.currentJobToken) return;

            const $btn = $('#fito-btn-save-account');
            $btn.prop('disabled', true);

            $.ajax({
                url: fitoData.restUrl + 'save-account',
                type: 'POST',
                data: {
                    token: self.currentJobToken
                },
                headers: {
                    'X-WP-Nonce': fitoData.nonce
                },
                success: function (res) {
                    if (res && res.success) {
                        const savedLabel = (fitoData.i18n && fitoData.i18n.saved) || 'Saved to account';
                        $('#fito-save-text').text(savedLabel);
                        $btn.prop('disabled', true);
                    } else {
                        $btn.prop('disabled', false);
                        alert(res && res.message ? res.message : (fitoData.i18n && fitoData.i18n.genericError));
                    }
                },
                error: function (xhr) {
                    $btn.prop('disabled', false);
                    const errMsg = (xhr.responseJSON && xhr.responseJSON.message) || (fitoData.i18n && fitoData.i18n.genericError);
                    alert(errMsg);
                }
            });
        },

        toggleFullscreen: function () {
            const stage = document.getElementById('fito-slider-stage');
            if (!stage) return;

            const isFs = Boolean(document.fullscreenElement || document.webkitFullscreenElement) || $(stage).hasClass('fito-is-fullscreen');
            if (isFs) {
                this.exitFullscreen();
            } else {
                this.enterFullscreen();
            }
        },

        enterFullscreen: function () {
            const stage = document.getElementById('fito-slider-stage');
            if (!stage) return;

            $(stage).addClass('fito-is-fullscreen');

            if (stage.requestFullscreen) {
                stage.requestFullscreen().catch(function () {});
            } else if (stage.webkitRequestFullscreen) {
                stage.webkitRequestFullscreen();
            } else if (stage.msRequestFullscreen) {
                stage.msRequestFullscreen();
            }
        },

        exitFullscreen: function () {
            const stage = document.getElementById('fito-slider-stage');
            if (stage) {
                $(stage).removeClass('fito-is-fullscreen');
            }

            if (document.fullscreenElement || document.webkitFullscreenElement) {
                try {
                    if (document.exitFullscreen) {
                        document.exitFullscreen().catch(function () {});
                    } else if (document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    } else if (document.msExitFullscreen) {
                        document.msExitFullscreen();
                    }
                } catch (err) {
                    // Ignore exit errors
                }
            }
        }
    };

    $(document).ready(function () {
        FitoApp.init();
    });

})(jQuery);
