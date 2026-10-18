<?php

defined( 'ABSPATH' ) || exit;

final class ContactHub_Store {
	public static function source() {
		$settings = (array) get_option( 'contacthub_general_settings', array() );
		$default = defined( 'GROUNDHOGG_VERSION' ) || class_exists( '\\Groundhogg\\Contact' ) ? 'groundhogg' : 'unconfigured';
		return isset( $settings['data_source'] ) ? sanitize_key( $settings['data_source'] ) : $default;
	}

	public static function standalone() {
		return 'standalone' === self::source() && self::ready();
	}

	public static function ready() {
		$status = ContactHub_Schema::status();
		return $status['total'] === $status['count'] && ContactHub_Schema::VERSION === $status['version'];
	}

	public static function counts() {
		global $wpdb;
		$result = array();
		foreach ( ContactHub_Schema::table_names() as $key => $table ) {
			$result[ $key ] = self::ready() ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- exact internally generated table name.
		}
		return $result;
	}

	public static function search_contacts( $search = '', $limit = 50, $offset = 0 ) {
		global $wpdb;
		$tables = ContactHub_Schema::table_names();
		$where = "deleted_at IS NULL";
		$params = array();
		if ( '' !== trim( $search ) ) {
			$like = '%' . $wpdb->esc_like( trim( $search ) ) . '%';
			$where .= ' AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)';
			$params = array( $like, $like, $like );
		}
		$count_sql = "SELECT COUNT(*) FROM `{$tables['contacts']}` WHERE {$where}";
		$total = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table and fixed clauses.
		$sql = "SELECT * FROM `{$tables['contacts']}` WHERE {$where} ORDER BY last_name ASC, first_name ASC LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( absint( $limit ), absint( $offset ) ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table and fixed clauses.
		return array( 'contacts' => array_map( array( __CLASS__, 'hydrate_contact' ), (array) $rows ), 'total_items' => $total );
	}

	public static function contact( $id, $include_deleted = false ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['contacts'];
		$state = $include_deleted ? '' : ' AND deleted_at IS NULL';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d{$state}", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		return $row ? self::hydrate_contact( $row ) : false;
	}

	public static function contact_by_email( $email, $include_deleted = false ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['contacts'];
		$state = $include_deleted ? '' : ' AND deleted_at IS NULL';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE email_normalized = %s{$state}", strtolower( sanitize_email( $email ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		return $row ? self::hydrate_contact( $row ) : false;
	}

	public static function contact_by_meta( $key, $value, $include_deleted = false ) {
		global $wpdb;
		$tables = ContactHub_Schema::table_names();
		$state = $include_deleted ? '' : ' AND c.deleted_at IS NULL';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT c.* FROM `{$tables['contacts']}` c INNER JOIN `{$tables['contactmeta']}` m ON m.contact_id=c.id WHERE m.meta_key=%s AND m.meta_value=%s{$state} ORDER BY c.id ASC LIMIT 1", sanitize_key( $key ), maybe_serialize( $value ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table names.
		return $row ? self::hydrate_contact( $row ) : false;
	}

	public static function duplicate_candidates( $limit = 50 ) {
		global $wpdb;
		$tables = ContactHub_Schema::table_names();
		$rows = (array) $wpdb->get_results( "SELECT id, wp_user_id, email, first_name, last_name FROM `{$tables['contacts']}` WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 5000", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$meta_rows = (array) $wpdb->get_results( "SELECT contact_id, meta_key, meta_value FROM `{$tables['contactmeta']}` WHERE meta_key IN ('primary_phone','billing_phone','phone','postcode','billing_postcode','contacthub_contact_channels')", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$meta = array();
		foreach ( $meta_rows as $item ) $meta[ absint( $item['contact_id'] ) ][ $item['meta_key'] ] = maybe_unserialize( $item['meta_value'] );
		$seen = array(); $pairs = array();
		foreach ( $rows as $row ) {
			$id = absint( $row['id'] );
			$phones = array( preg_replace( '/\D+/', '', (string) ( $meta[ $id ]['primary_phone'] ?? $meta[ $id ]['billing_phone'] ?? $meta[ $id ]['phone'] ?? '' ) ) );
			$emails = array( strtolower( sanitize_email( $row['email'] ?? '' ) ) );
			$channels = $meta[ $id ]['contacthub_contact_channels'] ?? array();
			foreach ( is_array( $channels ) ? $channels : array() as $channel ) {
				if ( ! is_array( $channel ) ) continue;
				if ( 'phone' === ( $channel['type'] ?? '' ) ) $phones[] = preg_replace( '/\D+/', '', (string) ( $channel['value'] ?? '' ) );
				if ( 'email' === ( $channel['type'] ?? '' ) ) $emails[] = strtolower( sanitize_email( $channel['value'] ?? '' ) );
			}
			$postcode = preg_replace( '/\s+/', '', strtolower( (string) ( $meta[ $id ]['postcode'] ?? $meta[ $id ]['billing_postcode'] ?? '' ) ) );
			$name = trim( strtolower( remove_accents( (string) $row['first_name'] . '|' . (string) $row['last_name'] ) ) );
			$keys = array();
			if ( ! empty( $row['wp_user_id'] ) ) $keys['wordpress'] = 'u:' . absint( $row['wp_user_id'] );
			foreach ( array_unique( array_filter( $phones ) ) as $phone ) if ( strlen( $phone ) >= 7 ) $keys[ __( 'Phone number', 'contacthub' ) . ':' . $phone ] = 'p:' . $phone;
			foreach ( array_unique( array_filter( $emails ) ) as $email ) $keys[ __( 'Email address', 'contacthub' ) . ':' . $email ] = 'e:' . $email;
			if ( '|' !== $name && $postcode ) $keys[ __( 'Name and postal code', 'contacthub' ) ] = 'n:' . $name . '|' . $postcode;
			foreach ( $keys as $reason => $key ) {
				if ( isset( $seen[ $key ] ) ) {
					$left = $seen[ $key ];
					$pairs[ $left['id'] . ':' . $id ] = array(
						'left_id' => $left['id'], 'right_id' => $id,
						'left_first_name' => $left['first_name'], 'left_last_name' => $left['last_name'],
						'right_first_name' => $row['first_name'], 'right_last_name' => $row['last_name'],
						'reason' => 'wordpress' === $reason ? __( 'Same WordPress account', 'contacthub' ) : preg_replace( '/:.+$/', '', $reason ),
					);
					if ( count( $pairs ) >= absint( $limit ) ) return array_values( $pairs );
				} else {
					$seen[ $key ] = $row;
				}
			}
		}
		return array_values( $pairs );
	}

	/**
	 * Return transitive duplicate groups instead of isolated pairs.
	 *
	 * A-B and B-C are deliberately presented as one review group. No records are
	 * changed here; the administrator still resolves every value in the merge UI.
	 */
	public static function duplicate_groups( $limit = 50 ) {
		$pairs = self::duplicate_candidates( max( 200, absint( $limit ) * 10 ) );
		$parents = array();
		$names = array();
		$reasons = array();
		$find = static function ( $id ) use ( &$parents, &$find ) {
			if ( ! isset( $parents[ $id ] ) ) $parents[ $id ] = $id;
			if ( $parents[ $id ] !== $id ) $parents[ $id ] = $find( $parents[ $id ] );
			return $parents[ $id ];
		};
		foreach ( $pairs as $pair ) {
			$left = absint( $pair['left_id'] ); $right = absint( $pair['right_id'] );
			$left_root = $find( $left ); $right_root = $find( $right );
			if ( $left_root !== $right_root ) $parents[ $right_root ] = $left_root;
			$names[ $left ] = trim( $pair['left_first_name'] . ' ' . $pair['left_last_name'] );
			$names[ $right ] = trim( $pair['right_first_name'] . ' ' . $pair['right_last_name'] );
			$reasons[ $left . ':' . $right ] = $pair['reason'];
		}
		$groups = array();
		foreach ( array_keys( $parents ) as $id ) $groups[ $find( $id ) ][] = $id;
		$result = array();
		foreach ( $groups as $ids ) {
			$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
			if ( count( $ids ) < 2 ) continue;
			$group_reasons = array();
			foreach ( $reasons as $key => $reason ) { list( $a, $b ) = array_map( 'absint', explode( ':', $key ) ); if ( in_array( $a, $ids, true ) && in_array( $b, $ids, true ) ) $group_reasons[] = $reason; }
			$result[] = array( 'ids' => $ids, 'names' => array_map( static function ( $id ) use ( $names ) { return $names[ $id ] ?: sprintf( __( 'Record #%d', 'contacthub' ), $id ); }, $ids ), 'reasons' => array_values( array_unique( $group_reasons ) ) );
			if ( count( $result ) >= absint( $limit ) ) break;
		}
		return $result;
	}

	public static function create_contact( $data ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['contacts'];
		$email = sanitize_email( isset( $data['email'] ) ? $data['email'] : '' );
		if ( $email && self::contact_by_email( $email, true ) ) {
			return new WP_Error( 'contacthub_duplicate_email', __( 'A person with this email address already exists.', 'contacthub' ) );
		}
		$now = current_time( 'mysql' );
		$inserted = $wpdb->insert(
			$table,
			array(
				'wp_user_id'      => ! empty( $data['user_id'] ) ? absint( $data['user_id'] ) : null,
				'email'           => $email ?: null,
				'email_normalized'=> $email ? strtolower( $email ) : null,
				'first_name'      => sanitize_text_field( isset( $data['first_name'] ) ? $data['first_name'] : '' ),
				'last_name'       => sanitize_text_field( isset( $data['last_name'] ) ? $data['last_name'] : '' ),
				'position'        => sanitize_text_field( isset( $data['position'] ) ? $data['position'] : '' ),
				'status'          => sanitize_key( isset( $data['status'] ) ? $data['status'] : 'active' ),
				'source'          => sanitize_key( isset( $data['source'] ) ? $data['source'] : 'manual' ),
				'created_at'      => $now,
				'updated_at'      => $now,
			)
		);
		if ( ! $inserted ) {
			return new WP_Error( 'contacthub_contact_insert', __( 'The person could not be saved in ContactHub.', 'contacthub' ) );
		}
		$id = (int) $wpdb->insert_id;
		self::update_contact( $id, $data );
		return $id;
	}

	public static function update_contact( $id, $data ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['contacts'];
		$core = array();
		$map = array( 'first_name' => 'first_name', 'last_name' => 'last_name', 'position' => 'position', 'user_id' => 'wp_user_id' );
		foreach ( $map as $input => $column ) {
			if ( array_key_exists( $input, $data ) ) {
				$core[ $column ] = 'user_id' === $input ? absint( $data[ $input ] ) : sanitize_text_field( $data[ $input ] );
			}
		}
		if ( array_key_exists( 'email', $data ) ) {
			$email = sanitize_email( $data['email'] );
			$core['email'] = $email ?: null;
			$core['email_normalized'] = $email ? strtolower( $email ) : null;
		}
		$core['updated_at'] = current_time( 'mysql' );
		$wpdb->update( $table, $core, array( 'id' => absint( $id ) ) );
		$meta = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array();
		foreach ( array( 'anrede', 'titel_vorangestellt', 'titel_nachgestellt', 'berufsbezeichnung', 'primary_phone', 'optin_status', 'groundhogg_contact_id' ) as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$meta[ $key ] = $data[ $key ];
			}
		}
		foreach ( $meta as $key => $value ) {
			self::set_contact_meta( $id, $key, $value );
		}
		return true;
	}

	public static function update_contact_address( $id, $address ) {
		$allowed = array( 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' );
		foreach ( $allowed as $key ) {
			$value = isset( $address[ $key ] ) ? sanitize_text_field( $address[ $key ] ) : '';
			if ( 'country' === $key ) {
				$value = strtoupper( $value );
			}
			if ( ! self::set_contact_meta( $id, $key, $value ) ) {
				return false;
			}
		}
		return true;
	}

	public static function companies() {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['companies'];
		$rows = $wpdb->get_results( "SELECT * FROM `{$table}` WHERE deleted_at IS NULL ORDER BY name ASC LIMIT 1000", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		return array_map( array( __CLASS__, 'hydrate_company' ), (array) $rows );
	}

	public static function company( $id ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['companies'];
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d AND deleted_at IS NULL", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		return $row ? self::hydrate_company( $row ) : false;
	}

	public static function create_company( $data ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['companies'];
		$now = current_time( 'mysql' );
		$wpdb->insert( $table, array( 'name' => sanitize_text_field( $data['name'] ), 'legal_name' => sanitize_text_field( isset( $data['legal_name'] ) ? $data['legal_name'] : $data['name'] ), 'industry' => sanitize_text_field( isset( $data['industry'] ) ? $data['industry'] : '' ), 'website' => esc_url_raw( isset( $data['website'] ) ? $data['website'] : '' ), 'vat_id' => sanitize_text_field( isset( $data['vat_id'] ) ? $data['vat_id'] : '' ), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now ) );
		return $wpdb->insert_id ? (int) $wpdb->insert_id : 0;
	}

	/** Import one standalone company row without clearing existing non-empty data. */
	public static function import_company_row( $data, $mode = 'fill', $source_id = '' ) {
		global $wpdb;
		$name = sanitize_text_field( $data['name'] ?? '' );
		if ( '' === $name ) return new WP_Error( 'contacthub_import_company_name', __( 'Company name is missing.', 'contacthub' ) );
		$id = self::company_by_name( $name );
		$existed = (bool) $id;
		if ( $id && 'skip' === $mode ) return 'skipped';
		if ( ! $id ) {
			$id = self::create_company( array( 'name' => $name, 'legal_name' => $data['legal_name'] ?? $name, 'industry' => $data['industry'] ?? '', 'website' => $data['website'] ?? '', 'vat_id' => $data['vat_id'] ?? '' ) );
			if ( ! $id ) return new WP_Error( 'contacthub_import_company_create', __( 'Company could not be created.', 'contacthub' ) );
		}
		$current = self::company( $id );
		$map = array( 'name', 'legal_name', 'industry', 'website', 'email', 'vat_id', 'street', 'address_2', 'postcode', 'city', 'country' );
		$input_map = array( 'street' => 'address_1' );
		$update = array();
		foreach ( $map as $field ) {
			$input = $input_map[ $field ] ?? $field;
			$value = sanitize_text_field( $data[ $input ] ?? '' );
			if ( '' !== $value && ( ! $current || 'update' === $mode || '' === trim( (string) ( $current[ $field ] ?? '' ) ) ) ) $update[ $field ] = 'website' === $field ? esc_url_raw( $value ) : $value;
		}
		if ( ! empty( $data['phone'] ) && ( ! $current || 'update' === $mode || empty( $current['phone_number'] ) ) ) $update['phone_number'] = sanitize_text_field( $data['phone'] );
		$update['updated_at'] = current_time( 'mysql' );
		if ( false === $wpdb->update( ContactHub_Schema::table_names()['companies'], $update, array( 'id' => $id ) ) ) return new WP_Error( 'contacthub_import_company_update', __( 'Company could not be updated.', 'contacthub' ) );
		self::set_company_meta( $id, 'csv_source_id', sanitize_text_field( $source_id ) );
		return $existed ? 'updated' : 'created';
	}

	public static function company_by_name( $name, $include_deleted = false ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['companies'];
		$state = $include_deleted ? '' : ' AND deleted_at IS NULL';
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE LOWER(name) = LOWER(%s){$state} LIMIT 1", sanitize_text_field( $name ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
	}

	/**
	 * Links a standalone contact to a freely entered company name. Existing
	 * active companies are reused; otherwise a minimal company is created.
	 */
	public static function link_contact_company_name( $email, $company_name ) {
		if ( ! self::standalone() ) {
			return true;
		}
		$email = sanitize_email( $email );
		$company_name = sanitize_text_field( $company_name );
		if ( ! $email || '' === trim( $company_name ) ) {
			return true;
		}
		$contact = self::contact_by_email( $email );
		if ( ! $contact ) {
			return false;
		}
		$company_id = self::company_by_name( $company_name );
		if ( ! $company_id ) {
			$company_id = self::create_company(
				array(
					'name'       => $company_name,
					'legal_name' => $company_name,
				)
			);
		}
		return $company_id ? self::link_company_contact( $company_id, $contact['id'] ) : false;
	}

	public static function link_company_contact( $company_id, $contact_id, $position = '', $primary = false ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['company_contacts'];
		if ( $primary && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE company_id = %d AND contact_id <> %d AND is_primary = 1 LIMIT 1", absint( $company_id ), absint( $contact_id ) ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
			$primary = false;
		}
		return false !== $wpdb->replace(
			$table,
			array(
				'company_id'        => absint( $company_id ),
				'contact_id'        => absint( $contact_id ),
				'relationship_type' => 'employee',
				'position'          => sanitize_text_field( $position ),
				'is_primary'        => $primary ? 1 : 0,
				'created_at'        => current_time( 'mysql' ),
				'updated_at'        => current_time( 'mysql' ),
			)
		);
	}

	public static function update_company( $id, $field, $value ) {
		global $wpdb;
		$tables = ContactHub_Schema::table_names();
		$map = array( 'name' => 'name', 'domain' => 'website', 'phone' => 'phone_number', 'industry' => 'industry' );
		if ( isset( $map[ $field ] ) ) {
			return false !== $wpdb->update( $tables['companies'], array( $map[ $field ] => $value, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $id ) ) );
		}
		return self::set_company_meta( $id, $field, $value );
	}

	public static function update_company_profile( $id, $data ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['companies'];
		return false !== $wpdb->update( $table, array( 'legal_name' => sanitize_text_field( $data['company'] ), 'industry' => sanitize_text_field( $data['industry'] ), 'website' => esc_url_raw( $data['website'] ), 'vat_id' => sanitize_text_field( $data['vat_id'] ), 'street' => sanitize_text_field( $data['billing_address_1'] ), 'address_2' => sanitize_text_field( $data['billing_address_2'] ), 'postcode' => sanitize_text_field( $data['billing_postcode'] ), 'city' => sanitize_text_field( $data['billing_city'] ), 'country' => sanitize_key( $data['billing_country'] ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $id ) ) );
	}

	public static function company_map() {
		global $wpdb;
		$tables = ContactHub_Schema::table_names();
		$rows = $wpdb->get_results( "SELECT company_id, contact_id FROM `{$tables['company_contacts']}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$companies = array();
		foreach ( self::companies() as $company ) {
			$companies[ $company['id'] ] = $company;
		}
		$map = array();
		foreach ( (array) $rows as $row ) {
			if ( isset( $companies[ $row['company_id'] ] ) ) {
				$map[ $row['contact_id'] ][] = $companies[ $row['company_id'] ];
			}
		}
		return $map;
	}

	public static function tags() {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['tags'];
		return (array) $wpdb->get_results( "SELECT id, name, description FROM `{$table}` ORDER BY name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
	}

	public static function create_tag( $name, $description = '' ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['tags'];
		$name = sanitize_text_field( $name );
		$slug = sanitize_title( $name );
		if ( '' === $name || '' === $slug ) return 0;
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE slug = %s", $slug ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		if ( $id ) return $id;
		$now = current_time( 'mysql' );
		$wpdb->insert( $table, array( 'name' => $name, 'slug' => $slug, 'description' => sanitize_textarea_field( $description ), 'created_at' => $now, 'updated_at' => $now ) );
		return (int) $wpdb->insert_id;
	}

	public static function tag_counts() {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['contact_tags'];
		$rows = $wpdb->get_results( "SELECT tag_id, COUNT(*) AS assignments FROM `{$table}` GROUP BY tag_id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ absint( $row['tag_id'] ) ] = absint( $row['assignments'] );
		}
		return $counts;
	}

	public static function rename_tag( $tag_id, $name, $description = '' ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['tags'];
		$name = sanitize_text_field( $name );
		$slug = sanitize_title( $name );
		if ( ! $tag_id || ! $name || ! $slug ) return false;
		$duplicate = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE slug = %s AND id <> %d LIMIT 1", $slug, absint( $tag_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		if ( $duplicate ) return false;
		return false !== $wpdb->update( $table, array( 'name' => $name, 'slug' => $slug, 'description' => sanitize_textarea_field( $description ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $tag_id ) ) );
	}

	public static function salutations() {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['salutations'];
		if ( self::ready() ) return (array) $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY first_name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$rows = array_values( (array) get_option( 'contacthub_salutation_library', array() ) );
		foreach ( $rows as $index => &$row ) $row['id'] = $index + 1;
		unset( $row ); usort( $rows, static function ( $a, $b ) { return strcasecmp( $a['first_name'], $b['first_name'] ); } ); return $rows;
	}

	public static function suggested_salutation( $first_name ) {
		global $wpdb;
		$normalized = self::normalize_first_name( $first_name );
		if ( ! $normalized ) return '';
		if ( ! self::ready() ) { $library = (array) get_option( 'contacthub_salutation_library', array() ); return isset( $library[ $normalized ]['salutation'] ) ? (string) $library[ $normalized ]['salutation'] : ''; }
		$table = ContactHub_Schema::table_names()['salutations'];
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT salutation FROM `{$table}` WHERE first_name_normalized=%s", $normalized ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
	}

	public static function learn_salutation( $first_name, $salutation, $source = 'confirmed' ) {
		global $wpdb;
		$salutation = sanitize_key( $salutation );
		$normalized = self::normalize_first_name( $first_name );
		if ( ! $normalized || ! in_array( $salutation, array( 'mr', 'ms', 'family', 'neutral' ), true ) ) return false;
		if ( ! self::ready() ) { $library = (array) get_option( 'contacthub_salutation_library', array() ); $now = current_time( 'mysql' ); $library[ $normalized ] = array( 'first_name' => sanitize_text_field( trim( $first_name ) ), 'first_name_normalized' => $normalized, 'salutation' => $salutation, 'source' => sanitize_key( $source ), 'created_at' => $library[ $normalized ]['created_at'] ?? $now, 'updated_at' => $now ); return update_option( 'contacthub_salutation_library', $library, false ) || isset( $library[ $normalized ] ); }
		$table = ContactHub_Schema::table_names()['salutations'];
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE first_name_normalized=%s", $normalized ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data = array( 'first_name' => sanitize_text_field( trim( $first_name ) ), 'first_name_normalized' => $normalized, 'salutation' => $salutation, 'source' => sanitize_key( $source ), 'updated_at' => current_time( 'mysql' ) );
		if ( $existing ) return false !== $wpdb->update( $table, $data, array( 'id' => $existing ) );
		$data['created_at'] = current_time( 'mysql' );
		return false !== $wpdb->insert( $table, $data );
	}

	public static function delete_salutation( $id ) {
		global $wpdb;
		if ( ! self::ready() ) { $target = array(); foreach ( self::salutations() as $row ) if ( absint( $row['id'] ?? 0 ) === absint( $id ) ) { $target = $row; break; } if ( empty( $target['first_name_normalized'] ) ) return false; $library = (array) get_option( 'contacthub_salutation_library', array() ); unset( $library[ $target['first_name_normalized'] ] ); return update_option( 'contacthub_salutation_library', $library, false ); }
		return false !== $wpdb->delete( ContactHub_Schema::table_names()['salutations'], array( 'id' => absint( $id ) ) );
	}

	/** Return one salutation-library row by its local identifier. */
	public static function salutation( $id ) {
		foreach ( self::salutations() as $row ) {
			if ( absint( $row['id'] ?? 0 ) === absint( $id ) ) return $row;
		}
		return false;
	}

	/**
	 * Merge local ContactHub records and move the source fragments to Trash.
	 *
	 * External systems are deliberately never called from this method. All
	 * relationships and history remain attached to the selected primary record.
	 */
	public static function merge_records( $entity, $ids, $primary_id, $choices = array(), $resolved = array() ) {
		global $wpdb;
		$entity = 'companies' === $entity ? 'companies' : 'contacts';
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		$primary_id = absint( $primary_id );
		if ( ! self::standalone() || count( $ids ) < 2 || ! in_array( $primary_id, $ids, true ) ) {
			return new WP_Error( 'contacthub_merge_invalid', __( 'Select at least two active records and one primary record.', 'contacthub' ) );
		}
		if ( 'contacts' === $entity && isset( $resolved['email'] ) && '' !== trim( (string) $resolved['email'] ) && ! is_email( $resolved['email'] ) ) {
			return new WP_Error( 'contacthub_merge_email', __( 'Enter a valid final email address before merging.', 'contacthub' ) );
		}
		if ( 'companies' === $entity && isset( $resolved['email'] ) && '' !== trim( (string) $resolved['email'] ) && ! is_email( $resolved['email'] ) ) {
			return new WP_Error( 'contacthub_merge_email', __( 'Enter a valid final email address before merging.', 'contacthub' ) );
		}
		$tables = ContactHub_Schema::table_names();
		$table = $tables[ $entity ];
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) return new WP_Error( 'contacthub_merge_transaction', __( 'The merge could not start safely.', 'contacthub' ) );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id IN ({$placeholders}) AND deleted_at IS NULL FOR UPDATE", $ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table and numeric placeholders.
		if ( count( $rows ) !== count( $ids ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'contacthub_merge_missing', __( 'One of the selected records is no longer active.', 'contacthub' ) ); }
		$by_id = array();
		foreach ( $rows as $row ) $by_id[ absint( $row['id'] ) ] = $row;
		$donors = array_values( array_diff( $ids, array( $primary_id ) ) );
		$now = current_time( 'mysql' );
		try {
			if ( 'contacts' === $entity ) {
				$fields = array( 'first_name', 'last_name', 'email', 'position', 'wp_user_id' );
				$update = array();
				foreach ( $fields as $field ) {
					$source = absint( $choices[ $field ] ?? $primary_id );
					if ( isset( $by_id[ $source ] ) && '' !== trim( (string) $by_id[ $source ][ $field ] ) ) $update[ $field ] = $by_id[ $source ][ $field ];
					if ( array_key_exists( $field, $resolved ) ) $update[ $field ] = 'wp_user_id' === $field ? absint( $resolved[ $field ] ) : sanitize_text_field( $resolved[ $field ] );
				}
				// Release unique values on donor fragments before applying a selected value.
				foreach ( $donors as $donor ) $wpdb->update( $table, array( 'email' => null, 'email_normalized' => null, 'wp_user_id' => null ), array( 'id' => $donor ) );
				if ( isset( $update['email'] ) ) {
					$update['email'] = sanitize_email( $update['email'] );
					$update['email_normalized'] = $update['email'] ? strtolower( $update['email'] ) : null;
				}
				if ( isset( $update['wp_user_id'] ) && ! $update['wp_user_id'] ) $update['wp_user_id'] = null;
				$update['updated_at'] = $now;
				if ( false === $wpdb->update( $table, $update, array( 'id' => $primary_id ) ) ) throw new Exception( 'update' );

				$merge_meta = array( 'anrede', 'titel_vorangestellt', 'titel_nachgestellt', 'berufsbezeichnung', 'primary_phone', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' );
				foreach ( $merge_meta as $meta_key ) {
					$choice_key = 'meta__' . $meta_key;
					$source = absint( $choices[ $choice_key ] ?? 0 );
					if ( $source && isset( $by_id[ $source ] ) ) { $source_meta = self::contact_meta( $source ); if ( '' !== trim( (string) ( $source_meta[ $meta_key ] ?? '' ) ) ) self::set_contact_meta( $primary_id, $meta_key, $source_meta[ $meta_key ] ); }
					if ( array_key_exists( $choice_key, $resolved ) ) {
						$value = sanitize_text_field( $resolved[ $choice_key ] );
						if ( 'anrede' === $meta_key && ! in_array( $value, array( '', 'mr', 'ms', 'family', 'neutral' ), true ) ) $value = '';
						if ( 'country' === $meta_key ) $value = strtoupper( $value );
						self::set_contact_meta( $primary_id, $meta_key, $value );
					}
				}
				$status_priority = array( 'spam' => 6, 'complained' => 6, 'blocked' => 6, 'bounced' => 5, 'hard_bounce' => 5, 'unsubscribed' => 4, 'confirmed' => 3, 'unconfirmed' => 1 );
				$best_status = ''; $best_priority = -1;
				foreach ( $ids as $source ) { $source_meta = self::contact_meta( $source ); $status = sanitize_key( $source_meta['optin_status'] ?? '' ); $priority = $status_priority[ $status ] ?? 0; if ( $priority > $best_priority ) { $best_status = $status; $best_priority = $priority; } }
				if ( $best_status ) self::set_contact_meta( $primary_id, 'optin_status', $best_status );
				$primary_meta = self::contact_meta( $primary_id );
				$aliases = array();
				$merged_channels = array();
				foreach ( $rows as $row ) {
					$row_id = absint( $row['id'] );
					$source_meta = self::contact_meta( $row_id );
					$core_email = strtolower( sanitize_email( $row['email'] ?? '' ) );
					if ( $core_email ) {
						$aliases[] = $core_email;
						$key = 'email:' . $core_email;
						if ( ! isset( $merged_channels[ $key ] ) ) {
							$merged_channels[ $key ] = array(
								'id'               => 'merged-email-' . $row_id,
								'type'             => 'email',
								'label'            => 'work',
								'value'            => $core_email,
								'is_primary'       => 0,
								'marketing_status' => sanitize_key( $source_meta['optin_status'] ?? 'unconfirmed' ),
							);
						}
					}
					$core_phone = sanitize_text_field( $source_meta['primary_phone'] ?? $source_meta['billing_phone'] ?? $source_meta['phone'] ?? '' );
					if ( $core_phone ) {
						$key = 'phone:' . preg_replace( '/\D+/', '', $core_phone );
						if ( ! isset( $merged_channels[ $key ] ) ) {
							$merged_channels[ $key ] = array(
								'id'               => 'merged-phone-' . $row_id,
								'type'             => 'phone',
								'label'            => 'mobile',
								'value'            => $core_phone,
								'is_primary'       => 0,
								'marketing_status' => '',
							);
						}
					}
					foreach ( is_array( $source_meta['contacthub_contact_channels'] ?? null ) ? $source_meta['contacthub_contact_channels'] : array() as $channel ) {
						if ( ! is_array( $channel ) || ! in_array( $channel['type'] ?? '', array( 'email', 'phone' ), true ) ) continue;
						$key = sanitize_key( $channel['type'] ) . ':' . strtolower( preg_replace( '/\s+/', '', (string) ( $channel['value'] ?? '' ) ) );
						if ( ! isset( $merged_channels[ $key ] ) ) { $channel['is_primary'] = 0; $merged_channels[ $key ] = $channel; }
						if ( 'email' === ( $channel['type'] ?? '' ) && ! empty( $channel['value'] ) ) $aliases[] = strtolower( sanitize_email( $channel['value'] ) );
					}
				}
				if ( $merged_channels ) self::set_contact_meta( $primary_id, 'contacthub_contact_channels', array_values( $merged_channels ) );
				foreach ( $donors as $donor ) {
					foreach ( self::contact_meta( $donor ) as $key => $value ) if ( ! array_key_exists( 'meta__' . $key, $resolved ) && ( ! isset( $primary_meta[ $key ] ) || '' === (string) $primary_meta[ $key ] ) ) self::set_contact_meta( $primary_id, $key, $value );
					$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$tables['contact_tags']}` (contact_id,tag_id,assigned_at,assigned_by,source) SELECT %d,tag_id,assigned_at,assigned_by,source FROM `{$tables['contact_tags']}` WHERE contact_id=%d", $primary_id, $donor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$tables['company_contacts']}` (company_id,contact_id,relationship_type,position,is_primary,created_at,updated_at) SELECT company_id,%d,relationship_type,position,is_primary,created_at,%s FROM `{$tables['company_contacts']}` WHERE contact_id=%d", $primary_id, $now, $donor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->update( $tables['consents'], array( 'contact_id' => $primary_id ), array( 'contact_id' => $donor ) );
					$wpdb->update( $tables['activities'], array( 'contact_id' => $primary_id ), array( 'contact_id' => $donor ) );
					$wpdb->delete( $tables['contact_tags'], array( 'contact_id' => $donor ) );
					$wpdb->delete( $tables['company_contacts'], array( 'contact_id' => $donor ) );
				}
				self::set_contact_meta( $primary_id, 'merged_contact_ids', $ids );
				self::set_contact_meta( $primary_id, 'merged_email_aliases', array_values( array_unique( array_filter( $aliases ) ) ) );
				$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET deleted_at=%s, updated_at=%s WHERE id IN (" . implode( ',', array_fill( 0, count( $donors ), '%d' ) ) . ')', array_merge( array( $now, $now ), $donors ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table and numeric placeholders.
				self::add_activity( $primary_id, 0, 'merge', __( 'Contact records merged', 'contacthub' ), sprintf( __( 'Source record IDs moved to Trash: %s', 'contacthub' ), implode( ', ', $donors ) ) );
			} else {
				$fields = array( 'name', 'legal_name', 'industry', 'website', 'email', 'phone_number', 'vat_id', 'street', 'address_2', 'postcode', 'city', 'country' );
				$update = array();
				foreach ( $fields as $field ) {
					$source = absint( $choices[ $field ] ?? $primary_id );
					if ( isset( $by_id[ $source ] ) && '' !== trim( (string) $by_id[ $source ][ $field ] ) ) $update[ $field ] = $by_id[ $source ][ $field ];
					if ( array_key_exists( $field, $resolved ) ) {
						$value = 'website' === $field ? esc_url_raw( $resolved[ $field ] ) : ( 'email' === $field ? sanitize_email( $resolved[ $field ] ) : sanitize_text_field( $resolved[ $field ] ) );
						$update[ $field ] = 'country' === $field ? strtoupper( $value ) : $value;
					}
				}
				$update['updated_at'] = $now;
				if ( false === $wpdb->update( $table, $update, array( 'id' => $primary_id ) ) ) throw new Exception( 'update' );
				foreach ( $donors as $donor ) {
					foreach ( self::company_meta( $donor ) as $key => $value ) if ( '' === (string) ( self::company_meta( $primary_id )[ $key ] ?? '' ) ) self::set_company_meta( $primary_id, $key, $value );
					$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$tables['company_contacts']}` (company_id,contact_id,relationship_type,position,is_primary,created_at,updated_at) SELECT %d,contact_id,relationship_type,position,is_primary,created_at,%s FROM `{$tables['company_contacts']}` WHERE company_id=%d", $primary_id, $now, $donor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->update( $tables['activities'], array( 'company_id' => $primary_id ), array( 'company_id' => $donor ) );
					$wpdb->delete( $tables['company_contacts'], array( 'company_id' => $donor ) );
				}
				self::set_company_meta( $primary_id, 'merged_company_ids', $ids );
				$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET deleted_at=%s, updated_at=%s WHERE id IN (" . implode( ',', array_fill( 0, count( $donors ), '%d' ) ) . ')', array_merge( array( $now, $now ), $donors ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::add_activity( 0, $primary_id, 'merge', __( 'Company records merged', 'contacthub' ), sprintf( __( 'Source record IDs moved to Trash: %s', 'contacthub' ), implode( ', ', $donors ) ) );
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) throw new Exception( 'commit' );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'contacthub_merge_failed', __( 'The records could not be merged safely. No changes were retained.', 'contacthub' ) );
		}
		return array( 'primary_id' => $primary_id, 'merged' => count( $donors ), 'trashed_ids' => $donors );
	}

	public static function import_woocommerce( $dry_run = true ) {
		if ( ! self::ready() || ! function_exists( 'wc_get_orders' ) ) {
			return new WP_Error( 'contacthub_woocommerce_import_unavailable', __( 'WooCommerce and the ContactHub database must be available for this import.', 'contacthub' ) );
		}
		$customers = array();
		$page = 1;
		do {
			$args = array( 'limit' => 100, 'page' => $page, 'paginate' => true, 'return' => 'objects', 'orderby' => 'date', 'order' => 'DESC' );
			if ( function_exists( 'wc_get_order_statuses' ) ) $args['status'] = array_keys( wc_get_order_statuses() );
			try {
				$result = wc_get_orders( $args );
			} catch ( Throwable $error ) {
				return new WP_Error( 'contacthub_woocommerce_import_read', __( 'WooCommerce customer data could not be read. No ContactHub data was changed.', 'contacthub' ) );
			}
			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'contacthub_woocommerce_import_read', __( 'WooCommerce customer data could not be read. No ContactHub data was changed.', 'contacthub' ) );
			}
			if ( is_object( $result ) && isset( $result->orders ) ) {
				$orders = (array) $result->orders;
			} elseif ( is_array( $result ) ) {
				$orders = $result;
			} else {
				return new WP_Error( 'contacthub_woocommerce_import_read', __( 'WooCommerce customer data could not be read. No ContactHub data was changed.', 'contacthub' ) );
			}
			foreach ( $orders as $order ) {
				if ( ! is_object( $order ) || ! method_exists( $order, 'get_billing_email' ) ) continue;
				$email = strtolower( sanitize_email( $order->get_billing_email() ) );
				if ( ! $email || isset( $customers[ $email ] ) ) continue;
				$customers[ $email ] = array(
					'email' => $email, 'user_id' => absint( $order->get_customer_id() ), 'first_name' => $order->get_billing_first_name(), 'last_name' => $order->get_billing_last_name(),
					'phone' => $order->get_billing_phone(), 'company' => $order->get_billing_company(), 'address_1' => $order->get_billing_address_1(), 'address_2' => $order->get_billing_address_2(),
					'postcode' => $order->get_billing_postcode(), 'city' => $order->get_billing_city(), 'state' => $order->get_billing_state(), 'country' => $order->get_billing_country(),
				);
			}
			$max_pages = is_object( $result ) && isset( $result->max_num_pages ) ? max( 1, absint( $result->max_num_pages ) ) : 1;
			$page++;
		} while ( $page <= $max_pages );

		$user_args = array( 'fields' => array( 'ID', 'user_email' ) );
		$roles = array_filter( array( get_role( 'customer' ) ? 'customer' : '', get_role( 'subscriber' ) ? 'subscriber' : '' ) );
		$users = array();
		if ( $roles ) {
			$user_args['role__in'] = array_values( $roles );
			try {
				$users = get_users( $user_args );
			} catch ( Throwable $error ) {
				return new WP_Error( 'contacthub_woocommerce_import_read', __( 'WooCommerce customer data could not be read. No ContactHub data was changed.', 'contacthub' ) );
			}
		}
		foreach ( $users as $user ) {
			$email = strtolower( sanitize_email( get_user_meta( $user->ID, 'billing_email', true ) ?: $user->user_email ) );
			if ( ! $email ) continue;
			$row = $customers[ $email ] ?? array( 'email' => $email );
			$map = array( 'first_name', 'last_name', 'phone', 'company', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' );
			foreach ( $map as $field ) {
				$key = 'billing_' . $field;
				$value = get_user_meta( $user->ID, $key, true );
				if ( '' === (string) $value && in_array( $field, array( 'first_name', 'last_name' ), true ) ) $value = get_user_meta( $user->ID, $field, true );
				if ( '' !== (string) $value ) $row[ $field ] = $value;
			}
			$row['user_id'] = (int) $user->ID;
			$customers[ $email ] = $row;
		}

		$report = array( 'found' => count( $customers ), 'new' => 0, 'updated' => 0, 'companies' => 0, 'skipped' => 0, 'dry_run' => $dry_run ? 1 : 0, 'completed_at' => current_time( 'mysql' ) );
		foreach ( $customers as $data ) {
			$existing = self::contact_by_email( $data['email'], true );
			if ( $existing && ! empty( $existing['deleted_at'] ) ) { $report['skipped']++; continue; }
			$existing ? $report['updated']++ : $report['new']++;
			if ( $dry_run ) continue;
			$payload = array( 'email' => $data['email'] );
			if ( ! $existing ) $payload['source'] = 'woocommerce';
			foreach ( array( 'first_name', 'last_name' ) as $field ) {
				if ( ! $existing || '' !== trim( (string) ( $data[ $field ] ?? '' ) ) ) $payload[ $field ] = $data[ $field ] ?? '';
			}
			if ( ! $existing || '' !== trim( (string) ( $data['phone'] ?? '' ) ) ) $payload['primary_phone'] = $data['phone'] ?? '';
			if ( ! empty( $data['user_id'] ) ) $payload['user_id'] = absint( $data['user_id'] );
			$contact_id = $existing ? absint( $existing['id'] ) : self::create_contact( $payload );
			if ( is_wp_error( $contact_id ) || ! $contact_id ) { $report['skipped']++; continue; }
			if ( $existing ) self::update_contact( $contact_id, $payload );
			$address = array();
			foreach ( array( 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' ) as $field ) {
				$value = $data[ $field ] ?? '';
				if ( $existing && '' === trim( (string) $value ) ) $value = $existing['meta'][ $field ] ?? '';
				$address[ $field ] = $value;
			}
			if ( ! self::update_contact_address( $contact_id, $address ) ) { $report['skipped']++; continue; }
			if ( ! empty( $data['company'] ) ) {
				$company_id = self::company_by_name( $data['company'], true );
				if ( $company_id && ! self::company( $company_id ) ) { $report['skipped']++; continue; }
				if ( ! $company_id ) { $company_id = self::create_company( array( 'name' => $data['company'] ) ); $report['companies']++; }
				if ( $company_id ) self::link_company_contact( $company_id, $contact_id, '', false );
			}
		}
		if ( ! $dry_run ) update_option( 'contacthub_last_woocommerce_import', $report, false );
		return $report;
	}

	private static function normalize_first_name( $first_name ) {
		$first = trim( preg_split( '/[\s\-]+/u', sanitize_text_field( $first_name ) )[0] ?? '' );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $first, 'UTF-8' ) : strtolower( $first );
	}

	public static function delete_tag( $tag_id ) {
		global $wpdb;
		$tables = ContactHub_Schema::table_names();
		$tag_id = absint( $tag_id );
		if ( ! $tag_id ) return false;
		$wpdb->delete( $tables['contact_tags'], array( 'tag_id' => $tag_id ) );
		return false !== $wpdb->delete( $tables['tags'], array( 'id' => $tag_id ) );
	}

	public static function assign_tag( $contact_id, $tag_id, $assign = true ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['contact_tags'];
		if ( ! $assign ) {
			return false !== $wpdb->delete( $table, array( 'contact_id' => absint( $contact_id ), 'tag_id' => absint( $tag_id ) ) );
		}
		return false !== $wpdb->replace( $table, array( 'contact_id' => absint( $contact_id ), 'tag_id' => absint( $tag_id ), 'assigned_at' => current_time( 'mysql' ), 'assigned_by' => get_current_user_id(), 'source' => 'manual' ) );
	}

	public static function record_consent( $contact_id, $status, $source, $date, $note = '' ) {
		global $wpdb;
		$tables = ContactHub_Schema::table_names();
		$occurred = $date . ' 00:00:00';
		$wpdb->insert( $tables['consents'], array( 'contact_id' => absint( $contact_id ), 'purpose' => 'marketing', 'status' => sanitize_key( $status ), 'legal_basis' => 'consent', 'source' => sanitize_text_field( $source ), 'proof_text' => $note, 'actor_user_id' => get_current_user_id(), 'occurred_at' => $occurred, 'created_at' => current_time( 'mysql' ) ) );
		self::set_contact_meta( $contact_id, 'optin_status', 'granted' === $status ? 'confirmed' : 'unsubscribed' );
		self::set_contact_meta( $contact_id, 'marketing_consent_source', $source );
		self::set_contact_meta( $contact_id, 'granted' === $status ? 'marketing_consent_date' : 'marketing_consent_revoked_date', $date );
		return self::add_activity( $contact_id, 0, 'consent', 'granted' === $status ? __( 'Marketing consent granted manually', 'contacthub' ) : __( 'Marketing consent revoked manually', 'contacthub' ), $note );
	}

	public static function add_activity( $contact_id, $company_id, $type, $title, $content, $occurred_at = '' ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['activities'];
		$occurred_at = preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $occurred_at ) ? $occurred_at : current_time( 'mysql' );
		return false !== $wpdb->insert( $table, array( 'contact_id' => $contact_id ? absint( $contact_id ) : null, 'company_id' => $company_id ? absint( $company_id ) : null, 'activity_type' => sanitize_key( $type ), 'title' => sanitize_text_field( $title ), 'content' => wp_kses_post( $content ), 'source' => 'contacthub', 'actor_user_id' => get_current_user_id(), 'occurred_at' => $occurred_at, 'created_at' => current_time( 'mysql' ) ) );
	}

	public static function activities( $contact_id = 0, $company_id = 0 ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['activities'];
		if ( $contact_id ) {
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, activity_type, title AS summary, content, occurred_at AS created_at FROM `{$table}` WHERE contact_id = %d ORDER BY occurred_at DESC LIMIT 50", absint( $contact_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		}
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, activity_type, title AS summary, content, occurred_at AS created_at FROM `{$table}` WHERE company_id = %d ORDER BY occurred_at DESC LIMIT 50", absint( $company_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
	}

	public static function link_user( $contact_id, $user_id ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()['contacts'];
		return false !== $wpdb->update( $table, array( 'wp_user_id' => absint( $user_id ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $contact_id ) ) );
	}

	public static function sync_registration( $user_id, $data ) {
		$contact = self::contact_by_email( $data['email'] );
		$contact_id = $contact ? (int) $contact['id'] : self::create_contact( array_merge( $data, array( 'user_id' => $user_id, 'source' => 'registration', 'optin_status' => 'unconfirmed' ) ) );
		if ( is_wp_error( $contact_id ) ) return $contact_id;
		self::update_contact( $contact_id, array( 'user_id' => $user_id, 'position' => $data['position'], 'primary_phone' => $data['phone'], 'anrede' => $data['salutation'] ?? '' ) );
		self::update_contact_address(
			$contact_id,
			array(
				'address_1' => $data['billing_address_1'], 'address_2' => $data['billing_address_2'],
				'postcode' => $data['billing_postcode'], 'city' => $data['billing_city'],
				'state' => '', 'country' => $data['billing_country'],
			)
		);
		if ( ! empty( $data['company'] ) ) {
			$company_id = self::company_by_name( $data['company'], true );
			if ( $company_id && ! self::company( $company_id ) ) return $contact_id;
			if ( ! $company_id ) $company_id = self::create_company( array( 'name' => $data['company'], 'industry' => $data['industry'], 'website' => $data['website'], 'vat_id' => $data['vat_id'] ) );
			if ( $company_id ) { self::update_company_profile( $company_id, $data ); self::link_company_contact( $company_id, $contact_id, $data['position'] ?? '', true ); }
		}
		return $contact_id;
	}

	public static function migrate_groundhogg() {
		if ( ! self::ready() || ( ! function_exists( 'wp_get_ability' ) && ! class_exists( '\\Groundhogg\\Contact' ) ) ) return new WP_Error( 'contacthub_migration_unavailable', __( 'The migration prerequisites are not available.', 'contacthub' ) );
		$report = array( 'contacts' => 0, 'source_contacts' => 0, 'companies' => 0, 'tags' => 0, 'source_tags' => 0, 'assignments' => 0, 'source_assignments' => 0, 'missing_tags' => array(), 'skipped_tags' => array(), 'skipped' => 0, 'errors' => 0 );
		$source_map = array();
		$source_tag_map = array();
		$source_tags = self::groundhogg_tags();
		$report['source_tags'] = count( $source_tags );
		$system_tag_ids = self::groundhogg_system_tag_ids();
		foreach ( $source_tags as $source_tag ) {
			$name = self::value( $source_tag, array( 'name', 'tag_name' ) );
			$source_tag_id = absint( self::value( $source_tag, array( 'id', 'tag_id', 'ID' ) ) );
			if ( $source_tag_id && in_array( $source_tag_id, $system_tag_ids, true ) ) {
				$report['skipped_tags'][] = sprintf( __( 'Internal Groundhogg status tag “%s” was intentionally omitted.', 'contacthub' ), $name ?: '#' . $source_tag_id );
				continue;
			}
			if ( ! $name || ! $source_tag_id ) {
				$report['missing_tags'][] = $source_tag_id ? sprintf( __( 'Tag ID %d has no name.', 'contacthub' ), $source_tag_id ) : __( 'A Groundhogg tag has no readable ID.', 'contacthub' );
				continue;
			}
			$new_tag_id = self::create_tag( $name, self::value( $source_tag, array( 'description', 'tag_description' ) ) );
			if ( $new_tag_id ) {
				$source_tag_map[ $source_tag_id ] = $new_tag_id;
			} else {
				$report['missing_tags'][] = sprintf( __( 'Tag “%s” could not be stored.', 'contacthub' ), $name );
			}
		}
		$rows = self::groundhogg_contacts_for_migration();
		$report['source_contacts'] = count( $rows );
		foreach ( $rows as $row ) {
				$source_id = absint( self::value( $row, array( 'id', 'ID' ) ) );
				if ( ! $source_id ) { $report['errors']++; continue; }
				$email = sanitize_email( self::value( $row, array( 'email' ) ) );
				$contact = self::contact_by_source_id( $source_id );
				if ( ! $contact && $email ) $contact = self::contact_by_email( $email, true );
				if ( $contact && ! empty( $contact['deleted_at'] ) ) { $report['skipped']++; continue; }
				$optin = self::optin_value( $row );
				$data = array( 'email' => $email, 'first_name' => self::value( $row, array( 'first_name' ) ), 'last_name' => self::value( $row, array( 'last_name' ) ), 'user_id' => absint( self::value( $row, array( 'user_id' ) ) ), 'source' => 'groundhogg', 'groundhogg_contact_id' => $source_id, 'optin_status' => $optin, 'primary_phone' => self::meta_value( $row, 'primary_phone' ), 'titel_vorangestellt' => self::meta_value( $row, 'titel_vorangestellt' ), 'titel_nachgestellt' => self::meta_value( $row, 'titel_nachgestellt' ), 'berufsbezeichnung' => self::meta_value( $row, 'berufsbezeichnung' ), 'meta' => array( 'email_confirmed' => in_array( $optin, array( 2, 4, 5, 'confirmed', 'weekly', 'monthly' ), true ) ? 1 : 0, 'address_1' => self::meta_value( $row, 'street_address_1' ) ?: self::meta_value( $row, 'address_1' ), 'address_2' => self::meta_value( $row, 'street_address_2' ) ?: self::meta_value( $row, 'address_2' ), 'postcode' => self::meta_value( $row, 'postal_zip' ) ?: self::meta_value( $row, 'postcode' ), 'city' => self::meta_value( $row, 'city' ), 'state' => self::meta_value( $row, 'region' ) ?: self::meta_value( $row, 'state' ), 'country' => self::meta_value( $row, 'country' ) ) );
				$contact_id = $contact ? (int) $contact['id'] : self::create_contact( $data );
				if ( is_wp_error( $contact_id ) || ! $contact_id ) { $report['errors']++; continue; }
				self::update_contact( $contact_id, $data );
				$source_map[ $source_id ] = $contact_id;
				$report['contacts']++;
				$contact_tags = isset( $row['tags'] ) ? (array) $row['tags'] : array();
				if ( ! $contact_tags && $source_id ) {
					$contact_tags = self::groundhogg_contact_tags( $source_id );
				}
				foreach ( $contact_tags as $tag ) {
					if ( is_scalar( $tag ) ) {
						$source_tag_id = absint( $tag );
						if ( in_array( $source_tag_id, $system_tag_ids, true ) ) continue;
						$report['source_assignments']++;
						if ( isset( $source_tag_map[ $source_tag_id ] ) ) {
							self::assign_tag( $contact_id, $source_tag_map[ $source_tag_id ] );
							$report['assignments']++;
						} else {
							$report['missing_tags'][] = sprintf( __( 'Tag ID %d is assigned to a contact but was not found in the tag list.', 'contacthub' ), $source_tag_id );
						}
						continue;
					}
					$name = self::value( $tag, array( 'name', 'tag_name' ) );
					$source_tag_id = absint( self::value( $tag, array( 'id', 'tag_id', 'ID' ) ) );
					if ( $source_tag_id && in_array( $source_tag_id, $system_tag_ids, true ) ) continue;
					$report['source_assignments']++;
					if ( $source_tag_id && isset( $source_tag_map[ $source_tag_id ] ) ) {
						self::assign_tag( $contact_id, $source_tag_map[ $source_tag_id ] );
						$report['assignments']++;
						continue;
					}
					if ( ! $name ) {
						$report['missing_tags'][] = sprintf( __( 'An unreadable tag assignment was found on Groundhogg contact %d.', 'contacthub' ), $source_id );
						continue;
					}
					$tag_id = self::create_tag( $name );
					if ( $tag_id ) {
						self::assign_tag( $contact_id, $tag_id );
						$report['assignments']++;
						if ( $source_tag_id ) $source_tag_map[ $source_tag_id ] = $tag_id;
					}
				}
		}
		$report['tags'] = count( self::tags() );
		$report['missing_tags'] = array_values( array_unique( $report['missing_tags'] ) );
		$report['skipped_tags'] = array_values( array_unique( $report['skipped_tags'] ) );
		$company_offset = 0;
		$seen_company_pages = array();
		do {
			$companies = self::ability( 'groundhogg-companies/list-companies', array( 'limit' => 100, 'offset' => $company_offset, 'expand' => array( 'meta', 'contacts' ) ) );
			$company_rows = is_array( $companies ) && isset( $companies['companies'] ) ? (array) $companies['companies'] : array();
			$company_fingerprint = md5( wp_json_encode( array_map( static function ( $company ) { return self::value( $company, array( 'id', 'ID' ) ); }, $company_rows ) ) );
			if ( $company_rows && isset( $seen_company_pages[ $company_fingerprint ] ) ) break;
			$seen_company_pages[ $company_fingerprint ] = true;
			foreach ( $company_rows as $company ) {
				$name = self::value( $company, array( 'name', 'company_name' ) );
				if ( ! $name ) { $report['skipped']++; continue; }
				$source_company_id = absint( self::value( $company, array( 'id', 'ID' ) ) );
				$company_id = self::company_by_source_id( $source_company_id );
				if ( $company_id && ! self::company( $company_id ) ) { $report['skipped']++; continue; }
				if ( $company_id ) {
					self::update_company( $company_id, 'name', $name );
					self::update_company( $company_id, 'industry', self::value( $company, array( 'industry' ) ) );
					self::update_company( $company_id, 'domain', self::value( $company, array( 'domain', 'website' ) ) );
				} else {
					$company_id = self::create_company( array( 'name' => $name, 'industry' => self::value( $company, array( 'industry' ) ), 'website' => self::value( $company, array( 'domain', 'website' ) ) ) );
				}
				if ( ! $company_id ) { $report['errors']++; continue; }
				self::set_company_meta( $company_id, 'groundhogg_company_id', $source_company_id );
				self::update_company( $company_id, 'phone', self::value( $company, array( 'phone' ) ) );
				self::update_company( $company_id, 'address', self::value( $company, array( 'address' ) ) );
				self::update_company( $company_id, 'description', self::value( $company, array( 'description' ) ) );
				$primary = absint( self::value( $company, array( 'primary_contact_id' ) ) );
				$source_contacts = is_array( $company ) && isset( $company['contacts'] ) ? (array) $company['contacts'] : ( is_object( $company ) && method_exists( $company, 'get_contacts' ) ? (array) $company->get_contacts() : array() );
				foreach ( $source_contacts as $source_contact ) {
					$source_contact_id = absint( self::value( $source_contact, array( 'id', 'ID' ) ) );
					if ( isset( $source_map[ $source_contact_id ] ) ) self::link_company_contact( $company_id, $source_map[ $source_contact_id ], '', $primary === $source_contact_id );
				}
				$report['companies']++;
			}
			$company_offset += count( $company_rows );
		} while ( $company_rows && $company_offset < 1000000 );
		$report['completed_at'] = current_time( 'mysql' );
		$report = (array) apply_filters( 'contacthub_groundhogg_migration_report', $report );
		update_option( 'contacthub_last_migration_report', $report, false );
		return $report;
	}

	private static function hydrate_contact( $row ) {
		$meta = self::contact_meta( $row['id'] );
		$row['user_id'] = (int) $row['wp_user_id'];
		$row['meta'] = $meta;
		$row['optin_status'] = isset( $meta['optin_status'] ) ? $meta['optin_status'] : 'unconfirmed';
		$row['tags'] = self::contact_tags( $row['id'] );
		return $row;
	}

	private static function hydrate_company( $row ) {
		global $wpdb;
		$tables = ContactHub_Schema::table_names();
		$meta = self::company_meta( $row['id'] );
		$row['domain'] = $row['website'];
		$row['phone'] = $row['phone_number'];
		$row['address'] = isset( $meta['address'] ) ? $meta['address'] : trim( $row['street'] . "\n" . $row['postcode'] . ' ' . $row['city'] );
		$row['description'] = isset( $meta['description'] ) ? $meta['description'] : '';
		$row['primary_contact_id'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT contact_id FROM `{$tables['company_contacts']}` WHERE company_id = %d AND is_primary = 1 LIMIT 1", $row['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT contact_id FROM `{$tables['company_contacts']}` WHERE company_id = %d ORDER BY is_primary DESC, id ASC", $row['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$row['contacts'] = array_values( array_filter( array_map( array( __CLASS__, 'contact' ), $ids ) ) );
		return $row;
	}

	private static function contact_meta( $id ) { return self::meta_rows( 'contactmeta', 'contact_id', $id ); }
	private static function company_meta( $id ) { return self::meta_rows( 'companymeta', 'company_id', $id ); }
	private static function meta_rows( $table_key, $id_key, $id ) {
		global $wpdb;
		$table = ContactHub_Schema::table_names()[ $table_key ];
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM `{$table}` WHERE {$id_key} = %d", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed key and internal table.
		$meta = array(); foreach ( (array) $rows as $row ) $meta[ $row['meta_key'] ] = maybe_unserialize( $row['meta_value'] ); return $meta;
	}
	private static function set_contact_meta( $id, $key, $value ) { return self::set_meta( 'contactmeta', 'contact_id', $id, $key, $value ); }
	private static function set_company_meta( $id, $key, $value ) { return self::set_meta( 'companymeta', 'company_id', $id, $key, $value ); }
	private static function set_meta( $table_key, $id_key, $id, $key, $value ) {
		global $wpdb; $table = ContactHub_Schema::table_names()[ $table_key ]; $key = sanitize_key( $key );
		$meta_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM `{$table}` WHERE {$id_key} = %d AND meta_key = %s LIMIT 1", absint( $id ), $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed key and internal table.
		$data = array( 'meta_value' => maybe_serialize( $value ) );
		return $meta_id ? false !== $wpdb->update( $table, $data, array( 'meta_id' => $meta_id ) ) : false !== $wpdb->insert( $table, array_merge( array( $id_key => absint( $id ), 'meta_key' => $key ), $data ) );
	}
	private static function contact_tags( $id ) {
		global $wpdb; $t = ContactHub_Schema::table_names();
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT tags.id, tags.name FROM `{$t['tags']}` tags INNER JOIN `{$t['contact_tags']}` rel ON rel.tag_id = tags.id WHERE rel.contact_id = %d ORDER BY tags.name", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table names.
	}
	private static function contact_by_source_id( $source_id ) {
		global $wpdb; $tables = ContactHub_Schema::table_names();
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT contact_id FROM `{$tables['contactmeta']}` WHERE meta_key = 'groundhogg_contact_id' AND meta_value = %s LIMIT 1", (string) absint( $source_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		return $id ? self::contact( $id, true ) : false;
	}
	private static function company_by_source_id( $source_id ) {
		global $wpdb; $table = ContactHub_Schema::table_names()['companymeta'];
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT company_id FROM `{$table}` WHERE meta_key = 'groundhogg_company_id' AND meta_value = %s LIMIT 1", (string) absint( $source_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
	}
	private static function groundhogg_tags() {
		$rows = array();
		$offset = 0;
		$limit = 100;
		$seen_pages = array();
		do {
			$result = self::ability( 'groundhogg/list-tags', array( 'limit' => $limit, 'offset' => $offset ) );
			$page_rows = is_array( $result ) && isset( $result['tags'] ) ? (array) $result['tags'] : array();
			$fingerprint = md5( wp_json_encode( array_map( static function ( $tag ) { return self::value( $tag, array( 'id', 'tag_id', 'ID' ) ); }, $page_rows ) ) );
			if ( $page_rows && isset( $seen_pages[ $fingerprint ] ) ) break;
			$seen_pages[ $fingerprint ] = true;
			$rows = array_merge( $rows, $page_rows );
			$offset += count( $page_rows );
		} while ( $page_rows && $offset < 100000 );
		if ( ! $rows && function_exists( '\\Groundhogg\\get_db' ) ) {
			try {
				$rows = (array) \Groundhogg\get_db( 'tags' )->query( array( 'limit' => 100000, 'orderby' => 'tag_name', 'order' => 'ASC' ) );
			} catch ( Throwable $e ) {
				$rows = array();
			}
		}
		$unique = array();
		foreach ( $rows as $row ) {
			$id = absint( self::value( $row, array( 'id', 'tag_id', 'ID' ) ) );
			if ( $id ) $unique[ $id ] = $row;
		}
		return array_values( $unique );
	}
	private static function groundhogg_system_tag_ids() {
		$options = array(
			'gh_confirmed_tag', 'gh_unconfirmed_tag', 'gh_unsubscribed_tag', 'gh_spammed_tag',
			'gh_bounced_tag', 'gh_complained_tag', 'gh_weekly_tag', 'gh_monthly_tag',
			'gh_marketable_tag', 'gh_non_marketable_tag',
		);
		return array_values( array_filter( array_map( 'absint', array_map( 'get_option', $options ) ) ) );
	}
	private static function groundhogg_contact_tags( $contact_id ) {
		if ( ! class_exists( '\\Groundhogg\\Contact' ) ) return array();
		try {
			$contact = new \Groundhogg\Contact( absint( $contact_id ) );
			foreach ( array( 'get_tags', 'get_tag_ids' ) as $method ) {
				if ( method_exists( $contact, $method ) ) {
					$tags = $contact->{$method}();
					if ( is_array( $tags ) ) return $tags;
				}
			}
		} catch ( Throwable $e ) {
			return array();
		}
		return array();
	}
	private static function groundhogg_contacts_for_migration() {
		global $wpdb;
		$contacts = array();
		if ( class_exists( '\\Groundhogg\\Contact' ) ) {
			$table = $wpdb->prefix . 'gh_contacts';
			try {
				$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
				if ( $table === $exists ) {
					$ids = (array) $wpdb->get_col( "SELECT ID FROM `{$table}` ORDER BY ID ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed Groundhogg table name using the active WordPress prefix.
					foreach ( $ids as $id ) {
						try {
							$contact = new \Groundhogg\Contact( absint( $id ) );
							if ( method_exists( $contact, 'exists' ) && ! $contact->exists() ) continue;
							if ( class_exists( '\\Groundhogg\\Abilities\\Schemas\\Contact_Schema' ) ) {
								try {
									$contact = \Groundhogg\Abilities\Schemas\Contact_Schema::transform( $contact, array( 'tags', 'meta', 'work_details' ) );
								} catch ( Throwable $error ) {
									// The original Groundhogg contact object still exposes its standard fields and metadata.
								}
							}
							$source_id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
							if ( $source_id ) $contacts[ $source_id ] = $contact;
						} catch ( Throwable $error ) {
							continue;
						}
					}
					return array_values( $contacts );
				}
			} catch ( Throwable $error ) {
				$contacts = array();
			}
		}

		$offset = 0;
		$seen_pages = array();
		do {
			$result = self::ability( 'groundhogg/search-contacts', array( 'limit' => 100, 'offset' => $offset, 'expand' => array( 'tags', 'meta', 'work_details' ) ) );
			$rows = is_array( $result ) && isset( $result['contacts'] ) ? (array) $result['contacts'] : array();
			$fingerprint = md5( wp_json_encode( array_map( static function ( $contact ) { return self::value( $contact, array( 'id', 'ID' ) ); }, $rows ) ) );
			if ( $rows && isset( $seen_pages[ $fingerprint ] ) ) break;
			$seen_pages[ $fingerprint ] = true;
			foreach ( $rows as $contact ) {
				$source_id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
				if ( $source_id ) $contacts[ $source_id ] = $contact;
			}
			$offset += count( $rows );
		} while ( $rows && $offset < 1000000 );
		return array_values( $contacts );
	}
	private static function ability( $name, $input ) { try { $ability = wp_get_ability( $name ); if ( ! $ability ) return false; $result = $ability->execute( $input ); return is_wp_error( $result ) ? false : $result; } catch ( Throwable $e ) { return false; } }
	private static function value( $source, $keys, $default = '' ) { foreach ( $keys as $key ) { if ( is_array( $source ) && isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) && '' !== (string) $source[ $key ] ) return (string) $source[ $key ]; if ( is_object( $source ) && method_exists( $source, $key ) ) { $value = $source->{$key}(); if ( is_scalar( $value ) && '' !== (string) $value ) return (string) $value; } $getter = 'get_' . $key; if ( is_object( $source ) && method_exists( $source, $getter ) ) { $value = $source->{$getter}(); if ( is_scalar( $value ) && '' !== (string) $value ) return (string) $value; } if ( is_object( $source ) && isset( $source->{$key} ) && is_scalar( $source->{$key} ) ) return (string) $source->{$key}; } return $default; }
	private static function meta_value( $source, $key ) { if ( is_array( $source ) && isset( $source['meta'][ $key ] ) ) return is_scalar( $source['meta'][ $key ] ) ? (string) $source['meta'][ $key ] : ''; if ( is_object( $source ) && method_exists( $source, 'get_meta' ) ) { try { $value = $source->get_meta( $key ); return is_scalar( $value ) ? (string) $value : ''; } catch ( Throwable $error ) { return ''; } } return ''; }
	private static function optin_value( $source ) { $value = is_array( $source ) && isset( $source['optin_status'] ) ? $source['optin_status'] : self::value( $source, array( 'optin_status' ), 'unconfirmed' ); if ( is_array( $value ) && isset( $value['value'] ) ) return is_numeric( $value['value'] ) ? (int) $value['value'] : sanitize_key( $value['value'] ); if ( is_scalar( $value ) ) return is_numeric( $value ) ? (int) $value : sanitize_key( $value ); return 'unconfirmed'; }
}
