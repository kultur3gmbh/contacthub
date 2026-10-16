<?php

defined( 'ABSPATH' ) || exit;

final class ContactHub_Schema {
	const VERSION = '1.1.1';
	const VERSION_OPTION = 'contacthub_db_version';

	/**
	 * Runs database changes only when the stored schema is older.
	 *
	 * This method is intentionally cheap on normal requests and can therefore
	 * be called before any ContactHub data is queried.
	 */
	public static function maybe_upgrade() {
		if ( self::VERSION === (string) get_option( self::VERSION_OPTION, '' ) ) {
			return true;
		}
		return self::install();
	}

	public static function table_names() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'chb_';
		return array(
			'contacts'         => $prefix . 'contacts',
			'companies'        => $prefix . 'companies',
			'company_contacts' => $prefix . 'company_contacts',
			'tags'             => $prefix . 'tags',
			'contact_tags'     => $prefix . 'contact_tags',
			'consents'         => $prefix . 'consents',
			'activities'       => $prefix . 'activities',
			'contactmeta'      => $prefix . 'contactmeta',
			'companymeta'      => $prefix . 'companymeta',
			'salutations'      => $prefix . 'salutations',
		);
	}

	public static function status() {
		$present = array();
		foreach ( self::table_names() as $key => $table ) {
			$present[ $key ] = self::table_exists( $table );
		}
		return array(
			'present' => $present,
			'count'   => count( array_filter( $present ) ),
			'total'   => count( $present ),
			'version' => (string) get_option( self::VERSION_OPTION, '' ),
		);
	}

	public static function install() {
		global $wpdb;
		$status = self::status();
		if ( $status['total'] === $status['count'] && self::VERSION === $status['version'] ) {
			return true;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$tables = self::table_names();
		$charset = $wpdb->get_charset_collate();
		$sql = array();

		$sql[] = "CREATE TABLE {$tables['contacts']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wp_user_id bigint(20) unsigned DEFAULT NULL,
			email varchar(190) DEFAULT NULL,
			email_normalized varchar(190) DEFAULT NULL,
			title_prefix varchar(100) NOT NULL DEFAULT '',
			first_name varchar(190) NOT NULL DEFAULT '',
			last_name varchar(190) NOT NULL DEFAULT '',
			position varchar(190) NOT NULL DEFAULT '',
			phone_country_code varchar(12) NOT NULL DEFAULT '',
			phone_number varchar(60) NOT NULL DEFAULT '',
			vat_id varchar(40) NOT NULL DEFAULT '',
			vat_status varchar(30) NOT NULL DEFAULT 'unchecked',
			vat_verified_at datetime DEFAULT NULL,
			status varchar(30) NOT NULL DEFAULT 'active',
			source varchar(60) NOT NULL DEFAULT 'manual',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY wp_user_id (wp_user_id),
			UNIQUE KEY email_normalized (email_normalized),
			KEY name_lookup (last_name,first_name),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['companies']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			legal_name varchar(190) NOT NULL DEFAULT '',
			industry varchar(190) NOT NULL DEFAULT '',
			website varchar(255) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone_country_code varchar(12) NOT NULL DEFAULT '',
			phone_number varchar(60) NOT NULL DEFAULT '',
			vat_id varchar(40) NOT NULL DEFAULT '',
			vat_status varchar(30) NOT NULL DEFAULT 'unchecked',
			vat_verified_at datetime DEFAULT NULL,
			street varchar(190) NOT NULL DEFAULT '',
			address_2 varchar(190) NOT NULL DEFAULT '',
			postcode varchar(30) NOT NULL DEFAULT '',
			city varchar(120) NOT NULL DEFAULT '',
			country char(2) NOT NULL DEFAULT '',
			status varchar(30) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY name (name),
			KEY vat_id (vat_id),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['company_contacts']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			company_id bigint(20) unsigned NOT NULL,
			contact_id bigint(20) unsigned NOT NULL,
			relationship_type varchar(60) NOT NULL DEFAULT '',
			position varchar(190) NOT NULL DEFAULT '',
			is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY company_contact (company_id,contact_id),
			KEY contact_id (contact_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['tags']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			slug varchar(190) NOT NULL,
			description text NULL,
			color varchar(20) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY name (name)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['contact_tags']} (
			contact_id bigint(20) unsigned NOT NULL,
			tag_id bigint(20) unsigned NOT NULL,
			assigned_at datetime NOT NULL,
			assigned_by bigint(20) unsigned DEFAULT NULL,
			source varchar(60) NOT NULL DEFAULT 'manual',
			PRIMARY KEY  (contact_id,tag_id),
			KEY tag_id (tag_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['consents']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL,
			purpose varchar(100) NOT NULL,
			status varchar(30) NOT NULL,
			legal_basis varchar(60) NOT NULL DEFAULT '',
			source varchar(60) NOT NULL DEFAULT '',
			form_id bigint(20) unsigned DEFAULT NULL,
			policy_page_id bigint(20) unsigned DEFAULT NULL,
			policy_version varchar(60) NOT NULL DEFAULT '',
			proof_text longtext NULL,
			ip_hash char(64) NOT NULL DEFAULT '',
			actor_user_id bigint(20) unsigned DEFAULT NULL,
			occurred_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY contact_purpose (contact_id,purpose),
			KEY occurred_at (occurred_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['activities']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned DEFAULT NULL,
			company_id bigint(20) unsigned DEFAULT NULL,
			activity_type varchar(60) NOT NULL,
			title varchar(190) NOT NULL DEFAULT '',
			content longtext NULL,
			source varchar(60) NOT NULL DEFAULT '',
			external_type varchar(60) NOT NULL DEFAULT '',
			external_id varchar(190) NOT NULL DEFAULT '',
			actor_user_id bigint(20) unsigned DEFAULT NULL,
			occurred_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY contact_id (contact_id),
			KEY company_id (company_id),
			KEY activity_type (activity_type),
			KEY occurred_at (occurred_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['contactmeta']} (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL,
			meta_key varchar(191) DEFAULT NULL,
			meta_value longtext NULL,
			PRIMARY KEY  (meta_id),
			KEY contact_id (contact_id),
			KEY meta_key (meta_key)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['companymeta']} (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			company_id bigint(20) unsigned NOT NULL,
			meta_key varchar(191) DEFAULT NULL,
			meta_value longtext NULL,
			PRIMARY KEY  (meta_id),
			KEY company_id (company_id),
			KEY meta_key (meta_key)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['salutations']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			first_name varchar(190) NOT NULL,
			first_name_normalized varchar(190) NOT NULL,
			salutation varchar(20) NOT NULL DEFAULT '',
			source varchar(60) NOT NULL DEFAULT 'manual',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY first_name_normalized (first_name_normalized),
			KEY salutation (salutation)
		) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		$status = self::status();
		if ( $status['total'] !== $status['count'] ) {
			return new WP_Error( 'contacthub_schema_incomplete', __( 'ContactHub could not create all standalone database tables.', 'contacthub' ) );
		}
		if ( ! self::required_columns_exist() ) {
			return new WP_Error( 'contacthub_schema_columns_incomplete', __( 'ContactHub could not create all required database fields.', 'contacthub' ) );
		}
		update_option( self::VERSION_OPTION, self::VERSION, false );
		return true;
	}

	public static function drop_all() {
		global $wpdb;
		foreach ( array_reverse( self::table_names() ) as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- exact internally generated table name.
		}
		delete_option( self::VERSION_OPTION );
	}

	public static function reset_data() {
		global $wpdb;
		$status = self::status();
		if ( $status['total'] !== $status['count'] ) {
			return new WP_Error( 'contacthub_schema_incomplete', __( 'ContactHub standalone tables are incomplete.', 'contacthub' ) );
		}
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- guarded administrative reset of ContactHub-owned tables.
		foreach ( array_reverse( self::table_names() ) as $table ) {
			$wpdb->query( "TRUNCATE TABLE `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- exact internally generated table name.
		}
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- restores session setting after guarded reset.
		delete_option( 'contacthub_last_migration_report' );
		do_action( 'contacthub_after_reset_data' );
		return true;
	}

	private static function table_exists( $table ) {
		global $wpdb;
		$like = $wpdb->esc_like( $table );
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
	}

	private static function required_columns_exist() {
		$tables = self::table_names();
		$required = array(
			'contacts'   => array( 'title_prefix', 'vat_id', 'vat_status', 'vat_verified_at' ),
			'companies'  => array( 'legal_name', 'vat_id', 'vat_status', 'vat_verified_at' ),
			'tags'       => array( 'description' ),
			'salutations' => array( 'first_name_normalized', 'salutation', 'source' ),
		);
		foreach ( $required as $table_key => $columns ) {
			foreach ( $columns as $column ) {
				if ( ! self::column_exists( $tables[ $table_key ], $column ) ) {
					return false;
				}
			}
		}
		return true;
	}

	private static function column_exists( $table, $column ) {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- exact internally generated table name.
		);
	}
}
