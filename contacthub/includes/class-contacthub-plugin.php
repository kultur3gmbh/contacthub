<?php

defined( 'ABSPATH' ) || exit;

final class ContactHub_Plugin {
	public static function boot() {
		load_plugin_textdomain( 'contacthub', false, dirname( plugin_basename( CONTACTHUB_FILE ) ) . '/languages' );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'submenu_order' ), 999 );
		add_filter( 'custom_menu_order', '__return_true' );
		add_filter( 'menu_order', array( __CLASS__, 'menu_order' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'disable_groundhogg_email_detector' ), 999 );
		add_action( 'wp_ajax_contacthub_update_company_field', array( __CLASS__, 'update_company_field' ) );
		add_action( 'wp_ajax_contacthub_update_contact_field', array( __CLASS__, 'update_contact_field' ) );
		add_action( 'wp_ajax_contacthub_update_marketing_consent', array( __CLASS__, 'update_marketing_consent' ) );
		add_action( 'wp_ajax_contacthub_bulk_marketing_consent', array( __CLASS__, 'bulk_marketing_consent' ) );
		add_action( 'wp_ajax_contacthub_create_contact', array( __CLASS__, 'create_contact' ) );
		add_action( 'wp_ajax_contacthub_create_company', array( __CLASS__, 'create_company' ) );
		add_action( 'wp_ajax_contacthub_manage_contact_tag', array( __CLASS__, 'manage_contact_tag' ) );
		add_action( 'wp_ajax_contacthub_preview_broadcast', array( __CLASS__, 'preview_broadcast' ) );
		add_action( 'wp_ajax_contacthub_preview_order', array( __CLASS__, 'preview_order' ) );
		add_action( 'wp_ajax_contacthub_update_user_roles', array( __CLASS__, 'update_user_roles' ) );
		add_action( 'wp_ajax_contacthub_create_user_account', array( __CLASS__, 'create_user_account' ) );
		add_action( 'wp_ajax_contacthub_update_customer_address', array( __CLASS__, 'update_customer_address' ) );
		add_action( 'wp_ajax_contacthub_validate_vat', array( __CLASS__, 'validate_vat' ) );
		add_action( 'admin_post_contacthub_tag_action', array( __CLASS__, 'handle_tag_action' ) );
		add_action( 'admin_post_contacthub_export_contacts', array( __CLASS__, 'export_contacts' ) );
		add_action( 'admin_post_contacthub_print_contacts', array( __CLASS__, 'print_contacts' ) );
		add_action( 'admin_post_contacthub_assign_missing_salutations', array( __CLASS__, 'assign_missing_salutations' ) );
	}

	public static function menu() {
		$companies_enabled = self::companies_available();
		$capability = self::access_capability();
		add_menu_page(
			'ContactHub',
			'ContactHub',
			$capability,
			'contacthub',
			array( __CLASS__, 'render_people' ),
			'dashicons-groups',
			3
		);
		add_submenu_page(
			'contacthub',
			__( 'People', 'contacthub' ),
			__( 'People', 'contacthub' ),
			$capability,
			'contacthub',
			array( __CLASS__, 'render_people' )
		);
		if ( $companies_enabled ) {
			add_submenu_page(
				'contacthub',
				__( 'Companies', 'contacthub' ),
				__( 'Companies', 'contacthub' ),
				$capability,
				'contacthub-firmen',
				array( __CLASS__, 'render' )
			);
		}
		add_submenu_page(
			'contacthub',
			__( 'Tags', 'contacthub' ),
			__( 'Tags', 'contacthub' ),
			$capability,
			'contacthub-tags',
			array( __CLASS__, 'render_tags_page' )
		);
	}

	public static function submenu_order() {
		global $submenu;
		if ( empty( $submenu['contacthub'] ) || ! is_array( $submenu['contacthub'] ) ) {
			return;
		}
		$priority = array(
			'contacthub'             => 10,
			'contacthub-firmen'      => 20,
			'contacthub-tags'        => 30,
			'contacthub-segments'    => 40,
			'contacthub-newsletters' => 50,
			'contacthub-campaigns'   => 60,
			'contacthub-trash'       => 70,
			'contacthub-settings'    => 999,
		);
		usort(
			$submenu['contacthub'],
			static function ( $left, $right ) use ( $priority ) {
				$left_order  = isset( $priority[ $left[2] ] ) ? $priority[ $left[2] ] : 500;
				$right_order = isset( $priority[ $right[2] ] ) ? $priority[ $right[2] ] : 500;
				return $left_order <=> $right_order;
			}
		);
	}

	private static function access_capability() {
		return class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
	}

	/**
	 * Places ContactHub directly after Avada in the first admin menu group.
	 * If Avada is not active, ContactHub is placed immediately before the
	 * first separator instead.
	 *
	 * @param string[] $menu_order Registered top-level menu slugs.
	 * @return string[]
	 */
	public static function menu_order( $menu_order ) {
		if ( ! is_array( $menu_order ) ) {
			return $menu_order;
		}

		$contacthub_slug = 'contacthub';
		$contacthub_key  = array_search( $contacthub_slug, $menu_order, true );

		if ( false === $contacthub_key ) {
			return $menu_order;
		}

		unset( $menu_order[ $contacthub_key ] );
		$menu_order = array_values( $menu_order );
		$avada_slug = '';

		global $menu;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $menu_item ) {
				if ( ! is_array( $menu_item ) || ! isset( $menu_item[0], $menu_item[2] ) ) {
					continue;
				}

				$label = strtolower( trim( wp_strip_all_tags( (string) $menu_item[0] ) ) );
				$slug  = (string) $menu_item[2];

				if ( 'avada' === $label || 'avada' === $slug || 0 === strpos( $slug, 'avada-' ) ) {
					$avada_slug = $slug;
					break;
				}
			}
		}

		$insert_at = false;
		if ( '' !== $avada_slug ) {
			$avada_key = array_search( $avada_slug, $menu_order, true );
			if ( false !== $avada_key ) {
				$insert_at = $avada_key + 1;
			}
		}

		if ( false === $insert_at ) {
			$separator_key = array_search( 'separator1', $menu_order, true );
			$insert_at     = false !== $separator_key ? $separator_key : 1;
		}

		array_splice( $menu_order, $insert_at, 0, array( $contacthub_slug ) );

		return $menu_order;
	}

	public static function assets( $hook ) {
		if ( 'contacthub_page_contacthub-trash' === $hook ) {
			wp_enqueue_style( 'contacthub', CONTACTHUB_URL . 'assets/contacthub-admin.css', array(), CONTACTHUB_ASSET_VERSION );
			wp_enqueue_script( 'contacthub-trash', CONTACTHUB_URL . 'assets/contacthub-trash.js', array(), CONTACTHUB_ASSET_VERSION, true );
			return;
		}
		if ( ! in_array( $hook, array( 'toplevel_page_contacthub', 'contacthub_page_contacthub-firmen', 'contacthub_page_contacthub-tags', 'contacthub_page_contacthub-settings' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'contacthub', CONTACTHUB_URL . 'assets/contacthub-admin.css', array(), CONTACTHUB_ASSET_VERSION );
		wp_enqueue_script( 'contacthub', CONTACTHUB_URL . 'assets/contacthub-admin.js', array(), CONTACTHUB_ASSET_VERSION, true );
		$salutation_suggestions = array();
		foreach ( ContactHub_Store::salutations() as $row ) {
			$salutation_suggestions[ (string) $row['first_name_normalized'] ] = (string) $row['salutation'];
		}
		wp_localize_script(
			'contacthub',
			'CONTACTHUB_CONFIG',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'contacthub_update_company' ),
				'phoneCountries' => self::phone_countries(),
				'salutationSuggestions' => $salutation_suggestions,
				'salutationLabels' => array(
					'mr'      => __( 'Mr', 'contacthub' ),
					'ms'      => __( 'Ms', 'contacthub' ),
					'family'  => __( 'Family', 'contacthub' ),
					'neutral' => __( 'Neutral', 'contacthub' ),
				),
				'i18n'           => array(
					'invoice'                  => __( 'Invoice', 'contacthub' ),
					'order'                    => __( 'Order', 'contacthub' ),
					'event'                    => __( 'Event', 'contacthub' ),
					'validateVat'              => __( 'Validate VAT ID', 'contacthub' ),
					'save'                     => __( 'Save', 'contacthub' ),
					'cancel'                   => __( 'Cancel', 'contacthub' ),
					'close'                    => __( 'Close', 'contacthub' ),
					'preview'                  => __( 'Preview', 'contacthub' ),
					'openNewWindow'            => __( 'Open in new window', 'contacthub' ),
					'noUsableResponse'         => __( 'No usable response was received.', 'contacthub' ),
					'vatValidationFailed'      => __( 'VAT ID validation could not be completed.', 'contacthub' ),
					'saveFailed'               => __( 'Saving failed.', 'contacthub' ),
					'confirmWithdrawal'        => __( 'I confirm that the withdrawal or unsubscribe has actually been documented.', 'contacthub' ),
					'confirmConsent'           => __( 'I confirm that explicit consent has been given.', 'contacthub' ),
					'onePersonSelected'        => __( '1 person selected', 'contacthub' ),
					'peopleSelected'           => __( '%d people selected', 'contacthub' ),
					'checkingContacts'         => __( 'Checking the selected contacts…', 'contacthub' ),
					'enterTagName'             => __( 'Please enter a tag name.', 'contacthub' ),
					'runningTagAction'         => __( 'Running tag action…', 'contacthub' ),
					'selectSuggestedRole'      => __( 'Please select a suggested WordPress role.', 'contacthub' ),
					'roleAlreadyAssigned'      => __( 'This role is already assigned.', 'contacthub' ),
					'removeRole'               => __( 'Remove role %s', 'contacthub' ),
					'confirmSaveRoles'         => __( 'Save the selected roles and the resulting permissions for this account?', 'contacthub' ),
					'confirmLinkAccount'       => __( 'Link the existing account to this person?', 'contacthub' ),
					'confirmCreateAccount'     => __( 'Create the new user account now?', 'contacthub' ),
					'savingAddress'            => __( 'Saving address…', 'contacthub' ),
					'countryCallingCode'       => __( 'Country calling code', 'contacthub' ),
					'phoneNumber'              => __( 'Phone number', 'contacthub' ),
					'invalidServerResponse'    => __( 'The server returned an invalid response.', 'contacthub' ),
					'checkingVat'              => __( 'Checking EU VIES…', 'contacthub' ),
					'saving'                   => __( 'Saving…', 'contacthub' ),
					'saved'                    => __( 'Saved.', 'contacthub' ),
					'salutationSuggestion'     => __( 'Suggestion: %s', 'contacthub' ),
					'savingConfirmed'          => __( 'Saving with confirmation…', 'contacthub' ),
					'bulkEditFailed'           => __( 'Bulk editing failed.', 'contacthub' ),
					'creatingPerson'           => __( 'Creating person…', 'contacthub' ),
					'creatingCompany'          => __( 'Creating company…', 'contacthub' ),
					'createFailed'             => __( 'Creation failed.', 'contacthub' ),
					'tagAlreadyAssigned'       => __( 'This tag is already assigned to the person.', 'contacthub' ),
					'tagActionFailed'          => __( 'Tag action failed.', 'contacthub' ),
					'broadcastLoadFailed'      => __( 'The broadcast could not be loaded.', 'contacthub' ),
					'broadcast'                => __( 'Broadcast', 'contacthub' ),
					'previewLoadFailed'        => __( 'The preview could not be loaded.', 'contacthub' ),
					'confirmDeleteOnUninstall' => __( 'Do you really want ContactHub-owned customer data to be permanently deleted when the plugin is uninstalled? Data from Groundhogg, WooCommerce, German Market, B2B Market, The Events Calendar, and Event Tickets will not be deleted.', 'contacthub' ),
					'confirmResetTestData'     => __( 'Permanently delete all data from the ContactHub database now? Groundhogg, WordPress, WooCommerce, invoices, and events will remain untouched.', 'contacthub' ),
					'confirmDeleteTag'         => __( 'Delete the tag “%1$s”? It is currently assigned to %2$d people. The people themselves will not be deleted.', 'contacthub' ),
					'confirmBulkDeleteTags'    => __( 'Delete the selected tags and all their contact assignments? The people themselves will not be deleted.', 'contacthub' ),
					'exportContacts'           => __( 'Export contacts', 'contacthub' ),
					'printContactList'         => __( 'Print contact list', 'contacthub' ),
					'chooseOutputScope'        => __( 'Choose whether selected people or all filtered people should be used.', 'contacthub' ),
				),
			)
		);
	}

	public static function disable_groundhogg_email_detector( $hook ) {
		if ( in_array( $hook, array( 'toplevel_page_contacthub', 'contacthub_page_contacthub-firmen', 'contacthub_page_contacthub-tags', 'contacthub_page_contacthub-trash', 'contacthub_page_contacthub-settings', 'contacthub_page_contacthub-newsletters' ), true ) ) {
			wp_dequeue_script( 'groundhogg-admin-email-detector' );
		}
	}

	public static function update_company_field() {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'contacthub' ) ), 403 );
		}
		check_ajax_referer( 'contacthub_update_company', 'nonce' );
		if ( ! self::companies_available() ) {
			wp_send_json_error( array( 'message' => __( 'Company management is unavailable because Groundhogg Companies is not active.', 'contacthub' ) ), 503 );
		}

		$company_id = isset( $_POST['company_id'] ) ? absint( $_POST['company_id'] ) : 0;
		$field      = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$value      = isset( $_POST['value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['value'] ) ) : '';
		$allowed    = array( 'name', 'domain', 'phone', 'industry', 'address', 'description', 'uid' );

		if ( ! $company_id || ! in_array( $field, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field.', 'contacthub' ) ), 400 );
		}
		if ( 'name' === $field && '' === trim( $value ) ) {
			wp_send_json_error( array( 'message' => __( 'The company name must not be empty.', 'contacthub' ) ), 400 );
		}
		if ( 'domain' === $field && '' !== $value ) {
			$value = esc_url_raw( $value );
			if ( ! $value ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a valid website address.', 'contacthub' ) ), 400 );
			}
		}
		if ( 'phone' === $field ) {
			$value = self::normalize_phone( $value );
			if ( $value && ! self::phone_country_code( $value ) ) {
				wp_send_json_error( array( 'message' => __( 'Please select a European country calling code.', 'contacthub' ) ), 400 );
			}
		}
		if ( 'uid' === $field ) {
			$value = self::normalize_vat( $value );
			if ( '' !== $value && ! self::valid_vat_format( $value ) ) {
				wp_send_json_error( array( 'message' => __( 'The VAT ID does not have a valid EU format.', 'contacthub' ) ), 400 );
			}
			$user = self::company_primary_user( $company_id );
			if ( ! $user ) {
				wp_send_json_error( array( 'message' => __( 'The primary contact does not have a WordPress user account.', 'contacthub' ) ), 400 );
			}
			$verified = '' !== $value && hash_equals( (string) get_user_meta( $user->ID, 'contacthub_vat_verified_hash', true ), hash( 'sha256', $value ) );
			update_user_meta( $user->ID, 'billing_vat', $value );
			update_user_meta( $user->ID, 'b2b_uid', $value );
			if ( ! $verified ) {
				delete_user_meta( $user->ID, 'contacthub_vat_verified_hash' );
				delete_user_meta( $user->ID, 'contacthub_vat_verified_at' );
			}
			wp_send_json_success( array( 'value' => $value, 'display' => '' !== $value ? $value : '—', 'verified' => $verified ) );
		}

		if ( ContactHub_Store::standalone() ) {
			$result = ContactHub_Store::update_company( $company_id, $field, $value );
			if ( ! $result ) wp_send_json_error( array( 'message' => __( 'ContactHub could not save the change.', 'contacthub' ) ), 500 );
			wp_send_json_success( array( 'value' => $value, 'display' => 'phone' === $field ? self::format_phone( $value ) : ( '' !== $value ? $value : '—' ) ) );
		}

		$result = self::execute_ability(
			'groundhogg-companies/update-company',
			array( 'id' => $company_id, $field => $value )
		);
		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Groundhogg could not save the change.', 'contacthub' ) ), 500 );
		}

		wp_send_json_success( array( 'value' => $value, 'display' => 'phone' === $field ? self::format_phone( $value ) : ( '' !== $value ? $value : '—' ) ) );
	}

	public static function update_contact_field() {
		self::guard_ajax();

		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$field      = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$value      = isset( $_POST['value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['value'] ) ) : '';
		$core       = array( 'first_name', 'last_name', 'email' );
		$meta       = array( 'anrede', 'titel_vorangestellt', 'titel_nachgestellt', 'berufsbezeichnung', 'primary_phone' );

		if ( ! $contact_id || ( ! in_array( $field, $core, true ) && ! in_array( $field, $meta, true ) && ! in_array( $field, array( 'uid', 'company' ), true ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid person field.', 'contacthub' ) ), 400 );
		}
		if ( 'company' === $field ) {
			if ( '' === trim( $value ) ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a company name.', 'contacthub' ) ), 400 );
			}
			if ( ! self::companies_available() ) {
				wp_send_json_error( array( 'message' => __( 'Company management is unavailable because Groundhogg Companies is not active.', 'contacthub' ) ), 503 );
			}
			$result = self::assign_contact_company( $contact_id, 0, $value, '' );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
			}
			$company_map = self::contact_company_map();
			$assigned = isset( $company_map[ $contact_id ] ) ? $company_map[ $contact_id ] : array();
			$names = array_values( array_filter( array_map( static function ( $company ) { return self::value( $company, array( 'name', 'company_name', 'get_name' ) ); }, $assigned ) ) );
			wp_send_json_success( array( 'value' => $value, 'display' => $names ? implode( ', ', $names ) : $value ) );
		}
		if ( 'uid' === $field ) {
			$value = self::normalize_vat( $value );
			if ( '' !== $value && ! self::valid_vat_format( $value ) ) {
				wp_send_json_error( array( 'message' => __( 'The VAT ID does not have a valid EU format.', 'contacthub' ) ), 400 );
			}
			$contact = self::contact( $contact_id );
			$user = $contact ? self::contact_user( $contact ) : false;
			if ( ! $user ) {
				wp_send_json_error( array( 'message' => __( 'This person does not have a linked WordPress user account.', 'contacthub' ) ), 400 );
			}
			$verified = '' !== $value && hash_equals( (string) get_user_meta( $user->ID, 'contacthub_vat_verified_hash', true ), hash( 'sha256', $value ) );
			update_user_meta( $user->ID, 'billing_vat', $value );
			update_user_meta( $user->ID, 'b2b_uid', $value );
			if ( ! $verified ) {
				delete_user_meta( $user->ID, 'contacthub_vat_verified_hash' );
				delete_user_meta( $user->ID, 'contacthub_vat_verified_at' );
			}
			wp_send_json_success( array( 'value' => $value, 'display' => '' !== $value ? $value : '—', 'verified' => $verified ) );
		}
		if ( in_array( $field, array( 'first_name', 'last_name', 'email' ), true ) && '' === trim( $value ) ) {
			wp_send_json_error( array( 'message' => __( 'This field must not be empty.', 'contacthub' ) ), 400 );
		}
		if ( 'email' === $field && ! is_email( $value ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'contacthub' ) ), 400 );
		}
		if ( 'primary_phone' === $field ) {
			$value = self::normalize_phone( $value );
			if ( $value && ! self::phone_country_code( $value ) ) {
				wp_send_json_error( array( 'message' => __( 'Please select a European country calling code.', 'contacthub' ) ), 400 );
			}
		}
		if ( 'anrede' === $field && ! in_array( $value, array( '', 'mr', 'ms', 'family', 'neutral' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a valid salutation.', 'contacthub' ) ), 400 );
		}

		$current_contact = self::contact( $contact_id );
		$current_first_name = $current_contact ? self::value( $current_contact, array( 'first_name', 'get_first_name' ) ) : '';
		$current_salutation = $current_contact ? sanitize_key( self::meta( $current_contact, array( 'anrede', 'salutation' ) ) ) : '';
		$automatic_salutation = '';
		if ( 'first_name' === $field && '' === $current_salutation ) {
			$automatic_salutation = ContactHub_Store::suggested_salutation( $value );
		}

		$input = array( 'id' => $contact_id );
		if ( in_array( $field, $core, true ) ) {
			$input[ $field ] = $value;
		} else {
			$input['meta'] = array( $field => $value );
		}
		if ( ContactHub_Store::standalone() ) {
			$standalone_input = in_array( $field, $core, true ) ? array( $field => $value ) : array( 'meta' => array( $field => $value ) );
			if ( $automatic_salutation ) {
				$standalone_input['meta'] = array( 'anrede' => $automatic_salutation );
			}
			$result = ContactHub_Store::update_contact( $contact_id, $standalone_input );
		} else {
			if ( $automatic_salutation ) {
				$input['meta'] = array( 'anrede' => $automatic_salutation );
			}
			$result = self::execute_ability( 'groundhogg/update-contact', $input );
		}
		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Groundhogg could not save the change.', 'contacthub' ) ), 500 );
		}
		$library_first_name = 'first_name' === $field ? $value : $current_first_name;
		$library_salutation = 'anrede' === $field ? $value : ( $automatic_salutation ?: $current_salutation );
		if ( $library_first_name && in_array( $library_salutation, array( 'mr', 'ms', 'family', 'neutral' ), true ) ) {
			ContactHub_Store::learn_salutation( $library_first_name, $library_salutation, 'confirmed' );
		}

		$display = 'primary_phone' === $field ? self::format_phone( $value ) : ( 'anrede' === $field ? self::salutation_label( $value ) : ( '' !== $value ? $value : '—' ) );
		wp_send_json_success( array( 'value' => $value, 'display' => $display, 'salutation' => $automatic_salutation ) );
	}

	public static function assign_missing_salutations() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contacthub' ) );
		check_admin_referer( 'contacthub_assign_missing_salutations' );
		$apply = isset( $_POST['confirm_apply'] ) && '1' === (string) $_POST['confirm_apply'];
		$matches = array();
		foreach ( self::extension_contacts() as $contact ) {
			$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
			$first_name = trim( self::value( $contact, array( 'first_name', 'get_first_name' ) ) );
			$current = sanitize_key( self::meta( $contact, array( 'anrede', 'salutation' ) ) );
			$suggested = $first_name ? ContactHub_Store::suggested_salutation( $first_name ) : '';
			if ( ! $id || ! $first_name || $current || ! $suggested ) continue;
			$matches[] = array( 'id' => $id, 'first_name' => $first_name, 'salutation' => $suggested );
		}
		if ( $apply ) {
			foreach ( $matches as $match ) {
				if ( ContactHub_Store::standalone() ) ContactHub_Store::update_contact( $match['id'], array( 'meta' => array( 'anrede' => $match['salutation'] ) ) );
				else self::execute_ability( 'groundhogg/update-contact', array( 'id' => $match['id'], 'meta' => array( 'anrede' => $match['salutation'] ) ) );
			}
		}
		set_transient( 'contacthub_salutation_bulk_' . get_current_user_id(), array( 'applied' => $apply, 'count' => count( $matches ), 'sample' => array_slice( $matches, 0, 20 ) ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=contacthub-settings&tab=misc&misc_section=salutations' ) ); exit;
	}

	public static function update_marketing_consent() {
		self::guard_ajax();

		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$decision   = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$source     = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$date       = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$note       = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$confirmed  = isset( $_POST['confirmed'] ) && '1' === (string) $_POST['confirmed'];
		$sources    = self::marketing_consent_sources();

		if ( ! $contact_id || ! in_array( $decision, array( 'grant', 'revoke' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid selection.', 'contacthub' ) ), 400 );
		}
		if ( ! $confirmed ) {
			wp_send_json_error( array( 'message' => __( 'Confirmation is required.', 'contacthub' ) ), 400 );
		}
		if ( ! isset( $sources[ $source ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select the source of consent.', 'contacthub' ) ), 400 );
		}
		$date_object = DateTime::createFromFormat( 'Y-m-d', $date );
		if ( ! $date_object || $date_object->format( 'Y-m-d' ) !== $date ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid date.', 'contacthub' ) ), 400 );
		}
		if ( $date > current_time( 'Y-m-d' ) ) {
			wp_send_json_error( array( 'message' => __( 'The date must not be in the future.', 'contacthub' ) ), 400 );
		}

		$is_grant = 'grant' === $decision;
		$current_contact = self::contact( $contact_id );
		$current_status = $current_contact ? self::optin_status( $current_contact ) : '';
		if ( $is_grant && in_array( $current_status, array( 6, 7, 8, 9, 'hard_bounce', 'spam', 'complained', 'blocked' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'This address is blocked because of a delivery, spam, or complaint status. The ordinary consent override cannot remove this protection.', 'contacthub' ) ), 409 );
		}
		$meta = array(
			'marketing_consent'        => $is_grant ? 'yes' : 'no',
			'marketing_consent_source' => $sources[ $source ],
		);
		if ( $is_grant ) {
			$meta['marketing_consent_date'] = $date;
		} else {
			$meta['marketing_consent_revoked_date'] = $date;
		}
		if ( ContactHub_Store::standalone() ) {
			$current_user = wp_get_current_user();
			$summary = $is_grant ? __( 'Marketing consent granted manually', 'contacthub' ) : __( 'Marketing consent revoked manually', 'contacthub' );
			$content = sprintf( __( '%1$s on %2$s. Source: %3$s. Edited by: %4$s.%5$s', 'contacthub' ), $summary, $date, $sources[ $source ], $current_user->display_name, $note ? __( ' Note: ', 'contacthub' ) . $note : '' );
			ContactHub_Store::record_consent( $contact_id, $is_grant ? 'granted' : 'revoked', $sources[ $source ], $date, $content );
			wp_send_json_success( array( 'status' => $is_grant ? 'confirmed' : 'unsubscribed', 'label' => $is_grant ? __( 'Marketing allowed', 'contacthub' ) : __( 'Unsubscribed', 'contacthub' ), 'message' => __( 'Marketing status and log were saved.', 'contacthub' ) ) );
		}

		$result = self::execute_ability(
			'groundhogg/update-contact',
			array(
				'id'           => $contact_id,
				'optin_status' => $is_grant ? 'confirmed' : 'unsubscribed',
				'meta'         => $meta,
			)
		);
		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Groundhogg could not change the marketing status.', 'contacthub' ) ), 500 );
		}

		$current_user = wp_get_current_user();
		$summary = $is_grant ? __( 'Marketing consent granted manually', 'contacthub' ) : __( 'Marketing consent revoked manually', 'contacthub' );
		$content = sprintf(
			__( '%1$s on %2$s. Source: %3$s. Edited by: %4$s.%5$s', 'contacthub' ),
			$summary,
			$date,
			$sources[ $source ],
			$current_user->display_name,
			$note ? __( ' Note: ', 'contacthub' ) . $note : ''
		);
		$logged = self::execute_ability(
			'groundhogg/add-contact-note',
			array( 'contact_id' => $contact_id, 'summary' => $summary, 'content' => $content )
		);

		wp_send_json_success(
			array(
				'status'  => $is_grant ? 'confirmed' : 'unsubscribed',
				'label'   => $is_grant ? __( 'Marketing allowed', 'contacthub' ) : __( 'Unsubscribed', 'contacthub' ),
				'message' => false === $logged ? __( 'Status saved; the additional log note could not be created.', 'contacthub' ) : __( 'Marketing status and log were saved.', 'contacthub' ),
			)
		);
	}

	public static function bulk_marketing_consent() {
		self::guard_ajax();

		$contact_ids = isset( $_POST['contact_ids'] ) ? (array) wp_unslash( $_POST['contact_ids'] ) : array();
		$contact_ids = array_values( array_unique( array_filter( array_map( 'absint', $contact_ids ) ) ) );
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$confirmed = isset( $_POST['confirmed'] ) && '1' === (string) $_POST['confirmed'];
		$sources = self::marketing_consent_sources();

		if ( ! $contact_ids || count( $contact_ids ) > 100 ) {
			wp_send_json_error( array( 'message' => __( 'Please select between 1 and 100 people.', 'contacthub' ) ), 400 );
		}
		if ( ! $confirmed ) {
			wp_send_json_error( array( 'message' => __( 'Explicit confirmation is required.', 'contacthub' ) ), 400 );
		}
		if ( ! isset( $sources[ $source ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select the source of consent.', 'contacthub' ) ), 400 );
		}
		$date_object = DateTime::createFromFormat( 'Y-m-d', $date );
		if ( ! $date_object || $date_object->format( 'Y-m-d' ) !== $date || $date > current_time( 'Y-m-d' ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid date that is not in the future.', 'contacthub' ) ), 400 );
		}

		$result = array( 'changed' => 0, 'already' => 0, 'protected' => 0, 'failed' => 0 );
		$current_user = wp_get_current_user();
		foreach ( $contact_ids as $contact_id ) {
			$contact = self::contact( $contact_id );
			if ( ! $contact ) {
				$result['failed']++;
				continue;
			}
			$status = self::optin_status( $contact );
			if ( self::is_marketable_status( $status ) ) {
				$result['already']++;
				continue;
			}
			if ( self::is_protected_optout_status( $status ) ) {
				$result['protected']++;
				continue;
			}

			if ( ContactHub_Store::standalone() ) {
				$updated = ContactHub_Store::record_consent( $contact_id, 'granted', $sources[ $source ], $date, $note );
			} else {
				$updated = self::execute_ability(
				'groundhogg/update-contact',
				array(
					'id'           => $contact_id,
					'optin_status' => 'confirmed',
					'meta'         => array(
						'marketing_consent'        => 'yes',
						'marketing_consent_source' => $sources[ $source ],
						'marketing_consent_date'   => $date,
					),
				)
				);
			}
			if ( false === $updated ) {
				$result['failed']++;
				continue;
			}

			$content = sprintf(
				__( 'Marketing consent enabled by bulk edit on %1$s. Source: %2$s. Edited by: %3$s.%4$s', 'contacthub' ),
				$date,
				$sources[ $source ],
				$current_user->display_name,
				$note ? __( ' Note: ', 'contacthub' ) . $note : ''
			);
			if ( ! ContactHub_Store::standalone() ) self::execute_ability( 'groundhogg/add-contact-note', array( 'contact_id' => $contact_id, 'summary' => __( 'Marketing consent enabled by bulk edit', 'contacthub' ), 'content' => $content ) );
			$result['changed']++;
		}

		$message = sprintf(
			__( '%1$d changed, %2$d already marketable, %3$d skipped due to unsubscribe or blocked status, %4$d failed.', 'contacthub' ),
			$result['changed'],
			$result['already'],
			$result['protected'],
			$result['failed']
		);
		wp_send_json_success( array_merge( $result, array( 'message' => $message ) ) );
	}

	public static function export_contacts() {
		self::guard_contact_output();
		$contacts = self::contacts_for_output();
		$all_fields = self::contact_output_fields();
		$requested = isset( $_POST['columns'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['columns'] ) ) : array();
		$columns = array_values( array_intersect( $requested, array_keys( $all_fields ) ) );
		if ( ! $columns ) {
			$columns = array( 'first_name', 'last_name', 'email', 'phone', 'company', 'postcode', 'city', 'country' );
		}
		$rows = self::contact_output_rows( $contacts );
		$filename = 'contacthub-contacts-' . current_time( 'Y-m-d' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo "\xEF\xBB\xBF";
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array_map( static function ( $key ) use ( $all_fields ) { return $all_fields[ $key ]; }, $columns ), ';' );
		foreach ( $rows as $row ) {
			fputcsv( $output, array_map( static function ( $key ) use ( $row ) { return self::csv_safe_value( isset( $row[ $key ] ) ? $row[ $key ] : '' ); }, $columns ), ';' );
		}
		fclose( $output );
		exit;
	}

	public static function print_contacts() {
		self::guard_contact_output();
		$contacts = self::contacts_for_output();
		$all_fields = self::contact_output_fields();
		$requested = isset( $_POST['columns'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['columns'] ) ) : array();
		$columns = array_values( array_intersect( $requested, array_keys( $all_fields ) ) );
		if ( ! $columns ) {
			$columns = array( 'first_name', 'last_name', 'email', 'phone', 'company', 'postcode', 'city', 'country' );
		}
		$rows = self::contact_output_rows( $contacts );
		$landscape = count( $columns ) > 6;
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="' . esc_attr( get_option( 'blog_charset' ) ) . '"><title>' . esc_html__( 'Contact list', 'contacthub' ) . '</title><style>@page{size:' . ( $landscape ? 'landscape' : 'portrait' ) . ';margin:12mm}*{box-sizing:border-box}body{margin:0;color:#202830;font:12px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}header{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:18px}h1{margin:0;font-size:22px}p{margin:3px 0;color:#65717e}.print-button{padding:8px 13px;border:1px solid #8c8f94;border-radius:4px;background:#fff;cursor:pointer}table{width:100%;border-collapse:collapse;table-layout:auto}th,td{padding:7px 8px;border:1px solid #cfd5db;text-align:left;vertical-align:top;overflow-wrap:anywhere}th{background:#eef1f4;font-weight:700}tbody tr:nth-child(even){background:#f8f9fa}@media print{.print-button{display:none}thead{display:table-header-group}tr{break-inside:avoid}}</style></head><body><header><div><h1>' . esc_html__( 'Contact list', 'contacthub' ) . '</h1><p>' . esc_html( sprintf( __( '%1$d people · created on %2$s', 'contacthub' ), count( $rows ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) ) . '</p></div><button class="print-button" onclick="window.print()">' . esc_html__( 'Print', 'contacthub' ) . '</button></header><table><thead><tr>';
		foreach ( $columns as $column ) echo '<th>' . esc_html( $all_fields[ $column ] ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $columns as $column ) {
				$value = isset( $row[ $column ] ) ? $row[ $column ] : '';
				if ( 'company' === $column && self::text_length( $value ) > 30 ) {
					$value = self::text_substr( $value, 0, 30 ) . '…';
				}
				echo '<td>' . esc_html( $value ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table><script>window.addEventListener("load",function(){window.print();});</script></body></html>';
		exit;
	}

	private static function guard_contact_output() {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to export or print contacts.', 'contacthub' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'contacthub_contact_output' );
	}

	private static function contacts_for_output() {
		$search = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
		$customer_filter = isset( $_POST['kunden'] ) ? sanitize_key( wp_unslash( $_POST['kunden'] ) ) : 'alle';
		$segment_id = isset( $_POST['segment'] ) ? absint( $_POST['segment'] ) : 0;
		$advanced_filter = isset( $_POST['contacthub_filter'] ) ? substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) wp_unslash( $_POST['contacthub_filter'] ) ), 0, 12000 ) : '';
		$orderby = isset( $_POST['orderby'] ) ? sanitize_key( wp_unslash( $_POST['orderby'] ) ) : '';
		$order = isset( $_POST['order'] ) && 'desc' === sanitize_key( wp_unslash( $_POST['order'] ) ) ? 'desc' : 'asc';
		$contacts = self::all_contacts( $search );
		$order_summaries = self::contact_order_summaries( $contacts );
		$event_summaries = self::events_available() ? self::contact_event_summaries( $contacts ) : array();
		if ( 'besteller' === $customer_filter ) {
			$contacts = array_values( array_filter( $contacts, static function ( $contact ) use ( $order_summaries ) { $id = absint( self::value( $contact, array( 'id', 'ID' ) ) ); return ! empty( $order_summaries[ $id ]['count'] ); } ) );
		} elseif ( 'event-besucher' === $customer_filter ) {
			$contacts = array_values( array_filter( $contacts, static function ( $contact ) use ( $event_summaries ) { $id = absint( self::value( $contact, array( 'id', 'ID' ) ) ); return ! empty( $event_summaries[ $id ]['visited'] ); } ) );
		}
		if ( $segment_id || $advanced_filter ) $contacts = apply_filters( 'contacthub_people_contacts', $contacts, $segment_id, $advanced_filter );
		if ( in_array( $orderby, array( 'first_name', 'last_name', 'company', 'order_total', 'event_count' ), true ) ) {
			$company_map = self::contact_company_map();
			usort( $contacts, static function ( $left, $right ) use ( $orderby, $order, $company_map, $order_summaries, $event_summaries ) { $comparison = self::compare_contacts( $left, $right, $orderby, $company_map, $order_summaries, $event_summaries ); return 'desc' === $order ? -$comparison : $comparison; } );
		}
		$scope = isset( $_POST['scope'] ) && 'selected' === sanitize_key( wp_unslash( $_POST['scope'] ) ) ? 'selected' : 'all';
		if ( 'selected' === $scope ) {
			$ids = isset( $_POST['selected_ids'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['selected_ids'] ) ) ) ) ) : array();
			if ( ! $ids ) wp_die( esc_html__( 'Please select at least one person.', 'contacthub' ), '', array( 'response' => 400 ) );
			$contacts = array_values( array_filter( $contacts, static function ( $contact ) use ( $ids ) { return in_array( absint( self::value( $contact, array( 'id', 'ID' ) ) ), $ids, true ); } ) );
		}
		return $contacts;
	}

	private static function contact_output_fields() {
		return array(
			'first_name' => __( 'First name', 'contacthub' ), 'last_name' => __( 'Last name', 'contacthub' ), 'title_prefix' => __( 'Prefix title', 'contacthub' ), 'title_suffix' => __( 'Suffix title', 'contacthub' ),
			'email' => __( 'Email', 'contacthub' ), 'phone' => __( 'Phone', 'contacthub' ), 'company' => __( 'Company', 'contacthub' ), 'position' => __( 'Position / Job title', 'contacthub' ), 'uid' => __( 'VAT ID', 'contacthub' ),
			'address_1' => __( 'Street and house number', 'contacthub' ), 'address_2' => __( 'Address line 2', 'contacthub' ), 'postcode' => __( 'Postcode', 'contacthub' ), 'city' => __( 'Location', 'contacthub' ), 'state' => __( 'State / region', 'contacthub' ), 'country' => __( 'Country', 'contacthub' ),
			'tags' => __( 'Tags', 'contacthub' ), 'roles' => __( 'WordPress roles', 'contacthub' ), 'optin_status' => __( 'Opt-in status', 'contacthub' ), 'email_confirmed' => __( 'Email confirmed', 'contacthub' ), 'marketable' => __( 'Marketable', 'contacthub' ),
			'orders' => __( 'Orders', 'contacthub' ), 'order_total' => __( 'Order total', 'contacthub' ), 'events' => __( 'Events', 'contacthub' ), 'created_at' => __( 'Date created', 'contacthub' ),
		);
	}

	private static function csv_safe_value( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		return preg_match( '/^[=+\-@\t\r]/u', $value ) ? "'" . $value : $value;
	}

	private static function text_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value ) : strlen( (string) $value );
	}

	private static function text_substr( $value, $start, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $value, $start, $length ) : substr( (string) $value, $start, $length );
	}

	private static function contact_output_rows( $contacts ) {
		$company_map = self::contact_company_map();
		$order_summaries = self::contact_order_summaries( $contacts );
		$event_summaries = self::events_available() ? self::contact_event_summaries( $contacts ) : array();
		$role_labels = self::editable_roles();
		$countries = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : self::contact_countries();
		$rows = array();
		foreach ( $contacts as $contact ) {
			$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
			$user = self::contact_user( $contact );
			$address = self::contact_address( $contact );
			if ( ! array_filter( $address ) && $user && class_exists( 'WC_Customer' ) ) { try { $address = self::customer_address( new WC_Customer( $user->ID ), 'billing' ); } catch ( Throwable $e ) {} }
			$company = isset( $company_map[ $id ][0] ) ? self::value( $company_map[ $id ][0], array( 'name', 'company_name', 'get_name' ) ) : self::meta( $contact, array( 'company_name', 'company', 'billing_company' ) );
			if ( ! $company && $user ) $company = get_user_meta( $user->ID, 'billing_company', true );
			$uid = $user ? ( get_user_meta( $user->ID, 'billing_vat', true ) ?: get_user_meta( $user->ID, 'b2b_uid', true ) ) : self::meta( $contact, array( 'vat_id', 'uid' ) );
			$tags = is_array( $contact ) && isset( $contact['tags'] ) ? self::user_managed_tags( (array) $contact['tags'] ) : array();
			$tag_names = array(); foreach ( $tags as $tag ) { $name = self::value( $tag, array( 'name', 'tag_name', 'get_name' ) ); if ( $name ) $tag_names[] = $name; }
			$roles = array(); if ( $user ) foreach ( (array) $user->roles as $role ) $roles[] = isset( $role_labels[ $role ] ) ? $role_labels[ $role ] : $role;
			$status = self::optin_status( $contact );
			$confirmed = ContactHub_Store::standalone() ? (bool) self::meta( $contact, array( 'email_confirmed' ) ) : in_array( $status, array( 2, 4, 5, 'confirmed', 'weekly', 'monthly' ), true );
			$summary = isset( $order_summaries[ $id ] ) ? $order_summaries[ $id ] : self::empty_order_summary();
			$rows[] = array(
				'first_name' => self::value( $contact, array( 'first_name', 'get_first_name' ) ), 'last_name' => self::value( $contact, array( 'last_name', 'get_last_name' ) ),
				'title_prefix' => self::meta( $contact, array( 'titel_vorangestellt', 'title_prefix' ) ), 'title_suffix' => self::meta( $contact, array( 'titel_nachgestellt', 'title_suffix' ) ),
				'email' => self::value( $contact, array( 'email', 'get_email' ) ), 'phone' => self::format_phone( self::meta( $contact, array( 'primary_phone', 'phone', 'phone_number' ) ) ), 'company' => $company, 'position' => self::meta( $contact, array( 'berufsbezeichnung', 'job_title', 'position' ) ), 'uid' => $uid,
				'address_1' => $address['address_1'] ?? '', 'address_2' => $address['address_2'] ?? '', 'postcode' => $address['postcode'] ?? '', 'city' => $address['city'] ?? '', 'state' => $address['state'] ?? '', 'country' => isset( $countries[ $address['country'] ?? '' ] ) ? $countries[ $address['country'] ] : ( $address['country'] ?? '' ),
				'tags' => implode( ', ', $tag_names ), 'roles' => implode( ', ', $roles ), 'optin_status' => self::status_label( $status ), 'email_confirmed' => $confirmed ? __( 'Yes', 'contacthub' ) : __( 'No', 'contacthub' ), 'marketable' => self::is_marketable_status( $status ) ? __( 'Yes', 'contacthub' ) : __( 'No', 'contacthub' ),
				'orders' => (string) $summary['count'], 'order_total' => function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $summary['total'], 2 ) : number_format( (float) $summary['total'], 2, '.', '' ), 'events' => (string) ( $event_summaries[ $id ]['visited'] ?? 0 ), 'created_at' => self::value( $contact, array( 'created_at', 'date_created', 'get_date_created' ) ),
			);
		}
		return $rows;
	}

	public static function create_contact() {
		self::guard_ajax();

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$salutation = isset( $_POST['salutation'] ) ? sanitize_key( wp_unslash( $_POST['salutation'] ) ) : '';
		if ( ! in_array( $salutation, array( '', 'mr', 'ms', 'family', 'neutral' ), true ) ) $salutation = '';
		if ( '' === $salutation && '' !== $first_name ) {
			$salutation = ContactHub_Store::suggested_salutation( $first_name );
		}
		$company_id = isset( $_POST['company_id'] ) ? absint( $_POST['company_id'] ) : 0;
		$company_name = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
		$position = isset( $_POST['company_position'] ) ? sanitize_text_field( wp_unslash( $_POST['company_position'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'contacthub' ) ), 400 );
		}
		if ( '' === trim( $first_name . $last_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter at least a first or last name.', 'contacthub' ) ), 400 );
		}
		if ( ContactHub_Store::standalone() && ContactHub_Store::contact_by_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'A person with this email address already exists.', 'contacthub' ) ), 409 );
		}
		if ( ! ContactHub_Store::standalone() && function_exists( '\\Groundhogg\\is_email_address_in_use' ) && \Groundhogg\is_email_address_in_use( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'A person with this email address already exists.', 'contacthub' ) ), 409 );
		}

		if ( ContactHub_Store::standalone() ) {
			$created = ContactHub_Store::create_contact( array( 'email' => $email, 'first_name' => $first_name, 'last_name' => $last_name, 'anrede' => $salutation, 'optin_status' => 'unconfirmed', 'source' => 'manual' ) );
			$contact_id = is_wp_error( $created ) ? 0 : absint( $created );
		} else {
			$result = self::execute_ability(
			'groundhogg/create-contact',
			array(
				'email'        => $email,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'optin_status' => 'unconfirmed',
				'meta'         => array( 'anrede' => $salutation ),
				'expand'       => array( 'tags', 'meta' ),
			)
			);
			$contact_id = is_array( $result ) && isset( $result['contact']['id'] ) ? absint( $result['contact']['id'] ) : 0;
		}
		if ( ! $contact_id ) {
			wp_send_json_error( array( 'message' => __( 'The selected data source could not create the person.', 'contacthub' ) ), 500 );
		}
		if ( $first_name && $salutation ) ContactHub_Store::learn_salutation( $first_name, $salutation, 'confirmed' );

		$company_warning = '';
		if ( $company_name && self::companies_available() ) {
			$link_result = self::assign_contact_company( $contact_id, $company_id, $company_name, $position );
			if ( is_wp_error( $link_result ) ) {
				// The person already exists at this point. Report the partial result as a
				// warning instead of inviting a retry that would create a duplicate.
				$company_warning = ' ' . $link_result->get_error_message();
			}
		}
		wp_send_json_success(
			array(
				'message'  => __( 'The person was created with the status “Unconfirmed”.', 'contacthub' ) . $company_warning,
				'redirect' => admin_url( 'admin.php?page=' . self::people_page_slug() . '&person=' . $contact_id ),
			)
		);
	}

	public static function create_company() {
		self::guard_ajax();
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a company name.', 'contacthub' ) ), 400 );
		}
		$id = self::create_company_record( $name );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'The company could not be created in the selected data source.', 'contacthub' ) ), 500 );
		}
		wp_send_json_success( array( 'message' => __( 'The company was created.', 'contacthub' ), 'redirect' => admin_url( 'admin.php?page=contacthub-firmen&firma=' . $id ) ) );
	}

	public static function manage_contact_tag() {
		self::guard_ajax();

		$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$tag_id = isset( $_POST['tag_id'] ) ? absint( $_POST['tag_id'] ) : 0;
		$tag_name = isset( $_POST['tag_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tag_name'] ) ) : '';

		if ( ! in_array( $operation, array( 'add', 'remove', 'create' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tag action.', 'contacthub' ) ), 400 );
		}
		if ( ! $contact_id || ! self::contact( $contact_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The person was not found.', 'contacthub' ) ), 404 );
		}

		if ( 'create' === $operation ) {
			if ( '' === $tag_name ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a tag name.', 'contacthub' ) ), 400 );
			}
			if ( ContactHub_Store::standalone() ) {
				$tag_id = ContactHub_Store::create_tag( $tag_name );
			} else {
				$created = self::execute_ability( 'groundhogg/create-tag', array( 'name' => $tag_name ) );
				$tag_id = is_array( $created ) ? absint( self::value( $created, array( 'id', 'tag_id', 'ID' ) ) ) : 0;
				if ( ! $tag_id ) {
					$tag_id = self::groundhogg_create_tag( $tag_name );
				}
			}
			if ( ! $tag_id ) {
				wp_send_json_error( array( 'message' => __( 'Groundhogg could not create the tag.', 'contacthub' ) ), 500 );
			}
			$operation = 'add';
		}

		if ( in_array( $operation, array( 'add', 'remove' ), true ) ) {
			if ( ! $tag_id ) {
				wp_send_json_error( array( 'message' => __( 'Please select a tag.', 'contacthub' ) ), 400 );
			}
			$key = 'add' === $operation ? 'tags_add' : 'tags_remove';
			$updated = ContactHub_Store::standalone() ? ContactHub_Store::assign_tag( $contact_id, $tag_id, 'add' === $operation ) : self::execute_ability( 'groundhogg/update-contact', array( 'id' => $contact_id, $key => array( $tag_id ), 'expand' => array( 'tags' ) ) );
			if ( ! ContactHub_Store::standalone() && false === $updated ) {
				$updated = self::groundhogg_assign_tag( $contact_id, $tag_id, 'add' === $operation );
			}
			if ( false === $updated ) {
				wp_send_json_error( array( 'message' => __( 'Groundhogg could not change the tag assignment.', 'contacthub' ) ), 500 );
			}
			wp_send_json_success( array( 'message' => 'add' === $operation ? __( 'Tag assigned.', 'contacthub' ) : __( 'Tag removed from this person.', 'contacthub' ) ) );
		}
	}

	public static function render_tags_page() {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage tags.', 'contacthub' ) );
		}
		$search = isset( $_GET['s'] ) && is_scalar( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$all_tags = self::all_tags();
		$counts = self::tag_assignment_counts();
		$tags = $all_tags;
		if ( '' !== $search ) {
			$tags = array_values( array_filter( $tags, static function ( $tag ) use ( $search ) { return false !== stripos( $tag['name'], $search ); } ) );
		}
		$page = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$per_page = 20;
		$total = count( $tags );
		$visible_tags = array_slice( $tags, ( $page - 1 ) * $per_page, $per_page );
		$edit_id = isset( $_GET['tag_id'] ) ? absint( $_GET['tag_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only editor selection.
		$edit_tag = false;
		foreach ( $all_tags as $candidate ) {
			if ( $edit_id === absint( $candidate['id'] ) ) {
				$edit_tag = $candidate;
				break;
			}
		}
		$notice = get_transient( 'contacthub_tag_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'contacthub_tag_notice_' . get_current_user_id() );
		}
		echo '<div class="wrap contacthub-wrap contacthub-tags-page"><header class="contacthub-heading"><div class="contacthub-avatar"><span class="dashicons dashicons-tag"></span></div><div><p class="contacthub-eyebrow">ContactHub</p><h1>' . esc_html__( 'Tags', 'contacthub' ) . '</h1><p>' . esc_html__( 'Create and manage tags in the currently selected data source.', 'contacthub' ) . '</p></div></header>';
		if ( is_array( $notice ) ) {
			echo '<div class="notice notice-' . ( ! empty( $notice['success'] ) ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
		echo '<div class="contacthub-taxonomy-layout"><section class="contacthub-card contacthub-tag-editor"><h2>' . esc_html( $edit_tag ? __( 'Edit tag', 'contacthub' ) : __( 'Add new tag', 'contacthub' ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_tag_action"><input type="hidden" name="operation" value="' . esc_attr( $edit_tag ? 'rename' : 'create' ) . '">';
		if ( $edit_tag ) {
			echo '<input type="hidden" name="tag_id" value="' . esc_attr( $edit_tag['id'] ) . '">';
		}
		wp_nonce_field( 'contacthub_tag_action' );
		echo '<label for="contacthub-new-tag"><strong>' . esc_html__( 'Name', 'contacthub' ) . '</strong></label><input id="contacthub-new-tag" class="regular-text" type="text" name="tag_name" required value="' . esc_attr( $edit_tag ? $edit_tag['name'] : '' ) . '"><p class="description">' . esc_html__( 'The name is displayed in contact profiles and filters.', 'contacthub' ) . '</p>';
		echo '<label for="contacthub-tag-description"><strong>' . esc_html__( 'Description (optional)', 'contacthub' ) . '</strong></label><textarea id="contacthub-tag-description" class="large-text" rows="4" name="tag_description">' . esc_textarea( $edit_tag['description'] ?? '' ) . '</textarea><p class="description">' . esc_html__( 'Use the description to document the purpose of this tag.', 'contacthub' ) . '</p><p class="submit"><button class="button button-primary">' . esc_html( $edit_tag ? __( 'Update tag', 'contacthub' ) : __( 'Add new tag', 'contacthub' ) ) . '</button>';
		if ( $edit_tag ) {
			echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=contacthub-tags' ) ) . '">' . esc_html__( 'Cancel', 'contacthub' ) . '</a>';
		}
		echo '</p></form></section><section class="contacthub-card contacthub-tag-list"><div class="contacthub-toolbar"><h2>' . esc_html__( 'Tags', 'contacthub' ) . '</h2><span class="contacthub-count">' . esc_html( $total ) . '</span></div>';
		echo '<form method="get" class="contacthub-tag-search"><input type="hidden" name="page" value="contacthub-tags"><label class="screen-reader-text" for="contacthub-tag-search">' . esc_html__( 'Search tags', 'contacthub' ) . '</label><input id="contacthub-tag-search" type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search tags', 'contacthub' ) . '"><button class="button">' . esc_html__( 'Search', 'contacthub' ) . '</button></form>';
		if ( ! $visible_tags ) {
			echo '<p class="contacthub-muted">' . esc_html__( 'No matching tags were found.', 'contacthub' ) . '</p></section></div></div>';
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contacthub-tag-bulk-form"><input type="hidden" name="action" value="contacthub_tag_action"><input type="hidden" name="operation" value="bulk_delete"><input type="hidden" name="delete_confirmed" value="0">';
		wp_nonce_field( 'contacthub_tag_action' );
		echo '<div class="contacthub-tag-bulk-actions"><select name="bulk_action"><option value="">' . esc_html__( 'Bulk actions', 'contacthub' ) . '</option><option value="delete">' . esc_html__( 'Delete', 'contacthub' ) . '</option></select><button class="button">' . esc_html__( 'Apply', 'contacthub' ) . '</button></div>';
		echo '<table class="widefat striped contacthub-table contacthub-tags-admin-table"><thead><tr><td class="check-column"><input type="checkbox" class="contacthub-select-all-tags" aria-label="' . esc_attr__( 'Select all tags', 'contacthub' ) . '"></td><th>' . esc_html__( 'Name', 'contacthub' ) . '</th><th>' . esc_html__( 'Description', 'contacthub' ) . '</th><th>' . esc_html__( 'Assigned people', 'contacthub' ) . '</th></tr></thead><tbody>';
		foreach ( $visible_tags as $tag ) {
			$id = absint( $tag['id'] );
			$count = isset( $counts[ $id ] ) ? absint( $counts[ $id ] ) : 0;
			$edit_url = admin_url( 'admin.php?page=contacthub-tags&action=edit&tag_id=' . $id );
			$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=contacthub_tag_action&operation=delete&delete_confirmed=1&tag_id=' . $id ), 'contacthub_tag_action' );
			echo '<tr><th class="check-column"><input type="checkbox" name="tag_ids[]" value="' . esc_attr( $id ) . '" aria-label="' . esc_attr( sprintf( __( 'Select %s', 'contacthub' ), $tag['name'] ) ) . '"></th><td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $tag['name'] ) . '</a></strong><div class="row-actions"><span><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'contacthub' ) . '</a> | </span><span class="delete"><a class="contacthub-delete-tag-link" data-count="' . esc_attr( $count ) . '" data-name="' . esc_attr( $tag['name'] ) . '" href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Delete', 'contacthub' ) . '</a></span></div></td><td>' . esc_html( $tag['description'] ?? '—' ) . '</td><td>' . esc_html( number_format_i18n( $count ) ) . '</td></tr>';
		}
		echo '</tbody></table></form>';
		if ( $total > $per_page ) {
			$base = add_query_arg( array( 'page' => 'contacthub-tags', 's' => $search, 'paged' => '%#%' ), admin_url( 'admin.php' ) );
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => $base, 'format' => '', 'current' => $page, 'total' => (int) ceil( $total / $per_page ) ) ) ) . '</div></div>';
		}
		echo '<p class="description">' . esc_html__( 'Deleting a tag removes only the tag and its assignments. People and their other data remain unchanged.', 'contacthub' ) . '</p></section></div></div>';
	}

	public static function handle_tag_action() {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage tags.', 'contacthub' ) );
		}
		check_admin_referer( 'contacthub_tag_action' );
		$operation = isset( $_REQUEST['operation'] ) ? sanitize_key( wp_unslash( $_REQUEST['operation'] ) ) : '';
		$tag_id = isset( $_REQUEST['tag_id'] ) ? absint( $_REQUEST['tag_id'] ) : 0;
		$name = isset( $_REQUEST['tag_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['tag_name'] ) ) : '';
		$description = isset( $_REQUEST['tag_description'] ) ? sanitize_textarea_field( wp_unslash( $_REQUEST['tag_description'] ) ) : '';
		$success = false;
		$message = __( 'The tag action could not be completed.', 'contacthub' );
		if ( 'create' === $operation && $name ) {
			$created_id = ContactHub_Store::standalone() ? ContactHub_Store::create_tag( $name, $description ) : self::groundhogg_create_tag( $name );
			$success = (bool) $created_id;
			if ( $success && ! ContactHub_Store::standalone() ) self::set_tag_description( $created_id, $description );
			$message = $success ? __( 'The tag was created.', 'contacthub' ) : __( 'The tag could not be created or already exists.', 'contacthub' );
		} elseif ( 'rename' === $operation && $tag_id && $name ) {
			$success = ContactHub_Store::standalone() ? ContactHub_Store::rename_tag( $tag_id, $name, $description ) : self::groundhogg_rename_tag( $tag_id, $name );
			if ( $success && ! ContactHub_Store::standalone() ) self::set_tag_description( $tag_id, $description );
			$message = $success ? __( 'The tag was renamed.', 'contacthub' ) : __( 'The tag could not be renamed.', 'contacthub' );
		} elseif ( 'delete' === $operation && $tag_id && isset( $_REQUEST['delete_confirmed'] ) && '1' === (string) $_REQUEST['delete_confirmed'] ) {
			$success = ContactHub_Store::standalone() ? ContactHub_Store::delete_tag( $tag_id ) : self::groundhogg_delete_tag( $tag_id );
			$message = $success ? __( 'The tag and its assignments were deleted.', 'contacthub' ) : __( 'The tag could not be deleted.', 'contacthub' );
		} elseif ( 'bulk_delete' === $operation && isset( $_POST['bulk_action'], $_POST['delete_confirmed'] ) && 'delete' === sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) && '1' === (string) $_POST['delete_confirmed'] ) {
			$ids = isset( $_POST['tag_ids'] ) && is_array( $_POST['tag_ids'] ) ? array_values( array_filter( array_map( 'absint', $_POST['tag_ids'] ) ) ) : array();
			$deleted = 0;
			foreach ( $ids as $id ) {
				if ( ContactHub_Store::standalone() ? ContactHub_Store::delete_tag( $id ) : self::groundhogg_delete_tag( $id ) ) {
					$deleted++;
				}
			}
			$success = $deleted > 0;
			$message = $success ? sprintf( __( 'Deleted tags: %d', 'contacthub' ), $deleted ) : __( 'Please select at least one tag.', 'contacthub' );
		}
		set_transient( 'contacthub_tag_notice_' . get_current_user_id(), array( 'success' => $success, 'message' => $message ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=contacthub-tags' ) );
		exit;
	}

	private static function tag_assignment_counts() {
		if ( ContactHub_Store::standalone() ) {
			return ContactHub_Store::tag_counts();
		}
		$counts = array();
		foreach ( self::all_contacts() as $contact ) {
			foreach ( isset( $contact['tags'] ) && is_array( $contact['tags'] ) ? $contact['tags'] : array() as $tag ) {
				$id = absint( self::value( $tag, array( 'id', 'tag_id', 'ID' ) ) );
				if ( $id ) {
					$counts[ $id ] = isset( $counts[ $id ] ) ? $counts[ $id ] + 1 : 1;
				}
			}
		}
		return $counts;
	}

	private static function groundhogg_create_tag( $name ) {
		if ( ! function_exists( '\\Groundhogg\\get_db' ) ) {
			return 0;
		}
		try {
			$db = \Groundhogg\get_db( 'tags' );
			$existing = $db->query( array( 'search' => $name, 'limit' => 100 ) );
			foreach ( (array) $existing as $tag ) {
				if ( 0 === strcasecmp( self::value( $tag, array( 'tag_name', 'name', 'get_name' ) ), $name ) ) {
					return absint( self::value( $tag, array( 'tag_id', 'id', 'ID', 'get_id' ) ) );
				}
			}
			if ( method_exists( $db, 'add' ) ) {
				return absint( $db->add( array( 'tag_name' => $name, 'tag_slug' => sanitize_title( $name ) ) ) );
			}
		} catch ( Throwable $e ) {
			return 0;
		}
		return 0;
	}

	private static function groundhogg_rename_tag( $tag_id, $name ) {
		if ( ! function_exists( '\\Groundhogg\\get_db' ) ) {
			return false;
		}
		try {
			$db = \Groundhogg\get_db( 'tags' );
			if ( method_exists( $db, 'update' ) ) {
				return false !== $db->update( $tag_id, array( 'tag_name' => $name, 'tag_slug' => sanitize_title( $name ) ) );
			}
		} catch ( Throwable $e ) {
			return false;
		}
		return false;
	}

	private static function groundhogg_delete_tag( $tag_id ) {
		if ( ! function_exists( '\\Groundhogg\\get_db' ) ) {
			return false;
		}
		try {
			$db = \Groundhogg\get_db( 'tags' );
			if ( method_exists( $db, 'delete' ) ) {
				return false !== $db->delete( $tag_id );
			}
		} catch ( Throwable $e ) {
			return false;
		}
		return false;
	}

	private static function groundhogg_assign_tag( $contact_id, $tag_id, $assign ) {
		if ( ! class_exists( '\\Groundhogg\\Contact' ) ) {
			return false;
		}
		try {
			$contact = new \Groundhogg\Contact( $contact_id );
			if ( method_exists( $contact, 'exists' ) && ! $contact->exists() ) {
				return false;
			}
			$methods = $assign ? array( 'apply_tag', 'add_tag' ) : array( 'remove_tag', 'delete_tag' );
			foreach ( $methods as $method ) {
				if ( method_exists( $contact, $method ) ) {
					$result = $contact->{$method}( $tag_id );
					return false !== $result;
				}
			}
		} catch ( Throwable $e ) {
			return false;
		}
		return false;
	}

	public static function preview_broadcast() {
		self::guard_ajax();

		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$broadcast_id = isset( $_POST['broadcast_id'] ) ? absint( $_POST['broadcast_id'] ) : 0;
		$allowed = false;
		foreach ( self::contact_broadcasts( $contact_id ) as $broadcast ) {
			if ( $broadcast_id === $broadcast['id'] ) {
				$allowed = true;
				break;
			}
		}
		if ( ! $allowed || ! class_exists( '\\Groundhogg\\Broadcast' ) ) {
			wp_send_json_error( array( 'message' => __( 'This broadcast was not found for the person.', 'contacthub' ) ), 404 );
		}

		try {
			$broadcast = new \Groundhogg\Broadcast( $broadcast_id );
			$email = $broadcast->get_object();
			if ( ! $broadcast->is_email() || ! $email || ! method_exists( $email, 'build' ) ) {
				wp_send_json_error( array( 'message' => __( 'The broadcast does not contain a displayable email.', 'contacthub' ) ), 404 );
			}
			$email->set_contact( $contact_id );
			$html = $email->build();
			$title = method_exists( $email, 'get_subject_line' ) ? $email->get_subject_line() : $broadcast->get_title();
		} catch ( Throwable $e ) {
			wp_send_json_error( array( 'message' => __( 'The broadcast email could not be rendered.', 'contacthub' ) ), 500 );
		}
		wp_send_json_success( array( 'title' => wp_strip_all_tags( $title ), 'html' => $html ) );
	}

	public static function preview_order() {
		self::guard_ajax();
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'The order was not found.', 'contacthub' ) ), 404 );
		}

		$billing = $order->get_formatted_billing_address();
		$shipping = $order->get_formatted_shipping_address();
		$html = '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><style>' . self::preview_document_css() . '</style></head><body>';
		$html .= __( '<header><div><span class="eyebrow">WooCommerce order</span><h1>Order #', 'contacthub' ) . esc_html( $order->get_order_number() ) . '</h1></div><span class="status">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span></header>';
		$html .= __( '<div class="facts"><div><span>Order date</span><strong>', 'contacthub' ) . esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd.m.Y H:i' ) : '—' ) . __( '</strong></div><div><span>Payment method</span><strong>', 'contacthub' ) . esc_html( $order->get_payment_method_title() ?: '—' ) . __( '</strong></div><div><span>Invoice number</span><strong>', 'contacthub' ) . esc_html( self::invoice_number( $order ) ?: '—' ) . '</strong></div></div>';
		$html .= __( '<div class="addresses"><section><h2>Billing address</h2><p>', 'contacthub' ) . ( $billing ? wp_kses_post( $billing ) : '—' ) . '</p><p>' . esc_html( $order->get_billing_email() ) . '<br>' . esc_html( self::format_phone( $order->get_billing_phone() ) ) . '</p></section>';
		if ( $order->needs_shipping_address() ) {
			$html .= __( '<section><h2>Shipping address</h2><p>', 'contacthub' ) . ( $shipping ? wp_kses_post( $shipping ) : '—' ) . '</p></section>';
		}
		$html .= __( '</div><table><thead><tr><th>Item</th><th class="num">Quantity</th><th class="num">Total</th></tr></thead><tbody>', 'contacthub' );
		foreach ( $order->get_items() as $item ) {
			$html .= '<tr><td><strong>' . esc_html( $item->get_name() ) . '</strong></td><td class="num">' . esc_html( $item->get_quantity() ) . '</td><td class="num">' . wp_kses_post( $order->get_formatted_line_subtotal( $item ) ) . '</td></tr>';
		}
		$html .= '</tbody><tfoot>';
		foreach ( $order->get_order_item_totals() as $total ) {
			$html .= '<tr><th colspan="2" class="num">' . wp_kses_post( $total['label'] ) . '</th><td class="num"><strong>' . wp_kses_post( $total['value'] ) . '</strong></td></tr>';
		}
		$html .= '</tfoot></table>';
		if ( $order->get_customer_note() ) {
			$html .= __( '<section class="note"><h2>Customer note</h2><p>', 'contacthub' ) . nl2br( esc_html( $order->get_customer_note() ) ) . '</p></section>';
		}
		$html .= '</body></html>';
		wp_send_json_success(
			array(
				'title'    => __( 'Order #', 'contacthub' ) . $order->get_order_number(),
				'html'     => $html,
				'edit_url' => $order->get_edit_order_url(),
			)
		);
	}

	public static function update_user_roles() {
		self::guard_user_admin_ajax();
		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$contact = self::contact( $contact_id );
		$user = $user_id ? get_user_by( 'id', $user_id ) : false;
		$linked_id = $contact ? absint( self::value( $contact, array( 'user_id', 'get_user_id' ) ) ) : 0;
		if ( ! $contact || ! $user || $linked_id !== $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The user account cannot be edited.', 'contacthub' ) ), 403 );
		}
		$roles = isset( $_POST['roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['roles'] ) ) : array();
		$result = self::set_user_roles( $user, $roles );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		self::log_contact_note( $contact_id, __( 'WordPress roles changed', 'contacthub' ), sprintf( __( 'WordPress roles changed to: %1$s. Edited by: %2$s.', 'contacthub' ), implode( ', ', $roles ), wp_get_current_user()->display_name ) );
		wp_send_json_success( array( 'message' => __( 'The WordPress roles were saved.', 'contacthub' ) ) );
	}

	public static function create_user_account() {
		self::guard_user_admin_ajax();
		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'create';
		$contact = self::contact( $contact_id );
		if ( ! $contact ) {
			wp_send_json_error( array( 'message' => __( 'The person was not found.', 'contacthub' ) ), 404 );
		}
		if ( ! ContactHub_Store::standalone() && ! class_exists( '\\Groundhogg\\Contact' ) ) {
			wp_send_json_error( array( 'message' => __( 'Groundhogg is unavailable; no user account was created.', 'contacthub' ) ), 503 );
		}
		$created_user = false;
		$send_invite = false;
		$email = sanitize_email( self::value( $contact, array( 'email', 'get_email' ) ) );
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'No valid email address is stored for this person.', 'contacthub' ) ), 400 );
		}
		$existing = get_user_by( 'email', $email );
		if ( 'link' === $mode ) {
			if ( ! $existing || ! current_user_can( 'edit_user', $existing->ID ) ) {
				wp_send_json_error( array( 'message' => __( 'The existing user account cannot be linked.', 'contacthub' ) ), 403 );
			}
			$user_id = (int) $existing->ID;
		} else {
			if ( $existing ) {
				wp_send_json_error( array( 'message' => __( 'A user account already exists for this email address. Please reload the page and use “Link”.', 'contacthub' ) ), 409 );
			}
			$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
			if ( ! $username || username_exists( $username ) ) {
				wp_send_json_error( array( 'message' => __( 'Please enter an available, valid username.', 'contacthub' ) ), 400 );
			}
			$roles = isset( $_POST['roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['roles'] ) ) : array( 'customer' );
			$valid_roles = array_keys( self::editable_roles() );
			$roles = array_values( array_intersect( array_unique( $roles ), $valid_roles ) );
			if ( ! $roles ) {
				wp_send_json_error( array( 'message' => __( 'Please select at least one permitted role.', 'contacthub' ) ), 400 );
			}
			$user_id = wp_insert_user(
				array(
					'user_login'   => $username,
					'user_email'   => $email,
					'user_pass'    => wp_generate_password( 32, true, true ),
					'first_name'   => self::value( $contact, array( 'first_name', 'get_first_name' ) ),
					'last_name'    => self::value( $contact, array( 'last_name', 'get_last_name' ) ),
					'display_name' => trim( self::value( $contact, array( 'first_name', 'get_first_name' ) ) . ' ' . self::value( $contact, array( 'last_name', 'get_last_name' ) ) ),
					'role'         => $roles[0],
				)
			);
			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( array( 'message' => $user_id->get_error_message() ), 400 );
			}
			$created_user = true;
			$user = get_user_by( 'id', $user_id );
			$role_result = self::set_user_roles( $user, $roles );
			if ( is_wp_error( $role_result ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $user_id );
				wp_send_json_error( array( 'message' => $role_result->get_error_message() ), 400 );
			}
			$send_invite = isset( $_POST['send_invite'] ) && '1' === (string) $_POST['send_invite'];
		}

		if ( ContactHub_Store::standalone() ) {
			if ( ! ContactHub_Store::link_user( $contact_id, $user_id ) ) {
				if ( $created_user ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $user_id ); }
				wp_send_json_error( array( 'message' => __( 'The user account could not be linked safely to ContactHub; the newly created account was rolled back.', 'contacthub' ) ), 500 );
			}
		} elseif ( class_exists( '\\Groundhogg\\Contact' ) ) {
			try {
				$groundhogg_contact = new \Groundhogg\Contact( $contact_id );
				$groundhogg_contact->update( array( 'user_id' => $user_id ) );
				if ( (int) $groundhogg_contact->get_user_id() !== (int) $user_id ) {
					throw new RuntimeException( __( 'Linking failed.', 'contacthub' ) );
				}
			} catch ( Throwable $e ) {
				if ( $created_user ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
					wp_delete_user( $user_id );
				}
				wp_send_json_error( array( 'message' => __( 'The user account could not be linked safely to Groundhogg; the newly created account was rolled back.', 'contacthub' ) ), 500 );
			}
		}
		if ( $created_user && $send_invite ) {
			wp_new_user_notification( $user_id, null, 'user' );
		}
		self::log_contact_note( $contact_id, __( 'WordPress user account', 'contacthub' ), sprintf( __( '%1$s Edited by: %2$s.', 'contacthub' ), 'link' === $mode ? __( 'Existing WordPress user account linked.', 'contacthub' ) : __( 'WordPress user account created and linked.', 'contacthub' ), wp_get_current_user()->display_name ) );
		wp_send_json_success( array( 'message' => 'link' === $mode ? __( 'The user account was linked.', 'contacthub' ) : __( 'The user account was created and linked.', 'contacthub' ) ) );
	}

	public static function update_customer_address() {
		self::guard_ajax();

		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$type = isset( $_POST['address_type'] ) ? sanitize_key( wp_unslash( $_POST['address_type'] ) ) : '';
		if ( ! $contact_id || ! in_array( $type, array( 'contact', 'billing', 'shipping' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid address selection.', 'contacthub' ) ), 400 );
		}

		$contact = self::contact( $contact_id );
		if ( ! $contact ) {
			wp_send_json_error( array( 'message' => __( 'The person could not be loaded from the selected data source.', 'contacthub' ) ), 404 );
		}
		$user = $contact ? self::contact_user( $contact ) : false;
		if ( 'contact' !== $type && ! class_exists( 'WC_Customer' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is unavailable.', 'contacthub' ) ), 503 );
		}
		if ( 'contact' !== $type && ! $user ) {
			wp_send_json_error( array( 'message' => __( 'This person does not have a linked WooCommerce customer account.', 'contacthub' ) ), 400 );
		}

		$fields = 'contact' === $type
			? array( 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' )
			: array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' );
		if ( 'billing' === $type ) {
			$fields[] = 'email';
			$fields[] = 'phone';
		}
		$values = array();
		foreach ( $fields as $field ) {
			$values[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		$values['country'] = strtoupper( $values['country'] );
		$countries = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : self::contact_countries();
		if ( $values['country'] && ! isset( $countries[ $values['country'] ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a valid country.', 'contacthub' ) ), 400 );
		}
		if ( 'billing' === $type && $values['email'] && ! is_email( $values['email'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid billing email address.', 'contacthub' ) ), 400 );
		}
		if ( 'billing' === $type ) {
			$values['phone'] = self::normalize_phone( $values['phone'] );
			if ( $values['phone'] && ! self::phone_country_code( $values['phone'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Please select a European telephone calling code.', 'contacthub' ) ), 400 );
			}
		}

		if ( 'contact' === $type ) {
			$groundhogg_meta = array(
				'street_address_1' => $values['address_1'], 'street_address_2' => $values['address_2'],
				'postal_zip' => $values['postcode'], 'city' => $values['city'],
				'region' => $values['state'], 'country' => $values['country'],
			);
			$input = array( 'id' => $contact_id, 'meta' => $groundhogg_meta );
			$result = ContactHub_Store::standalone()
				? ContactHub_Store::update_contact_address( $contact_id, $values )
				: self::execute_ability( 'groundhogg/update-contact', $input );
			if ( false === $result ) {
				wp_send_json_error( array( 'message' => __( 'The contact address could not be saved.', 'contacthub' ) ), 500 );
			}

			$sync = isset( $_POST['sync_to_woocommerce'] ) && '1' === (string) $_POST['sync_to_woocommerce'];
			if ( $sync ) {
				if ( ! $user || ! class_exists( 'WC_Customer' ) ) {
					wp_send_json_error( array( 'message' => __( 'The contact address was saved, but no linked WooCommerce customer account is available for copying.', 'contacthub' ) ), 409 );
				}
				try {
					$customer = new WC_Customer( $user->ID );
					foreach ( $values as $field => $value ) {
						$setter = 'set_billing_' . $field;
						if ( method_exists( $customer, $setter ) ) {
							$customer->{$setter}( $value );
						}
					}
					$customer->save();
				} catch ( Throwable $e ) {
					wp_send_json_error( array( 'message' => __( 'The contact address was saved, but WooCommerce could not copy it to the billing address.', 'contacthub' ) ), 500 );
				}
			}

			$display = self::format_address( $values );
			self::log_contact_note( $contact_id, __( 'Contact address', 'contacthub' ), sprintf( __( 'Contact address updated%1$s. Edited by: %2$s.', 'contacthub' ), $sync ? __( ' and copied to the WooCommerce billing address', 'contacthub' ) : '', wp_get_current_user()->display_name ) );
			wp_send_json_success( array( 'message' => $sync ? __( 'The contact address was saved and copied to the WooCommerce billing address.', 'contacthub' ) : __( 'The contact address was saved.', 'contacthub' ), 'display' => $display ?: '—' ) );
		}

		try {
			$customer = new WC_Customer( $user->ID );
			foreach ( $values as $field => $value ) {
				$setter = 'set_' . $type . '_' . $field;
				if ( method_exists( $customer, $setter ) ) {
					$customer->{$setter}( $value );
				}
			}
			$customer->save();
			foreach ( $values as $field => $value ) {
				update_user_meta( $user->ID, $type . '_' . $field, $value );
			}
			clean_user_cache( $user->ID );
		} catch ( Throwable $e ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce could not save the address.', 'contacthub' ) ), 500 );
		}

		wp_send_json_success( array( 'message' => __( 'The WooCommerce ', 'contacthub' ) . ( 'billing' === $type ? __( 'Billing address', 'contacthub' ) : __( 'Shipping address', 'contacthub' ) ) . __( ' was saved.', 'contacthub' ) ) );
	}

	public static function validate_vat() {
		self::guard_ajax();
		$vat = isset( $_POST['value'] ) ? self::normalize_vat( wp_unslash( $_POST['value'] ) ) : '';
		if ( ! self::valid_vat_format( $vat ) ) {
			wp_send_json_error( array( 'message' => __( 'The VAT ID does not have a valid EU format.', 'contacthub' ) ), 400 );
		}

		$country = substr( $vat, 0, 2 );
		$number  = substr( $vat, 2 );
		$body    = '<?xml version="1.0" encoding="UTF-8"?>' .
			'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:ec.europa.eu:taxud:vies:services:checkVat:types">' .
			'<soapenv:Body><urn:checkVat><urn:countryCode>' . esc_html( $country ) . '</urn:countryCode><urn:vatNumber>' . esc_html( $number ) . '</urn:vatNumber></urn:checkVat></soapenv:Body></soapenv:Envelope>';
		$response = wp_remote_post(
			'https://ec.europa.eu/taxation_customs/vies/services/checkVatService',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'text/xml; charset=utf-8', 'SOAPAction' => '' ),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => __( 'The EU VIES service is currently unavailable. The VAT ID was not evaluated.', 'contacthub' ) ), 503 );
		}
		$xml = wp_remote_retrieve_body( $response );
		if ( preg_match( '/<(?:[A-Za-z0-9_-]+:)?valid>\s*(true|false)\s*<\/(?:[A-Za-z0-9_-]+:)?valid>/i', $xml, $match ) ) {
			if ( 'true' === strtolower( $match[1] ) ) {
				$user = self::vat_user_from_request();
				if ( $user ) {
					update_user_meta( $user->ID, 'contacthub_vat_verified_hash', hash( 'sha256', $vat ) );
					update_user_meta( $user->ID, 'contacthub_vat_verified_at', current_time( 'mysql' ) );
				}
				wp_send_json_success( array( 'message' => __( 'The VAT ID is valid according to EU VIES.', 'contacthub' ), 'status' => 'valid', 'verified' => true ) );
			}
			$user = self::vat_user_from_request();
			if ( $user ) {
				delete_user_meta( $user->ID, 'contacthub_vat_verified_hash' );
				delete_user_meta( $user->ID, 'contacthub_vat_verified_at' );
			}
			wp_send_json_error( array( 'message' => __( 'The VAT ID is not valid according to EU VIES.', 'contacthub' ), 'status' => 'invalid' ), 422 );
		}
		wp_send_json_error( array( 'message' => __( 'EU VIES did not return a usable response.', 'contacthub' ) ), 503 );
	}

	public static function render() {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'contacthub' ) );
		}
		if ( ! self::companies_available() ) {
			self::render_people();
			return;
		}

		$company_id = isset( $_GET['firma'] ) ? absint( $_GET['firma'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		echo '<div class="wrap contacthub-wrap">';
		if ( $company_id ) {
			self::render_company( $company_id );
		} else {
			self::render_index();
		}
		echo '</div>';
	}

	public static function render_people() {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'contacthub' ) );
		}

		$contact_id = isset( $_GET['person'] ) ? absint( $_GET['person'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		echo '<div class="wrap contacthub-wrap">';
		if ( $contact_id ) {
			self::render_person( $contact_id );
		} else {
			self::render_people_index();
		}
		echo '</div>';
	}

	private static function render_people_index() {
		$companies_enabled = self::companies_available();
		$events_enabled = self::events_available();
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search.
		$page = isset( $_GET['contacthub_page'] ) ? max( 1, absint( $_GET['contacthub_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$customer_filter = isset( $_GET['kunden'] ) ? sanitize_key( wp_unslash( $_GET['kunden'] ) ) : 'alle'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$allowed_filters = array( 'alle', 'besteller' );
		if ( $events_enabled ) {
			$allowed_filters[] = 'event-besucher';
		}
		if ( ! in_array( $customer_filter, $allowed_filters, true ) ) {
			$customer_filter = 'alle';
		}
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sorting.
		$order = isset( $_GET['order'] ) && 'desc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'desc' : 'asc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sorting.
		$segment_id = isset( $_GET['segment'] ) ? absint( $_GET['segment'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$advanced_filter = isset( $_GET['contacthub_filter'] ) ? substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) wp_unslash( $_GET['contacthub_filter'] ) ), 0, 12000 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed-in read-only filter state.
		$sortable = array( 'first_name', 'last_name', 'order_total' );
		if ( $companies_enabled ) {
			$sortable[] = 'company';
		}
		if ( $events_enabled ) {
			$sortable[] = 'event_count';
		}
		if ( ! in_array( $orderby, $sortable, true ) ) {
			$orderby = '';
		}
		$limit = 50;
		$company_map = $companies_enabled ? self::contact_company_map() : array();
		$needs_complete_result = $segment_id || $advanced_filter || in_array( $customer_filter, array( 'besteller', 'event-besucher' ), true ) || '' !== $orderby;
		$order_summaries = array();
		$event_summaries = array();

		if ( $needs_complete_result ) {
			$contacts = self::all_contacts( $search );
			if ( 'besteller' === $customer_filter || 'order_total' === $orderby ) {
				$order_summaries = self::contact_order_summaries( $contacts );
			}
			if ( 'event-besucher' === $customer_filter || 'event_count' === $orderby ) {
				$event_summaries = self::contact_event_summaries( $contacts );
			}
			if ( 'besteller' === $customer_filter ) {
				$contacts = array_values( array_filter( $contacts, static function ( $contact ) use ( $order_summaries ) {
					$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
					return isset( $order_summaries[ $id ] ) && $order_summaries[ $id ]['count'] > 0;
				} ) );
			}
			if ( 'event-besucher' === $customer_filter ) {
				$contacts = array_values( array_filter( $contacts, static function ( $contact ) use ( $event_summaries ) {
					$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
					return isset( $event_summaries[ $id ] ) && $event_summaries[ $id ]['visited'] > 0;
				} ) );
			}
			if ( $segment_id || $advanced_filter ) {
				$contacts = apply_filters( 'contacthub_people_contacts', $contacts, $segment_id, $advanced_filter );
			}
			if ( $orderby ) {
				usort( $contacts, static function ( $left, $right ) use ( $orderby, $order, $company_map, $order_summaries, $event_summaries ) {
					$comparison = self::compare_contacts( $left, $right, $orderby, $company_map, $order_summaries, $event_summaries );
					return 'desc' === $order ? -$comparison : $comparison;
				} );
			}
			$total = count( $contacts );
			$pages = max( 1, (int) ceil( $total / $limit ) );
			$page = min( $page, $pages );
			$contacts = array_slice( $contacts, ( $page - 1 ) * $limit, $limit );
		} else {
			$result = self::contact_search( $search, $limit, ( $page - 1 ) * $limit );
			$contacts = isset( $result['contacts'] ) && is_array( $result['contacts'] ) ? $result['contacts'] : array();
			$total = isset( $result['total_items'] ) ? absint( $result['total_items'] ) : count( $contacts );
		}
		if ( ! $order_summaries && $contacts ) {
			$order_summaries = self::contact_order_summaries( $contacts );
		}
		if ( $events_enabled && ! $event_summaries ) {
			$event_summaries = self::contact_event_summaries( $contacts );
		}
			$list_state = self::store_people_list_state( $contacts, array(
				's' => $search, 'contacthub_page' => $page, 'kunden' => $customer_filter,
				'orderby' => $orderby, 'order' => $order, 'segment' => $segment_id,
				'contacthub_filter' => $advanced_filter,
			) );

		$heading_text = ContactHub_Store::standalone()
			? __( 'ContactHub contacts, company relationships, orders, events, and marketing status in one view.', 'contacthub' )
			: ( $companies_enabled
			? __( 'Groundhogg contacts, company relationships, and marketing status in one view.', 'contacthub' )
			: __( 'Groundhogg contacts, orders, and marketing status in one view.', 'contacthub' ) );
		echo __( '<div class="contacthub-heading"><div><p class="contacthub-eyebrow">CRM</p><h1>People</h1><p>', 'contacthub' ) . esc_html( $heading_text ) . __( '</p></div><button type="button" class="button button-primary contacthub-heading-action contacthub-open-new-person">Add person</button></div>', 'contacthub' );
		echo __( '<section class="contacthub-card"><div class="contacthub-toolbar"><h2>People overview</h2><span class="contacthub-count">', 'contacthub' ) . esc_html( $total ) . __( ' entries</span></div>', 'contacthub' );
		echo '<form class="contacthub-search" method="get"><input type="hidden" name="page" value="' . esc_attr( self::people_page_slug() ) . '">';
		do_action( 'contacthub_people_filter_controls', $segment_id );
		if ( $advanced_filter ) {
			echo '<input type="hidden" name="contacthub_filter" value="' . esc_attr( $advanced_filter ) . '">';
		}
		if ( 'alle' !== $customer_filter ) {
			echo '<input type="hidden" name="kunden" value="' . esc_attr( $customer_filter ) . '">';
		}
		if ( $orderby ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $orderby ) . '"><input type="hidden" name="order" value="' . esc_attr( $order ) . '">';
		}
		echo __( '<label class="screen-reader-text" for="contacthub-person-search">Search people</label><input id="contacthub-person-search" type="search" name="s" value="', 'contacthub' ) . esc_attr( $search ) . __( '" placeholder="Search by name or email"><button class="button">Search</button>', 'contacthub' );
		echo '<span class="contacthub-filter-buttons">' . self::people_filter_link( __( 'All', 'contacthub' ), 'alle', $customer_filter, $search, $orderby, $order ) . self::people_filter_link( __( 'Customers', 'contacthub' ), 'besteller', $customer_filter, $search, $orderby, $order );
		if ( $events_enabled ) {
			echo self::people_filter_link( __( 'Event attendees', 'contacthub' ), 'event-besucher', $customer_filter, $search, $orderby, $order );
		}
		echo '</span></form>';
		self::render_trash_link( 'contacts' );
		do_action( 'contacthub_people_advanced_search', $advanced_filter, $total );
		if ( ! $contacts ) {
			echo '<div class="contacthub-empty"><span class="dashicons dashicons-admin-users"></span><h3>' . esc_html__( 'No people found', 'contacthub' ) . '</h3><p>' . esc_html__( 'The selected data source returned no contacts for this search.', 'contacthub' ) . '</p></div>';
			self::render_new_person_modal();
			echo '</section>';
			return;
		}

		echo '<div class="contacthub-bulk-toolbar"><label class="screen-reader-text" for="contacthub-bulk-action">' . esc_html__( 'Select bulk action', 'contacthub' ) . '</label><select id="contacthub-bulk-action"><option value="">' . esc_html__( 'Bulk edit', 'contacthub' ) . '</option><option value="marketable">' . esc_html__( 'Enable marketable status', 'contacthub' ) . '</option></select><button type="button" class="button contacthub-open-bulk" disabled>' . esc_html__( 'Apply', 'contacthub' ) . '</button><span class="contacthub-bulk-selection" aria-live="polite">' . esc_html__( 'No person selected', 'contacthub' ) . '</span><span class="contacthub-list-output-actions"><button type="button" class="button contacthub-open-export"><span class="dashicons dashicons-download" aria-hidden="true"></span> ' . esc_html__( 'Export', 'contacthub' ) . '</button><button type="button" class="button contacthub-open-print"><span class="dashicons dashicons-printer" aria-hidden="true"></span> ' . esc_html__( 'Print list', 'contacthub' ) . '</button></span></div>';
		echo '<table class="widefat striped contacthub-table"><thead><tr>';
		echo __( '<td class="check-column"><input type="checkbox" class="contacthub-select-all" aria-label="Select all people on this page"></td>', 'contacthub' );
		echo self::sortable_heading( __( 'First name', 'contacthub' ), 'first_name', $orderby, $order, $customer_filter, $search );
		echo self::sortable_heading( __( 'Last name', 'contacthub' ), 'last_name', $orderby, $order, $customer_filter, $search );
		echo __( '<th><span class="screen-reader-text">Actions</span></th>', 'contacthub' );
		echo __( '<th>Email</th><th>Phone</th>', 'contacthub' );
		if ( $companies_enabled ) {
			echo self::sortable_heading( __( 'Company', 'contacthub' ), 'company', $orderby, $order, $customer_filter, $search );
		}
		$order_heading = self::sortable_heading( __( 'Orders', 'contacthub' ), 'order_total', $orderby, $order, $customer_filter, $search );
		echo str_replace( '<th ', '<th colspan="2" ', $order_heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated by sortable_heading().
		if ( $events_enabled ) {
			echo self::sortable_heading( __( 'Events', 'contacthub' ), 'event_count', $orderby, $order, $customer_filter, $search );
		}
		echo __( '<th><span class="screen-reader-text">Email confirmation and marketing status</span></th></tr></thead><tbody>', 'contacthub' );
		foreach ( $contacts as $contact ) {
			$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
			$first_name = self::value( $contact, array( 'first_name' ) );
			$last_name = self::value( $contact, array( 'last_name' ) );
			$company = $companies_enabled && isset( $company_map[ $id ][0] ) ? $company_map[ $id ][0] : false;
			$status = self::optin_status( $contact );
			$order_summary = isset( $order_summaries[ $id ] ) ? $order_summaries[ $id ] : self::empty_order_summary();
			$event_summary = isset( $event_summaries[ $id ] ) ? $event_summaries[ $id ] : array( 'visited' => 0 );
				$edit_url = add_query_arg( array( 'page' => self::people_page_slug(), 'person' => $id, 'contacthub_list_state' => $list_state ), admin_url( 'admin.php' ) );
			$view_url = add_query_arg( 'ansicht', 'anzeigen', $edit_url );
			$display_name = trim( $first_name . ' ' . $last_name );
			echo '<tr class="contacthub-person-data-row"><th class="check-column" scope="row"><input type="checkbox" class="contacthub-person-select" value="' . esc_attr( $id ) . '" aria-label="' . esc_attr( ( $display_name ?: __( 'Unnamed person', 'contacthub' ) ) . __( ' select', 'contacthub' ) ) . '"></th><td class="contacthub-first-name"><strong>' . esc_html( $first_name ?: '—' ) . '</strong></td><td class="contacthub-last-name"><strong>' . esc_html( $last_name ?: '—' ) . '</strong></td>';
			echo '<td><div class="contacthub-person-actions"><a class="contacthub-person-row-action" href="' . esc_url( $edit_url ) . __( '" aria-label="Edit person" title="Edit person"><span class="dashicons dashicons-edit" aria-hidden="true"></span></a><a class="contacthub-person-row-action" href="', 'contacthub' ) . esc_url( $view_url ) . __( '" aria-label="View person" title="View person"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></a>', 'contacthub' );
			if ( ContactHub_Trash::available() ) {
				echo self::trash_button_html( 'contacts', $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes all dynamic values.
			}
			echo '</div></td>';
			$email = self::value( $contact, array( 'email' ) );
			echo '<td><div class="contacthub-list-email"><span>' . esc_html( $email ?: '—' ) . '</span>';
			if ( is_email( $email ) ) {
				echo '<a class="contacthub-mail-link" href="mailto:' . esc_attr( $email ) . '" aria-label="' . esc_attr__( 'Send email', 'contacthub' ) . '" title="' . esc_attr__( 'Send email', 'contacthub' ) . '"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span></a>';
			}
			echo '</div></td>';
			echo '<td>' . esc_html( self::format_phone( self::meta( $contact, array( 'primary_phone' ) ) ) ) . '</td>';
			if ( $companies_enabled ) {
				$company_link = '—';
				if ( $company ) {
					$company_id   = absint( self::value( $company, array( 'id' ) ) );
					$company_name = self::value( $company, array( 'name' ) );
					$company_link = '<a href="' . esc_url( admin_url( 'admin.php?page=contacthub-firmen&firma=' . $company_id ) ) . '">' . esc_html( $company_name ) . '</a>';
				}
				echo '<td>' . $company_link . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped values.
			}
			echo '<td class="contacthub-order-count">' . esc_html( $order_summary['count'] ) . '</td>';
			echo '<td class="contacthub-order-total">' . wp_kses_post( $order_summary['formatted_total'] ) . '</td>';
			if ( $events_enabled ) {
				echo '<td class="contacthub-event-count">' . esc_html( $event_summary['visited'] ) . '</td>';
			}
			echo '<td><div class="contacthub-person-status-actions">' . self::email_confirmation_icon( $status, $contact ) . self::marketing_icon_badge( $status ) . '</div></td></tr>';
		}
		echo '</tbody></table>';
		self::render_bulk_marketing_modal();
		self::render_contact_output_modal( $total, $search, $customer_filter, $segment_id, $advanced_filter, $orderby, $order );
		self::render_new_person_modal();
		self::pagination( $page, $limit, $total, $search, array( 'kunden' => $customer_filter, 'orderby' => $orderby, 'order' => $order, 'segment' => $segment_id, 'contacthub_filter' => $advanced_filter ) );
		echo '</section>';
	}

		private static function store_people_list_state( $contacts, $args ) {
			$user_id = get_current_user_id();
			if ( ! $user_id ) return '';
			$token = strtolower( wp_generate_password( 12, false, false ) );
			$ids = array_values( array_filter( array_map( static function ( $contact ) {
				return absint( self::value( $contact, array( 'id', 'ID' ) ) );
			}, (array) $contacts ) ) );
			$return_args = array( 'page' => self::people_page_slug() );
			foreach ( (array) $args as $key => $value ) {
				if ( '' !== (string) $value && 0 !== $value && 'alle' !== $value && 'asc' !== $value ) $return_args[ sanitize_key( $key ) ] = $value;
			}
			set_transient( 'contacthub_people_state_' . $user_id . '_' . $token, array( 'ids' => $ids, 'return_url' => add_query_arg( $return_args, admin_url( 'admin.php' ) ) ), 2 * HOUR_IN_SECONDS );
			return $token;
		}

		private static function people_detail_navigation( $contact_id ) {
			$token = isset( $_GET['contacthub_list_state'] ) ? substr( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) wp_unslash( $_GET['contacthub_list_state'] ) ) ), 0, 24 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state.
			$state = $token ? get_transient( 'contacthub_people_state_' . get_current_user_id() . '_' . $token ) : false;
			$result = array( 'token' => $token, 'back' => admin_url( 'admin.php?page=' . self::people_page_slug() ), 'previous' => '', 'next' => '' );
			if ( ! is_array( $state ) ) return $result;
			$result['back'] = ! empty( $state['return_url'] ) ? $state['return_url'] : $result['back'];
			$ids = array_values( array_map( 'absint', (array) ( $state['ids'] ?? array() ) ) );
			$position = array_search( absint( $contact_id ), $ids, true );
			if ( false === $position ) return $result;
			$base = array( 'page' => self::people_page_slug(), 'contacthub_list_state' => $token );
			if ( $position > 0 ) $result['previous'] = add_query_arg( array_merge( $base, array( 'person' => $ids[ $position - 1 ] ) ), admin_url( 'admin.php' ) );
			if ( $position + 1 < count( $ids ) ) $result['next'] = add_query_arg( array_merge( $base, array( 'person' => $ids[ $position + 1 ] ) ), admin_url( 'admin.php' ) );
			return $result;
		}

	private static function render_person( $id ) {
		$contact = self::contact( $id );
		if ( ! $contact ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'The person could not be loaded from the selected data source.', 'contacthub' ) . '</p></div>';
			return;
		}

		$readonly = isset( $_GET['ansicht'] ) && 'anzeigen' === sanitize_key( wp_unslash( $_GET['ansicht'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$first_name = self::value( $contact, array( 'first_name', 'get_first_name' ) );
		$last_name = self::value( $contact, array( 'last_name', 'get_last_name' ) );
		$name = trim( $first_name . ' ' . $last_name );
		$email = self::value( $contact, array( 'email', 'get_email' ) );
		$orders = self::orders( array( $contact ) );
		$events_enabled = self::events_available();
		$events = $events_enabled ? self::contact_events( $contact ) : array();
		$broadcasts = self::contact_broadcasts( $id );
		$companies_enabled = self::companies_available();
		$company_map = $companies_enabled ? self::contact_company_map() : array();
		$companies = isset( $company_map[ $id ] ) ? $company_map[ $id ] : array();
		$company_names = array_values( array_filter( array_map( static function ( $company ) { return self::value( $company, array( 'name', 'company_name', 'get_name' ) ); }, $companies ) ) );
		$company_function = self::meta( $contact, array( 'company_function', 'funktion', 'role_at_company', 'job_function' ) );
		$user = self::contact_user( $contact );
		$customer = false;
		if ( $user && class_exists( 'WC_Customer' ) ) {
			try {
				$customer = new WC_Customer( $user->ID );
			} catch ( Throwable $e ) {
				$customer = false;
			}
		}
		$uid = $user ? (string) get_user_meta( $user->ID, 'billing_vat', true ) : '';
		if ( '' === $uid && $user ) {
			$uid = (string) get_user_meta( $user->ID, 'b2b_uid', true );
		}
		$uid_verified = $user && '' !== $uid && hash_equals( (string) get_user_meta( $user->ID, 'contacthub_vat_verified_hash', true ), hash( 'sha256', self::normalize_vat( $uid ) ) );

			$navigation = self::people_detail_navigation( $id );
			echo '<div class="contacthub-detail-navigation"><a class="contacthub-back" href="' . esc_url( $navigation['back'] ) . '">' . esc_html__( '← People overview', 'contacthub' ) . '</a><span class="contacthub-detail-navigation-buttons">';
			if ( $navigation['previous'] ) echo '<a class="button" href="' . esc_url( $navigation['previous'] ) . '">' . esc_html__( '← Previous person', 'contacthub' ) . '</a>';
			if ( $navigation['next'] ) echo '<a class="button" href="' . esc_url( $navigation['next'] ) . '">' . esc_html__( 'Next person →', 'contacthub' ) . '</a>';
			echo '</span></div>';
		echo '<header class="contacthub-heading contacthub-company-heading"><div class="contacthub-avatar">' . esc_html( strtoupper( substr( $first_name ?: $last_name ?: 'P', 0, 1 ) ) ) . '</div><div><p class="contacthub-eyebrow">' . ( $readonly ? esc_html__( 'View person', 'contacthub' ) : esc_html__( 'Edit person', 'contacthub' ) ) . '</p><h1>' . esc_html( $name ?: sprintf( __( 'Person #%d', 'contacthub' ), $id ) ) . '</h1><p>' . esc_html( sprintf( ContactHub_Store::standalone() ? __( 'ContactHub ID %d', 'contacthub' ) : __( 'Groundhogg ID %d', 'contacthub' ), $id ) ) . '</p></div>';
		$primary_address = self::person_primary_address( $customer, $contact );
		if ( $readonly ) {
				$edit_args = array( 'page' => self::people_page_slug(), 'person' => $id );
				if ( $navigation['token'] ) $edit_args['contacthub_list_state'] = $navigation['token'];
				echo '<a class="button button-primary contacthub-heading-action" href="' . esc_url( add_query_arg( $edit_args, admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Edit', 'contacthub' ) . '</a>';
		}
		echo '</header>';
		echo '<div class="contacthub-grid"><aside>';
		echo __( '<section class="contacthub-card contacthub-master-data"><h2>Master data</h2>', 'contacthub' );
		if ( $readonly ) {
			self::field( __( 'First name', 'contacthub' ), $first_name );
			self::field( __( 'Last name', 'contacthub' ), $last_name );
			echo '<div class="contacthub-master-pair">';
			self::field( __( 'Prefix title', 'contacthub' ), self::meta( $contact, array( 'titel_vorangestellt' ) ) );
			self::field( __( 'Suffix title', 'contacthub' ), self::meta( $contact, array( 'titel_nachgestellt' ) ) );
			echo '</div>';
			self::field( __( 'Salutation', 'contacthub' ), self::salutation_label( self::meta( $contact, array( 'anrede', 'salutation' ) ) ) );
			self::contact_email_field( __( 'Email address', 'contacthub' ), $email );
			self::field( __( 'Phone', 'contacthub' ), self::format_phone( self::meta( $contact, array( 'primary_phone' ) ) ) );
			self::field( __( 'Address', 'contacthub' ), $primary_address );
			self::field( __( 'Position / Job title', 'contacthub' ), self::meta( $contact, array( 'berufsbezeichnung', 'job_title', 'position' ) ) );
			self::field( __( 'Company', 'contacthub' ), implode( ', ', $company_names ) );
			if ( $company_function ) self::field( __( 'Function in company', 'contacthub' ), $company_function );
			if ( $user ) self::readonly_uid_field( __( 'VAT ID', 'contacthub' ), $uid, $uid_verified );
		} else {
			self::contact_editable_field( __( 'First name', 'contacthub' ), $first_name, 'first_name', $id );
			self::contact_editable_field( __( 'Last name', 'contacthub' ), $last_name, 'last_name', $id );
			echo '<div class="contacthub-master-pair">';
			self::contact_editable_field( __( 'Prefix title', 'contacthub' ), self::meta( $contact, array( 'titel_vorangestellt' ) ), 'titel_vorangestellt', $id );
			self::contact_editable_field( __( 'Suffix title', 'contacthub' ), self::meta( $contact, array( 'titel_nachgestellt' ) ), 'titel_nachgestellt', $id );
			echo '</div>';
			self::salutation_field( self::meta( $contact, array( 'anrede', 'salutation' ) ), $first_name, $id );
			self::contact_editable_field( __( 'Email address', 'contacthub' ), $email, 'email', $id );
			self::phone_field( __( 'Phone', 'contacthub' ), self::meta( $contact, array( 'primary_phone' ) ), 'primary_phone', $id, 'contact' );
			self::editable_address_summary( __( 'Address', 'contacthub' ), $primary_address );
			self::contact_editable_field( __( 'Position / Job title', 'contacthub' ), self::meta( $contact, array( 'berufsbezeichnung', 'job_title', 'position' ) ), 'berufsbezeichnung', $id );
			self::contact_editable_field( __( 'Company', 'contacthub' ), implode( ', ', $company_names ), 'company', $id );
			if ( $company_function ) self::field( __( 'Function in company', 'contacthub' ), $company_function );
			if ( $user ) self::uid_field( __( 'VAT ID', 'contacthub' ), $uid, $id, 'contact', $uid_verified );
		}
		if ( current_user_can( 'manage_options' ) ) {
			self::render_user_account_controls( $id, $contact, $user, $readonly );
		}
		echo '</section>';

		self::render_contact_tags( $contact, ! $readonly );
		self::render_marketing( $contact, ! $readonly );
		echo '</aside><main>';

		self::render_person_tabs( $id, $customer, $contact, $orders, $events, $events_enabled, $broadcasts, ! $readonly );
		echo '</main></div>';
	}

	private static function companies_available() {
		if ( ContactHub_Store::standalone() ) {
			return true;
		}
		if ( defined( 'GROUNDHOGG_COMPANIES_VERSION' ) || class_exists( '\\GroundhoggCompanies\\Company' ) ) {
			return true;
		}
		if ( function_exists( 'wp_get_ability' ) ) {
			return (bool) wp_get_ability( 'groundhogg-companies/list-companies' );
		}
		return false;
	}

	private static function people_page_slug() {
		return 'contacthub';
	}

	private static function events_available() {
		return (bool) apply_filters( 'contacthub_events_available', false );
	}

	private static function dependencies() {
		if ( ContactHub_Store::standalone() ) {
			return array( 'ContactHub database' => ContactHub_Store::ready() ? ContactHub_Schema::VERSION : false );
		}
		return array(
			'Groundhogg'           => defined( 'GROUNDHOGG_VERSION' ) ? GROUNDHOGG_VERSION : false,
			'Groundhogg Companies' => self::companies_available(),
		);
	}

	private static function render_trash_link( $entity, $id = 0, $cell = false ) {
		if ( ! ContactHub_Trash::available() ) return;
		$label = $id ? __( 'Move to Trash', 'contacthub' ) : __( 'Trash and bulk actions', 'contacthub' );
		if ( $cell ) {
			echo '<td class="contacthub-trash-column"><a class="button contacthub-trash-row-action" href="' . esc_url( ContactHub_Trash::url( $entity, $id, false ) ) . '" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $label ) . '</span></a></td>';
			return;
		}
		echo '<p><a class="button" href="' . esc_url( ContactHub_Trash::url( $entity, 0, true ) ) . '">' . esc_html( $label ) . '</a></p>';
	}

	private static function trash_button_html( $entity, $id ) {
		$label = __( 'Move to Trash', 'contacthub' );
		return '<a class="button contacthub-trash-row-action" href="' . esc_url( ContactHub_Trash::url( $entity, $id, false ) ) . '" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $label ) . '</span></a>';
	}

	private static function render_index() {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search.
		echo __( '<div class="contacthub-heading"><div><p class="contacthub-eyebrow">CRM</p><h1>Companies</h1><p>A unified view of companies, contacts, orders, and invoices.</p></div><button type="button" class="button button-primary contacthub-heading-action contacthub-open-new-company">Add company</button></div>', 'contacthub' );

		$missing = array_filter( self::dependencies(), static function ( $value ) { return false === $value; } );
		if ( $missing ) {
			echo __( '<div class="notice notice-warning inline"><p><strong>Diagnostics:</strong> Not all required extensions were detected: ', 'contacthub' ) . esc_html( implode( ', ', array_keys( $missing ) ) ) . '.</p></div>';
		}

		$companies = self::companies();
		if ( '' !== $search ) {
			$companies = array_values( array_filter( $companies, static function ( $company ) use ( $search ) {
				$name = self::value( $company, array( 'get_name', 'name', 'company_name' ) );
				return false !== stripos( $name, $search );
			} ) );
		}
		echo __( '<section class="contacthub-card"><div class="contacthub-toolbar"><h2>Companies overview</h2><span class="contacthub-count">', 'contacthub' ) . esc_html( count( $companies ) ) . __( ' entries</span></div>', 'contacthub' );
		echo __( '<form class="contacthub-search" method="get"><input type="hidden" name="page" value="contacthub-firmen"><label class="screen-reader-text" for="contacthub-company-search">Search companies</label><input id="contacthub-company-search" type="search" name="s" value="', 'contacthub' ) . esc_attr( $search ) . __( '" placeholder="Search company name"><button class="button">Search</button>', 'contacthub' );
		if ( '' !== $search ) {
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=contacthub-firmen' ) ) . __( '">Show all</a>', 'contacthub' );
		}
		echo '</form>';
		self::render_trash_link( 'companies' );
		if ( ! $companies ) {
			$empty_message = $search
				? __( 'No result was found for this company name.', 'contacthub' )
				: ( ContactHub_Store::standalone() ? __( 'No companies have been created in ContactHub yet.', 'contacthub' ) : __( 'Groundhogg Companies returned no companies.', 'contacthub' ) );
			echo __( '<div class="contacthub-empty"><span class="dashicons dashicons-building"></span><h3>No companies found</h3><p>', 'contacthub' ) . esc_html( $empty_message ) . '</p></div></section>';
			self::render_new_company_modal();
			self::render_status();
			return;
		}

		echo '<table class="widefat striped contacthub-table contacthub-companies-table"><thead><tr><th>' . esc_html__( 'Company', 'contacthub' ) . '</th>';
		echo '<th>' . esc_html__( 'Assigned people', 'contacthub' ) . '</th><th>' . esc_html__( 'Website', 'contacthub' ) . '</th><th>' . esc_html__( 'Phone', 'contacthub' ) . '</th><th>' . esc_html__( 'Location', 'contacthub' ) . '</th></tr></thead><tbody>';
		foreach ( $companies as $company ) {
			$id = self::value( $company, array( 'get_id', 'ID', 'id' ) );
			$edit_url = admin_url( 'admin.php?page=contacthub-firmen&firma=' . absint( $id ) );
			$view_url = add_query_arg( 'ansicht', 'anzeigen', $edit_url );
			$person_links = array();
			foreach ( self::contacts( $company ) as $contact ) {
				if ( is_scalar( $contact ) ) {
					$contact = self::contact( absint( $contact ) );
				}
				if ( ! $contact ) {
					continue;
				}
				$contact_id = absint( self::value( $contact, array( 'id', 'ID', 'get_id' ) ) );
				$name = trim( self::value( $contact, array( 'first_name', 'get_first_name' ) ) . ' ' . self::value( $contact, array( 'last_name', 'get_last_name' ) ) );
				if ( '' === $name ) {
					$name = self::value( $contact, array( 'email', 'get_email' ), $contact_id ? sprintf( __( 'Person #%d', 'contacthub' ), $contact_id ) : __( 'Unnamed person', 'contacthub' ) );
				}
				$person_links[] = $contact_id
					? '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::people_page_slug() . '&person=' . $contact_id ) ) . '">' . esc_html( $name ) . '</a>'
					: esc_html( $name );
			}
			echo '<tr><td><div class="contacthub-company-name-cell"><strong>' . esc_html( self::value( $company, array( 'get_name', 'name', 'company_name' ), __( 'Unnamed company', 'contacthub' ) ) ) . '</strong><div class="contacthub-person-actions contacthub-company-actions"><a class="contacthub-person-row-action" href="' . esc_url( $edit_url ) . '" aria-label="' . esc_attr__( 'Edit company', 'contacthub' ) . '" title="' . esc_attr__( 'Edit company', 'contacthub' ) . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span></a><a class="contacthub-person-row-action" href="' . esc_url( $view_url ) . '" aria-label="' . esc_attr__( 'View company', 'contacthub' ) . '" title="' . esc_attr__( 'View company', 'contacthub' ) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></a>';
			if ( ContactHub_Trash::available() ) echo self::trash_button_html( 'companies', $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes all dynamic values.
			echo '</div></div></td>';
			echo '<td class="contacthub-company-people">' . ( $person_links ? wp_kses_post( implode( ', ', $person_links ) ) : '&mdash;' ) . '</td>';
			echo '<td>' . esc_html( self::value( $company, array( 'domain', 'website', 'get_website' ), '—' ) ) . '</td>';
			echo '<td>' . esc_html( self::format_phone( self::value( $company, array( 'phone', 'get_phone' ) ) ) ) . '</td>';
			echo '<td>' . esc_html( self::value( $company, array( 'city', 'get_city' ), '—' ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></section>';
		self::render_new_company_modal();
	}

	private static function create_company_record( $name ) {
		$name = sanitize_text_field( $name );
		if ( ContactHub_Store::standalone() ) {
			$existing = ContactHub_Store::company_by_name( $name );
			return $existing ?: ContactHub_Store::create_company( array( 'name' => $name ) );
		}
		$result = self::execute_ability( 'groundhogg-companies/create-company', array( 'name' => $name ) );
		$id = is_array( $result ) ? absint( self::value( $result, array( 'id', 'company_id', 'ID' ) ) ) : 0;
		if ( ! $id && is_array( $result ) && isset( $result['company'] ) ) {
			$id = absint( self::value( $result['company'], array( 'id', 'company_id', 'ID' ) ) );
		}
		return $id;
	}

	private static function assign_contact_company( $contact_id, $company_id, $company_name, $position ) {
		if ( ContactHub_Store::standalone() ) {
			$company_id = $company_id && ContactHub_Store::company( $company_id ) ? $company_id : ContactHub_Store::company_by_name( $company_name );
			if ( ! $company_id ) {
				$company_id = ContactHub_Store::create_company( array( 'name' => $company_name ) );
			}
			$linked = $company_id && ContactHub_Store::link_company_contact( $company_id, $contact_id, $position );
			if ( $linked && '' !== $position ) {
				ContactHub_Store::update_contact( $contact_id, array( 'position' => $position, 'berufsbezeichnung' => $position ) );
			}
			return $linked ? true : new WP_Error( 'contacthub_company_link', __( 'The person was created, but the company assignment could not be saved.', 'contacthub' ) );
		}
		if ( ! $company_id ) {
			foreach ( self::companies() as $company ) {
				if ( 0 === strcasecmp( trim( self::value( $company, array( 'name', 'company_name', 'get_name' ) ) ), trim( $company_name ) ) ) {
					$company_id = absint( self::value( $company, array( 'id', 'ID', 'get_id' ) ) );
					break;
				}
			}
		}
		if ( ! $company_id ) {
			$company_id = self::create_company_record( $company_name );
		}
		if ( ! $company_id ) {
			return new WP_Error( 'contacthub_company_create', __( 'The person was created, but the company could not be created.', 'contacthub' ) );
		}
		$result = self::execute_ability( 'groundhogg-companies/add-contact-to-company', array( 'company_id' => $company_id, 'contact_id' => $contact_id ) );
		if ( false !== $result && '' !== $position ) {
			self::execute_ability(
				'groundhogg/update-contact',
				array(
					'id'   => $contact_id,
					'meta' => array(
						'job_title'          => $position,
						'berufsbezeichnung' => $position,
					),
				)
			);
		}
		return false === $result ? new WP_Error( 'contacthub_company_link', __( 'The person was created, but the company assignment could not be saved.', 'contacthub' ) ) : true;
	}

	private static function companies() {
		if ( ! self::companies_available() ) {
			return array();
		}
		if ( ContactHub_Store::standalone() ) {
			return ContactHub_Store::companies();
		}
		$result = self::execute_ability(
			'groundhogg-companies/list-companies',
			array(
					'limit'  => 200,
				'offset' => 0,
				'expand' => array( 'meta', 'contacts' ),
			)
		);
		if ( is_array( $result ) && isset( $result['companies'] ) && is_array( $result['companies'] ) ) {
			return $result['companies'];
		}

		if ( ! function_exists( '\\Groundhogg\\get_db' ) ) {
			return array();
		}
		try {
			$db = \Groundhogg\get_db( 'companies' );
			if ( ! $db || ! method_exists( $db, 'query' ) ) {
				return array();
			}
			$rows = $db->query( array( 'limit' => 100, 'orderby' => 'name', 'order' => 'ASC' ) );
			return is_array( $rows ) ? $rows : array();
		} catch ( Throwable $e ) {
			return array();
		}
	}

	private static function company( $id ) {
		if ( ! self::companies_available() ) {
			return false;
		}
		if ( ContactHub_Store::standalone() ) {
			return ContactHub_Store::company( $id );
		}
		$result = self::execute_ability(
			'groundhogg-companies/get-company',
			array(
				'id'     => $id,
				'expand' => array( 'meta', 'contacts' ),
			)
		);
		if ( is_array( $result ) && ! empty( $result['id'] ) ) {
			return $result;
		}

		if ( function_exists( '\\Groundhogg\\get_db' ) ) {
			try {
				$db = \Groundhogg\get_db( 'companies' );
				if ( $db && method_exists( $db, 'get' ) ) {
					return $db->get( $id );
				}
			} catch ( Throwable $e ) {
				return false;
			}
		}
		return false;
	}

	private static function contact_search( $search = '', $limit = 50, $offset = 0 ) {
		if ( ContactHub_Store::standalone() ) {
			return ContactHub_Store::search_contacts( $search, $limit, $offset );
		}
		$input = array(
			'limit'  => $limit,
			'offset' => $offset,
			'expand' => array( 'tags', 'meta', 'work_details' ),
		);
		if ( '' !== $search ) {
			$input['search'] = $search;
		}
		$result = self::execute_ability( 'groundhogg/search-contacts', $input );
		return is_array( $result ) ? $result : array();
	}

	private static function all_contacts( $search = '' ) {
		// Groundhogg's ability response can report the current page size as its
		// total on some installations. The advanced search must never trust that
		// value, because doing so silently limits every filter to the first page.
		if ( ! ContactHub_Store::standalone() ) {
			$complete = self::groundhogg_all_contacts( $search );
			if ( is_array( $complete ) ) {
				return $complete;
			}
		}

		$contacts = array();
		$offset = 0;
		$batch_size = 100;
		$total = null;
		$stalled = false;

		do {
			$before = count( $contacts );
			$result = self::contact_search( $search, $batch_size, $offset );
			$batch = isset( $result['contacts'] ) && is_array( $result['contacts'] ) ? $result['contacts'] : array();
			if ( null === $total && isset( $result['total_items'] ) ) {
				$total = absint( $result['total_items'] );
			}
			foreach ( $batch as $contact ) {
				$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
				if ( $id ) {
					$contacts[ $id ] = $contact;
				}
			}
			$received = count( $batch );
			$offset += $received;
			$added = count( $contacts ) - $before;
			if ( $received > 0 && 0 === $added ) {
				$stalled = true;
			}
		} while ( $received > 0 && $added > 0 && ( null === $total || $offset < $total ) );

		// Some older Groundhogg ability builds can repeat the first result page or
		// stop before their reported total. In that case, use Groundhogg's own
		// contact query to retrieve the complete read-only result set.
		if ( ! ContactHub_Store::standalone() && ( null === $total || $stalled || count( $contacts ) < $total ) ) {
			$complete = self::groundhogg_all_contacts( $search );
			if ( is_array( $complete ) ) {
				return $complete;
			}
		}

		return array_values( $contacts );
	}

	private static function groundhogg_all_contacts( $search = '' ) {
		// Reading the Groundhogg contact IDs directly is the most stable way to
		// avoid version-specific pagination differences in its query/ability APIs.
		// Contact objects remain Groundhogg-owned; ContactHub does not duplicate or
		// change any data here.
		$from_table = self::groundhogg_contacts_from_table( $search );
		if ( is_array( $from_table ) ) {
			return $from_table;
		}

		if ( ! class_exists( '\\Groundhogg\\Contact_Query' ) ) {
			return null;
		}

		$contacts = array();
		$offset = 0;
		$batch_size = 100;
		do {
			$args = array(
				'number'        => $batch_size,
				'offset'        => $offset,
				'orderby'       => 'ID',
				'order'         => 'ASC',
				'no_found_rows' => false,
			);
			if ( '' !== $search ) {
				$args['search'] = $search;
			}
			try {
				$query = new \Groundhogg\Contact_Query();
				$batch = (array) $query->query( $args, true );
			} catch ( Throwable $e ) {
				return null;
			}
			foreach ( $batch as $contact ) {
				if ( class_exists( '\\Groundhogg\\Abilities\\Schemas\\Contact_Schema' ) ) {
					try {
						$contact = \Groundhogg\Abilities\Schemas\Contact_Schema::transform( $contact, array( 'tags', 'meta', 'work_details' ) );
					} catch ( Throwable $e ) {
						// The original Contact object still exposes all standard fields.
					}
				}
				$id = absint( self::value( $contact, array( 'id', 'ID', 'get_id' ) ) );
				if ( $id ) {
					$contacts[ $id ] = $contact;
				}
			}
			$received = count( $batch );
			$offset += $received;
		} while ( $received === $batch_size );

		return array_values( $contacts );
	}

	private static function groundhogg_contacts_from_table( $search = '' ) {
		if ( ! class_exists( '\\Groundhogg\\Contact' ) ) {
			return null;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'gh_contacts';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table !== $exists ) {
			return null;
		}

		$ids = $wpdb->get_col( "SELECT ID FROM `{$table}` ORDER BY ID ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed Groundhogg table name.
		$contacts = array();
		$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $search ) ) : strtolower( trim( (string) $search ) );
		foreach ( (array) $ids as $id ) {
			try {
				$contact = new \Groundhogg\Contact( absint( $id ) );
			} catch ( Throwable $e ) {
				continue;
			}
			if ( method_exists( $contact, 'exists' ) && ! $contact->exists() ) {
				continue;
			}
			if ( '' !== $needle ) {
				$haystack = implode( ' ', array(
					self::value( $contact, array( 'first_name', 'get_first_name' ) ),
					self::value( $contact, array( 'last_name', 'get_last_name' ) ),
					self::value( $contact, array( 'email', 'get_email' ) ),
				) );
				$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );
				if ( false === strpos( $haystack, $needle ) ) {
					continue;
				}
			}
			$contacts[ absint( $id ) ] = $contact;
		}
		return array_values( $contacts );
	}

	/** Public, read-only API for separately loadable ContactHub modules. */
	public static function extension_contacts( $search = '' ) {
		return self::all_contacts( sanitize_text_field( $search ) );
	}

	/** Public, read-only company relationships for separately loadable modules. */
	public static function extension_company_map() {
		return self::contact_company_map();
	}

	/** Public, read-only user-managed tags for separately loadable modules. */
	public static function extension_tags() {
		return self::all_tags();
	}

	public static function extension_contact( $id ) {
		return self::contact( absint( $id ) );
	}

	/**
	 * Find one contact by an exact email address in the active data source.
	 *
	 * @param string $email Email address.
	 * @return array|object|false
	 */
	public static function extension_contact_by_email( $email ) {
		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) {
			return false;
		}
		if ( ContactHub_Store::standalone() ) {
			return ContactHub_Store::contact_by_email( $email );
		}
		$result = self::contact_search( $email, 20, 0 );
		$contacts = isset( $result['contacts'] ) && is_array( $result['contacts'] ) ? $result['contacts'] : array();
		foreach ( $contacts as $contact ) {
			$contact_email = strtolower( sanitize_email( self::value( $contact, array( 'email', 'get_email' ) ) ) );
			if ( $contact_email && hash_equals( $email, $contact_email ) ) {
				return $contact;
			}
		}
		// Older Groundhogg releases expose different search semantics. Fall back
		// to the existing complete read API only when the targeted search misses.
		foreach ( self::all_contacts( $email ) as $contact ) {
			$contact_email = strtolower( sanitize_email( self::value( $contact, array( 'email', 'get_email' ) ) ) );
			if ( $contact_email && hash_equals( $email, $contact_email ) ) {
				return $contact;
			}
		}
		return false;
	}

	/**
	 * Store a successfully confirmed double opt-in in the active data source.
	 *
	 * @param string $email      Confirmed email address.
	 * @param string $first_name First name supplied with the request.
	 * @param string $last_name  Last name supplied with the request.
	 * @param string $source     Auditable request source.
	 * @param string $proof      Human-readable proof note.
	 * @return int|WP_Error Contact ID or error.
	 */
	public static function extension_confirm_marketing_optin( $email, $first_name = '', $last_name = '', $source = 'double_opt_in', $proof = '' ) {
		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'contacthub_optin_email', __( 'The newsletter subscription could not be saved.', 'contacthub' ) );
		}
		$first_name = sanitize_text_field( $first_name );
		$last_name = sanitize_text_field( $last_name );
		$source = sanitize_key( $source );
		$proof = sanitize_textarea_field( $proof );
		$date = current_time( 'Y-m-d' );
		$confirmed_at = current_time( 'mysql' );
		$contact = self::extension_contact_by_email( $email );
		if ( $contact && in_array( self::optin_status( $contact ), array( 6, 7, 8, 9, 'hard_bounce', 'spam', 'complained', 'blocked' ), true ) ) {
			return new WP_Error( 'contacthub_optin_protected', __( 'This email address cannot be subscribed because it has a protected delivery or complaint status.', 'contacthub' ) );
		}

		$meta = array(
			'email_confirmed'         => 1,
			'email_confirmed_at'      => $confirmed_at,
			'marketing_consent'       => 'yes',
			'marketing_consent_source' => $source,
			'marketing_consent_date'  => $date,
		);
		if ( ContactHub_Store::standalone() ) {
			$payload = array(
				'email'        => $email,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'source'       => $source,
				'optin_status' => 'confirmed',
				'meta'         => $meta,
			);
			$contact_id = $contact ? absint( self::value( $contact, array( 'id', 'ID' ) ) ) : ContactHub_Store::create_contact( $payload );
			if ( is_wp_error( $contact_id ) || ! $contact_id ) {
				return is_wp_error( $contact_id ) ? $contact_id : new WP_Error( 'contacthub_optin_save', __( 'The newsletter subscription could not be saved.', 'contacthub' ) );
			}
			if ( $contact && ! ContactHub_Store::update_contact( $contact_id, $payload ) ) {
				return new WP_Error( 'contacthub_optin_save', __( 'The newsletter subscription could not be saved.', 'contacthub' ) );
			}
			ContactHub_Store::record_consent( $contact_id, 'granted', $source, $date, $proof );
			self::update_wordpress_marketing_status( $email, $confirmed_at );
			return $contact_id;
		}

		$contact_id = $contact ? absint( self::value( $contact, array( 'id', 'ID', 'get_id' ) ) ) : 0;
		if ( ! $contact_id ) {
			$created = self::execute_ability(
				'groundhogg/create-contact',
				array(
					'email'        => $email,
					'first_name'   => $first_name,
					'last_name'    => $last_name,
					'optin_status' => 'confirmed',
					'meta'         => $meta,
					'expand'       => array( 'tags', 'meta' ),
				)
			);
			$contact_id = is_array( $created ) && isset( $created['contact']['id'] ) ? absint( $created['contact']['id'] ) : 0;
		} else {
			$updated = self::execute_ability(
				'groundhogg/update-contact',
				array(
					'id'           => $contact_id,
					'optin_status' => 'confirmed',
					'meta'         => $meta,
				)
			);
			if ( false === $updated ) {
				return new WP_Error( 'contacthub_optin_save', __( 'The newsletter subscription could not be saved.', 'contacthub' ) );
			}
		}
		if ( ! $contact_id ) {
			return new WP_Error( 'contacthub_optin_save', __( 'The newsletter subscription could not be saved.', 'contacthub' ) );
		}
		self::execute_ability(
			'groundhogg/add-contact-note',
			array(
				'contact_id' => $contact_id,
				'summary'    => __( 'Marketing consent confirmed by double opt-in', 'contacthub' ),
				'content'    => $proof,
			)
		);
		self::update_wordpress_marketing_status( $email, $confirmed_at );
		return $contact_id;
	}

	private static function update_wordpress_marketing_status( $email, $confirmed_at ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return;
		}
		update_user_meta( $user->ID, 'contacthub_marketing_status', 'confirmed' );
		update_user_meta( $user->ID, 'contacthub_marketing_confirmed_at', $confirmed_at );
	}

	public static function extension_value( $contact, $keys, $default = '' ) {
		return self::value( $contact, (array) $keys, $default );
	}

	public static function ordered_countries( $countries ) {
		$countries = is_array( $countries ) ? $countries : array();
		$preferred = array();
		foreach ( array( 'AT', 'DE', 'CH' ) as $code ) if ( isset( $countries[ $code ] ) ) { $preferred[ $code ] = $countries[ $code ]; unset( $countries[ $code ] ); }
		asort( $countries, SORT_NATURAL | SORT_FLAG_CASE );
		return $countries ? $preferred + array( '__separator__' => '──────────' ) + $countries : $preferred;
	}

	public static function extension_optin_status( $contact ) {
		return self::optin_status( $contact );
	}

	public static function extension_is_marketable( $contact ) {
		return self::is_marketable_status( self::optin_status( $contact ) );
	}

	public static function extension_unsubscribe_contact( $contact_id, $source = 'newsletter', $note = '' ) {
		$contact_id = absint( $contact_id );
		if ( ! $contact_id ) {
			return false;
		}
		$source = sanitize_text_field( $source );
		$note = sanitize_textarea_field( $note );
		$date = current_time( 'Y-m-d' );
		if ( ContactHub_Store::standalone() ) {
			ContactHub_Store::update_contact( $contact_id, array( 'optin_status' => 'unsubscribed', 'meta' => array( 'marketing_consent' => 'no', 'marketing_consent_revoked_date' => $date ) ) );
			ContactHub_Store::record_consent( $contact_id, 'revoked', $source, $date, $note );
			return true;
		}
		$result = self::execute_ability( 'groundhogg/update-contact', array( 'id' => $contact_id, 'optin_status' => 'unsubscribed', 'meta' => array( 'marketing_consent' => 'no', 'marketing_consent_revoked_date' => $date ) ) );
		self::execute_ability( 'groundhogg/add-contact-note', array( 'contact_id' => $contact_id, 'summary' => __( 'Newsletter unsubscribed', 'contacthub' ), 'content' => $note ) );
		return false !== $result;
	}

	private static function contact( $id ) {
		if ( ContactHub_Store::standalone() ) {
			return ContactHub_Store::contact( $id );
		}
		$result = self::execute_ability(
			'groundhogg/get-contact',
			array( 'id' => $id, 'expand' => array( 'tags', 'meta', 'work_details' ) )
		);
		return is_array( $result ) && ! empty( $result['id'] ) ? $result : false;
	}

	private static function contact_company_map() {
		if ( ! self::companies_available() ) {
			return array();
		}
		if ( ContactHub_Store::standalone() ) {
			return ContactHub_Store::company_map();
		}
		static $map = null;
		if ( null !== $map ) {
			return $map;
		}
		$map = array();
		foreach ( self::companies() as $company ) {
			foreach ( self::contacts( $company ) as $contact ) {
				$contact_id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
				if ( $contact_id ) {
					$map[ $contact_id ][] = $company;
				}
			}
		}
		return $map;
	}

	private static function contact_user( $contact ) {
		$user_id = absint( self::value( $contact, array( 'user_id' ) ) );
		if ( $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				return $user;
			}
		}
		$email = self::value( $contact, array( 'email', 'get_email' ) );
		return $email ? get_user_by( 'email', $email ) : false;
	}

	private static function optin_status( $contact ) {
		$status = is_array( $contact ) && isset( $contact['optin_status'] ) ? $contact['optin_status'] : self::value( $contact, array( 'optin_status' ) );
		if ( is_array( $status ) ) {
			return isset( $status['value'] ) ? (int) $status['value'] : '';
		}
		return is_numeric( $status ) ? (int) $status : sanitize_key( (string) $status );
	}

	private static function status_badge( $status ) {
		$labels = array(
			1 => __( 'Unconfirmed', 'contacthub' ), 2 => __( 'Marketing allowed', 'contacthub' ), 3 => __( 'Unsubscribed', 'contacthub' ),
			4 => __( 'Weekly', 'contacthub' ), 5 => __( 'Monthly', 'contacthub' ), 6 => __( 'Undeliverable', 'contacthub' ),
			7 => __( 'Spam', 'contacthub' ), 8 => __( 'Complaint', 'contacthub' ), 9 => __( 'Blocked', 'contacthub' ),
			'unconfirmed' => __( 'Unconfirmed', 'contacthub' ), 'confirmed' => __( 'Marketing allowed', 'contacthub' ),
			'unsubscribed' => __( 'Unsubscribed', 'contacthub' ), 'weekly' => __( 'Weekly', 'contacthub' ),
			'monthly' => __( 'Monthly', 'contacthub' ), 'hard_bounce' => __( 'Undeliverable', 'contacthub' ),
			'spam' => __( 'Spam', 'contacthub' ), 'complained' => __( 'Complaint', 'contacthub' ), 'blocked' => __( 'Blocked', 'contacthub' ),
		);
		$positive = in_array( $status, array( 2, 4, 5, 'confirmed', 'weekly', 'monthly' ), true );
		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Unknown', 'contacthub' );
		return '<span class="contacthub-status-badge ' . ( $positive ? 'is-positive' : 'is-negative' ) . '">' . esc_html( $label ) . '</span>';
	}

	private static function marketing_icon_badge( $status ) {
		$positive = self::is_marketable_status( $status );
		$protected = self::is_protected_optout_status( $status );
		$label = self::status_label( $status );
		$icon = $positive ? 'dashicons-thumbs-up' : ( $protected ? 'dashicons-dismiss' : 'dashicons-thumbs-down' );
		return '<span class="contacthub-marketing-icon ' . ( $positive ? 'is-positive' : 'is-negative' ) . ( $protected ? ' is-protected' : '' ) . '" role="img" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '"><span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span></span>';
	}

	private static function email_confirmation_icon( $status, $contact = array() ) {
		$confirmed = ContactHub_Store::standalone() ? (bool) self::meta( $contact, array( 'email_confirmed' ) ) : in_array( $status, array( 2, 4, 5, 'confirmed', 'weekly', 'monthly' ), true );
		$label = $confirmed ? __( 'Email address confirmed', 'contacthub' ) : __( 'Email address not confirmed', 'contacthub' );
		$mark = $confirmed
			? '<span class="dashicons dashicons-yes" aria-hidden="true"></span>'
			: '<span class="contacthub-email-confirmation-mark" aria-hidden="true">—</span>';
		return '<span class="contacthub-email-confirmation-icon" role="img" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '">' . $mark . '</span>';
	}

	private static function is_marketable_status( $status ) {
		return in_array( $status, array( 2, 4, 5, 'confirmed', 'weekly', 'monthly' ), true );
	}

	private static function is_protected_optout_status( $status ) {
		return in_array( $status, array( 3, 6, 7, 8, 9, 'unsubscribed', 'hard_bounce', 'spam', 'complained', 'blocked' ), true );
	}

	private static function marketing_consent_sources() {
		return array(
			'personally' => __( 'In person', 'contacthub' ),
			'phone'      => __( 'By phone', 'contacthub' ),
			'written'    => __( 'In writing', 'contacthub' ),
			'email'      => __( 'By email', 'contacthub' ),
			'import'     => __( 'Documented import', 'contacthub' ),
			'other'      => __( 'Other', 'contacthub' ),
		);
	}

	private static function all_tags() {
		if ( ContactHub_Store::standalone() ) {
			return ContactHub_Store::tags();
		}
		$rows = array();
		$offset = 0;
		$limit = 100;
		$seen_pages = array();
		do {
			$result = self::execute_ability( 'groundhogg/list-tags', array( 'limit' => $limit, 'offset' => $offset ) );
			$page_rows = is_array( $result ) && isset( $result['tags'] ) && is_array( $result['tags'] ) ? $result['tags'] : array();
			$fingerprint = md5( wp_json_encode( array_map( static function ( $tag ) { return self::value( $tag, array( 'id', 'tag_id', 'ID', 'get_id' ) ); }, $page_rows ) ) );
			if ( $page_rows && isset( $seen_pages[ $fingerprint ] ) ) break;
			$seen_pages[ $fingerprint ] = true;
			$rows = array_merge( $rows, $page_rows );
			$offset += count( $page_rows );
		} while ( $page_rows && $offset < 100000 );
		if ( ! $rows && function_exists( '\\Groundhogg\\get_db' ) ) {
			try {
				$rows = \Groundhogg\get_db( 'tags' )->query( array( 'limit' => 100000, 'orderby' => 'tag_name', 'order' => 'ASC' ) );
			} catch ( Throwable $e ) {
				$rows = array();
			}
		}
		$tags = array();
		foreach ( $rows as $row ) {
			$id = absint( self::value( $row, array( 'id', 'tag_id', 'ID', 'get_id' ) ) );
			$name = self::value( $row, array( 'name', 'tag_name', 'get_name' ) );
			if ( $id && $name ) {
				$tags[ $id ] = array(
					'id'   => $id,
					'name' => $name,
					'description' => self::tag_description( $id ),
				);
			}
		}
		$tags = array_values( $tags );
		usort( $tags, static function ( $left, $right ) { return strnatcasecmp( $left['name'], $right['name'] ); } );
		return self::user_managed_tags( $tags );
	}

	private static function tag_description( $tag_id ) {
		$descriptions = (array) get_option( 'contacthub_tag_descriptions', array() );
		return isset( $descriptions[ absint( $tag_id ) ] ) ? sanitize_textarea_field( $descriptions[ absint( $tag_id ) ] ) : '';
	}

	private static function set_tag_description( $tag_id, $description ) {
		$descriptions = (array) get_option( 'contacthub_tag_descriptions', array() );
		$descriptions[ absint( $tag_id ) ] = sanitize_textarea_field( $description );
		update_option( 'contacthub_tag_descriptions', $descriptions, false );
	}

	private static function user_managed_tags( $tags ) {
		$status_tag_options = array(
			'gh_confirmed_tag', 'gh_unconfirmed_tag', 'gh_unsubscribed_tag', 'gh_spammed_tag',
			'gh_bounced_tag', 'gh_complained_tag', 'gh_weekly_tag', 'gh_monthly_tag',
			'gh_marketable_tag', 'gh_non_marketable_tag',
		);
		$system_tag_ids = array_values( array_filter( array_map( 'absint', array_map( 'get_option', $status_tag_options ) ) ) );
		$role_names = array();
		if ( ! ContactHub_Store::standalone() && function_exists( 'wp_roles' ) ) {
			foreach ( (array) wp_roles()->roles as $role_key => $role ) {
				$role_names[] = sanitize_title( $role_key );
				$role_names[] = sanitize_title( isset( $role['name'] ) ? $role['name'] : '' );
				$role_names[] = sanitize_title( isset( $role['name'] ) ? translate_user_role( $role['name'] ) : '' );
			}
			$role_names = array_values( array_unique( array_filter( $role_names ) ) );
		}
		return array_values(
			array_filter(
				(array) $tags,
				static function ( $tag ) use ( $system_tag_ids, $role_names ) {
					$tag_id = absint( self::value( $tag, array( 'id', 'tag_id', 'ID', 'get_id' ) ) );
					if ( $tag_id && in_array( $tag_id, $system_tag_ids, true ) ) {
						return false;
					}
					$tag_name = sanitize_title( self::value( $tag, array( 'name', 'tag_name', 'get_name' ) ) );
					if ( $tag_name && in_array( $tag_name, $role_names, true ) ) {
						return false;
					}
					return true;
				}
			)
		);
	}

	private static function status_label( $status ) {
		$labels = array(
			1 => __( 'Unconfirmed', 'contacthub' ), 2 => __( 'Marketing allowed', 'contacthub' ), 3 => __( 'Unsubscribed', 'contacthub' ),
			4 => __( 'Weekly', 'contacthub' ), 5 => __( 'Monthly', 'contacthub' ), 6 => __( 'Undeliverable', 'contacthub' ),
			7 => __( 'Spam', 'contacthub' ), 8 => __( 'Complaint', 'contacthub' ), 9 => __( 'Blocked', 'contacthub' ),
			'unconfirmed' => __( 'Unconfirmed', 'contacthub' ), 'confirmed' => __( 'Marketing allowed', 'contacthub' ),
			'unsubscribed' => __( 'Unsubscribed', 'contacthub' ), 'weekly' => __( 'Weekly', 'contacthub' ),
			'monthly' => __( 'Monthly', 'contacthub' ), 'hard_bounce' => __( 'Undeliverable', 'contacthub' ),
			'spam' => __( 'Spam', 'contacthub' ), 'complained' => __( 'Complaint', 'contacthub' ), 'blocked' => __( 'Blocked', 'contacthub' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Unknown', 'contacthub' );
	}

	private static function people_filter_link( $label, $filter, $current_filter, $search, $orderby, $order ) {
		$args = array( 'page' => self::people_page_slug() );
		if ( ! empty( $_GET['segment'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
			$args['segment'] = absint( $_GET['segment'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $_GET['contacthub_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
			$args['contacthub_filter'] = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) wp_unslash( $_GET['contacthub_filter'] ) ), 0, 12000 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( 'alle' !== $filter ) {
			$args['kunden'] = $filter;
		}
		if ( $orderby ) {
			$args['orderby'] = $orderby;
			$args['order'] = $order;
		}
		$class = 'button' . ( $filter === $current_filter ? ' button-primary' : '' );
		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) ) . '">' . esc_html( $label ) . '</a>';
	}

	private static function sortable_heading( $label, $field, $current_field, $current_order, $customer_filter, $search ) {
		$active = $field === $current_field;
		$next_order = $active && 'asc' === $current_order ? 'desc' : 'asc';
		$args = array(
			'page'    => self::people_page_slug(),
			'orderby' => $field,
			'order'   => $next_order,
		);
		if ( ! empty( $_GET['segment'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
			$args['segment'] = absint( $_GET['segment'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $_GET['contacthub_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
			$args['contacthub_filter'] = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) wp_unslash( $_GET['contacthub_filter'] ) ), 0, 12000 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( 'alle' !== $customer_filter ) {
			$args['kunden'] = $customer_filter;
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		$indicator = $active ? ( 'asc' === $current_order ? '↑' : '↓' ) : '↕';
		$aria = sprintf(
			__( 'Sort %1$s %2$s', 'contacthub' ),
			$label,
			'asc' === $next_order ? __( 'ascending', 'contacthub' ) : __( 'descending', 'contacthub' )
		);
		return '<th class="contacthub-sortable' . ( $active ? ' is-sorted' : '' ) . '"><a href="' . esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) ) . '" aria-label="' . esc_attr( $aria ) . '"><span>' . esc_html( $label ) . '</span><span class="contacthub-sort-indicator" aria-hidden="true">' . esc_html( $indicator ) . '</span></a></th>';
	}

	private static function compare_contacts( $left, $right, $orderby, $company_map, $order_summaries, $event_summaries = array() ) {
		$left_id = absint( self::value( $left, array( 'id', 'ID' ) ) );
		$right_id = absint( self::value( $right, array( 'id', 'ID' ) ) );
		if ( 'order_total' === $orderby ) {
			$left_value = isset( $order_summaries[ $left_id ]['total'] ) ? (float) $order_summaries[ $left_id ]['total'] : 0.0;
			$right_value = isset( $order_summaries[ $right_id ]['total'] ) ? (float) $order_summaries[ $right_id ]['total'] : 0.0;
			$comparison = $left_value <=> $right_value;
		} elseif ( 'event_count' === $orderby ) {
			$left_value = isset( $event_summaries[ $left_id ]['visited'] ) ? (int) $event_summaries[ $left_id ]['visited'] : 0;
			$right_value = isset( $event_summaries[ $right_id ]['visited'] ) ? (int) $event_summaries[ $right_id ]['visited'] : 0;
			$comparison = $left_value <=> $right_value;
		} elseif ( 'company' === $orderby ) {
			$left_company = isset( $company_map[ $left_id ][0] ) ? self::value( $company_map[ $left_id ][0], array( 'name', 'get_name', 'company_name' ) ) : '';
			$right_company = isset( $company_map[ $right_id ][0] ) ? self::value( $company_map[ $right_id ][0], array( 'name', 'get_name', 'company_name' ) ) : '';
			$comparison = strnatcasecmp( $left_company, $right_company );
		} else {
			$comparison = strnatcasecmp( self::value( $left, array( $orderby ) ), self::value( $right, array( $orderby ) ) );
		}
		return 0 !== $comparison ? $comparison : $left_id <=> $right_id;
	}

	private static function pagination( $page, $limit, $total, $search, $extra = array() ) {
		$pages = (int) ceil( $total / $limit );
		if ( $pages < 2 ) {
			return;
		}
		echo '<div class="tablenav"><div class="tablenav-pages">';
		$base_args = array( 'page' => self::people_page_slug(), 's' => $search );
		if ( isset( $extra['kunden'] ) && 'alle' !== $extra['kunden'] ) {
			$base_args['kunden'] = sanitize_key( $extra['kunden'] );
		}
		if ( ! empty( $extra['orderby'] ) ) {
			$base_args['orderby'] = $extra['orderby'];
			$base_args['order'] = isset( $extra['order'] ) ? $extra['order'] : 'asc';
		}
		if ( ! empty( $extra['segment'] ) ) {
			$base_args['segment'] = absint( $extra['segment'] );
		}
		if ( ! empty( $extra['contacthub_filter'] ) ) {
			$base_args['contacthub_filter'] = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $extra['contacthub_filter'] ), 0, 12000 );
		}
		if ( $page > 1 ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $base_args, array( 'contacthub_page' => $page - 1 ) ), admin_url( 'admin.php' ) ) ) . __( '">← Back</a> ', 'contacthub' );
		}
		echo '<span class="displaying-num">' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'contacthub' ), $page, $pages ) ) . '</span> ';
		if ( $page < $pages ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $base_args, array( 'contacthub_page' => $page + 1 ) ), admin_url( 'admin.php' ) ) ) . __( '">Next →</a>', 'contacthub' );
		}
		echo '</div></div>';
	}

	private static function render_company( $id ) {
		$company = self::company( $id );
		if ( ! $company ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'The company could not be loaded from the selected data source.', 'contacthub' ) . '</p></div>';
			return;
		}

		$readonly = isset( $_GET['ansicht'] ) && 'anzeigen' === sanitize_key( wp_unslash( $_GET['ansicht'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$name = self::value( $company, array( 'get_name', 'name', 'company_name' ), __( 'Company #', 'contacthub' ) . $id );
		$contacts = self::contacts( $company );
		$orders = self::orders( $contacts );
		$billing = $orders ? self::billing( $orders[0] ) : array();
		$uid_user = self::primary_user( $company, $contacts );
		$uid = $uid_user ? (string) get_user_meta( $uid_user->ID, 'billing_vat', true ) : '';
		if ( '' === $uid && $uid_user ) {
			$uid = (string) get_user_meta( $uid_user->ID, 'b2b_uid', true );
		}
		$uid_verified = $uid_user && '' !== $uid && hash_equals( (string) get_user_meta( $uid_user->ID, 'contacthub_vat_verified_hash', true ), hash( 'sha256', self::normalize_vat( $uid ) ) );

		echo '<a class="contacthub-back" href="' . esc_url( admin_url( 'admin.php?page=contacthub-firmen' ) ) . __( '">← Companies overview</a>', 'contacthub' );
		echo '<header class="contacthub-heading contacthub-company-heading"><div class="contacthub-avatar">' . esc_html( strtoupper( substr( $name, 0, 1 ) ) ) . '</div><div><p class="contacthub-eyebrow">' . ( $readonly ? esc_html__( 'View company', 'contacthub' ) : esc_html__( 'Edit company', 'contacthub' ) ) . '</p><h1>' . esc_html( $name ) . '</h1><p>' . esc_html( sprintf( ContactHub_Store::standalone() ? __( 'ContactHub ID %d', 'contacthub' ) : __( 'Groundhogg ID %d', 'contacthub' ), $id ) ) . '</p></div>';
		if ( $readonly ) {
			echo '<a class="button button-primary contacthub-heading-action" href="' . esc_url( admin_url( 'admin.php?page=contacthub-firmen&firma=' . $id ) ) . __( '">Edit</a>', 'contacthub' );
		}
		echo '</header>';
		echo '<div class="contacthub-grid">';
		echo __( '<aside><section class="contacthub-card contacthub-master-data"><h2>Master data</h2>', 'contacthub' );
		if ( $readonly ) {
			self::field( __( 'Company name', 'contacthub' ), $name );
			self::field( __( 'Website', 'contacthub' ), self::value( $company, array( 'domain', 'website', 'get_website' ) ) );
			self::field( __( 'Phone', 'contacthub' ), self::format_phone( self::value( $company, array( 'phone', 'get_phone' ) ) ) );
			self::field( __( 'Industry', 'contacthub' ), self::value( $company, array( 'industry', 'get_industry' ) ) );
			self::field( __( 'Company address', 'contacthub' ), self::value( $company, array( 'address', 'get_address' ) ) );
			self::field( __( 'Description', 'contacthub' ), self::value( $company, array( 'description', 'get_description' ) ) );
			self::readonly_uid_field( __( 'VAT ID', 'contacthub' ), $uid, $uid_verified );
		} else {
			self::editable_field( __( 'Company name', 'contacthub' ), $name, 'name', $id );
			self::editable_field( __( 'Website', 'contacthub' ), self::value( $company, array( 'domain', 'website', 'get_website' ) ), 'domain', $id );
			self::phone_field( __( 'Phone', 'contacthub' ), self::value( $company, array( 'phone', 'get_phone' ) ), 'phone', $id, 'company' );
			self::editable_field( __( 'Industry', 'contacthub' ), self::value( $company, array( 'industry', 'get_industry' ) ), 'industry', $id );
			self::editable_field( __( 'Company address', 'contacthub' ), self::value( $company, array( 'address', 'get_address' ) ), 'address', $id, true );
			self::editable_field( __( 'Description', 'contacthub' ), self::value( $company, array( 'description', 'get_description' ) ), 'description', $id, true );
			self::uid_field( __( 'VAT ID', 'contacthub' ), $uid, $id, 'company', $uid_verified );
		}
		echo __( '<p class="description contacthub-uid-help">Linked to German Market (billing_vat) and B2B Market (b2b_uid) for the primary contact. EU validation only runs when requested.</p>', 'contacthub' );
		echo __( '</section><section class="contacthub-card"><h2>Billing address</h2>', 'contacthub' );
		foreach ( array( __( 'Company', 'contacthub' ) => 'company', __( 'First name', 'contacthub' ) => 'first_name', __( 'Last name', 'contacthub' ) => 'last_name', __( 'Street', 'contacthub' ) => 'address_1', __( 'Address line 2', 'contacthub' ) => 'address_2', __( 'Postcode', 'contacthub' ) => 'postcode', __( 'Location', 'contacthub' ) => 'city', __( 'Country', 'contacthub' ) => 'country' ) as $label => $key ) {
			self::field( $label, isset( $billing[ $key ] ) ? $billing[ $key ] : '' );
		}
		echo '</section></aside>';

		echo __( '<main><section class="contacthub-card"><h2>Contacts <span class="contacthub-count">', 'contacthub' ) . esc_html( count( $contacts ) ) . '</span></h2>';
		if ( $contacts ) {
			echo '<div class="contacthub-people">';
			foreach ( $contacts as $contact ) {
				$full = trim( self::value( $contact, array( 'first_name', 'get_first_name' ) ) . ' ' . self::value( $contact, array( 'last_name', 'get_last_name' ) ) );
				$contact_id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
				echo '<div class="contacthub-person"><span class="dashicons dashicons-admin-users"></span><div><a href="' . esc_url( admin_url( 'admin.php?page=' . self::people_page_slug() . '&person=' . $contact_id ) ) . '"><strong>' . esc_html( $full ?: __( 'Contact', 'contacthub' ) ) . '</strong></a><br><span>' . esc_html( self::value( $contact, array( 'email', 'get_email' ), '—' ) ) . '</span></div></div>';
			}
			echo '</div>';
		} else {
			echo __( '<p class="contacthub-muted">No contacts were returned by the public Companies interface.</p>', 'contacthub' );
		}
		echo '</section>';

		echo __( '<section class="contacthub-card"><h2>Orders &amp; invoices <span class="contacthub-count">', 'contacthub' ) . esc_html( count( $orders ) ) . '</span></h2>';
		if ( $orders ) {
			self::render_orders_table( $orders );
		} else {
			echo __( '<p class="contacthub-muted">No order was found using the contacts\' email addresses.</p>', 'contacthub' );
		}
		echo '</section>';

		self::render_activities( $id );
		echo '</main></div>';
	}

	private static function render_person_tabs( $contact_id, $customer, $contact, $orders, $events, $events_enabled, $broadcasts, $editable ) {
		$tabs_id = 'contacthub-person-tabs-' . absint( $contact_id );
		$broadcast_page_arg = isset( $_GET['broadcast_page'] ) && is_scalar( $_GET['broadcast_page'] ) ? wp_unslash( $_GET['broadcast_page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$show_broadcasts = absint( $broadcast_page_arg ) > 0;
		echo __( '<section class="contacthub-card contacthub-tabs-card" data-tabs><div class="contacthub-tabs" role="tablist" aria-label="Person data">', 'contacthub' );
		echo '<button type="button" class="contacthub-tab' . ( $show_broadcasts ? '' : ' is-active' ) . '" id="' . esc_attr( $tabs_id . '-orders-tab' ) . '" role="tab" aria-selected="' . ( $show_broadcasts ? 'false' : 'true' ) . '" aria-controls="' . esc_attr( $tabs_id . '-orders' ) . '" tabindex="' . ( $show_broadcasts ? '-1' : '0' ) . '">' . esc_html__( 'Orders', 'contacthub' ) . ' <span class="contacthub-count">' . esc_html( count( $orders ) ) . '</span></button>';
		echo '<button type="button" class="contacthub-tab" id="' . esc_attr( $tabs_id . '-addresses-tab' ) . '" role="tab" aria-selected="false" aria-controls="' . esc_attr( $tabs_id . '-addresses' ) . '" tabindex="-1">' . esc_html__( 'Addresses', 'contacthub' ) . '</button>';
		if ( $events_enabled ) {
			echo '<button type="button" class="contacthub-tab" id="' . esc_attr( $tabs_id . '-events-tab' ) . '" role="tab" aria-selected="false" aria-controls="' . esc_attr( $tabs_id . '-events' ) . __( '" tabindex="-1">Events <span class="contacthub-count">', 'contacthub' ) . esc_html( count( $events ) ) . '</span></button>';
		}
		echo '<button type="button" class="contacthub-tab' . ( $show_broadcasts ? ' is-active' : '' ) . '" id="' . esc_attr( $tabs_id . '-broadcasts-tab' ) . '" role="tab" aria-selected="' . ( $show_broadcasts ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $tabs_id . '-broadcasts' ) . '" tabindex="' . ( $show_broadcasts ? '0' : '-1' ) . '">' . esc_html__( 'Mailings', 'contacthub' ) . ' <span class="contacthub-count">' . esc_html( count( $broadcasts ) ) . '</span></button>';
		echo '<button type="button" class="contacthub-tab" id="' . esc_attr( $tabs_id . '-activities-tab' ) . '" role="tab" aria-selected="false" aria-controls="' . esc_attr( $tabs_id . '-activities' ) . __( '" tabindex="-1">Activities</button></div>', 'contacthub' );
		echo '<div class="contacthub-tab-panel' . ( $show_broadcasts ? '' : ' is-active' ) . '" id="' . esc_attr( $tabs_id . '-orders' ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $tabs_id . '-orders-tab' ) . '"' . ( $show_broadcasts ? ' hidden' : '' ) . '>';
		self::render_person_orders( $orders, false );
		echo '</div><div class="contacthub-tab-panel" id="' . esc_attr( $tabs_id . '-addresses' ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $tabs_id . '-addresses-tab' ) . '" hidden>';
		self::render_customer_addresses( $contact_id, $customer, $contact, false, $editable );
		echo '</div>';
		if ( $events_enabled ) {
			echo '<div class="contacthub-tab-panel" id="' . esc_attr( $tabs_id . '-events' ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $tabs_id . '-events-tab' ) . '" hidden>';
			self::render_person_events( $events );
			echo '</div>';
		}
		echo '<div class="contacthub-tab-panel' . ( $show_broadcasts ? ' is-active' : '' ) . '" id="' . esc_attr( $tabs_id . '-broadcasts' ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $tabs_id . '-broadcasts-tab' ) . '"' . ( $show_broadcasts ? '' : ' hidden' ) . '>';
		self::render_person_broadcasts( $contact_id, $broadcasts );
		echo '</div>';
		echo '<div class="contacthub-tab-panel" id="' . esc_attr( $tabs_id . '-activities' ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $tabs_id . '-activities-tab' ) . '" hidden>';
		self::render_contact_activities( $contact_id, false );
		echo '</div></section>';
	}

	private static function render_person_events( $events ) {
		$visited = count( array_filter( $events, static function ( $event ) { return ! empty( $event['visited'] ); } ) );
		echo __( '<div class="contacthub-toolbar"><h2>Events</h2><span class="contacthub-count">', 'contacthub' ) . esc_html( $visited ) . __( ' attended</span></div>', 'contacthub' );
		if ( ! $events ) {
			echo __( '<p class="contacthub-muted">No event booking was found for this person.</p>', 'contacthub' );
			return;
		}
		echo __( '<table class="widefat contacthub-table"><thead><tr><th>Event</th><th><span class="screen-reader-text">Preview</span></th><th>Date</th><th>Status</th><th>Booking</th></tr></thead><tbody>', 'contacthub' );
		foreach ( $events as $event ) {
			$title = $event['title'] ? $event['title'] : __( 'Event #', 'contacthub' ) . $event['event_id'];
			echo '<tr><td><a href="' . esc_url( $event['event_url'] ) . '"><strong>' . esc_html( $title ) . '</strong></a></td>';
			echo '<td><button type="button" class="button-link contacthub-eye-action contacthub-preview-event" data-event="' . esc_attr( $event['event_id'] ) . __( '" aria-label="View event details" title="View event details"><span class="dashicons dashicons-visibility"></span></button></td>', 'contacthub' );
			echo '<td>' . esc_html( $event['date'] ? $event['date'] : '—' ) . '</td>';
			echo '<td><span class="contacthub-mini-badge">' . esc_html( $event['visited'] ? __( 'Attended', 'contacthub' ) : __( 'Booked', 'contacthub' ) ) . '</span></td>';
			echo '<td>';
			if ( $event['order_url'] ) {
				echo '<a href="' . esc_url( $event['order_url'] ) . __( '">Open order</a>', 'contacthub' );
			} else {
				echo '—';
			}
			echo '</td></tr>';
		}
		echo __( '</tbody></table><p class="description">Events are assigned by matching the attendee email address stored by Event Tickets.</p>', 'contacthub' );
	}

	private static function render_person_broadcasts( $contact_id, $broadcasts ) {
		$limit = 20;
		$total = count( $broadcasts );
		$pages = max( 1, (int) ceil( $total / $limit ) );
		$broadcast_page_arg = isset( $_GET['broadcast_page'] ) && is_scalar( $_GET['broadcast_page'] ) ? wp_unslash( $_GET['broadcast_page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$page = $broadcast_page_arg ? max( 1, absint( $broadcast_page_arg ) ) : 1;
		$page = min( $page, $pages );
		$page_items = array_slice( $broadcasts, ( $page - 1 ) * $limit, $limit );
		echo '<div class="contacthub-toolbar"><h2>' . esc_html__( 'Mailings', 'contacthub' ) . '</h2><span class="contacthub-count">' . esc_html( sprintf( __( '%d sent', 'contacthub' ), $total ) ) . '</span></div>';
		if ( ! $broadcasts ) {
			echo '<p class="contacthub-muted">' . esc_html__( 'No mailing with a reliable recipient assignment was found for this person.', 'contacthub' ) . '</p>';
			return;
		}
		echo '<table class="widefat contacthub-table contacthub-broadcast-table"><thead><tr><th>' . esc_html__( 'Mailing', 'contacthub' ) . '</th><th>' . esc_html__( 'Source', 'contacthub' ) . '</th><th>' . esc_html__( 'Sent', 'contacthub' ) . '</th><th>' . esc_html__( 'Delivered', 'contacthub' ) . '</th><th>' . esc_html__( 'Opened', 'contacthub' ) . '</th><th>' . esc_html__( 'Clicked', 'contacthub' ) . '</th></tr></thead><tbody>';
		foreach ( $page_items as $broadcast ) {
			if ( ! empty( $broadcast['detail_url'] ) ) echo '<tr><td><a href="' . esc_url( $broadcast['detail_url'] ) . '"><strong>' . esc_html( $broadcast['title'] ) . '</strong></a></td>';
			else echo '<tr><td><button type="button" class="button-link contacthub-preview-broadcast" data-contact="' . esc_attr( $contact_id ) . '" data-broadcast="' . esc_attr( $broadcast['id'] ) . '"><strong>' . esc_html( $broadcast['title'] ) . '</strong></button></td>';
			echo '<td><span class="contacthub-mini-badge">' . esc_html( $broadcast['source'] ?? __( 'Groundhogg', 'contacthub' ) ) . '</span></td><td>' . esc_html( $broadcast['date'] ) . '</td>';
			echo '<td>' . self::yes_no_badge( ! isset( $broadcast['delivered'] ) || $broadcast['delivered'] ) . '</td>';
			echo '<td>' . self::yes_no_badge( $broadcast['opened'] ) . '</td>';
			echo '<td>' . self::yes_no_badge( $broadcast['clicked'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( $pages > 1 ) {
			$base_args = array( 'page' => self::people_page_slug(), 'person' => absint( $contact_id ) );
			if ( ! empty( $_GET['contacthub_list_state'] ) ) $base_args['contacthub_list_state'] = sanitize_key( wp_unslash( $_GET['contacthub_list_state'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state.
			echo '<nav class="contacthub-broadcast-pagination" aria-label="' . esc_attr__( 'Mailing pages', 'contacthub' ) . '"><span>' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'contacthub' ), $page, $pages ) ) . '</span><span class="contacthub-pagination-buttons">';
			if ( $page > 1 ) {
				echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $base_args, array( 'broadcast_page' => $page - 1 ) ), admin_url( 'admin.php' ) ) ) . __( '">← Back</a>', 'contacthub' );
			}
			if ( $page < $pages ) {
				echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $base_args, array( 'broadcast_page' => $page + 1 ) ), admin_url( 'admin.php' ) ) ) . __( '">Next →</a>', 'contacthub' );
			}
			echo '</span></nav>';
		}
		echo '<p class="description">' . esc_html__( 'Available delivery, opening, and click information is read from the respective source. Privacy features or blocked images may affect tracking.', 'contacthub' ) . '</p>';
	}

	private static function yes_no_badge( $value ) {
		return '<span class="contacthub-status-badge ' . ( $value ? 'is-positive' : 'is-negative' ) . '">' . ( $value ? esc_html__( 'Yes', 'contacthub' ) : esc_html__( 'No', 'contacthub' ) ) . '</span>';
	}

	private static function render_bulk_marketing_modal() {
		echo '<div class="contacthub-consent-modal contacthub-bulk-modal" hidden><div class="contacthub-modal-panel" role="dialog" aria-modal="true" aria-labelledby="contacthub-bulk-title">';
		echo __( '<h2 id="contacthub-bulk-title">Enable marketable status for multiple people</h2>', 'contacthub' );
		echo __( '<p>Only selected people without an unsubscribe or blocked status will be changed. Already marketable contacts remain unchanged.</p>', 'contacthub' );
		echo __( '<p class="contacthub-bulk-modal-count"><strong>0 people selected</strong></p>', 'contacthub' );
		echo __( '<label>Source<select class="contacthub-bulk-source"><option value="">Please select</option><option value="personally">In person</option><option value="phone">By phone</option><option value="written">In writing</option><option value="email">By email</option><option value="import">Documented import</option><option value="other">Other</option></select></label>', 'contacthub' );
		echo __( '<label>Date<input type="date" class="contacthub-bulk-date" value="', 'contacthub' ) . esc_attr( current_time( 'Y-m-d' ) ) . '"></label>';
		echo __( '<label>Note / evidence<textarea class="contacthub-bulk-note" rows="3" placeholder="Optional evidence note"></textarea></label>', 'contacthub' );
		echo __( '<label class="contacthub-confirm"><input type="checkbox" class="contacthub-bulk-confirmed"> <span>I confirm that explicit marketing consent exists for every selected person.</span></label>', 'contacthub' );
		echo __( '<div class="contacthub-modal-message" aria-live="polite"></div><div class="contacthub-modal-actions"><button type="button" class="button button-primary contacthub-save-bulk">Enable with confirmation</button><button type="button" class="button contacthub-close-bulk">Cancel</button></div>', 'contacthub' );
		echo '</div></div>';
	}

	private static function render_contact_output_modal( $total, $search, $customer_filter, $segment_id, $advanced_filter, $orderby, $order ) {
		$default_columns = array( 'first_name', 'last_name', 'email', 'phone', 'company', 'postcode', 'city', 'country' );
		echo '<div class="contacthub-consent-modal contacthub-output-modal" hidden><div class="contacthub-modal-panel" role="dialog" aria-modal="true" aria-labelledby="contacthub-output-title">';
		echo '<h2 id="contacthub-output-title">' . esc_html__( 'Export contacts', 'contacthub' ) . '</h2><p class="contacthub-output-help">' . esc_html__( 'Choose whether selected people or all currently filtered people should be used.', 'contacthub' ) . '</p>';
		echo '<form class="contacthub-output-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_export_contacts"><input type="hidden" name="selected_ids" value="">';
		wp_nonce_field( 'contacthub_contact_output' );
		foreach ( array( 's' => $search, 'kunden' => $customer_filter, 'segment' => $segment_id, 'contacthub_filter' => $advanced_filter, 'orderby' => $orderby, 'order' => $order ) as $key => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		}
		echo '<fieldset class="contacthub-output-scope"><legend>' . esc_html__( 'People to include', 'contacthub' ) . '</legend><label><input type="radio" name="scope" value="selected"> <span class="contacthub-output-selected-label">' . esc_html__( 'Selected people', 'contacthub' ) . '</span></label><label><input type="radio" name="scope" value="all" checked> ' . esc_html( sprintf( __( 'All filtered people (%d)', 'contacthub' ), $total ) ) . '</label></fieldset>';
		echo '<fieldset class="contacthub-output-columns"><legend>' . esc_html__( 'Information to include', 'contacthub' ) . '</legend><div class="contacthub-output-column-grid">';
		foreach ( self::contact_output_fields() as $key => $label ) {
			echo '<label><input type="checkbox" name="columns[]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $default_columns, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div></fieldset><div class="contacthub-modal-message" aria-live="polite"></div><div class="contacthub-modal-actions"><button type="submit" class="button button-primary contacthub-submit-output">' . esc_html__( 'Export CSV', 'contacthub' ) . '</button><button type="button" class="button contacthub-close-output">' . esc_html__( 'Cancel', 'contacthub' ) . '</button></div></form></div></div>';
	}

	private static function render_new_person_modal() {
		$companies = self::companies_available() ? self::companies() : array();
		echo '<div class="contacthub-consent-modal contacthub-new-person-modal" hidden><div class="contacthub-modal-panel" role="dialog" aria-modal="true" aria-labelledby="contacthub-new-person-title">';
		echo '<h2 id="contacthub-new-person-title">' . esc_html__( 'Create new person', 'contacthub' ) . '</h2><p>' . esc_html__( 'The person is created in the selected data source with the marketing status “Unconfirmed”.', 'contacthub' ) . '</p>';
		echo __( '<label>First name<input type="text" class="contacthub-new-first-name" autocomplete="given-name"></label>', 'contacthub' );
		echo __( '<label>Last name<input type="text" class="contacthub-new-last-name" autocomplete="family-name"></label>', 'contacthub' );
		echo '<label>' . esc_html__( 'Salutation', 'contacthub' ) . '<select class="contacthub-new-salutation"><option value="">' . esc_html__( '--- Please select ---', 'contacthub' ) . '</option><option value="neutral">' . esc_html__( 'Neutral', 'contacthub' ) . '</option><option value="mr">' . esc_html__( 'Mr', 'contacthub' ) . '</option><option value="ms">' . esc_html__( 'Ms', 'contacthub' ) . '</option><option value="family">' . esc_html__( 'Family', 'contacthub' ) . '</option></select></label>';
		echo __( '<label>Email address<input type="email" class="contacthub-new-email" autocomplete="email" required></label>', 'contacthub' );
		if ( self::companies_available() ) {
			echo '<fieldset class="contacthub-new-company-fields"><legend>' . esc_html__( 'Company assignment (optional)', 'contacthub' ) . '</legend><p class="description">' . esc_html__( 'Select an existing company by typing its name, or enter a new company name. Similar existing names are suggested to avoid duplicates.', 'contacthub' ) . '</p>';
			echo '<label>' . esc_html__( 'Company', 'contacthub' ) . '<input type="text" class="contacthub-new-company-name" list="contacthub-new-company-options" autocomplete="organization"><input type="hidden" class="contacthub-new-company-id" value=""><datalist id="contacthub-new-company-options">';
			foreach ( $companies as $company ) {
				$id = absint( self::value( $company, array( 'id', 'ID', 'get_id' ) ) );
				$name = self::value( $company, array( 'name', 'company_name', 'get_name' ) );
				if ( $id && $name ) echo '<option value="' . esc_attr( $name ) . '" data-company-id="' . esc_attr( $id ) . '"></option>';
			}
			echo '</datalist></label><label>' . esc_html__( 'Position / function in the company', 'contacthub' ) . '<input type="text" class="contacthub-new-company-position"></label></fieldset>';
		}
		echo __( '<div class="contacthub-modal-message" aria-live="polite"></div><div class="contacthub-modal-actions"><button type="button" class="button button-primary contacthub-save-new-person">Create person</button><button type="button" class="button contacthub-close-new-person">Cancel</button></div>', 'contacthub' );
		echo '</div></div>';
	}

	private static function render_new_company_modal() {
		echo '<div class="contacthub-consent-modal contacthub-new-company-modal" hidden><div class="contacthub-modal-panel" role="dialog" aria-modal="true" aria-labelledby="contacthub-new-company-title"><h2 id="contacthub-new-company-title">' . esc_html__( 'Create company', 'contacthub' ) . '</h2><label>' . esc_html__( 'Company name', 'contacthub' ) . '<input type="text" class="contacthub-new-company-name-only" required></label><div class="contacthub-modal-message" aria-live="polite"></div><div class="contacthub-modal-actions"><button type="button" class="button button-primary contacthub-save-new-company">' . esc_html__( 'Create company', 'contacthub' ) . '</button><button type="button" class="button contacthub-close-new-company">' . esc_html__( 'Cancel', 'contacthub' ) . '</button></div></div></div>';
	}

	private static function render_marketing( $contact, $editable = true ) {
		$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
		$status = self::optin_status( $contact );
		$source = self::meta( $contact, array( 'marketing_consent_source' ) );
		$is_marketable = in_array( $status, array( 2, 4, 5, 'confirmed', 'weekly', 'monthly' ), true );
		$date = $is_marketable
			? self::meta( $contact, array( 'marketing_consent_date' ) )
			: self::meta( $contact, array( 'marketing_consent_revoked_date', 'marketing_consent_date' ) );
		$user = self::contact_user( $contact );
		$doi_complete = $user ? (bool) get_user_meta( $user->ID, 'contacthub_email_verified', true ) : (bool) self::meta( $contact, array( 'email_confirmed', 'double_opt_in_completed' ) );
		if ( ! $doi_complete && ! ContactHub_Store::standalone() ) {
			$doi_complete = in_array( $status, array( 2, 'confirmed' ), true );
		}

		echo '<section class="contacthub-card contacthub-marketing" data-contact="' . esc_attr( $id ) . __( '"><div class="contacthub-toolbar"><h2>Marketing &amp; consent</h2>', 'contacthub' ) . self::status_badge( $status ) . '</div>';
		echo '<div class="contacthub-consent-details">';
		self::field( __( 'Marketing status', 'contacthub' ), self::status_label( $status ) );
		self::field( __( 'Documented date', 'contacthub' ), $date );
		self::field( __( 'Source', 'contacthub' ), $source );
		self::field( __( 'Double opt-in', 'contacthub' ), $doi_complete ? __( 'Completed', 'contacthub' ) : __( 'Not completed', 'contacthub' ) );
		echo '</div>';
		if ( $editable ) {
			echo __( '<button type="button" class="button contacthub-open-consent">Edit consent</button>', 'contacthub' );
		}
		if ( $editable ) {
			echo '<p class="description">' . esc_html__( 'The change is saved in the selected leading data source and documented as an activity.', 'contacthub' ) . '</p>';
		}
		if ( ! $editable ) {
			echo '</section>';
			return;
		}
		echo '<div class="contacthub-consent-modal" hidden><div class="contacthub-modal-panel" role="dialog" aria-modal="true" aria-labelledby="contacthub-consent-title-' . esc_attr( $id ) . '">';
		echo '<h2 id="contacthub-consent-title-' . esc_attr( $id ) . __( '">Edit marketing consent</h2>', 'contacthub' );
		echo __( '<p>Only change this when consent or withdrawal is actually documented.</p>', 'contacthub' );
		echo __( '<label>Decision<select class="contacthub-consent-decision"><option value="grant">Grant consent</option><option value="revoke">Withdraw consent</option></select></label>', 'contacthub' );
		echo __( '<label>Source<select class="contacthub-consent-source"><option value="">Please select</option><option value="personally">In person</option><option value="phone">By phone</option><option value="written">In writing</option><option value="email">By email</option><option value="import">Documented import</option><option value="other">Other</option></select></label>', 'contacthub' );
		echo __( '<label>Date<input type="date" class="contacthub-consent-date" value="', 'contacthub' ) . esc_attr( current_time( 'Y-m-d' ) ) . '"></label>';
		echo __( '<label>Note / evidence<textarea class="contacthub-consent-note" rows="3" placeholder="Optional evidence note"></textarea></label>', 'contacthub' );
		echo __( '<label class="contacthub-confirm"><input type="checkbox" class="contacthub-consent-confirmed"> <span>I confirm that explicit consent has been given.</span></label>', 'contacthub' );
		echo __( '<div class="contacthub-modal-message" aria-live="polite"></div><div class="contacthub-modal-actions"><button type="button" class="button button-primary contacthub-save-consent">Save with confirmation</button><button type="button" class="button contacthub-close-consent">Cancel</button></div>', 'contacthub' );
		echo '</div></div></section>';
	}

	private static function render_contact_tags( $contact, $editable = true ) {
		$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
		$tags = is_array( $contact ) && isset( $contact['tags'] ) && is_array( $contact['tags'] ) ? $contact['tags'] : array();
		$tags = self::user_managed_tags( $tags );
		$all_tags = $editable ? self::all_tags() : array();

		echo '<section class="contacthub-card contacthub-tags-card" data-contact="' . esc_attr( $id ) . '"><h2>' . esc_html__( 'Tags', 'contacthub' ) . '</h2>';
		if ( ! $editable ) {
			echo '<div class="contacthub-tags"><div>';
			if ( $tags ) {
				foreach ( $tags as $tag ) {
					echo '<span class="contacthub-tag">' . esc_html( self::value( $tag, array( 'name', 'tag_name' ) ) ) . '</span>';
				}
			} else {
				echo __( '<span class="contacthub-muted">No tags assigned yet.</span>', 'contacthub' );
			}
			echo '</div></div></section>';
			return;
		}

		echo '<div class="contacthub-tag-manager"><div class="contacthub-token-control"><div class="contacthub-token-entry"><div class="contacthub-token-field">';
		foreach ( $tags as $tag ) {
			$tag_id = absint( self::value( $tag, array( 'id', 'tag_id', 'ID' ) ) );
			if ( ! $tag_id ) {
				continue;
			}
			echo '<span class="contacthub-tag" data-tag="' . esc_attr( $tag_id ) . '">' . esc_html( self::value( $tag, array( 'name', 'tag_name' ) ) ) . '<button type="button" class="button-link contacthub-remove-tag" data-tag="' . esc_attr( $tag_id ) . __( '" aria-label="Remove tag from this person">&times;</button></span>', 'contacthub' );
		}
		echo __( '<input type="text" class="contacthub-token-input contacthub-tag-input" placeholder="Search or create a tag" aria-label="Search or create a tag" autocomplete="off"></div><div class="contacthub-token-suggestions contacthub-tag-suggestions" role="listbox" hidden>', 'contacthub' );
		foreach ( $all_tags as $tag ) {
			echo '<button type="button" class="button-link contacthub-token-suggestion contacthub-tag-suggestion" role="option" data-tag="' . esc_attr( $tag['id'] ) . '" data-name="' . esc_attr( $tag['name'] ) . '">' . esc_html( $tag['name'] ) . '</button>';
		}
		echo __( '</div></div></div><p class="description contacthub-tag-autosave-note">Selecting a tag saves it immediately for this contact. Press Enter to create a new tag.</p>', 'contacthub' );
		echo '<div class="contacthub-tag-message" aria-live="polite"></div></div></section>';
	}

	private static function render_customer_addresses( $contact_id, $customer, $contact, $standalone = true, $editable = true ) {
		if ( $standalone ) {
			echo '<section class="contacthub-card">';
		}
		echo __( '<h2>Addresses</h2>', 'contacthub' );
		$countries = self::ordered_countries( function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : self::contact_countries() );
		$contact_address = self::contact_address( $contact );
		if ( ! array_filter( $contact_address ) && $customer ) {
			$billing_fallback = self::customer_address( $customer, 'billing' );
			foreach ( array_keys( $contact_address ) as $key ) {
				$contact_address[ $key ] = isset( $billing_fallback[ $key ] ) ? $billing_fallback[ $key ] : '';
			}
		}
		self::render_contact_address_form( $contact_address, $countries, $contact_id, $editable, (bool) $customer );
		if ( ! $customer ) {
			echo __( '<p class="contacthub-muted contacthub-woocommerce-address-note">No linked WordPress/WooCommerce customer account. The contact address is saved independently; billing and shipping addresses become available after an account is linked.</p>', 'contacthub' );
			if ( $standalone ) {
				echo '</section>';
			}
			return;
		}
		$billing = self::customer_address( $customer, 'billing' );
		if ( empty( $billing['first_name'] ) ) {
			$billing['first_name'] = self::value( $contact, array( 'first_name' ) );
		}
		if ( empty( $billing['last_name'] ) ) {
			$billing['last_name'] = self::value( $contact, array( 'last_name' ) );
		}
		if ( empty( $billing['email'] ) ) {
			$billing['email'] = self::value( $contact, array( 'email' ) );
		}
		echo '<div class="contacthub-address-grid">';
		self::render_customer_address_form( 'billing', __( 'Billing address', 'contacthub' ), $billing, $countries, $contact_id, $editable );
		self::render_customer_address_form( 'shipping', __( 'Shipping address', 'contacthub' ), self::customer_address( $customer, 'shipping' ), $countries, $contact_id, $editable );
		echo '</div>';
		if ( $editable ) {
			echo __( '<p class="description">Only the WooCommerce customer profile is saved. Existing orders and historical invoices are not changed.</p>', 'contacthub' );
		}
		if ( $standalone ) {
			echo '</section>';
		}
	}

	private static function render_contact_address_form( $address, $countries, $contact_id, $editable, $can_sync ) {
		echo '<div class="contacthub-address-form contacthub-contact-address-form" data-contact="' . esc_attr( $contact_id ) . '" data-address-type="contact"><h3>' . esc_html__( 'Contact address', 'contacthub' ) . '</h3>';
		$disabled = $editable ? '' : ' disabled';
		foreach ( array( __( 'Street and house number', 'contacthub' ) => 'address_1', __( 'Address line 2', 'contacthub' ) => 'address_2', __( 'Postcode', 'contacthub' ) => 'postcode', __( 'Location', 'contacthub' ) => 'city', __( 'State / region', 'contacthub' ) => 'state' ) as $label => $key ) {
			echo '<label>' . esc_html( $label ) . '<input type="text" data-key="' . esc_attr( $key ) . '" value="' . esc_attr( isset( $address[ $key ] ) ? $address[ $key ] : '' ) . '"' . $disabled . '></label>';
		}
		$selected_country = ! empty( $address['country'] ) ? $address['country'] : 'AT';
		echo __( '<label>Country<select data-key="country"', 'contacthub' ) . $disabled . '>';
		foreach ( $countries as $code => $label ) {
			if ( '__separator__' === $code ) { echo '<option value="" disabled>' . esc_html( $label ) . '</option>'; continue; }
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $selected_country, $code, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
		if ( $editable && $can_sync ) {
			echo '<label class="contacthub-address-sync"><input type="checkbox" class="contacthub-sync-to-woocommerce" value="1"> <span>' . esc_html__( 'Also copy this address to the WooCommerce billing address.', 'contacthub' ) . '</span></label>';
		}
		if ( $editable ) {
			echo '<div class="contacthub-address-message" aria-live="polite"></div><button type="button" class="button button-primary contacthub-save-address">' . esc_html__( 'Save contact address', 'contacthub' ) . '</button>';
		}
		echo '</div>';
	}

	private static function render_customer_address_form( $type, $title, $address, $countries, $contact_id, $editable = true ) {
		echo '<div class="contacthub-address-form" data-contact="' . esc_attr( $contact_id ) . '" data-address-type="' . esc_attr( $type ) . '"><h3>' . esc_html( $title ) . '</h3>';
		$disabled = $editable ? '' : ' disabled';
		foreach ( array( __( 'First name', 'contacthub' ) => 'first_name', __( 'Last name', 'contacthub' ) => 'last_name', __( 'Company', 'contacthub' ) => 'company', __( 'Street and house number', 'contacthub' ) => 'address_1', __( 'Address line 2', 'contacthub' ) => 'address_2', __( 'Postcode', 'contacthub' ) => 'postcode', __( 'Location', 'contacthub' ) => 'city', __( 'State / region', 'contacthub' ) => 'state' ) as $label => $key ) {
			echo '<label>' . esc_html( $label ) . '<input type="text" data-key="' . esc_attr( $key ) . '" value="' . esc_attr( isset( $address[ $key ] ) ? $address[ $key ] : '' ) . '"' . $disabled . '></label>';
		}
		$selected_country = ! empty( $address['country'] ) ? $address['country'] : 'AT';
		echo __( '<label>Country<select data-key="country"', 'contacthub' ) . $disabled . '>';
		foreach ( $countries as $code => $label ) {
			if ( '__separator__' === $code ) { echo '<option value="" disabled>' . esc_html( $label ) . '</option>'; continue; }
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $selected_country, $code, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
		if ( 'billing' === $type ) {
			echo __( '<label>Billing email<input type="email" data-key="email" value="', 'contacthub' ) . esc_attr( isset( $address['email'] ) ? $address['email'] : '' ) . '"' . $disabled . '></label>';
			if ( $editable ) {
				echo __( '<label>Billing phone', 'contacthub' ) . self::phone_input_html( isset( $address['phone'] ) ? $address['phone'] : '' ) . '</label>';
			} else {
				echo __( '<label>Billing phone<input type="text" value="', 'contacthub' ) . esc_attr( self::format_phone( isset( $address['phone'] ) ? $address['phone'] : '' ) ) . '" disabled></label>';
			}
		}
		if ( $editable ) {
			echo '<div class="contacthub-address-message" aria-live="polite"></div><button type="button" class="button button-primary contacthub-save-address">' . esc_html( $title ) . __( ' save</button>', 'contacthub' );
		}
		echo '</div>';
	}

	private static function render_person_orders( $orders, $standalone = true ) {
		if ( $standalone ) {
			echo '<section class="contacthub-card">';
		}
		echo __( '<h2>Orders &amp; invoices <span class="contacthub-count">', 'contacthub' ) . esc_html( count( $orders ) ) . '</span></h2>';
		if ( ! $orders ) {
			echo __( '<p class="contacthub-muted">No order was found using the email address.</p>', 'contacthub' );
			if ( $standalone ) {
				echo '</section>';
			}
			return;
		}
		self::render_orders_table( $orders );
		if ( $standalone ) {
			echo '</section>';
		}
	}

	private static function render_order_row( $order ) {
		$invoice_url = self::invoice_url( $order );
		$invoice_number = self::invoice_number( $order );
		echo '<tr><td><a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a></td>';
		echo '<td>' . esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd.m.Y' ) : '—' ) . '</td>';
		echo '<td>' . esc_html( $invoice_number ?: '—' ) . '</td>';
		echo '<td><div class="contacthub-order-actions">';
		echo '<button type="button" class="button-link contacthub-eye-action contacthub-preview-order" data-order="' . esc_attr( $order->get_id() ) . __( '" aria-label="View order information" title="View order information"><span class="dashicons dashicons-visibility"></span></button>', 'contacthub' );
		if ( $invoice_url ) {
			echo '<a class="button-link contacthub-eye-action contacthub-invoice-preview" href="' . esc_url( $invoice_url ) . __( '" data-title="Invoice for order #', 'contacthub' ) . esc_attr( $order->get_order_number() ) . __( '" aria-label="View invoice PDF" title="View invoice PDF"><span class="dashicons dashicons-media-document"></span></a>', 'contacthub' );
		} else {
			echo __( '<span class="contacthub-document-unavailable" title="German Market does not provide an invoice action for this order">—</span>', 'contacthub' );
		}
		echo '</div></td>';
		echo '<td>' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</td>';
		echo '<td>' . esc_html( $order->get_payment_method_title() ?: '—' ) . '</td>';
		echo '<td>' . wp_kses_post( $order->get_formatted_order_total() ) . '</td></tr>';
	}

	private static function render_orders_table( $orders ) {
		$totals = array();
		echo __( '<table class="widefat contacthub-table contacthub-orders-table"><thead><tr><th>Order number</th><th>Date</th><th>Invoice number</th><th>Actions</th><th>Status</th><th>Payment method</th><th>Total</th></tr></thead><tbody>', 'contacthub' );
		foreach ( $orders as $order ) {
			self::render_order_row( $order );
			$currency = $order->get_currency() ?: get_woocommerce_currency();
			$order_total = (float) $order->get_total();
			if ( method_exists( $order, 'get_total_refunded' ) ) {
				$order_total -= (float) $order->get_total_refunded();
			}
			$totals[ $currency ] = isset( $totals[ $currency ] ) ? $totals[ $currency ] + $order_total : $order_total;
		}
		echo __( '</tbody><tfoot><tr><th colspan="6">Grand total</th><td><strong>', 'contacthub' );
		$formatted = array();
		foreach ( $totals as $currency => $total ) {
			$formatted[] = wc_price( $total, array( 'currency' => $currency ) );
		}
		echo wp_kses_post( implode( '<br>', $formatted ) ) . '</strong></td></tr></tfoot></table>';
	}

	private static function invoice_number( $order ) {
		$candidates = array( '_wgm_invoice_number', 'wgm_invoice_number', '_wp_wc_invoice_pdf_invoice_number', 'wp_wc_invoice_pdf_invoice_number', '_wcpdf_formatted_invoice_number', '_wcpdf_invoice_number', '_invoice_number', 'invoice_number' );
		foreach ( $candidates as $key ) {
			$value = $order->get_meta( $key, true );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}
		foreach ( $order->get_meta_data() as $meta ) {
			$data = is_object( $meta ) && method_exists( $meta, 'get_data' ) ? $meta->get_data() : array();
			$key = isset( $data['key'] ) ? strtolower( (string) $data['key'] ) : '';
			$value = isset( $data['value'] ) ? $data['value'] : '';
			$is_invoice = false !== strpos( $key, 'invoice' ) || false !== strpos( $key, 'rechnung' );
			$is_number = false !== strpos( $key, 'number' ) || false !== strpos( $key, 'nummer' );
			if ( $is_invoice && $is_number && is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}
		$filtered = (string) apply_filters( 'contacthub_german_market_invoice_number', '', $order );
		return '' !== trim( $filtered ) ? trim( $filtered ) : (string) $order->get_order_number();
	}

	private static function invoice_url( $order ) {
		$actions = apply_filters( 'woocommerce_admin_order_actions', array(), $order );
		foreach ( (array) $actions as $key => $action ) {
			if ( ! is_array( $action ) || empty( $action['url'] ) ) {
				continue;
			}
			$haystack = strtolower( $key . ' ' . ( isset( $action['action'] ) ? $action['action'] : '' ) . ' ' . ( isset( $action['name'] ) ? $action['name'] : '' ) . ' ' . $action['url'] );
			if ( ( false !== strpos( $haystack, 'invoice' ) || false !== strpos( $haystack, 'rechnung' ) ) && false === strpos( $haystack, 'refund' ) ) {
				return esc_url_raw( $action['url'] );
			}
		}
		return (string) apply_filters( 'contacthub_german_market_invoice_url', '', $order );
	}

	private static function preview_document_css() {
		return 'body{margin:0;padding:28px;font:14px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#253342;background:#f5f7fa}header{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}h1{font-size:26px;margin:3px 0}h2{font-size:15px;margin:0 0 8px}.eyebrow{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#5266d4}.status{background:#e8eefc;border-radius:99px;padding:6px 12px;font-weight:600}.facts,.addresses{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:0 0 20px}.facts>div,.addresses section,.note,.event-fact{background:#fff;border:1px solid #dfe5eb;border-radius:9px;padding:15px}.facts span,.event-fact span{display:block;color:#718096;font-size:12px;margin-bottom:4px}.event-layout{display:grid;grid-template-columns:minmax(210px,260px) minmax(0,1fr);gap:22px;align-items:start}.event-sidebar{display:flex;flex-direction:column;gap:12px}.event-fact strong{display:block}.event-fact small{display:block;color:#5266d4;font-weight:700;margin-top:5px}.event-content{min-width:0}.event-content header{margin-top:2px}.long-info{margin-top:14px}.long-info img{max-width:100%;height:auto}.event-image{display:block;width:100%;max-height:420px;object-fit:cover;border-radius:10px;margin-bottom:22px}table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #dfe5eb}th,td{padding:11px;border-bottom:1px solid #e8edf1;text-align:left}.num{text-align:right}p{margin:6px 0}@media(max-width:700px){body{padding:16px}.facts,.addresses,.event-layout{grid-template-columns:1fr}}';
	}

	private static function editable_roles() {
		$roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();
		$result = array();
		foreach ( $roles as $key => $role ) {
			$result[ sanitize_key( $key ) ] = isset( $role['name'] ) ? translate_user_role( $role['name'] ) : $key;
		}
		return $result;
	}

	private static function render_role_picker( $roles, $selected_roles ) {
		$selected_roles = array_values( array_intersect( array_map( 'sanitize_key', (array) $selected_roles ), array_keys( $roles ) ) );
		echo '<div class="contacthub-role-picker" data-role-picker><div class="contacthub-token-entry"><div class="contacthub-token-field">';
		foreach ( $selected_roles as $role ) {
			echo '<span class="contacthub-role-token" data-role="' . esc_attr( $role ) . '">' . esc_html( $roles[ $role ] ) . '<button type="button" class="button-link contacthub-remove-role" aria-label="' . esc_attr( sprintf( __( 'Remove role %s', 'contacthub' ), $roles[ $role ] ) ) . '">&times;</button></span>';
		}
		echo __( '<input type="text" class="contacthub-token-input contacthub-role-input" placeholder="Search WordPress roles" aria-label="Search WordPress roles" autocomplete="off"></div><div class="contacthub-token-suggestions contacthub-role-suggestions" role="listbox" hidden>', 'contacthub' );
		foreach ( $roles as $role => $label ) {
			echo '<button type="button" class="button-link contacthub-token-suggestion contacthub-role-suggestion" role="option" data-role="' . esc_attr( $role ) . '" data-name="' . esc_attr( $label ) . '">' . esc_html( $label ) . '</button>';
		}
		echo __( '</div></div><div class="contacthub-role-picker-message" aria-live="polite"></div></div>', 'contacthub' );
	}

	private static function render_user_account_controls( $contact_id, $contact, $user, $readonly ) {
		$linked_id = absint( self::value( $contact, array( 'user_id', 'get_user_id' ) ) );
		$roles = self::editable_roles();
		echo __( '<div class="contacthub-user-account"><span>WordPress user account</span>', 'contacthub' );
		if ( $user ) {
			echo '<strong>' . esc_html__( 'Account available', 'contacthub' ) . '</strong><small>' . esc_html( $user->user_login ) . '</small>';
			if ( ! $readonly && ! $linked_id ) {
				echo __( '<button type="button" class="button button-small contacthub-open-user-account" data-mode="link">Link existing account</button>', 'contacthub' );
			}
		} else {
			echo __( '<strong>No user account</strong>', 'contacthub' );
			if ( ! $readonly ) {
				echo __( '<button type="button" class="button button-small contacthub-open-user-account" data-mode="create">Create user account</button>', 'contacthub' );
			}
		}
		echo '</div>';
		if ( $user ) {
			$labels = array();
			foreach ( (array) $user->roles as $role ) {
				$labels[] = isset( $roles[ $role ] ) ? $roles[ $role ] : $role;
			}
			echo '<div class="contacthub-user-roles"><span>' . esc_html__( 'Customer / WordPress roles', 'contacthub' ) . '</span><strong>' . esc_html( implode( ', ', $labels ) ?: __( 'No role', 'contacthub' ) ) . '</strong>';
			if ( ! $readonly && $linked_id ) {
				echo __( '<button type="button" class="button button-small contacthub-open-roles">Edit customer roles</button>', 'contacthub' );
			}
			echo '</div>';
		}
		if ( ! $readonly && $user && $linked_id ) {
			echo '<div class="contacthub-consent-modal contacthub-roles-modal" hidden><div class="contacthub-modal-panel" data-contact="' . esc_attr( $contact_id ) . '" data-user="' . esc_attr( $user->ID ) . __( '"><h2>Edit customer roles</h2><p>Multiple WordPress roles are supported. Start typing and select a suggested role. Changes immediately affect this user account\'s permissions.</p>', 'contacthub' );
			self::render_role_picker( $roles, (array) $user->roles );
			echo __( '<div class="contacthub-modal-message"></div><div class="contacthub-modal-actions"><button type="button" class="button button-primary contacthub-save-roles">Save roles</button><button type="button" class="button contacthub-close-roles">Cancel</button></div></div></div>', 'contacthub' );
		}
		if ( ! $readonly && ( ! $user || ! $linked_id ) ) {
			$base = sanitize_user( strtolower( trim( self::value( $contact, array( 'first_name' ) ) . '.' . self::value( $contact, array( 'last_name' ) ) ) ), true );
			$base = $base ?: sanitize_user( strtok( self::value( $contact, array( 'email' ) ), '@' ), true );
			echo '<div class="contacthub-consent-modal contacthub-user-account-modal" hidden><div class="contacthub-modal-panel" data-contact="' . esc_attr( $contact_id ) . '" data-mode="' . ( $user ? 'link' : 'create' ) . '"><h2>' . ( $user ? __( 'Link user account', 'contacthub' ) : __( 'Create user account', 'contacthub' ) ) . '</h2>';
			if ( $user ) {
				$source_label = ContactHub_Store::standalone() ? __( 'ContactHub person', 'contacthub' ) : __( 'Groundhogg contact', 'contacthub' );
				echo '<p>' . sprintf( wp_kses( __( 'The existing account <strong>%1$s</strong> with the same email address will be linked to this %2$s.', 'contacthub' ), array( 'strong' => array() ) ), esc_html( $user->user_login ), esc_html( $source_label ) ) . '</p>';
			} else {
				echo __( '<p>An account with a secure random password will be created. This does not grant marketing consent.</p><label>Username<input type="text" class="contacthub-account-username" value="', 'contacthub' ) . esc_attr( $base ) . '"></label>';
				self::render_role_picker( $roles, array( 'customer' ) );
				echo __( '<label class="contacthub-confirm"><input type="checkbox" class="contacthub-account-invite" checked> <span>Send an invitation to set the password.</span></label>', 'contacthub' );
			}
			echo '<div class="contacthub-modal-message"></div><div class="contacthub-modal-actions"><button type="button" class="button button-primary contacthub-save-user-account">' . ( $user ? __( 'Link', 'contacthub' ) : __( 'Create account', 'contacthub' ) ) . __( '</button><button type="button" class="button contacthub-close-user-account">Cancel</button></div></div></div>', 'contacthub' );
		}
	}

	private static function guard_user_admin_ajax() {
		self::guard_ajax();
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'promote_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'Only administrators may manage user accounts and roles.', 'contacthub' ) ), 403 );
		}
	}

	private static function set_user_roles( $user, $roles ) {
		$roles = array_values( array_unique( array_map( 'sanitize_key', (array) $roles ) ) );
		$allowed = self::editable_roles();
		if ( ! $roles || array_diff( $roles, array_keys( $allowed ) ) ) {
			return new WP_Error( 'contacthub_invalid_roles', __( 'Please select at least one permitted role.', 'contacthub' ) );
		}
		if ( is_multisite() && is_super_admin( $user->ID ) && ! is_super_admin() ) {
			return new WP_Error( 'contacthub_super_admin', __( 'This super administrator account cannot be changed.', 'contacthub' ) );
		}
		if ( get_current_user_id() === (int) $user->ID && in_array( 'administrator', (array) $user->roles, true ) && ! in_array( 'administrator', $roles, true ) ) {
			return new WP_Error( 'contacthub_self_admin', __( 'You cannot remove your own administrator role here.', 'contacthub' ) );
		}
		if ( in_array( 'administrator', (array) $user->roles, true ) && ! in_array( 'administrator', $roles, true ) ) {
			$admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) );
			if ( count( $admins ) <= 1 ) {
				return new WP_Error( 'contacthub_last_admin', __( 'The role of the last administrator cannot be removed.', 'contacthub' ) );
			}
		}
		$user->set_role( array_shift( $roles ) );
		foreach ( $roles as $role ) {
			$user->add_role( $role );
		}
		return true;
	}

	private static function log_contact_note( $contact_id, $summary, $content ) {
		if ( ContactHub_Store::standalone() ) {
			return ContactHub_Store::add_activity( $contact_id, 0, 'note', $summary, $content );
		}
		if ( ! class_exists( '\\Groundhogg\\Contact' ) ) {
			return false;
		}
		try {
			$contact = new \Groundhogg\Contact( $contact_id );
			return $contact->add_note( wp_kses_post( $content ), 'user', get_current_user_id(), array( 'summary' => sanitize_text_field( $summary ) ) );
		} catch ( Throwable $e ) {
			return false;
		}
	}

	private static function render_contact_activities( $contact_id, $standalone = true ) {
		if ( ContactHub_Store::standalone() ) {
			$notes = ContactHub_Store::activities( $contact_id, 0 );
		} else {
			$result = self::execute_ability( 'groundhogg/list-contact-notes', array( 'contact_id' => $contact_id, 'limit' => 20, 'offset' => 0 ) );
			$notes = is_array( $result ) && isset( $result['notes'] ) && is_array( $result['notes'] ) ? $result['notes'] : array();
		}
		if ( $standalone ) {
			echo '<section class="contacthub-card">';
		}
		echo __( '<h2>Activities <span class="contacthub-count">', 'contacthub' ) . esc_html( count( $notes ) ) . '</span></h2><div class="contacthub-timeline">';
		if ( ! $notes ) {
			echo __( '<div><span></span><strong>No contact notes yet</strong><p>Groundhogg currently provides no notes for this person.</p></div>', 'contacthub' );
		} else {
			foreach ( $notes as $note ) {
				echo '<div><span></span><strong>' . esc_html( self::value( $note, array( 'summary', 'title' ), __( 'Contact note', 'contacthub' ) ) ) . '</strong><p>' . esc_html( wp_strip_all_tags( self::value( $note, array( 'content', 'note' ) ) ) ) . '</p>';
				$date = self::value( $note, array( 'date_created', 'created_at' ) );
				if ( $date ) {
					echo '<small>' . esc_html( $date ) . '</small>';
				}
				echo '</div>';
			}
		}
		echo '</div>';
		if ( $standalone ) {
			echo '</section>';
		}
	}

	private static function contacts( $company ) {
		if ( is_array( $company ) && isset( $company['contacts'] ) && is_array( $company['contacts'] ) ) {
			return $company['contacts'];
		}
		foreach ( array( 'get_contacts', 'contacts' ) as $method ) {
			if ( is_object( $company ) && method_exists( $company, $method ) ) {
				$result = $company->{$method}();
				return is_array( $result ) ? $result : array();
			}
		}
		return array();
	}

	private static function primary_user( $company, $contacts ) {
		$primary_id = absint( self::value( $company, array( 'primary_contact_id' ) ) );
		$ordered = $contacts;
		if ( $primary_id ) {
			usort( $ordered, static function ( $a, $b ) use ( $primary_id ) {
				return ( absint( self::value( $b, array( 'id', 'ID' ) ) ) === $primary_id ) <=> ( absint( self::value( $a, array( 'id', 'ID' ) ) ) === $primary_id );
			} );
		}
		foreach ( $ordered as $contact ) {
			$user = self::contact_user( $contact );
			if ( $user ) {
				return $user;
			}
		}
		return false;
	}

	private static function company_primary_user( $company_id ) {
		$company = self::company( $company_id );
		return $company ? self::primary_user( $company, self::contacts( $company ) ) : false;
	}

	private static function normalize_vat( $vat ) {
		$vat = strtoupper( sanitize_text_field( $vat ) );
		return preg_replace( '/[^A-Z0-9]/', '', $vat );
	}

	private static function phone_countries() {
		return array(
			array( 'country' => 'AT', 'label' => __( 'Austria', 'contacthub' ), 'code' => '+43' ),
			array( 'country' => 'DE', 'label' => 'Deutschland', 'code' => '+49' ),
			array( 'country' => 'CH', 'label' => 'Schweiz', 'code' => '+41' ),
			array( 'country' => 'IT', 'label' => 'Italien / Vatikan', 'code' => '+39' ),
			array( 'country' => 'AL', 'label' => 'Albanien', 'code' => '+355' ),
			array( 'country' => 'AD', 'label' => 'Andorra', 'code' => '+376' ),
			array( 'country' => 'BE', 'label' => 'Belgien', 'code' => '+32' ),
			array( 'country' => 'BA', 'label' => 'Bosnien und Herzegowina', 'code' => '+387' ),
			array( 'country' => 'BG', 'label' => 'Bulgarien', 'code' => '+359' ),
			array( 'country' => 'DK', 'label' => __( 'Denmark', 'contacthub' ), 'code' => '+45' ),
			array( 'country' => 'EE', 'label' => 'Estland', 'code' => '+372' ),
			array( 'country' => 'FI', 'label' => 'Finnland', 'code' => '+358' ),
			array( 'country' => 'FR', 'label' => 'Frankreich', 'code' => '+33' ),
			array( 'country' => 'FO', 'label' => __( 'Faroe Islands', 'contacthub' ), 'code' => '+298' ),
			array( 'country' => 'GI', 'label' => 'Gibraltar', 'code' => '+350' ),
			array( 'country' => 'GR', 'label' => 'Griechenland', 'code' => '+30' ),
			array( 'country' => 'GB', 'label' => __( 'United Kingdom', 'contacthub' ), 'code' => '+44' ),
			array( 'country' => 'IE', 'label' => 'Irland', 'code' => '+353' ),
			array( 'country' => 'IS', 'label' => 'Island', 'code' => '+354' ),
			array( 'country' => 'XK', 'label' => 'Kosovo', 'code' => '+383' ),
			array( 'country' => 'HR', 'label' => 'Kroatien', 'code' => '+385' ),
			array( 'country' => 'LV', 'label' => 'Lettland', 'code' => '+371' ),
			array( 'country' => 'LI', 'label' => 'Liechtenstein', 'code' => '+423' ),
			array( 'country' => 'LT', 'label' => 'Litauen', 'code' => '+370' ),
			array( 'country' => 'LU', 'label' => 'Luxemburg', 'code' => '+352' ),
			array( 'country' => 'MT', 'label' => 'Malta', 'code' => '+356' ),
			array( 'country' => 'MD', 'label' => 'Moldau', 'code' => '+373' ),
			array( 'country' => 'MC', 'label' => 'Monaco', 'code' => '+377' ),
			array( 'country' => 'ME', 'label' => 'Montenegro', 'code' => '+382' ),
			array( 'country' => 'NL', 'label' => 'Niederlande', 'code' => '+31' ),
			array( 'country' => 'MK', 'label' => 'Nordmazedonien', 'code' => '+389' ),
			array( 'country' => 'NO', 'label' => 'Norwegen', 'code' => '+47' ),
			array( 'country' => 'PL', 'label' => 'Polen', 'code' => '+48' ),
			array( 'country' => 'PT', 'label' => 'Portugal', 'code' => '+351' ),
			array( 'country' => 'RO', 'label' => __( 'Romania', 'contacthub' ), 'code' => '+40' ),
			array( 'country' => 'RU', 'label' => 'Russland', 'code' => '+7' ),
			array( 'country' => 'SM', 'label' => 'San Marino', 'code' => '+378' ),
			array( 'country' => 'SE', 'label' => 'Schweden', 'code' => '+46' ),
			array( 'country' => 'RS', 'label' => 'Serbien', 'code' => '+381' ),
			array( 'country' => 'SK', 'label' => 'Slowakei', 'code' => '+421' ),
			array( 'country' => 'SI', 'label' => 'Slowenien', 'code' => '+386' ),
			array( 'country' => 'ES', 'label' => 'Spanien', 'code' => '+34' ),
			array( 'country' => 'CZ', 'label' => 'Tschechien', 'code' => '+420' ),
			array( 'country' => 'TR', 'label' => __( 'Türkiye', 'contacthub' ), 'code' => '+90' ),
			array( 'country' => 'UA', 'label' => 'Ukraine', 'code' => '+380' ),
			array( 'country' => 'HU', 'label' => 'Ungarn', 'code' => '+36' ),
			array( 'country' => 'BY', 'label' => 'Belarus', 'code' => '+375' ),
			array( 'country' => 'CY', 'label' => 'Zypern', 'code' => '+357' ),
		);
	}

	private static function normalize_phone( $phone ) {
		$phone = trim( (string) $phone );
		if ( '' === $phone ) {
			return '';
		}
		$phone = preg_replace( '/[^0-9+]/', '', $phone );
		if ( 0 === strpos( $phone, '00' ) ) {
			$phone = '+' . substr( $phone, 2 );
		}
		if ( '+' !== substr( $phone, 0, 1 ) ) {
			$national = preg_replace( '/\D/', '', $phone );
			$national = 0 === strpos( $national, '0' ) ? substr( $national, 1 ) : $national;
			return $national ? '+43' . $national : '';
		}
		$digits = preg_replace( '/\D/', '', substr( $phone, 1 ) );
		$normalized = '+' . $digits;
		$countries = self::phone_countries();
		usort( $countries, static function ( $a, $b ) { return strlen( $b['code'] ) <=> strlen( $a['code'] ); } );
		foreach ( $countries as $country ) {
			if ( 0 === strpos( $normalized, $country['code'] ) ) {
				$national = substr( $normalized, strlen( $country['code'] ) );
				if ( '+39' !== $country['code'] && 0 === strpos( $national, '0' ) ) {
					$national = substr( $national, 1 );
				}
				return $country['code'] . $national;
			}
		}
		return $normalized;
	}

	private static function phone_country_code( $phone ) {
		$phone = self::normalize_phone( $phone );
		$countries = self::phone_countries();
		usort( $countries, static function ( $a, $b ) { return strlen( $b['code'] ) <=> strlen( $a['code'] ); } );
		foreach ( $countries as $country ) {
			if ( 0 === strpos( $phone, $country['code'] ) ) {
				return $country['code'];
			}
		}
		return '';
	}

	private static function format_phone( $phone ) {
		$phone = self::normalize_phone( $phone );
		if ( '' === $phone ) {
			return '—';
		}
		$country_code = self::phone_country_code( $phone );
		if ( ! $country_code ) {
			return $phone;
		}
		$national = substr( $phone, strlen( $country_code ) );
		if ( '+43' !== $country_code ) {
			return trim( $country_code . ' ' . $national );
		}
		$prefix = '';
		foreach ( array( '316', '463', '512', '662', '732' ) as $known ) {
			if ( 0 === strpos( $national, $known ) ) {
				$prefix = $known;
				break;
			}
		}
		if ( ! $prefix && preg_match( '/^(65[0-9]|66[0-9]|67[0-9]|68[0-9]|69[0-9]|710|720|730|740|780|800|810|820|828|900|901|930|931|939)/', $national, $match ) ) {
			$prefix = $match[1];
		}
		if ( ! $prefix && 0 === strpos( $national, '1' ) ) {
			$prefix = '1';
		}
		if ( ! $prefix && preg_match( '/^[2-7][0-9]{3}/', $national, $match ) ) {
			$prefix = $match[0];
		}
		return $prefix ? '+43 ' . $prefix . ' ' . substr( $national, strlen( $prefix ) ) : '+43 ' . $national;
	}

	private static function valid_vat_format( $vat ) {
		$vat = self::normalize_vat( $vat );
		$eu_prefixes = array( 'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI' );
		return (bool) preg_match( '/^[A-Z]{2}[A-Z0-9]{2,12}$/', $vat ) && in_array( substr( $vat, 0, 2 ), $eu_prefixes, true );
	}

	private static function vat_user_from_request() {
		$company_id = isset( $_POST['company_id'] ) ? absint( $_POST['company_id'] ) : 0;
		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		if ( $company_id ) {
			return self::company_primary_user( $company_id );
		}
		if ( $contact_id ) {
			$contact = self::contact( $contact_id );
			return $contact ? self::contact_user( $contact ) : false;
		}
		return false;
	}

	private static function guard_ajax() {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'contacthub' ) ), 403 );
		}
		check_ajax_referer( 'contacthub_update_company', 'nonce' );
	}

	private static function contact_event_summaries( $contacts ) {
		$summaries = array();
		foreach ( $contacts as $contact ) {
			$contact_id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
			if ( $contact_id ) {
				$summaries[ $contact_id ] = array( 'visited' => 0 );
			}
		}
		return (array) apply_filters( 'contacthub_event_summaries', $summaries, $contacts );
	}

	private static function contact_broadcasts( $contact_id ) {
		$contact_id = absint( $contact_id );
		if ( ContactHub_Store::standalone() ) return (array) apply_filters( 'contacthub_contact_mailings', array(), $contact_id );
		if ( ! $contact_id || ! function_exists( '\\Groundhogg\\get_db' ) || ! class_exists( '\\Groundhogg\\Broadcast' ) ) {
			return (array) apply_filters( 'contacthub_contact_mailings', array(), $contact_id );
		}
		try {
			$events = \Groundhogg\get_db( 'events' )->query(
				array(
					'contact_id' => $contact_id,
					'funnel_id'  => 1,
					'event_type' => 2,
					'status'     => 'complete',
					'limit'      => 200,
					'orderby'    => 'time',
					'order'      => 'DESC',
					'found_rows' => false,
				)
			);
			$activity = \Groundhogg\get_db( 'activity' )->query(
				array(
					'contact_id'    => $contact_id,
					'funnel_id'     => 1,
					'activity_type' => array( 'email_opened', 'email_link_click' ),
					'limit'         => 500,
					'orderby'       => 'timestamp',
					'order'         => 'DESC',
					'found_rows'    => false,
				)
			);
		} catch ( Throwable $e ) {
			return (array) apply_filters( 'contacthub_contact_mailings', array(), $contact_id );
		}

		$engagement = array();
		foreach ( is_array( $activity ) ? $activity : array() as $item ) {
			$step_id = absint( self::value( $item, array( 'step_id' ) ) );
			$type = self::value( $item, array( 'activity_type', 'type' ) );
			if ( ! $step_id ) {
				continue;
			}
			if ( ! isset( $engagement[ $step_id ] ) ) {
				$engagement[ $step_id ] = array( 'opened' => false, 'clicked' => false );
			}
			if ( 'email_opened' === $type ) {
				$engagement[ $step_id ]['opened'] = true;
			}
			if ( 'email_link_click' === $type ) {
				$engagement[ $step_id ]['clicked'] = true;
				$engagement[ $step_id ]['opened'] = true;
			}
		}

		$broadcasts = array();
		foreach ( is_array( $events ) ? $events : array() as $event ) {
			$broadcast_id = absint( self::value( $event, array( 'step_id' ) ) );
			if ( ! $broadcast_id || isset( $broadcasts[ $broadcast_id ] ) ) {
				continue;
			}
			try {
				$broadcast = new \Groundhogg\Broadcast( $broadcast_id );
				if ( ! $broadcast->exists() || ! $broadcast->is_email() ) {
					continue;
				}
				$timestamp = absint( self::value( $event, array( 'time' ) ) );
				if ( ! $timestamp ) {
					$timestamp = $broadcast->get_send_time();
				}
				$tracking = isset( $engagement[ $broadcast_id ] ) ? $engagement[ $broadcast_id ] : array( 'opened' => false, 'clicked' => false );
				$broadcasts[ $broadcast_id ] = array(
					'id'        => $broadcast_id,
					'title'     => $broadcast->get_title(),
					'timestamp' => $timestamp,
					'date'      => $timestamp ? wp_date( 'd.m.Y H:i', $timestamp ) : '—',
					'opened'    => ! empty( $tracking['opened'] ),
					'clicked'   => ! empty( $tracking['clicked'] ),
					'delivered' => true,
					'source'    => __( 'Groundhogg', 'contacthub' ),
				);
			} catch ( Throwable $e ) {
				continue;
			}
		}
		usort( $broadcasts, static function ( $left, $right ) { return $right['timestamp'] <=> $left['timestamp']; } );
		return array_values( (array) apply_filters( 'contacthub_contact_mailings', array_values( $broadcasts ), $contact_id ) );
	}

	private static function contact_events( $contact ) {
		return (array) apply_filters( 'contacthub_contact_events', array(), $contact );
	}

	private static function orders( $contacts ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$emails = array();
		$user_ids = array();
		foreach ( $contacts as $contact ) {
			$emails[] = self::value( $contact, array( 'email', 'get_email' ) );
			$user = self::contact_user( $contact );
			if ( $user ) {
				$user_ids[] = (int) $user->ID;
				$emails[] = $user->user_email;
				$emails[] = get_user_meta( $user->ID, 'billing_email', true );
			}
		}
		$emails = array_values( array_unique( array_filter( array_map( 'sanitize_email', $emails ) ) ) );
		$user_ids = array_values( array_unique( array_filter( array_map( 'absint', $user_ids ) ) ) );
		if ( ! $emails && ! $user_ids ) {
			return array();
		}
		$orders = array();
		foreach ( $user_ids as $user_id ) {
			try {
				$found = wc_get_orders( array( 'customer_id' => $user_id, 'limit' => 25, 'orderby' => 'date', 'order' => 'DESC' ) );
			} catch ( Throwable $e ) {
				$found = array();
			}
			foreach ( $found as $order ) {
				$orders[ $order->get_id() ] = $order;
			}
		}
		foreach ( $emails as $email ) {
			try {
				$found = wc_get_orders( array( 'billing_email' => $email, 'limit' => 25, 'orderby' => 'date', 'order' => 'DESC' ) );
			} catch ( Throwable $e ) {
				$found = array();
			}
			foreach ( $found as $order ) {
				$orders[ $order->get_id() ] = $order;
			}
		}
		usort( $orders, static function ( $a, $b ) {
			$a_date = $a->get_date_created();
			$b_date = $b->get_date_created();
			return ( $b_date ? $b_date->getTimestamp() : 0 ) <=> ( $a_date ? $a_date->getTimestamp() : 0 );
		} );
		return array_slice( $orders, 0, 25 );
	}

	private static function empty_order_summary() {
		return array(
			'count'           => 0,
			'total'           => 0.0,
			'formatted_total' => function_exists( 'wc_price' ) ? wc_price( 0 ) : '—',
		);
	}

	private static function contact_order_summaries( $contacts ) {
		$summaries = array();
		$user_map = array();
		$email_map = array();

		foreach ( $contacts as $contact ) {
			$contact_id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
			if ( ! $contact_id ) {
				continue;
			}
			$summaries[ $contact_id ] = self::empty_order_summary();
			$summaries[ $contact_id ]['order_ids'] = array();

			$user = self::contact_user( $contact );
			if ( $user ) {
				$user_map[ (int) $user->ID ][] = $contact_id;
			}
			$emails = array( self::value( $contact, array( 'email', 'get_email' ) ) );
			if ( $user ) {
				$emails[] = $user->user_email;
				$emails[] = get_user_meta( $user->ID, 'billing_email', true );
			}
			foreach ( $emails as $email ) {
				$email = strtolower( sanitize_email( $email ) );
				if ( $email ) {
					$email_map[ $email ][] = $contact_id;
				}
			}
		}

		if ( ! $summaries || ! function_exists( 'wc_get_orders' ) || ( ! $user_map && ! $email_map ) ) {
			return $summaries;
		}

		$args = array(
			'limit'    => 250,
			'paginate' => true,
			'return'   => 'objects',
			'status'   => self::summary_order_statuses(),
		);
		$filtered_query = false;
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$field_query = array( 'relation' => 'OR' );
			if ( $user_map ) {
				$field_query[] = array( 'field' => 'customer_id', 'value' => array_keys( $user_map ), 'compare' => 'IN' );
			}
			if ( $email_map ) {
				$field_query[] = array( 'field' => 'billing_email', 'value' => array_keys( $email_map ), 'compare' => 'IN' );
			}
			$args['field_query'] = $field_query;
			$filtered_query = true;
		}

		try {
			self::accumulate_order_summaries( $args, $user_map, $email_map, $summaries );
		} catch ( Throwable $e ) {
			if ( $filtered_query ) {
				unset( $args['field_query'] );
				try {
					self::accumulate_order_summaries( $args, $user_map, $email_map, $summaries );
				} catch ( Throwable $fallback_error ) {
					// Keep the list available even when WooCommerce cannot return the summary.
				}
			}
		}

		foreach ( $summaries as &$summary ) {
			$summary['count'] = count( $summary['order_ids'] );
			$summary['formatted_total'] = function_exists( 'wc_price' ) ? wc_price( $summary['total'] ) : (string) $summary['total'];
			unset( $summary['order_ids'] );
		}
		unset( $summary );
		return $summaries;
	}

	private static function summary_order_statuses() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}
		return array_values( array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-cancelled', 'wc-failed', 'wc-checkout-draft' ) ) );
	}

	private static function accumulate_order_summaries( $args, $user_map, $email_map, &$summaries ) {
		$page = 1;
		do {
			$args['paged'] = $page;
			$result = wc_get_orders( $args );
			$orders = is_object( $result ) && isset( $result->orders ) ? $result->orders : ( is_array( $result ) ? $result : array() );
			$max_pages = is_object( $result ) && isset( $result->max_num_pages ) ? max( 1, absint( $result->max_num_pages ) ) : 1;

			foreach ( $orders as $order ) {
				$matched_contacts = array();
				$customer_id = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;
				$email = method_exists( $order, 'get_billing_email' ) ? strtolower( sanitize_email( $order->get_billing_email() ) ) : '';
				if ( $customer_id && isset( $user_map[ $customer_id ] ) ) {
					$matched_contacts = array_merge( $matched_contacts, $user_map[ $customer_id ] );
				}
				if ( $email && isset( $email_map[ $email ] ) ) {
					$matched_contacts = array_merge( $matched_contacts, $email_map[ $email ] );
				}
				$matched_contacts = array_unique( array_map( 'absint', $matched_contacts ) );
				if ( ! $matched_contacts ) {
					continue;
				}

				$order_id = (int) $order->get_id();
				$order_total = (float) $order->get_total();
				if ( method_exists( $order, 'get_total_refunded' ) ) {
					$order_total -= (float) $order->get_total_refunded();
				}
				$order_total = max( 0, $order_total );
				foreach ( $matched_contacts as $contact_id ) {
					if ( ! isset( $summaries[ $contact_id ] ) || isset( $summaries[ $contact_id ]['order_ids'][ $order_id ] ) ) {
						continue;
					}
					$summaries[ $contact_id ]['order_ids'][ $order_id ] = true;
					$summaries[ $contact_id ]['total'] += $order_total;
				}
			}
			$page++;
		} while ( $page <= $max_pages );
	}

	private static function render_activities( $company_id ) {
		if ( ContactHub_Store::standalone() ) {
			$notes = ContactHub_Store::activities( 0, $company_id );
		} else {
			$result = self::execute_ability(
			'groundhogg-companies/list-company-notes',
			array( 'company_id' => $company_id, 'limit' => 20, 'offset' => 0 )
			);
			$notes = is_array( $result ) && isset( $result['notes'] ) && is_array( $result['notes'] ) ? $result['notes'] : array();
		}
		echo __( '<section class="contacthub-card"><h2>Activities <span class="contacthub-count">', 'contacthub' ) . esc_html( count( $notes ) ) . '</span></h2><div class="contacthub-timeline">';
		if ( ! $notes ) {
				echo __( '<div><span></span><strong>No company notes yet</strong><p>Groundhogg currently provides no notes for this company.</p></div>', 'contacthub' );
		} else {
			foreach ( $notes as $note ) {
				$title = self::value( $note, array( 'summary', 'title' ), __( 'Company note', 'contacthub' ) );
				$content = self::value( $note, array( 'content', 'note' ) );
				$date = self::value( $note, array( 'date_created', 'created_at' ) );
				echo '<div><span></span><strong>' . esc_html( $title ) . '</strong><p>' . esc_html( wp_strip_all_tags( $content ) ) . '</p>';
				if ( $date ) {
					echo '<small>' . esc_html( $date ) . '</small>';
				}
				echo '</div>';
			}
		}
		echo '</div></section>';
	}

	private static function execute_ability( $name, $input ) {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return false;
		}
		$ability = wp_get_ability( $name );
		if ( ! $ability ) {
			return false;
		}
		$result = $ability->execute( $input );
		return is_wp_error( $result ) ? false : $result;
	}

	private static function billing( $order ) {
		return self::address( $order, 'billing' );
	}

	private static function address( $order, $type ) {
		$result = array();
		$type = 'shipping' === $type ? 'shipping' : 'billing';
		foreach ( array( 'company', 'first_name', 'last_name', 'address_1', 'address_2', 'postcode', 'city', 'country' ) as $key ) {
			$method = 'get_' . $type . '_' . $key;
			$result[ $key ] = method_exists( $order, $method ) ? $order->{$method}() : '';
		}
		return $result;
	}

	private static function customer_address( $customer, $type ) {
		$result = array();
		$type = 'shipping' === $type ? 'shipping' : 'billing';
		$fields = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' );
		if ( 'billing' === $type ) {
			$fields[] = 'email';
			$fields[] = 'phone';
		}
		foreach ( $fields as $field ) {
			$getter = 'get_' . $type . '_' . $field;
			$result[ $field ] = method_exists( $customer, $getter ) ? $customer->{$getter}() : '';
		}
		return $result;
	}

	private static function contact_address( $contact ) {
		$result = array();
		foreach ( array( 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' ) as $field ) {
			$aliases = array( $field );
			if ( 'address_1' === $field ) {
				$aliases[] = 'street_address_1';
				$aliases[] = 'street_address';
			}
			if ( 'address_2' === $field ) {
				$aliases[] = 'street_address_2';
			}
			if ( 'postcode' === $field ) {
				$aliases[] = 'postal_zip';
				$aliases[] = 'postal_code';
			}
			if ( 'state' === $field ) {
				$aliases[] = 'region';
			}
			$result[ $field ] = self::meta( $contact, $aliases );
		}
		$result['country'] = strtoupper( $result['country'] );
		return $result;
	}

	private static function contact_countries() {
		return array(
			'AT' => __( 'Austria', 'contacthub' ),
			'DE' => __( 'Germany', 'contacthub' ),
			'CH' => __( 'Switzerland', 'contacthub' ),
			'IT' => __( 'Italy', 'contacthub' ),
			'FR' => __( 'France', 'contacthub' ),
			'NL' => __( 'Netherlands', 'contacthub' ),
			'BE' => __( 'Belgium', 'contacthub' ),
			'CZ' => __( 'Czechia', 'contacthub' ),
			'SK' => __( 'Slovakia', 'contacthub' ),
			'HU' => __( 'Hungary', 'contacthub' ),
			'SI' => __( 'Slovenia', 'contacthub' ),
			'HR' => __( 'Croatia', 'contacthub' ),
		);
	}

	private static function format_address( $address ) {
		$street = trim( (string) ( $address['address_1'] ?? '' ) . ' ' . (string) ( $address['address_2'] ?? '' ) );
		$place = implode( ', ', array_filter( array( trim( (string) ( $address['postcode'] ?? '' ) . ' ' . (string) ( $address['city'] ?? '' ) ), $address['state'] ?? '', $address['country'] ?? '' ) ) );
		return implode( "\n", array_filter( array( $street, $place ) ) );
	}

	private static function person_primary_address( $customer, $contact ) {
		$address = self::contact_address( $contact );
		if ( ! array_filter( $address ) && $customer ) {
			$address = self::customer_address( $customer, 'billing' );
		}
		return self::format_address( $address );
	}

	private static function phone_input_html( $phone ) {
		$phone = self::normalize_phone( $phone );
		$country_code = self::phone_country_code( $phone );
		if ( ! $country_code ) {
			$country_code = '+43';
		}
		$national = $phone && 0 === strpos( $phone, $country_code ) ? substr( $phone, strlen( $country_code ) ) : '';
		$html = __( '<span class="contacthub-phone-input-group"><select class="contacthub-phone-prefix" aria-label="Country calling code">', 'contacthub' );
		foreach ( self::phone_countries() as $country ) {
			$html .= '<option value="' . esc_attr( $country['code'] ) . '"' . selected( $country_code, $country['code'], false ) . '>' . esc_html( $country['label'] . ' ' . $country['code'] ) . '</option>';
		}
		$html .= '</select><input type="tel" class="contacthub-phone-national" value="' . esc_attr( $national ) . __( '" placeholder="Phone number"></span>', 'contacthub' );
		return $html;
	}

	private static function meta( $object, $keys ) {
		if ( is_array( $object ) && isset( $object['meta'] ) && is_array( $object['meta'] ) ) {
			foreach ( $keys as $key ) {
				if ( isset( $object['meta'][ $key ] ) && '' !== (string) $object['meta'][ $key ] ) {
					return $object['meta'][ $key ];
				}
			}
		}
		if ( ! is_object( $object ) || ! method_exists( $object, 'get_meta' ) ) {
			return '';
		}
		foreach ( $keys as $key ) {
			$value = $object->get_meta( $key );
			if ( '' !== (string) $value ) {
				return $value;
			}
		}
		return '';
	}

	private static function value( $source, $candidates, $default = '' ) {
		foreach ( $candidates as $candidate ) {
			if ( is_object( $source ) && method_exists( $source, $candidate ) ) {
				$value = $source->{$candidate}();
			} elseif ( is_object( $source ) && method_exists( $source, 'get_' . $candidate ) ) {
				$getter = 'get_' . $candidate;
				$value = $source->{$getter}();
			} elseif ( is_object( $source ) && isset( $source->{$candidate} ) ) {
				$value = $source->{$candidate};
			} elseif ( is_array( $source ) && isset( $source[ $candidate ] ) ) {
				$value = $source[ $candidate ];
			} else {
				continue;
			}
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				return (string) $value;
			}
		}
		return $default;
	}

	private static function field( $label, $value ) {
		$display = '' !== (string) $value ? nl2br( esc_html( $value ) ) : '—';
		echo '<div class="contacthub-field"><span>' . esc_html( $label ) . '</span><strong>' . wp_kses( $display, array( 'br' => array() ) ) . '</strong></div>';
	}

	private static function salutation_label( $value ) {
		$labels = array( 'mr' => __( 'Mr', 'contacthub' ), 'ms' => __( 'Ms', 'contacthub' ), 'family' => __( 'Family', 'contacthub' ), 'neutral' => __( 'Neutral', 'contacthub' ) );
		return $labels[ sanitize_key( $value ) ] ?? '—';
	}

	private static function salutation_field( $value, $first_name, $contact_id ) {
		$suggested = ContactHub_Store::suggested_salutation( $first_name );
		$value = sanitize_key( $value );
		echo '<div class="contacthub-field contacthub-salutation-field" data-contact="' . esc_attr( $contact_id ) . '"><span>' . esc_html__( 'Salutation', 'contacthub' ) . '</span><select class="contacthub-salutation-select">';
		echo '<option value=""' . selected( $value, '', false ) . '>' . esc_html__( '--- Please select ---', 'contacthub' ) . '</option>';
		foreach ( array( 'neutral', 'mr', 'ms', 'family' ) as $option ) echo '<option value="' . esc_attr( $option ) . '"' . selected( $value, $option, false ) . '>' . esc_html( self::salutation_label( $option ) ) . '</option>';
		echo '</select>';
		if ( $suggested && $suggested !== $value ) echo '<button type="button" class="button-link contacthub-salutation-suggestion" data-value="' . esc_attr( $suggested ) . '">' . esc_html( sprintf( __( 'Suggestion: %s', 'contacthub' ), self::salutation_label( $suggested ) ) ) . '</button>';
		echo '<div class="contacthub-field-message" aria-live="polite"></div></div>';
	}

	private static function editable_field( $label, $value, $field, $company_id, $multiline = false ) {
		echo '<div class="contacthub-field contacthub-editable" data-entity="company" data-company="' . esc_attr( $company_id ) . '" data-field="' . esc_attr( $field ) . '" data-multiline="' . ( $multiline ? '1' : '0' ) . '">';
		echo '<span>' . esc_html( $label ) . '</span><div class="contacthub-field-row"><strong class="contacthub-field-value" data-value="' . esc_attr( $value ) . '">' . esc_html( '' !== (string) $value ? $value : '—' ) . '</strong>';
		echo '<button type="button" class="button-link contacthub-edit" aria-label="' . esc_attr( sprintf( __( 'Edit %s', 'contacthub' ), $label ) ) . '"><span class="dashicons dashicons-edit"></span></button></div>';
		echo '<div class="contacthub-field-message" aria-live="polite"></div></div>';
	}

	private static function contact_editable_field( $label, $value, $field, $contact_id, $multiline = false ) {
		echo '<div class="contacthub-field contacthub-editable" data-entity="contact" data-contact="' . esc_attr( $contact_id ) . '" data-field="' . esc_attr( $field ) . '" data-multiline="' . ( $multiline ? '1' : '0' ) . '">';
		echo '<span>' . esc_html( $label ) . '</span><div class="contacthub-field-row"><strong class="contacthub-field-value" data-value="' . esc_attr( $value ) . '">' . esc_html( '' !== (string) $value ? $value : '—' ) . '</strong>';
		if ( 'email' === $field && is_email( $value ) ) {
			echo '<a class="button-link contacthub-mail-link" href="mailto:' . esc_attr( $value ) . '" aria-label="' . esc_attr__( 'Send email', 'contacthub' ) . '" title="' . esc_attr__( 'Send email', 'contacthub' ) . '"><span class="dashicons dashicons-email-alt"></span></a>';
		}
		echo '<button type="button" class="button-link contacthub-edit" aria-label="' . esc_attr( sprintf( __( 'Edit %s', 'contacthub' ), $label ) ) . '"><span class="dashicons dashicons-edit"></span></button></div>';
		echo '<div class="contacthub-field-message" aria-live="polite"></div></div>';
	}

	private static function contact_email_field( $label, $email ) {
		echo '<div class="contacthub-field"><span>' . esc_html( $label ) . '</span><div class="contacthub-field-row"><strong>' . esc_html( $email ?: '—' ) . '</strong>';
		if ( is_email( $email ) ) {
			echo '<a class="button-link contacthub-mail-link" href="mailto:' . esc_attr( $email ) . '" aria-label="' . esc_attr__( 'Send email', 'contacthub' ) . '" title="' . esc_attr__( 'Send email', 'contacthub' ) . '"><span class="dashicons dashicons-email-alt"></span></a>';
		}
		echo '</div></div>';
	}

	private static function editable_address_summary( $label, $address ) {
		echo '<div class="contacthub-field contacthub-address-summary"><span>' . esc_html( $label ) . '</span><div class="contacthub-field-row"><strong class="contacthub-address-summary-value">' . wp_kses( nl2br( esc_html( $address ?: '—' ) ), array( 'br' => array() ) ) . '</strong><button type="button" class="button-link contacthub-edit-address-summary" aria-label="' . esc_attr__( 'Edit address', 'contacthub' ) . '"><span class="dashicons dashicons-edit"></span></button></div><small>' . esc_html__( 'The structured contact address opens for editing.', 'contacthub' ) . '</small></div>';
	}

	private static function phone_field( $label, $value, $field, $id, $entity ) {
		$value = self::normalize_phone( $value );
		$id_attr = 'contact' === $entity ? 'data-contact="' . esc_attr( $id ) . '"' : 'data-company="' . esc_attr( $id ) . '"';
		echo '<div class="contacthub-field contacthub-editable contacthub-phone-field" data-entity="' . esc_attr( $entity ) . '" ' . $id_attr . ' data-field="' . esc_attr( $field ) . '" data-type="phone" data-multiline="0">';
		echo '<span>' . esc_html( $label ) . '</span><div class="contacthub-field-row"><strong class="contacthub-field-value" data-value="' . esc_attr( $value ) . '">' . esc_html( self::format_phone( $value ) ) . '</strong>';
		echo '<button type="button" class="button-link contacthub-edit" aria-label="' . esc_attr( sprintf( __( 'Edit %s', 'contacthub' ), $label ) ) . '"><span class="dashicons dashicons-edit"></span></button></div>';
		echo '<div class="contacthub-field-message" aria-live="polite"></div></div>';
	}

	private static function uid_field( $label, $value, $id, $entity, $verified ) {
		$attr = 'company' === $entity ? 'data-company="' . esc_attr( $id ) . '"' : 'data-contact="' . esc_attr( $id ) . '"';
		echo '<div class="contacthub-field contacthub-editable contacthub-uid-field" data-entity="' . esc_attr( $entity ) . '" ' . $attr . ' data-field="uid" data-multiline="0" data-verified="' . ( $verified ? '1' : '0' ) . '">';
		echo '<span>' . esc_html( $label ) . '</span><div class="contacthub-field-row"><div class="contacthub-value-with-status"><strong class="contacthub-field-value" data-value="' . esc_attr( $value ) . '">' . esc_html( '' !== (string) $value ? $value : '—' ) . __( '</strong><span class="contacthub-vat-check" title="Successfully validated through EU VIES"', 'contacthub' ) . ( $verified ? '' : ' hidden' ) . '>✓</span></div>';
		echo '<button type="button" class="button-link contacthub-edit" aria-label="' . esc_attr( sprintf( __( 'Edit %s', 'contacthub' ), $label ) ) . '"><span class="dashicons dashicons-edit"></span></button></div>';
		echo '<div class="contacthub-field-message" aria-live="polite"></div></div>';
	}

	private static function readonly_uid_field( $label, $value, $verified ) {
		echo '<div class="contacthub-field"><span>' . esc_html( $label ) . '</span><div class="contacthub-value-with-status"><strong>' . esc_html( '' !== (string) $value ? $value : '—' ) . '</strong>';
		if ( $verified ) {
			echo __( '<span class="contacthub-vat-check" title="Successfully validated through EU VIES">✓</span>', 'contacthub' );
		}
		echo '</div></div>';
	}

	private static function render_status() {
		echo __( '<section class="contacthub-card contacthub-status"><h2>System status</h2>', 'contacthub' );
		foreach ( self::dependencies() as $name => $version ) {
			echo '<div><span class="contacthub-dot ' . ( $version ? 'ok' : '' ) . '"></span><strong>' . esc_html( $name ) . '</strong><span>' . esc_html( is_string( $version ) ? $version : ( $version ? __( 'detected', 'contacthub' ) : __( 'not detected', 'contacthub' ) ) ) . '</span></div>';
		}
		echo '</section>';
	}
}

