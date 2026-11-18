if (typeof LOGS_ENABLED === 'undefined') {
    var LOGS_ENABLED = false;
}
if (typeof LOGS_TYPE_ENABLED === 'undefined') {
    var LOGS_TYPE_ENABLED = 'all';
}

function dpdroLog(enable, type, ...args) {
    const isEnabled = enable === 1 || enable === '1' || enable === true;
    if (!isEnabled) {
        return;
    }

    const receivedTypeLabel = String(type == null ? 'all' : type).trim() || 'all';
    const enabledTypes = String(LOGS_TYPE_ENABLED || 'all')
        .toLowerCase()
        .split(',')
        .map(function (item) { return item.trim(); })
        .filter(function (item) { return item !== ''; });
    const receivedTypes = receivedTypeLabel
        .toLowerCase()
        .split(',')
        .map(function (item) { return item.trim(); })
        .filter(function (item) { return item !== ''; });

    const hasWildcard = enabledTypes.indexOf('all') !== -1 || receivedTypes.indexOf('all') !== -1;
    const hasTypeMatch = receivedTypes.some(function (item) { return enabledTypes.indexOf(item) !== -1; });

    if (!hasWildcard && !hasTypeMatch) {
        return;
    }

    window.console.log("[" + receivedTypeLabel + "]", ...args);
}

/**
 * Resolve the postcode to write into the checkout field after a city lookup.
 *
 * The state/county change handler calls reloadWidget() with the sentinel '-1',
 * meaning "a new county was selected, ignore any postcode already in the form".
 * That sentinel is only meaningful for the lookup request; it is not a postcode
 * and must never be written back into the field.
 *
 * @param responsePostcode postcode returned by the searchCity lookup
 * @param requestPostcode  postcode sent with the lookup (may be the '-1' sentinel)
 * @param countryId        DPD numeric country id (642 = Romania)
 * @returns {string}       value to write into the postcode field
 */
function dpdroResolveFieldPostcode(responsePostcode, requestPostcode, countryId) {
    const fromResponse = String(responsePostcode ?? '').trim();
    if (fromResponse !== '') {
        return fromResponse;
    }

    // Romania always follows the lookup result, even when it is empty.
    if (Number(countryId) === 642) {
        return '';
    }

    // Other countries keep whatever the customer already had, unless it is the
    // sentinel - in which case the field is cleared for the new county.
    const fromRequest = String(requestPostcode ?? '').trim();
    return fromRequest === '-1' ? '' : fromRequest;
}

(function ($) {

    const checkoutSteps = ['billing', 'shipping'];
    var cityLoaded = false;
    window.programmaticPostcodeChange = false;
    const reloadRequestsByStep = {};

    window.isBlocksCheckout =
        document.querySelector('.wc-block-checkout') ||
        document.querySelector('select.wc-blocks-components-select__select') ||
        document.querySelector('#shipping-country, #billing-country');

    $(document).ready(function () {

        // Checkout steps
        $.each(checkoutSteps, function (index, step) {

            const isChecked = jQuery('#ship-to-different-address-checkbox').is(':checked');
            const isCheckedBlocks = $('.wc-block-checkout__use-address-for-billing input[type="checkbox"]').is(':checked');
            if (step == "billing" && (isChecked == true || isCheckedBlocks == true)) {
                return true;
            }

            if (step === "shipping" && (isChecked == false && isCheckedBlocks == false)) {
                return true;
            }

            dpdroLog(LOGS_ENABLED, "on-change", "step", step);
            //classic for country
            $(document).on('change', '[name="' + step + '_country"]', function (e) {
                $('[name="' + step + '_pickup"]').val(null).trigger('change');
                $('.js-dpdro-offices-name').val(dpdRoGeneral.textNoOfficeSelected);
                $('body').trigger('update_checkout');
                var self = $(this);
                reloadWidget(step, self);

            });

            //blocks for country
            $(document).on('change', '[id="' + step + '-country"]', function (e) {
                $('[id="dpdro/pickup_id"]').val(null).trigger('change');
                $('[id="' + step + '-pickup"]').val(null).trigger('change');
                $('[id="' + step + '-dpdro-pickup_id"]').val('');
                $('[id="' + step + '-dpdro-pickup_name"]').val('');
                $('[id="' + step + '-dpdro-pickup_type"]').val('');
                $('.js-dpdro-offices-name').val(dpdRoGeneral.textNoOfficeSelected);
                $('.js-dpdro-offices-type').val('');
                if (!window.dpdExpectedPostcodes) window.dpdExpectedPostcodes = {};
                window.dpdExpectedPostcodes[step] = '';
                if (step === 'shipping' && isBlocksUsingShippingAsBilling()) {
                    window.dpdExpectedPostcodes.billing = '';
                }
                var self = $(this);
                var isProgrammaticChange = !e.originalEvent;

                if (!isProgrammaticChange && typeof window.setBlocksCountryEverywhere === 'function') {
                    dpdroLog(LOGS_ENABLED, "on-change", "isProgrammaticChange", isProgrammaticChange);
                    Promise.resolve(window.setBlocksCountryEverywhere(step, self.val())).finally(function () {
                        reloadWidget(step, self, "");
                    });
                    return;
                }

                reloadWidget(step, self, "");

            });

            //classic for state
            $(document).on('change', '[name="' + step + '_state"]', function () {
                dpdroLog(LOGS_ENABLED, "on-change", "Reseting city from "  + $('[name="' + step + '_city"]').val());
                if (cityLoaded) {
                    $('[name="' + step + '_city"]').val(null);
                    $('[name="' + step + '_postcode"]').val(null);
                    $('[name="' + step + '_pickup"]').val(null).trigger('change');
                    $('.js-dpdro-offices-name').val(dpdRoGeneral.textNoOfficeSelected);
                    $('body').trigger('update_checkout');
                    var self = $(this);
                   
                    //reloadWidget(step, self);
                }
            });

            //blocks for state
            $(document).on('change', '[id="' + step + '-state"]', function () {
                $('[id="' + step + '-city"]').val(null);
                //$('[id="' + step + '-postcode"]').val(null);
                $('[id="' + step + '-pickup"]').val(null).trigger('change');
                $('.js-dpdro-offices-name').val(dpdRoGeneral.textNoOfficeSelected);
                var self = $(this);
                window.dpdExpectedPostcodes[step] = '';
                reloadWidget(step, self, '-1');
            });

            //classic for city
            $(document).on('change', '[name="' + step + '_city"]', function () {
                if (window.isBlocksCheckout && $(this).is('select.city_select')) {
                    dpdroLog(LOGS_ENABLED, "on-change", '[DPD] Ignoring classic city handler for blocks city_select, step:' + step);
                    return;
                }
                var self = $(this);
                $('[name="' + step + '_pickup"]').val(null).trigger('change');
                $('.js-dpdro-offices-name').val(dpdRoGeneral.textNoOfficeSelected);
                $('.js-dpdro-offices-type').val('');
                $('body').trigger('update_checkout');
                reloadWidget(step, self);
            });

            //blocks for city
            $(document).on('change', '[id="' + step + '-city"]', function () {
                // Always clear pickup fields on city change
                $('[id="' + step + '-pickup"]').val(null).trigger('change');
                $('[id="' + step + '-dpdro-pickup_id"]').val('');
                $('[id="' + step + '-dpdro-pickup_name"]').val('');
                $('[id="' + step + '-dpdro-pickup_type"]').val('');
                $('.js-dpdro-offices-name').val(dpdRoGeneral.textNoOfficeSelected);
                $('.js-dpdro-offices-type').val('');

                // Clear pickup session on server (don't trigger extensionCartUpdate —
                // reloadWidget already triggers updateCustomerData which recalculates)
                if (window.isBlocksCheckout) {
                    window.dpdroPickupCleared = true;
                    jQuery.ajax({
                        url: dpdRo.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'dpdro_update_session',
                            nonce: dpdRoGeneral.noneSearchCity,
                            pickup_id: '',
                            pickup_name: '',
                            pickup_type: '',
                            mirror_to_billing: isBlocksUsingShippingAsBilling() ? 1 : 0
                        },
                        success: function(response) {
                            dpdroLog(LOGS_ENABLED, "update-session", '[DPD] Pickup cleared on server');
                        }
                    });
                }

                dpdroLog(LOGS_ENABLED, "update-session", "City changed, not reloading widget with step", step);
                //reloadWidget(step, self);
            });

            //classic for postcode
            $(document).on('change', '[name="' + step + '_postcode"]', function () {
                var self = $(this);
                $('[name="' + step + '_pickup"]').val(null).trigger('change');
                $('.js-dpdro-offices-name').val(dpdRoGeneral.textNoOfficeSelected);
                $('.js-dpdro-offices-type').val('');
                $('body').trigger('update_checkout');
                reloadWidget(step, self);
            });

            //blocks for postcode
            $(document).on('change', '[id="' + step + '-postcode"]', function () {
                const suppressStep = window.dpdProgrammaticPostcodeStep;
                const suppressUntil = window.dpdProgrammaticPostcodeUntil || 0;
                const isSuppressed = suppressStep === step && Date.now() < suppressUntil;
                const currentValue = ($(this).val() ?? '').toString().trim();
                const expectedValue = (((window.dpdExpectedPostcodes || {})[step]) ?? '').toString().trim();

                if (window.programmaticPostcodeChange || isSuppressed) {
                    dpdroLog(LOGS_ENABLED, "on-change", '[DPD] ignoring programmatic postcode change');
                    if (isSuppressed) {
                        window.dpdProgrammaticPostcodeStep = null;
                        window.dpdProgrammaticPostcodeUntil = 0;
                    }
                    return;
                }

                // Guard against React/store re-emitting the exact postcode we just set programmatically.
                if (expectedValue && currentValue === expectedValue) {
                    dpdroLog(LOGS_ENABLED, "on-change", '[DPD] ignoring postcode change equal to expected programmatic value:', currentValue);
                    return;
                }

                dpdroLog(LOGS_ENABLED, "on-change", '[DPD] no longer ignoring postcode change equal to expected programmatic value:', currentValue);
                var self = $(this);
                $('[id="' + step + '-pickup"]').val(null).trigger('change');
                $('.js-dpdro-offices-name').val(dpdRoGeneral.textNoOfficeSelected);
                $('.js-dpdro-offices-type').val('');
                reloadWidget(step, self);
            });


            // The street address (address_1) is the customer's own address and is
            // independent of the DPD pickup point, so editing it must not drop the
            // selected locker. (Before 3.3.13 the locker address was written into
            // address_1, and editing that field meant giving up the locker.)

            $('[name="' + step + '_country"]').trigger('change');
            $('[id="' + step + '-country"]').trigger('change');

        });

        //blocks: sync pickup fields to server when pickup_id changes (outside loop to avoid duplicate bindings)
        window.lastSyncedPickupId = 'aaaaa';
        $(document).on('change', '[id="shipping-dpdro-pickup_id"]', function () {
            if (!window.isBlocksCheckout) return;

            var step = this.id.replace('-dpdro-pickup_id', '');
            var pickupId = $(this).val() || '';
            var pickupName = jQuery('[id="' + step + '-dpdro-pickup_name"]').val() || '';
            var pickupType = jQuery('[id="' + step + '-dpdro-pickup_type"]').val() || '';
            var address =  jQuery('[id="' + step + '-address_1"]').val() || '';
            dpdroLog(LOGS_ENABLED, 'pickup', '[DPD] pickup_id changed?', { pickupId: pickupId, pickupName: pickupName, pickupType: pickupType, lastSyncedPickupId: lastSyncedPickupId });
            if (pickupId === lastSyncedPickupId) return;
            lastSyncedPickupId = pickupId;

            dpdroLog(LOGS_ENABLED, "pickup", '[DPD] pickup_id changed:', { pickupId: pickupId, pickupName: pickupName, pickupType: pickupType, address: address });
            dpdroLog(LOGS_ENABLED, "update-session", '[DPD] pickup_id changed:', { pickupId: pickupId, pickupName: pickupName, pickupType: pickupType, address: address });

            jQuery.ajax({
                url: dpdRo.ajaxurl,
                type: 'POST',
                data: {
                    action: 'dpdro_update_session',
                    nonce: dpdRoGeneral.noneSearchCity,
                    pickup_id: pickupId,
                    pickup_name: pickupName,
                    pickup_type: pickupType,
                    address: address,
                    mirror_to_billing: isBlocksUsingShippingAsBilling() ? 1 : 0
                },
                success: function(response) {
                    dpdroLog(LOGS_ENABLED, "update-session", '[DPD] Pickup session updated, triggering recalculation:', response);
                    var { extensionCartUpdate } = wc.blocksCheckout;
                    extensionCartUpdate({
                        namespace: 'dpdro',
                        data: { recalculate: true }
                    });
                }
            });
        });

        $(document).on('change', 'input[name^="payment_method"]', function () {
            if (!window.isBlocksCheckout) {
            $('body').trigger('update_checkout');
            }
        });



        window.addEventListener('message', function (e) {

            if ($('#frameOfficeLocator').length) {
                if (e.origin == 'https://services.dpd.ro') {

                    // Office data
                    let office = e.data;
                    dpdroLog(LOGS_ENABLED, "event-listener", "inside the event listener");

                    // Store the selected pickup point in the DPD pickup fields only.
                    // The address fields keep the customer's own address: the AWB and the
                    // shipping price use the pickup point id, and writing the locker address
                    // here made it the billing address too (WooCommerce Blocks copies shipping
                    // into billing when "Use same address for billing" is checked).
                    $.each(checkoutSteps, function (index, step) {

                        // Name and type first: changing the pickup id fires a handler that
                        // reads them and sends them to the server, so they must already hold
                        // the new pickup point (not empty, not the previous selection's).
                        $('[name="' + step + '_pickup_name"]').val(office.name);
                        $('[id="' + step + '-pickup_name"]').val(office.name).trigger('change');
                        $('[id="' + step + '-dpdro-pickup_name"]').val(office.name).trigger('change');

                        $('[name="' + step + '_pickup_type"]').val(office.type);
                        $('[id="' + step + '-pickup_type"]').val(office.type).trigger('change');
                        $('[id="' + step + '-dpdro-pickup_type"]').val(office.type).trigger('change');

                        $('[name="' + step + '_pickup"]').val(office.id);
                        $('[id="' + step + '-pickup"]').val(office.id).trigger('change');
                        $('[id="' + step + '-dpdro-pickup_id"]').val(office.id).trigger('change');

                        $('.js-dpdro-offices-name').val(office.name);
                        if (!window.isBlocksCheckout) {
                            $('body').trigger('update_checkout');
                        }
                    });

                    // For blocks checkout: first set session data, then trigger recalculation
                    if (window.isBlocksCheckout) {

                        dpdroLog(LOGS_ENABLED, "event-listener", '[DPD] Sending DPD office data to server:', {
                            id: office.id,
                            name: office.name,
                            type: office.type
                        });

                        dpdroLog(LOGS_ENABLED, "update-session", '[DPD] Sending DPD office data to server:', {
                            id: office.id,
                            name: office.name,
                            type: office.type
                        });

                        jQuery.ajax({
                            url: dpdRo.ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'dpdro_update_session',
                                nonce: dpdRoGeneral.noneSearchCity,
                                pickup_id: office.id,
                                pickup_name: office.name,
                                pickup_type: office.type
                            },
                            success: function(response) {
                                dpdroLog(LOGS_ENABLED, "update-session", '[DPD] Session updated via AJAX:', response);

                                // Recalculate shipping for the new pickup point. The address is
                                // left untouched, so there is nothing to persist first.
                                const { extensionCartUpdate } = wc.blocksCheckout;
                                extensionCartUpdate({
                                    namespace: 'dpdro',
                                    data: { recalculate: true }
                                });
                            },
                            error: function(error) {
                                console.error('[DPD] Failed to update session:', error);
                            }
                        });


                    }
                }
            }

        }, false);

    });


    function setBlocksPostalCode(address1, postalCode, type) {

        if (!window.wp?.data?.dispatch) return false;

        const cartDispatch = window.wp.data.dispatch('wc/store/cart');

        if (typeof cartDispatch.setShippingAddress !== 'function') return false;
        dpdroLog(LOGS_ENABLED, "setBlocksPostalCode", "adding postcode", postalCode, type);

        // Read current DPD field values to include in update
        const pickupId = jQuery('#' + type + '-dpdro-pickup_id').val() || '';
        const pickupName = jQuery('#' + type + '-dpdro-pickup_name').val() || '';
        const pickupType = jQuery('#' + type + '-dpdro-pickup_type').val() || '';

        dpdroLog(LOGS_ENABLED, "setBlocksPostalCode", 'pickupId', pickupId);

        const updatedAddress = {
            address_1: address1,
            postcode: postalCode,
            'dpdro/pickup_id': pickupId,
            'dpdro/pickup_name': pickupName,
            'dpdro/pickup_type': pickupType
        };

        dpdroLog(LOGS_ENABLED, "setBlocksPostalCode", '[DPD] setBlocksPostalCode updating with DPD fields:', updatedAddress);

        if (type=="shipping") {
            cartDispatch.setShippingAddress(updatedAddress);
            // Persist to server to trigger checkout update
            cartDispatch.updateCustomerData({ shippingAddress: updatedAddress }, false);
        } else {
            cartDispatch.setBillingAddress(updatedAddress);
            // Persist to server to trigger checkout update
            cartDispatch.updateCustomerData({ billingAddress: updatedAddress }, false);
        }

        return true;
    }

    window.reloadWidget = function reloadWidget(step, self, postcodeOverride = null)
    {
        dpdroLog(LOGS_ENABLED, 'widget', 'reload widget step', step);
        dpdroLog(LOGS_ENABLED, 'widget', 'self', self);
        dpdroLog(LOGS_ENABLED, 'widget', 'postcodeOverride', postcodeOverride);

        // --- country ---
        const countryFromBlocks = ($(`[id="${step}-country"]`).val() ?? '').toString().trim();
        const countryFromClassic = ($(`[name="${step}_country"]`).val() ?? '').toString().trim();
        let countryId = false;
        if (window.isBlocksCheckout) {
            countryId = getCountryId(countryFromBlocks) || getCountryId(countryFromClassic);
        } else {
            countryId = getCountryId(countryFromClassic) || getCountryId(countryFromBlocks);
        }

        // --- state ---
        const stateFromClassic = ($(`[name="${step}_state"]`).val() ?? '').toString().trim();
        const stateFromBlocksValue = ($(`[id="${step}-state"]`).val() ?? '').toString().trim();
        const stateFromBlocksLabel = ($(`[id="${step}-state"] option:selected`).text() ?? '').toString().trim();
        // In Blocks, the WC data store holds the canonical state abbreviation (e.g. "IF"),
        // while the DOM may lag or expose only the localized label ("Ilfov").
        const stateFromStore = (
            window.wp?.data?.select?.('wc/store/cart')
                ?.getCustomerData?.()?.[step + 'Address']?.state ?? ''
        ).toString().trim();
        let state = '';
        if (window.isBlocksCheckout) {
            state = stateFromStore || stateFromBlocksValue || stateFromBlocksLabel || stateFromClassic;
        } else {
            state = stateFromClassic || stateFromBlocksLabel || stateFromBlocksValue;
        }

        let postcode = (postcodeOverride ?? '').toString().trim();

        // --- postcode ---
        if (!postcode) {
            const postcodeFromBlocks = ($(`[id="${step}-postcode"]`).val() ?? '').toString().trim();
            const postcodeFromClassic = ($(`[name="${step}_postcode"]`).val() ?? '').toString().trim();
            postcode = window.isBlocksCheckout
                ? (postcodeFromBlocks || postcodeFromClassic)
                : (postcodeFromClassic || postcodeFromBlocks);
        }

        if ( countryId === 642 && state === 'B') {
            postcode = '010011';
        }

        if (countryId === 642 && state === 'IF' && !postcode.startsWith("0")) {
            postcode = "0" + postcode;
        }

        if (!reloadRequestsByStep[step]) {
            reloadRequestsByStep[step] = { seq: 0, xhr: null };
        }
        const requestSlot = reloadRequestsByStep[step];
        requestSlot.seq += 1;
        const requestSeq = requestSlot.seq;

        if (requestSlot.xhr && requestSlot.xhr.readyState !== 4) {
            requestSlot.xhr.abort();
        }

        let search = self.val().trim();
        dpdroLog(LOGS_ENABLED, "widget", "state", state);
        dpdroLog(LOGS_ENABLED, "widget", "country", countryId);
        dpdroLog(LOGS_ENABLED, "widget", "postcode", postcode);
        dpdroLog(LOGS_ENABLED, "widget", "search", search);

        requestSlot.xhr = $.ajax({
            url: dpdRo.ajaxurl,
            data: {
                action: 'searchCity',
                nonce: dpdRoGeneral.noneSearchCity,
                country: countryId,
                state: state,
                postcode: postcode,
                search: search,
            },
            dataType: 'json',
            type: 'POST',
            success: function (response) {
                if ((reloadRequestsByStep[step]?.seq ?? 0) !== requestSeq) {
                    dpdroLog(LOGS_ENABLED, "widget", '[DPD][DBG] Ignoring stale reloadWidget success', { step, requestSeq });
                    return;
                }

                dpdroLog(LOGS_ENABLED, "widget", 'AJAX response:', response);
                dpdroLog(LOGS_ENABLED, "widget", 'Response length:', response.length);
                if (response.length > 0) {
                    dpdroLog(LOGS_ENABLED, "widget", 'First result:', response[0]);
                    dpdroLog(LOGS_ENABLED, "widget", 'Postcode from response:', response[0].postcode);
                }

                if (response.length > 0) {
                    let newPc = String(response[0].postcode ?? '').trim();

                    // Classic checkout
                    if (!window.isBlocksCheckout) {
                        $('[name="' + step + '_postcode"]').val(newPc);
                        dpdroLog(LOGS_ENABLED, "widget", "Updated classic postcode field to", newPc);
                        $('body').trigger('update_checkout');
                    } else {

                        newPc = dpdroResolveFieldPostcode(newPc, postcode, countryId);

                        // Blocks checkout needs both DOM update and store update.
                        const selector = getBlocksPostcodeSelectors(step);
                        const $postcodeInputs = jQuery(selector);
                        window.programmaticPostcodeChange = true;
                        window.dpdProgrammaticPostcodeStep = step;
                        window.dpdProgrammaticPostcodeUntil = Date.now() + 4000;

                        dpdroLog(LOGS_ENABLED, "widget", '[DPD][DBG] reloadWidget blocks postcode DOM candidates', {
                            step,
                            selector,
                            count: $postcodeInputs.length
                        });

                        $postcodeInputs.each(function () {
                            Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(this, newPc);
                            if (shouldDispatchReactEventsForPostcodeInput(this)) {
                                this.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        });

                        if (typeof window.setBlocksPostcodeEverywhere === 'function') {
                            window.setBlocksPostcodeEverywhere(step, newPc);
                        } else {
                            setTimeout(function() {
                                window.programmaticPostcodeChange = false;
                            }, 1500);
                        }
                        dpdroLog(LOGS_ENABLED, "widget", "[DPD] Updated blocks postcode from widget to:", newPc);
                    }
                }

                if (response.length > 0 && response[0].siteId != '') {

                    if (countryId != 100 || countryId != 642) {
                        response[0].siteId = '';
                    }

                    if (response[0].siteId != '') {
                        var officeMap = 'https://services.dpd.ro/office_locator_widget_v3/office_locator.php?lang=en&showAddressForm=0&showOfficesList=0&siteID=' + response[0].siteId + '&selectOfficeButtonCaption=Select this office' + '&countryId=' + countryId;
                    } else {

                        if(postcode == -1) {
                            postcode = '';
                        }
                        if (!window.isBlocksCheckout) {
                            var siteName = $('[name="' + step + '_city"]').val();
                        } else {
                            var siteName = $('[id="' + step + '-city"]').val();
                        }
                        dpdroLog(LOGS_ENABLED, "widget", 'siteName:', siteName);
                        var officeMap = 'https://services.dpd.ro/office_locator_widget_v3/office_locator.php?lang=en&showAddressForm=0&showOfficesList=0&siteID=&selectOfficeButtonCaption=Select this office' + '&countryId=' + countryId + "&postCode=" + postcode + "&siteName=" + siteName;
                    }
                    dpdroLog(LOGS_ENABLED, "widget", officeMap);
                    dpdroLog(LOGS_ENABLED, "widget", response);
                    if ($('#frameOfficeLocator').length) {
                        $('#frameOfficeLocator').attr('src', officeMap);
                    }
                } else {

                   if(postcode == -1) {
                       postcode = '';
                   }
                    if (!window.isBlocksCheckout) {
                        let siteName = $('[name="' + step + '_city"]').val();
                    } else {
                        let siteName = $('[id="' + step + '-city"]').val();
                    }
                    dpdroLog(LOGS_ENABLED, "widget", 'siteName:', siteName);
                    var officeMap = 'https://services.dpd.ro/office_locator_widget_v3/office_locator.php?lang=en&showAddressForm=0&showOfficesList=0&siteID=&selectOfficeButtonCaption=Select this office' + '&countryId=' + countryId + "&postCode=" + postcode + "&siteName=" + siteName;
                    dpdroLog(LOGS_ENABLED, "widget", officeMap);
                    if ($('#frameOfficeLocator').length) {
                        $('#frameOfficeLocator').attr('src', officeMap);
                    }
                }
            },
            error: function(response, textStatus) {
                dpdroLog(LOGS_ENABLED, "widget", 'AJAX error:', response, textStatus);

                var officeMap = 'https://services.dpd.ro/office_locator_widget_v3/office_locator.php?lang=en&showAddressForm=0&showOfficesList=0&siteID=&selectOfficeButtonCaption=Select this office' + '&countryId=' + countryId;

                if ($('#frameOfficeLocator').length) {
                    $('#frameOfficeLocator').attr('src', officeMap);
                }

                if (textStatus === 'abort') {
                    return;
                }
                if ((reloadRequestsByStep[step]?.seq ?? 0) !== requestSeq) {
                    dpdroLog(LOGS_ENABLED, "widget", '[DPD][DBG] Ignoring stale reloadWidget error', { step, requestSeq });
                    return;
                }
                dpdroLog(LOGS_ENABLED, "widget", 'error:');
                dpdroLog(LOGS_ENABLED, "widget", response);
            },
            complete: function () {
                if ((reloadRequestsByStep[step]?.seq ?? 0) !== requestSeq) {
                    return;
                }
                if (!window.setBlocksPostalCodeStable) {
                    $('body').trigger('update_checkout');
                }
            }
        });
    }


    function getCountryId(code)
    {
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
            case 'SI':
                return 705;
            case 'SK':
                return 703;
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
                return false;
        }
    }

    const CART_STORE = 'wc/store/cart';
    const CHECKOUT_STORE = 'wc/store/checkout';

    let pendingPostcode = null;
    let unsubscribe = null;
    let applying = false;
    const postcodeWatchersByStep = {};

    function getStores() {
        if (!window.wp?.data?.select || !window.wp?.data?.dispatch) {
        return { cartSelect: null, cartDispatch: null, checkoutSelect: null };
        }
        return {
        cartSelect: window.wp.data.select(CART_STORE) || null,
        cartDispatch: window.wp.data.dispatch(CART_STORE) || null,
        checkoutSelect: window.wp.data.select(CHECKOUT_STORE) || null,
        };
    }

    function isBlocksUsingShippingAsBilling() {
        return jQuery('.wc-block-checkout__use-address-for-billing input[type="checkbox"]:checked').length > 0;
    }

    function getBlocksPostcodeSelectors(step) {
        const selectors = [
            '#' + step + '-postcode',
            '#' + step + '_postcode',
            '[name="' + step + '_postcode"]'
        ];
        if (step === 'shipping' && isBlocksUsingShippingAsBilling()) {
            selectors.push('#billing-postcode');
            selectors.push('#billing_postcode');
            selectors.push('[name="billing_postcode"]');
        }
        return selectors.join(', ');
    }

    function shouldDispatchReactEventsForPostcodeInput(inputEl) {
        const id = String(inputEl?.id ?? '').toLowerCase();
        return id.endsWith('-postcode');
    }

    function getBlocksStorePostcode(step) {
        const { cartSelect } = getStores();
        const customer = cartSelect?.getCustomerData?.() || {};
        const address = step === 'shipping' ? customer.shippingAddress : customer.billingAddress;
        return String(address?.postcode ?? '').trim();
    }

    function getCheckoutStorePostcode(step) {
        if (!window.wp?.data?.select) return '';
        const checkoutSelect = window.wp.data.select('wc/store/checkout');
        if (!checkoutSelect) return '';

        const readAddress = function (names) {
            for (const n of names) {
                if (typeof checkoutSelect[n] === 'function') {
                    try {
                        const value = checkoutSelect[n]();
                        if (value && typeof value === 'object') {
                            return value;
                        }
                    } catch (e) {}
                }
            }
            return null;
        };

        const address = step === 'shipping'
            ? readAddress(['getShippingAddress', 'getShippingData', '__experimentalGetShippingAddress', '__experimentalGetShippingData'])
            : readAddress(['getBillingAddress', 'getBillingData', '__experimentalGetBillingAddress', '__experimentalGetBillingData']);

        return String(address?.postcode ?? '').trim();
    }

    function getBlocksStoreAddress(step) {
        const { cartSelect } = getStores();
        const customer = cartSelect?.getCustomerData?.() || {};
        return step === 'shipping'
            ? (customer.shippingAddress || {})
            : (customer.billingAddress || {});
    }

    function getCheckoutStoreAddress(step) {
        if (!window.wp?.data?.select) return {};
        const checkoutSelect = window.wp.data.select(CHECKOUT_STORE);
        if (!checkoutSelect) return {};

        const readAddress = function (names) {
            for (const n of names) {
                if (typeof checkoutSelect[n] === 'function') {
                    try {
                        const value = checkoutSelect[n]();
                        if (value && typeof value === 'object') {
                            return value;
                        }
                    } catch (e) {}
                }
            }
            return {};
        };

        return step === 'shipping'
            ? readAddress(['getShippingAddress', 'getShippingData', '__experimentalGetShippingAddress', '__experimentalGetShippingData'])
            : readAddress(['getBillingAddress', 'getBillingData', '__experimentalGetBillingAddress', '__experimentalGetBillingData']);
    }

    function getAddressFieldValue(step, field) {
        const fieldId = '#' + step + '-' + field;
        const fieldName = '[name="' + step + '_' + field + '"]';
        const domValue = (jQuery(fieldId).val() ?? jQuery(fieldName).val() ?? '').toString().trim();
        if (domValue !== '') {
            return domValue;
        }

        const checkoutAddress = getCheckoutStoreAddress(step);
        const checkoutValue = (checkoutAddress?.[field] ?? '').toString().trim();
        if (checkoutValue !== '') {
            return checkoutValue;
        }

        const cartAddress = getBlocksStoreAddress(step);
        return (cartAddress?.[field] ?? '').toString().trim();
    }

    function buildCurrentBlocksAddress(step, baseAddress, postcodeOverride = null) {
        const nextAddress = { ...(baseAddress || {}) };
        const trackedFields = ['country', 'state', 'city', 'address_1'];

        trackedFields.forEach(function (field) {
            const currentValue = getAddressFieldValue(step, field);
            if (currentValue !== '' || nextAddress[field] == null) {
                nextAddress[field] = currentValue;
            }
        });

        const resolvedPostcode = (postcodeOverride ?? getAddressFieldValue(step, 'postcode')).toString().trim();
        nextAddress.postcode = resolvedPostcode;

        return nextAddress;
    }

      function isIdle() {
            const { cartSelect, checkoutSelect } = getStores();

            // Stores not ready yet → not idle (keep waiting)
            if (!cartSelect || !checkoutSelect) return false;

            const cartMeta = cartSelect?.getCartMeta?.() || {};
            const updatingCustomerData = !!cartMeta.updatingCustomerData;

            const calculating = !!checkoutSelect?.isCalculating?.();
            return !updatingCustomerData && !calculating;
    }

    function syncBlocksPostcodeInput(step, expectedPostcode, timeoutMs = 4000) {
        const target = String(expectedPostcode ?? '').trim();
        if (!target) return;

        const selector = getBlocksPostcodeSelectors(step);
        dpdroLog(LOGS_ENABLED, 'postcode', '[DPD][DBG] syncBlocksPostcodeInput:start', { step, target, selector, timeoutMs });

        const applyDom = function () {
            const $inputs = jQuery(selector);
            if (!$inputs.length) {
                dpdroLog(LOGS_ENABLED, "postcode", '[DPD][DBG] syncBlocksPostcodeInput:applyDom missing input', { step, selector });
                return false;
            }

            const valuesBefore = $inputs.map(function () {
                return String(this.value ?? '').trim();
            }).get();
            const allSyncedBefore = valuesBefore.every(function (v) { return v === target; });
            if (allSyncedBefore) {
                dpdroLog(LOGS_ENABLED, "postcode", '[DPD][DBG] syncBlocksPostcodeInput:applyDom already in sync', {
                    step,
                    target,
                    count: $inputs.length,
                    valuesBefore
                });
                return true;
            }

            dpdroLog(LOGS_ENABLED, "postcode", '[DPD][DBG] syncBlocksPostcodeInput:applyDom writing', {
                step,
                target,
                count: $inputs.length,
                valuesBefore
            });

            window.programmaticPostcodeChange = true;
            window.dpdProgrammaticPostcodeStep = step;
            window.dpdProgrammaticPostcodeUntil = Date.now() + 3000;

            $inputs.each(function () {
                Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(this, target);
                if (shouldDispatchReactEventsForPostcodeInput(this)) {
                    this.dispatchEvent(new Event('input', { bubbles: true }));
                    this.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            const valuesAfter = $inputs.map(function () {
                return String(this.value ?? '').trim();
            }).get();
            const allSyncedAfter = valuesAfter.every(function (v) { return v === target; });
            dpdroLog(LOGS_ENABLED, "postcode", '[DPD][DBG] syncBlocksPostcodeInput:applyDom after write', {
                step,
                target,
                valuesAfter
            });
            return allSyncedAfter;
        };

        if (applyDom()) return;
        if (!window.wp?.data?.subscribe) {
            dpdroLog(LOGS_ENABLED, "postcode", '[DPD][DBG] syncBlocksPostcodeInput:no wp.data.subscribe, using fallback timeout', { step });
            setTimeout(applyDom, 100);
            return;
        }

        const started = Date.now();
        const unsubscribeSync = window.wp.data.subscribe(() => {
            const timedOut = Date.now() - started > timeoutMs;

            const { cartSelect } = getStores();
            const customer = cartSelect?.getCustomerData?.() || {};
            const address = step === 'shipping' ? customer.shippingAddress : customer.billingAddress;
            const storePostcode = String(address?.postcode ?? '').trim();

            const storeReady = isIdle() && storePostcode === target;
            dpdroLog(LOGS_ENABLED, "postcode", '[DPD][DBG] syncBlocksPostcodeInput:tick', {
                step,
                target,
                storePostcode,
                storeReady,
                timedOut
            });
            if (!storeReady && !timedOut) return;

            dpdroLog(LOGS_ENABLED, "postcode", '[DPD][DBG] syncBlocksPostcodeInput:stop condition met', { step, storeReady, timedOut });
            applyDom();
            unsubscribeSync?.();
        });
    }

    window.setBlocksPostalCodeStable = function (postcode) {
        dpdroLog(LOGS_ENABLED, "postcode", '[DPD] setBlocksPostalCodeStable()', postcode);

        pendingPostcode = postcode;

        if (!unsubscribe && window.wp?.data?.subscribe) {
        unsubscribe = window.wp.data.subscribe(() => {
            try {
            if (applying) return;
            if (pendingPostcode == null) return;
            if (!isIdle()) return;

            applying = true;
            const pc = pendingPostcode;

            Promise.resolve(applyPostcode(pc)).finally(() => {
                applying = false;

                if (pendingPostcode === pc) {
                pendingPostcode = null;
                unsubscribe?.();
                unsubscribe = null;
                }
            });
            } catch (e) {
            console.warn('[DPD] postcode subscribe error:', e);
            }
        });
        }

        return true;
    };

    window.setBlocksPostcodeEverywhere = function (step, postcode) {
        if (!window.wp?.data?.dispatch || !window.wp?.data?.select) return false;

        dpdroLog(LOGS_ENABLED, "postcode", '[DPD] setBlocksPostcodeEverywhere step:', step, 'postcode:', postcode);

        const pc = String(postcode ?? '').trim();
        const currentCartPostcode = getBlocksStorePostcode(step);
        const currentCheckoutPostcode = getCheckoutStorePostcode(step);
        if (currentCartPostcode === pc && currentCheckoutPostcode === pc) {
            dpdroLog(LOGS_ENABLED, "postcode", '[DPD][DBG] setBlocksPostcodeEverywhere skip (stores already match)', {
                step,
                pc,
                cart: currentCartPostcode,
                checkout: currentCheckoutPostcode
            });
            syncBlocksPostcodeInput(step, pc);
            return true;
        }

        const cartKey = 'wc/store/cart';
        const checkoutKey = 'wc/store/checkout';

        const cartDispatch = window.wp.data.dispatch(cartKey);
        const cartSelect   = window.wp.data.select(cartKey);
        const checkoutDispatch = window.wp.data.dispatch(checkoutKey);

        const customer = cartSelect?.getCustomerData?.() || {};
        const shipping = customer.shippingAddress || {};
        const billing  = customer.billingAddress || {};
        dpdroLog(LOGS_ENABLED, "postcode", '[DPD][DBG] setBlocksPostcodeEverywhere:before', {
            step,
            pc,
            shippingPostcode: shipping?.postcode ?? '',
            billingPostcode: billing?.postcode ?? ''
        });

        const nextShipping = buildCurrentBlocksAddress('shipping', shipping, pc);
        const nextBilling  = buildCurrentBlocksAddress('billing', billing, pc);

        // Set flag to prevent postcode change handler from firing
        window.programmaticPostcodeChange = true;
        const guardToken = Date.now() + ':' + Math.random().toString(36).slice(2);
        window.dpdProgrammaticPostcodeGuardToken = guardToken;

        // Store the expected postcode to watch for reverts
        if (!window.dpdExpectedPostcodes) window.dpdExpectedPostcodes = {};
        window.dpdExpectedPostcodes[step] = pc;

        // Reset flag after a delay
        setTimeout(function() {
            if (window.dpdProgrammaticPostcodeGuardToken === guardToken) {
                window.programmaticPostcodeChange = false;
                dpdroLog(LOGS_ENABLED, "postcode", '[DPD] Reset programmaticPostcodeChange flag');
            }
        }, 3000); // Increased to 3 seconds to cover server response time

        // ✅ Only update the address for the current step
        if (step === 'shipping') {
            dpdroLog(LOGS_ENABLED, "postcode", '[DPD] Updating ONLY shipping address with postcode:', pc);
            cartDispatch?.setShippingAddress?.(nextShipping);
            const mirrorToBilling = isBlocksUsingShippingAsBilling();
            const nextBillingFromShipping = buildCurrentBlocksAddress('billing', billing, pc);
            dpdroLog(LOGS_ENABLED, "postcode", '[DPD][DBG] shipping mirrorToBilling:', mirrorToBilling);

            // Try checkout store for shipping
            const tryCall = (obj, names, arg) => {
                for (const n of names) if (obj && typeof obj[n] === 'function') {
                    try { obj[n](arg); } catch (e) {}
                }
            };
            tryCall(checkoutDispatch, ['setShippingAddress','setShippingData','__experimentalSetShippingAddress','__experimentalSetShippingData'], nextShipping);
            if (mirrorToBilling) {
                cartDispatch?.setBillingAddress?.(nextBillingFromShipping);
                tryCall(checkoutDispatch, ['setBillingAddress','setBillingData','__experimentalSetBillingAddress','__experimentalSetBillingData'], nextBillingFromShipping);
            }

            // Persist to server and keep DOM in sync after store settles
            const promise = cartDispatch?.updateCustomerData?.(
                mirrorToBilling
                    ? { shippingAddress: nextShipping, billingAddress: nextBillingFromShipping }
                    : { shippingAddress: nextShipping },
                false
            );
            Promise.resolve(promise).finally(() => {
                dpdroLog(LOGS_ENABLED, "postcode", '[DPD][DBG] setBlocksPostcodeEverywhere:shipping updateCustomerData finished', { step, pc });
                syncBlocksPostcodeInput(step, pc);
            });

            // Set up a watcher to re-apply if server reverts the postcode
            setupPostcodeWatcher(step, pc);

            return promise;
        } else {
            dpdroLog(LOGS_ENABLED, "postcode", '[DPD] Updating ONLY billing address with postcode:', pc);
            cartDispatch?.setBillingAddress?.(nextBilling);

            // Try checkout store for billing
            const tryCall = (obj, names, arg) => {
                for (const n of names) if (obj && typeof obj[n] === 'function') {
                    try { obj[n](arg); } catch (e) {}
                }
            };
            tryCall(checkoutDispatch, ['setBillingAddress','setBillingData','__experimentalSetBillingAddress','__experimentalSetBillingData'], nextBilling);

            // Persist to server and keep DOM in sync after store settles
            const promise = cartDispatch?.updateCustomerData?.(
                { billingAddress: nextBilling },
                false
            );
            Promise.resolve(promise).finally(() => {
                dpdroLog(LOGS_ENABLED, "postcode", '[DPD][DBG] setBlocksPostcodeEverywhere:billing updateCustomerData finished', { step, pc });
                syncBlocksPostcodeInput(step, pc);
            });

            // Set up a watcher to re-apply if server reverts the postcode
            setupPostcodeWatcher(step, pc);

            return promise;
        }
    };

    function clearBlocksDependentFields(step) {
        const selectors = [
            '#' + step + '-state',
            '#' + step + '-city',
            getBlocksPostcodeSelectors(step),
            '#' + step + '-pickup',
            '#' + step + '-dpdro-pickup_id',
            '#' + step + '-dpdro-pickup_name',
            '#' + step + '-dpdro-pickup_type'
        ];

        selectors.forEach(function (selector) {
            jQuery(selector).val('');
        });
    }

    window.setBlocksCountryEverywhere = function (step, countryValue) {
        if (!window.wp?.data?.dispatch || !window.wp?.data?.select) return false;

        const country = String(countryValue ?? '').trim();
        if (!country) return false;

        const cartKey = 'wc/store/cart';
        const checkoutKey = 'wc/store/checkout';

        const cartDispatch = window.wp.data.dispatch(cartKey);
        const cartSelect = window.wp.data.select(cartKey);
        const checkoutDispatch = window.wp.data.dispatch(checkoutKey);
        const customer = cartSelect?.getCustomerData?.() || {};
        const shipping = customer.shippingAddress || {};
        const billing = customer.billingAddress || {};

        const tryCall = (obj, names, arg) => {
            for (const n of names) if (obj && typeof obj[n] === 'function') {
                try {
                    obj[n](arg);
                } catch (e) {}
            }
        };

        const buildCountryAddress = function (targetStep, baseAddress) {
            const nextAddress = buildCurrentBlocksAddress(targetStep, baseAddress, '');
            nextAddress.country = country;
            nextAddress.state = '';
            nextAddress.city = '';
            nextAddress.postcode = '';
            return nextAddress;
        };

        if (step === 'shipping') {
            const nextShipping = buildCountryAddress('shipping', shipping);
            const mirrorToBilling = isBlocksUsingShippingAsBilling();
            const nextBillingFromShipping = buildCountryAddress('billing', billing);

            clearBlocksDependentFields('shipping');
            cartDispatch?.setShippingAddress?.(nextShipping);
            tryCall(checkoutDispatch, ['setShippingAddress','setShippingData','__experimentalSetShippingAddress','__experimentalSetShippingData'], nextShipping);

            if (mirrorToBilling) {
                clearBlocksDependentFields('billing');
                cartDispatch?.setBillingAddress?.(nextBillingFromShipping);
                tryCall(checkoutDispatch, ['setBillingAddress','setBillingData','__experimentalSetBillingAddress','__experimentalSetBillingData'], nextBillingFromShipping);
            }

            return cartDispatch?.updateCustomerData?.(
                mirrorToBilling
                    ? { shippingAddress: nextShipping, billingAddress: nextBillingFromShipping }
                    : { shippingAddress: nextShipping },
                false
            );
        }

        const nextBilling = buildCountryAddress('billing', billing);
        clearBlocksDependentFields('billing');
        cartDispatch?.setBillingAddress?.(nextBilling);
        tryCall(checkoutDispatch, ['setBillingAddress','setBillingData','__experimentalSetBillingAddress','__experimentalSetBillingData'], nextBilling);

        return cartDispatch?.updateCustomerData?.(
            { billingAddress: nextBilling },
            false
        );
    };

    window.setBlocksCityEverywhere = function (step, cityValue) {
        if (!window.wp?.data?.dispatch || !window.wp?.data?.select) return false;

        const city = String(cityValue ?? '').trim();
        const cartKey = 'wc/store/cart';
        const checkoutKey = 'wc/store/checkout';

        const cartDispatch = window.wp.data.dispatch(cartKey);
        const cartSelect = window.wp.data.select(cartKey);
        const checkoutDispatch = window.wp.data.dispatch(checkoutKey);
        const customer = cartSelect?.getCustomerData?.() || {};
        const shipping = customer.shippingAddress || {};
        const billing = customer.billingAddress || {};

        const tryCall = (obj, names, arg) => {
            for (const n of names) if (obj && typeof obj[n] === 'function') {
                try {
                    obj[n](arg);
                } catch (e) {}
            }
        };

        if (step === 'shipping') {
            const nextShipping = { ...shipping, city };
            const mirrorToBilling = isBlocksUsingShippingAsBilling();
            const nextBillingFromShipping = { ...billing, city };

            cartDispatch?.setShippingAddress?.(nextShipping);
            tryCall(checkoutDispatch, ['setShippingAddress','setShippingData','__experimentalSetShippingAddress','__experimentalSetShippingData'], nextShipping);

            if (mirrorToBilling) {
                cartDispatch?.setBillingAddress?.(nextBillingFromShipping);
                tryCall(checkoutDispatch, ['setBillingAddress','setBillingData','__experimentalSetBillingAddress','__experimentalSetBillingData'], nextBillingFromShipping);
            }

            return cartDispatch?.updateCustomerData?.(
                mirrorToBilling
                    ? { shippingAddress: nextShipping, billingAddress: nextBillingFromShipping }
                    : { shippingAddress: nextShipping },
                false
            );
        }

        const nextBilling = { ...billing, city };
        cartDispatch?.setBillingAddress?.(nextBilling);
        tryCall(checkoutDispatch, ['setBillingAddress','setBillingData','__experimentalSetBillingAddress','__experimentalSetBillingData'], nextBilling);

        return cartDispatch?.updateCustomerData?.(
            { billingAddress: nextBilling },
            false
        );
    };

    // Watch for postcode reverts and re-apply
    function setupPostcodeWatcher(step, expectedPostcode) {
        if (!window.wp?.data?.subscribe) return;

        // Keep one watcher per step to avoid racing re-applies from older subscriptions.
        if (typeof postcodeWatchersByStep[step] === 'function') {
            postcodeWatchersByStep[step]();
            postcodeWatchersByStep[step] = null;
        }

        let revertCount = 0;
        const maxChecks = 30; // Check for 15 seconds (30 * 500ms)
        let checksPerformed = 0;

        dpdroLog(LOGS_ENABLED, "postcode", '[DPD] Starting postcode watcher for step:', step, 'expected:', expectedPostcode);

        const unsubscribeWatcher = window.wp.data.subscribe(() => {
            checksPerformed++;

            const cartSelect = window.wp.data.select('wc/store/cart');
            const customer = cartSelect?.getCustomerData?.() || {};
            const address = (step === 'shipping') ? customer.shippingAddress : customer.billingAddress;
            const currentPostcode = (address?.postcode ?? '').toString().trim();
            const currentCheckoutPostcode = getCheckoutStorePostcode(step);
            const cartMismatch = !!currentPostcode && currentPostcode !== expectedPostcode;
            const checkoutMismatch = !!currentCheckoutPostcode && currentCheckoutPostcode !== expectedPostcode;

            if (checksPerformed > maxChecks) {
                dpdroLog(LOGS_ENABLED, 'postcode', '[DPD] Postcode watcher completed after', maxChecks, 'checks. Final postcodes:', {
                    cart: currentPostcode,
                    checkout: currentCheckoutPostcode
                });
                unsubscribeWatcher?.();
                return;
            }

            if (checksPerformed % 5 === 0) {
                dpdroLog(LOGS_ENABLED, 'postcode', '[DPD][DBG] watcher tick', {
                    step,
                    expectedPostcode,
                    currentPostcode,
                    currentCheckoutPostcode,
                    checksPerformed
                });
            }

            // Fix React state if it reverted
            if (cartMismatch || checkoutMismatch) {
                revertCount++;
                dpdroLog(LOGS_ENABLED, 'postcode', '[DPD] DETECTED REVERT #' + revertCount + '! Expected:', expectedPostcode, 'but got:', {
                    cart: currentPostcode,
                    checkout: currentCheckoutPostcode
                }, 'Re-applying...');

                const cartDispatch = window.wp.data.dispatch('wc/store/cart');
                const checkoutDispatch = window.wp.data.dispatch('wc/store/checkout');
                const nextAddress = buildCurrentBlocksAddress(step, address, expectedPostcode);
                const mirrorToBilling = step === 'shipping' && isBlocksUsingShippingAsBilling();
                const customerAll = cartSelect?.getCustomerData?.() || {};
                const billingAddress = customerAll.billingAddress || {};
                const nextBillingAddress = buildCurrentBlocksAddress('billing', billingAddress, expectedPostcode);

                if (step === 'shipping') {
                    cartDispatch?.setShippingAddress?.(nextAddress);
                    const tryCall = (obj, names, arg) => {
                        for (const n of names) if (obj && typeof obj[n] === 'function') {
                            try { obj[n](arg); } catch (e) {}
                        }
                    };
                    tryCall(checkoutDispatch, ['setShippingAddress','setShippingData','__experimentalSetShippingAddress','__experimentalSetShippingData'], nextAddress);

                    if (mirrorToBilling) {
                        cartDispatch?.setBillingAddress?.(nextBillingAddress);
                        tryCall(checkoutDispatch, ['setBillingAddress','setBillingData','__experimentalSetBillingAddress','__experimentalSetBillingData'], nextBillingAddress);
                    }
                } else {
                    cartDispatch?.setBillingAddress?.(nextAddress);
                    const tryCall = (obj, names, arg) => {
                        for (const n of names) if (obj && typeof obj[n] === 'function') {
                            try { obj[n](arg); } catch (e) {}
                        }
                    };
                    tryCall(checkoutDispatch, ['setBillingAddress','setBillingData','__experimentalSetBillingAddress','__experimentalSetBillingData'], nextAddress);
                }

                // Persist the correct postcode again
                cartDispatch?.updateCustomerData?.(
                    step === 'shipping'
                        ? (mirrorToBilling
                            ? { shippingAddress: nextAddress, billingAddress: nextBillingAddress }
                            : { shippingAddress: nextAddress })
                        : { billingAddress: nextAddress },
                    false
                );

                // Sync DOM only after store settles with the expected postcode.
                syncBlocksPostcodeInput(step, expectedPostcode);
            }
        });

        // Auto-cleanup
        setTimeout(() => {
            if (unsubscribeWatcher) {
                unsubscribeWatcher();
            }
        }, maxChecks * 500);

        postcodeWatchersByStep[step] = unsubscribeWatcher;
    }

      function applyPostcode(postcode) {
            dpdroLog(LOGS_ENABLED, "postcode", '[DPD] applyPostcode()', postcode);

            const { cartSelect, cartDispatch } = getStores();
            if (!cartSelect || !cartDispatch) return;

            const customer = cartSelect?.getCustomerData?.() || {};
            const shipping = customer.shippingAddress || {};
            const billing = customer.billingAddress || {};

            const resolvedPostcode = String(postcode ?? '').trim();
            const nextShipping = buildCurrentBlocksAddress('shipping', shipping, resolvedPostcode);
            const nextBilling  = buildCurrentBlocksAddress('billing', billing, resolvedPostcode);

            // local UI update
            cartDispatch?.setShippingAddress?.(nextShipping);
            cartDispatch?.setBillingAddress?.(nextBilling);

            // persist to server (this is what prevents “revert”)
            return cartDispatch?.updateCustomerData?.(
            { shippingAddress: nextShipping, billingAddress: nextBilling },
            false
            );
        }


})(jQuery);
