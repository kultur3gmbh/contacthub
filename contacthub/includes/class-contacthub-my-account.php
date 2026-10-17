<?php

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the WooCommerce account experience compact and ContactHub-aware without
 * replacing WooCommerce templates in the active theme.
 */
final class ContactHub_My_Account {
	public static function boot() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_filter( 'pre_option_woocommerce_enable_myaccount_registration', array( __CLASS__, 'hide_native_registration' ) );
		add_action( 'woocommerce_after_customer_login_form', array( __CLASS__, 'registration_disclosure' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu' ), 90 );
		add_filter( 'woocommerce_endpoint_edit-account_title', array( __CLASS__, 'profile_title' ) );
		add_filter( 'woocommerce_endpoint_contacthub-password_title', array( __CLASS__, 'password_title' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'dashboard_overview' ), 5 );
		add_action( 'wp_loaded', array( __CLASS__, 'replace_account_callbacks' ) );
		add_action( 'woocommerce_account_contacthub-password_endpoint', array( __CLASS__, 'password_form' ) );
		add_action( 'init', array( __CLASS__, 'register_password_endpoint' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_routes' ), 99 );
		add_action( 'admin_post_contacthub_save_profile', array( __CLASS__, 'save_profile' ) );
		add_action( 'admin_post_contacthub_save_addresses', array( __CLASS__, 'save_addresses' ) );
		add_action( 'wp_ajax_contacthub_save_account_profile', array( __CLASS__, 'save_profile_ajax' ) );
		add_action( 'wp_ajax_contacthub_save_account_addresses', array( __CLASS__, 'save_addresses_ajax' ) );
		add_action( 'admin_post_contacthub_change_password', array( __CLASS__, 'change_password' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function hide_native_registration( $value ) {
		if ( ! is_admin() && function_exists( 'is_account_page' ) && is_account_page() && ! is_user_logged_in() ) {
			return 'no';
		}
		return false;
	}

	public static function replace_account_callbacks() {
		remove_action( 'woocommerce_account_edit-account_endpoint', 'woocommerce_account_edit_account' );
		remove_action( 'woocommerce_account_edit-address_endpoint', 'woocommerce_account_edit_address' );
		add_action( 'woocommerce_account_edit-account_endpoint', array( __CLASS__, 'profile_form' ) );
		add_action( 'woocommerce_account_edit-address_endpoint', array( __CLASS__, 'address_form' ) );
	}

	public static function assets() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		wp_enqueue_style( 'contacthub-registration', CONTACTHUB_URL . 'assets/contacthub-registration.css', array(), CONTACTHUB_ASSET_VERSION );
		wp_enqueue_script( 'contacthub-registration', CONTACTHUB_URL . 'assets/contacthub-registration.js', array(), CONTACTHUB_ASSET_VERSION, true );
		wp_localize_script(
			'contacthub-registration',
			'CONTACTHUB_ACCOUNT',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'contacthub_account_ajax' ),
				'vatNonce' => wp_create_nonce( 'contacthub_registration_validate_vat' ),
				'i18n'     => array(
					'saving'             => __( 'Saving…', 'contacthub' ),
					'saveFailed'          => __( 'The changes could not be saved.', 'contacthub' ),
					'checkingVat'         => __( 'Checking EU VIES…', 'contacthub' ),
					'vatValidationFailed' => __( 'VAT ID validation could not be completed.', 'contacthub' ),
				),
			)
		);
	}

	public static function registration_disclosure() {
		if ( is_user_logged_in() ) {
			return;
		}
		$requested_type = isset( $_GET['contacthub_registration_type'] ) ? sanitize_key( wp_unslash( $_GET['contacthub_registration_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display choice only.
		$is_open        = in_array( $requested_type, array( 'customer', 'business' ), true );
		$form = do_shortcode( '[contacthub_registration]' );
		if ( '' === trim( $form ) ) {
			return;
		}
		echo '<section class="contacthub-account-registration"><h2>' . esc_html__( 'New here?', 'contacthub' ) . '</h2><p>' . esc_html__( 'Create a customer account to manage your profile, addresses, orders, and bookings.', 'contacthub' ) . '</p>';
		echo '<button type="button" class="button contacthub-registration-toggle" aria-expanded="' . ( $is_open ? 'true' : 'false' ) . '" aria-controls="contacthub-account-registration-form" data-open-label="' . esc_attr__( 'Register now', 'contacthub' ) . '" data-close-label="' . esc_attr__( 'Close registration', 'contacthub' ) . '">' . esc_html( $is_open ? __( 'Close registration', 'contacthub' ) : __( 'Register now', 'contacthub' ) ) . '</button>';
		echo '<div id="contacthub-account-registration-form" class="contacthub-account-registration-form"' . ( $is_open ? '' : ' hidden' ) . '>' . $form . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ContactHub shortcode returns escaped form markup.
	}

	public static function account_menu( $items ) {
		$ordered = array();
		$known = array( 'dashboard', 'edit-account', 'edit-address', 'payment-methods', 'customer-logout' );
		if ( isset( $items['dashboard'] ) ) {
			$ordered['dashboard'] = $items['dashboard'];
		}
		if ( isset( $items['edit-account'] ) ) {
			$ordered['edit-account'] = __( 'My profile', 'contacthub' );
		}
		$ordered['contacthub-password'] = __( 'Change password', 'contacthub' );
		if ( isset( $items['edit-address'] ) ) {
			$ordered['edit-address'] = $items['edit-address'];
		}
		if ( isset( $items['payment-methods'] ) ) {
			$ordered['payment-methods'] = $items['payment-methods'];
		}

		// Keep endpoints contributed by extensions (for example Custom Affiliate)
		// in their original order, immediately before logout.
		foreach ( $items as $endpoint => $label ) {
			if ( in_array( $endpoint, $known, true ) || in_array( $endpoint, array( 'orders', 'downloads' ), true ) ) {
				continue;
			}
			$ordered[ $endpoint ] = $label;
		}
		if ( isset( $items['customer-logout'] ) ) {
			$ordered['customer-logout'] = $items['customer-logout'];
		}
		return $ordered;
	}

	public static function profile_title() {
		return __( 'My profile', 'contacthub' );
	}

	public static function password_title() {
		return __( 'Change password', 'contacthub' );
	}

	public static function register_password_endpoint() {
		add_rewrite_endpoint( 'contacthub-password', EP_ROOT | EP_PAGES );
	}

	public static function maybe_flush_routes() {
		if ( '1' !== get_option( 'contacthub_account_routes_version' ) ) {
			flush_rewrite_rules( false );
			update_option( 'contacthub_account_routes_version', '1', false );
		}
	}

	public static function body_classes( $classes ) {
		$general = (array) get_option( 'contacthub_general_settings', array() );
		if ( function_exists( 'is_account_page' ) && is_account_page() && ! empty( $general['theme_styles'] ) ) {
			$classes[] = 'contacthub-account-theme-styles';
		}
		if ( is_user_logged_in() && function_exists( 'is_account_page' ) && is_account_page() && function_exists( 'is_wc_endpoint_url' ) && ! is_wc_endpoint_url() ) {
			$classes[] = 'contacthub-account-dashboard';
		}
		return $classes;
	}

	public static function dashboard_overview() {
		$user_id = get_current_user_id();
		$order_count = function_exists( 'wc_get_customer_order_count' ) ? wc_get_customer_order_count( $user_id ) : 0;
		$total_spent = function_exists( 'wc_get_customer_total_spent' ) ? wc_get_customer_total_spent( $user_id ) : 0;
		$event_count = self::attended_event_count( $user_id );

		$profile_url = wc_get_endpoint_url( 'edit-account', '', wc_get_page_permalink( 'myaccount' ) );
		$address_url = wc_get_endpoint_url( 'edit-address', '', wc_get_page_permalink( 'myaccount' ) );
		$payment_url = wc_get_endpoint_url( 'payment-methods', '', wc_get_page_permalink( 'myaccount' ) );
		$password_url = wc_get_endpoint_url( 'contacthub-password', '', wc_get_page_permalink( 'myaccount' ) );
		echo '<section class="contacthub-account-overview"><h2>' . esc_html__( 'Overview', 'contacthub' ) . '</h2>';
		echo '<p class="contacthub-dashboard-intro">' . sprintf( wp_kses( __( 'From your dashboard you can manage your <a href="%1$s">profile</a>, <a href="%2$s">addresses</a>, <a href="%3$s">payment methods</a>, and <a href="%4$s">password</a>.', 'contacthub' ), array( 'a' => array( 'href' => array() ) ) ), esc_url( $profile_url ), esc_url( $address_url ), esc_url( $payment_url ), esc_url( $password_url ) ) . '</p><div class="contacthub-account-facts">';
		echo '<div><span>' . esc_html__( 'Orders', 'contacthub' ) . '</span><strong>' . esc_html( number_format_i18n( $order_count ) ) . '</strong></div>';
		echo '<div><span>' . esc_html__( 'Order total', 'contacthub' ) . '</span><strong>' . wp_kses_post( wc_price( $total_spent ) ) . '</strong></div>';
		if ( null !== $event_count ) {
			echo '<div><span>' . esc_html__( 'Attended events', 'contacthub' ) . '</span><strong>' . esc_html( number_format_i18n( $event_count ) ) . '</strong></div>';
		}
		echo '</div>';
		self::recent_orders( $user_id );
		echo '</section>';
	}

	private static function recent_orders( $user_id ) {
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 5,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		echo '<div class="contacthub-recent-orders"><h3>' . esc_html__( 'Recent orders', 'contacthub' ) . '</h3>';
		if ( ! $orders ) {
			echo '<p>' . esc_html__( 'No orders have been placed yet.', 'contacthub' ) . '</p></div>';
			return;
		}
		echo '<div class="contacthub-account-order-list">';
		foreach ( $orders as $order ) {
			$url = $order->get_view_order_url();
			$date = $order->get_date_created();
			echo '<a href="' . esc_url( $url ) . '"><span><strong>' . esc_html( sprintf( __( 'Order #%s', 'contacthub' ), $order->get_order_number() ) ) . '</strong><small>' . esc_html( $date ? wc_format_datetime( $date ) : '—' ) . '</small></span><span>' . wp_kses_post( $order->get_formatted_order_total() ) . '<small>' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</small></span></a>';
		}
		echo '</div></div>';
	}

	private static function attended_event_count( $user_id ) {
		return apply_filters( 'contacthub_account_attended_event_count', null, absint( $user_id ) );
	}

	public static function profile_form() {
		$user = wp_get_current_user();
		$country = get_user_meta( $user->ID, 'contacthub_profile_country', true );
		$country = $country ?: 'AT';
		echo '<section class="contacthub-account-section contacthub-profile-section"><h2>' . esc_html__( 'My profile', 'contacthub' ) . '</h2><form class="contacthub-account-form contacthub-account-ajax-form" data-ajax-action="contacthub_save_account_profile" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_save_profile">';
		wp_nonce_field( 'contacthub_save_profile' );
		echo '<div class="contacthub-account-grid">';
		self::account_input( 'first_name', __( 'First name', 'contacthub' ), $user->first_name, true, 'given-name' );
		self::account_input( 'last_name', __( 'Last name', 'contacthub' ), $user->last_name, true, 'family-name' );
		self::account_input( 'contacthub_title_prefix', __( 'Prefix title', 'contacthub' ), get_user_meta( $user->ID, 'contacthub_title_prefix', true ), false, 'honorific-prefix' );
		self::account_input( 'contacthub_title_suffix', __( 'Suffix title', 'contacthub' ), get_user_meta( $user->ID, 'contacthub_title_suffix', true ), false, 'honorific-suffix' );
		echo '<label class="contacthub-account-field contacthub-account-field-wide"><span>' . esc_html__( 'Phone', 'contacthub' ) . '</span>' . self::phone_input( get_user_meta( $user->ID, 'contacthub_profile_phone', true ) ?: get_user_meta( $user->ID, 'billing_phone', true ) ) . '</label>';
		self::account_input( 'billing_company', __( 'Company (optional)', 'contacthub' ), get_user_meta( $user->ID, 'billing_company', true ), false, 'organization', 'contacthub-account-field-wide' );
		echo '<div class="contacthub-account-subheading"><h3>' . esc_html__( 'Profile address', 'contacthub' ) . '</h3></div>';
		self::account_input( 'profile_address_1', __( 'Street and house number', 'contacthub' ), get_user_meta( $user->ID, 'contacthub_profile_address_1', true ), false, 'address-line1', 'contacthub-account-field-wide' );
		self::account_input( 'profile_address_2', __( 'Address line 2', 'contacthub' ), get_user_meta( $user->ID, 'contacthub_profile_address_2', true ), false, 'address-line2', 'contacthub-account-field-wide' );
		self::account_input( 'profile_postcode', __( 'Postal code', 'contacthub' ), get_user_meta( $user->ID, 'contacthub_profile_postcode', true ), false, 'postal-code' );
		self::account_input( 'profile_city', __( 'City', 'contacthub' ), get_user_meta( $user->ID, 'contacthub_profile_city', true ), false, 'address-level2' );
		self::account_input( 'profile_state', __( 'State / Province', 'contacthub' ), get_user_meta( $user->ID, 'contacthub_profile_state', true ), false, 'address-level1' );
		self::country_input( 'profile_country', __( 'Country', 'contacthub' ), $country );
		echo '</div><button type="submit" class="button contacthub-account-save">' . esc_html__( 'Save profile', 'contacthub' ) . '</button><div class="contacthub-account-message" aria-live="polite"></div></form></section>';
	}

	public static function password_form() {
		echo '<section class="contacthub-account-section contacthub-password-section"><h2>' . esc_html__( 'Change password', 'contacthub' ) . '</h2><form class="contacthub-account-form contacthub-password-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_change_password">';
		wp_nonce_field( 'contacthub_change_password' );
		self::account_input( 'password_current', __( 'Current password', 'contacthub' ), '', true, 'current-password', 'contacthub-account-field-wide', 'password' );
		self::account_input( 'password_1', __( 'New password', 'contacthub' ), '', true, 'new-password', 'contacthub-account-field-wide', 'password' );
		self::account_input( 'password_2', __( 'Confirm new password', 'contacthub' ), '', true, 'new-password', 'contacthub-account-field-wide', 'password' );
		echo '<p class="contacthub-form-help">' . esc_html__( 'The new password must contain at least 10 characters.', 'contacthub' ) . '</p><button type="submit" class="button contacthub-account-save">' . esc_html__( 'Save new password', 'contacthub' ) . '</button></form></section>';
	}

	public static function address_form() {
		$user_id = get_current_user_id();
		$customer = new WC_Customer( $user_id );
		$different = (bool) get_user_meta( $user_id, 'contacthub_shipping_different', true );
		$profile = self::profile_address( $user_id );
		$vat = get_user_meta( $user_id, 'billing_vat', true );
		$vat = $vat ?: get_user_meta( $user_id, 'b2b_uid', true );
		$vat_verified = $vat && hash_equals( (string) get_user_meta( $user_id, 'contacthub_vat_verified_hash', true ), hash( 'sha256', self::normalize_vat( $vat ) ) );
		echo '<section class="contacthub-account-section contacthub-address-section"><h2>' . esc_html__( 'Addresses', 'contacthub' ) . '</h2><form class="contacthub-account-form contacthub-address-account-form contacthub-account-ajax-form" data-ajax-action="contacthub_save_account_addresses" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_save_addresses">';
		wp_nonce_field( 'contacthub_save_addresses' );
		echo '<div class="contacthub-address-panel"><div class="contacthub-account-subheading"><h3>' . esc_html__( 'Billing address', 'contacthub' ) . '</h3>';
		if ( self::has_profile_address( $profile ) ) {
			echo '<button type="button" class="button contacthub-copy-profile-address" data-profile="' . esc_attr( wp_json_encode( $profile ) ) . '">' . esc_html__( 'Use address from my profile', 'contacthub' ) . '</button>';
		}
		echo '</div><div class="contacthub-account-grid">';
		self::address_fields( 'billing', $customer );
		echo '</div></div>';
		$vat_button_label = __( 'Validate VAT ID', 'contacthub' );
		$vat_success_label = __( 'VAT ID successfully validated', 'contacthub' );
		echo '<label class="contacthub-account-field contacthub-account-field-wide contacthub-vat-field"><span>' . esc_html__( 'VAT ID', 'contacthub' ) . '</span><span class="contacthub-vat-controls"><input type="text" name="vat_id" value="' . esc_attr( $vat ) . '" autocomplete="off" data-verified="' . ( $vat_verified ? '1' : '0' ) . '"><button type="button" class="contacthub-vat-button' . ( $vat_verified ? ' is-valid' : '' ) . '" data-default-label="' . esc_attr( $vat_button_label ) . '" data-success-label="' . esc_attr( $vat_success_label ) . '" aria-label="' . esc_attr( $vat_verified ? $vat_success_label : $vat_button_label ) . '">' . ( $vat_verified ? '✓' : esc_html( $vat_button_label ) ) . '</button></span><span class="contacthub-vat-message" aria-live="polite"></span></label>';
		echo '<label class="contacthub-shipping-toggle"><input type="checkbox" name="shipping_different" value="1"' . checked( $different, true, false ) . '> <span>' . esc_html__( 'The shipping address differs from the billing address.', 'contacthub' ) . '</span></label>';
		echo '<div class="contacthub-address-panel contacthub-shipping-account-fields"' . ( $different ? '' : ' hidden' ) . '><div class="contacthub-account-subheading"><h3>' . esc_html__( 'Shipping address', 'contacthub' ) . '</h3></div><div class="contacthub-account-grid">';
		self::address_fields( 'shipping', $customer, $different );
		echo '</div></div><button type="submit" class="button contacthub-account-save">' . esc_html__( 'Save addresses', 'contacthub' ) . '</button><div class="contacthub-account-message" aria-live="polite"></div></form></section>';
	}

	private static function account_input( $name, $label, $value, $required = false, $autocomplete = '', $class = '', $type = 'text' ) {
		echo '<label class="contacthub-account-field ' . esc_attr( $class ) . '"><span>' . esc_html( $label ) . ( $required ? ' <em>*</em>' : '' ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . ( $autocomplete ? ' autocomplete="' . esc_attr( $autocomplete ) . '"' : '' ) . '></label>';
	}

	private static function country_input( $name, $label, $selected, $class = '' ) {
		$countries = ContactHub_Plugin::ordered_countries( WC()->countries->get_countries() );
		echo '<label class="contacthub-account-field ' . esc_attr( $class ) . '"><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '">';
		foreach ( $countries as $code => $country ) {
			if ( '__separator__' === $code ) { echo '<option value="" disabled>' . esc_html( $country ) . '</option>'; continue; }
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $selected, $code, false ) . '>' . esc_html( $country ) . '</option>';
		}
		echo '</select></label>';
	}

	private static function address_fields( $prefix, $customer, $required = true ) {
		$get = static function ( $key ) use ( $prefix, $customer ) {
			$method = 'get_' . $prefix . '_' . $key;
			return is_callable( array( $customer, $method ) ) ? $customer->{$method}() : '';
		};
		self::account_input( $prefix . '_company', __( 'Company (optional)', 'contacthub' ), $get( 'company' ), false, 'organization', 'contacthub-account-field-wide' );
		self::account_input( $prefix . '_first_name', __( 'First name', 'contacthub' ), $get( 'first_name' ), $required, 'given-name' );
		self::account_input( $prefix . '_last_name', __( 'Last name', 'contacthub' ), $get( 'last_name' ), $required, 'family-name' );
		self::account_input( $prefix . '_address_1', __( 'Street and house number', 'contacthub' ), $get( 'address_1' ), $required, 'address-line1', 'contacthub-account-field-wide' );
		self::account_input( $prefix . '_address_2', __( 'Address line 2', 'contacthub' ), $get( 'address_2' ), false, 'address-line2', 'contacthub-account-field-wide' );
		self::account_input( $prefix . '_postcode', __( 'Postal code', 'contacthub' ), $get( 'postcode' ), $required, 'postal-code' );
		self::account_input( $prefix . '_city', __( 'City', 'contacthub' ), $get( 'city' ), $required, 'address-level2' );
		self::account_input( $prefix . '_state', __( 'State / Province', 'contacthub' ), $get( 'state' ), false, 'address-level1' );
		self::country_input( $prefix . '_country', __( 'Country', 'contacthub' ), $get( 'country' ) ?: 'AT' );
	}

	private static function profile_address( $user_id ) {
		$address = array();
		foreach ( array( 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' ) as $key ) {
			$address[ $key ] = get_user_meta( $user_id, 'contacthub_profile_' . $key, true );
		}
		$address['first_name'] = get_user_meta( $user_id, 'first_name', true );
		$address['last_name'] = get_user_meta( $user_id, 'last_name', true );
		$address['company'] = get_user_meta( $user_id, 'billing_company', true );
		return $address;
	}

	private static function has_profile_address( $address ) {
		return ! empty( $address['address_1'] ) && ! empty( $address['postcode'] ) && ! empty( $address['city'] ) && ! empty( $address['country'] );
	}

	public static function save_profile() {
		self::guard_account_action( 'contacthub_save_profile' );
		$result = self::persist_profile();
		if ( is_wp_error( $result ) ) {
			self::account_redirect( wc_customer_edit_account_url(), $result->get_error_message(), 'error' );
		}
		self::account_redirect( wc_customer_edit_account_url(), __( 'Your profile has been saved.', 'contacthub' ) );
	}

	public static function save_profile_ajax() {
		self::guard_account_ajax();
		$result = self::persist_profile();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'message' => __( 'Your profile has been saved.', 'contacthub' ) ) );
	}

	private static function persist_profile() {
		$user_id = get_current_user_id();
		$first_name = self::posted( 'first_name' );
		$last_name = self::posted( 'last_name' );
		if ( '' === $first_name || '' === $last_name ) {
			return new WP_Error( 'contacthub_profile_name', __( 'Please enter your first and last name.', 'contacthub' ) );
		}
		$result = wp_update_user( array( 'ID' => $user_id, 'first_name' => $first_name, 'last_name' => $last_name ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$map = array(
			'contacthub_title_prefix' => 'contacthub_title_prefix',
			'contacthub_title_suffix' => 'contacthub_title_suffix',
			'billing_company'         => 'billing_company',
			'profile_address_1'       => 'contacthub_profile_address_1',
			'profile_address_2'       => 'contacthub_profile_address_2',
			'profile_postcode'        => 'contacthub_profile_postcode',
			'profile_city'            => 'contacthub_profile_city',
			'profile_state'           => 'contacthub_profile_state',
			'profile_country'         => 'contacthub_profile_country',
		);
		foreach ( $map as $post_key => $meta_key ) {
			$value = self::posted( $post_key );
			if ( 'profile_country' === $post_key && ! isset( WC()->countries->get_countries()[ strtoupper( $value ) ] ) ) {
				$value = 'AT';
			}
			update_user_meta( $user_id, $meta_key, $value );
		}
		$phone = self::normalize_profile_phone( self::posted( 'phone_prefix' ), self::posted( 'phone_national' ) );
		update_user_meta( $user_id, 'contacthub_profile_phone', $phone );
		update_user_meta( $user_id, 'billing_phone', $phone );
		self::sync_standalone_company( $user_id, self::posted( 'billing_company' ) );
		return true;
	}

	public static function save_addresses() {
		self::guard_account_action( 'contacthub_save_addresses' );
		$result = self::persist_addresses();
		$url = wc_get_endpoint_url( 'edit-address', '', wc_get_page_permalink( 'myaccount' ) );
		if ( is_wp_error( $result ) ) {
			self::account_redirect( $url, $result->get_error_message(), 'error' );
		}
		self::account_redirect( $url, __( 'Your addresses have been saved.', 'contacthub' ) );
	}

	public static function save_addresses_ajax() {
		self::guard_account_ajax();
		$result = self::persist_addresses();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'message' => __( 'Your addresses have been saved.', 'contacthub' ), 'vatVerified' => ! empty( $result['vat_verified'] ) ) );
	}

	private static function persist_addresses() {
		$user_id = get_current_user_id();
		$customer = new WC_Customer( $user_id );
		$different = ! empty( $_POST['shipping_different'] );
		$countries = WC()->countries->get_countries();
		$billing = self::posted_address( 'billing' );
		if ( ! self::valid_required_address( $billing ) || ! isset( $countries[ $billing['country'] ] ) ) {
			return new WP_Error( 'contacthub_billing_address', __( 'Please complete the billing address.', 'contacthub' ) );
		}
		$shipping = $different ? self::posted_address( 'shipping' ) : $billing;
		if ( $different && ( ! self::valid_required_address( $shipping ) || ! isset( $countries[ $shipping['country'] ] ) ) ) {
			return new WP_Error( 'contacthub_shipping_address', __( 'Please complete the separate shipping address.', 'contacthub' ) );
		}
		$vat = self::normalize_vat( self::posted( 'vat_id' ) );
		$vat_verified = false;
		if ( $vat ) {
			$stored_hash = (string) get_user_meta( $user_id, 'contacthub_vat_verified_hash', true );
			$vat_verified = $stored_hash && hash_equals( $stored_hash, hash( 'sha256', $vat ) );
			if ( ! $vat_verified ) {
				$vat_result = ContactHub_Registration::check_vat_vies( $vat );
				if ( is_wp_error( $vat_result ) ) {
					return $vat_result;
				}
				if ( ! $vat_result ) {
					return new WP_Error( 'contacthub_vat_invalid', __( 'The VAT ID is not valid according to EU VIES.', 'contacthub' ) );
				}
				$vat_verified = true;
			}
		}
		try {
			foreach ( array( 'billing' => $billing, 'shipping' => $shipping ) as $prefix => $address ) {
				foreach ( $address as $key => $value ) {
					$setter = 'set_' . $prefix . '_' . $key;
					if ( is_callable( array( $customer, $setter ) ) ) {
						$customer->{$setter}( $value );
					}
				}
			}
			$customer->set_billing_email( wp_get_current_user()->user_email );
			$profile_phone = get_user_meta( $user_id, 'contacthub_profile_phone', true );
			if ( $profile_phone ) {
				$customer->set_billing_phone( $profile_phone );
			}
			$customer->save();
			foreach ( array( 'billing' => $billing, 'shipping' => $shipping ) as $prefix => $address ) {
				foreach ( $address as $key => $value ) {
					update_user_meta( $user_id, $prefix . '_' . $key, $value );
				}
			}
			update_user_meta( $user_id, 'contacthub_shipping_different', $different ? 1 : 0 );
		} catch ( Throwable $exception ) {
			return new WP_Error( 'contacthub_address_save', $exception->getMessage() );
		}
		foreach ( array( 'billing_vat', 'b2b_uid' ) as $vat_key ) {
			update_user_meta( $user_id, $vat_key, $vat );
		}
		if ( $vat_verified ) {
			update_user_meta( $user_id, 'contacthub_vat_verified_hash', hash( 'sha256', $vat ) );
			update_user_meta( $user_id, 'contacthub_vat_verified_at', current_time( 'mysql' ) );
		} else {
			delete_user_meta( $user_id, 'contacthub_vat_verified_hash' );
			delete_user_meta( $user_id, 'contacthub_vat_verified_at' );
		}

		// Do not report success until WooCommerce returns every persisted value.
		clean_user_cache( $user_id );
		wp_cache_delete( $user_id, 'user_meta' );
		$stored_customer = new WC_Customer( $user_id );
		$mismatches = array();
		foreach ( array( 'billing' => $billing, 'shipping' => $shipping ) as $prefix => $expected_address ) {
			foreach ( $expected_address as $key => $expected_value ) {
				$getter = 'get_' . $prefix . '_' . $key;
				$stored_value = is_callable( array( $stored_customer, $getter ) ) ? (string) $stored_customer->{$getter}( 'edit' ) : (string) get_user_meta( $user_id, $prefix . '_' . $key, true );
				if ( trim( $stored_value ) !== trim( (string) $expected_value ) ) {
					$mismatches[] = $prefix . '_' . $key;
				}
			}
		}
		if ( $mismatches ) {
			return new WP_Error(
				'contacthub_address_not_persisted',
				sprintf(
					__( 'WooCommerce did not persist all address fields. Please try again. Affected fields: %s', 'contacthub' ),
					implode( ', ', array_map( 'sanitize_text_field', $mismatches ) )
				)
			);
		}
		self::sync_standalone_company( $user_id, $billing['company'] );

		return array( 'vat_verified' => $vat_verified, 'billing' => $billing, 'shipping' => $shipping );
	}

	/**
	 * Mirrors a freely entered billing company into standalone ContactHub without
	 * changing WooCommerce, Groundhogg, or any historical source record.
	 */
	private static function sync_standalone_company( $user_id, $company_name ) {
		if ( ! ContactHub_Store::standalone() ) {
			return;
		}
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user ) {
			return;
		}
		ContactHub_Store::link_contact_company_name( $user->user_email, $company_name );
	}

	public static function change_password() {
		self::guard_account_action( 'contacthub_change_password' );
		$url = wc_get_endpoint_url( 'contacthub-password', '', wc_get_page_permalink( 'myaccount' ) );
		$user = wp_get_current_user();
		$current = isset( $_POST['password_current'] ) ? (string) wp_unslash( $_POST['password_current'] ) : '';
		$password = isset( $_POST['password_1'] ) ? (string) wp_unslash( $_POST['password_1'] ) : '';
		$confirmation = isset( $_POST['password_2'] ) ? (string) wp_unslash( $_POST['password_2'] ) : '';
		if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
			self::account_redirect( $url, __( 'The current password is incorrect.', 'contacthub' ), 'error' );
		}
		if ( strlen( $password ) < 10 ) {
			self::account_redirect( $url, __( 'The new password must contain at least 10 characters.', 'contacthub' ), 'error' );
		}
		if ( $password !== $confirmation ) {
			self::account_redirect( $url, __( 'The new passwords do not match.', 'contacthub' ), 'error' );
		}
		wp_set_password( $password, $user->ID );
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		self::account_redirect( $url, __( 'Your password has been changed.', 'contacthub' ) );
	}

	private static function posted_address( $prefix ) {
		$result = array();
		foreach ( array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' ) as $key ) {
			$result[ $key ] = self::posted( $prefix . '_' . $key );
		}
		$result['country'] = strtoupper( $result['country'] );
		return $result;
	}

	private static function valid_required_address( $address ) {
		foreach ( array( 'first_name', 'last_name', 'address_1', 'postcode', 'city', 'country' ) as $key ) {
			if ( empty( $address[ $key ] ) ) {
				return false;
			}
		}
		return true;
	}

	private static function guard_account_action( $action ) {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}
		check_admin_referer( $action );
	}

	private static function guard_account_ajax() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Please sign in again.', 'contacthub' ) ), 401 );
		}
		check_ajax_referer( 'contacthub_account_ajax', 'nonce' );
	}

	private static function account_redirect( $url, $message, $type = 'success' ) {
		wc_add_notice( $message, $type );
		wp_safe_redirect( $url );
		exit;
	}

	private static function posted( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	private static function normalize_profile_phone( $prefix, $national ) {
		$allowed = wp_list_pluck( self::phone_countries(), 'code' );
		$prefix = in_array( $prefix, $allowed, true ) ? $prefix : '+43';
		$national = preg_replace( '/\D/', '', $national );
		if ( '+39' !== $prefix ) {
			$national = ltrim( $national, '0' );
		}
		return $national ? $prefix . $national : '';
	}

	private static function normalize_vat( $vat ) {
		return strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) $vat ) );
	}

	private static function phone_input( $phone ) {
		$phone = preg_replace( '/[^0-9+]/', '', (string) $phone );
		if ( 0 === strpos( $phone, '00' ) ) {
			$phone = '+' . substr( $phone, 2 );
		}
		$prefix = '+43';
		$countries = self::phone_countries();
		usort( $countries, static function ( $left, $right ) { return strlen( $right['code'] ) <=> strlen( $left['code'] ); } );
		foreach ( $countries as $country ) {
			if ( 0 === strpos( $phone, $country['code'] ) ) {
				$prefix = $country['code'];
				break;
			}
		}
		$national = 0 === strpos( $phone, $prefix ) ? substr( $phone, strlen( $prefix ) ) : $phone;
		$html = '<span class="contacthub-profile-phone"><select name="phone_prefix" class="contacthub-phone-prefix" aria-label="' . esc_attr__( 'Country calling code', 'contacthub' ) . '">';
		foreach ( self::phone_countries() as $country ) {
			$html .= '<option value="' . esc_attr( $country['code'] ) . '"' . selected( $prefix, $country['code'], false ) . '>' . esc_html( $country['label'] . ' ' . $country['code'] ) . '</option>';
		}
		return $html . '</select><input type="tel" name="phone_national" class="contacthub-phone-national" value="' . esc_attr( $national ) . '" autocomplete="tel-national" placeholder="' . esc_attr__( 'Phone number', 'contacthub' ) . '"></span>';
	}

	private static function phone_countries() {
		$codes = array( 'AT' => '+43', 'DE' => '+49', 'CH' => '+41', 'IT' => '+39', 'AL' => '+355', 'AD' => '+376', 'BE' => '+32', 'BA' => '+387', 'BG' => '+359', 'DK' => '+45', 'EE' => '+372', 'FI' => '+358', 'FR' => '+33', 'FO' => '+298', 'GI' => '+350', 'GR' => '+30', 'GB' => '+44', 'IE' => '+353', 'IS' => '+354', 'XK' => '+383', 'HR' => '+385', 'LV' => '+371', 'LI' => '+423', 'LT' => '+370', 'LU' => '+352', 'MT' => '+356', 'MD' => '+373', 'MC' => '+377', 'ME' => '+382', 'NL' => '+31', 'MK' => '+389', 'NO' => '+47', 'PL' => '+48', 'PT' => '+351', 'RO' => '+40', 'RU' => '+7', 'SM' => '+378', 'SE' => '+46', 'RS' => '+381', 'SK' => '+421', 'SI' => '+386', 'ES' => '+34', 'CZ' => '+420', 'TR' => '+90', 'UA' => '+380', 'HU' => '+36', 'BY' => '+375', 'CY' => '+357' );
		$countries = WC()->countries->get_countries();
		$result = array();
		foreach ( $codes as $country => $code ) {
			$result[] = array( 'country' => $country, 'label' => isset( $countries[ $country ] ) ? $countries[ $country ] : $country, 'code' => $code );
		}
		return $result;
	}
}
