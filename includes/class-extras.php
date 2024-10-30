<?php
namespace ivendPay;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IvendPay extras class.
 */
class Extras {

	/**
	 * __FILE__ from the root plugin file.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $file;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $file Must be __FILE__ from the root plugin file.
	 */
	public function __construct( $file ) {
		$this->file = $file;

		add_filter( 'woocommerce_gateway_icon', [ $this, 'add_gateway_icons' ], 10, 2 );

		add_filter( 'plugin_action_links_' . plugin_basename($this->file), [$this, 'plugin_action_links'] );
	}

	/**
	 * Add IvendPay logo to the gateway.
	 *
	 * @since 1.0.0
	 * @param string $icons Html image tags.
	 * @param string $gateway_id Gateway id.
	 * @return string
	 */
	public function add_gateway_icons( $icons, $gateway_id ) {
		if ( 'ivendpay_gateway' === $gateway_id ) {
			$icons .= sprintf(
				'<img style="max-height: 30px; max-width: 150px;" src="%1$sassets/media/ivendpay.png" alt="ivendpay.com" />',
				plugin_dir_url( $this->file )
			);
		}
		return $icons;
	}

	/**
	 * Add IvendPay setting links on plugin page.
	 *
	 * @since 1.0.0
	 * @param array links.
	 * @return array
	 */
	public function plugin_action_links($links) {
		$setting_link = admin_url('admin.php?page=wc-settings&tab=checkout&section=ivendpay_gateway');

		$plugin_links = array(
			'<a target="_blank" href="' . $setting_link . '">' . __('Settings', 'ivendpay') . '</a>',
		);

	    return array_merge($plugin_links, $links);
   }
}
