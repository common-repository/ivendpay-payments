<?php
namespace ivendPay;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ivendPay plugin factory class.
 */
class Plugin_Factory {

	/**
	 * __FILE__ from the root plugin file.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $file;

	/**
	 * The current version of the plugin.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $version;

	/**
	 * Extras / misc.
	 *
	 * @since 1.0.0
	 * @var \ivendPay\Extras
	 */
	public $extras;

	/**
	 * Holds a single instance of this class.
	 *
	 * @since 1.0.0
	 * @var \ivendPay\Plugin_Factory|null
	 */
	protected static $_instance = null;

	/**
	 * Returns a single instance of this class.
	 *
	 * @since 1.0.0
	 * @param string $file Must be __FILE__ from the root plugin file.
	 * @param string $software_version Current software version of this plugin.
	 * @return \ivendPay\Plugin_Factory|null
	 */
	public static function instance( $file, $software_version ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $software_version );
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $file Must be __FILE__ from the root plugin file.
	 * @param string $software_version Current software version of this plugin.
	 */
	public function __construct( $file, $software_version ) {
		$this->file    = $file;
		$this->version = $software_version;

		$this->init_dependencies();

		add_filter( 'woocommerce_payment_gateways', [ $this, 'register_payment_gateway' ] );
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ $this, 'register_post_type' ] );

		add_shortcode( 'ivendpay_payment_widget', [ $this, 'ivendpay_payment_widget_shortcode' ] );

		$url = 'ivend-pay-check-transaction-woo';
		$actionData = [$this, 'check_ivend_pay_transaction'];
		add_action( 'wp_ajax_' . $url, $actionData );
		add_action( 'wp_ajax_nopriv_' . $url, $actionData );

		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	}

	public function payment_scripts() {
		wp_enqueue_script( 'woocommerce_ivendpay_js', plugins_url('assets/js/ivendpay.js', $this->file) );
		wp_enqueue_style( 'woocommerce_ivendpay', plugins_url('assets/css/ivendpay.css', $this->file) );
	}

	public function ivendpay_payment_widget_shortcode($atts = [], $content = null)
	{
		$html = 'Order ID not found!';

		if (! empty($_GET['token']) && ! empty($_GET['arg'])) {
			$token = sanitize_text_field($_GET['token']);
			$argID = sanitize_text_field($_GET['arg']);

			$gateway = new Gateway($this->file);
			$table = $gateway->orderData($token, $argID);

			$site       = site_url();
			$ajaxUrl    = admin_url( 'admin-ajax.php');
			$siteUrl    = plugins_url('assets/media/', $this->file);
			$siteUrlCss = plugins_url('assets/css/', $this->file);
			$siteUrlJs  = plugins_url('assets/js/', $this->file);

			$args = '?token=' . $token . '&arg='. $argID;

			$successOrder = $gateway->get_route_url($gateway->route_success) . $args;
			$cancelOrder  = $gateway->get_route_url($gateway->route_cancel) . $args;

			if (! $table) {
				return '<h3>Token for payment order not found!</h3>';
			}

			wp_enqueue_script( 'woocommerce_ivendpay_form_js', plugins_url('assets/js/ivendpay-form.js', $this->file) );
			wp_enqueue_script( 'woocommerce_ivendpay_lottie_js', plugins_url('assets/js/lottie-player.js', $this->file) );

			if ($table->payment_status === 'PAID') {
				ob_start();
				include plugin_dir_path($this->file) . '/assets/partials/success.php';
				return ob_get_clean();

			} elseif ($table->payment_status === 'TIMEOUT') {
				ob_start();
				include plugin_dir_path($this->file) . '/assets/partials/failed.php';
				return ob_get_clean();
			}

			$id       = $table->id;
			$price    = $table->payment_price;
			$currency = $table->payment_currency;
			$qrcode   = $table->payment_qrcode;
			$address  = $table->payment_address;
			$url      = $table->payment_string;
			$hash     = $table->payment_invoice;

			$data = [
				'id'           => $id,
				'price'        => $price,
				'currency'     => $currency,
				'qrcode'       => $qrcode,
				'address'      => $address,
				'title'        => '',
				'url'          => $url,
				'hash'         => $hash,
				'storage_time' => strtotime($table->payment_date) * 1000,
			];

			ob_start();
			include plugin_dir_path($this->file) . '/assets/partials/step3.php';
			$html = ob_get_clean();
		}

		return $html;
	}

	/**
	 * Init plugin dependencies.
	 *
	 * @since 1.0.0
	 */
	public function init_dependencies() {

		/**
		 * Extras / misc.
		 *
		 * @since 1.0.0
		 * @param string $file Must be __FILE__ from the root plugin file.
		 */
		$this->extras = new \ivendPay\Extras( $this->file );

	}

	/**
	 * Register the payment gateway.
	 *
	 * @since 1.0.0
	 * @param array $gateways Payment gateways.
	 */
	public function register_payment_gateway( $gateways ) {
		$gateways[] = new \ivendPay\Gateway( $this->file );
		return $gateways;
	}

	/**
	 * Load textdomain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'ivendpay', false, dirname( plugin_basename( $this->file ) ) . '/languages' );
	}

	public function register_post_type()
	{
		register_post_type('ivendpay_payment',
			array(
				'labels' => array(
					'name' => __('ivendPay Payment'),
					'singular_name' => __('ivendPay Payment')
				),
				'public' => true,
				'has_archive' => false,
				'publicly_queryable' => true,
				'exclude_from_search' => true,
				'show_in_menu' => false,
				'show_in_nav_menus' => false,
				'show_in_admin_bar' => false,
				'show_in_rest' => false,
				'hierarchical' => false,
				'supports' => array('title'),
			)
		);
		flush_rewrite_rules();
	}

	// check transaction from ivendPay and send answer status for invoice
	public function check_ivend_pay_transaction() {
		$response = 'expired';

		if (! empty((int) $_POST['transaction_id'])) {
			require_once plugin_dir_path($this->file) . 'includes/class-ivend-pay-table.php';
			$payTable = new Ivend_Pay_Admin_Table();
			$transaction_id = sanitize_text_field($_POST['transaction_id']);
			$row = $payTable->fetchDataByID((int) $transaction_id);

			if ($row) {
				if ((string)$row->payment_status === 'PAID') {
					$response = 'success';

				} elseif ((string)$row->payment_status === 'TIMEOUT') {
					$response = 'timeout';
				}
			}
		}

		wp_send_json([
			'response' => $response,
			'success' => true,
		]);
	}
}
