<?php

defined( 'ABSPATH' ) || exit;

/** Import, merge, shared-library and administrator quality-of-life tools. */
final class ContactHub_Data_Tools {
	const CENTRAL_OPTION = 'contacthub_central_salutation_library';
	const NOTICE_OPTION  = 'contacthub_admin_notice_settings';

	public static function boot() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( 'contacthub_render_migration_tools', array( __CLASS__, 'render_migration_tools' ) );
		add_action( 'contacthub_render_salutation_tools', array( __CLASS__, 'render_central_library' ) );
		add_filter( 'contacthub_misc_settings_sections', array( __CLASS__, 'misc_sections' ) );
		add_action( 'contacthub_render_misc_settings_section_admin_notices', array( __CLASS__, 'render_notice_settings' ) );
		add_action( 'admin_post_contacthub_csv_preview', array( __CLASS__, 'handle_csv_preview' ) );
		add_action( 'admin_post_contacthub_csv_import', array( __CLASS__, 'handle_csv_import' ) );
		add_action( 'admin_post_contacthub_merge_preview', array( __CLASS__, 'handle_merge_preview' ) );
		add_action( 'admin_post_contacthub_merge_commit', array( __CLASS__, 'handle_merge_commit' ) );
		add_action( 'admin_post_contacthub_central_salutations', array( __CLASS__, 'handle_central_settings' ) );
		add_action( 'admin_post_contacthub_sync_central_salutations', array( __CLASS__, 'handle_central_sync' ) );
		add_action( 'admin_post_contacthub_admin_notice_settings', array( __CLASS__, 'handle_notice_settings' ) );
		add_action( 'wp_ajax_contacthub_dismiss_admin_notice', array( __CLASS__, 'dismiss_admin_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_notice_helper' ), 100 );
		add_action( 'contacthub_central_salutation_sync', array( __CLASS__, 'scheduled_central_sync' ) );
	}

	public static function cron_schedules( $schedules ) {
		$schedules['contacthub_weekly'] = array( 'interval' => WEEK_IN_SECONDS, 'display' => __( 'Once weekly', 'contacthub' ) );
		return $schedules;
	}

	public static function misc_sections( $sections ) {
		$sections['admin_notices'] = __( 'Admin notices', 'contacthub' );
		return $sections;
	}

	private static function require_admin( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contacthub' ), '', array( 'response' => 403 ) );
		check_admin_referer( $nonce_action );
	}

	private static function migration_url( $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=contacthub-settings&tab=migration' ) );
	}

	private static function redirect_notice( $type, $message, $args = array() ) {
		set_transient( 'contacthub_database_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), MINUTE_IN_SECONDS );
		wp_safe_redirect( self::migration_url( $args ) );
		exit;
	}

	public static function render_migration_tools() {
		self::render_csv_import();
		self::render_merge_tool();
	}

	private static function render_csv_import() {
		$token = isset( $_GET['csv_token'] ) ? sanitize_key( wp_unslash( $_GET['csv_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- opaque transient reference.
		$preview = $token ? get_transient( 'contacthub_csv_' . get_current_user_id() . '_' . $token ) : false;
		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Import CSV data', 'contacthub' ) . '</h2><p>' . esc_html__( 'Import people or companies from a CSV file. The preview and field mapping are shown before anything is saved. Empty CSV values never erase existing data.', 'contacthub' ) . '</p>';
		$report = get_transient( 'contacthub_csv_report_' . get_current_user_id() );
		if ( is_array( $report ) ) { delete_transient( 'contacthub_csv_report_' . get_current_user_id() ); echo '<div class="contacthub-migration-report"><h3>' . esc_html__( 'Last CSV import', 'contacthub' ) . '</h3><p>' . esc_html( sprintf( __( '%1$d created, %2$d updated, %3$d skipped, %4$d errors.', 'contacthub' ), $report['created'], $report['updated'], $report['skipped'], count( $report['errors'] ) ) ) . '</p>'; if ( $report['errors'] ) { echo '<details><summary>' . esc_html__( 'Show import errors', 'contacthub' ) . '</summary><ul>'; foreach ( array_slice( $report['errors'], 0, 100 ) as $error ) echo '<li>' . esc_html( $error ) . '</li>'; echo '</ul></details>'; } echo '</div>'; }
		if ( ! $preview ) {
			echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contacthub-settings-grid"><input type="hidden" name="action" value="contacthub_csv_preview">';
			wp_nonce_field( 'contacthub_csv_preview' );
			echo '<label><span>' . esc_html__( 'Data type', 'contacthub' ) . '</span><select name="entity"><option value="contacts">' . esc_html__( 'People', 'contacthub' ) . '</option><option value="companies">' . esc_html__( 'Companies', 'contacthub' ) . '</option></select></label>';
			echo '<label><span>' . esc_html__( 'Duplicate handling', 'contacthub' ) . '</span><select name="mode"><option value="fill">' . esc_html__( 'Fill missing values', 'contacthub' ) . '</option><option value="skip">' . esc_html__( 'Skip existing records', 'contacthub' ) . '</option><option value="update">' . esc_html__( 'Update with non-empty CSV values', 'contacthub' ) . '</option></select></label>';
			echo '<label><span>' . esc_html__( 'CSV file', 'contacthub' ) . '</span><input type="file" name="csv_file" accept=".csv,text/csv,text/plain" required></label><p><button class="button button-primary">' . esc_html__( 'Upload and preview', 'contacthub' ) . '</button></p></form>';
			echo '<p class="description">' . esc_html__( 'In Groundhogg frontend mode, people are imported directly into Groundhogg and companies into Groundhogg Companies when available. Source files and external records are never changed.', 'contacthub' ) . '</p></section>';
			return;
		}

		$fields = self::csv_fields( $preview['entity'] );
		echo '<div class="contacthub-migration-report"><h3>' . esc_html__( 'Import preview', 'contacthub' ) . '</h3><p>' . esc_html( sprintf( __( '%1$d data rows detected using “%2$s” as the delimiter. Map the columns before importing.', 'contacthub' ), count( $preview['rows'] ), $preview['delimiter_label'] ) ) . '</p></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_csv_import"><input type="hidden" name="csv_token" value="' . esc_attr( $token ) . '">';
		wp_nonce_field( 'contacthub_csv_import' );
		echo '<table class="widefat striped contacthub-csv-mapping"><thead><tr><th>' . esc_html__( 'ContactHub field', 'contacthub' ) . '</th><th>' . esc_html__( 'CSV column', 'contacthub' ) . '</th></tr></thead><tbody>';
		foreach ( $fields as $key => $label ) {
			$guess = self::guess_header( $key, $preview['headers'] );
			echo '<tr><th>' . esc_html( $label ) . ( in_array( $key, array( 'email', 'name' ), true ) ? ' *' : '' ) . '</th><td><select name="mapping[' . esc_attr( $key ) . ']" class="regular-text"><option value="">' . esc_html__( 'Do not import', 'contacthub' ) . '</option>';
			foreach ( $preview['headers'] as $index => $header ) echo '<option value="' . esc_attr( $index ) . '"' . selected( $guess, $index, false ) . '>' . esc_html( $header ) . '</option>';
			echo '</select></td></tr>';
		}
		echo '</tbody></table><h3>' . esc_html__( 'First rows', 'contacthub' ) . '</h3><div class="contacthub-csv-preview"><table class="widefat striped"><thead><tr>';
		foreach ( $preview['headers'] as $header ) echo '<th>' . esc_html( $header ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( array_slice( $preview['rows'], 0, 10 ) as $row ) { echo '<tr>'; foreach ( $preview['headers'] as $index => $unused ) echo '<td>' . esc_html( $row[ $index ] ?? '' ) . '</td>'; echo '</tr>'; }
		echo '</tbody></table></div><p><label><input type="checkbox" name="confirmed" value="1" required> ' . esc_html__( 'I checked the preview and field mapping.', 'contacthub' ) . '</label></p><p><button class="button button-primary">' . esc_html__( 'Start import', 'contacthub' ) . '</button> <a class="button" href="' . esc_url( self::migration_url() ) . '">' . esc_html__( 'Cancel', 'contacthub' ) . '</a></p></form></section>';
	}

	public static function handle_csv_preview() {
		self::require_admin( 'contacthub_csv_preview' );
		if ( empty( $_FILES['csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) || (int) $_FILES['csv_file']['size'] > 5 * MB_IN_BYTES ) self::redirect_notice( 'error', __( 'Select a CSV file no larger than 5 MB.', 'contacthub' ) );
		$entity = 'companies' === sanitize_key( wp_unslash( $_POST['entity'] ?? '' ) ) ? 'companies' : 'contacts';
		$mode = sanitize_key( wp_unslash( $_POST['mode'] ?? 'fill' ) );
		if ( ! in_array( $mode, array( 'skip', 'fill', 'update' ), true ) ) $mode = 'fill';
		$parsed = self::parse_csv( $_FILES['csv_file']['tmp_name'] );
		if ( is_wp_error( $parsed ) ) self::redirect_notice( 'error', $parsed->get_error_message() );
		$token = wp_generate_password( 12, false, false );
		$parsed['entity'] = $entity; $parsed['mode'] = $mode; $parsed['source_hash'] = hash_file( 'sha256', $_FILES['csv_file']['tmp_name'] );
		set_transient( 'contacthub_csv_' . get_current_user_id() . '_' . $token, $parsed, HOUR_IN_SECONDS );
		wp_safe_redirect( self::migration_url( array( 'csv_token' => $token ) ) ); exit;
	}

	private static function parse_csv( $path ) {
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- protected upload stream.
		if ( ! $handle ) return new WP_Error( 'contacthub_csv_open', __( 'The CSV file could not be read.', 'contacthub' ) );
		$sample = (string) fgets( $handle ); rewind( $handle );
		$counts = array( ',' => substr_count( $sample, ',' ), ';' => substr_count( $sample, ';' ), "\t" => substr_count( $sample, "\t" ) ); arsort( $counts ); $delimiter = (string) key( $counts );
		$headers = fgetcsv( $handle, 0, $delimiter ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv
		if ( ! is_array( $headers ) || count( $headers ) < 1 ) { fclose( $handle ); return new WP_Error( 'contacthub_csv_header', __( 'No CSV header row was detected.', 'contacthub' ) ); }
		$headers = array_map( static function ( $value ) { return sanitize_text_field( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $value ) ); }, $headers );
		$rows = array();
		while ( count( $rows ) < 5000 && false !== ( $row = fgetcsv( $handle, 0, $delimiter ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv
			if ( ! array_filter( $row, static function ( $value ) { return '' !== trim( (string) $value ); } ) ) continue;
			$rows[] = array_map( 'sanitize_text_field', $row );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( ! $rows ) return new WP_Error( 'contacthub_csv_empty', __( 'The CSV file contains no data rows.', 'contacthub' ) );
		return array( 'headers' => $headers, 'rows' => $rows, 'delimiter' => $delimiter, 'delimiter_label' => "\t" === $delimiter ? __( 'tab', 'contacthub' ) : $delimiter );
	}

	private static function csv_fields( $entity ) {
		if ( 'companies' === $entity ) return array( 'name' => __( 'Company name', 'contacthub' ), 'legal_name' => __( 'Legal name', 'contacthub' ), 'email' => __( 'Email', 'contacthub' ), 'phone' => __( 'Phone', 'contacthub' ), 'website' => __( 'Website', 'contacthub' ), 'industry' => __( 'Industry', 'contacthub' ), 'vat_id' => __( 'VAT ID', 'contacthub' ), 'address_1' => __( 'Street address', 'contacthub' ), 'address_2' => __( 'Address line 2', 'contacthub' ), 'postcode' => __( 'Postal code', 'contacthub' ), 'city' => __( 'City', 'contacthub' ), 'country' => __( 'Country', 'contacthub' ) );
		return array( 'email' => __( 'Email', 'contacthub' ), 'first_name' => __( 'First name', 'contacthub' ), 'last_name' => __( 'Last name', 'contacthub' ), 'salutation' => __( 'Salutation', 'contacthub' ), 'title_prefix' => __( 'Title before name', 'contacthub' ), 'title_suffix' => __( 'Title after name', 'contacthub' ), 'phone' => __( 'Phone', 'contacthub' ), 'position' => __( 'Position', 'contacthub' ), 'company' => __( 'Company', 'contacthub' ), 'address_1' => __( 'Street address', 'contacthub' ), 'address_2' => __( 'Address line 2', 'contacthub' ), 'postcode' => __( 'Postal code', 'contacthub' ), 'city' => __( 'City', 'contacthub' ), 'state' => __( 'State', 'contacthub' ), 'country' => __( 'Country', 'contacthub' ), 'tags' => __( 'Tags (comma separated)', 'contacthub' ), 'marketing' => __( 'Marketing consent', 'contacthub' ) );
	}

	private static function guess_header( $field, $headers ) {
		$aliases = array( 'email' => array( 'email', 'e-mail', 'mail' ), 'first_name' => array( 'first name', 'firstname', 'vorname' ), 'last_name' => array( 'last name', 'lastname', 'nachname' ), 'name' => array( 'company', 'company name', 'firma', 'firmenname', 'name' ), 'company' => array( 'company', 'firma' ), 'phone' => array( 'phone', 'telephone', 'telefon' ), 'postcode' => array( 'postcode', 'postal code', 'zip', 'plz' ), 'city' => array( 'city', 'ort', 'stadt' ), 'country' => array( 'country', 'land' ), 'address_1' => array( 'street', 'address', 'adresse', 'strasse', 'straße' ), 'vat_id' => array( 'vat', 'vat id', 'uid', 'uid nummer' ), 'tags' => array( 'tags', 'tag' ), 'marketing' => array( 'marketing', 'newsletter', 'consent', 'zustimmung' ) );
		$needles = $aliases[ $field ] ?? array( str_replace( '_', ' ', $field ) );
		foreach ( $headers as $index => $header ) { $normalized = strtolower( trim( remove_accents( $header ) ) ); if ( in_array( $normalized, $needles, true ) ) return $index; }
		return '';
	}

	public static function handle_csv_import() {
		self::require_admin( 'contacthub_csv_import' );
		if ( '1' !== ( $_POST['confirmed'] ?? '' ) ) self::redirect_notice( 'error', __( 'Confirm the field mapping before importing.', 'contacthub' ) );
		$token = sanitize_key( wp_unslash( $_POST['csv_token'] ?? '' ) );
		$data = get_transient( 'contacthub_csv_' . get_current_user_id() . '_' . $token );
		if ( ! is_array( $data ) ) self::redirect_notice( 'error', __( 'The import preview expired. Upload the CSV file again.', 'contacthub' ) );
		$mapping = isset( $_POST['mapping'] ) && is_array( $_POST['mapping'] ) ? array_map( 'absint', wp_unslash( $_POST['mapping'] ) ) : array();
		$required = 'companies' === $data['entity'] ? 'name' : 'email';
		if ( ! isset( $_POST['mapping'][ $required ] ) || '' === (string) $_POST['mapping'][ $required ] ) self::redirect_notice( 'error', __( 'Map the required identifying field before importing.', 'contacthub' ), array( 'csv_token' => $token ) );
		$report = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );
		foreach ( $data['rows'] as $number => $row ) {
			$record = array(); foreach ( self::csv_fields( $data['entity'] ) as $field => $label ) if ( isset( $_POST['mapping'][ $field ] ) && '' !== (string) $_POST['mapping'][ $field ] ) $record[ $field ] = sanitize_text_field( $row[ $mapping[ $field ] ] ?? '' );
			$result = self::import_csv_record( $data['entity'], $record, $data['mode'], $data['source_hash'] . ':' . ( $number + 2 ) );
			if ( is_wp_error( $result ) ) { $report['errors'][] = sprintf( __( 'Row %1$d: %2$s', 'contacthub' ), $number + 2, $result->get_error_message() ); continue; }
			$report[ $result ]++;
		}
		delete_transient( 'contacthub_csv_' . get_current_user_id() . '_' . $token );
		set_transient( 'contacthub_csv_report_' . get_current_user_id(), $report, HOUR_IN_SECONDS );
		self::redirect_notice( 'success', sprintf( __( 'CSV import completed: %1$d created, %2$d updated, %3$d skipped, %4$d errors.', 'contacthub' ), $report['created'], $report['updated'], $report['skipped'], count( $report['errors'] ) ) );
	}

	private static function import_csv_record( $entity, $record, $mode, $source_id ) {
		if ( 'companies' === $entity ) {
			if ( ! ContactHub_Store::standalone() ) return ContactHub_Plugin::extension_import_company( $record, $mode, $source_id );
			$name = sanitize_text_field( $record['name'] ?? '' ); if ( '' === $name ) return new WP_Error( 'contacthub_csv_name', __( 'Company name is missing.', 'contacthub' ) );
			return ContactHub_Store::import_company_row( $record, $mode, $source_id );
		}
		$email = strtolower( sanitize_email( $record['email'] ?? '' ) ); if ( ! is_email( $email ) ) return new WP_Error( 'contacthub_csv_email', __( 'A valid email address is required.', 'contacthub' ) );
		if ( ! ContactHub_Store::standalone() ) return ContactHub_Plugin::extension_import_contact( $record, $mode, $source_id );
		$current = ContactHub_Store::contact_by_email( $email ); if ( $current && 'skip' === $mode ) return 'skipped';
		$payload = array( 'email' => $email, 'source' => 'csv', 'meta' => array( 'csv_source_id' => $source_id ) );
		$map = array( 'first_name', 'last_name', 'position' ); foreach ( $map as $field ) if ( ! $current || ( ! empty( $record[ $field ] ) && ( 'update' === $mode || empty( $current[ $field ] ) ) ) ) $payload[ $field ] = $record[ $field ] ?? '';
		foreach ( array( 'salutation' => 'anrede', 'title_prefix' => 'titel_vorangestellt', 'title_suffix' => 'titel_nachgestellt', 'phone' => 'primary_phone' ) as $input => $field ) if ( ! empty( $record[ $input ] ) && ( 'update' === $mode || empty( $current['meta'][ $field ] ) ) ) $payload[ $field ] = $record[ $input ];
		$id = $current ? absint( $current['id'] ) : ContactHub_Store::create_contact( $payload ); if ( is_wp_error( $id ) || ! $id ) return new WP_Error( 'contacthub_csv_contact', __( 'Person could not be saved.', 'contacthub' ) );
		if ( $current ) ContactHub_Store::update_contact( $id, $payload );
		$address = array(); foreach ( array( 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' ) as $field ) $address[ $field ] = ! empty( $record[ $field ] ) ? $record[ $field ] : ( $current['meta'][ $field ] ?? '' ); ContactHub_Store::update_contact_address( $id, $address );
		if ( ! empty( $record['company'] ) ) ContactHub_Store::link_contact_company_name( $email, $record['company'] );
		if ( ! empty( $record['tags'] ) ) foreach ( preg_split( '/[,;]+/', $record['tags'] ) as $tag ) { $tag_id = ContactHub_Store::create_tag( trim( $tag ) ); if ( $tag_id ) ContactHub_Store::assign_tag( $id, $tag_id ); }
		$current_status = sanitize_key( (string) ( $current['optin_status'] ?? '' ) );
		$protected_statuses = array( 'unsubscribed', 'hard_bounce', 'bounced', 'spam', 'complained', 'blocked' );
		if ( ! empty( $record['marketing'] ) && in_array( strtolower( $record['marketing'] ), array( '1', 'yes', 'ja', 'true', 'confirmed' ), true ) && ! in_array( $current_status, $protected_statuses, true ) && 'confirmed' !== $current_status ) ContactHub_Store::record_consent( $id, 'granted', 'csv_import', current_time( 'Y-m-d' ), __( 'Imported from a confirmed CSV field.', 'contacthub' ) );
		return $current ? 'updated' : 'created';
	}

	private static function render_merge_tool() {
		if ( ! ContactHub_Store::standalone() ) { echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Merge duplicates', 'contacthub' ) . '</h2><p>' . esc_html__( 'Record merging is available in standalone ContactHub mode. Groundhogg records must be merged in Groundhogg so its source identifiers remain authoritative.', 'contacthub' ) . '</p></section>'; return; }
		$token = isset( $_GET['merge_token'] ) ? sanitize_key( wp_unslash( $_GET['merge_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- opaque transient reference.
		$state = $token ? get_transient( 'contacthub_merge_' . get_current_user_id() . '_' . $token ) : false;
		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Merge duplicates', 'contacthub' ) . '</h2><p>' . esc_html__( 'Select two or more fragments, choose the primary record, and resolve conflicting fields. Related tags, consents, notes, and company links are retained; source fragments are moved to Trash.', 'contacthub' ) . '</p>';
		if ( $state ) { self::render_merge_preview( $token, $state ); echo '</section>'; return; }
		$entity = isset( $_GET['merge_entity'] ) && 'companies' === $_GET['merge_entity'] ? 'companies' : 'contacts'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['merge_search'] ) ? sanitize_text_field( wp_unslash( $_GET['merge_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<form method="get" class="contacthub-search"><input type="hidden" name="page" value="contacthub-settings"><input type="hidden" name="tab" value="migration"><select name="merge_entity"><option value="contacts"' . selected( $entity, 'contacts', false ) . '>' . esc_html__( 'People', 'contacthub' ) . '</option><option value="companies"' . selected( $entity, 'companies', false ) . '>' . esc_html__( 'Companies', 'contacthub' ) . '</option></select><input type="search" name="merge_search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search records', 'contacthub' ) . '"><button class="button">' . esc_html__( 'Search', 'contacthub' ) . '</button></form>';
		if ( '' === $search ) { echo '<p class="description">' . esc_html__( 'Enter a name, company, or email fragment to find records to merge.', 'contacthub' ) . '</p></section>'; return; }
		$records = 'contacts' === $entity ? ContactHub_Store::search_contacts( $search, 50, 0 )['contacts'] : array_values( array_filter( ContactHub_Store::companies(), static function ( $company ) use ( $search ) { return false !== stripos( (string) $company['name'], $search ); } ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_merge_preview"><input type="hidden" name="entity" value="' . esc_attr( $entity ) . '">'; wp_nonce_field( 'contacthub_merge_preview' );
		echo '<table class="widefat striped"><thead><tr><th class="check-column"></th><th>' . esc_html__( 'Record', 'contacthub' ) . '</th><th>' . esc_html__( 'Details', 'contacthub' ) . '</th></tr></thead><tbody>';
		foreach ( $records as $record ) { $id = absint( $record['id'] ); $name = 'contacts' === $entity ? trim( $record['first_name'] . ' ' . $record['last_name'] ) : $record['name']; $details = 'contacts' === $entity ? $record['email'] : trim( ( $record['city'] ?? '' ) . ' ' . ( $record['vat_id'] ?? '' ) ); echo '<tr><td><input type="checkbox" name="ids[]" value="' . esc_attr( $id ) . '"></td><td>' . esc_html( $name ?: sprintf( __( 'Record #%d', 'contacthub' ), $id ) ) . '</td><td>' . esc_html( $details ) . '</td></tr>'; }
		if ( ! $records ) echo '<tr><td colspan="3">' . esc_html__( 'No matching records found.', 'contacthub' ) . '</td></tr>';
		echo '</tbody></table><p><button class="button button-primary">' . esc_html__( 'Compare selected records', 'contacthub' ) . '</button></p></form></section>';
	}

	public static function handle_merge_preview() {
		self::require_admin( 'contacthub_merge_preview' );
		$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', $_POST['ids'] ) ) ) ) : array();
		if ( count( $ids ) < 2 || count( $ids ) > 20 ) self::redirect_notice( 'error', __( 'Select between two and twenty records to merge.', 'contacthub' ) );
		$token = wp_generate_password( 12, false, false ); $state = array( 'entity' => 'companies' === ( $_POST['entity'] ?? '' ) ? 'companies' : 'contacts', 'ids' => $ids );
		set_transient( 'contacthub_merge_' . get_current_user_id() . '_' . $token, $state, HOUR_IN_SECONDS ); wp_safe_redirect( self::migration_url( array( 'merge_token' => $token ) ) ); exit;
	}

	private static function render_merge_preview( $token, $state ) {
		$records = array(); foreach ( $state['ids'] as $id ) { $record = 'contacts' === $state['entity'] ? ContactHub_Store::contact( $id ) : ContactHub_Store::company( $id ); if ( $record ) $records[ $id ] = $record; }
		$fields = 'contacts' === $state['entity'] ? array( 'first_name' => __( 'First name', 'contacthub' ), 'last_name' => __( 'Last name', 'contacthub' ), 'email' => __( 'Email', 'contacthub' ), 'position' => __( 'Position', 'contacthub' ), 'wp_user_id' => __( 'WordPress user', 'contacthub' ), 'meta__anrede' => __( 'Salutation', 'contacthub' ), 'meta__titel_vorangestellt' => __( 'Title before name', 'contacthub' ), 'meta__titel_nachgestellt' => __( 'Title after name', 'contacthub' ), 'meta__primary_phone' => __( 'Phone', 'contacthub' ), 'meta__address_1' => __( 'Street address', 'contacthub' ), 'meta__address_2' => __( 'Address line 2', 'contacthub' ), 'meta__postcode' => __( 'Postal code', 'contacthub' ), 'meta__city' => __( 'City', 'contacthub' ), 'meta__state' => __( 'State', 'contacthub' ), 'meta__country' => __( 'Country', 'contacthub' ) ) : array( 'name' => __( 'Company name', 'contacthub' ), 'legal_name' => __( 'Legal name', 'contacthub' ), 'industry' => __( 'Industry', 'contacthub' ), 'website' => __( 'Website', 'contacthub' ), 'email' => __( 'Email', 'contacthub' ), 'phone_number' => __( 'Phone', 'contacthub' ), 'vat_id' => __( 'VAT ID', 'contacthub' ), 'street' => __( 'Street address', 'contacthub' ), 'postcode' => __( 'Postal code', 'contacthub' ), 'city' => __( 'City', 'contacthub' ), 'country' => __( 'Country', 'contacthub' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_merge_commit"><input type="hidden" name="merge_token" value="' . esc_attr( $token ) . '">'; wp_nonce_field( 'contacthub_merge_commit' );
		echo '<h3>' . esc_html__( 'Choose the primary record', 'contacthub' ) . '</h3><div class="contacthub-merge-primary">';
		foreach ( $records as $id => $record ) { $name = 'contacts' === $state['entity'] ? trim( $record['first_name'] . ' ' . $record['last_name'] ) : $record['name']; echo '<label><input type="radio" name="primary_id" value="' . esc_attr( $id ) . '"' . checked( $id, array_key_first( $records ), false ) . '> ' . esc_html( ( $name ?: __( 'Unnamed record', 'contacthub' ) ) . ' #' . $id ) . '</label>'; }
		echo '</div><h3>' . esc_html__( 'Resolve field values', 'contacthub' ) . '</h3><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Field', 'contacthub' ) . '</th><th>' . esc_html__( 'Keep value from', 'contacthub' ) . '</th></tr></thead><tbody>';
		foreach ( $fields as $field => $label ) { echo '<tr><th>' . esc_html( $label ) . '</th><td>'; $first = true; foreach ( $records as $id => $record ) { $value = 0 === strpos( $field, 'meta__' ) ? ( $record['meta'][ substr( $field, 6 ) ] ?? '' ) : ( $record[ $field ] ?? '' ); if ( '' === trim( (string) $value ) ) continue; echo '<label class="contacthub-merge-choice"><input type="radio" name="choices[' . esc_attr( $field ) . ']" value="' . esc_attr( $id ) . '"' . checked( $first, true, false ) . '> ' . esc_html( (string) $value ) . ' <small>#' . esc_html( $id ) . '</small></label>'; $first = false; } if ( $first ) echo '&mdash;'; echo '</td></tr>'; }
		echo '</tbody></table><div class="notice notice-warning inline"><p>' . esc_html__( 'After confirmation, source fragments are moved to the ContactHub Trash. External systems, orders, invoices, and source records are not changed.', 'contacthub' ) . '</p></div><p><label><input type="checkbox" name="confirmed" value="1" required> ' . esc_html__( 'I reviewed the primary record and every conflicting value.', 'contacthub' ) . '</label></p><p><button class="button button-primary">' . esc_html__( 'Merge records', 'contacthub' ) . '</button> <a class="button" href="' . esc_url( self::migration_url() ) . '">' . esc_html__( 'Cancel', 'contacthub' ) . '</a></p></form>';
	}

	public static function handle_merge_commit() {
		self::require_admin( 'contacthub_merge_commit' );
		if ( '1' !== ( $_POST['confirmed'] ?? '' ) ) self::redirect_notice( 'error', __( 'Confirm the reviewed merge before continuing.', 'contacthub' ) );
		$token = sanitize_key( wp_unslash( $_POST['merge_token'] ?? '' ) ); $state = get_transient( 'contacthub_merge_' . get_current_user_id() . '_' . $token );
		if ( ! is_array( $state ) ) self::redirect_notice( 'error', __( 'The merge preview expired. Select the records again.', 'contacthub' ) );
		$choices = isset( $_POST['choices'] ) && is_array( $_POST['choices'] ) ? array_map( 'absint', wp_unslash( $_POST['choices'] ) ) : array();
		$result = ContactHub_Store::merge_records( $state['entity'], $state['ids'], absint( $_POST['primary_id'] ?? 0 ), $choices );
		if ( is_wp_error( $result ) ) self::redirect_notice( 'error', $result->get_error_message(), array( 'merge_token' => $token ) );
		delete_transient( 'contacthub_merge_' . get_current_user_id() . '_' . $token ); self::redirect_notice( 'success', sprintf( __( '%d duplicate fragments were merged and moved to Trash.', 'contacthub' ), $result['merged'] ) );
	}

	public static function render_central_library() {
		$settings = wp_parse_args( (array) get_option( self::CENTRAL_OPTION, array() ), array( 'enabled' => 0, 'auto' => 0, 'source_url' => '', 'version' => '', 'updated_at' => '', 'error' => '' ) );
		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Optional central salutation library', 'contacthub' ) . '</h2><p>' . esc_html__( 'You can optionally supplement your local library with a centrally maintained name list. Local and manually confirmed assignments always take precedence. No contact or customer data is uploaded.', 'contacthub' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_central_salutations">'; wp_nonce_field( 'contacthub_central_salutations' );
		echo '<p><label><input type="checkbox" name="enabled" value="1"' . checked( ! empty( $settings['enabled'] ), true, false ) . '> ' . esc_html__( 'Enable the central library', 'contacthub' ) . '</label></p><p><label><input type="checkbox" name="accepted" value="1"' . checked( ! empty( $settings['enabled'] ), true, false ) . '> ' . esc_html__( 'I understand that name-based salutations may be incomplete or incorrect and remain responsible for reviewing them.', 'contacthub' ) . '</label></p><p><label><input type="checkbox" name="auto" value="1"' . checked( ! empty( $settings['auto'] ), true, false ) . '> ' . esc_html__( 'Check weekly for library updates', 'contacthub' ) . '</label></p><p><button class="button button-primary">' . esc_html__( 'Save library settings', 'contacthub' ) . '</button></p></form>';
		if ( ! empty( $settings['enabled'] ) ) { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_sync_central_salutations">'; wp_nonce_field( 'contacthub_sync_central_salutations' ); echo '<button class="button">' . esc_html__( 'Update central library now', 'contacthub' ) . '</button></form>'; }
		if ( $settings['version'] || $settings['updated_at'] ) echo '<p class="description">' . esc_html( sprintf( __( 'Installed central version: %1$s. Last check: %2$s.', 'contacthub' ), $settings['version'] ?: '—', $settings['updated_at'] ?: '—' ) ) . '</p>';
		if ( $settings['error'] ) echo '<div class="notice notice-warning inline"><p>' . esc_html( $settings['error'] ) . '</p></div>';
		echo '</section>';
	}

	public static function handle_central_settings() {
		self::require_admin( 'contacthub_central_salutations' );
		$enabled = ! empty( $_POST['enabled'] ); if ( $enabled && empty( $_POST['accepted'] ) ) wp_die( esc_html__( 'Accept the library disclaimer before enabling it.', 'contacthub' ), '', array( 'response' => 400 ) );
		$settings = (array) get_option( self::CENTRAL_OPTION, array() ); $settings['enabled'] = $enabled ? 1 : 0; $settings['auto'] = $enabled && ! empty( $_POST['auto'] ) ? 1 : 0; update_option( self::CENTRAL_OPTION, $settings, false );
		if ( $settings['auto'] && ! wp_next_scheduled( 'contacthub_central_salutation_sync' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'contacthub_weekly', 'contacthub_central_salutation_sync' );
		if ( ! $settings['auto'] ) wp_clear_scheduled_hook( 'contacthub_central_salutation_sync' );
		wp_safe_redirect( admin_url( 'admin.php?page=contacthub-settings&tab=misc&misc_section=salutations' ) ); exit;
	}

	public static function handle_central_sync() { self::require_admin( 'contacthub_sync_central_salutations' ); $result = self::sync_central_library(); set_transient( 'contacthub_database_notice_' . get_current_user_id(), array( 'type' => is_wp_error( $result ) ? 'error' : 'success', 'message' => is_wp_error( $result ) ? $result->get_error_message() : sprintf( __( 'Central library updated: %d new names added.', 'contacthub' ), $result ) ), MINUTE_IN_SECONDS ); wp_safe_redirect( admin_url( 'admin.php?page=contacthub-settings&tab=misc&misc_section=salutations' ) ); exit; }
	public static function scheduled_central_sync() { $settings = (array) get_option( self::CENTRAL_OPTION, array() ); if ( ! empty( $settings['enabled'] ) && ! empty( $settings['auto'] ) ) self::sync_central_library(); }

	private static function sync_central_library() {
		$settings = wp_parse_args( (array) get_option( self::CENTRAL_OPTION, array() ), array( 'enabled' => 0, 'source_url' => '', 'version' => '', 'updated_at' => '', 'error' => '' ) );
		if ( empty( $settings['enabled'] ) ) return new WP_Error( 'contacthub_central_disabled', __( 'Enable the central library before updating it.', 'contacthub' ) );
		$url = apply_filters( 'contacthub_central_salutation_library_url', 'https://raw.githubusercontent.com/kultur3gmbh/contacthub-salutations/main/salutations.json' );
		$response = wp_safe_remote_get( $url, array( 'timeout' => 15, 'user-agent' => 'ContactHub/' . CONTACTHUB_VERSION ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { $settings['error'] = __( 'The central library could not be downloaded. Your local library was not changed.', 'contacthub' ); $settings['updated_at'] = current_time( 'mysql' ); update_option( self::CENTRAL_OPTION, $settings, false ); return new WP_Error( 'contacthub_central_download', $settings['error'] ); }
		$json = json_decode( wp_remote_retrieve_body( $response ), true ); $entries = isset( $json['entries'] ) && is_array( $json['entries'] ) ? $json['entries'] : ( is_array( $json ) ? $json : array() ); $added = 0;
		foreach ( $entries as $entry ) { if ( ! is_array( $entry ) ) continue; $name = sanitize_text_field( $entry['first_name'] ?? '' ); $salutation = sanitize_key( $entry['salutation'] ?? '' ); if ( $name && ! ContactHub_Store::suggested_salutation( $name ) && ContactHub_Store::learn_salutation( $name, $salutation, 'central' ) ) $added++; }
		$settings['version'] = sanitize_text_field( $json['version'] ?? gmdate( 'Y-m-d' ) ); $settings['source_url'] = esc_url_raw( $url ); $settings['updated_at'] = current_time( 'mysql' ); $settings['error'] = ''; update_option( self::CENTRAL_OPTION, $settings, false ); return $added;
	}

	public static function render_notice_settings() {
		$settings = wp_parse_args( (array) get_option( self::NOTICE_OPTION, array() ), array( 'enabled' => 0 ) ); $hidden = (array) get_user_meta( get_current_user_id(), 'contacthub_hidden_admin_notices', true );
		echo '<section class="contacthub-card contacthub-settings-card"><h2>' . esc_html__( 'Optional notice cleanup', 'contacthub' ) . '</h2><p>' . esc_html__( 'ContactHub can add a Hide link to non-critical promotional, license-key, and upgrade reminders. Error messages, security warnings, and WordPress core update notices are never hidden.', 'contacthub' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contacthub_admin_notice_settings">'; wp_nonce_field( 'contacthub_admin_notice_settings' );
		echo '<label><input type="checkbox" name="enabled" value="1"' . checked( ! empty( $settings['enabled'] ), true, false ) . '> ' . esc_html__( 'Allow me to hide non-critical reminders', 'contacthub' ) . '</label><p><label><input type="checkbox" name="restore" value="1"> ' . esc_html( sprintf( __( 'Restore all hidden reminders for my account (%d)', 'contacthub' ), count( $hidden ) ) ) . '</label></p><p><button class="button button-primary">' . esc_html__( 'Save notice settings', 'contacthub' ) . '</button></p></form></section>';
	}

	public static function handle_notice_settings() { self::require_admin( 'contacthub_admin_notice_settings' ); update_option( self::NOTICE_OPTION, array( 'enabled' => empty( $_POST['enabled'] ) ? 0 : 1 ), false ); if ( ! empty( $_POST['restore'] ) ) delete_user_meta( get_current_user_id(), 'contacthub_hidden_admin_notices' ); wp_safe_redirect( admin_url( 'admin.php?page=contacthub-settings&tab=misc&misc_section=admin_notices' ) ); exit; }

	public static function enqueue_notice_helper() {
		$settings = (array) get_option( self::NOTICE_OPTION, array() );
		if ( ! current_user_can( 'manage_options' ) || empty( $settings['enabled'] ) ) return;
		wp_enqueue_script( 'contacthub-admin-notices', CONTACTHUB_URL . 'assets/contacthub-admin-notices.js', array(), CONTACTHUB_ASSET_VERSION, true );
		wp_localize_script( 'contacthub-admin-notices', 'CONTACTHUB_NOTICE_CONFIG', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'contacthub_dismiss_admin_notice' ), 'hidden' => array_keys( (array) get_user_meta( get_current_user_id(), 'contacthub_hidden_admin_notices', true ) ), 'hideLabel' => __( 'Hide this reminder', 'contacthub' ) ) );
	}

	public static function dismiss_admin_notice() {
		check_ajax_referer( 'contacthub_dismiss_admin_notice', 'nonce' ); if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error(); $key = sanitize_key( wp_unslash( $_POST['key'] ?? '' ) ); $excerpt = sanitize_text_field( wp_unslash( $_POST['excerpt'] ?? '' ) ); if ( ! preg_match( '/^[a-f0-9]{8}$/', $key ) ) wp_send_json_error(); $hidden = (array) get_user_meta( get_current_user_id(), 'contacthub_hidden_admin_notices', true ); $hidden[ $key ] = substr( $excerpt, 0, 180 ); update_user_meta( get_current_user_id(), 'contacthub_hidden_admin_notices', $hidden ); wp_send_json_success();
	}
}
