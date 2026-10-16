<?php
/**
 * Plugin Name: ContactHub Basic – User-Friendly CRM for WordPress
 * Description: Free contact and company management for WordPress with a complete WooCommerce customer overview and optional Groundhogg compatibility.
 * Version: 0.14.0
 * Author: Kultur3 GmbH
 * Text Domain: contacthub
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.4
 */

defined( 'ABSPATH' ) || exit;

define( 'CONTACTHUB_VERSION', '0.14.0' );
define( 'CONTACTHUB_ASSET_VERSION', '0.14.0-20261016-1' );
define( 'CONTACTHUB_FILE', __FILE__ );
define( 'CONTACTHUB_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONTACTHUB_URL', plugin_dir_url( __FILE__ ) );

require_once CONTACTHUB_PATH . 'includes/class-contacthub-plugin.php';
require_once CONTACTHUB_PATH . 'includes/class-contacthub-modules.php';
require_once CONTACTHUB_PATH . 'includes/class-contacthub-schema.php';
require_once CONTACTHUB_PATH . 'includes/class-contacthub-store.php';
require_once CONTACTHUB_PATH . 'includes/class-contacthub-registration.php';
require_once CONTACTHUB_PATH . 'includes/class-contacthub-my-account.php';

add_action( 'plugins_loaded', array( 'ContactHub_Plugin', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactHub_Registration', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactHub_My_Account', 'boot' ), 31 );

register_activation_hook( __FILE__, array( 'ContactHub_Registration', 'activate' ) );

