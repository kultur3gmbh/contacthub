<?php
/** Run with: php tests/groundhogg-migration-source.php. Sanitized fixtures; no WordPress installation needed. */
namespace Groundhogg {
	class Contact {
		private $id;
		public function __construct( $id ) { $this->id = (int) $id; }
		public function exists() { return true; }
		public function get_id() { return $this->id; }
		public function get_email() { return 'fixture-' . $this->id . '@example.test'; }
		public function get_first_name() { return 'Fixture'; }
		public function get_last_name() { return 'Contact ' . $this->id; }
		public function get_optin_status() { return 2; }
		public function get_meta( $key ) { return 'primary_phone' === $key ? '+43 1 234567' : ''; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	class GroundhoggMigrationDB {
		public $prefix = 'fixture_';
		public function prepare( $sql, ...$args ) { return $sql; }
		public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
		public function get_var( $sql ) { return 'fixture_gh_contacts'; }
		public function get_col( $sql ) { return array( 3, 7, 11 ); }
	}
	class GroundhoggMigrationAbility {
		public function execute( $input ) { $GLOBALS['ability_calls']++; return array( 'contacts' => array() ); }
	}
	class WP_Error {}
	function absint( $value ) { return abs( (int) $value ); }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function wp_get_ability( $name ) { return new GroundhoggMigrationAbility(); }
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
	function verify( $condition, $label ) { if ( ! $condition ) throw new \RuntimeException( $label ); }

	$GLOBALS['wpdb'] = new GroundhoggMigrationDB();
	$GLOBALS['ability_calls'] = 0;
	require __DIR__ . '/../contacthub/includes/class-contacthub-store.php';

	$source = new \ReflectionMethod( 'ContactHub_Store', 'groundhogg_contacts_for_migration' );
	$source->setAccessible( true );
	$contacts = $source->invoke( null );
	verify( 3 === count( $contacts ), 'Every Groundhogg table contact is returned' );
	verify( array( 3, 7, 11 ) === array_map( static function ( $contact ) { return $contact->get_id(); }, $contacts ), 'Stable Groundhogg IDs are preserved' );
	verify( 0 === $GLOBALS['ability_calls'], 'Direct contact objects avoid the version-dependent ability when the source table exists' );

	$meta = new \ReflectionMethod( 'ContactHub_Store', 'meta_value' );
	$meta->setAccessible( true );
	verify( '+43 1 234567' === $meta->invoke( null, $contacts[0], 'primary_phone' ), 'Groundhogg object metadata is readable' );
	$optin = new \ReflectionMethod( 'ContactHub_Store', 'optin_value' );
	$optin->setAccessible( true );
	verify( 2 === $optin->invoke( null, $contacts[0] ), 'Groundhogg object opt-in status is preserved' );

	echo "Groundhogg migration source tests passed.\n";
}
