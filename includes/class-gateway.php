<?php

namespace ivendPay;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ivendPay gateway class.
 */
class Gateway extends \WC_Payment_Gateway {

	/**
	 * __FILE__ from the root plugin file.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $file;

	/**
	 * Whether or not logging is enabled.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	public static $log_enabled = false;

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var WC_Logger
	 */
	public static $log = false;

	/**
	 * Whether or not test-mode is enabled.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	public static $test_mode = false;

	private static $instance;

	public static function get_instance($file): Gateway {
		if (null === self::$instance) {
			self::$instance = new self($file);
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $file Must be __FILE__ from the root plugin file.
	 */
	public function __construct( $file ) {
		$this->file               = $file;
		$this->id                 = 'ivendpay_gateway';
		$this->method_title       = __( 'ivendPay', 'ivendpay' );
		$this->method_description = __( 'Integrate the plugin to your website and start receiving payments in crypto.', 'ivendpay' );
		$this->supports           = ['products'];
		$this->has_fields         = true;

		$this->init();

		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		add_action( 'woocommerce_api_' . $this->route_callback, [ $this, 'route_callback' ] );
		add_action( 'woocommerce_api_' . $this->route_cancel, [ $this, 'route_cancel' ] );
		add_action( 'woocommerce_api_' . $this->route_success, [ $this, 'route_success' ] );

		$this->activationPluginInit();
	}

	/**
	 * Initialise gateway settings.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// User set variables.
		$this->title             = $this->get_option( 'title' );
		$this->description       = $this->get_option( 'description' );
		$this->order_button_text = $this->get_option( 'order_button_text' );
		$this->debug             = 'yes' === $this->get_option( 'debug', 'no' );
		self::$log_enabled       = $this->debug;
		$this->access_token      = $this->get_option( 'access_token' );

		// Routes.
		$this->route_callback = 'ivendpay/callback';
		$this->route_cancel   = 'ivendpay/cancel';
		$this->route_success  = 'ivendpay/success';

		// API urls.
		$this->api_urls = [
			'generateOrder' => 'https://gate.ivendpay.com/api/v3/create', // POST
			'orderDetails'  => 'https://gate.ivendpay.com/api/v3/bill/', // GET
			'listCoins'     => 'https://gate.ivendpay.com/api/v3/coins', // GET
		];

        if (is_admin()) {
	        $remoteCoins = $this->get_remote_list_conins();
	        $listCoins = [];
	        if (! empty($this->access_token)) {
		        if (! empty($remoteCoins['list'])) {
			        foreach ($remoteCoins['list'] as $value) {
				        $listCoins[$value['id']] = $value['name'] .' ('. $value['ticker_name'].')';
			        }
		        }
	        }

	        $this->form_fields = [
		        'enabled'           => [
			        'title'   => __( 'Enable/Disable', 'ivendpay' ),
			        'type'    => 'checkbox',
			        'label'   => __( 'Enable ivendPay', 'ivendpay' ),
			        'default' => 'yes',
		        ],
		        'title'             => [
			        'title'       => __( 'Title', 'ivendpay' ),
			        'type'        => 'text',
			        'description' => __( 'This controls the title which the user sees during checkout.', 'ivendpay' ),
			        'default'     => __( 'ivendPay', 'ivendpay' ),
			        'desc_tip'    => true,
		        ],
		        'description'       => [
			        'title'       => __( 'Description', 'ivendpay' ),
			        'type'        => 'text',
			        'description' => __( 'This controls the description which the user sees during checkout.', 'ivendpay' ),
			        'default'     => __( 'Pay with ivendPay', 'ivendpay' ),
			        'desc_tip'    => true,
		        ],
		        'order_button_text' => [
			        'title'       => __( 'Order button text', 'ivendpay' ),
			        'type'        => 'text',
			        'description' => __( 'This controls the order button text which the user sees during checkout.', 'ivendpay' ),
			        'default'     => __( 'Proceed to ivendPay', 'ivendpay' ),
			        'desc_tip'    => true,
		        ],
		        'debug'             => [
			        'title'       => __( 'Debug Log', 'ivendpay' ),
			        'type'        => 'checkbox',
			        'label'       => __( 'Enable Logging', 'ivendpay' ),
			        'default'     => 'no',
			        'description' => sprintf( __( 'Log ivendPay events inside: <code>%s</code>', 'ivendpay' ), wc_get_log_file_path( 'ivendpay_gateway' ) ),
		        ],
		        'access_token'      => [
			        'title'       => __( 'Access token', 'ivendpay' ),
			        'type'        => 'text',
			        'description' => __( 'Authentication token are created from customer cabinet.', 'ivendpay' ),
			        'desc_tip'    => true,
		        ]
	        ];

	        $this->init_settings();
        }
	}

	/**
	 * Logging method.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param string $level Optional. Default 'info'. Possible values:
	 *                      emergency|alert|critical|error|warning|notice|info|debug.
	 */
	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->log( $level, $message, [ 'source' => 'ivendpay_gateway' ] );
		}
	}

	/**
	 * Display notices in admin dashboard.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_notices() {
		if ( ! $this->has_required_options() ) {
			echo '<div class="error"><p>' . wp_kses_data( sprintf( __( 'ivendPay Payments: Please fill out required options <a href="%s">here</a>.', 'ivendpay' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $this->id ) ) ) . '</p></div>';
		}
	}

	/**
	 * Output the gateway settings screen.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function admin_options() {
		echo '<h2>' . esc_html( $this->get_method_title() );
		wc_back_link( __( 'Return to payments', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
		echo '</h2>';
		echo wp_kses_post( wpautop( $this->get_method_description() ) );
		\WC_Settings_API::admin_options();
	}

	/**
	 * Is this gateway available?
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_available() {
		return parent::is_available() && $this->has_required_options();
	}

	/**
	 * Are all required options filled out?
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function has_required_options() {
		return $this->access_token;
	}

	/**
	 * API request.
	 *
	 * @since 1.0.0
	 * @param array  $params Query string parameters.
	 * @param string $url URL.
	 * @param string $type Request type.
	 * @return array|false
	 */
	public function api_request( $params, $url, $type = 'POST' ) {
		$response = wp_remote_request(
			$url,
			[
				'method' => $type,
				'body'   => !empty($params) ? json_encode($params) : '',
				'headers' => [
					"Content-Type" => "application/json",
					"X-API-KEY" => $this->access_token,
				]
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->log( $response->get_error_message(), 'error' );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( ! $body ) {
			return false;
		}

		$return = json_decode(html_entity_decode(stripslashes($body)), true);

		if (isset($return['success']) && !$return['success']) {
			$this->log( sprintf( 'Incoming %s, raw data posted: %s', $url, $return ), 'error' );
		}

		return $return;
	}

	/**
	 * Process the payment and redirect client.
	 *
	 * @since 1.0.0
	 * @param  int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
        $orderData = $order->get_data();

		$params = [
			"currency"      => sanitize_text_field($_POST['crypto-method']),
			"amount_fiat"   => $orderData['total'],
			"currency_fiat" => $orderData['currency'],
		];

		$this->log( sprintf( 'Params to send: %s', wp_json_encode( $params, JSON_PRETTY_PRINT ) ), 'info' );

		$response = $this->api_request($params, $this->api_urls['generateOrder']);

		$this->log( sprintf( 'Response on generateOrder: %s, order_id: %d', wp_json_encode( $response, JSON_PRETTY_PRINT ), $order->get_id() ), 'info' );

		if (! $response || ! isset($response['data'][0]['invoice']) || $response['success'] !== true ) {
			$this->log( 'No valid response from ivendPay', 'error' );
			wc_add_notice( __( 'ivendPay not responding, please try again later.', 'ivendpay' ), 'error' );
			return;
		}

		$table = $this->create_ivendpay_request($orderData, $response['data'][0]);

		$order->set_transaction_id($table->payment_invoice);
		$order->save();

		return [
			'result'   => 'success',
			'redirect' => $response['data'][0]['payment_url'],
		];
	}

	public function orderData($invoice, $id) {
		require_once plugin_dir_path($this->file) . 'includes/class-ivend-pay-table.php';
		$payTable = new Ivend_Pay_Admin_Table();
		$row = $payTable->fetchDataByID((int)$id);

        if (! $row) {
            return false;
        }

        if ($row->payment_invoice === (string)$invoice &&
            ! empty($row->order_id)) {

            $order = wc_get_order( $row->order_id );
            if ($order) {
                return $row;
            }
        }

        return false;
	}

  protected function create_ivendpay_request($orderData, $data) {
    require_once plugin_dir_path($this->file) . 'includes/class-ivend-pay-table.php';
    $payTable = new Ivend_Pay_Admin_Table();

    $ticket = [
	    'currency'    => $orderData['currency'],
	    'price'       => $orderData['total'],
	    'user_name'   => $orderData['billing']['first_name'] . ' ' . $orderData['billing']['last_name'],
	    'user_email'  => $orderData['billing']['email'],
	    'user_phone'  => $orderData['billing']['phone'],
        'ip'          => $orderData['customer_ip_address']
    ];

      $order = [
        'id' => $orderData['id'],
        'key' => $orderData['order_key'],
      ];

      return $payTable->createData($ticket, $order, $data);
  }

	/**
	 * Route callback.
	 * Payment status is communicated via callback before success redirect.
	 *
	 * @since 1.0.0
	 */
	public function route_callback() {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ( ABSPATH . '/wp-admin/includes/file.php');
			WP_Filesystem();
		}

		$checkHeaderApiKey = false;
		$headers = apache_request_headers();

		foreach ($headers as $header => $value) {
		    if (mb_strtoupper($header) === 'X-API-KEY') {
                if ($value === $this->access_token) {
                    $checkHeaderApiKey = true;
                    break;
                }
            }
		}

		if (! $checkHeaderApiKey) {
			$this->log( sprintf( 'checkHeader from callback: %s', wp_json_encode($headers, JSON_PRETTY_PRINT) ), 'info' );

			wp_send_json([
				'hook' => 'error X-API-KEY'
			]);
		}

        $this->log( sprintf( 'post: %s', wp_json_encode($_POST, JSON_PRETTY_PRINT) ), 'info' );

		$findInvoice = false;
		$data = [];

		if (empty($_POST)) {
			wp_send_json([
				'hook' => 'error. Empty $_POST request'
			]);
		}

		foreach ($_POST as $key => $value) {
			$key = str_replace([':_', ',_'], [':', ','], $key);
			$post_data = json_decode(html_entity_decode(stripslashes($key)), true);

			if (! empty($post_data['payment_status']) && ! empty($post_data['invoice'])) {
				$findInvoice = true;
				break;
			}
		}

		if (! $findInvoice) {
            $this->log( 'Invoice not found from request.', 'info' );

			wp_send_json([
				'hook' => 'error. Invoice not found from request.'
			]);
		}

		$this->log( sprintf( 'Incoming callback, raw data posted: %s', wp_json_encode($post_data, JSON_PRETTY_PRINT) ), 'info' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        require_once plugin_dir_path($this->file) . 'includes/class-ivend-pay-table.php';
        $payTable = new Ivend_Pay_Admin_Table();
        $invoice = sanitize_text_field($post_data['invoice']);
        $row = $payTable->fetchDataByInvoice($invoice);

        if (! $row) {
          $this->log( 'Invoice not found from DB.', 'info' );

          wp_send_json([
            'hook' => 'error. Invoice not found from DB.'
          ]);
        }

		$this->log( sprintf( 'Find row from callback: %s', wp_json_encode($row, JSON_PRETTY_PRINT) ), 'info' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$shop_order_id = $row->order_id;
		$order         = wc_get_order( $shop_order_id );

		if ( ! $order ) {
			$this->log( sprintf( 'Cannot find order by shop_order_id %s.', $shop_order_id ), 'error' );

			wp_send_json([
				'hook' => 'error. Cannot find order'
			]);
		}

		if ( 'yes' !== $order->get_meta( 'ivendpay_status_received' ) ) {
			$token = $order->get_transaction_id();

			if ((string)$token !== $invoice) {
				$this->log( sprintf( 'Order invoice !== Callback invoice by shop_order_id %s.', $shop_order_id ), 'error' );

				wp_send_json([
					'hook' => 'error. Order invoice !== Callback invoice'
				]);
			}

			$remote_order = $this->get_remote_order_details( $token );

			$this->log( sprintf( 'Check remote order data %s', wp_json_encode( $remote_order, JSON_PRETTY_PRINT ) ), 'info' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if (!$remote_order['success'] || empty($remote_order['data'][0]['status'])) {
				$this->log( sprintf( 'Remote invoice not found. invoice: %s.', $token ), 'error' );

				wp_send_json([
					'hook' => 'error. Remote invoice not found'
				]);
			}

			$details = $remote_order['data'][0];
//            $remote_order['data'][1]['payment_info'][0] = [
//                'processed_at' => '11',
//                'hash' => '22',
//                'explorer_url' => '33',
//            ];
//
//            $details['status'] = 'PAID';

			$statusPayment = sanitize_text_field($details['status']);

			if ($statusPayment === 'TIMEOUT') {

				$payTable->updateItemRow($row->id, [
					'payment_status' => 'TIMEOUT',
					'updated_at'     => gmdate( 'Y-m-d H:i:s' ),
				]);

			} elseif ($statusPayment === 'CANCELED') {

                $payTable->updateItemRow($row->id, [
                    'payment_status' => 'CANCELED',
                    'updated_at'     => gmdate( 'Y-m-d H:i:s' ),
                ]);

            } elseif ($statusPayment === 'PAID') {

				$paymentInfo = @$remote_order['data'][1]['payment_info'][0];

				if (empty($paymentInfo)) {
					$this->log( sprintf( 'Not found data payment_info by invoice: %s.', $token ), 'error' );

					wp_send_json([
						'hook' => 'error. Not found data payment_info'
					]);
				}

				$payTable->updateItemRow($row->id, [
					'date_paid'      => sanitize_text_field($paymentInfo['processed_at']),
					'hash'           => sanitize_text_field($paymentInfo['hash']),
					'explorer_url'   => str_replace('_', '.', sanitize_text_field($paymentInfo['explorer_url'])),
					'payment_status' => 'PAID',
					'updated_at'     => gmdate( 'Y-m-d H:i:s' ),
				]);

				$this->log( 'Payment success!', 'info' );
				$this->payment_complete( $order );

				$order->update_meta_data( 'ivendpay_status_received', 'yes' );
				$order->update_meta_data( 'ivendpay_status', 'PAID' );

                $order->update_meta_data( 'ivendpay_hash', sanitize_text_field($paymentInfo['hash']) );
                $order->update_meta_data( 'ivendpay_explorer_url', str_replace('_', '.', sanitize_text_field($paymentInfo['explorer_url'])) );

                $order->save();

			} else {
				wp_send_json([
					'hook' => 'error. Status incorrect for save'
				]);
			}
		}

		status_header( 200 );
		exit;
	}

	/**
	 * Form and script with payment method
	 */
	public function payment_fields() {
        if ( $this->description ) {
			echo wpautop( wp_kses_post( $this->description ) );
		}

		$remoteCoins = $this->get_remote_list_conins();
        $listCoins = $remoteCoins['list'] ?? null;

		if (empty($listCoins)) {
            $siteUrlMedia = plugins_url('assets/media/', $this->file);

            $listCoins = [
				[
                  "id" => "usdt-bep20",
                  "name" => "USDT BEP-20",
                  "ticker_name" => "USDT",
                  "image" => "{$siteUrlMedia}usdt-bep20.png"
                ],
                [
                  "id" => "usdt-erc20",
                  "name" => "USDT ERC-20",
                  "ticker_name" => "USDT",
                  "image" => "{$siteUrlMedia}usdt-erc20.png"
                ],
                [
                  "id" => "usdt-poly20",
                  "name" => "USDT POLY-20",
                  "ticker_name" => "USDT",
                  "image" => "{$siteUrlMedia}usdt-poly20.png"
                ],
                [
                  "id" => "usdt-trc20",
                  "name" => "USDT TRC-20",
                  "ticker_name" => "USDT",
                  "image" => "{$siteUrlMedia}usdt-trc20.png"
                ],
			];
		}

		ob_start();
			$siteUrl = plugins_url('assets/media/', $this->file);
			do_action( 'woocommerce_credit_card_form_start', $this->id );
			include plugin_dir_path($this->file) . '/assets/partials/step2.php';
			do_action( 'woocommerce_credit_card_form_end', $this->id );
		$html = ob_get_clean();

		echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
        echo $html;
		echo '<div class="clear"></div></fieldset>';
	}

	/**
	 * Validate form before function process_payment
	 */
	public function validate_fields() {
		if (empty( $_POST['crypto-method']) ) {
			wc_add_notice('Please choose currency!', 'error');
			return false;
		}

		return true;
	}

	/**
	 * Get order from remote API.
	 *
	 * @since 1.0.0
	 * @param string $token Order token.
	 * @return array|false
	 */
	public function get_remote_order_details( $token ) {
		return $this->api_request([], $this->api_urls['orderDetails'] . $token, 'GET');
	}

	/**
	 * Get list coins from remote API.
	 *
	 * @since 1.0.0
	 * @return array|false
	 */
	public function get_remote_list_conins() {
		return $this->api_request([], $this->api_urls['listCoins'], 'GET');
	}

	/**
	 * Route success.
	 * When user is redirected back to shop after successful payment.
	 *
	 * @since 1.0.0
	 */
	public function route_success() {
		WC()->cart->empty_cart();
		wp_safe_redirect( $this->get_safe_success_url() );
		exit;
	}

	/**
	 * Route cancel.
	 * When user clicks cancel on payment page.
	 *
	 * @since 1.0.0
	 */
	public function route_cancel() {
		wc_add_notice( __( 'Payment was cancelled.', 'ivendpay' ), 'notice' );
		wp_safe_redirect( wc_get_page_permalink( 'cart' ) );
		exit;
	}

	/**
	 * Get success return url (order received page) in a safe manner.
	 *
	 * @since 1.0.0-
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function get_safe_success_url( $order = false ) {
		if ( $order && $order->get_user_id() === get_current_user_id() ) {
			return $this->get_return_url( $order );
		} else {
			return wc_get_endpoint_url( 'order-received', '', wc_get_page_permalink( 'checkout' ) );
		}
	}

	/**
	 * Payment complete.
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order Order object.
	 * @return bool
	 */
	public function payment_complete( $order ) {
		if ( $order->payment_complete() ) {
			$order->add_order_note( __( 'ivendPay payment complete.', 'ivendpay' ) );
			return true;
		}
		return false;
	}

	/**
	 * Get full route url.
	 *
	 * @since 1.0.0
	 * @param string $route Route.
	 * @return string
	 */
	public function get_route_url( $route ) {
		return sprintf('%s/wc-api/%s', get_bloginfo( 'url' ), $route);
	}

	public static function plugin_activation() {
        $file = str_replace('includes/class-gateway.php', 'ivendpay-payments.php', __FILE__);
		$self = self::get_instance($file);

		$self->settings['payment_page_id'] = null;
		update_option($self->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $self->id, $self->settings));

		$page_id = $self->get_option('payment_page_id');

		if ($page_id && get_post_type($page_id) != 'ivendpay_payment') {
			wp_delete_post($page_id);
		}

        self::createDatabaseTable();
	}

    protected static function createDatabaseTable() {
	    global $wpdb;

	    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	    $collate = $wpdb->get_charset_collate();
	    $prefix = $wpdb->base_prefix;

	    dbDelta("
			CREATE TABLE IF NOT EXISTS `{$prefix}payment_ivendpay_woo` (
			  `id` bigint unsigned NOT NULL AUTO_INCREMENT,

			  `payment_invoice` varchar(255) NOT NULL,
			  `payment_status` varchar(255) NOT NULL,
			  `payment_date` varchar(255) NOT NULL,
			  `payment_expired` varchar(255) NOT NULL,
			  `payment_price` varchar(255) NOT NULL,
			  `payment_currency` varchar(255) NOT NULL,
			  `payment_qrcode` varchar(255) NOT NULL,
			  `payment_address` varchar(255) NOT NULL,
			  `payment_string` varchar(255) NOT NULL,

			  `date_paid` varchar(255) DEFAULT NULL,
			  `hash` varchar(255) DEFAULT NULL,
			  `explorer_url` varchar(255) DEFAULT NULL,

			  `user_name` varchar(255) DEFAULT NULL,
			  `user_email` varchar(255) DEFAULT NULL,
			  `user_phone` varchar(255) DEFAULT NULL,

			  `payment_price_from` varchar(255) NOT NULL,
			  `payment_currency_from` varchar(255) NOT NULL,
			  `order_id` varchar(255) DEFAULT NULL,
			  `key` varchar(255) DEFAULT NULL,

			  `note` varchar(500) DEFAULT NULL,
			  `ip` varchar(255) DEFAULT NULL,

			  `created_at` datetime NOT NULL,
			  `updated_at` datetime NOT NULL,
			  PRIMARY KEY (`id`)
			) {$collate};
		");
    }

	public function get_post_id_from_guid($guid) {
		global $wpdb;

		return $wpdb->get_var($wpdb->prepare("SELECT id FROM $wpdb->posts WHERE guid=%s", $guid));
	}

    public function activationPluginInit() {
      $guid = home_url('/ivendpay_payment/ivendpay');

      $page_id = $this->get_post_id_from_guid($guid);

      if (! $page_id) {
          $page_data = [
              'post_status' => 'publish',
              'post_type' => 'ivendpay_payment',
              'post_title' => 'ivendPay',
              'post_content' => '<!-- wp:shortcode -->[ivendpay_payment_widget]<!-- /wp:shortcode -->',
              'comment_status' => 'closed',
              'guid' => $guid,
          ];
          $page_id = wp_insert_post($page_data);
      }

      $this->settings['payment_page_id'] = $page_id;
      update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings));
  }
}
