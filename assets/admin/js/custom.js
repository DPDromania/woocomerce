(function ($) {

    function dpdRoLoader(container) {
        var loader = `<div class="d-loader js-d-loader"><span></span></div>`;
        $(container).append(loader);
    }

    function dpdRoResponse(message, type) {
        var response = `
            <div class="d-message-box js-d-message-box ` + type + `">
                <div class="d-message-box-body">
                    <p>` + message + `</p>
                    <button type="button" class="js-d-hide-message">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            </div>
        `;
        $('.js-d-message').html(response);
        setTimeout(function () {
            $('.js-d-message-box').fadeOut(500, function () {
                $('.js-d-message-box').remove();
            });
        }, 9000);
    }

    function dpdRoTaxRate() {
        var services = '';
        $.each(dpdRoGeneral.services, function (key, service) {
            services += '<option value="' + service.service_id + '">' + service.service_name + '</option>';
        });
        var zones = '';
        $.each(dpdRoGeneral.zones, function (key, zone) {
            zones += '<option value="' + key + '">' + zone + '</option>';
        });
        var html = `
            <tr class="js-d-taxrate">
                <td>
                    <div class="d-field full">
                        <select name="service_id">` + services + `</select>
                    </div>
                </td>
                <td class="w-210">
                    <div class="d-field full">
                        <select name="zone_id">` + zones + `</select>
                    </div>
                </td>
                <td class="w-210">
                    <div class="d-field full">
                        <select name="based_on">
                            <option selected value="1">` + dpdRoGeneral.translate.price + `</option>
                            <option value="0">` + dpdRoGeneral.translate.weight + `</option>
                        </select>
                    </div>
                </td>
                <td class="w-130">
                    <div class="d-field full">
                        <input type="text" name="apply_over" value="0.00" />
                    </div>
                </td>
                <td class="w-130">
                    <div class="d-field full">
                        <input type="text" name="tax_rate" value="0.00" />
                    </div>
                </td>
                <td class="w-145">
                    <div class="d-field full">
                        <select name="calculation_type">
                            <option selected value="1">` + dpdRoGeneral.translate.fixed + `</option>
                            <option value="0">` + dpdRoGeneral.translate.percentage + `</option>
                        </select>
                    </div>
                </td>
                <td class="w-130">
                    <div class="d-field full">
                        <select name="status">
                            <option value="1">` + dpdRoGeneral.translate.enabled + `</option>
                            <option value="0">` + dpdRoGeneral.translate.disabled + `</option>
                        </select>
                    </div>
                </td>
                <td class="w-130">
                    <button type="button" class="d-button danger js-d-taxrate-remove">
                        ` + dpdRoGeneral.translate.remove + `
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </td>
            </tr>
        `;
        return html;
    }

    function dpdRoTaxRateOffices() {
        var services = '';
        $.each(dpdRoGeneral.services, function (key, service) {
            services += '<option value="' + service.service_id + '">' + service.service_name + '</option>';
        });
        var zones = '';
        $.each(dpdRoGeneral.zonesOffices, function (key, zone) {
            var zoneName = zone.office_site_type != '' ? zone.office_site_type + ' ' + zone.office_site_name : zone.office_site_name;
            zones += '<option value="' + zone.office_site_id + '">' + zoneName + '</option>';
        });
        var html = `
            <tr class="js-d-taxrate-offices">
                <td>
                    <div class="d-field full">
                        <select name="service_id">` + services + `</select>
                    </div>
                </td>
                <td class="w-210">
                    <div class="d-field full">
                        <select name="zone_id">` + zones + `</select>
                    </div>
                </td>
                <td class="w-210">
                    <div class="d-field full">
                        <select name="based_on">
                            <option selected value="1">` + dpdRoGeneral.translate.price + `</option>
                            <option value="0">` + dpdRoGeneral.translate.weight + `</option>
                        </select>
                    </div>
                </td>
                <td class="w-130">
                    <div class="d-field full">
                        <input type="text" name="apply_over" value="0.00" />
                    </div>
                </td>
                <td class="w-130">
                    <div class="d-field full">
                        <input type="text" name="tax_rate" value="0.00" />
                    </div>
                </td>
                <td class="w-145">
                    <div class="d-field full">
                        <select name="calculation_type">
                            <option selected value="1">` + dpdRoGeneral.translate.fixed + `</option>
                            <option value="0">` + dpdRoGeneral.translate.percentage + `</option>
                        </select>
                    </div>
                </td>
                <td class="w-130">
                    <div class="d-field full">
                        <select name="status">
                            <option value="1">` + dpdRoGeneral.translate.enabled + `</option>
                            <option value="0">` + dpdRoGeneral.translate.disabled + `</option>
                        </select>
                    </div>
                </td>
                <td class="w-130">
                    <button type="button" class="d-button danger js-d-taxrate-offices-remove">
                        ` + dpdRoGeneral.translate.remove + `
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </td>
            </tr>
        `;
        return html;
    }

    $(document).ready(function () {

        // Response message from requests
        $(document).on('click', '.js-d-hide-message', function (e) {
            var self = $(this);
            self.closest('.js-d-message-box').fadeOut(500, function () {
                self.closest('.js-d-message-box').remove();
            });
        });

        // Tabs actions from admin panel
        $(document).on('click', '.js-d-box-nav', function (e) {
            e.preventDefault();
            var content = $('.js-d-box-content[data-content="' + $(this).attr('data-content') + '"]');
            $('.js-d-box-nav').removeClass('active');
            $('.js-d-box-content').removeClass('active');
            $(this).addClass('active');
            content.addClass('active');
            return false;
        });

        // Allow only numbers
        $(document).on('keyup', '.js-d-setting-number', function (e) {
            if ((e.keyCode >= 96 && e.keyCode <= 105) || (e.keyCode >= 48 && e.keyCode <= 57)) {
                $(this).val(Number($(this).val()));
            } else {
                $(this).val(0);
            }
        });

        // Allow only numbers and dots
        $(document).on('keypress', '.js-d-setting-float', function (e) {
            if ((e.which != 46 || $(this).val().indexOf('.') != -1) && (e.which < 48 || e.which > 57)) {
                $(this).val(0);
                e.preventDefault();
            }
        });

        // Courier service payer
        $(document).on('change', '.js-d-setting[name="courier_service_payer"]', function (e) {
            if ($(this).val() == 'THIRD_PARTY') {
                $('.js-d-field-id-payer-contract').removeClass('hidden');
            } else {
                if (!$('.js-d-field-id-payer-contract').hasClass('hidden')) {
                    $('.js-d-field-id-payer-contract').addClass('hidden');
                }
            }
        });

        // Client contracts
        $(document).on('change', '.js-d-setting[name="client_contracts"]', function (e) {
            $('.js-d-setting[name="office_locations"]').val(0);
        });

        // Office locations
        $(document).on('change', '.js-d-setting[name="office_locations"]', function (e) {
            $('.js-d-setting[name="client_contracts"]').val(0);
        });

        // Save connection
        $(document).on('click', '.js-d-save-connection', function (e) {
            e.preventDefault();
            var self = $(this);
            var data = {
                action: 'saveConnection',
                nonce: self.attr('data-nonce'),
                params: {
                    username: $('.js-d-setting[name="username"]').val().trim(),
                    password: $('.js-d-setting[name="password"]').val().trim(),
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(self.closest('.js-d-content'));
                },
                success: function (response) {
                    $('.js-d-loader').remove();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                    if (response.error) {
                        dpdRoResponse(response.message, 'error');
                    } else {
                        dpdRoResponse(response.message, 'success');
                        setTimeout(function () {
                            window.location.href = response.redirect;
                        }, 500);
                    }
                },
                error: function (error) {
                    $('.js-d-loader').remove();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                    dpdRoResponse(dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Update connection
        $(document).on('click', '.js-d-update-connection', function (e) {
            e.preventDefault();
            var self = $(this);
            var data = {
                action: 'updateConnection',
                nonce: self.attr('data-nonce'),
                params: {
                    username: $('.js-d-setting[name="username"]').val().trim(),
                    password: $('.js-d-setting[name="password"]').val().trim(),
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(self.closest('.js-d-content'));
                },
                success: function (response) {
                    $('.js-d-loader').remove();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                    if (response.error) {
                        dpdRoResponse(response.message, 'error');
                    } else {
                        dpdRoResponse(response.message, 'success');
                    }
                },
                error: function (error) {
                    $('.js-d-loader').remove();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                    dpdRoResponse(dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Save settings
        $(document).on('click', '.js-d-save-settings', function (e) {
            e.preventDefault();
            var self = $(this);
            var data = {
                action: 'saveSettings',
                nonce: self.attr('data-nonce'),
                params: {
                    packages: $('.js-d-setting[name="packages"]').val().trim(),
                    contents: $('.js-d-setting[name="contents"]').val().trim(),
                    packaging_method: $('.js-d-setting[name="packaging_method"]:checked').val().trim(),
                    services: [],
                    client_contracts: $('.js-d-setting[name="client_contracts"]').val().trim(),
                    office_locations: $('.js-d-setting[name="office_locations"]').val().trim(),
                    sender_payer_insurance: $('.js-d-setting[name="sender_payer_insurance"]:checked').val().trim(),
                    include_shipping_price: $('.js-d-setting[name="include_shipping_price"]').val().trim(),
                    courier_service_payer: $('.js-d-setting[name="courier_service_payer"]').val().trim(),
                    id_payer_contract: $('.js-d-setting[name="id_payer_contract"]').val().trim(),
                    print_format: $('.js-d-setting[name="print_format"]').val().trim(),
                    print_paper_size: $('.js-d-setting[name="print_paper_size"]').val().trim(),
                    test_or_open: $('.js-d-setting[name="test_or_open"]').val().trim(),
                    test_or_open_courier: $('.js-d-setting[name="test_or_open_courier"]').val().trim(),
                }
            };
            var services = $('.js-d-setting-services');
            services.each(function (index, element) {
                if ($(element).is(':checked')) {
                    data['params']['services'].push($(element).val());
                }
            });
            data['params']['services'] = JSON.stringify(data['params']['services']);
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(self.closest('.js-d-content'));
                },
                success: function (response) {
                    $('.js-d-loader').remove();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                    if (response.error) {
                        dpdRoResponse(response.message, 'error');
                    } else {
                        dpdRoResponse(response.message, 'success');
                    }
                },
                error: function (error) {
                    $('.js-d-loader').remove();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                    dpdRoResponse(dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Save advance settings
        $(document).on('click', '.js-d-save-advance-settings', function (e) {
            e.preventDefault();
            var self = $(this);
            var data = {
                action: 'saveAdvanceSettings',
                nonce: self.attr('data-nonce'),
                params: {
                    max_weight: $('.js-d-setting[name="max_weight"]').val().trim(),
                    max_weight_automat: $('.js-d-setting[name="max_weight_automat"]').val().trim(),
                    show_office_selection: $('.js-d-setting[name="show_office_selection"]:checked').length ? '1' : '0',
                    county_before_city: $('.js-d-setting[name="county_before_city"]:checked').length ? '1' : '0',
                    city_dropdown: $('.js-d-setting[name="city_dropdown"]:checked').length ? '1' : '0',
                    use_default_weight: $('.js-d-setting[name="use_default_weight"]:checked').length ? '1' : '0',
                    default_weight: $('.js-d-setting[name="default_weight"]').val().trim(),
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(self.closest('.js-d-content'));
                },
                success: function (response) {
                    $('.js-d-loader').remove();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                    if (response.error) {
                        dpdRoResponse(response.message, 'error');
                    } else {
                        dpdRoResponse(response.message, 'success');
                    }
                },
                error: function (error) {
                    $('.js-d-loader').remove();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                    dpdRoResponse(dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Save payment settings
        $(document).on('click', '.js-d-save-payment-settings', function (e) {
            e.preventDefault();
            var self = $(this);
            var data = {
                action: 'savePaymentSettings',
                nonce: self.attr('data-nonce'),
                params: {
                    payment_zones: [],
                }
            };
            var zones = $('.js-payment-zone');
            zones.each(function (index, element) {
                data['params']['payment_zones'].push({
                    id: $(element).find('.js-d-setting[name="payment_id"]').val().trim(),
                    name: $(element).find('.js-d-setting[name="payment_name"]').val().trim(),
                    type: $(element).find('.js-d-setting[name="payment_type"]').val(),
                    tax_rate: $(element).find('.js-d-setting[name="payment_tax_rate"]').val().trim(),
                    vat_rate: $(element).find('.js-d-setting[name="payment_vat_rate"]').val().trim(),
                    status: $(element).find('.js-d-setting[name="payment_status"]').val(),
                });
            });
            data['params']['payment_zones'] = JSON.stringify(data['params']['payment_zones']);
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(self.closest('.js-d-content'));
                },
                success: function (response) {
                    $('.js-d-loader').remove();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                    if (response.error) {
                        dpdRoResponse(response.message, 'error');
                    } else {
                        dpdRoResponse(response.message, 'success');
                    }
                },
                error: function (error) {
                    $('.js-d-loader').remove();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                    dpdRoResponse(dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Add tax rate
        $(document).on('click', '.js-d-taxrate-add', function (e) {
            e.preventDefault();
            var self = $(this);
            var taxRateEmpty = self.closest('.js-d-taxrates').find('.js-d-taxrates-list .js-d-taxrate-empty');
            if (taxRateEmpty.length > 0) {
                taxRateEmpty.remove();
            }
            var taxRate = dpdRoTaxRate();
            self.closest('.js-d-taxrates').find('.js-d-taxrates-list').append(taxRate);
            $('html, body').animate({
                scrollTop: 0
            }, 500);
            dpdRoResponse(dpdRoGeneral.infoMessage, 'warning');
            return false;
        });

        // Remove tax rate
        $(document).on('click', '.js-d-taxrate-remove', function (e) {
            e.preventDefault();
            var self = $(this);
            var taxRates = self.closest('.js-d-taxrates-list').find('.js-d-taxrate');
            setTimeout(function () {
                self.closest('.js-d-taxrate').fadeOut(500, function () {
                    self.closest('.js-d-taxrate').remove();
                });
            }, 100);
            $('html, body').animate({
                scrollTop: 0
            }, 500);
            dpdRoResponse(dpdRoGeneral.infoMessage, 'warning');
            if (taxRates.length < 2) {
                self.closest('.js-d-taxrates-list').html(`
                    <tr class="js-d-taxrate-empty">
                        <td colspan="8">
                            <p class="d-table-empty">` + dpdRoGeneral.translate.no_tax + `</p>
                        </td>
                    </tr>
                `);
            }
            return false;
        });

        // Save tax rate
        $(document).on('click', '.js-d-taxrate-save', function (e) {
            e.preventDefault();
            var self = $(this);
            var data = {
                action: 'saveTaxRates',
                nonce: self.attr('data-nonce'),
                params: []
            };
            var taxRates = $(this).closest('.js-d-taxrates').find('.js-d-taxrate');
            taxRates.each(function (index, element) {
                data['params'].push({
                    'service_id': $(element).find('select[name="service_id"]').val().trim(),
                    'zone_id': $(element).find('select[name="zone_id"]').val().trim(),
                    'based_on': $(element).find('select[name="based_on"]').val().trim(),
                    'apply_over': $(element).find('input[name="apply_over"]').val().trim(),
                    'tax_rate': $(element).find('input[name="tax_rate"]').val().trim(),
                    'calculation_type': $(element).find('select[name="calculation_type"]').val().trim(),
                    'status': $(element).find('select[name="status"]').val().trim(),
                });
            });
            data['params'] = JSON.stringify(data['params']);
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(self.closest('.js-d-content'));
                },
                success: function (response) {
                    $('.js-d-loader').remove();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                    if (response.error) {
                        dpdRoResponse(response.message, 'error');
                    } else {
                        dpdRoResponse(response.message, 'success');
                    }
                },
                error: function (error) {
                    $('.js-d-loader').remove();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                    dpdRoResponse(dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Add tax rate offices
        $(document).on('click', '.js-d-taxrate-offices-add', function (e) {
            e.preventDefault();
            var self = $(this);
            var taxRateOfficesEmpty = self.closest('.js-d-taxrates-offices').find('.js-d-taxrates-offices-list .js-d-taxrate-offices-empty');
            if (taxRateOfficesEmpty.length > 0) {
                taxRateOfficesEmpty.remove();
            }
            var taxRateOffices = dpdRoTaxRateOffices();
            $(this).closest('.js-d-taxrates-offices').find('.js-d-taxrates-offices-list').append(taxRateOffices);
            $('html, body').animate({
                scrollTop: 0
            }, 500);
            dpdRoResponse(dpdRoGeneral.infoMessage, 'warning');
            return false;
        });

        // Remove tax rate offices
        $(document).on('click', '.js-d-taxrate-offices-remove', function (e) {
            e.preventDefault();
            var self = $(this);
            var taxRatesOffices = self.closest('.js-d-taxrates-offices-list').find('.js-d-taxrate-offices');
            setTimeout(function () {
                self.closest('.js-d-taxrate-offices').fadeOut(500, function () {
                    self.closest('.js-d-taxrate-offices').remove();
                });
            }, 100);
            $('html, body').animate({
                scrollTop: 0
            }, 500);
            dpdRoResponse(dpdRoGeneral.infoMessage, 'warning');
            if (taxRatesOffices.length < 2) {
                self.closest('.js-d-taxrates-offices-list').html(`
                    <tr class="js-d-taxrate-offices-empty">
                        <td colspan="8">
                            <p class="d-table-empty">` + dpdRoGeneral.translate.no_tax + `</p>
                        </td>
                    </tr>
                `);
            }
            return false;
        });

        // Save tax rate offices
        $(document).on('click', '.js-d-taxrate-offices-save', function (e) {
            e.preventDefault();
            var self = $(this);
            var data = {
                action: 'saveTaxRatesOffices',
                nonce: self.attr('data-nonce'),
                params: []
            };
            var taxRatesOffices = $(this).closest('.js-d-taxrates-offices').find('.js-d-taxrate-offices');
            taxRatesOffices.each(function (index, element) {
                data['params'].push({
                    'service_id': $(element).find('select[name="service_id"]').val().trim(),
                    'zone_id': $(element).find('select[name="zone_id"]').val().trim(),
                    'based_on': $(element).find('select[name="based_on"]').val().trim(),
                    'apply_over': $(element).find('input[name="apply_over"]').val().trim(),
                    'tax_rate': $(element).find('input[name="tax_rate"]').val().trim(),
                    'calculation_type': $(element).find('select[name="calculation_type"]').val().trim(),
                    'status': $(element).find('select[name="status"]').val().trim(),
                });
            });
            data['params'] = JSON.stringify(data['params']);
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(self.closest('.js-d-content'));
                },
                success: function (response) {
                    $('.js-d-loader').remove();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                    if (response.error) {
                        dpdRoResponse(response.message, 'error');
                    } else {
                        dpdRoResponse(response.message, 'success');
                    }
                },
                error: function (error) {
                    $('.js-d-loader').remove();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                    dpdRoResponse(dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

    });

})(jQuery);