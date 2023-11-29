<?php
/**
 * The functionality of the crypay payment gateway.
 *
 * @link       https://crypay.com
 * @since      1.0.0
 *
 * @package    Crypay_For_Woocommerce
 * @subpackage Crypay_For_Woocommerce/includes
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (!class_exists('WC_Payment_Gateway')) {
    return;
}

use CryPay\Exception\ApiErrorException;
use CryPay\Client;

/**
 * The functionality of the crypay payment gateway.
 *
 * @since      1.0.0
 * @package    Crypay_For_Woocommerce
 * @subpackage Crypay_For_Woocommerce/includes
 * @author     CryPay <support@crypay.com>
 */
class Crypay_For_Woocommerce_Payment_Gateway extends WC_Payment_Gateway
{

    public const ORDER_TOKEN_META_KEY = 'crypay_order_token';

    public const SETTINGS_KEY = 'woocommerce_crypay_settings';

    /**
     * Crypay_Payment_Gateway constructor.
     */
    public function __construct()
    {
        $this->id = 'crypay';
        $this->has_fields = false;
        $this->method_title = 'CryPay';
        $this->icon = apply_filters('woocommerce_crypay_icon', CRYPAY_FOR_WOOCOMMERCE_PLUGIN_URL . 'assets/bitcoin.png');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->api_key = $this->get_option('api_key');
        $this->api_secret = $this->get_option('api_secret');
        $this->order_statuses = $this->get_option('order_statuses');
        $this->test = ('yes' === $this->get_option('test', 'no'));

        add_action('woocommerce_update_options_payment_gateways_crypay', array($this, 'process_admin_options'));
        add_action('woocommerce_update_options_payment_gateways_crypay', array($this, 'save_order_statuses'));
        add_action('woocommerce_thankyou_crypay', array($this, 'thankyou'));
        add_action('woocommerce_api_wc_gateway_crypay', array($this, 'payment_callback'));
    }

    /**
     * Output the gateway settings screen.
     */
    public function admin_options()
    {
        ?>
        <h3>
            <?php
            esc_html_e('CryPay', 'crypay');
            ?>
        </h3>
        <p>
            <?php
            esc_html_e(
                'Accept Bitcoin through the CryPay.com',
                'crypay'
            );
            ?>
            <br>
            <a href="https://dev.crypay.com/docs/issues" target="_blank">
                <?php
                esc_html_e('Not working? Common issues');
                ?>
            </a> &middot;
            <a href="mailto:support@crypay.com">support@crypay.com</a>
        </p>
        <table class="form-table">
            <?php
            $this->generate_settings_html();
            ?>
        </table>
        <?php
    }

    /**
     * Initialise settings form fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable CryPay', 'crypay'),
                'label' => __('Enable Cryptocurrency payments via CryPay', 'crypay'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ),
            'callback' => array(
                'title' => __('Callback url', 'crypay'),
                'label' => __('Callback url', 'crypay'),
                'type' => 'text',
                'description' => '',
                'disabled' => true,
                'default' => trailingslashit(get_bloginfo('wpurl')) . '?wc-api=wc_gateway_crypay',
            ),
            'description' => array(
                'title' => __('Description', 'crypay'),
                'type' => 'textarea',
                'description' => __('The payment method description which a user sees at the checkout of your store.', 'crypay'),
                'default' => __('Pay with BTC, LTC, ETH, XMR, XRP, BCH and other cryptocurrencies. Powered by CryPay.', 'crypay'),
            ),
            'title' => array(
                'title' => __('Title', 'crypay'),
                'type' => 'text',
                'description' => __('The payment method title which a customer sees at the checkout of your store.', 'crypay'),
                'default' => __('Cryptocurrencies via CryPay', 'crypay'),
            ),
            'api_key' => array(
                'title' => __('API Key', 'crypay'),
                'type' => 'text',
                'description' => __('CryPay API Key', 'crypay'),
                'default' => '',
            ),
            'api_secret' => array(
                'title' => __('API Secret', 'crypay'),
                'type' => 'text',
                'description' => __('CryPay API Secret', 'crypay'),
                'default' => '',
            ),
            'order_statuses' => array(
                'type' => 'order_statuses',
            ),
            'test' => array(
                'title' => __('Test', 'crypay'),
                'type' => 'checkbox',
                'label' => __('Enable Test Mode', 'crypay'),
                'default' => 'no',
                'description' => __(
                    'To test on <a href="https://dev.crypay.com" target="_blank">CryPay Test</a>, turn Test Mode "On".
					Please note, for Test Mode you must create a separate account on <a href="https://dev.crypay.com" target="_blank">dev.crypay.com</a> and generate API credentials there.
					API credentials generated on <a href="https://crypay.com" target="_blank">crypay.com</a> are "Live" credentials and will not work for "Test" mode.',
                    'crypay'
                ),
            ),
        );
    }

    /**
     * Thank you page.
     */
    public function thankyou()
    {
        $description = $this->get_description();
        if ($description) {
            echo '<p>' . esc_html($description) . '</p>';
        }
    }

    /**
     * Payment process.
     *
     * @param int $order_id The order ID.
     * @return string[]
     *
     * @throws Exception Unknown exception type.
     */
    public function process_payment($order_id)
    {
        global $woocommerce, $page, $paged;
        $order = wc_get_order($order_id);

        $client = $this->init_crypay();

        $params = [
            'variableSymbol' => (string)$order->get_id(),
            'amount' => (float)$order->get_total(),
            'symbol' => $order->get_currency(),
            'currency' => $order->get_currency(),
            'failUrl' => $this->get_fail_order_url($order),
            'successUrl' => add_query_arg('order-received', $order->get_id(), add_query_arg('key', $order->get_order_key(), $this->get_return_url($order))),
 	    'timestamp' => time(),
        ];

        $response = array('result' => 'fail');

        try {
            $gateway_order = $client->payment->create($params);
            if ($gateway_order) {
                // update_post_meta($order->get_id(), static::ORDER_TOKEN_META_KEY, $gateway_order->token);
                $response['result'] = 'success';
                $response['redirect'] = $gateway_order->shortLink;
            }
        } catch (ApiErrorException $exception) {
            error_log($exception->getMessage());
        }

        return $response;
    }

    /**
     * Payment callback.
     *
     * @throws Exception Unknown exception type.
     */
    public function payment_callback()
    {
        $request = file_get_contents('php://input');

        $client = $this->init_crypay();

        $signature = isset($_SERVER['HTTP_X_SIGNATURE']) ? $_SERVER['HTTP_X_SIGNATURE'] : null;

        if ($signature != $client->generateSignature($request, $this->settings['api_secret'])) {
            throw new Exception('CryPay callback signature does not valid');
        }

        $request = json_decode($request, true);

        $order = wc_get_order(sanitize_text_field($request['variableSymbol']));

        if (!$order || !$order->get_id()) {
            throw new Exception('Order #' . $order->get_id() . ' does not exists');
        }

        if ($order->get_payment_method() !== $this->id) {
            throw new Exception('Order #' . $order->get_id() . ' payment method is not ' . $this->method_title);
        }

        $callback_order_status = sanitize_text_field($request['state']);

        $order_statuses = $this->get_option('order_statuses');

        $wc_order_status = isset($order_statuses[$callback_order_status]) ? $order_statuses[$callback_order_status] : null;
        if (!$wc_order_status) {
            return;
        }

        switch ($callback_order_status) {
            case 'SUCCESS':
                if (!$this->is_order_paid_status_valid($order, $request['amount'])) {
                    throw new Exception('CryPay Order #' . $order->get_id() . ' amounts do not match');
                }

                $status_was = 'wc-' . $order->get_status();

                $this->handle_order_status($order, $wc_order_status);
                $order->add_order_note(__('Payment is confirmed on the network, and has been credited to the merchant. Purchased goods/services can be securely delivered to the buyer.', 'crypay'));
                $order->payment_complete();

                $wc_expired_status = $order_statuses['EXPIRED'];

                if ('processing' === $order->status && ($status_was === $wc_expired_status)) {
                    WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order->get_id());
                }
                if (('processing' === $order->status || 'completed' === $order->status) && ($status_was === $wc_expired_status)) {
                    WC()->mailer()->emails['WC_Email_New_Order']->trigger($order->get_id());
                }
                break;
            case 'WAITING_FOR_CONFIRMATION':
                $this->handle_order_status($order, $wc_order_status);
                $order->add_order_note(__('Shopper transferred the payment for the invoice. Awaiting blockchain network confirmation.', 'crypay'));
                break;
            case 'EXPIRED':
                $this->handle_order_status($order, $wc_order_status);
                $order->add_order_note(__('Buyer did not pay within the required time and the invoice expired.', 'crypay'));
                break;
        }
    }

    /**
     * Generates a URL so that a customer can cancel their (unpaid - pending) order.
     *
     * @param WC_Order $order Order.
     * @param string $redirect Redirect URL.
     * @return string
     */
    public function get_fail_order_url($order, $redirect = '')
    {
        return apply_filters(
            'woocommerce_get_cancel_order_url',
            wp_nonce_url(
                add_query_arg(
                    array(
                        'order' => $order->get_order_key(),
                        'order_id' => $order->get_id(),
                        'redirect' => $redirect,
                    ),
                    $order->get_cancel_endpoint()
                ),
                'woocommerce-cancel_order'
            )
        );
    }

    /**
     * Generate order statuses.
     *
     * @return false|string
     */
    public function generate_order_statuses_html()
    {
        ob_start();

        $cg_statuses = $this->crypay_order_statuses();
        $default_status['ignore'] = __('Do nothing', 'crypay');
        $wc_statuses = array_merge($default_status, wc_get_order_statuses());

        $default_statuses = array(
            'SUCCESS' => 'wc-processing',
            'WAITING_FOR_CONFIRMATION' => 'ignore',
            'EXPIRED' => 'ignore',
        );

        ?>
        <tr valign="top">
            <th scope="row" class="titledesc"> <?php esc_html_e('Order Statuses:', 'crypay'); ?></th>
            <td class="forminp" id="crypay_order_statuses">
                <table cellspacing="0">
                    <?php
                    foreach ($cg_statuses as $cg_status_name => $cg_status_title) {
                        ?>
                        <tr>
                            <th><?php echo esc_html($cg_status_title); ?></th>
                            <td>
                                <select name="woocommerce_crypay_order_statuses[<?php echo esc_html($cg_status_name); ?>]">
                                    <?php
                                    $cg_settings = get_option(static::SETTINGS_KEY);
                                    $order_statuses = $cg_settings['order_statuses'];

                                    foreach ($wc_statuses as $wc_status_name => $wc_status_title) {
                                        $current_status = isset($order_statuses[$cg_status_name]) ? $order_statuses[$cg_status_name] : null;

                                        if (empty($current_status)) {
                                            $current_status = $default_statuses[$cg_status_name];
                                        }

                                        if ($current_status === $wc_status_name) {
                                            echo '<option value="' . esc_attr($wc_status_name) . '" selected>' . esc_html($wc_status_title) . '</option>';
                                        } else {
                                            echo '<option value="' . esc_attr($wc_status_name) . '">' . esc_html($wc_status_title) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </table>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    /**
     * Validate order statuses field.
     *
     * @return mixed|string
     */
    public function validate_order_statuses_field()
    {
        $order_statuses = $this->get_option('order_statuses');

        if (isset($_POST[$this->plugin_id . $this->id . '_order_statuses'])) {
            return array_map(
                'sanitize_text_field',
                wp_unslash($_POST[$this->plugin_id . $this->id . '_order_statuses'])
            );
        }

        return $order_statuses;
    }

    /**
     * Save order statuses.
     */
    public function save_order_statuses()
    {
        $crypay_order_statuses = $this->crypay_order_statuses();
        $wc_statuses = wc_get_order_statuses();

        if (isset($_POST['woocommerce_crypay_order_statuses'])) {
            $cg_settings = get_option(static::SETTINGS_KEY);
            $order_statuses = $cg_settings['order_statuses'];

            foreach ($crypay_order_statuses as $cg_status_name => $cg_status_title) {
                if (!isset($_POST['woocommerce_crypay_order_statuses'][$cg_status_name])) {
                    continue;
                }

                $wc_status_name = sanitize_text_field(wp_unslash($_POST['woocommerce_crypay_order_statuses'][$cg_status_name]));

                if (array_key_exists($wc_status_name, $wc_statuses)) {
                    $order_statuses[$cg_status_name] = $wc_status_name;
                }
            }

            $cg_settings['order_statuses'] = $order_statuses;
            update_option(static::SETTINGS_KEY, $cg_settings);
        }
    }

    /**
     * Handle order status.
     *
     * @param WC_Order $order The order.
     * @param string $status Order status.
     */
    protected function handle_order_status(WC_Order $order, string $status)
    {
        if ('ignore' !== $status) {
            $order->update_status($status);
        }
    }

    /**
     * List of crypay order statuses.
     *
     * @return string[]
     */
    private function crypay_order_statuses()
    {
        return [
            'SUCCESS' => 'SUCCESS',
            'WAITING_FOR_CONFIRMATION' => 'WAITING_FOR_CONFIRMATION',
            'EXPIRED' => 'EXPIRED',
        ];
    }

    /**
     * Initial client.
     *
     * @return Client
     */
    private function init_crypay()
    {

        $client = new Client($this->api_key, $this->test);
        $client::setAppInfo('Crypay For Woocommerce', CRYPAY_FOR_WOOCOMMERCE_VERSION);

        return $client;
    }

    /**
     * Check if order status is valid.
     *
     * @param WC_Order $order The order.
     * @param mixed $price Price.
     * @return bool
     */
    private function is_order_paid_status_valid(WC_Order $order, $price)
    {
        return $order->get_total() >= (float)$price;
    }

    /**
     * Check token match.
     *
     * @param WC_Order $order The order.
     * @param string $token Token.
     * @return bool
     */
    private function is_token_valid(WC_Order $order, string $token)
    {
        $order_token = $order->get_meta(static::ORDER_TOKEN_META_KEY);

        return !empty($order_token) && $token === $order_token;
    }

}
