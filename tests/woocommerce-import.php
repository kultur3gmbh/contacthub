<?php
/** Run with: php tests/woocommerce-import.php. Sanitized fixtures; no WordPress installation needed. */
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

class ContactHub_Schema {
	const VERSION = 'fixture';
	public static function status() { return array( 'total' => 1, 'count' => 1, 'version' => self::VERSION ); }
	public static function table_names() { return array( 'contacts' => 'fixture_chb_contacts' ); }
}

class WooCommerceImportDB {
	public function prepare( $sql, ...$args ) { return $sql; }
	public function get_row( $sql, $format = null ) { return null; }
}

class WooCommerceImportOrder {
	public function get_billing_email() { return 'order@example.test'; }
	public function get_customer_id() { return 0; }
	public function get_billing_first_name() { return 'Order'; }
	public function get_billing_last_name() { return 'Fixture'; }
	public function get_billing_phone() { return '+43 1 234567'; }
	public function get_billing_company() { return ''; }
	public function get_billing_address_1() { return 'Testgasse 1'; }
	public function get_billing_address_2() { return ''; }
	public function get_billing_postcode() { return '1010'; }
	public function get_billing_city() { return 'Wien'; }
	public function get_billing_state() { return 'W'; }
	public function get_billing_country() { return 'AT'; }
}

function __( $message, $domain = '' ) { return $message; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function current_time( $format ) { return '2026-10-16 12:00:00'; }
function get_role( $role ) { return 'customer' === $role ? (object) array( 'name' => $role ) : null; }
function wc_get_order_statuses() { return array( 'wc-completed' => 'Completed' ); }
function wc_get_orders( $args ) {
	$GLOBALS['order_args'][] = $args;
	if ( 'exception' === $GLOBALS['order_mode'] ) throw new RuntimeException( 'Unsafe source error' );
	if ( 'malformed' === $GLOBALS['order_mode'] ) return (object) array( 'max_num_pages' => 1 );
	return (object) array( 'orders' => array( new WooCommerceImportOrder() ), 'max_num_pages' => 1 );
}
function get_users( $args ) {
	$GLOBALS['user_args'] = $args;
	return array( (object) array( 'ID' => 7, 'user_email' => 'profile@example.test' ) );
}
function get_user_meta( $user_id, $key, $single ) {
	$GLOBALS['meta_calls'][] = $key;
	$values = array( 'first_name' => 'Profile', 'last_name' => 'Fixture' );
	return $values[ $key ] ?? '';
}
function verify( $condition, $label ) { if ( ! $condition ) throw new RuntimeException( $label ); }

$GLOBALS['wpdb'] = new WooCommerceImportDB();
$GLOBALS['order_mode'] = 'valid';
$GLOBALS['order_args'] = array();
$GLOBALS['meta_calls'] = array();
require __DIR__ . '/../contacthub/includes/class-contacthub-store.php';

$report = ContactHub_Store::import_woocommerce( true );
verify( ! is_wp_error( $report ) && 2 === $report['found'], 'Preview reads order and customer profile fixtures' );
verify( array( 'ID', 'user_email' ) === $GLOBALS['user_args']['fields'], 'User query requests only real WordPress user columns' );
verify( array( 'customer' ) === $GLOBALS['user_args']['role__in'], 'Available WooCommerce customer role is applied' );
verify( in_array( 'first_name', $GLOBALS['meta_calls'], true ) && in_array( 'last_name', $GLOBALS['meta_calls'], true ), 'Names are read from WordPress user metadata' );

foreach ( array( 'malformed', 'exception' ) as $mode ) {
	$GLOBALS['order_mode'] = $mode;
	$error = ContactHub_Store::import_woocommerce( true );
	verify( is_wp_error( $error ) && 'contacthub_woocommerce_import_read' === $error->get_error_code(), 'WooCommerce read failure is reported: ' . $mode );
	verify( false === strpos( $error->get_error_message(), 'Unsafe' ), 'Raw source errors are not disclosed' );
}

echo "WooCommerce customer import tests passed.\n";
