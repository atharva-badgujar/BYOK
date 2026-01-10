/**
 * SmartBot Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initColorPicker();
        initProviderSwitch();
        initAPITest();
        initFormSubmit();
    });
    
    /**
     * Initialize WordPress Color Picker
     */
    function initColorPicker() {
        if (typeof $.fn.wpColorPicker !== 'undefined') {
            $('.smartbot-color-picker').wpColorPicker();
        }
    }
    
    /**
     * Initialize Provider Switcher
     */
    function initProviderSwitch() {
        const $providerSelect = $('#smartbot_provider');
        const $providerName = $('#provider-name');
        const $providerLink = $('#provider-link');
        
        if (!$providerSelect.length) {
            return;
        }
        
        $providerSelect.on('change', function() {
            const provider = $(this).val();
            let link = '';
            let name = '';
            
            switch (provider) {
                case 'openai':
                    link = 'https://platform.openai.com/api-keys';
                    name = 'OpenAI';
                    break;
                case 'gemini':
                    link = 'https://makersuite.google.com/app/apikey';
                    name = 'Google Gemini';
                    break;
                case 'claude':
                    link = 'https://console.anthropic.com/';
                    name = 'Anthropic Claude';
                    break;
            }
            
            $providerName.text(name);
            $providerLink.attr('href', link).text(link);
        });
    }
    
    /**
     * Initialize API Test
     */
    function initAPITest() {
        const $testBtn = $('#smartbot-test-api');
        const $apiKeyInput = $('#smartbot_api_key');
        const $providerSelect = $('#smartbot_provider');
        const $resultDiv = $('#api-test-result');
        
        if (!$testBtn.length) {
            return;
        }
        
        $testBtn.on('click', function(e) {
            e.preventDefault();
            
            const apiKey = $apiKeyInput.val().trim();
            const provider = $providerSelect.val();
            
            if (!apiKey) {
                showNotice('Please enter an API key first.', 'error', $resultDiv);
                return;
            }
            
            $testBtn.prop('disabled', true).text('Testing...');
            $resultDiv.html('<div class="notice notice-info inline"><p>Testing API connection...</p></div>');
            
            $.ajax({
                url: smartbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smartbot_test_api',
                    nonce: smartbotAdmin.nonce,
                    api_key: apiKey,
                    provider: provider
                },
                success: function(response) {
                    $testBtn.prop('disabled', false).text('Test Connection');
                    
                    if (response.success) {
                        showNotice(response.data.message, 'success', $resultDiv);
                    } else {
                        showNotice(response.data.message, 'error', $resultDiv);
                    }
                },
                error: function(xhr, status, error) {
                    $testBtn.prop('disabled', false).text('Test Connection');
                    showNotice('An error occurred while testing the API. Please try again.', 'error', $resultDiv);
                }
            });
        });
    }
    
    /**
     * Initialize Form Submit with Success Popup
     */
    function initFormSubmit() {
        $('#smartbot-general-form, #smartbot-appearance-form').on('submit', function(e) {
            const $form = $(this);
            const $submitBtn = $form.find('input[type="submit"]');
            
            $submitBtn.prop('disabled', true).val('Saving...');
            sessionStorage.setItem('smartbot_settings_saved', 'true');
        });
        
        if (sessionStorage.getItem('smartbot_settings_saved') === 'true') {
            sessionStorage.removeItem('smartbot_settings_saved');
            showSuccessPopup();
        }
    }
    
    /**
     * Show Success Popup
     */
    function showSuccessPopup() {
        const popup = $('<div class="smartbot-popup-overlay">' +
            '<div class="smartbot-popup">' +
                '<div class="smartbot-popup-icon">✓</div>' +
                '<h2>Settings Saved!</h2>' +
                '<p>Your changes have been saved successfully.</p>' +
                '<button class="button button-primary smartbot-popup-close">OK</button>' +
            '</div>' +
        '</div>');
        
        $('body').append(popup);
        
        setTimeout(function() {
            popup.addClass('show');
        }, 100);
        
        popup.find('.smartbot-popup-close, .smartbot-popup-overlay').on('click', function(e) {
            if (e.target === this) {
                popup.removeClass('show');
                setTimeout(function() {
                    popup.remove();
                }, 300);
            }
        });
    }
    
    /**
     * Show Notice
     */
    function showNotice(message, type, $container) {
        const noticeClass = type === 'success' ? 'notice-success' : (type === 'error' ? 'notice-error' : 'notice-info');
        const $notice = $('<div class="notice ' + noticeClass + ' inline"><p>' + message + '</p></div>');
        
        $container.html($notice);
        
        if (type === 'success') {
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }
    
})(jQuery);
