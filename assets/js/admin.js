/**
 * SmartBot Admin JavaScript
 * Handles admin interface interactions
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initColorPicker();
        initLicenseActivation();
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
     * Initialize License Activation
     */
    function initLicenseActivation() {
        const $activateBtn = $('#smartbot-activate-license');
        const $licenseKeyInput = $('#smartbot_license_key');
        const $statusSpan = $('#smartbot-license-status');
        
        if (!$activateBtn.length) {
            return;
        }
        
        $activateBtn.on('click', function(e) {
            e.preventDefault();
            
            const licenseKey = $licenseKeyInput.val().trim();
            
            if (!licenseKey) {
                alert('Please enter a license key.');
                return;
            }
            
            // Disable button and show loading
            $activateBtn.prop('disabled', true).text('Activating...');
            
            // Make AJAX request
            $.ajax({
                url: smartbotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smartbot_activate_license',
                    nonce: smartbotAdmin.nonce,
                    license_key: licenseKey
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI for success
                        $statusSpan
                            .removeClass('smartbot-license-inactive')
                            .addClass('smartbot-license-active')
                            .html('<span class="dashicons dashicons-yes-alt"></span> Active');
                        
                        $activateBtn.prop('disabled', true).text('License Activated');
                        $licenseKeyInput.prop('disabled', true);
                        
                        // Show success message
                        showAdminNotice(response.data.message, 'success');
                        
                        // Reload page after 2 seconds
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        // Show error
                        showAdminNotice(response.data.message, 'error');
                        $activateBtn.prop('disabled', false).text('Activate License');
                    }
                },
                error: function(xhr, status, error) {
                    showAdminNotice('An error occurred. Please try again.', 'error');
                    $activateBtn.prop('disabled', false).text('Activate License');
                }
            });
        });
    }
    
    /**
     * Show admin notice
     */
    function showAdminNotice(message, type) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        
        const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.smartbot-admin-wrap h1').after($notice);
        
        // Make dismissible
        $(document).trigger('wp-updates-notice-added');
        
        // Auto remove after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
})(jQuery);