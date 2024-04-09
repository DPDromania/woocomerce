(function ($) {

    const checkoutSteps = ['billing', 'shipping'];

    $(document).ready(function () {

        // Checkout steps
        $.each(checkoutSteps, function (index, step) {
            $(document).on('change', '[name="' + step + '_country"]', function () {
                $('[name="' + step + '_pickup"]').val(null).trigger('change');
                $('.js-dpdro-offices-name').val(dpdRoGeneral.textNoOfficeSelected);
                $('body').trigger('update_checkout');
            });
            $(document).on('change', '[name="' + step + '_state"]', function () {
                $('[name="' + step + '_city"]').val(null);
                $('[name="' + step + '_postcode"]').val(null);
                $('[name="' + step + '_pickup"]').val(null).trigger('change');
                $('.js-dpdro-offices-name').val(dpdRoGeneral.textNoOfficeSelected);
                $('body').trigger('update_checkout');
            });
            $(document).on('change', '[name="' + step + '_city"]', function () {
                var self = $(this);
                if ($('[name="' + step + '_country"]').val() == 'RO' || $('[name="' + step + '_country"]').val() == 'BG') {
                    $.ajax({
                        url: dpdRo.ajaxurl,
                        data: {
                            action: 'searchCity',
                            nonce: dpdRoGeneral.noneSearchCity,
                            country: $('[name="' + step + '_country"]').val() == 'BG' ? 100 : 642,
                            state: $('[name="' + step + '_state"] option:selected').text().trim(),
                            postcode: $('[name="' + step + '_postcode"]').val().trim(),
                            search: self.val().trim(),
                        },
                        dataType: 'json',
                        type: 'POST',
                        success: function (response) {
                            if (response.length > 0 && response[0].postcode != '') {
                                $('[name="' + step + '_postcode"]').val(response[0].postcode);
                            }
                            if (response.length > 0 && response[0].siteId != '') {
                                var officeMap = 'https://services.dpd.ro/office_locator_widget_v2/office_locator.php?lang=en&showAddressForm=0&showOfficesList=0&siteID=' + response[0].siteId + '&selectOfficeButtonCaption=Select this office';
                                if ($('#frameOfficeLocator').length) {
                                    $('#frameOfficeLocator').attr('src', officeMap);
                                }
                            }
                        },
                        complete: function () {
                            $('body').trigger('update_checkout');
                        }
                    });
                }
                $('[name="' + step + '_pickup"]').val(null).trigger('change');
                $('.js-dpdro-offices-name').val(dpdRoGeneral.textNoOfficeSelected);
                $('.js-dpdro-offices-type').val('');
                $('body').trigger('update_checkout');
            });
            $(document).on('change', '[name="' + step + '_address_1"]', function () {
                $('[name="' + step + '_pickup"]').val(null).trigger('change');
                $('.js-dpdro-offices-name').val(dpdRoGeneral.textNoOfficeSelected);
                $('body').trigger('update_checkout');
            });
        });

        $(document).on('change', 'input[name^="payment_method"]', function () {
            $('body').trigger('update_checkout');
        });

        window.addEventListener('message', function (e) {

            if ($('#frameOfficeLocator').length) {
                if (e.origin == 'https://services.dpd.ro') {

                    // Office data
                    let office = e.data;

                    // Checkout steps
                    $.each(checkoutSteps, function (index, step) {

                        $('[name="' + step + '_country"]').val('RO');
                        if (typeof dpd_city_dropdown === 'undefined') {
                            $('[name="' + step + '_city"]').val(office.address.siteName);
                        }
                        $('[name="' + step + '_postcode"]').val(office.address.postCode);
                        $('[name="' + step + '_address_1"]').val(office.address.fullAddressString);
                        $('[name="' + step + '_pickup"]').val(office.id);
                        $('[name="' + step + '_pickup_name"]').val(office.name);
                        $('[name="' + step + '_pickup_type"]').val(office.type);
                        $('.js-dpdro-offices-name').val(office.name);
                        $('body').trigger('update_checkout');
                    });
                }
            }

        }, false);

    });

})(jQuery);