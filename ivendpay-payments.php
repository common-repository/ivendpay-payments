<?php
/**
 * Plugin Name: ivendPay Payments
 * Description: Integrate the plugin to your website and start receiving payments in crypto.
 * Version:     1.0.7
 * Author:      ivendPay
 * Author URI:  https://ivendpay.com
 * License:     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 * Domain Path: /languages
 * Text Domain: ivendpay
 * WC requires at least: 3.0.0
 * WC tested up to: 8.7.0
 *
 * @package     ivendPay Payments
 * @author      ivendPay http://ivendpay.com/
 * @copyright   Copyright (c) ivendPay (info@ivendpay.com)
 * @since       1.0.7
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Composer autoload.
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Init plugin.
 *
 * @param string $file Must be __FILE__ from the root plugin file.
 * @param string $software_version Current software version of this plugin.
 *                                 Starts at version 1.0.0 and uses SemVer - https://semver.org
 */
\ivendPay\Plugin_Factory::instance( __FILE__, '1.0.7' );

register_activation_hook(__FILE__, [ 'ivendPay\Gateway', 'plugin_activation' ]);
