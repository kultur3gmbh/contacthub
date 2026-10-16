<?php
/** Run with: php tests/trash.php. Sanitized fixtures; no WordPress installation needed. */
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
$GLOBALS['admin'] = true;
$GLOBALS['mode'] = 'standalone';
function current_user_can( $cap ) { return 'manage_options' === $cap && $GLOBALS['admin']; }
function absint( $value ) { return abs( (int) $value ); }
function current_time( $format ) { return '2026-10-16 12:00:00'; }
function __( $value, $domain = '' ) { return $value; }
function esc_html__( $value, $domain = '' ) { return $value; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_unslash( $value ) { return stripslashes( $value ); }
function wp_die( $message, $title = '', $args = array() ) { throw new RuntimeException( $message ); }
function check_admin_referer( $action ) { if ( empty( $GLOBALS['nonce'] ) ) throw new RuntimeException( 'Invalid nonce' ); }
class ContactHub_Store { public static function standalone() { return 'standalone' === $GLOBALS['mode']; } }
require __DIR__ . '/../contacthub/includes/class-contacthub-schema.php';
require __DIR__ . '/../contacthub/includes/class-contacthub-trash.php';

class TrashDB {
	public $prefix = 'fixture_7_';
	public $data = array();
	public $snapshot;
	public $writes = array();
	public $fail = '';
	public $engine = 'InnoDB';
	public function prepare( $sql, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) ) $args = $args[0];
		foreach ( $args as $arg ) $sql = preg_replace_callback( '/%[ds]/', function ( $match ) use ( $arg ) { return '%d' === $match[0] ? (string) (int) $arg : "'" . str_replace( "'", "''", $arg ) . "'"; }, $sql, 1 );
		return $sql;
	}
	public function query( $sql ) {
		$this->writes[] = $sql;
		if ( 'START TRANSACTION' === $sql ) { $this->snapshot = $this->data; return 0; }
		if ( 'ROLLBACK' === $sql ) { $this->data = $this->snapshot; return 0; }
		if ( 'COMMIT' === $sql ) return 'COMMIT' === $this->fail ? false : 0;
		if ( preg_match( '/UPDATE `([^`]+)` SET deleted_at = (NULL|\x27[^\x27]+\x27), updated_at = .* WHERE id = (\d+) AND deleted_at (IS NULL|IS NOT NULL)/', $sql, $m ) ) {
			foreach ( $this->data[ $m[1] ] as &$row ) {
				if ( (int) $row['id'] !== (int) $m[3] || ( null === $row['deleted_at'] ) !== ( 'IS NULL' === $m[4] ) ) continue;
				$row['deleted_at'] = 'NULL' === $m[2] ? null : trim( $m[2], "'" ); return 1;
			}
			return 0;
		}
		throw new RuntimeException( 'Unexpected query: ' . $sql );
	}
	public function get_var( $sql ) { if ( false !== strpos( $sql, 'SELECT ENGINE' ) ) return $this->engine; throw new RuntimeException( $sql ); }
	public function get_row( $sql, $format ) {
		preg_match( '/FROM `([^`]+)` WHERE id = (\d+)/', $sql, $m );
		foreach ( $this->data[ $m[1] ] as $row ) if ( (int) $row['id'] === (int) $m[2] && null !== $row['deleted_at'] ) return $row;
		return null;
	}
	public function delete( $table, $where ) {
		$this->writes[] = $table;
		if ( $table === $this->fail ) return false;
		$before = count( $this->data[ $table ] );
		$this->data[ $table ] = array_values( array_filter( $this->data[ $table ], function ( $row ) use ( $where ) { foreach ( $where as $key => $value ) if ( ( $row[ $key ] ?? null ) != $value ) return true; return false; } ) );
		return $before - count( $this->data[ $table ] );
	}
	public function update( $table, $values, $where ) {
		$this->writes[] = $table;
		if ( $table === $this->fail ) return false;
		foreach ( $this->data[ $table ] as &$row ) { if ( array_intersect_assoc( $where, $row ) === $where ) $row = array_merge( $row, $values ); }
		return 1;
	}
}
function verify( $condition, $label ) { if ( ! $condition ) throw new RuntimeException( $label ); }
function fixture() {
	$GLOBALS['admin'] = true; $GLOBALS['mode'] = 'standalone';
	$db = new TrashDB();
	foreach ( ContactHub_Schema::table_names() as $table ) $db->data[ $table ] = array();
	foreach ( array( 'contacts', 'companies' ) as $entity ) $db->data[ 'fixture_7_chb_' . $entity ] = array( array( 'id' => 1, 'deleted_at' => null ), array( 'id' => 2, 'deleted_at' => '2026-10-15 12:00:00' ) );
	foreach ( array( 'contactmeta', 'contact_tags', 'consents', 'company_contacts', 'companymeta', 'activities' ) as $name ) $db->data[ 'fixture_7_chb_' . $name ] = array( array( 'id' => 10, 'contact_id' => 2, 'company_id' => 2, 'external_id' => 'sanitized-event-1' ), array( 'id' => 11, 'contact_id' => 1, 'company_id' => 1 ) );
	foreach ( array( 'users', 'posts', 'wc_orders', 'invoices', 'events', 'gh_contacts', 'gh_companies' ) as $name ) $db->data[ 'fixture_7_' . $name ] = array( array( 'id' => 2, 'value' => 'source fixture' ) );
	$GLOBALS['wpdb'] = $db; return $db;
}
foreach ( array( 'contacts', 'companies' ) as $entity ) {
	$db = fixture(); $original = $db->data;
	verify( ! ContactHub_Trash::change( $entity, 1, 'purge', true ), 'Active records cannot be purged' );
	verify( $original === $db->data, 'Refused purge must not change data' );
	verify( ContactHub_Trash::change( $entity, 1, 'trash', true ), 'Trash active record' );
	verify( ! ContactHub_Trash::change( $entity, 1, 'trash', true ), 'Repeated trash is a no-op' );
	verify( ContactHub_Trash::change( $entity, 1, 'restore', true ), 'Restore record' );
	verify( $original === $db->data, 'Restore preserves every relationship and source' );
	verify( ! ContactHub_Trash::change( $entity, 1, 'restore', true ), 'Active restore is a no-op' );
	verify( ContactHub_Trash::change( $entity, 2, 'purge', true ), 'Purge trashed record' );
	$key = 'contacts' === $entity ? 'contact_id' : 'company_id';
	verify( 2 === count( $db->data['fixture_7_chb_activities'] ), 'Never delete activities or events' );
	verify( null === $db->data['fixture_7_chb_activities'][0][ $key ], 'Detach only local activity reference' );
	verify( 'sanitized-event-1' === $db->data['fixture_7_chb_activities'][0]['external_id'], 'Retain event source identifier' );
	foreach ( $original as $table => $rows ) if ( false === strpos( $table, '_chb_' ) ) verify( $rows === $db->data[ $table ], 'External source unchanged: ' . $table );
	foreach ( array( 'fixture_7_chb_company_contacts', 'fixture_7_chb_activities', 'fixture_7_chb_' . $entity, 'COMMIT' ) as $failure ) {
		$db = fixture(); $original = $db->data; $db->fail = $failure;
		verify( ! ContactHub_Trash::change( $entity, 2, 'purge', true ), 'Report database failure' );
		verify( $original === $db->data, 'Rollback every local mutation' );
	}
	foreach ( array( 'trash', 'restore', 'purge' ) as $operation ) {
		foreach ( array( 'permission', 'mode', 'confirmation', 'engine' ) as $guard ) {
			$db = fixture(); $original = $db->data;
			if ( 'permission' === $guard ) $GLOBALS['admin'] = false;
			if ( 'mode' === $guard ) $GLOBALS['mode'] = 'groundhogg';
			if ( 'engine' === $guard ) { if ( 'purge' !== $operation ) continue; $db->engine = 'MyISAM'; }
			verify( ! ContactHub_Trash::change( $entity, 'trash' === $operation ? 1 : 2, $operation, 'confirmation' !== $guard ), 'Enforce ' . $guard );
			verify( $original === $db->data && ! $db->writes, 'Guard before mutation' );
		}
	}
}
fixture();
foreach ( array( 'users', 'events', 'gh_contacts', '../contacts', 'contacts`' ) as $entity ) verify( ! ContactHub_Trash::change( $entity, 2, 'purge', true ), 'Reject non-allowlisted table' );
foreach ( array( 0, '', null ) as $id ) verify( ! ContactHub_Trash::change( 'contacts', $id, 'purge', true ), 'Reject missing ID' );
verify( ! ContactHub_Trash::change( 'contacts', 2, 'delete', true ), 'Reject unsupported action' );
// Endpoint rejects forged methods, nonces, malformed and unconfirmed submissions before mutation.
foreach ( array( 'GET', 'nonce', 'confirmation', 'array-id', 'limit', 'entity', 'operation' ) as $case ) {
	$db = fixture(); $_SERVER['REQUEST_METHOD'] = 'GET' === $case ? 'GET' : 'POST'; $GLOBALS['nonce'] = 'nonce' !== $case;
	$_POST = array( 'entity' => 'entity' === $case ? 'users' : 'contacts', 'operation' => 'operation' === $case ? 'drop' : 'purge', 'ids' => array( 2 ), 'confirmed' => 'confirmation' === $case ? '0' : '1' );
	if ( 'array-id' === $case ) $_POST['ids'] = array( array( 2 ) );
	if ( 'limit' === $case ) $_POST['ids'] = range( 1, 51 );
	$rejected = false;
	try { ContactHub_Trash::handle(); } catch ( RuntimeException $error ) { $rejected = true; }
	verify( $rejected && ! $db->writes, 'Reject endpoint case: ' . $case );
}
echo "Trash lifecycle and safety tests passed.\n";

