<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Crypay_Blocks_Support extends AbstractPaymentMethodType {

    protected $name = 'crypay';

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    public function initialize()
    {
        $this->settings = get_option( 'woocommerce_crypay_settings', [] );
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting( 'title' ),
            'description' => $this->get_setting( 'description' ),
        ];
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc-crypay-blocks-integration',
            CRYPAY_FOR_WOOCOMMERCE_PLUGIN_URL . 'assets/checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            false,
            true
        );
        if( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'wc-crypay-blocks-integration');
        }
        return [ 'wc-crypay-blocks-integration' ];
    }
}