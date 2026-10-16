<?php
/** Run with: php tests/woocommerce-checkout.php. No WordPress installation needed. */
define( 'ABSPATH', __DIR__ );

function __( $value, $domain = '' ) { return $value; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function get_option( $name, $default = false ) { global $contacthub_test_options; return $contacthub_test_options[ $name ] ?? $default; }
function verify( $condition, $label ) { if ( ! $condition ) throw new RuntimeException( $label ); }

require __DIR__ . '/../contacthub/includes/class-contacthub-woocommerce.php';

$clean = ContactHub_WooCommerce::sanitize_settings(
	array(
		'checkout_layout'           => 'contacthub',
		'checkout_style'            => 'theme',
		'guest_account'             => '1',
		'newsletter_optin'          => '1',
		'marketing_optin'           => '',
		'newsletter_label'          => '<b>Newsletter</b>',
		'marketing_label'           => 'Offers',
		'guest_account_description' => ' Account information ',
	)
);
verify( 'contacthub' === $clean['checkout_layout'], 'ContactHub checkout can be enabled' );
verify( 'theme' === $clean['checkout_style'], 'Theme styles can be selected' );
verify( 1 === $clean['guest_account'], 'Optional account registration can be enabled' );
verify( 1 === $clean['newsletter_optin'] && 0 === $clean['marketing_optin'], 'Consent options remain independent' );
verify( 'Newsletter' === $clean['newsletter_label'], 'Checkout labels are sanitized' );

$invalid = ContactHub_WooCommerce::sanitize_settings( array( 'checkout_layout' => 'unknown', 'checkout_style' => 'unknown' ) );
verify( 'standard' === $invalid['checkout_layout'], 'Invalid checkout layouts fall back safely' );
verify( 'theme' === $invalid['checkout_style'], 'Invalid checkout styles fall back safely' );
verify( 0 === $invalid['guest_account'] && 0 === $invalid['newsletter_optin'] && 0 === $invalid['marketing_optin'], 'Missing checkboxes are disabled' );

$contacthub_test_options = array();
verify( true === ContactHub_WooCommerce::create_account_default_checked( true ), 'Inactive ContactHub checkout does not change the existing account default' );
$contacthub_test_options[ ContactHub_WooCommerce::OPTION ] = array( 'checkout_layout' => 'contacthub', 'guest_account' => 1 );
verify( false === ContactHub_WooCommerce::create_account_default_checked( true ), 'ContactHub guest account registration starts unchecked' );
verify( true === ContactHub_WooCommerce::checkout_registration_enabled( false ), 'ContactHub can offer optional guest account registration' );

echo "WooCommerce checkout settings tests passed.\n";
