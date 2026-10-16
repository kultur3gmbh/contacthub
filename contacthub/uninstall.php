<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$contacthub_general_settings = (array) get_option( 'contacthub_general_settings', array() );
if ( empty( $contacthub_general_settings['delete_on_uninstall'] ) ) {
	return;
}

require_once __DIR__ . '/includes/class-contacthub-schema.php';

ContactHub_Schema::drop_all();

delete_option( 'contacthub_general_settings' );
delete_option( 'contacthub_registration_settings' );
delete_option( 'contacthub_db_version' );
delete_option( 'contacthub_last_migration_report' );

global $wpdb;
$contacthub_meta_pattern = $wpdb->esc_like( 'contacthub_' ) . '%';
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		$contacthub_meta_pattern
	)
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup explicitly requested by the administrator.

$contacthub_option_patterns = array(
	$wpdb->esc_like( '_transient_contacthub_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_contacthub_' ) . '%',
);
foreach ( $contacthub_option_patterns as $contacthub_option_pattern ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$contacthub_option_pattern
		)
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup explicitly requested by the administrator.
}
