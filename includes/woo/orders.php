<?php

/**
 * Namespace: includes/woo.
 */

use Automattic\WooCommerce\Utilities\OrderUtil;


if (!defined('ABSPATH')) {
    exit;
}

class WooOrders
{
    /**
     * Global database.
     */
    private $wpdb;

    /** 
     * Constructor.
     */
    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->init();
    }

    /**
     * Init your settings.
     */
    function init()
    {

        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && OrderUtil::custom_orders_table_usage_is_enabled() ) {
            add_filter( 'woocommerce_shop_order_list_table_columns', array($this, 'loadOrdersActions'));
            add_filter('woocommerce_shop_order_list_table_columns', array($this, 'loadOrdersActionsSortable'));
            add_action('woocommerce_shop_order_list_table_custom_column',  array($this, 'loadOrdersActionsContent'), 10, 2);
            add_action( 'woocommerce_order_list_table_extra_tablenav', array($this, 'loadOrdersActionsModalHPOS'), 20, 2);


        } else {
            // Traditional CPT-based orders are in use.
            add_filter('manage_edit-shop_order_columns', array($this, 'loadOrdersActions'));
            add_filter('manage_edit-shop_order_sortable_columns', array($this, 'loadOrdersActionsSortable'));
            add_action('manage_shop_order_posts_custom_column', array($this, 'loadOrdersActionsContent'), 2);
            add_action('manage_posts_extra_tablenav',  array($this, 'loadOrdersActionsModal'), 20, 1);
        }
    }


    /**
     * Load orders actions for WooCommerce.
     */
    public function loadOrdersActions($columns)
    {

        $new_columns = (is_array($columns)) ? $columns : array();
        unset($new_columns['order_actions']);

        /**
         * All of your columns will be added before the actions column.
         */
        $new_columns['dpdro'] = __('DPD RO', 'dpdro');

        /**
         * Stop editing. 
         */
        //$new_columns['order_actions'] = $columns['order_actions'];
        return $new_columns;
    }

    /**
     * Load orders actions sortable for WooCommerce.
     */
    public function loadOrdersActionsSortable($columns)
    {
        $custom = array(
            'dpdro' => __('DPD RO', 'dpdro')
        );
        return wp_parse_args($custom, $columns);
    }

    /**
     * Load orders actions content for WooCommerce.
     */
    public function loadOrdersActionsContent($column, $order = null)
    {
        /**
         * Start editing, I was saving my fields for the orders as custom post meta.
         */
        if ($column == 'dpdro') {

            /**
             * Order data.
             */
            global $post;
            if (!empty($post)) {
                $order = wc_get_order($post->ID);
            }
            if ($order) {
                $orderShippingMethod = false;
                $orderShippingMethods = $order->get_shipping_methods();
                foreach ($orderShippingMethods as $method) {
                    $orderShippingMethod = str_replace('dpdro_shipping_', '', $method->get_method_id());
                    $orderShippingMethod = str_replace('shipping_dpd_', '', $orderShippingMethod);
                }

                /**
                 * Woo order
                 */
                $wooOrder = new WooOrder($this->wpdb);
                $wooOrderShipment = $wooOrder->getOrderShipment($order->id);
                $wooOrderShipping = $wooOrder->getOrderShipping($order->id);
            }
            /** 
             * Data Lists
             */
            $dataLists = new DataLists($this->wpdb);
            $orderShippingMethod = $dataLists->getServiceById($orderShippingMethod);
            echo '<div class="dpdro js-dpdro">';
            if ($order->get_status() == 'completed') {
                if ($wooOrderShipment && !empty($wooOrderShipment)) {
                    $orderRequestCourier = $wooOrder->getRequestCourier($wooOrderShipment->shipment_id);
                    if ($orderShippingMethod) {
                        if ($orderRequestCourier && !empty($orderRequestCourier)) {
                            $orderShipmentLabels = admin_url('admin.php/dpdro_print?print=labels&id=' . absint($order->id));
                            echo '
                                <a href="' . $orderShipmentLabels . '" title="' . __('Print labels', 'dpdro') . '" type="button" class="d-button d-opacity icon d-mb warning">
                                    <i class="dashicons dashicons-printer"></i>
                                </a>
                            ';
                            $orderShipmentReturns = json_decode($wooOrderShipment->shipment_data);
                            if ($orderShipmentReturns && $orderShipmentReturns->shipment_has_voucher === 'true') {
                                $orderShipmentVoucher = admin_url('admin.php/dpdro_print?print=voucher&id=' . absint($order->id));
                                echo '
                                    <a href="' . $orderShipmentVoucher . '" title="' . __('Print voucher', 'dpdro') . '" class="d-button d-opacity icon d-mb warning">
                                        <i class="dashicons dashicons-printer"></i>
                                    </a>
                                ';
                            }
                            echo '
                                <button style="cursor: help;" title="' . __('Order has been picked up', 'dpdro') . '" type="button" class="d-button d-opacity icon success">
                                    <i class="dashicons dashicons-yes-alt"></i>
                                </button>
                            ';
                            echo '
                                <a target="_blank" href="https://tracking.dpd.ro/?shipmentNumber=' . $wooOrderShipment->shipment_id . '" title="' . __('Track', 'dpdro') . '" class="d-button d-opacity icon d-mb info js-d-track-order">
                                    <i class="dashicons dashicons-location"></i>
                                </a>
                            ';
                        } else {
                            $orderShipmentLabels = admin_url('admin.php/dpdro_print?print=labels&id=' . absint($order->id));
                            echo '
                                <a href="' . $orderShipmentLabels . '" title="' . __('Print labels', 'dpdro') . '" type="button" class="d-button d-opacity icon d-mb warning">
                                    <i class="dashicons dashicons-printer"></i>
                                </a>
                            ';
                            $orderShipmentReturns = json_decode($wooOrderShipment->shipment_data);
                            if ($orderShipmentReturns && $orderShipmentReturns->shipment_has_voucher === 'true') {
                                $orderShipmentVoucher = admin_url('admin.php/dpdro_print?print=voucher&id=' . absint($order->id));
                                echo '
                                    <a href="' . $orderShipmentVoucher . '" title="' . __('Print voucher', 'dpdro') . '" class="d-button d-opacity icon d-mb warning">
                                        <i class="dashicons dashicons-printer"></i>
                                    </a>
                                ';
                            }
                            echo '
                                <a target="_blank" href="https://tracking.dpd.ro/?shipmentNumber=' . $wooOrderShipment->shipment_id . '" title="' . __('Track', 'dpdro') . '" class="d-button d-opacity icon d-mb info js-d-track-order">
                                    <i class="dashicons dashicons-location"></i>
                                </a>
                            ';
                            echo '
                                <button style="cursor: help;" title="' . __('No pickup order', 'dpdro') . '" type="button" class="d-button d-opacity icon danger">
                                    <i class="dashicons dashicons-warning"></i>
                                </button>
                            ';
                        }
                    } else {
                        if ($wooOrderShipment && !empty($wooOrderShipment) && $wooOrderShipping && !empty($wooOrderShipping)) {
                            if ($orderRequestCourier && !empty($orderRequestCourier)) {
                                $orderShipmentLabels = admin_url('admin.php/dpdro_print?print=labels&id=' . absint($order->id));
                                echo '
                                    <a href="' . $orderShipmentLabels . '" title="' . __('Print labels for order without DPD RO shipping method', 'dpdro') . '" type="button" class="d-button icon d-mb warning">
                                        <i class="dashicons dashicons-printer"></i>
                                    </a>
                                ';
                                $orderShipmentReturns = json_decode($wooOrderShipment->shipment_data);
                                if ($orderShipmentReturns && $orderShipmentReturns->shipment_has_voucher === 'true') {
                                    $orderShipmentVoucher = admin_url('admin.php/dpdro_print?print=voucher&id=' . absint($order->id));
                                    echo '
                                        <a href="' . $orderShipmentVoucher . '" title="' . __('Print voucher for order without DPD RO shipping method', 'dpdro') . '" class="d-button icon d-mb warning">
                                            <i class="dashicons dashicons-printer"></i>
                                        </a>
                                    ';
                                }
                                echo '
                                    <button style="cursor: help;" title="' . __('Order without DPD RO shipping method has been picked up', 'dpdro') . '" type="button" class="d-button icon success">
                                        <i class="dashicons dashicons-yes-alt"></i>
                                    </button>
                                ';
                                echo '
                                    <a target="_blank" href="https://tracking.dpd.ro/?shipmentNumber=' . $wooOrderShipment->shipment_id . '" title="' . __('Track', 'dpdro') . '" class="d-button icon d-mb info js-d-track-order">
                                        <i class="dashicons dashicons-location"></i>
                                    </a>
                                ';
                            } else {
                                $orderShipmentLabels = admin_url('admin.php/dpdro_print?print=labels&id=' . absint($order->id));
                                echo '
                                    <a href="' . $orderShipmentLabels . '" title="' . __('Print labels for order without DPD RO shipping method', 'dpdro') . '" type="button" class="d-button icon d-mb warning">
                                        <i class="dashicons dashicons-printer"></i>
                                    </a>
                                ';
                                $orderShipmentReturns = json_decode($wooOrderShipment->shipment_data);
                                if ($orderShipmentReturns && $orderShipmentReturns->shipment_has_voucher === 'true') {
                                    $orderShipmentVoucher = admin_url('admin.php/dpdro_print?print=voucher&id=' . absint($order->id));
                                    echo '
                                        <a href="' . $orderShipmentVoucher . '" title="' . __('Print voucher for order without DPD RO shipping method', 'dpdro') . '" class="d-button icon d-mb warning">
                                            <i class="dashicons dashicons-printer"></i>
                                        </a>
                                    ';
                                }
                                echo '
                                    <a target="_blank" href="https://tracking.dpd.ro/?shipmentNumber=' . $wooOrderShipment->shipment_id . '" title="' . __('Track', 'dpdro') . '" class="d-button icon d-mb info js-d-track-order">
                                        <i class="dashicons dashicons-location"></i>
                                    </a>
                                ';
                                echo '
                                    <button style="cursor: help;" title="' . __('No pickup order', 'dpdro') . '" type="button" class="d-button icon danger">
                                        <i class="dashicons dashicons-warning"></i>
                                    </button>
                                ';
                            }
                        }
                    }
                } else {
                    echo '
                        <button style="cursor: help;" title="' . __('No shipping carrier', 'dpdro') . '" type="button" class="d-button d-opacity icon danger">
                            <i class="dashicons dashicons-warning"></i>
                        </button>
                    ';
                }
            } else {
                if ($orderShippingMethod) {
                    if ($wooOrderShipment && !empty($wooOrderShipment)) {
                        $ajaxNonceDeleteShipment = wp_create_nonce('dpdro_delete_shipment');
                        $ajaxNonceRequestCourier = wp_create_nonce('dpdro_request_courier');
                        $orderShipmentLabels = admin_url('admin.php/dpdro_print?print=labels&id=' . absint($order->id));
                        echo '
                            <button data-nonce="' . $ajaxNonceDeleteShipment . '" title="' . __('Delete shipment', 'dpdro') . '" data-order-id="' . $order->id  . '" type="button" class="d-button d-opacity icon d-mb danger js-d-delete-shipment">
                                <i class="dashicons dashicons-trash"></i>
                            </button>
                            <a href="' . $orderShipmentLabels . '" title="' . __('Print labels', 'dpdro') . '" type="button" class="d-button d-opacity icon d-mb warning">
                                <i class="dashicons dashicons-printer"></i>
                            </a>
                        ';
                        $orderShipmentReturns = json_decode($wooOrderShipment->shipment_data);
                        if ($orderShipmentReturns && $orderShipmentReturns->shipment_has_voucher === 'true') {
                            $orderShipmentVoucher = admin_url('admin.php/dpdro_print?print=voucher&id=' . absint($order->id));
                            echo '
                                <a href="' . $orderShipmentVoucher . '" title="' . __('Print voucher', 'dpdro') . '" class="d-button d-opacity icon d-mb warning">
                                    <i class="dashicons dashicons-printer"></i>
                                </a>
                            ';
                        }
                        $orderRequestCourier = $wooOrder->getRequestCourier($wooOrderShipment->shipment_id);
                        if (!$orderRequestCourier || empty($orderRequestCourier)) {
                            echo '
                                <button data-nonce="' . $ajaxNonceRequestCourier . '" title="' . __('Request courier', 'dpdro') . '" data-shipment-id="' . $wooOrderShipment->shipment_id  . '" type="button" class="d-button d-opacity icon d-mb primary js-d-request-courier">
                                    <i class="dashicons dashicons-car"></i>
                                </button>
                            ';
                        }
                        echo '
                            <a target="_blank" href="https://tracking.dpd.ro/?shipmentNumber=' . $wooOrderShipment->shipment_id . '" title="' . __('Track', 'dpdro') . '" class="d-button d-opacity icon d-mb info js-d-track-order">
                                <i class="dashicons dashicons-location"></i>
                            </a>
                        ';
                        if ($orderRequestCourier && !empty($orderRequestCourier)) {
                            echo '
                                <button style="cursor: help;" title="' . __('A courier has been requested for order', 'dpdro') . '" type="button" class="d-button d-opacity icon success">
                                    <i class="dashicons dashicons-info-outline"></i>
                                </button>
                            ';
                        }
                    } else {
                        $ajaxNonceCreateShipment = wp_create_nonce('dpdro_create_shipment');
                        echo '
                            <button data-nonce="' . $ajaxNonceCreateShipment . '" title="' . __('Create shipment', 'dpdro') . '" data-order-id="' . $order->id  . '" type="button" class="d-button d-opacity icon d-mb primary js-d-create-shipment">
                                <i class="dashicons dashicons-welcome-add-page"></i>
                            </button>
                        ';
                    }
                } else {
                    if ($wooOrderShipment && !empty($wooOrderShipment) && $wooOrderShipping && !empty($wooOrderShipping)) {
                        $ajaxNonceDeleteShipment = wp_create_nonce('dpdro_delete_shipment');
                        $ajaxNonceRequestCourier = wp_create_nonce('dpdro_request_courier');
                        $orderShipmentLabels = admin_url('admin.php/dpdro_print?print=labels&id=' . absint($order->id));
                        echo '
                            <button data-nonce="' . $ajaxNonceDeleteShipment . '" title="' . __('Delete shipment for order without DPD RO shipping method', 'dpdro') . '" data-order-id="' . $order->id  . '" type="button" class="d-button icon d-mb danger js-d-delete-shipment">
                                <i class="dashicons dashicons-trash"></i>
                            </button>
                            <a href="' . $orderShipmentLabels . '" title="' . __('Print labels for order without DPD RO shipping method', 'dpdro') . '" type="button" class="d-button icon d-mb warning">
                                <i class="dashicons dashicons-printer"></i>
                            </a>
                        ';
                        $orderShipmentReturns = json_decode($wooOrderShipment->shipment_data);
                        if ($orderShipmentReturns && $orderShipmentReturns->shipment_has_voucher === 'true') {
                            $orderShipmentVoucher = admin_url('admin.php/dpdro_print?print=voucher&id=' . absint($order->id));
                            echo '
                                <a href="' . $orderShipmentVoucher . '" title="' . __('Print voucher for order without DPD RO shipping method', 'dpdro') . '" class="d-button icon d-mb warning">
                                    <i class="dashicons dashicons-printer"></i>
                                </a>
                            ';
                        }
                        $orderRequestCourier = $wooOrder->getRequestCourier($wooOrderShipment->shipment_id);
                        if (!$orderRequestCourier || empty($orderRequestCourier)) {
                            echo '
                                <button data-nonce="' . $ajaxNonceRequestCourier . '" title="' . __('Request courier for order without DPD RO shipping method', 'dpdro') . '" data-shipment-id="' . $wooOrderShipment->shipment_id  . '" type="button" class="d-button icon d-mb primary js-d-request-courier">
                                    <i class="dashicons dashicons-car"></i>
                                </button>
                            ';
                        }
                        echo '
                            <a target="_blank" href="https://tracking.dpd.ro/?shipmentNumber=' . $wooOrderShipment->shipment_id . '" title="' . __('Track for order without DPD RO shipping method', 'dpdro') . '" class="d-button icon d-mb info js-d-track-order">
                                <i class="dashicons dashicons-location"></i>
                            </a>
                        ';
                        if ($orderRequestCourier && !empty($orderRequestCourier)) {
                            echo '
                                <button style="cursor: help;" title="' . __('A courier has been requested for order without DPD RO shipping method', 'dpdro') . '" type="button" class="d-button icon success">
                                    <i class="dashicons dashicons-info-outline"></i>
                                </button>
                            ';
                        }
                    } else {
                        $ajaxNonceCreateShipment = wp_create_nonce('dpdro_create_shipment');
                        echo '
                            <button data-nonce="' . $ajaxNonceCreateShipment . '" title="' . __('Create shipment for order without DPD RO shipping method', 'dpdro') . '" data-order-id="' . $order->id  . '" type="button" class="d-button icon d-mb primary js-d-create-shipment">
                                <i class="dashicons dashicons-welcome-add-page"></i>
                            </button>
                        ';
                    }
                }
            }
            echo '</div>';
        }
    }

    /**
     * Load orders modal content for WooCommerce.
     */
    public function loadOrdersActionsModal($which)
    {
        global $pagenow, $typenow;
        if ($typenow === 'shop_order' && $pagenow === 'edit.php' && $which === 'top') {
            echo '
                <div class="dpdro js-dpdro">
                    <div class="d-modal js-d-modal">
                        <div class="d-modal-box js-d-modal-box">
                            <button type="button" class="d-modal-close js-d-modal-close">
                                <i class="fa fa-times" aria-hidden="true"></i>
                            </button>  
                            <div class="d-modal-content js-d-modal-content"></div>
                        </div>
                    </div>
                </div>
            ';
        }
    }

    public function loadOrdersActionsModalHPOS($order_type, $which)
    {
        global $pagenow, $typenow;
        if ($order_type === 'shop_order' && $pagenow === 'admin.php' && $which === 'top') {
            echo '
                <div class="dpdro js-dpdro">
                    <div class="d-modal js-d-modal">
                        <div class="d-modal-box js-d-modal-box">
                            <button type="button" class="d-modal-close js-d-modal-close">
                                <i class="fa fa-times" aria-hidden="true"></i>
                            </button>  
                            <div class="d-modal-content js-d-modal-content"></div>
                        </div>
                    </div>
                </div>
            ';
        }
    }
}
