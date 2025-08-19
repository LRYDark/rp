function rp_loadCriForm(action, modal, params) {
    var formInput;

    if (params.form != undefined) {
        formInput = getRpFormData($('form[name="' + params.form + '"]'));
    }

    $.ajax({
        url: params.root_doc + '/ajax/cri.php',
        type: "POST",
        dataType: "html",
        data: {
            'action': action,
            'params': params,
            'pdf_action': params.pdf_action,
            'formInput': formInput,
            'modal': modal
        },
        success: function (response, opts) {
            try {
                var json = $.parseJSON(response);
                if (!json.success) {
                    $("#rp_cri_error").html(json.message).show().delay(2000).fadeOut('slow');
                }

            } catch (err) {
                $('#' + modal).html(response);

                switch (action) {

                    case 'saveCri':
                        // $('#' + modal).dialog('close');
                        window.location.reload();
                        break;
                    default:
                        glpi_html_dialog({
                            title: __('Rapport / Fiche de prise en charge', 'rp'),
                            body: response,
                            id: action,
                        })
                        break;
                }
            }
        }
    });
}