/**
 * HookAnywhere - Admin Scripts
 * Extracted from inline <script> tags
 */

jQuery(document).ready(function ($) {

    // --- 1. List Table Toggle (hookaw post type only) ---
    $('.hookaw-list-toggle').on('change', function () {
        var checkbox = $(this);
        var postId = checkbox.data('post-id');
        var isActive = checkbox.is(':checked') ? 'yes' : 'no';
        var spinner = $('#hookaw-spinner-' + postId);

        // Show spinner, disable checkbox
        spinner.addClass('is-active');
        checkbox.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'hookaw_toggle_status',
                post_id: postId,
                is_active: isActive,
                nonce: hookawData.nonceToggle
            },
            success: function (response) {
                if (!response.success) {
                    alert('Failed to update toggle. Refresh page and try again.');
                    checkbox.prop('checked', !checkbox.is(':checked')); // revert
                }
            },
            error: function () {
                alert('An error occurred. Check browser console.');
                checkbox.prop('checked', !checkbox.is(':checked')); // revert
            },
            complete: function () {
                spinner.removeClass('is-active');
                checkbox.prop('disabled', false);
            }
        });
    });

    // --- 2. Configuration Meta Box (hookaw edit screen) ---
    if (typeof hookawData !== 'undefined' && hookawData.groupedHooks) {
        var groupedHooks = hookawData.groupedHooks;

        $(document).on('change', '.hookaw-integration-select', function () {
            var $this = $(this);
            var $wrapper = $this.closest('.hookaw-dashboard-wrap');
            var $actionSelect = $wrapper.find('.hookaw-action-select');
            var $customInputWrap = $wrapper.find('.hookaw-custom-hook-wrap');
            var $customInput = $customInputWrap.find('input');
            var integration = $this.val();

            var currentActionVal = $actionSelect.data('current-val') || '';
            $actionSelect.data('current-val', '');

            if (integration === 'custom_advanced') {
                $actionSelect.empty().hide();
                $customInputWrap.show();
                $customInput.prop('required', true);
            } else if (integration) {
                $customInputWrap.hide();
                $customInput.prop('required', false);

                $actionSelect.empty().show().prop('disabled', false);
                $actionSelect.append('<option value="" disabled selected>-- Select Event --</option>');

                if (groupedHooks[integration]) {
                    $.each(groupedHooks[integration], function (i, hook) {
                        var selected = (hook === currentActionVal) ? 'selected' : '';
                        $actionSelect.append('<option value="' + hook + '" ' + selected + '>' + hook + '</option>');
                    });
                }
            } else {
                $actionSelect.empty().show().prop('disabled', true);
                $actionSelect.append('<option value="" disabled selected>-- Select Event --</option>');
                $customInputWrap.hide();
            }
        });

        $('.hookaw-integration-select').trigger('change');
    }

    $(document).on('change', '.hookaw-action-select', function () {
        var $this = $(this);
        var $customInputWrap = $this.closest('.hookaw-dashboard-wrap').find('.hookaw-custom-hook-wrap');
        $customInputWrap.find('input').val($this.val());
    });


        $('#hookaw-auth-type-select').on('change', function () {
            var type = $(this).val();
            $('#hookaw-auth-basic-fields').hide();
            $('#hookaw-auth-bearer-fields').hide();
            $('#hookaw-auth-header-fields').hide();
            $('#hookaw-auth-query-fields').hide();

            if (type === 'basic') {
                $('#hookaw-auth-basic-fields').css('display', 'grid');
            } else if (type === 'bearer') {
                $('#hookaw-auth-bearer-fields').css('display', 'grid');
            } else if (type === 'header') {
                $('#hookaw-auth-header-fields').css('display', 'grid');
            } else if (type === 'query') {
                $('#hookaw-auth-query-fields').css('display', 'grid');
            }
        });

        // Add new row for headers and parameters
        $('.hookaw-add-row').on('click', function () {
            var $this = $(this);
            var type = $this.data('type');
            var $container = $this.prevAll('.hookaw-repeater-rows').first();
            var rowCount = $container.children('.hookaw-repeater-row').length;
            var namePrefix = type === 'headers' ? 'hookaw_headers' : 'hookaw_body_params';
            var keyLabel = type === 'headers' ? 'Header Name:' : 'Parameter key:';
            var valueLabel = type === 'headers' ? 'Header Value:' : 'Value:';
            var keyPlaceholder = type === 'headers' ? 'e.g. Content-Type' : 'e.g. source';
            var valuePlaceholder = type === 'headers' ? 'e.g. application/json' : 'e.g. wordpress';

            var newRow = `
                <div class="hookaw-grid-repeater hookaw-repeater-row" style="align-items: end; margin-bottom:10px;">
                    <div class="hookaw-field-group">
                        <label>${keyLabel}</label>
                        <input type="text" name="${namePrefix}[${rowCount}][key]" style="width: 100%;" placeholder="${keyPlaceholder}" />
                    </div>
                    <div class="hookaw-field-group">
                        <label>${valueLabel}</label>
                        <input type="text" name="${namePrefix}[${rowCount}][value]" style="width: 100%;" placeholder="${valuePlaceholder}" />
                    </div>
                    <div class="hookaw-field-group" style="max-width: 50px;">
                        <label style="visibility: hidden;">${keyLabel}</label>
                        <button type="button" class="hookaw-remove-row">&times;</button>
                    </div>
                </div>
            `;
            $container.append(newRow);
        });

        // Remove row
        $(document).on('click', '.hookaw-remove-row', function () {
            $(this).closest('.hookaw-repeater-row').remove();
        });



    // --- 4. Export Logs Button Injection ---
    if (typeof hookawData.exportUrl !== 'undefined' && hookawData.exportUrl !== '') {
        var searchBox = document.querySelector('.search-box');
        if (searchBox) {
            var exportBtn = document.createElement('a');
            exportBtn.href = hookawData.exportUrl;
            exportBtn.className = "button button-secondary";
            exportBtn.style.cssText = "display: inline-flex; align-items: center; gap: 6px; margin-right: 10px; vertical-align: middle;";
            exportBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 14px; height: 14px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>' + (hookawData.i18n.exportLogs || 'Export Logs to CSV');

            searchBox.insertBefore(exportBtn, searchBox.firstChild);
        }
    }

}); // end ready
