<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;

/**
 * Class for integrating with WooCommerce Blocks scripts
 *
 * @package 
 * @since
 */
final class PaymentMethodBorgunRPG extends AbstractPaymentMethodType {
	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'borgun_rpg';

	/**
	 * Settings from the WP options table
	 *
	 * @var array
	 */
	protected $settings = [];

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize(){
		$this->settings = get_option( 'woocommerce_borgun_rpg_settings', [] );
		$this->register_scripts();
		$this->register_edit_scripts();
	}

	/**
	 * @return bool
	 */
	public function register_scripts(){
		$script_path       = 'blocks/build/view.js';
		$script_url = plugin_dir_url( __DIR__ ) . $script_path;
		$script_asset_path = plugin_dir_url( __DIR__ )  . 'blocks/build/view.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_asset_path ),
			);

		$result = wp_register_script(
			'borgun-rpg-script-frontend',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		if (!$result) {
			return false;
		}

		wp_set_script_translations(
			'borgun-rpg-script-frontend',
			'borgun-rpg-payment-gateway-for-woocommerce',
			dirname(dirname( __FILE__ )) . '/languages'
		);

		return true;
	}

	/**
	 * @return bool
	 */
	public function register_edit_scripts(){
		$script_path       = 'blocks/build/index.js';
		$script_url = plugin_dir_url( __DIR__ ) . $script_path;
		$script_asset_path = plugin_dir_url( __DIR__ )  . 'blocks/build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_asset_path ),
			);

		$result = wp_register_script(
			'borgun-rpg-script-editor',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		if (!$result) {
			return false;
		}

		wp_set_script_translations(
			'borgun-rpg-script-editor',
			'borgun-rpg-payment-gateway-for-woocommerce',
			dirname(dirname( __FILE__ )) . '/languages'
		);
		return true;
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Returns an array of script handles to enqueue for this payment method in
	 * the frontend context
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		return ['borgun-rpg-script-frontend'];
	}

	/**
	 * Returns an array of script handles to enqueue for this payment method in
	 * the admin context
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles_for_admin() {
		return ['borgun-rpg-script-editor'];
	}

	/**
	 * An array of key, value pairs of data made available to payment methods
	 * client side.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
			'testmode'    => $this->get_setting('testmode') === 'yes',
			'publickey'   => $this->get_setting( 'publickey' ),
			'logoUrl'     => plugin_dir_url( __DIR__ ) . 'teya.png'
		];
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version( $file ){
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $file ) ) {
			return filemtime( $file );
		}
		return BORGUN_RPG_VERSION;
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features(){
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways['borgun_rpg'] ) ) {
			$gateway = $gateways['borgun_rpg'];
			return $gateway->supports;
		}

		return [];
	}
}