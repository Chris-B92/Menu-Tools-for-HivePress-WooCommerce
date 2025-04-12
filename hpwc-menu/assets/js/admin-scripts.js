/**
 * Admin scripts for HivePress & WooCommerce Menu Integration
 */
jQuery(document).ready(function($) {
    // Add link button handler
    $('#hpwc-add-link').on('click', function() {
        var index = $('.hpwc-link-row').length;
        var template = $('.hpwc-link-row').first().clone();
        template.find('input[type="text"]').val('');
        template.find('select').val('');
        template.find('input[type="checkbox"]').prop('checked', false);
        template.find('input[name^="hpwc_menu_custom_links"][name$="[type]"][value="wp_page"]').prop('checked', true);
        template.find('input[name^="hpwc_menu_custom_links"][name$="[menu_location]"]').first().prop('checked', true);
        template.find('input[name^="hpwc_menu_custom_links"][name$="[position]"]').first().prop('checked', true);
        template.find('input[name^="hpwc_menu_custom_links_visibility"][name$="[type]"]').first().prop('checked', true);
        template.find('.hpwc-roles-selector').hide();
        template.find('input[name^="hpwc_menu_custom_links"]').each(function() {
            var name = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
            $(this).attr('name', name);
        });
        template.find('select[name^="hpwc_menu_custom_links"]').each(function() {
            var name = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
            $(this).attr('name', name);
        });
        template.find('input[name^="hpwc_menu_custom_links_visibility"]').each(function() {
            var name = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
            $(this).attr('name', name);
        });
        $('#hpwc-links-container').append(template);
        template.find('.hpwc-link-type:checked').trigger('change');
    });

    function initLinkFunctionality() {
        $('.hpwc-remove-link').off('click').on('click', function() {
            if ($('.hpwc-link-row').length > 1) {
                $(this).closest('.hpwc-link-row').remove();
                reindexInputs();
            } else {
                var row = $(this).closest('.hpwc-link-row');
                row.find('input[type="text"]').val('');
                row.find('select').val('');
                row.find('input[type="checkbox"]').prop('checked', false);
                row.find('input[name^="hpwc_menu_custom_links"][name$="[type]"][value="wp_page"]').prop('checked', true).trigger('change');
                row.find('input[name^="hpwc_menu_custom_links"][name$="[menu_location]"]').first().prop('checked', true);
                row.find('input[name^="hpwc_menu_custom_links"][name$="[position]"]').first().prop('checked', true);
                row.find('input[name^="hpwc_menu_custom_links_visibility"][name$="[type]"]').first().prop('checked', true);
                row.find('.hpwc-roles-selector').hide();
            }
        });

        // Keep all dropdowns enabled; no disabling logic
        $('.hpwc-link-type').off('change').on('change', function() {
            // No action needed here since all inputs remain enabled
        });

        $('.hpwc-visibility-roles, .hpwc-visibility-subscription').off('change').on('change', function() {
            var row = $(this).closest('.hpwc-link-row');
            var type = $(this).val();
            if (type === 'roles') {
                row.find('.hpwc-roles-selector').show();
            } else {
                row.find('.hpwc-roles-selector').hide();
            }
        });

        // Ensure all dropdowns and inputs are enabled by default
        $('.hpwc-wp-page-select, .hpwc-url-input, .hpwc-wc-page-select, .hpwc-hp-route-select').prop('disabled', false);
    }

    function reindexInputs() {
        $('.hpwc-link-row').each(function(index) {
            $(this).find('input[name^="hpwc_menu_custom_links"]').each(function() {
                var name = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
                $(this).attr('name', name);
            });
            $(this).find('select[name^="hpwc_menu_custom_links"]').each(function() {
                var name = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
                $(this).attr('name', name);
            });
            $(this).find('input[name^="hpwc_menu_custom_links_visibility"]').each(function() {
                var name = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
                $(this).attr('name', name);
            });
        });
    }

    initLinkFunctionality();
});