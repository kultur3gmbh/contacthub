<?php

defined( 'ABSPATH' ) || exit;

/**
 * Optional WooCommerce checkout presentation and consent integration.
 *
 * WooCommerce remains responsible for the cart, checkout validation, payment,
 * customer account, order, and session lifecycle. ContactHub only changes the
 * presentation when an administrator explicitly enables it.
 */
final class ContactHub_WooCommerce {
	const OPTION = 'contacthub_woocommerce_settings';

	public static function boot() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'contacthub_settings_tabs', array( __CLASS__, 'settings_tab' ) );
		add_action( 'contacthub_render_settings_tab_woocommerce', array( __CLASS__, 'render_settings' ) );

		add_filter( 'the_content', array( __CLASS__, 'checkout_content' ), 8 );
		add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'woocommerce_checkout_registration_enabled', array( __CLASS__, 'checkout_registration_enabled' ) );
		add_filter( 'woocommerce_create_account_default_checked', array( __CLASS__, 'create_account_default_checked' ) );
		add_action( 'woocommerce_before_checkout_registration_form', array( __CLASS__, 'render_account_information' ) );
		add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'remember_checkout_state' ) );
		add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'render_checkout_consents' ), 8 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_checkout_requests' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'process_checkout_requests' ), 20, 3 );
	}

	private static function defaults() {
		return array(
			'checkout_layout'          => 'standard',
			'checkout_style'           => 'theme',
			'guest_account'            => 1,
			'newsletter_optin'         => 0,
			'marketing_optin'          => 0,
			'newsletter_label'         => __( 'I would like to receive the newsletter.', 'contacthub' ),
			'marketing_label'          => __( 'I would like to receive news and offers by email.', 'contacthub' ),
			'guest_account_description' => __( 'Create a customer account to manage orders and addresses more easily.', 'contacthub' ),
		);
	}

	private static function settings() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function register_settings() {
		register_setting(
			'contacthub_woocommerce',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$layout = isset( $input['checkout_layout'] ) ? sanitize_key( $input['checkout_layout'] ) : $defaults['checkout_layout'];
		$style = isset( $input['checkout_style'] ) ? sanitize_key( $input['checkout_style'] ) : $defaults['checkout_style'];

		return array(
			'checkout_layout'           => in_array( $layout, array( 'standard', 'contacthub' ), true ) ? $layout : 'standard',
			'checkout_style'            => in_array( $style, array( 'theme', 'contacthub' ), true ) ? $style : 'theme',
			'guest_account'             => empty( $input['guest_account'] ) ? 0 : 1,
			'newsletter_optin'          => empty( $input['newsletter_optin'] ) ? 0 : 1,
			'marketing_optin'           => empty( $input['marketing_optin'] ) ? 0 : 1,
			'newsletter_label'          => isset( $input['newsletter_label'] ) ? sanitize_text_field( $input['newsletter_label'] ) : $defaults['newsletter_label'],
			'marketing_label'           => isset( $input['marketing_label'] ) ? sanitize_text_field( $input['marketing_label'] ) : $defaults['marketing_label'],
			'guest_account_description' => isset( $input['guest_account_description'] ) ? sanitize_text_field( $input['guest_account_description'] ) : $defaults['guest_account_description'],
		);
	}

	public static function settings_tab( $tabs ) {
		$tabs = is_array( $tabs ) ? $tabs : array();
		$ordered = array();
		foreach ( $tabs as $key => $label ) {
			$ordered[ $key ] = $label;
			if ( 'general' === $key ) {
				$ordered['woocommerce'] = __( 'WooCommerce', 'contacthub' );
			}
		}
		if ( ! isset( $ordered['woocommerce'] ) ) {
			$ordered['woocommerce'] = __( 'WooCommerce', 'contacthub' );
		}
		return $ordered;
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'contacthub' ) );
		}
		$settings = self::settings();
		$data_source = ContactHub_Store::standalone() ? __( 'ContactHub database', 'contacthub' ) : __( 'Groundhogg', 'contacthub' );

		echo '<form method="post" action="options.php">';
		settings_fields( 'contacthub_woocommerce' );
		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Checkout presentation', 'contacthub' ) . '</h2>';
		echo '<p>' . esc_html__( 'WooCommerce continues to control the cart, validation, payment methods, orders, and sessions. ContactHub only provides the optional presentation.', 'contacthub' ) . '</p>';
		echo '<div class="contacthub-style-switch">';
		self::radio_card( 'checkout_layout', 'standard', __( 'WooCommerce standard checkout', 'contacthub' ), __( 'Keep the checkout page and layout currently configured in WooCommerce.', 'contacthub' ), $settings );
		self::radio_card( 'checkout_layout', 'contacthub', __( 'ContactHub checkout', 'contacthub' ), __( 'Use the classic WooCommerce checkout logic with a modern two-column ContactHub presentation.', 'contacthub' ), $settings );
		echo '</div>';
		echo '<div class="contacthub-setting-row"><strong>' . esc_html__( 'Checkout colors and typography', 'contacthub' ) . '</strong><div class="contacthub-style-switch">';
		self::radio_card( 'checkout_style', 'theme', __( 'Use active theme styles', 'contacthub' ), __( 'Inherit compatible colors, typography, fields, buttons, borders, and corner radii from the active theme.', 'contacthub' ), $settings );
		self::radio_card( 'checkout_style', 'contacthub', __( 'Use ContactHub styles', 'contacthub' ), __( 'Use the neutral ContactHub fallback design.', 'contacthub' ), $settings );
		echo '</div></div></section>';

		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Customer account at checkout', 'contacthub' ) . '</h2>';
		self::checkbox( 'guest_account', __( 'Offer account registration to guests', 'contacthub' ), __( 'WooCommerce creates the customer account only when the guest explicitly selects the account option.', 'contacthub' ), $settings );
		self::text( 'guest_account_description', __( 'Account registration information', 'contacthub' ), $settings );
		echo '</section>';

		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Consent at checkout', 'contacthub' ) . '</h2>';
		echo '<p>' . sprintf( esc_html__( 'Confirmed consent is stored by the active contact data source: %s. Orders remain independent of every optional consent.', 'contacthub' ), esc_html( $data_source ) ) . '</p>';
		self::checkbox( 'newsletter_optin', __( 'Offer newsletter signup', 'contacthub' ), __( 'Shows an optional, unchecked newsletter checkbox when no confirmed marketing consent exists.', 'contacthub' ), $settings );
		self::text( 'newsletter_label', __( 'Newsletter checkbox text', 'contacthub' ), $settings );
		self::checkbox( 'marketing_optin', __( 'Ask for marketing consent', 'contacthub' ), __( 'Shows a second optional, unchecked consent for news and offers when no confirmed marketing consent exists.', 'contacthub' ), $settings );
		self::text( 'marketing_label', __( 'Marketing checkbox text', 'contacthub' ), $settings );
		echo '<p class="description"><strong>' . esc_html__( 'Double opt-in is always required.', 'contacthub' ) . '</strong> ' . esc_html__( 'Selecting either consent sends one confirmation email. Consent becomes active only after the confirmation link is opened.', 'contacthub' ) . '</p>';
		submit_button( __( 'Save WooCommerce settings', 'contacthub' ) );
		echo '</section></form>';
	}

	private static function radio_card( $key, $value, $label, $description, $settings ) {
		echo '<label class="contacthub-style-option"><input type="radio" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"' . checked( $settings[ $key ], $value, false ) . '><span><strong>' . esc_html( $label ) . '</strong><small>' . esc_html( $description ) . '</small></span></label>';
	}

	private static function checkbox( $key, $label, $description, $settings ) {
		echo '<div class="contacthub-setting-row"><label><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="1"' . checked( ! empty( $settings[ $key ] ), true, false ) . '> <strong>' . esc_html( $label ) . '</strong></label><p class="description">' . esc_html( $description ) . '</p></div>';
	}

	private static function text( $key, $label, $settings ) {
		echo '<div class="contacthub-setting-row"><label for="contacthub-woocommerce-' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><input id="contacthub-woocommerce-' . esc_attr( $key ) . '" class="large-text" type="text" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $settings[ $key ] ) . '"></div>';
	}

	private static function contacthub_checkout_enabled() {
		return 'contacthub' === self::settings()['checkout_layout'];
	}

	private static function is_checkout_root() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return false;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() ) {
			return false;
		}
		return true;
	}

	public static function checkout_content( $content ) {
		if ( is_admin() || wp_doing_ajax() || ! self::contacthub_checkout_enabled() || ! self::is_checkout_root() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		return '<div class="contacthub-checkout-shell">' . do_shortcode( '[woocommerce_checkout]' ) . '</div>';
	}

	public static function body_classes( $classes ) {
		if ( self::contacthub_checkout_enabled() && self::is_checkout_root() ) {
			$classes[] = 'contacthub-checkout-active';
			$classes[] = 'theme' === self::settings()['checkout_style'] ? 'contacthub-checkout-theme' : 'contacthub-checkout-neutral';
		}
		return $classes;
	}

	public static function assets() {
		if ( ! self::contacthub_checkout_enabled() || ! self::is_checkout_root() ) {
			return;
		}
		wp_enqueue_style( 'contacthub-checkout', CONTACTHUB_URL . 'assets/contacthub-checkout.css', array(), CONTACTHUB_ASSET_VERSION );
	}

	public static function checkout_registration_enabled( $enabled ) {
		if ( self::contacthub_checkout_enabled() && ! empty( self::settings()['guest_account'] ) ) {
			return true;
		}
		return $enabled;
	}

	public static function create_account_default_checked( $checked ) {
		if ( self::contacthub_checkout_enabled() && ! empty( self::settings()['guest_account'] ) ) {
			return false;
		}
		return $checked;
	}

	public static function render_account_information() {
		if ( ! self::contacthub_checkout_enabled() || empty( self::settings()['guest_account'] ) ) {
			return;
		}
		$description = trim( (string) self::settings()['guest_account_description'] );
		if ( $description ) {
			echo '<p class="contacthub-checkout-account-info">' . esc_html( $description ) . '</p>';
		}
	}

	public static function remember_checkout_state( $posted_data ) {
		if ( ! self::contacthub_checkout_enabled() || ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$data = array();
		parse_str( (string) $posted_data, $data );
		WC()->session->set( 'contacthub_checkout_email', sanitize_email( $data['billing_email'] ?? '' ) );
		WC()->session->set( 'contacthub_checkout_newsletter', empty( $data['contacthub_newsletter_optin'] ) ? 0 : 1 );
		WC()->session->set( 'contacthub_checkout_marketing', empty( $data['contacthub_marketing_optin'] ) ? 0 : 1 );
	}

	public static function render_checkout_consents() {
		if ( ! self::contacthub_checkout_enabled() ) {
			return;
		}
		$settings = self::settings();
		if ( empty( $settings['newsletter_optin'] ) && empty( $settings['marketing_optin'] ) ) {
			return;
		}
		$email = '';
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$email = sanitize_email( $user->user_email );
		} elseif ( function_exists( 'WC' ) && WC()->session ) {
			$email = sanitize_email( WC()->session->get( 'contacthub_checkout_email', '' ) );
		}
		if ( $email && self::has_marketing_consent( $email ) ) {
			return;
		}
		$newsletter_checked = function_exists( 'WC' ) && WC()->session ? (bool) WC()->session->get( 'contacthub_checkout_newsletter', false ) : false;
		$marketing_checked = function_exists( 'WC' ) && WC()->session ? (bool) WC()->session->get( 'contacthub_checkout_marketing', false ) : false;

		echo '<section class="contacthub-checkout-consents" aria-labelledby="contacthub-checkout-consent-title"><h3 id="contacthub-checkout-consent-title">' . esc_html__( 'Stay informed', 'contacthub' ) . '</h3>';
		wp_nonce_field( 'contacthub_checkout_consent', 'contacthub_checkout_consent_nonce' );
		if ( ! empty( $settings['newsletter_optin'] ) ) {
			echo '<label><input type="checkbox" name="contacthub_newsletter_optin" value="1"' . checked( $newsletter_checked, true, false ) . '> <span>' . esc_html( $settings['newsletter_label'] ) . '</span></label>';
		}
		if ( ! empty( $settings['marketing_optin'] ) ) {
			echo '<label><input type="checkbox" name="contacthub_marketing_optin" value="1"' . checked( $marketing_checked, true, false ) . '> <span>' . esc_html( $settings['marketing_label'] ) . '</span></label>';
		}
		echo '<p>' . esc_html__( 'Optional and not required for the order. A confirmation email is sent before consent becomes active.', 'contacthub' ) . '</p></section>';
	}

	private static function has_marketing_consent( $email ) {
		if ( ! is_email( $email ) || ! method_exists( 'ContactHub_Plugin', 'extension_contact_by_email' ) ) {
			return false;
		}
		$contact = ContactHub_Plugin::extension_contact_by_email( $email );
		return $contact && ContactHub_Plugin::extension_is_marketable( $contact );
	}

	public static function save_checkout_requests( $order, $data ) {
		if ( ! self::contacthub_checkout_enabled() || ! $order instanceof WC_Order ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['contacthub_checkout_consent_nonce'] ?? '' ) );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'contacthub_checkout_consent' ) ) {
			return;
		}
		$email = sanitize_email( $data['billing_email'] ?? $order->get_billing_email() );
		if ( $email && self::has_marketing_consent( $email ) ) {
			return;
		}
		if ( ! empty( $_POST['contacthub_newsletter_optin'] ) ) {
			$order->update_meta_data( '_contacthub_newsletter_requested', 'yes' );
		}
		if ( ! empty( $_POST['contacthub_marketing_optin'] ) ) {
			$order->update_meta_data( '_contacthub_marketing_requested', 'yes' );
		}
		if ( ! empty( $_POST['contacthub_newsletter_optin'] ) || ! empty( $_POST['contacthub_marketing_optin'] ) ) {
			$order->update_meta_data( '_contacthub_consent_source', 'woocommerce_checkout' );
		}
	}

	public static function process_checkout_requests( $order_id, $posted_data, $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_contacthub_optin_email_sent', true ) ) {
			return;
		}
		$newsletter = 'yes' === $order->get_meta( '_contacthub_newsletter_requested', true );
		$marketing = 'yes' === $order->get_meta( '_contacthub_marketing_requested', true );
		if ( ! $newsletter && ! $marketing ) {
			return;
		}
		$email = sanitize_email( $order->get_billing_email() );
		if ( ! $email || self::has_marketing_consent( $email ) ) {
			return;
		}
		$source = $newsletter && $marketing ? 'woocommerce_checkout_newsletter_marketing' : ( $newsletter ? 'woocommerce_checkout_newsletter' : 'woocommerce_checkout_marketing' );
		$result = ContactHub_Registration::request_newsletter_optin( $email, $order->get_billing_first_name(), $order->get_billing_last_name(), home_url( '/' ), $source );
		if ( is_wp_error( $result ) ) {
			$order->add_order_note( sprintf( __( 'ContactHub could not send the double opt-in email: %s', 'contacthub' ), $result->get_error_message() ) );
			return;
		}
		$order->update_meta_data( '_contacthub_optin_email_sent', current_time( 'mysql', true ) );
		$order->save();
		if ( $order->get_customer_id() ) {
			update_user_meta( $order->get_customer_id(), 'contacthub_marketing_status', 'pending' );
			update_user_meta( $order->get_customer_id(), 'contacthub_marketing_requested_at', current_time( 'mysql', true ) );
		}
		do_action( 'contacthub/woocommerce_checkout_optin_requested', $order_id, $email, $source );
	}
}
