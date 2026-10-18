<?php

defined( 'ABSPATH' ) || exit;

final class ContactHub_Registration {
	const OPTION = 'contacthub_registration_settings';
	const GENERAL_OPTION = 'contacthub_general_settings';
	const NOTICE_PREFIX = 'contacthub_registration_notice_';

	public static function boot() {
		// Complete pending schema changes before assets, menus, AJAX handlers, or
		// front-end account screens can issue their first ContactHub query.
		self::ensure_contacthub_database( true );
		add_action( 'admin_init', array( __CLASS__, 'ensure_contacthub_database' ), 1 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_shortcode( 'contacthub_registration', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'contacthub_newsletter_signup', array( __CLASS__, 'newsletter_shortcode' ) );
		add_action( 'admin_post_nopriv_contacthub_register', array( __CLASS__, 'handle_registration' ) );
		add_action( 'admin_post_contacthub_register', array( __CLASS__, 'handle_registration' ) );
		add_action( 'admin_post_nopriv_contacthub_newsletter_signup', array( __CLASS__, 'handle_newsletter_signup' ) );
		add_action( 'admin_post_contacthub_newsletter_signup', array( __CLASS__, 'handle_newsletter_signup' ) );
		add_action( 'admin_post_nopriv_contacthub_pending_resend', array( __CLASS__, 'handle_pending_resend' ) );
		add_action( 'admin_post_contacthub_pending_resend', array( __CLASS__, 'handle_pending_resend' ) );
		add_action( 'admin_post_nopriv_contacthub_pending_email', array( __CLASS__, 'handle_pending_email_change' ) );
		add_action( 'admin_post_contacthub_pending_email', array( __CLASS__, 'handle_pending_email_change' ) );
		add_action( 'admin_post_contacthub_registration_decision', array( __CLASS__, 'handle_decision' ) );
		add_action( 'admin_post_contacthub_registration_resend', array( __CLASS__, 'handle_resend' ) );
		add_action( 'admin_post_contacthub_setup_standalone', array( __CLASS__, 'handle_setup_standalone' ) );
		add_action( 'admin_post_contacthub_migrate_groundhogg', array( __CLASS__, 'handle_migrate_groundhogg' ) );
		add_action( 'admin_post_contacthub_reset_standalone', array( __CLASS__, 'handle_reset_standalone' ) );
		add_action( 'admin_post_contacthub_adopt_theme_styles', array( __CLASS__, 'handle_adopt_theme_styles' ) );
		add_action( 'admin_post_contacthub_salutation_dictionary', array( __CLASS__, 'handle_salutation_dictionary' ) );
		add_action( 'admin_post_contacthub_export_salutations', array( __CLASS__, 'export_salutations' ) );
		add_action( 'admin_post_contacthub_import_woocommerce', array( __CLASS__, 'handle_import_woocommerce' ) );
		add_action( 'admin_post_contacthub_migrate_newsletter_plugin', array( __CLASS__, 'handle_migrate_newsletter_plugin' ) );
		add_action( 'wp_ajax_nopriv_contacthub_registration_validate_vat', array( __CLASS__, 'validate_vat_ajax' ) );
		add_action( 'wp_ajax_contacthub_registration_validate_vat', array( __CLASS__, 'validate_vat_ajax' ) );
		add_action( 'init', array( __CLASS__, 'handle_verification' ) );
		add_filter( 'the_content', array( __CLASS__, 'success_page_notice' ), 8 );
		add_filter( 'authenticate', array( __CLASS__, 'guard_login' ), 40, 3 );
		add_filter( 'register_url', array( __CLASS__, 'registration_url' ) );
		add_action( 'login_form_register', array( __CLASS__, 'maybe_redirect_registration' ) );
		add_action( 'template_redirect', array( __CLASS__, 'render_virtual_registration' ) );
	}

	public static function menu() {
		add_submenu_page(
			'contacthub',
			__( 'ContactHub settings', 'contacthub' ),
			__( 'Settings', 'contacthub' ),
			'manage_options',
			'contacthub-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	public static function register_settings() {
		register_setting(
			'contacthub_general',
			self::GENERAL_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_general_settings' ),
				'default'           => self::general_defaults(),
			)
		);
		register_setting(
			'contacthub_registration',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::defaults(),
			)
		);
	}

	private static function general_defaults() {
		return array(
			'data_source'        => self::groundhogg_active() ? 'groundhogg' : 'unconfigured',
			'theme_styles'       => 0,
			'theme_stylesheet'   => '',
			'theme_name'         => '',
			'delete_on_uninstall' => 0,
		);
	}

	private static function general_settings() {
		return wp_parse_args( (array) get_option( self::GENERAL_OPTION, array() ), self::general_defaults() );
	}

	public static function activate() {
		self::ensure_contacthub_database();
	}

	public static function ensure_contacthub_database( $upgrade_only = false ) {
		$stored = get_option( self::GENERAL_OPTION, false );
		$source = is_array( $stored ) && isset( $stored['data_source'] ) ? sanitize_key( $stored['data_source'] ) : 'unconfigured';
		if ( 'standalone' === $source ) {
			$upgrade_only ? ContactHub_Schema::maybe_upgrade() : ContactHub_Schema::install();
			return;
		}
		if ( self::groundhogg_active() ) {
			return;
		}
		if ( ! in_array( $source, array( 'unconfigured', '' ), true ) ) {
			return;
		}
		$result = ContactHub_Schema::install();
		if ( is_wp_error( $result ) ) {
			return;
		}
		$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), self::general_defaults() );
		$settings['data_source'] = 'standalone';
		update_option( self::GENERAL_OPTION, $settings, false );
	}

	public static function sanitize_general_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$current = self::general_settings();
		$source = isset( $input['data_source'] ) ? sanitize_key( $input['data_source'] ) : $current['data_source'];
		if ( 'groundhogg' === $source && ! self::groundhogg_active() && 'groundhogg' !== $current['data_source'] ) {
			$source = 'unconfigured';
		}
		if ( 'standalone' === $source && ! ContactHub_Store::ready() ) {
			add_settings_error( self::GENERAL_OPTION, 'contacthub_standalone_pending', __( 'Create the ContactHub database before selecting ContactHub mode.', 'contacthub' ), 'warning' );
			$source = $current['data_source'];
		}
		if ( ! in_array( $source, array( 'groundhogg', 'standalone', 'unconfigured' ), true ) ) {
			$source = $current['data_source'];
		}
		$theme = wp_get_theme();
		$theme_styles = array_key_exists( 'theme_styles', $input ) ? ( empty( $input['theme_styles'] ) ? 0 : 1 ) : (int) ( $current['theme_styles'] ?? 0 );
		return array(
			'data_source'         => $source,
			'theme_styles'        => $theme_styles,
			'theme_stylesheet'    => $theme_styles ? sanitize_key( $theme->get_stylesheet() ) : '',
			'theme_name'          => $theme_styles ? sanitize_text_field( $theme->get( 'Name' ) ?: $theme->get_stylesheet() ) : '',
			'delete_on_uninstall' => array_key_exists( 'delete_on_uninstall', $input ) ? ( empty( $input['delete_on_uninstall'] ) ? 0 : 1 ) : (int) $current['delete_on_uninstall'],
		);
	}

	private static function groundhogg_active() {
		return defined( 'GROUNDHOGG_VERSION' ) || class_exists( '\\Groundhogg\\Contact' ) || function_exists( '\\Groundhogg\\get_db' );
	}

	private static function defaults() {
		$default_role = get_role( 'customer' ) ? 'customer' : 'subscriber';
		return array(
			'enabled'              => 0,
			'business_enabled'     => 1,
			'newsletter_enabled'   => 1,
			'require_email_verify' => 1,
			'require_approval'     => 1,
			'require_vat'          => 0,
			'allow_marketing'      => 1,
			'replace_registration' => 0,
			'allowed_roles'        => array( $default_role ),
			'registration_page_id' => 0,
			'privacy_page_id'      => absint( get_option( 'wp_page_for_privacy_policy' ) ),
			'terms_page_id'        => 0,
			'admin_email'          => sanitize_email( get_option( 'admin_email' ) ),
			'success_page_id'      => 0,
			'submission_message'   => __( 'Thank you. Please check your inbox and confirm your email address. Your requested customer role will only be assigned after approval.', 'contacthub' ),
			'customer_submission_message' => __( 'Thank you. Please check your inbox and confirm your email address.', 'contacthub' ),
			'confirmation_subject' => __( 'Confirm your registration at {site_name}', 'contacthub' ),
			'confirmation_message' => __( 'Hello {first_name},

please confirm your email address by opening this link:
{confirmation_link}

The link is valid for 24 hours.', 'contacthub' ),
			'verified_message'     => __( 'Your email address has been confirmed. Your requested customer role is still awaiting approval.', 'contacthub' ),
			'customer_verified_message' => __( 'Your email address has been confirmed. You can now sign in.', 'contacthub' ),
			'verified_page_id'     => 0,
			'verified_delay'       => 5,
			'newsletter_subject'   => __( 'Confirm your newsletter subscription at {site_name}', 'contacthub' ),
			'newsletter_message'   => __( 'Hello {first_name},

please confirm your newsletter subscription by opening this link:
{confirmation_link}

The link is valid for 24 hours.', 'contacthub' ),
			'newsletter_success'   => __( 'Thank you. Please check your inbox and confirm your newsletter subscription.', 'contacthub' ),
			'newsletter_verified'  => __( 'Your newsletter subscription has been confirmed.', 'contacthub' ),
		);
	}

	private static function settings() {
		$settings = (array) get_option( self::OPTION, array() );
		if ( empty( $settings['allowed_roles'] ) && ! empty( $settings['approved_role'] ) ) {
			$settings['allowed_roles'] = array( sanitize_key( $settings['approved_role'] ) );
		}
		return wp_parse_args( $settings, self::defaults() );
	}

	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$roles = self::allowed_roles();
		$requested_roles = isset( $input['allowed_roles'] ) && is_array( $input['allowed_roles'] ) ? array_map( 'sanitize_key', $input['allowed_roles'] ) : array();
		$allowed_roles = array();
		foreach ( $requested_roles as $role ) {
			if ( isset( $roles[ $role ] ) ) {
				$allowed_roles[] = $role;
			}
		}
		foreach ( array( 'submission_message', 'customer_submission_message', 'confirmation_subject', 'confirmation_message', 'verified_message', 'customer_verified_message', 'newsletter_subject', 'newsletter_message', 'newsletter_success', 'newsletter_verified' ) as $required_text ) {
			if ( empty( $input[ $required_text ] ) ) {
				$input[ $required_text ] = $defaults[ $required_text ];
			}
		}
		return array(
			'enabled'              => empty( $input['enabled'] ) ? 0 : 1,
			'business_enabled'     => empty( $input['business_enabled'] ) ? 0 : 1,
			'newsletter_enabled'   => empty( $input['newsletter_enabled'] ) ? 0 : 1,
			'require_email_verify' => empty( $input['require_email_verify'] ) ? 0 : 1,
			'require_approval'     => 1,
			'require_vat'          => empty( $input['require_vat'] ) ? 0 : 1,
			'allow_marketing'      => empty( $input['allow_marketing'] ) ? 0 : 1,
			'replace_registration' => empty( $input['replace_registration'] ) ? 0 : 1,
			'allowed_roles'        => array_values( array_unique( $allowed_roles ) ),
			'registration_page_id' => isset( $input['registration_page_id'] ) ? absint( $input['registration_page_id'] ) : 0,
			'privacy_page_id'      => isset( $input['privacy_page_id'] ) ? absint( $input['privacy_page_id'] ) : 0,
			'terms_page_id'        => isset( $input['terms_page_id'] ) ? absint( $input['terms_page_id'] ) : 0,
			'admin_email'          => isset( $input['admin_email'] ) ? sanitize_email( $input['admin_email'] ) : $defaults['admin_email'],
			'success_page_id'      => isset( $input['success_page_id'] ) ? absint( $input['success_page_id'] ) : 0,
			'submission_message'   => isset( $input['submission_message'] ) ? sanitize_textarea_field( $input['submission_message'] ) : $defaults['submission_message'],
			'customer_submission_message' => isset( $input['customer_submission_message'] ) ? sanitize_textarea_field( $input['customer_submission_message'] ) : $defaults['customer_submission_message'],
			'confirmation_subject' => isset( $input['confirmation_subject'] ) ? sanitize_text_field( $input['confirmation_subject'] ) : $defaults['confirmation_subject'],
			'confirmation_message' => isset( $input['confirmation_message'] ) ? wp_kses_post( $input['confirmation_message'] ) : $defaults['confirmation_message'],
			'verified_message'     => isset( $input['verified_message'] ) ? sanitize_textarea_field( $input['verified_message'] ) : $defaults['verified_message'],
			'customer_verified_message' => isset( $input['customer_verified_message'] ) ? sanitize_textarea_field( $input['customer_verified_message'] ) : $defaults['customer_verified_message'],
			'verified_page_id'     => isset( $input['verified_page_id'] ) ? absint( $input['verified_page_id'] ) : 0,
			'verified_delay'       => isset( $input['verified_delay'] ) ? min( 30, max( 0, absint( $input['verified_delay'] ) ) ) : $defaults['verified_delay'],
			'newsletter_subject'   => isset( $input['newsletter_subject'] ) ? sanitize_text_field( $input['newsletter_subject'] ) : $defaults['newsletter_subject'],
			'newsletter_message'   => isset( $input['newsletter_message'] ) ? wp_kses_post( $input['newsletter_message'] ) : $defaults['newsletter_message'],
			'newsletter_success'   => isset( $input['newsletter_success'] ) ? sanitize_textarea_field( $input['newsletter_success'] ) : $defaults['newsletter_success'],
			'newsletter_verified'  => isset( $input['newsletter_verified'] ) ? sanitize_textarea_field( $input['newsletter_verified'] ) : $defaults['newsletter_verified'],
		);
	}

	private static function allowed_roles() {
		$registry = function_exists( 'wp_roles' ) ? wp_roles() : null;
		$roles = $registry && isset( $registry->roles ) && is_array( $registry->roles ) ? $registry->roles : array();
		$result = array();
		foreach ( $roles as $key => $role ) {
			$capabilities = isset( $role['capabilities'] ) && is_array( $role['capabilities'] ) ? $role['capabilities'] : array();
			if ( 'administrator' === $key || ! empty( $capabilities['manage_options'] ) || ! empty( $capabilities['edit_users'] ) || ! empty( $capabilities['activate_plugins'] ) ) {
				continue;
			}
			$result[ $key ] = isset( $role['name'] ) ? translate_user_role( $role['name'] ) : $key;
		}
		return $result;
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'contacthub' ) );
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab navigation.
		if ( in_array( $tab, array( 'migration', 'duplicates' ), true ) ) { $_GET['other_section'] = $tab; $tab = 'other'; }
		if ( 'registration' === $tab ) { $_GET['general_section'] = 'registration'; $tab = 'general'; }
		$tabs = (array) apply_filters(
			'contacthub_settings_tabs',
			array(
				'general'      => __( 'General', 'contacthub' ),
				'integrations' => __( 'Integrations', 'contacthub' ),
				'misc'         => __( 'Miscellaneous parameters', 'contacthub' ),
				'other'        => __( 'Miscellaneous', 'contacthub' ),
			)
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}
		echo '<div class="wrap contacthub-wrap"><div class="contacthub-heading"><div><p class="contacthub-eyebrow">CONTACTHUB</p><h1>' . esc_html__( 'Settings', 'contacthub' ) . '</h1><p>' . esc_html__( 'Configure ContactHub and its optional integrations.', 'contacthub' ) . '</p></div></div>';
		echo '<nav class="nav-tab-wrapper contacthub-settings-tabs" aria-label="' . esc_attr__( 'ContactHub settings', 'contacthub' ) . '">';
		foreach ( $tabs as $tab_key => $tab_label ) {
			$tab_key = sanitize_key( $tab_key );
			echo '<a class="nav-tab ' . ( $tab_key === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=contacthub-settings&tab=' . $tab_key ) ) . '">' . esc_html( $tab_label ) . '</a>';
		}
		echo '</nav>';
		settings_errors();
		if ( 'misc' === $tab ) {
			self::render_misc_settings();
		} elseif ( 'general' === $tab ) {
			self::render_general_tab();
		} elseif ( 'integrations' === $tab ) {
			self::render_integrations_settings();
		} elseif ( 'other' === $tab ) {
			self::render_other_settings();
		} else {
			do_action( 'contacthub_render_settings_tab_' . $tab );
		}
		echo '</div>';
	}

	private static function render_general_tab() {
		$section = isset( $_GET['general_section'] ) ? sanitize_key( wp_unslash( $_GET['general_section'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<nav class="nav-tab-wrapper contacthub-settings-subtabs"><a class="nav-tab ' . ( 'settings' === $section ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=contacthub-settings&tab=general' ) ) . '">' . esc_html__( 'General settings', 'contacthub' ) . '</a><a class="nav-tab ' . ( 'registration' === $section ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=contacthub-settings&tab=general&general_section=registration' ) ) . '">' . esc_html__( 'Registration', 'contacthub' ) . '</a></nav>';
		if ( 'registration' === $section ) self::render_registration_settings(); else self::render_general_settings();
	}

	private static function render_other_settings() {
		$sections = (array) apply_filters( 'contacthub_other_settings_sections', array( 'migration' => __( 'Migration', 'contacthub' ), 'duplicates' => __( 'Duplicates', 'contacthub' ), 'admin_notices' => __( 'Admin notices', 'contacthub' ) ) );
		$current = isset( $_GET['other_section'] ) ? sanitize_key( wp_unslash( $_GET['other_section'] ) ) : 'migration'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $sections[ $current ] ) ) $current = 'migration';
		echo '<nav class="nav-tab-wrapper contacthub-settings-subtabs">'; foreach ( $sections as $key => $label ) echo '<a class="nav-tab ' . ( $current === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=contacthub-settings&tab=other&other_section=' . sanitize_key( $key ) ) ) . '">' . esc_html( $label ) . '</a>'; echo '</nav>';
		if ( 'migration' === $current ) self::render_migration_settings(); elseif ( 'admin_notices' === $current ) ContactHub_Data_Tools::render_notice_settings(); else do_action( 'contacthub_render_other_settings_section_' . $current );
	}

	private static function render_misc_settings() {
		$notice = get_transient( 'contacthub_database_notice_' . get_current_user_id() );
		if ( $notice ) { delete_transient( 'contacthub_database_notice_' . get_current_user_id() ); echo '<div class="notice notice-' . ( 'success' === $notice['type'] ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>'; }
		$sections = (array) apply_filters( 'contacthub_misc_settings_sections', array( 'salutations' => __( 'First names', 'contacthub' ) ) );
		$current = isset( $_GET['misc_section'] ) ? sanitize_key( wp_unslash( $_GET['misc_section'] ) ) : 'salutations'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display routing.
		if ( ! isset( $sections[ $current ] ) ) $current = 'salutations';
		echo '<nav class="nav-tab-wrapper contacthub-settings-subtabs">';
		foreach ( $sections as $key => $label ) echo '<a class="nav-tab ' . ( $current === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=contacthub-settings&tab=misc&misc_section=' . sanitize_key( $key ) ) ) . '">' . esc_html( $label ) . '</a>';
		echo '</nav>';
		if ( 'salutations' !== $current ) { do_action( 'contacthub_render_misc_settings_section_' . $current ); return; }
		$rows = ContactHub_Store::salutations();
		$edit_id = isset( $_GET['edit_salutation'] ) ? absint( $_GET['edit_salutation'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only editor selection.
		$editing = $edit_id ? ContactHub_Store::salutation( $edit_id ) : false;
		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Salutation library', 'contacthub' ) . '</h2><p>' . esc_html__( 'Saved assignments are applied automatically when the same first name is entered again. A manually selected salutation updates the library.', 'contacthub' ) . '</p>';
		$bulk = get_transient( 'contacthub_salutation_bulk_' . get_current_user_id() );
		if ( is_array( $bulk ) ) { delete_transient( 'contacthub_salutation_bulk_' . get_current_user_id() ); echo '<div class="notice notice-info inline"><p>' . esc_html( $bulk['applied'] ? sprintf( __( '%d missing salutations were assigned.', 'contacthub' ), absint( $bulk['count'] ) ) : sprintf( __( '%d contacts can be assigned from the library. Confirm to apply the changes.', 'contacthub' ), absint( $bulk['count'] ) ) ) . '</p></div>'; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contacthub-settings-grid"><input type="hidden" name="action" value="contacthub_salutation_dictionary"><input type="hidden" name="operation" value="save"><input type="hidden" name="original_id" value="' . esc_attr( $edit_id ) . '">'; wp_nonce_field( 'contacthub_salutation_dictionary' );
		echo '<label><span>' . esc_html__( 'First name', 'contacthub' ) . '</span><input required name="first_name" value="' . esc_attr( $editing['first_name'] ?? '' ) . '"></label><label><span>' . esc_html__( 'Salutation', 'contacthub' ) . '</span><select name="salutation" required><option value="">' . esc_html__( '--- Please select ---', 'contacthub' ) . '</option><option value="mr"' . selected( $editing['salutation'] ?? '', 'mr', false ) . '>' . esc_html__( 'Mr', 'contacthub' ) . '</option><option value="ms"' . selected( $editing['salutation'] ?? '', 'ms', false ) . '>' . esc_html__( 'Ms', 'contacthub' ) . '</option><option value="family"' . selected( $editing['salutation'] ?? '', 'family', false ) . '>' . esc_html__( 'Family', 'contacthub' ) . '</option><option value="neutral"' . selected( $editing['salutation'] ?? '', 'neutral', false ) . '>' . esc_html__( 'Neutral', 'contacthub' ) . '</option></select></label><p><button class="button button-primary">' . ( $editing ? esc_html__( 'Save changes', 'contacthub' ) : esc_html__( 'Add entry', 'contacthub' ) ) . '</button>' . ( $editing ? ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=contacthub-settings&tab=misc&misc_section=salutations' ) ) . '">' . esc_html__( 'Cancel', 'contacthub' ) . '</a>' : '' ) . '</p></form>';
		echo '<table class="widefat striped contacthub-table"><thead><tr><th>' . esc_html__( 'First name', 'contacthub' ) . '</th><th>' . esc_html__( 'Salutation', 'contacthub' ) . '</th><th>' . esc_html__( 'Source', 'contacthub' ) . '</th><th>' . esc_html__( 'Actions', 'contacthub' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$delete = wp_nonce_url( admin_url( 'admin-post.php?action=contacthub_salutation_dictionary&operation=delete&id=' . absint( $row['id'] ) ), 'contacthub_salutation_dictionary' );
			$edit = admin_url( 'admin.php?page=contacthub-settings&tab=misc&misc_section=salutations&edit_salutation=' . absint( $row['id'] ) );
			$salutation_labels = array( 'mr' => __( 'Mr', 'contacthub' ), 'ms' => __( 'Ms', 'contacthub' ), 'family' => __( 'Family', 'contacthub' ), 'neutral' => __( 'Neutral', 'contacthub' ) );
			$source_labels = array( 'manual' => __( 'Manual', 'contacthub' ), 'confirmed' => __( 'Confirmed selection', 'contacthub' ), 'import' => __( 'Import', 'contacthub' ), 'central' => __( 'Central library', 'contacthub' ) );
			echo '<tr><td>' . esc_html( $row['first_name'] ) . '</td><td>' . esc_html( $salutation_labels[ $row['salutation'] ] ?? $row['salutation'] ) . '</td><td>' . esc_html( $source_labels[ $row['source'] ] ?? $row['source'] ) . '</td><td><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'contacthub' ) . '</a> · <a href="' . esc_url( $delete ) . '">' . esc_html__( 'Delete', 'contacthub' ) . '</a></td></tr>';
		}
		if ( ! $rows ) echo '<tr><td colspan="4">' . esc_html__( 'The dictionary is empty.', 'contacthub' ) . '</td></tr>';
		echo '</tbody></table><div class="contacthub-database-actions"><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=contacthub_export_salutations' ), 'contacthub_export_salutations' ) ) . '">' . esc_html__( 'Export CSV', 'contacthub' ) . '</a>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_salutation_dictionary"><input type="hidden" name="operation" value="import">'; wp_nonce_field( 'contacthub_salutation_dictionary' );
		echo '<input type="file" name="dictionary_file" accept=".csv,text/csv" required><button class="button">' . esc_html__( 'Import and merge CSV', 'contacthub' ) . '</button></form></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contacthub-salutation-bulk">'; wp_nonce_field( 'contacthub_assign_missing_salutations' ); echo '<input type="hidden" name="action" value="contacthub_assign_missing_salutations"><label><input type="checkbox" name="confirm_apply" value="1"> ' . esc_html__( 'Apply the assignments immediately after checking.', 'contacthub' ) . '</label> <button class="button">' . esc_html__( 'Reconcile now', 'contacthub' ) . '</button></form></section>';
		do_action( 'contacthub_render_salutation_tools' );
	}

	public static function handle_salutation_dictionary() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contacthub' ) );
		check_admin_referer( 'contacthub_salutation_dictionary' );
		$operation = sanitize_key( wp_unslash( $_REQUEST['operation'] ?? '' ) );
		if ( 'delete' === $operation ) {
			ContactHub_Store::delete_salutation( absint( $_GET['id'] ?? 0 ) );
		} elseif ( 'save' === $operation ) {
			$original_id = absint( $_POST['original_id'] ?? 0 );
			$original = $original_id ? ContactHub_Store::salutation( $original_id ) : false;
			$first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
			if ( $original && 0 !== strcasecmp( $original['first_name'], $first_name ) ) ContactHub_Store::delete_salutation( $original_id );
			ContactHub_Store::learn_salutation( $first_name, sanitize_key( wp_unslash( $_POST['salutation'] ?? '' ) ), 'manual' );
		} elseif ( 'import' === $operation && ! empty( $_FILES['dictionary_file']['tmp_name'] ) ) {
			$handle = fopen( $_FILES['dictionary_file']['tmp_name'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- protected uploaded CSV stream.
			if ( $handle ) {
				$first = true;
				while ( false !== ( $row = fgetcsv( $handle, 0, ';' ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv
					if ( $first && isset( $row[0] ) && false !== stripos( $row[0], 'vorname' ) ) { $first = false; continue; }
					$first = false; if ( count( $row ) < 2 ) continue;
					ContactHub_Store::learn_salutation( $row[0], $row[1], 'import' );
				}
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=contacthub-settings&tab=misc' ) ); exit;
	}

	public static function export_salutations() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contacthub' ) );
		check_admin_referer( 'contacthub_export_salutations' );
		nocache_headers(); header( 'Content-Type: text/csv; charset=UTF-8' ); header( 'Content-Disposition: attachment; filename="contacthub-anreden.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'Vorname', 'Anrede', 'Quelle' ), ';' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		foreach ( ContactHub_Store::salutations() as $row ) fputcsv( $out, array( $row['first_name'], $row['salutation'], $row['source'] ), ';' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		fclose( $out ); exit; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	private static function render_registration_settings() {
		$settings = self::settings();
		$roles = self::allowed_roles();
		$section = isset( $_GET['registration_section'] ) ? sanitize_key( wp_unslash( $_GET['registration_section'] ) ) : 'customers'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display routing only.
		if ( ! in_array( $section, array( 'customers', 'business', 'newsletter' ), true ) ) $section = 'customers';
		if ( isset( $_GET['contacthub_role_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only result flag.
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The requested role is no longer enabled and was not assigned. Please update the allowed roles before approving this registration.', 'contacthub' ) . '</p></div>';
		}
		if ( isset( $_GET['contacthub_email_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only result flag.
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The requested role was not assigned because the email address has not yet been confirmed.', 'contacthub' ) . '</p></div>';
		}
		echo '<nav class="nav-tab-wrapper contacthub-settings-subtabs">';
		$registration_sections = array( 'customers' => __( 'General customer registration', 'contacthub' ), 'business' => __( 'Business customers', 'contacthub' ), 'newsletter' => __( 'Newsletter signup', 'contacthub' ) );
		foreach ( $registration_sections as $key => $label ) echo '<a class="nav-tab ' . ( $section === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=contacthub-settings&tab=general&general_section=registration&registration_section=' . $key ) ) . '">' . esc_html( $label ) . '</a>';
		echo '</nav><form method="post" action="options.php"><section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Customer account registration', 'contacthub' ) . '</h2>';
		settings_fields( 'contacthub_registration' );
		echo '<div class="contacthub-registration-panel"' . ( 'customers' === $section ? '' : ' hidden' ) . '>';
		self::checkbox_setting( 'enabled', __( 'Enable standard customer registration', 'contacthub' ), __( 'Makes the standard customer account form available through [contacthub_registration].', 'contacthub' ), $settings );
		self::checkbox_setting( 'require_email_verify', __( 'Require email confirmation', 'contacthub' ), __( 'The account remains blocked until the email address has been confirmed.', 'contacthub' ), $settings );
		echo '<input type="hidden" name="' . esc_attr( self::OPTION ) . '[allow_marketing]" value="1"><p class="description">' . esc_html__( 'Every account registration offers an optional, unchecked marketing consent. Account registration never implies marketing consent.', 'contacthub' ) . '</p>';
		self::checkbox_setting( 'replace_registration', __( 'Replace the standard WordPress registration', 'contacthub' ), __( 'Redirects the public WordPress registration to the selected ContactHub registration page. Administrative login remains available.', 'contacthub' ), $settings );
		self::page_setting( 'registration_page_id', __( 'Registration page', 'contacthub' ), $settings );
		if ( ! $settings['registration_page_id'] ) echo '<div class="contacthub-setting-row"><strong>' . esc_html__( 'Built-in registration page', 'contacthub' ) . '</strong><p><a class="button" href="' . esc_url( self::virtual_registration_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'View registration page', 'contacthub' ) . '</a></p><p class="description">' . esc_html__( 'ContactHub uses a theme-compatible built-in page while no WordPress page is selected.', 'contacthub' ) . '</p></div>';
		self::page_setting( 'privacy_page_id', __( 'Privacy policy page', 'contacthub' ), $settings );
		self::page_setting( 'terms_page_id', __( 'Terms and conditions page', 'contacthub' ), $settings );
		self::page_setting( 'success_page_id', __( 'Page after successful submission', 'contacthub' ), $settings );
		self::textarea_setting( 'customer_submission_message', __( 'Text after standard customer registration', 'contacthub' ), __( 'This text is displayed after a standard customer account registration.', 'contacthub' ), $settings );
		echo '</div><div class="contacthub-registration-panel"' . ( 'business' === $section ? '' : ' hidden' ) . '>';
		self::checkbox_setting( 'business_enabled', __( 'Offer business customer registration', 'contacthub' ), __( 'Adds a separate business registration with company data, VAT ID validation, and a requested customer role.', 'contacthub' ), $settings );
		self::checkbox_setting( 'require_vat', __( 'Require VAT ID', 'contacthub' ), __( 'A VAT ID is optional unless this setting is enabled. Every entered VAT ID must be successfully validated through EU VIES.', 'contacthub' ), $settings );
		echo '<div class="contacthub-setting-row"><strong>' . esc_html__( 'Roles customers may request', 'contacthub' ) . '</strong><div class="contacthub-role-options">';
		foreach ( $roles as $role => $label ) {
			echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[allowed_roles][]" value="' . esc_attr( $role ) . '"' . checked( in_array( $role, (array) $settings['allowed_roles'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div><p class="description">' . esc_html__( 'The customer only requests a role. It is assigned exclusively after an administrator approves the registration. Privileged administrative roles are excluded.', 'contacthub' ) . '</p></div>';

		self::textarea_setting( 'submission_message', __( 'Text after business customer registration', 'contacthub' ), __( 'This text is displayed after a business customer registration.', 'contacthub' ), $settings );
		echo '</div><div class="contacthub-registration-panel"' . ( 'newsletter' === $section ? '' : ' hidden' ) . '>';
		self::checkbox_setting( 'newsletter_enabled', __( 'Enable separate newsletter signup', 'contacthub' ), __( 'Provides [contacthub_newsletter_signup]. The form creates or updates a ContactHub contact but never creates a WordPress user account.', 'contacthub' ), $settings );
		self::text_setting( 'newsletter_subject', __( 'Newsletter confirmation subject', 'contacthub' ), $settings );
		echo '<div class="contacthub-setting-row"><label for="contacthub-newsletter-message"><strong>' . esc_html__( 'Newsletter confirmation email', 'contacthub' ) . '</strong></label>';
		wp_editor( $settings['newsletter_message'], 'contacthub-newsletter-message', array( 'textarea_name' => self::OPTION . '[newsletter_message]', 'textarea_rows' => 8, 'media_buttons' => false, 'quicktags' => true ) );
		echo '<p class="description">' . esc_html__( 'Available placeholders: {first_name}, {site_name}, {confirmation_link}.', 'contacthub' ) . '</p></div>';
		self::textarea_setting( 'newsletter_success', __( 'Text after newsletter signup', 'contacthub' ), __( 'Shown after the form has been submitted.', 'contacthub' ), $settings );
		self::textarea_setting( 'newsletter_verified', __( 'Text after newsletter confirmation', 'contacthub' ), __( 'Shown after the confirmation link has been opened.', 'contacthub' ), $settings );
		echo '</div><div class="contacthub-registration-common"' . ( 'newsletter' === $section ? ' hidden' : '' ) . '>';
		echo '<h3 class="contacthub-settings-subheading">' . esc_html__( 'Double opt-in email', 'contacthub' ) . '</h3>';
		self::text_setting( 'confirmation_subject', __( 'Email subject', 'contacthub' ), $settings );
		echo '<div class="contacthub-setting-row"><label for="contacthub-confirmation-message"><strong>' . esc_html__( 'Email text', 'contacthub' ) . '</strong></label>';
		wp_editor(
			$settings['confirmation_message'],
			'contacthub-confirmation-message',
			array(
				'textarea_name' => self::OPTION . '[confirmation_message]',
				'textarea_rows' => 10,
				'media_buttons' => false,
				'quicktags'     => true,
			)
		);
		echo '<p class="description">' . esc_html__( 'Available placeholders: {first_name}, {site_name}, {confirmation_link}. If the confirmation link is omitted, it is appended automatically.', 'contacthub' ) . '</p></div>';
		echo '<h3 class="contacthub-settings-subheading">' . esc_html__( 'After double opt-in confirmation', 'contacthub' ) . '</h3>';
		self::textarea_setting( 'customer_verified_message', __( 'Confirmation text for standard customers', 'contacthub' ), __( 'This text is displayed after a standard customer confirms the email address.', 'contacthub' ), $settings );
		self::textarea_setting( 'verified_message', __( 'Confirmation text for business customers', 'contacthub' ), __( 'This text is displayed after a business customer confirms the email address.', 'contacthub' ), $settings );
		self::page_setting( 'verified_page_id', __( 'Redirect page after confirmation', 'contacthub' ), $settings );
		echo '<div class="contacthub-setting-row"><label for="contacthub-verified-delay"><strong>' . esc_html__( 'Redirect delay in seconds', 'contacthub' ) . '</strong></label><input id="contacthub-verified-delay" class="small-text" type="number" min="0" max="30" name="' . esc_attr( self::OPTION ) . '[verified_delay]" value="' . esc_attr( $settings['verified_delay'] ) . '"><p class="description">' . esc_html__( 'Use 0 for an immediate redirect or up to 30 seconds.', 'contacthub' ) . '</p></div>';
		echo '<div class="contacthub-setting-row"><label for="contacthub-admin-email"><strong>' . esc_html__( 'Notification email address', 'contacthub' ) . '</strong></label><input id="contacthub-admin-email" class="regular-text" type="email" name="' . esc_attr( self::OPTION ) . '[admin_email]" value="' . esc_attr( $settings['admin_email'] ) . '"></div>';
		echo '</div>';
		submit_button();
		echo '</section></form>';

		self::render_integration_status();
		self::render_pending_registrations();
	}

	private static function render_general_settings() {
		$settings = self::general_settings();
		$groundhogg = self::groundhogg_active();
		$schema = ContactHub_Schema::status();
		$notice = get_transient( 'contacthub_database_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'contacthub_database_notice_' . get_current_user_id() );
			echo '<div class="notice notice-' . ( 'success' === $notice['type'] ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
		self::render_theme_settings( $settings );
		echo '<form method="post" action="options.php">';
		settings_fields( 'contacthub_general' );
		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Data source', 'contacthub' ) . '</h2><p>' . esc_html__( 'Only one system may be the leading source for contacts, companies, tags, and consent data.', 'contacthub' ) . '</p><div class="contacthub-source-options">';
		if ( $groundhogg ) {
			echo '<label class="contacthub-source-option"><input type="radio" name="' . esc_attr( self::GENERAL_OPTION ) . '[data_source]" value="groundhogg"' . checked( 'groundhogg', $settings['data_source'], false ) . '><span><strong>' . esc_html__( 'Groundhogg frontend mode', 'contacthub' ) . '</strong><small>' . esc_html__( 'Groundhogg remains responsible for contacts, companies, tags, consent, broadcasts, funnels, and automations. ContactHub provides the user-friendly interface.', 'contacthub' ) . '</small></span></label>';
		} else {
			if ( 'groundhogg' === $settings['data_source'] ) {
				echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Groundhogg is configured as the data source but is currently inactive. ContactHub has not switched the data source automatically.', 'contacthub' ) . '</p></div>';
			}
		}
		$standalone_ready = ContactHub_Store::ready();
		echo '<label class="contacthub-source-option' . ( $standalone_ready ? '' : ' is-disabled' ) . '"><input type="radio" name="' . esc_attr( self::GENERAL_OPTION ) . '[data_source]" value="standalone"' . checked( 'standalone', $settings['data_source'], false ) . ( $standalone_ready ? '' : ' disabled' ) . '><span><strong>' . esc_html__( 'ContactHub database', 'contacthub' ) . '</strong><small>' . esc_html__( 'ContactHub is responsible for contacts, companies, tags, consent, activities, and—when ContactHub Plus is active—newsletter delivery. Groundhogg is not written to in this mode.', 'contacthub' ) . '</small></span></label>';
		echo '</div>';
		if ( 0 === $schema['count'] ) {
			echo '<p class="description">' . esc_html__( 'No ContactHub customer tables have been created. With Groundhogg active, they are created only after an explicit ContactHub setup or migration.', 'contacthub' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html( sprintf( __( '%1$d of %2$d ContactHub database tables are present. Schema version: %3$s.', 'contacthub' ), $schema['count'], $schema['total'], $schema['version'] ?: __( 'unknown', 'contacthub' ) ) ) . '</p>';
		}
		submit_button();
		echo '</section></form>';

		echo '<form method="post" action="options.php">';
		settings_fields( 'contacthub_general' );
		echo '<section class="contacthub-card contacthub-settings-card contacthub-danger-card"><h2>' . esc_html__( 'Uninstallation and data', 'contacthub' ) . '</h2><p><strong>' . esc_html__( 'By default, uninstalling ContactHub does not delete customer data.', 'contacthub' ) . '</strong></p>';
		self::render_preserved_integrations();
		echo '<div class="contacthub-setting-row"><input type="hidden" name="' . esc_attr( self::GENERAL_OPTION ) . '[delete_on_uninstall]" value="0"><label><input type="checkbox" data-contacthub-delete-on-uninstall name="' . esc_attr( self::GENERAL_OPTION ) . '[delete_on_uninstall]" value="1"' . checked( ! empty( $settings['delete_on_uninstall'] ), true, false ) . '> <strong>' . esc_html__( 'Permanently delete all ContactHub-owned customer data when the plugin is uninstalled.', 'contacthub' ) . '</strong></label><p class="description">' . esc_html__( 'This affects only ContactHub chb_ tables, ContactHub options, and ContactHub user metadata. Data belonging to other plugins is never deleted.', 'contacthub' ) . '</p></div>';
		submit_button( __( 'Save general settings', 'contacthub' ) );
		echo '</section></form>';
	}

	private static function render_migration_settings() {
		$schema = ContactHub_Schema::status();
		$notice = get_transient( 'contacthub_database_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'contacthub_database_notice_' . get_current_user_id() );
			echo '<div class="notice notice-' . ( 'success' === $notice['type'] ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Migration center', 'contacthub' ) . '</h2><p>' . esc_html__( 'Imports are started only after an explicit confirmation. Source systems remain unchanged, and existing ContactHub contacts are matched by email address before new records are created.', 'contacthub' ) . '</p></section>';
		self::render_database_tools( $schema );
		if ( class_exists( 'WooCommerce' ) && ContactHub_Store::ready() ) {
			self::render_woocommerce_import();
		}
		self::render_newsletter_plugin_migration();
		do_action( 'contacthub_render_migration_tools' );
	}

	private static function render_woocommerce_import() {
		$preview = get_transient( 'contacthub_woocommerce_import_' . get_current_user_id() );
		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Import WooCommerce customers', 'contacthub' ) . '</h2><p>' . esc_html__( 'ContactHub can merge customer profiles from WooCommerce into the ContactHub database using the email address as the unique key. Orders, invoices, prices, and WooCommerce data remain unchanged.', 'contacthub' ) . '</p>';
		if ( $preview ) {
			echo '<div class="contacthub-migration-report"><h3>' . esc_html__( 'Import preview', 'contacthub' ) . '</h3><p>' . esc_html( sprintf( __( '%1$d unique customers found: %2$d new and %3$d already present. No data has been changed yet.', 'contacthub' ), absint( $preview['found'] ?? 0 ), absint( $preview['new'] ?? 0 ), absint( $preview['updated'] ?? 0 ) ) ) . '</p></div>';
		}
		echo '<div class="contacthub-database-actions">';
		self::woocommerce_import_form( 'preview', __( 'Preview import', 'contacthub' ), 'button-secondary' );
		if ( $preview ) self::woocommerce_import_form( 'run', __( 'Import and merge now', 'contacthub' ), 'button-primary' );
		echo '</div></section>';
	}

	private static function newsletter_plugin_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'newsletter';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		return $found === $table ? $table : '';
	}

	private static function newsletter_plugin_installed() {
		if ( defined( 'NEWSLETTER_VERSION' ) || class_exists( 'Newsletter' ) ) return true;
		if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
		foreach ( (array) get_plugins() as $file => $plugin ) {
			if ( 0 === strpos( $file, 'newsletter/' ) || 'newsletter' === sanitize_key( $plugin['TextDomain'] ?? '' ) ) return true;
		}
		return false;
	}

	private static function render_newsletter_plugin_migration() {
		if ( ! self::newsletter_plugin_installed() || ! apply_filters( 'contacthub_can_migrate_newsletter_plugin', false ) ) return;
		$table = self::newsletter_plugin_table();
		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Import The Newsletter Plugin data', 'contacthub' ) . '</h2>';
		if ( ! defined( 'NEWSLETTER_VERSION' ) && ! class_exists( 'Newsletter' ) ) echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The Newsletter Plugin is installed but inactive. Existing source tables can still be read without changing them.', 'contacthub' ) . '</p></div>';
		if ( ! ContactHub_Store::ready() ) {
			echo '<p>' . esc_html__( 'Create the ContactHub database before importing newsletter subscribers.', 'contacthub' ) . '</p></section>';
			return;
		}
		if ( ! $table ) {
			echo '<p>' . esc_html__( 'No compatible The Newsletter Plugin subscriber table was detected. Nothing will be changed.', 'contacthub' ) . '</p></section>';
			return;
		}
		$report = (array) get_option( 'contacthub_last_newsletter_plugin_migration_report', array() );
		$preview = (array) get_transient( 'contacthub_newsletter_plugin_preview_' . get_current_user_id() );
		echo '<p>' . esc_html__( 'Subscribers are matched by email address. Historical mailings and available statistics are imported as read-only archives by ContactHub Plus. Unsubscribed and bounced states take precedence and are never changed into a marketing consent by the import.', 'contacthub' ) . '</p>';
		if ( $preview ) echo '<div class="contacthub-migration-report"><h3>' . esc_html__( 'Import preview', 'contacthub' ) . '</h3><p>' . esc_html( sprintf( __( '%1$d subscribers and %2$d historical mailings were found. No data has been changed yet.', 'contacthub' ), absint( $preview['subscribers'] ?? 0 ), absint( $preview['mailings'] ?? 0 ) ) ) . '</p></div>';
		if ( $report ) {
			echo '<p><strong>' . esc_html__( 'Last import:', 'contacthub' ) . '</strong> ' . esc_html( sprintf( __( '%1$d new, %2$d updated, %3$d mailings, %4$d skipped, %5$d errors. Completed: %6$s', 'contacthub' ), absint( $report['new'] ?? 0 ), absint( $report['updated'] ?? 0 ), absint( $report['mailings'] ?? 0 ), absint( $report['skipped'] ?? 0 ), absint( $report['errors'] ?? 0 ), sanitize_text_field( $report['completed_at'] ?? '' ) ) ) . '</p>';
			if ( isset( $report['mailing_recipients'] ) ) echo '<p>' . esc_html( sprintf( __( '%d historical recipient assignments were imported and linked to matching people.', 'contacthub' ), absint( $report['mailing_recipients'] ) ) ) . '</p>';
		}
		echo '<div class="contacthub-database-actions">';
		self::newsletter_import_form( 'preview', __( 'Preview import', 'contacthub' ), 'button-secondary' );
		if ( $preview ) self::newsletter_import_form( 'run', __( 'Import and merge now', 'contacthub' ), 'button-primary' );
		echo '<p class="description">' . esc_html__( 'The original newsletter plugin data remains unchanged.', 'contacthub' ) . '</p></div>';
		echo '</section>';
	}

	private static function newsletter_import_form( $mode, $label, $class ) {
		echo '<form class="contacthub-inline-action-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_migrate_newsletter_plugin"><input type="hidden" name="mode" value="' . esc_attr( $mode ) . '">';
		wp_nonce_field( 'contacthub_migrate_newsletter_plugin' );
		echo '<button type="submit" class="button ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	public static function handle_migrate_newsletter_plugin() {
		self::guard_database_action( 'contacthub_migrate_newsletter_plugin' );
		if ( ! apply_filters( 'contacthub_can_migrate_newsletter_plugin', false ) ) wp_die( esc_html__( 'The Newsletter Plugin migration is available with ContactHub Plus.', 'contacthub' ), '', array( 'response' => 403 ) );
		$table = self::newsletter_plugin_table();
		if ( ! $table || ! ContactHub_Store::ready() ) {
			self::database_redirect( 'error', __( 'The newsletter subscriber source or the ContactHub database is not available.', 'contacthub' ) );
		}
		global $wpdb;
		$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- verified WordPress-prefixed plugin table.
		$wanted = array_values( array_intersect( array( 'id', 'email', 'name', 'surname', 'status', 'created', 'token' ), $columns ) );
		if ( ! in_array( 'email', $wanted, true ) ) {
			self::database_redirect( 'error', __( 'The detected newsletter table does not contain a compatible email field.', 'contacthub' ) );
		}
		$quoted = '`' . implode( '`,`', $wanted ) . '`';
		$rows = (array) $wpdb->get_results( "SELECT {$quoted} FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- columns are allow-listed above.
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'run';
		$preview_only = 'preview' === $mode;
		$report = array( 'new' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'subscribers' => count( $rows ), 'completed_at' => current_time( 'mysql' ) );
		foreach ( $rows as $row ) {
			$email = sanitize_email( $row['email'] ?? '' );
			if ( ! is_email( $email ) ) { ++$report['skipped']; continue; }
			$status_code = strtoupper( sanitize_key( $row['status'] ?? '' ) );
			$status_map = array( 'C' => 'confirmed', 'U' => 'unconfirmed', 'S' => 'unsubscribed', 'B' => 'bounced' );
			$incoming = $status_map[ $status_code ] ?? 'unconfirmed';
			$contact = ContactHub_Store::contact_by_email( $email );
			$data = array(
				'email'      => $email,
				'first_name' => sanitize_text_field( $row['name'] ?? '' ),
				'last_name'  => sanitize_text_field( $row['surname'] ?? '' ),
				'source'     => 'newsletter_plugin',
				'meta'       => array( 'newsletter_plugin_id' => absint( $row['id'] ?? 0 ), 'newsletter_plugin_status' => $status_code ),
			);
			if ( $contact ) {
				if ( $preview_only ) { ++$report['updated']; continue; }
				$current = sanitize_key( $contact['optin_status'] ?? '' );
				$was_confirmed = 'confirmed' === $current;
				if ( in_array( $current, array( 'unsubscribed', 'bounced', 'spam' ), true ) && 'confirmed' === $incoming ) $incoming = $current;
				$data['first_name'] = $contact['first_name'] ?: $data['first_name'];
				$data['last_name'] = $contact['last_name'] ?: $data['last_name'];
				$data['optin_status'] = $incoming;
				ContactHub_Store::update_contact( $contact['id'], $data );
				if ( 'confirmed' === $incoming && ! $was_confirmed ) ContactHub_Store::record_consent( $contact['id'], 'granted', 'newsletter_plugin_import', self::valid_import_date( $row['created'] ?? '' ), __( 'Imported newsletter subscription.', 'contacthub' ) );
				++$report['updated'];
			} else {
				if ( $preview_only ) { ++$report['new']; continue; }
				$data['optin_status'] = $incoming;
				$id = ContactHub_Store::create_contact( $data );
				if ( is_wp_error( $id ) || ! $id ) { ++$report['errors']; continue; }
				if ( 'confirmed' === $incoming ) ContactHub_Store::record_consent( $id, 'granted', 'newsletter_plugin_import', self::valid_import_date( $row['created'] ?? '' ), __( 'Imported newsletter subscription.', 'contacthub' ) );
				++$report['new'];
			}
		}
		$report = (array) apply_filters( 'contacthub_newsletter_plugin_migration_report', $report, $preview_only, $table );
		if ( $preview_only ) {
			set_transient( 'contacthub_newsletter_plugin_preview_' . get_current_user_id(), $report, HOUR_IN_SECONDS );
			self::database_redirect( 'success', __( 'The newsletter import preview is ready. No source or ContactHub data has been changed.', 'contacthub' ) );
		}
		delete_transient( 'contacthub_newsletter_plugin_preview_' . get_current_user_id() );
		update_option( 'contacthub_last_newsletter_plugin_migration_report', $report, false );
		self::database_redirect( 'success', sprintf( __( 'Newsletter subscriber import completed: %1$d new contacts, %2$d updated contacts, %3$d skipped.', 'contacthub' ), $report['new'], $report['updated'], $report['skipped'] ) );
	}

	private static function valid_import_date( $value ) {
		$date = substr( sanitize_text_field( (string) $value ), 0, 10 );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : current_time( 'Y-m-d' );
	}

	public static function render_duplicate_preview() {
		if ( ! ContactHub_Store::ready() ) return;
		$candidates = ContactHub_Store::duplicate_groups( 50 );
		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Duplicate review', 'contacthub' ) . '</h2><p>' . esc_html__( 'ContactHub lists likely duplicates without changing them. Use the merge tool below to select a primary record and confirm every conflict.', 'contacthub' ) . '</p>';
		if ( ! $candidates ) {
			echo '<p>' . esc_html__( 'No likely duplicates were found using phone number, WordPress account, or matching name and address.', 'contacthub' ) . '</p></section>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Possible duplicate', 'contacthub' ) . '</th><th>' . esc_html__( 'Match', 'contacthub' ) . '</th><th>' . esc_html__( 'Review', 'contacthub' ) . '</th></tr></thead><tbody>';
		foreach ( $candidates as $group ) {
			$names = array_values( $group['names'] );
			$primary = array_shift( $names );
			echo '<tr><td><strong>' . esc_html( $primary ) . '</strong>' . ( $names ? '<br><span class="contacthub-muted">' . esc_html( implode( ', ', $names ) ) . '</span>' : '' ) . '</td><td>' . esc_html( implode( ', ', $group['reasons'] ) ) . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_merge_group">';
			foreach ( $group['ids'] as $id ) echo '<input type="hidden" name="ids[]" value="' . esc_attr( $id ) . '">';
			wp_nonce_field( 'contacthub_merge_group' ); echo '<button class="button">' . esc_html__( 'Edit duplicates', 'contacthub' ) . '</button></form></td></tr>';
		}
		echo '</tbody></table></section>';
	}

	private static function render_integrations_settings() {
		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Integrations', 'contacthub' ) . '</h2><p>' . esc_html__( 'Connected systems remain the owners of their source data. ContactHub Basic displays compatible data without changing it.', 'contacthub' ) . '</p></section>';
		if ( class_exists( 'WooCommerce' ) ) {
			echo '<section class="contacthub-card contacthub-settings-card"><h2>WooCommerce</h2><p>' . esc_html__( 'WooCommerce is active. Orders and customer information can be displayed in ContactHub; existing orders, invoices, prices, and WooCommerce records are never changed.', 'contacthub' ) . '</p>';
			self::render_plus_promotion( 'woocommerce' ); echo '</section>';
		}
		if ( self::newsletter_plugin_installed() ) self::render_newsletter_plugin_readonly();
		if ( self::groundhogg_active() ) echo '<section class="contacthub-card contacthub-settings-card"><h2>Groundhogg</h2><p>' . esc_html__( 'Groundhogg is active and can remain the leading contact data source.', 'contacthub' ) . '</p></section>';
		do_action( 'contacthub_render_integrations_settings' );
	}

	private static function render_newsletter_plugin_readonly() {
		global $wpdb;
		$email_table = $wpdb->prefix . 'newsletter_emails';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $email_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		echo '<section class="contacthub-card contacthub-settings-card"><h2>The Newsletter Plugin</h2><p>' . esc_html__( 'The Newsletter Plugin is active. ContactHub Basic provides a read-only overview; the source plugin remains unchanged.', 'contacthub' ) . '</p>';
		if ( $exists ) {
			$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$email_table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$title_column = in_array( 'subject', $columns, true ) ? 'subject' : ( in_array( 'name', $columns, true ) ? 'name' : 'id' );
			$sent_column = in_array( 'sent', $columns, true ) ? 'sent' : ( in_array( 'total', $columns, true ) ? 'total' : '0' );
			$open_column = in_array( 'open', $columns, true ) ? 'open' : ( in_array( 'opens', $columns, true ) ? 'opens' : '0' );
			$rows = (array) $wpdb->get_results( "SELECT id, `{$title_column}` AS title, {$sent_column} AS sent, {$open_column} AS opened FROM `{$email_table}` ORDER BY id DESC LIMIT 20", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Mailing', 'contacthub' ) . '</th><th>' . esc_html__( 'Delivered', 'contacthub' ) . '</th><th>' . esc_html__( 'Opened', 'contacthub' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $row ) echo '<tr><td>' . esc_html( $row['title'] ?: sprintf( __( 'Mailing #%d', 'contacthub' ), $row['id'] ) ) . '</td><td>' . esc_html( absint( $row['sent'] ) ) . '</td><td>' . esc_html( absint( $row['opened'] ) ) . '</td></tr>';
			if ( ! $rows ) echo '<tr><td colspan="3">' . esc_html__( 'No previous mailings were found.', 'contacthub' ) . '</td></tr>';
			echo '</tbody></table>';
		}
		self::render_plus_promotion( 'newsletter' ); echo '</section>';
	}

	public static function render_plus_promotion( $context = '' ) {
		if ( ! current_user_can( 'manage_options' ) || defined( 'CONTACTHUB_PLUS_VERSION' ) ) return;
		$url = apply_filters( 'contacthub_plus_url', 'https://contacthub.com/', $context );
		echo '<div class="contacthub-plus-promotion"><p><strong>' . esc_html__( 'Manage this data centrally in ContactHub Plus.', 'contacthub' ) . '</strong> ' . esc_html__( 'Save time and costs, avoid duplicate work, and unlock additional automation, newsletter, and reporting options.', 'contacthub' ) . '</p><p><a class="button button-primary" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Learn about ContactHub Plus', 'contacthub' ) . '</a></p></div>';
	}

	private static function woocommerce_import_form( $mode, $label, $class ) {
		echo '<form class="contacthub-inline-action-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_import_woocommerce"><input type="hidden" name="mode" value="' . esc_attr( $mode ) . '">';
		wp_nonce_field( 'contacthub_import_woocommerce' );
		echo '<button type="submit" class="button ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	public static function handle_import_woocommerce() {
		self::guard_database_action( 'contacthub_import_woocommerce' );
		$mode = sanitize_key( wp_unslash( $_POST['mode'] ?? 'preview' ) );
		try {
			$result = ContactHub_Store::import_woocommerce( 'run' !== $mode );
		} catch ( Throwable $error ) {
			self::database_redirect( 'error', __( 'The WooCommerce import could not be completed. WooCommerce data was not changed. Please review the ContactHub data before trying again.', 'contacthub' ) );
		}
		if ( is_wp_error( $result ) ) self::database_redirect( 'error', $result->get_error_message() );
		if ( 'run' !== $mode ) {
			set_transient( 'contacthub_woocommerce_import_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS );
			self::database_redirect( 'success', __( 'The preview is ready. Review the totals before starting the import.', 'contacthub' ) );
		}
		delete_transient( 'contacthub_woocommerce_import_' . get_current_user_id() );
		self::database_redirect( 'success', sprintf( __( 'WooCommerce import completed: %1$d new contacts, %2$d updated contacts, %3$d new companies, %4$d skipped.', 'contacthub' ), absint( $result['new'] ), absint( $result['updated'] ), absint( $result['companies'] ), absint( $result['skipped'] ) ) );
	}

	private static function render_theme_settings( $settings ) {
		$theme = wp_get_theme();
		$name = $theme->get( 'Name' ) ? $theme->get( 'Name' ) : $theme->get_stylesheet();
		$active = ! empty( $settings['theme_styles'] );
		echo '<section class="contacthub-card contacthub-settings-card contacthub-theme-settings"><h2>' . esc_html__( 'Appearance', 'contacthub' ) . '</h2>';
		echo '<div class="contacthub-active-theme"><span><strong>' . esc_html__( 'Active theme', 'contacthub' ) . '</strong><small>' . esc_html( $name ) . '</small></span><span class="contacthub-status-badge is-positive">' . ( $active ? esc_html( sprintf( __( '%s theme style', 'contacthub' ), $name ) ) : esc_html__( 'ContactHub style', 'contacthub' ) ) . '</span></div>';
		echo '<p class="description">' . esc_html__( 'ContactHub can inherit the theme typography, colors, form fields, buttons, borders, and corner radii. Its functional layout remains intact.', 'contacthub' ) . '</p>';
		echo '<form method="post" action="options.php">'; settings_fields( 'contacthub_general' );
		echo '<div class="contacthub-style-switch"><label class="contacthub-style-option"><input type="radio" name="' . esc_attr( self::GENERAL_OPTION ) . '[theme_styles]" value="0"' . checked( ! $active, true, false ) . '><span><strong>' . esc_html__( 'ContactHub style', 'contacthub' ) . '</strong><small>' . esc_html__( 'Use the neutral ContactHub form and account design.', 'contacthub' ) . '</small></span></label>';
		echo '<label class="contacthub-style-option"><input type="radio" name="' . esc_attr( self::GENERAL_OPTION ) . '[theme_styles]" value="1"' . checked( $active, true, false ) . '><span><strong>' . esc_html( sprintf( __( '%s theme style', 'contacthub' ), $name ) ) . '</strong><small>' . esc_html__( 'Inherit compatible typography, colors, fields, buttons, borders, and spacing from the active theme.', 'contacthub' ) . '</small></span></label></div>';
		submit_button( __( 'Save appearance', 'contacthub' ) ); echo '</form></section>';
	}

	public static function handle_adopt_theme_styles() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'contacthub' ) );
		}
		check_admin_referer( 'contacthub_adopt_theme_styles' );
		$theme = wp_get_theme();
		$settings = self::general_settings();
		$settings['theme_styles'] = 1;
		$settings['theme_stylesheet'] = sanitize_key( $theme->get_stylesheet() );
		$settings['theme_name'] = sanitize_text_field( $theme->get( 'Name' ) ? $theme->get( 'Name' ) : $theme->get_stylesheet() );
		update_option( self::GENERAL_OPTION, $settings, false );
		set_transient( 'contacthub_database_notice_' . get_current_user_id(), array( 'type' => 'success', 'message' => sprintf( __( 'ContactHub now adopts the styles from %s.', 'contacthub' ), $settings['theme_name'] ) ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=contacthub-settings&tab=general' ) );
		exit;
	}

	private static function render_database_tools( $schema ) {
		$counts = ContactHub_Store::counts();
		$report = (array) get_option( 'contacthub_last_migration_report', array() );
		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'ContactHub database', 'contacthub' ) . '</h2>';
		if ( ! ContactHub_Store::ready() ) {
			echo '<p>' . esc_html__( 'The ContactHub database tables do not exist yet or require an update. Creating or updating them does not change Groundhogg or any connected plugin.', 'contacthub' ) . '</p>';
			self::admin_action_form( 'contacthub_setup_standalone', __( 'Create or update ContactHub database and activate', 'contacthub' ), 'button-primary' );
		} else {
			echo '<div class="contacthub-database-counts"><span><strong>' . esc_html( $counts['contacts'] ) . '</strong>' . esc_html__( 'Contacts', 'contacthub' ) . '</span><span><strong>' . esc_html( $counts['companies'] ) . '</strong>' . esc_html__( 'Companies', 'contacthub' ) . '</span><span><strong>' . esc_html( $counts['tags'] ) . '</strong>' . esc_html__( 'Tags', 'contacthub' ) . '</span><span><strong>' . esc_html( $counts['activities'] ) . '</strong>' . esc_html__( 'Activities', 'contacthub' ) . '</span></div>';
			echo '<div class="contacthub-database-actions">';
			if ( self::groundhogg_active() ) self::admin_action_form( 'contacthub_migrate_groundhogg', __( 'Migrate data from Groundhogg', 'contacthub' ), 'button-primary', __( 'Groundhogg remains read only. Contacts, companies, tags, and—with ContactHub Plus—mail templates and completed broadcasts are imported without duplicates.', 'contacthub' ) );
			self::admin_action_form( 'contacthub_reset_standalone', __( 'Reset ContactHub data', 'contacthub' ), 'button-secondary contacthub-reset-test-data', __( 'Deletes ContactHub CRM records and imported Groundhogg newsletter archives. ContactHub-created newsletter drafts, Groundhogg, WordPress, WooCommerce, invoices, and events remain untouched.', 'contacthub' ), true );
			echo '</div>';
			if ( $report ) {
				echo '<div class="contacthub-migration-report"><h3>' . esc_html__( 'Last migration', 'contacthub' ) . '</h3><p>' . esc_html( sprintf( __( '%1$d contacts, %2$d companies, %3$d tags, %4$d tag assignments, %5$d skipped, %6$d errors. Completed: %7$s', 'contacthub' ), absint( $report['contacts'] ?? 0 ), absint( $report['companies'] ?? 0 ), absint( $report['tags'] ?? 0 ), absint( $report['assignments'] ?? 0 ), absint( $report['skipped'] ?? 0 ), absint( $report['errors'] ?? 0 ), sanitize_text_field( $report['completed_at'] ?? '' ) ) ) . '</p>';
				if ( isset( $report['source_contacts'] ) ) {
					echo '<p>' . esc_html( sprintf( __( 'Groundhogg contact comparison: %1$d found in the source, %2$d imported or updated.', 'contacthub' ), absint( $report['source_contacts'] ), absint( $report['contacts'] ?? 0 ) ) ) . '</p>';
				}
				if ( isset( $report['source_tags'] ) ) {
					echo '<p>' . esc_html( sprintf( __( 'Tag comparison: %1$d in Groundhogg, %2$d in ContactHub; %3$d source assignments processed.', 'contacthub' ), absint( $report['source_tags'] ), absint( $report['tags'] ?? 0 ), absint( $report['source_assignments'] ?? 0 ) ) ) . '</p>';
				}
				if ( isset( $report['email_templates'] ) || isset( $report['broadcasts'] ) ) {
					echo '<p>' . esc_html( sprintf( __( 'Newsletter archive: %1$d email templates and %2$d completed broadcasts imported; %3$d errors.', 'contacthub' ), absint( $report['email_templates'] ?? 0 ), absint( $report['broadcasts'] ?? 0 ), absint( $report['newsletter_errors'] ?? 0 ) ) ) . '</p>';
					if ( isset( $report['broadcast_recipients'] ) ) {
						echo '<p>' . esc_html( sprintf( __( '%d historical broadcast recipient assignments were imported where Groundhogg made them available.', 'contacthub' ), absint( $report['broadcast_recipients'] ) ) ) . '</p>';
					}
				}
				if ( ! empty( $report['missing_tags'] ) && is_array( $report['missing_tags'] ) ) {
					echo '<details><summary>' . esc_html( sprintf( __( 'Tag warnings: %d', 'contacthub' ), count( $report['missing_tags'] ) ) ) . '</summary><ul>';
					foreach ( array_slice( $report['missing_tags'], 0, 100 ) as $warning ) {
						echo '<li>' . esc_html( $warning ) . '</li>';
					}
					echo '</ul></details>';
				}
				if ( ! empty( $report['skipped_tags'] ) && is_array( $report['skipped_tags'] ) ) {
					echo '<details><summary>' . esc_html( sprintf( __( 'Intentionally omitted system tags: %d', 'contacthub' ), count( $report['skipped_tags'] ) ) ) . '</summary><ul>';
					foreach ( array_slice( $report['skipped_tags'], 0, 100 ) as $warning ) {
						echo '<li>' . esc_html( $warning ) . '</li>';
					}
					echo '</ul></details>';
				}
				echo '</div>';
			}
		}
		echo '</section>';
	}

	private static function admin_action_form( $action, $label, $class = '', $description = '', $danger = false ) {
		echo '<form class="contacthub-inline-action-form' . ( $danger ? ' contacthub-reset-form' : '' ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( $action );
		if ( $danger ) echo '<input type="hidden" name="confirm_reset" value="1">';
		echo '<button type="' . ( $danger ? 'button' : 'submit' ) . '" class="button ' . esc_attr( $class ) . '"' . ( $danger ? ' data-contacthub-reset-open' : '' ) . '>' . esc_html( $label ) . '</button>';
		if ( $description ) echo '<p class="description">' . esc_html( $description ) . '</p>';
		echo '</form>';
		if ( $danger ) {
			echo '<div class="contacthub-consent-modal contacthub-reset-modal" hidden><div class="contacthub-modal-panel" role="dialog" aria-modal="true" aria-labelledby="contacthub-reset-title"><h2 id="contacthub-reset-title">' . esc_html__( 'Permanently delete ContactHub data?', 'contacthub' ) . '</h2><p><strong>' . esc_html__( 'This action cannot be undone.', 'contacthub' ) . '</strong></p><p>' . esc_html__( 'ContactHub CRM records and imported newsletter archives will be deleted. ContactHub-created email drafts, WordPress users, WooCommerce orders and invoices, Groundhogg data, and event data remain unchanged.', 'contacthub' ) . '</p><div class="contacthub-modal-actions"><button type="button" class="button contacthub-reset-cancel">' . esc_html__( 'Cancel', 'contacthub' ) . '</button><button type="button" class="button button-primary contacthub-reset-confirm">' . esc_html__( 'Permanently delete data', 'contacthub' ) . '</button></div></div></div>';
		}
	}

	public static function handle_setup_standalone() {
		self::guard_database_action( 'contacthub_setup_standalone' );
		$result = ContactHub_Schema::install();
		if ( is_wp_error( $result ) ) self::database_redirect( 'error', $result->get_error_message() );
		$settings = self::general_settings(); $settings['data_source'] = 'standalone'; update_option( self::GENERAL_OPTION, $settings, false );
		self::database_redirect( 'success', __( 'The ContactHub database was created and activated.', 'contacthub' ) );
	}

	public static function handle_migrate_groundhogg() {
		self::guard_database_action( 'contacthub_migrate_groundhogg' );
		$result = ContactHub_Store::migrate_groundhogg();
		if ( is_wp_error( $result ) ) self::database_redirect( 'error', $result->get_error_message() );
		self::database_redirect( 'success', __( 'The Groundhogg migration was completed. Review the migration report before switching modes.', 'contacthub' ) );
	}

	public static function handle_reset_standalone() {
		self::guard_database_action( 'contacthub_reset_standalone' );
		if ( empty( $_POST['confirm_reset'] ) ) self::database_redirect( 'error', __( 'The required deletion confirmation was not provided.', 'contacthub' ) );
		$result = ContactHub_Schema::reset_data();
		if ( is_wp_error( $result ) ) self::database_redirect( 'error', $result->get_error_message() );
		self::database_redirect( 'success', __( 'Only ContactHub data was deleted. The empty ContactHub database is ready for a new migration.', 'contacthub' ) );
	}

	private static function guard_database_action( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission to perform this action.', 'contacthub' ) );
		check_admin_referer( $action );
	}

	private static function database_redirect( $type, $message ) {
		set_transient( 'contacthub_database_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=contacthub-settings&tab=other&other_section=migration' ) ); exit;
	}

	private static function render_preserved_integrations() {
		$integrations = self::installed_integrations();
		if ( ! $integrations ) {
			return;
		}
		echo '<div class="contacthub-preserved-integrations"><h3>' . esc_html__( 'Data preserved in detected plugins', 'contacthub' ) . '</h3>';
		foreach ( $integrations as $integration ) {
			echo '<div class="contacthub-preserved-row"><span><strong>' . esc_html( $integration['name'] ) . '</strong><small>' . esc_html( $integration['message'] ) . '</small></span><span class="contacthub-status-badge ' . ( $integration['active'] ? 'is-positive' : 'is-negative' ) . '">' . ( $integration['active'] ? esc_html__( 'Active', 'contacthub' ) : esc_html__( 'Installed, inactive', 'contacthub' ) ) . '</span></div>';
		}
		echo '</div>';
	}

	private static function installed_integrations() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		$definitions = array(
			array( 'name' => 'Groundhogg', 'needles' => array( 'groundhogg' ), 'message' => __( 'Groundhogg contacts, companies, tags, funnels, and activities remain untouched.', 'contacthub' ) ),
			array( 'name' => 'WooCommerce', 'needles' => array( 'woocommerce' ), 'message' => __( 'WordPress accounts, customer profiles, and orders remain untouched.', 'contacthub' ) ),
			array( 'name' => 'German Market', 'needles' => array( 'german market' ), 'message' => __( 'Invoices and invoice documents remain untouched.', 'contacthub' ) ),
			array( 'name' => 'B2B Market', 'needles' => array( 'b2b market' ), 'message' => __( 'Customer groups, roles, prices, and tax rules remain untouched.', 'contacthub' ) ),
			array( 'name' => 'The Events Calendar', 'needles' => array( 'the events calendar' ), 'message' => __( 'Events and venue data remain untouched.', 'contacthub' ) ),
			array( 'name' => 'Event Tickets', 'needles' => array( 'event tickets' ), 'message' => __( 'Tickets, attendees, and check-ins remain untouched.', 'contacthub' ) ),
		);
		$result = array();
		foreach ( $definitions as $definition ) {
			$matched_files = array();
			foreach ( $plugins as $file => $plugin ) {
				$haystack = strtolower( (string) ( $plugin['Name'] ?? '' ) . ' ' . $file . ' ' . ( $plugin['TextDomain'] ?? '' ) );
				foreach ( $definition['needles'] as $needle ) {
					if ( false !== strpos( $haystack, $needle ) ) {
						$matched_files[] = $file;
						break;
					}
				}
			}
			if ( ! $matched_files ) {
				continue;
			}
			$active = false;
			foreach ( $matched_files as $file ) {
				if ( is_plugin_active( $file ) ) {
					$active = true;
					break;
				}
			}
			$result[] = array( 'name' => $definition['name'], 'message' => $definition['message'], 'active' => $active );
		}
		return $result;
	}

	private static function checkbox_setting( $key, $label, $description, $settings ) {
		echo '<div class="contacthub-setting-row"><label><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="1"' . checked( ! empty( $settings[ $key ] ), true, false ) . '> <strong>' . esc_html( $label ) . '</strong></label><p class="description">' . esc_html( $description ) . '</p></div>';
	}

	private static function page_setting( $key, $label, $settings ) {
		echo '<div class="contacthub-setting-row"><label for="contacthub-' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label>';
		wp_dropdown_pages(
			array(
				'name'              => self::OPTION . '[' . $key . ']',
				'id'                => 'contacthub-' . $key,
				'show_option_none'  => __( '— Select page —', 'contacthub' ),
				'option_none_value' => 0,
				'sort_column'       => 'post_title',
				'selected'          => absint( $settings[ $key ] ),
			)
		);
		echo '</div>';
	}

	private static function text_setting( $key, $label, $settings ) {
		echo '<div class="contacthub-setting-row"><label for="contacthub-' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><input id="contacthub-' . esc_attr( $key ) . '" class="large-text" type="text" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $settings[ $key ] ) . '"></div>';
	}

	private static function textarea_setting( $key, $label, $description, $settings ) {
		echo '<div class="contacthub-setting-row"><label for="contacthub-' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><textarea id="contacthub-' . esc_attr( $key ) . '" class="large-text" rows="6" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']">' . esc_textarea( $settings[ $key ] ) . '</textarea><p class="description">' . esc_html( $description ) . '</p></div>';
	}

	private static function render_integration_status() {
		$integrations = array(
			'WordPress'    => true,
			'WooCommerce'  => class_exists( 'WooCommerce' ),
			'German Market' => defined( 'WGM_PLUGIN_VERSION' ) || class_exists( 'Woocommerce_German_Market' ),
			'B2B Market'   => defined( 'BM_VERSION' ) || class_exists( 'BM' ) || class_exists( 'B2B_Market' ),
		);
		echo '<section class="contacthub-card"><h2>' . esc_html__( 'Integration status', 'contacthub' ) . '</h2><div class="contacthub-integration-grid">';
		foreach ( $integrations as $name => $active ) {
			echo '<div><strong>' . esc_html( $name ) . '</strong><span class="contacthub-status-badge ' . ( $active ? 'is-positive' : 'is-negative' ) . '">' . ( $active ? esc_html__( 'Available', 'contacthub' ) : esc_html__( 'Not detected', 'contacthub' ) ) . '</span></div>';
		}
		echo '</div><p class="description">' . esc_html__( 'Registration data is stored in compatible WordPress and WooCommerce profile fields. Existing orders and invoices are never changed.', 'contacthub' ) . '</p></section>';
	}

	private static function render_pending_registrations() {
		$settings = self::settings();
		$roles = self::allowed_roles();
		$configured_roles = array_intersect_key( $roles, array_flip( (array) $settings['allowed_roles'] ) );
		$users = get_users(
			array(
				'meta_key'   => 'contacthub_business_status',
				'meta_value' => 'pending',
				'number'     => 100,
				'orderby'    => 'registered',
				'order'      => 'ASC',
			)
		);
		echo '<section class="contacthub-card"><div class="contacthub-toolbar"><h2>' . esc_html__( 'Pending business registrations', 'contacthub' ) . '</h2><span class="contacthub-count">' . esc_html( count( $users ) ) . '</span></div>';
		if ( ! $users ) {
			echo '<p class="contacthub-muted">' . esc_html__( 'There are currently no registrations awaiting approval.', 'contacthub' ) . '</p></section>';
			return;
		}
		echo '<table class="widefat striped contacthub-table"><thead><tr><th>' . esc_html__( 'Person', 'contacthub' ) . '</th><th>' . esc_html__( 'Company', 'contacthub' ) . '</th><th>' . esc_html__( 'Requested role', 'contacthub' ) . '</th><th>' . esc_html__( 'VAT ID', 'contacthub' ) . '</th><th>' . esc_html__( 'Email confirmed', 'contacthub' ) . '</th><th>' . esc_html__( 'Registered', 'contacthub' ) . '</th><th>' . esc_html__( 'Actions', 'contacthub' ) . '</th></tr></thead><tbody>';
		foreach ( $users as $user ) {
			$requested_role = sanitize_key( get_user_meta( $user->ID, 'contacthub_requested_role', true ) );
			$role_label = isset( $roles[ $requested_role ] ) ? $roles[ $requested_role ] : __( 'No longer available', 'contacthub' );
			$email_confirmed = (bool) get_user_meta( $user->ID, 'contacthub_email_verified', true );
			$role_can_be_approved = isset( $configured_roles[ $requested_role ] );
			echo '<tr><td><strong>' . esc_html( $user->display_name ) . '</strong><br><a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a></td><td>' . esc_html( get_user_meta( $user->ID, 'billing_company', true ) ) . '</td><td>' . esc_html( $role_label ) . '</td><td>' . esc_html( get_user_meta( $user->ID, 'billing_vat', true ) ) . '</td><td>' . ( get_user_meta( $user->ID, 'contacthub_email_verified', true ) ? esc_html__( 'Yes', 'contacthub' ) : esc_html__( 'No', 'contacthub' ) ) . '</td><td>' . esc_html( get_date_from_gmt( $user->user_registered, get_option( 'date_format' ) ) ) . '</td><td><div class="contacthub-registration-actions">';
			if ( $role_can_be_approved && $email_confirmed ) {
				self::decision_form( $user->ID, 'approve', __( 'Approve', 'contacthub' ), 'button-primary' );
			} elseif ( ! $email_confirmed ) {
				echo '<span class="contacthub-muted">' . esc_html__( 'Email confirmation is still pending.', 'contacthub' ) . '</span>';
			} else {
				echo '<span class="contacthub-muted">' . esc_html__( 'Select this role again in the settings before approval.', 'contacthub' ) . '</span>';
			}
			self::decision_form( $user->ID, 'reject', __( 'Reject', 'contacthub' ), '' );
			if ( ! get_user_meta( $user->ID, 'contacthub_email_verified', true ) ) {
				self::resend_form( $user->ID );
			}
			echo '</div></td></tr>';
		}
		echo '</tbody></table></section>';
	}

	private static function decision_form( $user_id, $decision, $label, $class ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_registration_decision"><input type="hidden" name="user_id" value="' . esc_attr( $user_id ) . '"><input type="hidden" name="decision" value="' . esc_attr( $decision ) . '">';
		wp_nonce_field( 'contacthub_registration_decision_' . $user_id );
		echo '<button class="button ' . esc_attr( $class ) . '" type="submit">' . esc_html( $label ) . '</button></form>';
	}

	private static function resend_form( $user_id ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_registration_resend"><input type="hidden" name="user_id" value="' . esc_attr( $user_id ) . '">';
		wp_nonce_field( 'contacthub_registration_resend_' . $user_id );
		echo '<button class="button" type="submit">' . esc_html__( 'Resend confirmation', 'contacthub' ) . '</button></form>';
	}

	public static function shortcode( $atts = array() ) {
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) ) {
			return current_user_can( 'manage_options' ) ? '<div class="contacthub-registration-notice is-error">' . esc_html__( 'The ContactHub registration form is disabled in the settings.', 'contacthub' ) . '</div>' : '';
		}
		if ( is_user_logged_in() ) {
			return '<div class="contacthub-registration-notice">' . esc_html__( 'You are already signed in.', 'contacthub' ) . '</div>';
		}
		$atts = shortcode_atts( array( 'type' => '' ), $atts, 'contacthub_registration' );
		$type = sanitize_key( $atts['type'] );
		if ( ! in_array( $type, array( 'customer', 'business' ), true ) ) {
			$type = isset( $_GET['contacthub_registration_type'] ) && 'business' === sanitize_key( wp_unslash( $_GET['contacthub_registration_type'] ) ) ? 'business' : 'customer'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display choice only.
		}
		if ( 'business' === $type && empty( $settings['business_enabled'] ) ) {
			$type = 'customer';
		}
		$available_roles = array_intersect_key( self::allowed_roles(), array_flip( (array) $settings['allowed_roles'] ) );
		if ( 'business' === $type && ! $available_roles ) {
			return '<div class="contacthub-registration-notice is-error">' . esc_html__( 'Registration is currently unavailable because no customer role has been configured.', 'contacthub' ) . '</div>';
		}
		wp_enqueue_style( 'contacthub-registration', CONTACTHUB_URL . 'assets/contacthub-registration.css', array(), CONTACTHUB_ASSET_VERSION );
		wp_enqueue_script( 'contacthub-registration', CONTACTHUB_URL . 'assets/contacthub-registration.js', array(), CONTACTHUB_ASSET_VERSION, true );
		wp_localize_script(
			'contacthub-registration',
			'CONTACTHUB_REGISTRATION',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'vatNonce' => wp_create_nonce( 'contacthub_registration_validate_vat' ),
				'i18n' => array(
					'checkingVat' => __( 'Checking EU VIES…', 'contacthub' ),
					'vatValidationFailed' => __( 'VAT ID validation could not be completed.', 'contacthub' ),
				),
			)
		);
		$notice = self::consume_notice();
		$countries = self::countries();
		ob_start();
		if ( $notice ) {
			echo self::render_front_notice( $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped markup.
			if ( ! empty( $notice['verification_complete'] ) || ! empty( $notice['confirmation_pending'] ) ) {
				return ob_get_clean();
			}
		}
		$general = self::general_settings();
		$theme_class = '';
		if ( ! empty( $general['theme_styles'] ) ) {
			$theme_class = ' contacthub-use-theme-styles contacthub-theme-' . sanitize_html_class( (string) $general['theme_stylesheet'] );
		}
		if ( ! empty( $settings['business_enabled'] ) && '' === sanitize_key( $atts['type'] ) ) {
			$base = remove_query_arg( 'contacthub_registration_type', self::current_url() );
			$customer_url = add_query_arg( 'contacthub_registration_type', 'customer', $base ) . '#contacthub-account-registration-form';
			$business_url = add_query_arg( 'contacthub_registration_type', 'business', $base ) . '#contacthub-account-registration-form';
			echo '<nav class="contacthub-registration-types" role="tablist" aria-label="' . esc_attr__( 'Registration type', 'contacthub' ) . '"><a role="tab" aria-selected="' . ( 'customer' === $type ? 'true' : 'false' ) . '" class="' . ( 'customer' === $type ? 'is-active' : '' ) . '" href="' . esc_url( $customer_url ) . '">' . esc_html__( 'Private customer', 'contacthub' ) . '</a><a role="tab" aria-selected="' . ( 'business' === $type ? 'true' : 'false' ) . '" class="' . ( 'business' === $type ? 'is-active' : '' ) . '" href="' . esc_url( $business_url ) . '">' . esc_html__( 'Business customer', 'contacthub' ) . '</a></nav>';
		}
		echo '<form class="contacthub-registration-form' . esc_attr( $theme_class ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_register"><input type="hidden" name="registration_type" value="' . esc_attr( $type ) . '"><input type="hidden" name="redirect_to" value="' . esc_url( self::current_url() ) . '">';
		wp_nonce_field( 'contacthub_register', 'contacthub_registration_nonce' );
		echo '<div class="contacthub-registration-trap" aria-hidden="true"><label>Website<input type="text" name="contacthub_website" tabindex="-1" autocomplete="off"></label></div>';
		self::form_section_account();
		self::form_section_person();
		if ( 'business' === $type ) self::form_section_company( $settings );
		self::form_section_address( 'billing', __( 'Billing address', 'contacthub' ), $countries, true );
		self::form_section_shipping( $countries );
		if ( 'business' === $type ) self::form_section_role( $settings );
		self::form_section_consent( $settings );
		echo '<button class="contacthub-registration-submit" type="submit">' . esc_html( 'business' === $type ? __( 'Submit business registration', 'contacthub' ) : __( 'Create customer account', 'contacthub' ) ) . '</button></form>';
		return ob_get_clean();
	}

	public static function newsletter_shortcode() {
		$settings = self::settings();
		if ( empty( $settings['newsletter_enabled'] ) || ! ContactHub_Store::standalone() ) {
			return current_user_can( 'manage_options' ) ? '<div class="contacthub-registration-notice is-error">' . esc_html__( 'The separate ContactHub newsletter signup is available only when the ContactHub database is the active data source.', 'contacthub' ) . '</div>' : '';
		}
		wp_enqueue_style( 'contacthub-registration', CONTACTHUB_URL . 'assets/contacthub-registration.css', array(), CONTACTHUB_ASSET_VERSION );
		$notice = self::consume_notice();
		ob_start();
		if ( $notice ) {
			echo self::render_front_notice( $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped markup.
			if ( ! empty( $notice['confirmation_pending'] ) ) return ob_get_clean();
		}
		$general = self::general_settings();
		$theme_class = ! empty( $general['theme_styles'] ) ? ' contacthub-use-theme-styles contacthub-theme-' . sanitize_html_class( (string) $general['theme_stylesheet'] ) : '';
		echo '<form class="contacthub-registration-form contacthub-newsletter-signup' . esc_attr( $theme_class ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_newsletter_signup"><input type="hidden" name="redirect_to" value="' . esc_url( self::current_url() ) . '">';
		wp_nonce_field( 'contacthub_newsletter_signup', 'contacthub_newsletter_nonce' );
		echo '<div class="contacthub-registration-trap" aria-hidden="true"><label>Website<input type="text" name="contacthub_website" tabindex="-1" autocomplete="off"></label></div><section class="contacthub-form-section"><h3>' . esc_html__( 'Newsletter signup', 'contacthub' ) . '</h3><div class="contacthub-form-grid">';
		self::input( 'first_name', __( 'First name', 'contacthub' ), 'text', true, 'given-name' );
		self::input( 'last_name', __( 'Last name', 'contacthub' ), 'text', false, 'family-name' );
		self::input( 'email', __( 'Email address', 'contacthub' ), 'email', true, 'email' );
		echo '</div>';
		$privacy = self::linked_label( __( 'I have read the privacy policy.', 'contacthub' ), $settings['privacy_page_id'] );
		echo '<label class="contacthub-form-check"><input type="checkbox" name="privacy_accepted" value="1" required> <span>' . wp_kses_post( $privacy ) . '</span></label><label class="contacthub-form-check"><input type="checkbox" name="marketing_consent" value="1" required> <span>' . esc_html__( 'I would like to receive the newsletter and confirm that I can withdraw my consent at any time.', 'contacthub' ) . '</span></label></section><button class="contacthub-registration-submit" type="submit">' . esc_html__( 'Subscribe to newsletter', 'contacthub' ) . '</button></form>';
		return ob_get_clean();
	}

	public static function handle_newsletter_signup() {
		$redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), home_url( '/' ) ) : home_url( '/' );
		if ( ! isset( $_POST['contacthub_newsletter_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['contacthub_newsletter_nonce'] ) ), 'contacthub_newsletter_signup' ) ) self::redirect_notice( $redirect, 'error', __( 'The form session has expired. Please try again.', 'contacthub' ) );
		$settings = self::settings();
		if ( empty( $settings['newsletter_enabled'] ) || ! ContactHub_Store::standalone() || ! empty( $_POST['contacthub_website'] ) ) self::redirect_notice( $redirect, 'error', __( 'The newsletter signup could not be processed.', 'contacthub' ) );
		if ( ! self::rate_limit_ok( 'newsletter_signup', 5 ) ) self::redirect_notice( $redirect, 'error', __( 'Too many signup attempts. Please wait and try again.', 'contacthub' ) );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$last_name = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
		if ( ! is_email( $email ) || '' === $first_name || empty( $_POST['privacy_accepted'] ) || empty( $_POST['marketing_consent'] ) ) self::redirect_notice( $redirect, 'error', __( 'Please complete all required fields and confirm the newsletter consent.', 'contacthub' ) );
		$management_token = self::create_pending_management(
			array(
				'kind'       => 'newsletter',
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'redirect'   => $redirect,
				'source'     => 'newsletter_signup',
			)
		);
		$result = self::send_pending_newsletter_confirmation( $management_token );
		if ( is_wp_error( $result ) ) self::redirect_notice( $redirect, 'error', $result->get_error_message(), self::pending_notice_extra( $management_token, $email, 'newsletter' ) );
		self::redirect_notice( $redirect, 'success', $settings['newsletter_success'], self::pending_notice_extra( $management_token, $email, 'newsletter' ) );
	}

	public static function request_newsletter_optin( $email, $first_name = '', $last_name = '', $redirect = '', $source = 'newsletter_signup' ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) return new WP_Error( 'contacthub_optin_invalid', __( 'The newsletter signup could not be processed.', 'contacthub' ) );
		$result = self::send_newsletter_confirmation(
			array(
				'email'      => $email,
				'first_name' => sanitize_text_field( $first_name ),
				'last_name'  => sanitize_text_field( $last_name ),
				'redirect'   => wp_validate_redirect( $redirect, home_url( '/' ) ),
				'source'     => sanitize_key( $source ),
			)
		);
		return is_wp_error( $result ) ? $result : true;
	}

	private static function send_newsletter_confirmation( $data, $management_key = '' ) {
		$settings = self::settings();
		$token = bin2hex( random_bytes( 32 ) );
		$verification_key = hash( 'sha256', $token );
		$payload = array(
			'email'          => sanitize_email( $data['email'] ?? '' ),
			'first_name'     => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name'      => sanitize_text_field( $data['last_name'] ?? '' ),
			'redirect'       => wp_validate_redirect( $data['redirect'] ?? '', home_url( '/' ) ),
			'source'         => sanitize_key( $data['source'] ?? 'newsletter_signup' ),
			'management_key' => sanitize_key( $management_key ),
		);
		$verification_ttl = max( HOUR_IN_SECONDS, absint( apply_filters( 'contacthub_newsletter_confirmation_ttl', DAY_IN_SECONDS, $payload ) ) );
		set_transient( 'contacthub_newsletter_verify_' . $verification_key, $payload, $verification_ttl );
		$link = add_query_arg( array( 'contacthub_newsletter_verify' => $token ), home_url( '/' ) );
		$replacements = array( '{first_name}' => esc_html( $payload['first_name'] ), '{site_name}' => esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ), '{confirmation_link}' => '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Confirm newsletter subscription', 'contacthub' ) . '</a>' );
		$subject = strtr( $settings['newsletter_subject'], $replacements );
		$message = strtr( wpautop( $settings['newsletter_message'] ), $replacements );
		if ( false === strpos( $settings['newsletter_message'], '{confirmation_link}' ) ) $message .= '<p>' . $replacements['{confirmation_link}'] . '</p>';
		if ( ! wp_mail( $payload['email'], $subject, $message, array( 'Content-Type: text/html; charset=UTF-8' ) ) ) {
			delete_transient( 'contacthub_newsletter_verify_' . $verification_key );
			return new WP_Error( 'contacthub_optin_mail', __( 'The confirmation email could not be sent. Please try again later.', 'contacthub' ) );
		}
		do_action( 'contacthub_newsletter_confirmation_sent', $verification_key, $token, $payload );
		return $verification_key;
	}

	private static function handle_newsletter_verification() {
		if ( empty( $_GET['contacthub_newsletter_verify'] ) ) return false;
		$token = sanitize_text_field( wp_unslash( $_GET['contacthub_newsletter_verify'] ) );
		$key = 'contacthub_newsletter_verify_' . hash( 'sha256', $token );
		$data = get_transient( $key );
		$redirect = is_array( $data ) ? wp_validate_redirect( $data['redirect'] ?? '', home_url( '/' ) ) : home_url( '/' );
		if ( ! is_array( $data ) || empty( $data['email'] ) ) self::redirect_notice( $redirect, 'error', __( 'The confirmation link is invalid or has expired.', 'contacthub' ) );
		delete_transient( $key );
		if ( ! empty( $data['management_key'] ) ) delete_transient( 'contacthub_pending_confirmation_' . sanitize_key( $data['management_key'] ) );
		$source = ! empty( $data['source'] ) ? sanitize_key( $data['source'] ) . '_double_opt_in' : 'newsletter_double_opt_in';
		$contact_id = ContactHub_Plugin::extension_confirm_marketing_optin(
			$data['email'],
			$data['first_name'] ?? '',
			$data['last_name'] ?? '',
			$source,
			__( 'Newsletter consent confirmed through double opt-in.', 'contacthub' )
		);
		if ( is_wp_error( $contact_id ) || ! $contact_id ) self::redirect_notice( $redirect, 'error', is_wp_error( $contact_id ) ? $contact_id->get_error_message() : __( 'The newsletter subscription could not be saved.', 'contacthub' ) );
		self::redirect_notice( $redirect, 'success', self::settings()['newsletter_verified'] );
		return true;
	}

	public static function success_page_notice( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() || empty( $_GET['contacthub_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- opaque one-time display token.
			return $content;
		}
		$settings = self::settings();
		if ( ! $settings['success_page_id'] || get_queried_object_id() !== absint( $settings['success_page_id'] ) ) {
			return $content;
		}
		$notice = self::consume_notice();
		if ( ! $notice ) {
			return $content;
		}
		wp_enqueue_style( 'contacthub-registration', CONTACTHUB_URL . 'assets/contacthub-registration.css', array(), CONTACTHUB_ASSET_VERSION );
		$html = self::render_front_notice( $notice );
		return $html . $content;
	}

	private static function create_pending_management( $data ) {
		$token = bin2hex( random_bytes( 32 ) );
		$key = hash( 'sha256', $token );
		$data = is_array( $data ) ? $data : array();
		$data['kind'] = in_array( $data['kind'] ?? '', array( 'registration', 'newsletter' ), true ) ? $data['kind'] : '';
		$data['email'] = sanitize_email( $data['email'] ?? '' );
		$data['redirect'] = wp_validate_redirect( $data['redirect'] ?? '', home_url( '/' ) );
		$data['created_at'] = time();
		set_transient( 'contacthub_pending_confirmation_' . $key, $data, DAY_IN_SECONDS );
		return $token;
	}

	private static function pending_management( $token ) {
		$token = sanitize_text_field( $token );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) return false;
		$key = hash( 'sha256', $token );
		$data = get_transient( 'contacthub_pending_confirmation_' . $key );
		if ( ! is_array( $data ) || empty( $data['kind'] ) ) return false;
		$data['_key'] = $key;
		return $data;
	}

	private static function save_pending_management( $token, $data ) {
		$key = hash( 'sha256', sanitize_text_field( $token ) );
		unset( $data['_key'] );
		return set_transient( 'contacthub_pending_confirmation_' . $key, $data, DAY_IN_SECONDS );
	}

	private static function pending_notice_extra( $token, $email, $kind ) {
		return array(
			'confirmation_pending' => 1,
			'pending_token'        => sanitize_text_field( $token ),
			'pending_email'        => sanitize_email( $email ),
			'pending_kind'         => sanitize_key( $kind ),
		);
	}

	private static function send_pending_newsletter_confirmation( $management_token ) {
		$data = self::pending_management( $management_token );
		if ( ! $data || 'newsletter' !== $data['kind'] ) return new WP_Error( 'contacthub_pending_invalid', __( 'This confirmation request is no longer available. Please submit the signup form again.', 'contacthub' ) );
		if ( ! empty( $data['verification_key'] ) ) delete_transient( 'contacthub_newsletter_verify_' . sanitize_key( $data['verification_key'] ) );
		$key = $data['_key'];
		$result = self::send_newsletter_confirmation( $data, $key );
		if ( is_wp_error( $result ) ) return $result;
		$data['verification_key'] = $result;
		self::save_pending_management( $management_token, $data );
		return true;
	}

	private static function pending_registration_user( $data ) {
		$user_id = absint( $data['user_id'] ?? 0 );
		$user = $user_id ? get_user_by( 'id', $user_id ) : false;
		$key = $user ? sanitize_key( get_user_meta( $user_id, 'contacthub_pending_management_key', true ) ) : '';
		$expires = $user ? absint( get_user_meta( $user_id, 'contacthub_pending_management_expires', true ) ) : 0;
		if ( ! $user || ! $key || empty( $data['_key'] ) || ! hash_equals( $key, $data['_key'] ) || ! $expires || time() > $expires || get_user_meta( $user_id, 'contacthub_email_verified', true ) ) {
			return new WP_Error( 'contacthub_pending_invalid', __( 'This confirmation request is no longer available. Please submit the signup form again.', 'contacthub' ) );
		}
		return $user;
	}

	public static function handle_pending_resend() {
		$token = isset( $_POST['pending_token'] ) ? sanitize_text_field( wp_unslash( $_POST['pending_token'] ) ) : '';
		$data = self::pending_management( $token );
		$redirect = $data ? wp_validate_redirect( $data['redirect'] ?? '', home_url( '/' ) ) : home_url( '/' );
		if ( ! $data ) self::redirect_notice( $redirect, 'error', __( 'This confirmation request is no longer available. Please submit the signup form again.', 'contacthub' ) );
		$extra = self::pending_notice_extra( $token, $data['email'] ?? '', $data['kind'] );
		if ( ! self::rate_limit_ok( 'pending_resend', 3 ) ) self::redirect_notice( $redirect, 'error', __( 'A confirmation email was requested too often. Please wait a few minutes and try again.', 'contacthub' ), $extra );
		if ( 'registration' === $data['kind'] ) {
			$user = self::pending_registration_user( $data );
			if ( is_wp_error( $user ) ) self::redirect_notice( $redirect, 'error', $user->get_error_message() );
			$data['email'] = $user->user_email;
			self::save_pending_management( $token, $data );
			if ( ! self::issue_confirmation( $user->ID ) ) self::redirect_notice( $redirect, 'error', __( 'The confirmation email could not be sent. Please try again later.', 'contacthub' ), self::pending_notice_extra( $token, $user->user_email, 'registration' ) );
		} else {
			$result = self::send_pending_newsletter_confirmation( $token );
			if ( is_wp_error( $result ) ) self::redirect_notice( $redirect, 'error', $result->get_error_message(), $extra );
		}
		$email = 'registration' === $data['kind'] && isset( $user ) ? $user->user_email : $data['email'];
		self::redirect_notice( $redirect, 'success', __( 'A new confirmation email has been sent.', 'contacthub' ), self::pending_notice_extra( $token, $email, $data['kind'] ) );
	}

	public static function handle_pending_email_change() {
		$token = isset( $_POST['pending_token'] ) ? sanitize_text_field( wp_unslash( $_POST['pending_token'] ) ) : '';
		$data = self::pending_management( $token );
		$redirect = $data ? wp_validate_redirect( $data['redirect'] ?? '', home_url( '/' ) ) : home_url( '/' );
		if ( ! $data ) self::redirect_notice( $redirect, 'error', __( 'This confirmation request is no longer available. Please submit the signup form again.', 'contacthub' ) );
		$old_email = sanitize_email( $data['email'] ?? '' );
		$extra = self::pending_notice_extra( $token, $old_email, $data['kind'] );
		if ( ! self::rate_limit_ok( 'pending_email_change', 3 ) ) self::redirect_notice( $redirect, 'error', __( 'The email address was changed too often. Please wait a few minutes and try again.', 'contacthub' ), $extra );
		$new_email = isset( $_POST['new_email'] ) ? sanitize_email( wp_unslash( $_POST['new_email'] ) ) : '';
		if ( ! is_email( $new_email ) ) self::redirect_notice( $redirect, 'error', __( 'Please enter a valid email address.', 'contacthub' ), $extra );

		if ( 'registration' === $data['kind'] ) {
			$user = self::pending_registration_user( $data );
			if ( is_wp_error( $user ) ) self::redirect_notice( $redirect, 'error', $user->get_error_message() );
			$existing_user = email_exists( $new_email );
			if ( $existing_user && absint( $existing_user ) !== $user->ID ) self::redirect_notice( $redirect, 'error', __( 'A user account already exists for this email address.', 'contacthub' ), $extra );
			$contact = ContactHub_Store::standalone() ? ContactHub_Store::contact_by_email( $user->user_email ) : false;
			$existing_contact = ContactHub_Store::standalone() ? ContactHub_Store::contact_by_email( $new_email, true ) : false;
			if ( $existing_contact && ( ! $contact || absint( $existing_contact['id'] ) !== absint( $contact['id'] ) ) ) self::redirect_notice( $redirect, 'error', __( 'A person with this email address already exists.', 'contacthub' ), $extra );
			$result = wp_update_user( array( 'ID' => $user->ID, 'user_email' => $new_email ) );
			if ( is_wp_error( $result ) ) self::redirect_notice( $redirect, 'error', $result->get_error_message(), $extra );
			update_user_meta( $user->ID, 'billing_email', $new_email );
			if ( $contact ) ContactHub_Store::update_contact( $contact['id'], array( 'email' => $new_email ) );
			$data['email'] = $new_email;
			self::save_pending_management( $token, $data );
			if ( ! self::issue_confirmation( $user->ID ) ) self::redirect_notice( $redirect, 'error', __( 'The email address was changed, but the new confirmation email could not be sent. Please request it again.', 'contacthub' ), self::pending_notice_extra( $token, $new_email, 'registration' ) );
		} else {
			$data['email'] = $new_email;
			self::save_pending_management( $token, $data );
			$result = self::send_pending_newsletter_confirmation( $token );
			if ( is_wp_error( $result ) ) self::redirect_notice( $redirect, 'error', $result->get_error_message(), self::pending_notice_extra( $token, $new_email, 'newsletter' ) );
		}
		self::redirect_notice( $redirect, 'success', __( 'The email address was corrected and a new confirmation email has been sent.', 'contacthub' ), self::pending_notice_extra( $token, $new_email, $data['kind'] ) );
	}

	private static function render_front_notice( $notice ) {
		$type = 'error' === ( $notice['type'] ?? '' ) ? 'error' : 'success';
		$redirect_url = ! empty( $notice['redirect_url'] ) ? esc_url( $notice['redirect_url'] ) : '';
		$delay = isset( $notice['delay'] ) ? absint( $notice['delay'] ) : 0;
		$attributes = $redirect_url ? ' data-contacthub-redirect="' . esc_attr( $redirect_url ) . '" data-contacthub-delay="' . esc_attr( $delay ) . '"' : '';
		$html = '<div class="contacthub-registration-notice is-' . esc_attr( $type ) . '" role="' . ( 'error' === $type ? 'alert' : 'status' ) . '"' . $attributes . '><p class="contacthub-notice-message">' . nl2br( esc_html( $notice['message'] ?? '' ) ) . '</p>';
		if ( ! empty( $notice['confirmation_pending'] ) && ! empty( $notice['pending_token'] ) && is_email( $notice['pending_email'] ?? '' ) ) {
			$token = sanitize_text_field( $notice['pending_token'] );
			$email = sanitize_email( $notice['pending_email'] );
			$html .= '<div class="contacthub-confirmation-pending"><h3>' . esc_html__( 'Please confirm your email address', 'contacthub' ) . '</h3>';
			$html .= '<p>' . sprintf( esc_html__( 'We sent the confirmation email to %s.', 'contacthub' ), '<strong>' . esc_html( $email ) . '</strong>' ) . '</p>';
			$html .= '<p>' . esc_html__( 'If you cannot find the message, please also check your spam folder.', 'contacthub' ) . '</p>';
			$html .= '<div class="contacthub-pending-actions"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_pending_resend"><input type="hidden" name="pending_token" value="' . esc_attr( $token ) . '"><button type="submit" class="contacthub-pending-button">' . esc_html__( 'Send confirmation email again', 'contacthub' ) . '</button></form>';
			$html .= '<details class="contacthub-pending-correction"><summary>' . esc_html__( 'Correct email address', 'contacthub' ) . '</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_pending_email"><input type="hidden" name="pending_token" value="' . esc_attr( $token ) . '"><label><span>' . esc_html__( 'Email address', 'contacthub' ) . '</span><input type="email" name="new_email" value="' . esc_attr( $email ) . '" autocomplete="email" required></label><button type="submit" class="contacthub-pending-button">' . esc_html__( 'Save and send again', 'contacthub' ) . '</button></form></details></div></div>';
		}
		return $html . '</div>';
	}

	private static function form_section_account() {
		echo '<section class="contacthub-form-section"><h3>' . esc_html__( 'User account', 'contacthub' ) . '</h3><div class="contacthub-form-grid">';
		self::input( 'user_login', __( 'Username', 'contacthub' ), 'text', true, 'username' );
		self::input( 'email', __( 'Email address', 'contacthub' ), 'email', true, 'email' );
		self::input( 'password', __( 'Password', 'contacthub' ), 'password', true, 'new-password' );
		self::input( 'password_confirm', __( 'Repeat password', 'contacthub' ), 'password', true, 'new-password' );
		echo '</div></section>';
	}

	private static function form_section_person() {
		echo '<section class="contacthub-form-section"><h3>' . esc_html__( 'Name', 'contacthub' ) . '</h3><div class="contacthub-form-grid">';
		echo '<label><span>' . esc_html__( 'Salutation', 'contacthub' ) . '</span><select name="salutation"><option value="">' . esc_html__( '--- Please select ---', 'contacthub' ) . '</option><option value="neutral">' . esc_html__( 'Neutral', 'contacthub' ) . '</option><option value="mr">' . esc_html__( 'Mr', 'contacthub' ) . '</option><option value="ms">' . esc_html__( 'Ms', 'contacthub' ) . '</option><option value="family">' . esc_html__( 'Family', 'contacthub' ) . '</option></select></label>';
		self::input( 'title_prefix', __( 'Prefix title', 'contacthub' ) );
		self::input( 'first_name', __( 'First name', 'contacthub' ), 'text', true, 'given-name' );
		self::input( 'last_name', __( 'Last name', 'contacthub' ), 'text', true, 'family-name' );
		self::input( 'position', __( 'Position / Job title', 'contacthub' ), 'text', false, 'organization-title' );
		self::phone_input();
		echo '</div></section>';
	}

	private static function form_section_company( $settings ) {
		echo '<details class="contacthub-form-section contacthub-company-section contacthub-inline-disclosure"><summary><span class="contacthub-disclosure-label">' . esc_html__( 'Add company details', 'contacthub' ) . '</span></summary><div class="contacthub-form-grid">';
		self::input( 'company', __( 'Legal company name', 'contacthub' ), 'text', false, 'organization' );
		self::input( 'industry', __( 'Industry', 'contacthub' ) );
		self::input( 'website', __( 'Website', 'contacthub' ), 'url', false, 'url' );
		self::vat_input( ! empty( $settings['require_vat'] ) );
		echo '</div></details>';
	}

	private static function form_section_address( $prefix, $title, $countries, $required ) {
		echo '<section class="contacthub-form-section"><h3>' . esc_html( $title ) . '</h3><div class="contacthub-form-grid">';
		self::input( $prefix . '_address_1', __( 'Street and house number', 'contacthub' ), 'text', $required, 'address-line1' );
		self::input( $prefix . '_address_2', __( 'Address line 2', 'contacthub' ), 'text', false, 'address-line2' );
		self::input( $prefix . '_postcode', __( 'Postal code', 'contacthub' ), 'text', $required, 'postal-code' );
		self::input( $prefix . '_city', __( 'City', 'contacthub' ), 'text', $required, 'address-level2' );
		self::country_select( $prefix . '_country', __( 'Country', 'contacthub' ), $countries, $required );
		echo '</div></section>';
	}

	private static function form_section_shipping( $countries ) {
		echo '<details class="contacthub-form-section contacthub-shipping-section contacthub-inline-disclosure"><summary><span class="contacthub-disclosure-label">' . esc_html__( 'Add a separate shipping address', 'contacthub' ) . '</span></summary><input type="hidden" name="shipping_different" value="0"><div class="contacthub-form-grid contacthub-shipping-fields">';
		self::input( 'shipping_address_1', __( 'Street and house number', 'contacthub' ), 'text', false, 'shipping address-line1' );
		self::input( 'shipping_address_2', __( 'Address line 2', 'contacthub' ), 'text', false, 'shipping address-line2' );
		self::input( 'shipping_postcode', __( 'Postal code', 'contacthub' ), 'text', false, 'shipping postal-code' );
		self::input( 'shipping_city', __( 'City', 'contacthub' ), 'text', false, 'shipping address-level2' );
		self::country_select( 'shipping_country', __( 'Country', 'contacthub' ), $countries, false );
		echo '</div></details>';
	}

	private static function form_section_role( $settings ) {
		$roles = self::allowed_roles();
		$allowed = array_intersect_key( $roles, array_flip( (array) $settings['allowed_roles'] ) );
		if ( ! $allowed ) {
			return;
		}
		echo '<section class="contacthub-form-section"><h3>' . esc_html__( 'Requested customer role', 'contacthub' ) . '</h3><p class="contacthub-form-help">' . esc_html__( 'Please select the customer group you would like to request. The role is assigned only after approval.', 'contacthub' ) . '</p><div class="contacthub-role-request"><label><span>' . esc_html__( 'Customer role', 'contacthub' ) . ' <em>*</em></span><select name="requested_role" required><option value="">' . esc_html__( 'Please select', 'contacthub' ) . '</option>';
		foreach ( $allowed as $role => $label ) {
			echo '<option value="' . esc_attr( $role ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></div></section>';
	}

	private static function form_section_consent( $settings ) {
		echo '<section class="contacthub-form-section"><h3>' . esc_html__( 'Confirmations', 'contacthub' ) . '</h3>';
		$privacy = self::linked_label( __( 'I have read the privacy policy.', 'contacthub' ), $settings['privacy_page_id'] );
		echo '<label class="contacthub-form-check"><input type="checkbox" name="privacy_accepted" value="1" required> <span>' . wp_kses_post( $privacy ) . '</span></label>';
		if ( $settings['terms_page_id'] ) {
			$terms = self::linked_label( __( 'I accept the terms and conditions.', 'contacthub' ), $settings['terms_page_id'] );
			echo '<label class="contacthub-form-check"><input type="checkbox" name="terms_accepted" value="1" required> <span>' . wp_kses_post( $terms ) . '</span></label>';
		}
		echo '<label class="contacthub-form-check"><input type="checkbox" name="marketing_consent" value="1"> <span>' . esc_html__( 'I would also like to receive news and offers by email. I can withdraw this consent at any time.', 'contacthub' ) . '</span></label>';
		echo '</section>';
	}

	private static function linked_label( $text, $page_id ) {
		if ( ! $page_id ) {
			return esc_html( $text );
		}
		return '<a href="' . esc_url( get_permalink( $page_id ) ) . '" target="_blank" rel="noopener">' . esc_html( $text ) . '</a>';
	}

	private static function input( $name, $label, $type = 'text', $required = false, $autocomplete = '' ) {
		echo '<label><span>' . esc_html( $label ) . ( $required ? ' <em>*</em>' : '' ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . ( $autocomplete ? ' autocomplete="' . esc_attr( $autocomplete ) . '"' : '' ) . '></label>';
	}

	private static function phone_input() {
		$codes = array( 'AT' => '+43', 'DE' => '+49', 'CH' => '+41', 'IT' => '+39', 'AL' => '+355', 'AD' => '+376', 'BE' => '+32', 'BA' => '+387', 'BG' => '+359', 'DK' => '+45', 'EE' => '+372', 'FI' => '+358', 'FR' => '+33', 'FO' => '+298', 'GI' => '+350', 'GR' => '+30', 'GB' => '+44', 'IE' => '+353', 'IS' => '+354', 'XK' => '+383', 'HR' => '+385', 'LV' => '+371', 'LI' => '+423', 'LT' => '+370', 'LU' => '+352', 'MT' => '+356', 'MD' => '+373', 'MC' => '+377', 'ME' => '+382', 'NL' => '+31', 'MK' => '+389', 'NO' => '+47', 'PL' => '+48', 'PT' => '+351', 'RO' => '+40', 'RU' => '+7', 'SM' => '+378', 'SE' => '+46', 'RS' => '+381', 'SK' => '+421', 'SI' => '+386', 'ES' => '+34', 'CZ' => '+420', 'TR' => '+90', 'UA' => '+380', 'HU' => '+36', 'BY' => '+375', 'CY' => '+357' );
		$countries = self::countries();
		echo '<label class="contacthub-registration-phone"><span>' . esc_html__( 'Phone', 'contacthub' ) . ' <em>*</em></span><span class="contacthub-profile-phone"><select name="phone_prefix" class="contacthub-phone-prefix" aria-label="' . esc_attr__( 'Country calling code', 'contacthub' ) . '">';
		foreach ( $codes as $country => $code ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( '+43', $code, false ) . '>' . esc_html( ( isset( $countries[ $country ] ) ? $countries[ $country ] : $country ) . ' ' . $code ) . '</option>';
		}
		echo '</select><input type="tel" name="phone_national" class="contacthub-phone-national" required autocomplete="tel-national" placeholder="' . esc_attr__( 'Phone number', 'contacthub' ) . '"></span></label>';
	}

	private static function vat_input( $required ) {
		$button_label = __( 'Validate VAT ID', 'contacthub' );
		echo '<label class="contacthub-vat-field"><span>' . esc_html__( 'VAT ID', 'contacthub' ) . ( $required ? ' <em>*</em>' : '' ) . '</span><span class="contacthub-vat-controls"><input type="text" name="vat_id" autocomplete="off"' . ( $required ? ' required' : '' ) . '><button type="button" class="contacthub-vat-button" data-default-label="' . esc_attr( $button_label ) . '" data-success-label="' . esc_attr__( 'VAT ID successfully validated', 'contacthub' ) . '">' . esc_html( $button_label ) . '</button></span><span class="contacthub-vat-message" aria-live="polite"></span></label>';
	}

	private static function country_select( $name, $label, $countries, $required ) {
		echo '<label><span>' . esc_html( $label ) . ( $required ? ' <em>*</em>' : '' ) . '</span><select name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . '><option value="">' . esc_html__( 'Please select', 'contacthub' ) . '</option>';
		foreach ( $countries as $code => $country ) {
			if ( '__separator__' === $code ) { echo '<option value="" disabled>' . esc_html( $country ) . '</option>'; continue; }
			echo '<option value="' . esc_attr( $code ) . '"' . selected( 'AT', $code, false ) . '>' . esc_html( $country ) . '</option>';
		}
		echo '</select></label>';
	}

	private static function countries() {
		if ( function_exists( 'WC' ) && WC() && WC()->countries ) {
			return ContactHub_Plugin::ordered_countries( WC()->countries->get_countries() );
		}
		return ContactHub_Plugin::ordered_countries( array(
			'AT' => __( 'Austria', 'contacthub' ),
			'DE' => __( 'Germany', 'contacthub' ),
			'CH' => __( 'Switzerland', 'contacthub' ),
			'IT' => __( 'Italy', 'contacthub' ),
			'FR' => __( 'France', 'contacthub' ),
			'NL' => __( 'Netherlands', 'contacthub' ),
		) );
	}

	private static function normalize_vat( $vat ) {
		return strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) $vat ) );
	}

	private static function valid_vat_format( $vat ) {
		$vat = self::normalize_vat( $vat );
		$eu_prefixes = array( 'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI' );
		return (bool) preg_match( '/^[A-Z]{2}[A-Z0-9]{2,12}$/', $vat ) && in_array( substr( $vat, 0, 2 ), $eu_prefixes, true );
	}

	public static function check_vat_vies( $vat ) {
		$vat = self::normalize_vat( $vat );
		if ( ! self::valid_vat_format( $vat ) ) {
			return new WP_Error( 'contacthub_vat_format', __( 'The VAT ID does not have a valid EU format.', 'contacthub' ) );
		}
		$cache_key = 'contacthub_vies_' . hash( 'sha256', $vat );
		$cached = get_transient( $cache_key );
		if ( 'valid' === $cached || 'invalid' === $cached ) {
			return 'valid' === $cached;
		}
		$country = substr( $vat, 0, 2 );
		$number = substr( $vat, 2 );
		$body = '<?xml version="1.0" encoding="UTF-8"?>' .
			'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:ec.europa.eu:taxud:vies:services:checkVat:types">' .
			'<soapenv:Body><urn:checkVat><urn:countryCode>' . esc_html( $country ) . '</urn:countryCode><urn:vatNumber>' . esc_html( $number ) . '</urn:vatNumber></urn:checkVat></soapenv:Body></soapenv:Envelope>';
		$response = wp_remote_post(
			'https://ec.europa.eu/taxation_customs/vies/services/checkVatService',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'text/xml; charset=utf-8', 'SOAPAction' => '' ),
				'body' => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'contacthub_vies_unavailable', __( 'The EU VIES service is currently unavailable. The VAT ID was not evaluated.', 'contacthub' ) );
		}
		$xml = wp_remote_retrieve_body( $response );
		if ( preg_match( '/<(?:[A-Za-z0-9_-]+:)?valid>\s*(true|false)\s*<\/(?:[A-Za-z0-9_-]+:)?valid>/i', $xml, $match ) ) {
			$is_valid = 'true' === strtolower( $match[1] );
			set_transient( $cache_key, $is_valid ? 'valid' : 'invalid', 5 * MINUTE_IN_SECONDS );
			return $is_valid;
		}
		return new WP_Error( 'contacthub_vies_response', __( 'EU VIES did not return a usable response.', 'contacthub' ) );
	}

	public static function validate_vat_ajax() {
		check_ajax_referer( 'contacthub_registration_validate_vat', 'nonce' );
		if ( ! self::rate_limit_ok( 'vat', 10 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many VAT ID checks. Please wait and try again.', 'contacthub' ) ), 429 );
		}
		$vat = isset( $_POST['value'] ) ? self::normalize_vat( wp_unslash( $_POST['value'] ) ) : '';
		$result = self::check_vat_vies( $vat );
		if ( is_wp_error( $result ) ) {
			$status = 'contacthub_vat_format' === $result->get_error_code() ? 400 : 503;
			wp_send_json_error( array( 'message' => $result->get_error_message(), 'status' => 'unavailable' ), $status );
		}
		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'The VAT ID is not valid according to EU VIES.', 'contacthub' ), 'status' => 'invalid' ), 422 );
		}
		wp_send_json_success( array( 'message' => __( 'The VAT ID is valid according to EU VIES.', 'contacthub' ), 'status' => 'valid' ) );
	}

	public static function handle_registration() {
		$redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), home_url( '/' ) ) : home_url( '/' );
		if ( ! isset( $_POST['contacthub_registration_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['contacthub_registration_nonce'] ) ), 'contacthub_register' ) ) {
			self::redirect_notice( $redirect, 'error', __( 'The form session has expired. Please try again.', 'contacthub' ) );
		}
		$settings = self::settings();
		$type = isset( $_POST['registration_type'] ) && 'business' === sanitize_key( wp_unslash( $_POST['registration_type'] ) ) ? 'business' : 'customer';
		if ( empty( $settings['enabled'] ) || ( 'business' === $type && empty( $settings['business_enabled'] ) ) || ! empty( $_POST['contacthub_website'] ) ) {
			self::redirect_notice( $redirect, 'error', __( 'The registration could not be processed.', 'contacthub' ) );
		}
		if ( ! self::rate_limit_ok() ) {
			self::redirect_notice( $redirect, 'error', __( 'Too many registration attempts. Please wait and try again.', 'contacthub' ) );
		}

		$data = self::registration_data();
		$data['registration_type'] = $type;
		$errors = self::validate_registration( $data, $settings );
		$vat_verified = false;
		if ( ! $errors && '' !== $data['vat_id'] ) {
			$vat_result = self::check_vat_vies( $data['vat_id'] );
			if ( is_wp_error( $vat_result ) ) {
				$errors[] = $vat_result->get_error_message();
			} elseif ( ! $vat_result ) {
				$errors[] = __( 'The VAT ID is not valid according to EU VIES.', 'contacthub' );
			} else {
				$vat_verified = true;
			}
		}
		if ( $errors ) {
			self::redirect_notice( $redirect, 'error', implode( ' ', $errors ) );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $data['user_login'],
				'user_email'   => $data['email'],
				'user_pass'    => $data['password'],
				'first_name'   => $data['first_name'],
				'last_name'    => $data['last_name'],
				'display_name' => trim( $data['first_name'] . ' ' . $data['last_name'] ),
				'role'         => 'business' === $type ? 'subscriber' : ( get_role( 'customer' ) ? 'customer' : 'subscriber' ),
			)
		);
		if ( is_wp_error( $user_id ) ) {
			self::redirect_notice( $redirect, 'error', $user_id->get_error_message() );
		}

		self::save_profile( $user_id, $data, $settings );
		$stored = false;
		if ( ContactHub_Store::standalone() ) {
			$stored = ContactHub_Store::sync_registration( $user_id, $data );
			if ( is_wp_error( $stored ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $user_id );
				self::redirect_notice( $redirect, 'error', $stored->get_error_message() );
			}
			if ( $stored ) ContactHub_Store::learn_salutation( $data['first_name'], $data['salutation'], 'confirmed' );
		}
		if ( $vat_verified ) {
			update_user_meta( $user_id, 'contacthub_vat_verified_hash', hash( 'sha256', $data['vat_id'] ) );
			update_user_meta( $user_id, 'contacthub_vat_verified_at', current_time( 'mysql' ) );
		}
		$needs_confirmation = ! empty( $settings['require_email_verify'] ) || ! empty( $data['marketing_consent'] );
		$management_token = '';
		$confirmation_sent = true;
		if ( $needs_confirmation ) {
			$management_token = self::create_pending_management(
				array(
					'kind'     => 'registration',
					'user_id'  => $user_id,
					'email'    => $data['email'],
					'redirect' => $settings['success_page_id'] ? get_permalink( $settings['success_page_id'] ) : $redirect,
				)
			);
			$management_key = hash( 'sha256', $management_token );
			update_user_meta( $user_id, 'contacthub_pending_management_key', $management_key );
			update_user_meta( $user_id, 'contacthub_pending_management_expires', time() + DAY_IN_SECONDS );
			$confirmation_sent = self::issue_confirmation( $user_id );
		} else {
			update_user_meta( $user_id, 'contacthub_email_verified', 1 );
			if ( $stored ) ContactHub_Store::update_contact( $stored, array( 'meta' => array( 'email_confirmed' => 1, 'email_confirmed_at' => current_time( 'mysql' ) ) ) );
		}
		if ( 'business' === $type ) self::notify_admin( $user_id );
		do_action( 'business' === $type ? 'contacthub/business_registration_created' : 'contacthub/customer_registration_created', $user_id, $data );

		$success_url = $settings['success_page_id'] ? get_permalink( $settings['success_page_id'] ) : $redirect;
		$extra = $management_token ? self::pending_notice_extra( $management_token, $data['email'], 'registration' ) : array();
		if ( ! $confirmation_sent ) {
			self::redirect_notice( $success_url, 'error', __( 'The confirmation email could not be sent. Please try again later.', 'contacthub' ), $extra );
		}
		self::redirect_notice( $success_url, 'success', 'business' === $type ? $settings['submission_message'] : $settings['customer_submission_message'], $extra );
	}

	private static function registration_data() {
		$text_fields = array( 'first_name', 'last_name', 'title_prefix', 'position', 'phone', 'company', 'industry', 'billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'shipping_address_1', 'shipping_address_2', 'shipping_postcode', 'shipping_city' );
		$data = array();
		foreach ( $text_fields as $field ) {
			$data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		$data['phone'] = self::normalize_phone_parts(
			isset( $_POST['phone_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_prefix'] ) ) : '+43',
			isset( $_POST['phone_national'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_national'] ) ) : $data['phone']
		);
		$data['email'] = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$data['user_login'] = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ), true ) : '';
		$data['password'] = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$data['password_confirm'] = isset( $_POST['password_confirm'] ) ? (string) wp_unslash( $_POST['password_confirm'] ) : '';
		$data['website'] = isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '';
		$data['vat_id'] = isset( $_POST['vat_id'] ) ? self::normalize_vat( wp_unslash( $_POST['vat_id'] ) ) : '';
		$data['billing_country'] = isset( $_POST['billing_country'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['billing_country'] ) ) ) : '';
		$data['shipping_country'] = isset( $_POST['shipping_country'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['shipping_country'] ) ) ) : '';
		$data['shipping_same'] = empty( $_POST['shipping_different'] ) ? 1 : 0;
		$data['salutation'] = isset( $_POST['salutation'] ) ? sanitize_key( wp_unslash( $_POST['salutation'] ) ) : '';
		if ( ! in_array( $data['salutation'], array( '', 'mr', 'ms', 'family', 'neutral' ), true ) ) $data['salutation'] = '';
		if ( '' === $data['salutation'] && '' !== $data['first_name'] ) {
			$data['salutation'] = ContactHub_Store::suggested_salutation( $data['first_name'] );
		}
		$data['requested_role'] = isset( $_POST['requested_role'] ) ? sanitize_key( wp_unslash( $_POST['requested_role'] ) ) : '';
		$data['privacy_accepted'] = empty( $_POST['privacy_accepted'] ) ? 0 : 1;
		$data['terms_accepted'] = empty( $_POST['terms_accepted'] ) ? 0 : 1;
		$data['marketing_consent'] = empty( $_POST['marketing_consent'] ) ? 0 : 1;
		$data['registration_type'] = isset( $_POST['registration_type'] ) && 'business' === sanitize_key( wp_unslash( $_POST['registration_type'] ) ) ? 'business' : 'customer';
		return $data;
	}

	private static function normalize_phone_parts( $prefix, $national ) {
		$allowed = array( '+43', '+49', '+41', '+39', '+355', '+376', '+32', '+387', '+359', '+45', '+372', '+358', '+33', '+298', '+350', '+30', '+44', '+353', '+354', '+383', '+385', '+371', '+423', '+370', '+352', '+356', '+373', '+377', '+382', '+31', '+389', '+47', '+48', '+351', '+40', '+7', '+378', '+46', '+381', '+421', '+386', '+34', '+420', '+90', '+380', '+36', '+375', '+357' );
		$prefix = in_array( $prefix, $allowed, true ) ? $prefix : '+43';
		$national = preg_replace( '/\D/', '', (string) $national );
		if ( '+39' !== $prefix ) {
			$national = ltrim( $national, '0' );
		}
		return $national ? $prefix . $national : '';
	}

	private static function validate_registration( $data, $settings ) {
		$errors = array();
		if ( ! $data['user_login'] || ! validate_username( $data['user_login'] ) ) {
			$errors[] = __( 'Please enter a valid username.', 'contacthub' );
		} elseif ( username_exists( $data['user_login'] ) ) {
			$errors[] = __( 'This username is already in use.', 'contacthub' );
		}
		if ( ! is_email( $data['email'] ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'contacthub' );
		} elseif ( email_exists( $data['email'] ) ) {
			$errors[] = __( 'A user account already exists for this email address.', 'contacthub' );
		}
		if ( strlen( $data['password'] ) < 10 ) {
			$errors[] = __( 'The password must contain at least 10 characters.', 'contacthub' );
		} elseif ( ! hash_equals( $data['password'], $data['password_confirm'] ) ) {
			$errors[] = __( 'The passwords do not match.', 'contacthub' );
		}
		if ( '' === $data['first_name'] || '' === $data['last_name'] || '' === $data['phone'] ) {
			$errors[] = __( 'Please complete all required contact details.', 'contacthub' );
		}
		if ( '' === $data['billing_address_1'] || '' === $data['billing_postcode'] || '' === $data['billing_city'] || '' === $data['billing_country'] ) {
			$errors[] = __( 'Please complete all required billing details.', 'contacthub' );
		}
		if ( 'business' === $data['registration_type'] && ! empty( $settings['require_vat'] ) && '' === $data['vat_id'] ) {
			$errors[] = __( 'Please enter a VAT ID.', 'contacthub' );
		} elseif ( '' !== $data['vat_id'] && ! self::valid_vat_format( $data['vat_id'] ) ) {
			$errors[] = __( 'Please enter a formally valid EU VAT ID.', 'contacthub' );
		}
		if ( 'business' === $data['registration_type'] ) {
			$roles = self::allowed_roles();
			$allowed_roles = array_intersect_key( $roles, array_flip( (array) $settings['allowed_roles'] ) );
			if ( ! $allowed_roles || ! isset( $allowed_roles[ $data['requested_role'] ] ) ) {
				$errors[] = __( 'Please select one of the available customer roles.', 'contacthub' );
			}
		}
		if ( empty( $data['privacy_accepted'] ) ) {
			$errors[] = __( 'The privacy policy must be acknowledged.', 'contacthub' );
		}
		if ( $settings['terms_page_id'] && empty( $data['terms_accepted'] ) ) {
			$errors[] = __( 'The terms and conditions must be accepted.', 'contacthub' );
		}
		$countries = self::countries();
		if ( $data['billing_country'] && ! isset( $countries[ $data['billing_country'] ] ) ) {
			$errors[] = __( 'Please select a valid billing country.', 'contacthub' );
		}
		if ( empty( $data['shipping_same'] ) ) {
			if ( '' === $data['shipping_address_1'] || '' === $data['shipping_postcode'] || '' === $data['shipping_city'] || ! isset( $countries[ $data['shipping_country'] ] ) ) {
				$errors[] = __( 'Please complete the separate shipping address.', 'contacthub' );
			}
		}
		return $errors;
	}

	private static function save_profile( $user_id, $data, $settings ) {
		$meta = array(
			'contacthub_salutation'           => $data['salutation'],
			'contacthub_title_prefix'        => $data['title_prefix'],
			'contacthub_position'            => $data['position'],
			'contacthub_industry'            => $data['industry'],
			'contacthub_company_website'     => $data['website'],
			'contacthub_email_verified'      => empty( $settings['require_email_verify'] ) ? 1 : 0,
			'contacthub_business_status'     => 'business' === $data['registration_type'] ? 'pending' : 'not_required',
			'contacthub_registration_source' => 'business' === $data['registration_type'] ? 'contacthub_business_registration' : 'contacthub_customer_registration',
			'contacthub_form_version'         => 'business' === $data['registration_type'] ? 'business-registration-3' : 'customer-registration-1',
			'contacthub_requested_role'       => $data['requested_role'],
			'contacthub_registered_at'       => current_time( 'mysql', true ),
			'contacthub_privacy_accepted_at' => current_time( 'mysql', true ),
			'contacthub_privacy_page_id'     => absint( $settings['privacy_page_id'] ),
			'billing_first_name'             => $data['first_name'],
			'billing_last_name'              => $data['last_name'],
			'billing_company'                => $data['company'],
			'billing_email'                  => $data['email'],
			'billing_phone'                  => $data['phone'],
			'billing_address_1'              => $data['billing_address_1'],
			'billing_address_2'              => $data['billing_address_2'],
			'billing_postcode'               => $data['billing_postcode'],
			'billing_city'                   => $data['billing_city'],
			'billing_country'                => $data['billing_country'],
			'billing_vat'                    => $data['vat_id'],
			'b2b_uid'                        => $data['vat_id'],
		);
		$shipping = $data['shipping_same']
			? array( 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'company' => $data['company'], 'address_1' => $data['billing_address_1'], 'address_2' => $data['billing_address_2'], 'postcode' => $data['billing_postcode'], 'city' => $data['billing_city'], 'country' => $data['billing_country'] )
			: array( 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'company' => $data['company'], 'address_1' => $data['shipping_address_1'], 'address_2' => $data['shipping_address_2'], 'postcode' => $data['shipping_postcode'], 'city' => $data['shipping_city'], 'country' => $data['shipping_country'] );
		foreach ( $shipping as $key => $value ) {
			$meta[ 'shipping_' . $key ] = $value;
		}
		if ( $settings['terms_page_id'] ) {
			$meta['contacthub_terms_accepted_at'] = current_time( 'mysql', true );
			$meta['contacthub_terms_page_id'] = absint( $settings['terms_page_id'] );
			$meta['contacthub_terms_page_version'] = (string) get_post_field( 'post_modified_gmt', $settings['terms_page_id'] );
		}
		if ( $settings['privacy_page_id'] ) {
			$meta['contacthub_privacy_page_version'] = (string) get_post_field( 'post_modified_gmt', $settings['privacy_page_id'] );
		}
		if ( $data['marketing_consent'] ) {
			$meta['contacthub_marketing_status'] = 'pending';
			$meta['contacthub_marketing_requested_at'] = current_time( 'mysql', true );
			$meta['contacthub_marketing_consent_version'] = hash( 'sha256', 'business-registration-marketing-consent-1' );
		}
		foreach ( $meta as $key => $value ) {
			update_user_meta( $user_id, $key, $value );
		}
	}

	private static function issue_confirmation( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}
		$token = bin2hex( random_bytes( 32 ) );
		update_user_meta( $user_id, 'contacthub_verification_token', hash( 'sha256', $token ) );
		update_user_meta( $user_id, 'contacthub_verification_expires', time() + DAY_IN_SECONDS );
		$link = add_query_arg( array( 'contacthub_verify' => $user_id, 'key' => $token ), home_url( '/' ) );
		$settings = self::settings();
		$replacements = array(
			'{first_name}' => esc_html( $user->first_name ),
			'{site_name}' => esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			'{confirmation_link}' => '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Confirm email address', 'contacthub' ) . '</a>',
		);
		$subject = strtr( $settings['confirmation_subject'], $replacements );
		$message = strtr( wpautop( $settings['confirmation_message'] ), $replacements );
		if ( false === strpos( $settings['confirmation_message'], '{confirmation_link}' ) ) {
			$message .= '<p><a href="' . esc_url( $link ) . '">' . esc_html__( 'Confirm email address', 'contacthub' ) . '</a></p>';
		}
		return wp_mail( $user->user_email, $subject, $message, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	public static function handle_verification() {
		if ( self::handle_newsletter_verification() ) return;
		if ( ! isset( $_GET['contacthub_verify'], $_GET['key'] ) ) {
			return;
		}
		$settings = self::settings();
		$notice_page = $settings['registration_page_id'] ? get_permalink( $settings['registration_page_id'] ) : self::virtual_registration_url();
		$user_id = absint( $_GET['contacthub_verify'] );
		$key = sanitize_text_field( wp_unslash( $_GET['key'] ) );
		$stored = (string) get_user_meta( $user_id, 'contacthub_verification_token', true );
		$expires = absint( get_user_meta( $user_id, 'contacthub_verification_expires', true ) );
		if ( ! $user_id || ! $stored || ! $expires || time() > $expires || ! hash_equals( $stored, hash( 'sha256', $key ) ) ) {
			self::redirect_notice( $notice_page ?: home_url( '/' ), 'error', __( 'The confirmation link is invalid or has expired.', 'contacthub' ) );
		}
		update_user_meta( $user_id, 'contacthub_email_verified', 1 );
		if ( ContactHub_Store::standalone() ) {
			$user = get_user_by( 'id', $user_id );
			$contact = $user ? ContactHub_Store::contact_by_email( $user->user_email ) : false;
			if ( $contact ) ContactHub_Store::update_contact( $contact['id'], array( 'meta' => array( 'email_confirmed' => 1, 'email_confirmed_at' => current_time( 'mysql' ) ) ) );
		}
		delete_user_meta( $user_id, 'contacthub_verification_token' );
		delete_user_meta( $user_id, 'contacthub_verification_expires' );
		$management_key = sanitize_key( get_user_meta( $user_id, 'contacthub_pending_management_key', true ) );
		if ( $management_key ) delete_transient( 'contacthub_pending_confirmation_' . $management_key );
		delete_user_meta( $user_id, 'contacthub_pending_management_key' );
		delete_user_meta( $user_id, 'contacthub_pending_management_expires' );
		if ( 'pending' === get_user_meta( $user_id, 'contacthub_marketing_status', true ) ) {
			update_user_meta( $user_id, 'contacthub_marketing_status', 'confirmed' );
			update_user_meta( $user_id, 'contacthub_marketing_confirmed_at', current_time( 'mysql', true ) );
			if ( ContactHub_Store::standalone() && ! empty( $contact ) ) ContactHub_Store::record_consent( $contact['id'], 'granted', 'registration_double_opt_in', current_time( 'Y-m-d' ), __( 'Marketing consent confirmed through the registration double opt-in.', 'contacthub' ) );
		}
		$redirect_url = $settings['verified_page_id'] ? get_permalink( $settings['verified_page_id'] ) : '';
		$registration_source = get_user_meta( $user_id, 'contacthub_registration_source', true );
		self::redirect_notice(
			$notice_page ?: home_url( '/' ),
			'success',
			'contacthub_customer_registration' === $registration_source ? $settings['customer_verified_message'] : $settings['verified_message'],
			array(
				'verification_complete' => 1,
				'redirect_url' => $redirect_url,
				'delay' => absint( $settings['verified_delay'] ),
			)
		);
	}

	public static function guard_login( $user, $username, $password ) {
		if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
			return $user;
		}
		$source = get_user_meta( $user->ID, 'contacthub_registration_source', true );
		if ( ! in_array( $source, array( 'contacthub_business_registration', 'contacthub_customer_registration' ), true ) ) {
			return $user;
		}
		if ( ! get_user_meta( $user->ID, 'contacthub_email_verified', true ) ) {
			return new WP_Error( 'contacthub_email_unverified', __( 'Please confirm your email address before signing in.', 'contacthub' ) );
		}
		if ( 'contacthub_customer_registration' === $source ) return $user;
		$status = get_user_meta( $user->ID, 'contacthub_business_status', true );
		if ( 'pending' === $status ) {
			return new WP_Error( 'contacthub_approval_pending', __( 'Your business account is still awaiting approval.', 'contacthub' ) );
		}
		if ( 'rejected' === $status ) {
			return new WP_Error( 'contacthub_registration_rejected', __( 'This business registration was not approved. Please contact us.', 'contacthub' ) );
		}
		return $user;
	}

	public static function registration_url( $url ) {
		$settings = self::settings();
		if ( ! is_multisite() && ! empty( $settings['enabled'] ) && ! empty( $settings['replace_registration'] ) ) {
			$page_url = $settings['registration_page_id'] ? get_permalink( $settings['registration_page_id'] ) : self::virtual_registration_url();
			if ( $page_url ) return $page_url;
		}
		return $url;
	}

	private static function virtual_registration_url() {
		return add_query_arg( 'contacthub_registration', '1', home_url( '/' ) );
	}

	public static function render_virtual_registration() {
		$virtual = isset( $_GET['contacthub_registration'] ) ? sanitize_key( wp_unslash( $_GET['contacthub_registration'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only endpoint.
		if ( is_admin() || '1' !== $virtual ) return;
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) || $settings['registration_page_id'] ) return;
		status_header( 200 ); nocache_headers(); get_header();
		echo '<main id="primary" class="site-main contacthub-virtual-registration"><div class="contacthub-virtual-registration-inner"><h1>' . esc_html__( 'Registration', 'contacthub' ) . '</h1>' . do_shortcode( '[contacthub_registration]' ) . '</div></main>';
		get_footer(); exit;
	}

	public static function maybe_redirect_registration() {
		$settings = self::settings();
		if ( is_multisite() || empty( $settings['enabled'] ) || empty( $settings['replace_registration'] ) ) {
			return;
		}
		$page_url = $settings['registration_page_id'] ? get_permalink( $settings['registration_page_id'] ) : self::virtual_registration_url();
		if ( $page_url ) {
			wp_safe_redirect( $page_url );
			exit;
		}
	}

	public static function handle_decision() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contacthub' ) );
		}
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		check_admin_referer( 'contacthub_registration_decision_' . $user_id );
		$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$user = get_user_by( 'id', $user_id );
		$is_contacthub_registration = $user && 'contacthub_business_registration' === get_user_meta( $user_id, 'contacthub_registration_source', true );
		$is_pending = $user && 'pending' === get_user_meta( $user_id, 'contacthub_business_status', true );
		if ( ! $is_contacthub_registration || ! $is_pending || ! in_array( $decision, array( 'approve', 'reject' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=contacthub-settings' ) );
			exit;
		}
		if ( 'approve' === $decision ) {
			if ( ! get_user_meta( $user_id, 'contacthub_email_verified', true ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=contacthub-settings&contacthub_email_error=1' ) );
				exit;
			}
			$settings = self::settings();
			$roles = self::allowed_roles();
			$configured_roles = array_intersect_key( $roles, array_flip( (array) $settings['allowed_roles'] ) );
			$requested_role = sanitize_key( get_user_meta( $user_id, 'contacthub_requested_role', true ) );
			if ( ! isset( $configured_roles[ $requested_role ] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=contacthub-settings&contacthub_role_error=1' ) );
				exit;
			}
			$user->set_role( $requested_role );
			update_user_meta( $user_id, 'contacthub_business_status', 'approved' );
			update_user_meta( $user_id, 'contacthub_approved_at', current_time( 'mysql', true ) );
			update_user_meta( $user_id, 'contacthub_approved_by', get_current_user_id() );
			wp_mail( $user->user_email, __( 'Your business account has been approved', 'contacthub' ), __( 'Your business account and requested customer role have been approved. You can now sign in.', 'contacthub' ) );
		} else {
			update_user_meta( $user_id, 'contacthub_business_status', 'rejected' );
			update_user_meta( $user_id, 'contacthub_rejected_at', current_time( 'mysql', true ) );
			update_user_meta( $user_id, 'contacthub_rejected_by', get_current_user_id() );
			wp_mail( $user->user_email, __( 'Update about your business registration', 'contacthub' ), __( 'Your business registration could not be approved. Please contact us if you have questions.', 'contacthub' ) );
		}
		if ( ContactHub_Store::standalone() ) {
			$contact = ContactHub_Store::contact_by_email( $user->user_email );
			if ( $contact ) ContactHub_Store::add_activity( $contact['id'], 0, 'registration', 'approve' === $decision ? __( 'Registration approved', 'contacthub' ) : __( 'Registration rejected', 'contacthub' ), sprintf( __( 'Edited by: %s', 'contacthub' ), wp_get_current_user()->display_name ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=contacthub-settings&updated=1' ) );
		exit;
	}

	public static function handle_resend() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contacthub' ) );
		}
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		check_admin_referer( 'contacthub_registration_resend_' . $user_id );
		$user = get_user_by( 'id', $user_id );
		$is_contacthub_registration = $user && 'contacthub_business_registration' === get_user_meta( $user_id, 'contacthub_registration_source', true );
		$is_pending = $user && 'pending' === get_user_meta( $user_id, 'contacthub_business_status', true );
		if ( $is_contacthub_registration && $is_pending && ! get_user_meta( $user_id, 'contacthub_email_verified', true ) ) {
			self::issue_confirmation( $user_id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=contacthub-settings&updated=1' ) );
		exit;
	}

	private static function notify_admin( $user_id ) {
		$settings = self::settings();
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! is_email( $settings['admin_email'] ) ) {
			return;
		}
		$subject = sprintf( __( 'New business registration: %s', 'contacthub' ), $user->display_name );
		$roles = self::allowed_roles();
		$requested_role = sanitize_key( get_user_meta( $user_id, 'contacthub_requested_role', true ) );
		$role_label = isset( $roles[ $requested_role ] ) ? $roles[ $requested_role ] : $requested_role;
		$message = sprintf( __( 'A new business registration was submitted.

Person: %1$s
Company: %2$s
Email: %3$s
Requested role: %4$s

Review: %5$s', 'contacthub' ), $user->display_name, get_user_meta( $user_id, 'billing_company', true ), $user->user_email, $role_label, admin_url( 'admin.php?page=contacthub-settings' ) );
		wp_mail( $settings['admin_email'], $subject, $message );
	}

	private static function rate_limit_ok( $scope = 'registration', $limit = 5 ) {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'contacthub_' . sanitize_key( $scope ) . '_rate_' . hash( 'sha256', $address . wp_salt( 'nonce' ) );
		$count = absint( get_transient( $key ) );
		if ( $count >= absint( $limit ) ) {
			return false;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	private static function redirect_notice( $url, $type, $message, $extra = array() ) {
		$token = wp_generate_password( 20, false, false );
		$notice = array_merge( array( 'type' => $type, 'message' => $message ), is_array( $extra ) ? $extra : array() );
		set_transient( self::NOTICE_PREFIX . $token, $notice, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'contacthub_notice', $token, $url ) );
		exit;
	}

	private static function consume_notice() {
		if ( empty( $_GET['contacthub_notice'] ) ) {
			return false;
		}
		$token = sanitize_key( wp_unslash( $_GET['contacthub_notice'] ) );
		$notice = get_transient( self::NOTICE_PREFIX . $token );
		delete_transient( self::NOTICE_PREFIX . $token );
		return is_array( $notice ) ? $notice : false;
	}

	private static function current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		return esc_url_raw( $scheme . $host . $uri );
	}
}
