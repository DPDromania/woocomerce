(function ($) {

    const checkoutSteps = ['billing', 'shipping'];

    $(document).ready(function () {

        // Checkout steps
        $.each(checkoutSteps, function (index, step) {
            $(document).on('change', '[name="' + step + '_country"]', function (e) {
                $('[name="' + step + '_pickup"]').val(null).trigger('change');
                $('.js-dpdro-offices-name').val(dpdRoGeneral.textNoOfficeSelected);
                $('body').trigger('update_checkout');
                var self = $(this);
                reloadWidget(step, self);

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
                reloadWidget(step, self);
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

            $('[name="' + step + '_country"]').trigger('change');
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

                        $('[name="' + step + '_country"]').val($('[name="' + step + '_country"]').val());
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

    function reloadWidget(step, self)
    {

        $countryId = getCountryId($('[name="' + step + '_country"]').val());
        console.log('reloading widget:' + $countryId);

        $.ajax({
            url: dpdRo.ajaxurl,
            data: {
                action: 'searchCity',
                nonce: dpdRoGeneral.noneSearchCity,
                country: $countryId,
                state: $('[name="' + step + '_state"] option:selected').text().trim(),
                postcode: $('[name="' + step + '_postcode"]').val().trim(),
                search: self.val().trim(),
            },
            dataType: 'json',
            type: 'POST',
            success: function (response) {
                console.log('success');
                console.log(response);
                if (response.length > 0 && response[0].postcode != '') {
                    $('[name="' + step + '_postcode"]').val(response[0].postcode);
                }
                if (response.length > 0 && response[0].siteId != '') {
                    var officeMap = 'https://services.dpd.ro/office_locator_widget_v3/office_locator.php?lang=en&showAddressForm=0&showOfficesList=0&siteID=' + response[0].siteId + '&selectOfficeButtonCaption=Select this office' + '&countryId=' + $countryId;

                    if ($('#frameOfficeLocator').length) {
                        $('#frameOfficeLocator').attr('src', officeMap);
                    }
                } else {
                    var officeMap = 'https://services.dpd.ro/office_locator_widget_v3/office_locator.php?lang=en&showAddressForm=0&showOfficesList=0&siteID=&selectOfficeButtonCaption=Select this office' + '&countryId=' + $countryId;

                    if ($('#frameOfficeLocator').length) {
                        $('#frameOfficeLocator').attr('src', officeMap);
                    }
                }

                console.log(officeMap);
            },
            error: function(response) {
                console.log('error:')
                console.log(response);
            },
            complete: function () {
                $('body').trigger('update_checkout');
            }
        });

    }

    function getCountryId(code)
    {
        console.log(code);
        switch (code) {
            case 'RO':
                return 642;
            case 'BG':
                return 100;
            case 'GR':
                return 300;
            case 'HU':
                return 348;
            case 'PL':
                return 616;
            case 'SL':
                return 703;
            case 'SK':
                return 705;
            case 'CZ':
                return 203;
            case 'HR':
                return 191;
            case 'AT':
                return 40;
            case 'IT':
                return 380;
            case 'DE':
                return 276;
            case 'ES':
                return 724;
            case 'FR':
                return 250;
            case 'NL':
                return 528;
            case 'BE':
                return 56;
            case 'EE':
                return 233;
            case 'DK':
                return 208;
            case 'LU':
                return 442;
            case 'LV':
                return 428;
            case 'LT':
                return 440;
            case 'FI':
                return 246;
            case 'PT':
                return 620;
            case 'SE':
                return 752;
            default:
                return 642;
        }
    }


})(jQuery);