<?php
namespace ivendPay;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ivend_Pay_Admin_Table {
    private $table;

    private $db;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct() {
		global $wpdb;

	    $this->db = $wpdb;

        $this->table = $wpdb->base_prefix . 'payment_ivendpay_woo';
	}

    public function createData($ticket, $order, $data) {
	  $array = [
		  'payment_invoice'  => sanitize_text_field($data['invoice']),
		  'payment_status'   => sanitize_text_field($data['status']),
		  'payment_date'     => gmdate('Y-m-d H:i:s'),
		  'payment_expired'  => date('Y-m-d H:i:s', strtotime('+15minutes ' . gmdate('Y-m-d H:i:s'))),
		  'payment_price'    => sanitize_text_field($data['amount']),
		  'payment_currency' => sanitize_text_field($data['ticker_name']),
		  'payment_qrcode'   => sanitize_text_field($data['qrcode']),
		  'payment_address'  => sanitize_text_field($data['address']),
		  'payment_string'   => sanitize_text_field($data['string']),

		  'payment_price_from'    => $ticket['price'],
		  'payment_currency_from' => $ticket['currency'],
		  'user_name'             => $ticket['user_name'],
		  'user_email'            => $ticket['user_email'],
		  'user_phone'            => $ticket['user_phone'],
		  'ip'                    => $ticket['ip'],

		  'order_id' => $order['id'],
		  'key'      => $order['key'],

		  'created_at' => gmdate('Y-m-d H:i:s'),
		  'updated_at' => gmdate('Y-m-d H:i:s'),
	  ];

	  $this->db->insert($this->table, $array);

	  return $this->fetchDataByID($this->db->insert_id);
  }

	public function fetchDataByID($id) {
	    if (empty($id)) {
	      return false;
	    }

		return $this->db->get_row('SELECT * FROM `'. $this->table . '` WHERE `id` = '. $id .' LIMIT 1' );
	}

    public function fetchDataByInvoice($invoice) {
	    if (empty($invoice)) {
	      return false;
	    }

	    $invoice = sanitize_text_field($invoice);

		return $this->db->get_row('SELECT * FROM `'. $this->table . '` WHERE `payment_invoice` = "'. $invoice .'" LIMIT 1' );
	}

	public function deleteItemRow($id) {
		if (empty($id)) {
			return false;
		}

		$id = (int)sanitize_text_field($id);

		return $this->db->delete($this->table, [
			'id' => $id
		]);
	}

	public function updateItemRow($id, $data) {
		if (empty($id)) {
			return false;
		}

		$id = (int)sanitize_text_field($id);

		return $this->db->update($this->table, $data, [
			'id' => $id
		]);
	}
}
