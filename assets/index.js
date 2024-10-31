jQuery(document).ready(function ($) {
    $('.reainc-form').on('click', '.submitdelete', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    let generate = $('#key_generate').is(':checked');
    if (generate) {
        $('#key_key').attr('readonly', true);
    } else {
        $('#key_key').removeAttr('readonly');
    }

    $('.reainc-form').on('change', '#key_generate', function (e) {
        if (this.checked) {
            $('#key_key').attr('readonly', true);
        } else {
            $('#key_key').removeAttr('readonly');
        }
    });

    $('.reainc-form').on('click', '#create_api_key', function (e) {
        e.preventDefault();
        $('.reainc-form .button-holder').addClass('loading');
        $('.reainc-form #create_api_key').attr('disabled', 'disabled');
        
        let key = $('#key_key').val(),
            domain = $('#key_domain').val(),
            generate = $('#key_generate').is(':checked'),
            key_active = $('#key_active').is(':checked'),
            key_enabled = $('#key_enabled').is(':checked');

        $.ajax({
            url: reainc.ajaxurl,
            data: {
                'action': 'create_api_key',
                'security': $('.reainc-form input[name="security"]').val(),
                'key': key,
                'domain': domain,
                'generate': generate,
                'key_active': 'key_active',
                'key_enabled': key_enabled
            },
            type: 'POST',
            beforeSend: function () {
                $('.reainc-form #create_api_key').attr('disabled', 'disabled');
            },
            success: function (response) {
                if (response.success) {
                    window.location.href = reainc.redirect_url + '&key-saved=true';
                    return;
                }

                $('.reainc-form .attr_status').text(response.data.message);
            },
            complete: function () {
                $('.reainc-form #create_api_key').removeAttr('disabled');
                $('.reainc-form .button-holder').removeClass('loading');
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $('.reainc-form .attr_status').text(errorThrown);
                $('.reainc-form #trigger_cron').removeAttr('disabled');
                $('.reainc-form .button-holder').removeClass('loading');
            }
        });
    });
});