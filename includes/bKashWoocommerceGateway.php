<?php

namespace Inc;

use Inc\Base\BkashQuery;
use WC_AJAX;
use WC_Payment_Gateway;

class bKashWoocommerceGateway extends WC_Payment_Gateway
{
    /**
     * Initialize the gateway
     */
    public function __construct()
    {
        $this->id = 'bkash';
        $this->icon = false;
        $this->has_fields = true;
        $this->method_title = __('bKash', 'bKash-wc');
        $this->method_description = __('Pay via bKash payment', 'bKash-wc');
        $title = $this->get_option('title');
        $this->title = empty($title) ? __('bKash', 'bKash-wc') : $title;
        $this->description = $this->get_option('description');

        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    /**
     * Admin configuration parameters
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'bKash-wc'),
                'type' => 'checkbox',
                'label' => __('Enable bKash', 'bKash-wc'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'bKash-wc'),
                'type' => 'text',
                'default' => __('bKash Payment', 'bKash-wc'),
            ),
            'username' => array(
                'title' => __('User Name', 'bKash-wc'),
                'type' => 'text',
            ),
            'password' => array(
                'title' => __('Password', 'bKash-wc'),
                'type' => 'password',
            ),
            'app_key' => array(
                'title' => __('App Key', 'bKash-wc'),
                'type' => 'text',
            ),
            'app_secret' => array(
                'title' => __('App Secret', 'bKash-wc'),
                'type' => 'text',
            ),
            'description' => array(
                'title' => __('Description', 'bKash-wc'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'bKash-wc'),
                'default' => __('Pay via bKash', 'bKash-wc'),
                'desc_tip' => true,
            ),
        );
    }


    /**
     * Process the gateway integration
     *
     * @param  int $order_id
     *
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        // Remove cart
        WC()->cart->empty_cart();

        return array(
            'result' => 'success',
            'order_number' => $order_id,
            'amount' => (float)$order->get_total(),
            'redirect' => $this->get_return_url($order)
        );
    }

    /**
     * include payment scripts
     */
    public function payment_scripts()
    {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        // if our payment gateway is disabled
        if ('no' === $this->enabled) {
            return;
        }
        wp_dequeue_script('wc-checkout');
        wp_enqueue_script('bkash_sandbox', 'https://scripts.pay.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout.js', array(), '1.2.0', true);
        wp_register_script('wcb-checkout', plugins_url('js/bkash.js', dirname(__FILE__)), array('jquery', 'woocommerce', 'wc-country-select', 'wc-address-i18n'), '3.9.1', true);
        wp_enqueue_script('wcb-checkout');

        wp_enqueue_style('bkash_woocommerce_css', plugins_url('css/bkash-woocommerce.css', dirname(__FILE__)), array(), '1.0.0', false);

        $this->localizeScripts();
    }

    /**
     * localize scripts and pass data
     */
    public function localizeScripts()
    {
        global $woocommerce;
        global $wp;

        $data = array(
            'amount' => $woocommerce->cart->cart_contents_total,
            'nonce' => wp_create_nonce('wc-bkash-process'),
        );

        if ($token = BkashQuery::getToken()) {
            $headers = [
                "Content-Type" => "application/json",
                "Authorization" => "Bearer $token",
                "X-APP-Key" => $this->get_option('app_key')
            ];
            $data['headers'] = $headers;
        }

        $params = array(
            'ajax_url' => WC()->ajax_url(),
            'wc_ajax_url' => WC_AJAX::get_endpoint('%%endpoint%%'),
            'update_order_review_nonce' => wp_create_nonce('update-order-review'),
            'apply_coupon_nonce' => wp_create_nonce('apply-coupon'),
            'remove_coupon_nonce' => wp_create_nonce('remove-coupon'),
            'option_guest_checkout' => get_option('woocommerce_enable_guest_checkout'),
            'checkout_url' => WC_AJAX::get_endpoint('checkout'),
            'is_checkout' => is_checkout() && empty($wp->query_vars['order-pay']) && !isset($wp->query_vars['order-received']) ? 1 : 0,
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            'i18n_checkout_error' => esc_attr__('Error processing checkout. Please try again.', 'woocommerce'),
        );

        wp_localize_script('wcb-checkout', 'wc_checkout_params', $params);
        wp_localize_script('bkash_sandbox', 'bkash_params', $data);
    }
}
