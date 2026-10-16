<?php
/**
 * Real WordPress/GitHub release installation acceptance runner.
 * CONTACTHUB_TEST_WP_ROOT=/path/to/disposable/wp php tests/wordpress-update-integration.php
 * Requires an explicit .contacthub-isolated-test marker before WordPress is bootstrapped.
 * Never run against production. Does not create releases, versions or packages.
 */
if ( 'cli' !== PHP_SAPI ) { http_response_code( 403 ); exit; }
$root = getenv( 'CONTACTHUB_TEST_WP_ROOT' );
if ( ! $root || ! is_file( rtrim( $root, '/' ) . '/.contacthub-isolated-test' ) || ! is_file( rtrim( $root, '/' ) . '/wp-load.php' ) ) {
	fwrite( STDERR, "BLOCKED: supply a disposable WordPress root with .contacthub-isolated-test marker.\n" ); exit( 2 );
}
require rtrim( $root, '/' ) . '/wp-load.php';
if ( ! function_exists( 'wp_get_environment_type' ) || 'local' !== wp_get_environment_type() || ! class_exists( 'ContactHub_GitHub_Updater' ) ) {
	fwrite( STDERR, "BLOCKED: WP_ENVIRONMENT_TYPE must be local and the updater-enabled Basic code must be active.\n" ); exit( 2 );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/update.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $admins ) { fwrite( STDERR, "BLOCKED: disposable site has no administrator.\n" ); exit( 2 ); }
wp_set_current_user( $admins[0] );
$plugins = array( 'contacthub/contacthub.php', 'contacthub-plus/contacthub-plus.php' );
$before = array();
foreach ( $plugins as $plugin ) {
	if ( ! is_plugin_active( $plugin ) || ! is_file( WP_PLUGIN_DIR . '/' . $plugin ) ) { fwrite( STDERR, "BLOCKED: both test plugins must be installed and active.\n" ); exit( 2 ); }
	$before[ $plugin ] = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false )['Version'];
}
foreach ( array( 'contacthub_github_release', 'contacthub-plus_github_release', 'update_plugins' ) as $key ) delete_site_transient( $key );
wp_update_plugins();
$offers = get_site_transient( 'update_plugins' );
foreach ( $plugins as $plugin ) {
	if ( empty( $offers->response[ $plugin ]->contacthub_github ) || ! version_compare( $offers->response[ $plugin ]->new_version, $before[ $plugin ], '>' ) ) {
		fwrite( STDERR, "BLOCKED: both plugins need an already authorized newer published release, matching manifest/ZIP, and valid update access. No installation attempted.\n" ); exit( 2 );
	}
}
// Add harmless, temporary sentinels; plugin updates must not reset options.
$key = 'contacthub_isolated_update_' . wp_generate_password( 12, false, false );
$sentinel = array( 'basic' => 'sanitized-sentinel', 'plus' => 'sanitized-sentinel' );
add_option( $key, $sentinel, '', false );
$status = 0;
try {
	foreach ( $plugins as $plugin ) {
		delete_site_transient( 'update_plugins' ); wp_update_plugins();
		$offer = get_site_transient( 'update_plugins' );
		if ( empty( $offer->response[ $plugin ]->package ) ) throw new RuntimeException( 'BLOCKED: requirements or release access prevent this installation.' );
		$expected = $offer->response[ $plugin ]->new_version;
		$skin = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result = $upgrader->upgrade( $plugin );
		if ( is_wp_error( $result ) || true !== $result || $skin->get_errors()->has_errors() ) throw new RuntimeException( 'FAIL: WordPress Plugin_Upgrader did not complete successfully; inspect the fixed updater error on the disposable site.' );
		$after = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false )['Version'];
		if ( $expected !== $after || ! is_plugin_active( $plugin ) || get_option( $key ) !== $sentinel ) throw new RuntimeException( 'FAIL: version, plugin basename, activation state or sentinel settings changed unexpectedly.' );
		echo esc_html( $plugin . ': detected ' . $before[ $plugin ] . ' -> ' . $after . '; actual ZIP installation, basename, activation and settings checks passed.' ) . "\n";
	}
} catch ( Throwable $error ) {
	// Do not print raw HTTP, authorization, or transport exception payloads.
	fwrite( STDERR, "FAIL/BLOCKED: real update acceptance did not complete. Review the disposable site's updater notices and requirements.\n" ); $status = 1;
} finally {
	delete_option( $key );
}
exit( $status );
