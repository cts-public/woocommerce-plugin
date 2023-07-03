<?php
/**
 * Fired during plugin deactivation.
 *
 * @link       https://crypay.com
 * @since      1.0.0
 *
 * @package    Crypay_For_Woocommerce
 * @subpackage Crypay_For_Woocommerce/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Crypay_For_Woocommerce
 * @subpackage Crypay_For_Woocommerce/includes
 * @author     CryPay <support@crypay.com>
 */
class Crypay_For_Woocommerce_Deactivator {

	/**
	 * Delete plugin settings.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		delete_option( 'woocommerce_crypay_settings' );
	}

}
