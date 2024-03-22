(function ($) {

    function dpdRoLoader(modal, status = true) {
        var loader = `
            <div class="d-loader js-d-loader">
                <span></span>
            </div>
        `;
        if (status) {
            modal.html(loader);
        } else {
            modal.find('.js-d-loader').remove();
        }
    }

    function dpdRoResponse(modal, message, type) {
        var response = `
            <div class="d-message d-` + type + `">
                ` + message + `
            </div>
        `;
        modal.html(response);
    }

    $(document).ready(function () {

        // Close modal
        $(document).on('click', '.js-dpdro .js-d-modal-close', function (e) {
            e.preventDefault();
            $('body').removeClass('dpdro-overflow-hidden');
            $('.js-d-modal').removeClass('active').find('.js-d-modal-content').html('');
            return false;
        });
        $(document).on('click', function (e) {
            if ($('.js-d-modal').hasClass('active')) {
                if (!$(e.target).closest($('.js-d-modal').find('.js-d-modal-box')).length &&
                    !$(e.target).closest($('.js-d-modal').find('.js-d-modal-close')).length &&
                    !$(e.target).closest($('.select2-container--open').find('.select2-dropdown')).length
                ) {
                    $('body').removeClass('dpdro-overflow-hidden');
                    $('.js-d-modal').removeClass('active').find('.js-d-modal-content').html('');
                }
            }
        });

        // Create shipment
        $(document).on('click', '.js-dpdro .js-d-create-shipment', function (e) {
            e.preventDefault();
            var self = $(this);
            var modal = $('.js-dpdro').find('.js-d-modal-content');
            var data = {
                action: 'createShipment',
                nonce: self.attr('data-nonce'),
                params: {
                    orderId: self.attr('data-order-id')
                }
            };
            $('.js-dpdro .js-d-modal').removeClass('active');
            $('body').addClass('dpdro-overflow-hidden');
            $('.js-dpdro').find('.js-d-modal').addClass('active');
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(modal);
                },
                complete: function () {
                    dpdRoLoader(modal, false);
                },
                success: function (response) {
                    if (response.error) {
                        dpdRoResponse(modal, response.message, 'error');
                    } else {
                        modal.html(response.html);
                    }
                },
                error: function (error) {
                    dpdRoResponse(modal, dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Save shipment
        $(document).on('click', '.js-dpdro .js-d-save-shipment', function (e) {
            e.preventDefault();
            var self = $(this);
            var modal = self.closest('.js-d-modal-content');
            var data = {
                action: 'saveShipment',
                nonce: self.attr('data-nonce'),
                params: {
                    orderId: self.attr('data-order-id'),
                    products: [],
                    parcels: [],
                    swap: modal.find('input[name="swap"]').is(':checked') ? true : false,
                    rod: modal.find('input[name="rod"]').is(':checked') ? true : false,
                    rop: modal.find('input[name="rop"]').length && modal.find('input[name="rop"]').is(':checked') ? true : false,
                    rsp: modal.find('input[name="rsp"]').is(':checked') ? true : false,
                    voucher: modal.find('input[name="voucher"]').is(':checked') ? true : false,
                    voucherSender: modal.find('select[name="voucher_sender"]').val(),
                    private: modal.find('input[name="private"]').is(':checked') ? true : false,
                    privatePerson: modal.find('input[name="private_person"]').val(),
                    packages: modal.find('input[name="packages"]').val(),
                    contents: modal.find('input[name="contents"]').val(),
                    notes: modal.find('textarea[name="notes"]').val(),
                    ref2: modal.find('textarea[name="ref_2"]').val(),
                }
            };

            // Products
            var products = modal.find('.js-d-modal-table-product');
            $(products).each(function (index, product) {
                data['params']['products'].push({
                    'id': $(product).find('[name="id"]').val(),
                    'name': $(product).find('[name="name"]').val(),
                    'weight': $(product).find('[name="weight"]').val(),
                    'width': $(product).find('[name="width"]').val(),
                    'depth': $(product).find('[name="depth"]').val(),
                    'height': $(product).find('[name="height"]').val(),
                    'parcel': $(product).find('[name="parcel"]').val()
                });
            });

            // Parcels
            var parcels = modal.find('.js-d-modal-table-parcel');
            $(parcels).each(function (index, parcel) {
                data['params']['parcels'].push({
                    'id': $(parcel).find('[name="id"]').val(),
                    'description': $(parcel).find('[name="description"]').val()
                });
            });

            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(modal);
                },
                complete: function () {
                    dpdRoLoader(modal, false);
                },
                success: function (response) {
                    modal.html(response.html);
                    if (response.success) {
                        setTimeout(function () {
                            window.location.href = window.location.href;
                        }, 500);
                    }
                },
                error: function (error) {
                    dpdRoResponse(modal, dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });
        
        // You can choose SWAP or Return reusable packaging
        $(document).on('change', '.js-d-modal-content #shipment-swap', function (e) {
            if ($(this).prop('checked')) {
                $('.js-d-modal-content #shipment-rsp').prop('checked', false).attr('checked', '');
            }
        });
        $(document).on('change', '.js-d-modal-content #shipment-rsp', function (e) {
            if ($(this).prop('checked')) {
                $('.js-d-modal-content #shipment-swap').prop('checked', false).attr('checked', '');
            }
        });

        // Delete shipment
        $(document).on('click', '.js-dpdro .js-d-delete-shipment', function (e) {
            e.preventDefault();
            var self = $(this);
            var data = {
                action: 'deleteShipment',
                nonce: self.attr('data-nonce'),
                params: {
                    orderId: self.attr('data-order-id'),
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    self.find('i').removeClass('dashicons-trash').addClass('dashicons-image-rotate d-spinning');
                },
                complete: function () { },
                success: function (response) {
                    window.location.href = window.location.href;
                },
                error: function (error) {
                    console.error(error);
                }
            });
            return false;
        });

        // Skip validation address
        $(document).on('click', '.js-dpdro .js-d-skip-validation-address', function (e) {
            e.preventDefault();
            var self = $(this);
            var modal = $('.js-dpdro').find('.js-d-modal-content');
            var data = {
                action: 'skipValidationAddress',
                nonce: self.attr('data-nonce'),
                params: {
                    orderId: self.attr('data-order-id')
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(modal);
                },
                complete: function () {
                    dpdRoLoader(modal, false);
                },
                success: function (response) {
                    if (response.error) {
                        dpdRoResponse(modal, response.message, 'error');
                    } else {
                        modal.html(response.html);
                    }
                },
                error: function (error) {
                    dpdRoResponse(modal, dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Validation address
        $(document).on('click', '.js-dpdro .js-d-validation-address', function (e) {
            e.preventDefault();
            var self = $(this);
            var modal = $('.js-dpdro').find('.js-d-modal-content');
            var data = {
                action: 'validationAddress',
                nonce: self.attr('data-nonce'),
                params: {
                    orderId: self.attr('data-order-id')
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(modal);
                },
                complete: function () {
                    dpdRoLoader(modal, false);
                },
                success: function (response) {
                    if (response.error) {
                        dpdRoResponse(modal, response.message, 'error');
                    } else {
                        modal.html(response.html);
                    }
                },
                error: function (error) {
                    dpdRoResponse(modal, dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Validate address
        $(document).on('click', '.js-dpdro .js-d-validate-address', function (e) {
            e.preventDefault();
            var self = $(this);
            var modal = $('.js-dpdro').find('.js-d-modal-content');
            var data = {
                action: 'validateAddress',
                nonce: self.attr('data-nonce'),
                params: {
                    orderId: self.attr('data-order-id'),
                    country: modal.find('input[name="country"]').val(),
                    city: modal.find('input[name="address_city_name"]').val(),
                    cityId: modal.find('input[name="address_city_id"]').val(),
                    streetId: modal.find('input[name="address_street_id"]').val(),
                    streetType: modal.find('input[name="address_street_type"]').val(),
                    streetName: modal.find('input[name="address_street_name"]').val(),
                    number: modal.find('input[name="address_number"]').val(),
                    block: modal.find('input[name="address_block"]').val(),
                    apartment: modal.find('input[name="address_apartment"]').val(),
                    postcode: modal.find('input[name="address_postcode"]').val(),
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(modal);
                },
                complete: function () {
                    dpdRoLoader(modal, false);
                },
                success: function (response) {
                    modal.html(response.html);
                },
                error: function (error) {
                    dpdRoResponse(modal, dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Request courier
        $(document).on('click', '.js-dpdro .js-d-request-courier', function (e) {
            e.preventDefault();
            var self = $(this);
            var shipments = [];
            shipments.push(self.attr('data-shipment-id'));
            var data = {
                action: 'requestCourier',
                nonce: self.attr('data-nonce'),
                params: {
                    shipments: shipments,
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    self.find('i').removeClass('dashicons-car').addClass('dashicons-image-rotate d-spinning');
                },
                complete: function () { },
                success: function (response) {
                    window.location.href = window.location.href;
                },
                error: function (error) {
                    console.error(error);
                }
            });
            return false;
        });

        // Go back
        $(document).on('click', '.js-dpdro .js-d-go-back', function (e) {
            e.preventDefault();
            var self = $(this);
            var modal = $('.js-dpdro').find('.js-d-modal-content');
            var data = {
                action: 'createShipment',
                nonce: self.attr('data-nonce'),
                params: {
                    orderId: self.attr('data-order-id')
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(modal);
                },
                complete: function () {
                    dpdRoLoader(modal, false);
                },
                success: function (response) {
                    if (response.error) {
                        dpdRoResponse(modal, response.message, 'error');
                    } else {
                        modal.html(response.html);
                    }
                },
                error: function (error) {
                    dpdRoResponse(modal, dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Add address
        $(document).on('click', '.js-dpdro .js-d-add-address', function (e) {
            e.preventDefault();
            var self = $(this);
            var modal = $('.js-dpdro').find('.js-d-modal-content');
            var data = {
                action: 'addAddress',
                nonce: self.attr('data-nonce'),
                params: {
                    orderId: self.attr('data-order-id')
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(modal);
                },
                complete: function () {
                    dpdRoLoader(modal, false);
                },
                success: function (response) {
                    if (response.error) {
                        dpdRoResponse(modal, response.message, 'error');
                    } else {
                        modal.html(response.html);
                    }
                },
                error: function (error) {
                    dpdRoResponse(modal, dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Save address
        $(document).on('click', '.js-dpdro .js-d-save-address', function (e) {
            e.preventDefault();
            var self = $(this);
            var modal = self.closest('.js-dpdro').find('.js-d-modal-content');
            var data = {
                action: 'saveAddress',
                nonce: self.attr('data-nonce'),
                params: {
                    orderId: self.attr('data-order-id'),
                    country: modal.find('input[name="country"]').val().trim(),
                    address: modal.find('input[name="address"]').val().trim(),
                    cityId: modal.find('input[name="address_city_id"]').val().trim(),
                    cityName: modal.find('input[name="address_city_name"]').val().trim(),
                    streetId: modal.find('input[name="address_street_id"]').val().trim(),
                    streetType: modal.find('input[name="address_street_type"]').val().trim(),
                    streetName: modal.find('input[name="address_street_name"]').val().trim(),
                    number: modal.find('input[name="address_number"]').val().trim(),
                    block: modal.find('input[name="address_block"]').val().trim(),
                    apartment: modal.find('input[name="address_apartment"]').val().trim(),
                    postcode: modal.find('input[name="address_postcode"]').val().trim(),
                    method: modal.find('input[name="method"]:checked').val().trim(),
                    officeId: modal.find('input[name="office_id"]').val().trim(),
                    officeName: modal.find('input[name="office_name"]').val().trim(),
                    officeType: modal.find('input[name="office_type"]').val().trim(),
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(modal);
                },
                complete: function () {
                    dpdRoLoader(modal, false);
                },
                success: function (response) {
                    if (response.error) {
                        dpdRoResponse(modal, response.message, 'error');
                    } else {
                        modal.html(response.html);
                    }
                },
                error: function (error) {
                    dpdRoResponse(modal, dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Change office
        $(document).on('click', '.js-dpdro .js-d-change-office', function (e) {
            e.preventDefault();
            var self = $(this);
            var modal = $('.js-dpdro').find('.js-d-modal-content');
            var data = {
                action: 'changeOffice',
                nonce: self.attr('data-nonce'),
                params: {
                    orderId: self.attr('data-order-id')
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(modal);
                },
                complete: function () {
                    dpdRoLoader(modal, false);
                },
                success: function (response) {
                    if (response.error) {
                        dpdRoResponse(modal, response.message, 'error');
                    } else {
                        modal.html(response.html);
                    }
                },
                error: function (error) {
                    dpdRoResponse(modal, dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Save office
        $(document).on('click', '.js-dpdro .js-d-save-office', function (e) {
            e.preventDefault();
            var self = $(this);
            var modal = self.closest('.js-dpdro').find('.js-d-modal-content');
            var data = {
                action: 'saveOffice',
                nonce: self.attr('data-nonce'),
                params: {
                    orderId: self.attr('data-order-id'),
                    officeId: modal.find('input[name="office_id"]').val().trim(),
                    officeName: modal.find('input[name="office_name"]').val().trim(),
                    officeType: modal.find('input[name="office_type"]').val().trim(),
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(modal);
                },
                complete: function () {
                    dpdRoLoader(modal, false);
                },
                success: function (response) {
                    if (response.error) {
                        dpdRoResponse(modal, response.message, 'error');
                    } else {
                        modal.html(response.html);
                    }
                },
                error: function (error) {
                    dpdRoResponse(modal, dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Tab address
        $(document).on('change', '.js-dpdro .js-d-modal-nav:checked', function (e) {
            var content = $('.js-dpdro .js-d-modal-nav-content[data-content="' + $(this).val() + '"]');
            $('.js-dpdro .js-d-modal-nav-content').removeClass('active');
            $(content).addClass('active');
        });

        // Add shipping method
        $(document).on('click', '.js-dpdro .js-d-add-shipping-method', function (e) {
            e.preventDefault();
            var self = $(this);
            var modal = $('.js-dpdro').find('.js-d-modal-content');
            var data = {
                action: 'addShippingMethod',
                nonce: self.attr('data-nonce'),
                params: {
                    orderId: self.attr('data-order-id')
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(modal);
                },
                complete: function () {
                    dpdRoLoader(modal, false);
                },
                success: function (response) {
                    if (response.error) {
                        dpdRoResponse(modal, response.message, 'error');
                    } else {
                        modal.html(response.html);
                    }
                },
                error: function (error) {
                    dpdRoResponse(modal, dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Save shipping method
        $(document).on('click', '.js-dpdro .js-d-save-shipping-method', function (e) {
            e.preventDefault();
            var self = $(this);
            var modal = self.closest('.js-dpdro').find('.js-d-modal-content');
            var service = modal.find('input[name="service_id"]:checked');
            var data = {
                action: 'saveShippingMethod',
                nonce: self.attr('data-nonce'),
                params: {
                    orderId: self.attr('data-order-id'),
                    serviceId: modal.find('input[name="service_id"]:checked').val().trim(),
                    serviceCode: service.closest('li').find('input[name="service_code"]').val().trim(),
                    serviceName: service.closest('li').find('input[name="service_name"]').val().trim(),
                    serviceTax: service.closest('li').find('input[name="service_tax"]').val().trim(),
                    serviceTaxRate: service.closest('li').find('input[name="service_tax_rate"]').val().trim(),
                }
            };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: dpdRo.ajaxurl,
                data: data,
                beforeSend: function () {
                    dpdRoLoader(modal);
                },
                complete: function () {
                    dpdRoLoader(modal, false);
                },
                success: function (response) {
                    if (response.error) {
                        dpdRoResponse(modal, response.message, 'error');
                    } else {
                        modal.html(response.html);
                    }
                },
                error: function (error) {
                    dpdRoResponse(modal, dpdRoGeneral.errorMessage, 'error');
                }
            });
            return false;
        });

        // Select office address
        window.addEventListener('message', function (e) {

            // Office data
            let office = e.data;
            $('.js-dpdro input[name="address_city_name"]').val(office.address.siteName);
            $('.js-dpdro input[name="address_postcode"]').val(office.address.postCode);
            $('.js-dpdro input[name="address"]').val(office.address.fullAddressString);
            $('.js-dpdro input[name="office_id"]').val(office.id);
            $('.js-dpdro input[name="office_name"]').val(office.name);
            $('.js-dpdro input[name="office_type"]').val(office.type);
            $('.js-d-save-address').attr('disabled', '').prop('disabled', false);
            $('.js-d-save-office').attr('disabled', '').prop('disabled', false);

        }, false);

    });

})(jQuery);