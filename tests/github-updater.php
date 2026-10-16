<?php
/** Offline behavior tests: php tests/github-updater.php [--plus]. No network or real credentials. */
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
$engine = getenv( 'CONTACTHUB_BASIC_UPDATER_FILE' ) ?: __DIR__ . '/../contacthub/includes/class-contacthub-github-updater.php';
define( 'WP_PLUGIN_DIR', dirname( dirname( dirname( realpath( $engine ) ) ) ) );
define( 'CONTACTHUB_UPDATER_TEST_TOKEN', '<TOKEN-PLACEHOLDER>' );
class WP_Error {
	private $code; private $message;
	public function __construct( $code, $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $message, $domain = '' ) { return $message; }
function esc_html( $message ) { return htmlspecialchars( $message ); }
function add_filter( ...$args ) {}
function add_action( ...$args ) {}
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_url( $value ) { return parse_url( $value ); }
function set_site_transient( $key, $value, $ttl ) { $GLOBALS['cache'][ $key ] = $value; }
function get_site_transient( $key ) { return $GLOBALS['cache'][ $key ] ?? false; }
function delete_site_transient( $key ) { unset( $GLOBALS['cache'][ $key ] ); }
function get_site_option( $key, $default = null ) { return $GLOBALS['options'][ $key ] ?? $default; }
function current_user_can( $cap ) { return true; }
function wp_doing_cron() { return false; }
function wp_remote_retrieve_response_code( $response ) { return $response['status']; }
function wp_remote_retrieve_header( $response, $name ) { return $response['headers'][ $name ] ?? ''; }
function wp_remote_retrieve_body( $response ) { return $response['body'] ?? ''; }
function wp_safe_remote_get( $url, $args ) {
	$GLOBALS['requests'][] = array( $url, $args );
	if ( ! $GLOBALS['queue'] ) throw new RuntimeException( 'Unexpected HTTP request' );
	$response = array_shift( $GLOBALS['queue'] );
	if ( ! is_wp_error( $response ) && ! empty( $args['stream'] ) ) file_put_contents( $args['filename'], $response['body'] ?? '' );
	return $response;
}
function wp_tempnam( $name ) { $file = tempnam( sys_get_temp_dir(), 'chb-test-' ); $GLOBALS['temporary'][] = $file; return $file; }
function wp_delete_file( $file ) { if ( is_file( $file ) ) unlink( $file ); }
function is_plugin_active( $plugin ) { return $GLOBALS['basic_active']; }
function get_file_data( $file, $headers ) { return array( 'Version' => $GLOBALS['basic_version'] ); }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function verify( $condition, $label ) { if ( ! $condition ) throw new RuntimeException( $label ); }
$engine = getenv( 'CONTACTHUB_BASIC_UPDATER_FILE' ) ?: __DIR__ . '/../contacthub/includes/class-contacthub-github-updater.php';
require $engine;
$plus = in_array( '--plus', $argv, true );
$slug = $plus ? 'contacthub-plus' : 'contacthub';
$config = array( 'slug' => $slug, 'name' => $slug, 'repo' => $slug, 'zip_prefix' => $plus ? $slug : 'contacthub-basic', 'plugin' => $slug . '/' . $slug . '.php', 'version' => '0.14.0', 'enabled' => 'CONTACTHUB_UPDATER_TEST_ENABLED', 'basic' => $plus );
if ( $plus ) $config['token'] = 'CONTACTHUB_UPDATER_TEST_TOKEN';
$api = 'https://api.github.com/repos/kultur3gmbh/' . $slug;
$package = 'fixture bytes, not a published release archive';
function fixture( $config ) {
	$GLOBALS['cache'] = array(); $GLOBALS['requests'] = array(); $GLOBALS['queue'] = array(); $GLOBALS['options'] = array(); $GLOBALS['wp_version'] = '6.8'; $GLOBALS['basic_active'] = true; $GLOBALS['basic_version'] = '0.14.0';
	return new ContactHub_GitHub_Updater( $config );
}
function response( $body, $status = 200, $headers = array() ) { return array( 'status' => $status, 'headers' => $headers, 'body' => is_array( $body ) ? json_encode( $body ) : $body ); }
function release_fixture( $config, $bytes ) {
	$manifest = array( 'slug' => $config['slug'], 'version' => '0.15.0', 'requires' => '6.4', 'requires_php' => '7.4', 'tested' => '6.8', 'requires_basic' => '0.14.0', 'sha256' => hash( 'sha256', $bytes ) );
	$release = array( 'draft' => false, 'prerelease' => false, 'tag_name' => 'v0.15.0', 'published_at' => '2026-10-16T10:00:00Z', 'assets' => array( array( 'id' => 101, 'state' => 'uploaded', 'name' => $config['slug'] . '-update.json' ), array( 'id' => 102, 'state' => 'uploaded', 'name' => $config['zip_prefix'] . '-0.15.0.zip', 'size' => strlen( $bytes ), 'digest' => 'sha256:' . $manifest['sha256'] ) ) );
	return array( $release, $manifest );
}
list( $release, $manifest ) = release_fixture( $config, $package );
$updater = fixture( $config ); $GLOBALS['queue'] = array( response( $release ), response( $manifest ) );
$meta = $updater->release(); verify( ! is_wp_error( $meta ) && '0.15.0' === $meta['version'], 'Published release detection' );
verify( ! isset( $meta['token'], $meta['Authorization'] ), 'No credentials in metadata' );
$updater->release(); verify( 2 === count( $GLOBALS['requests'] ), 'Successful check cached' );
verify( $api . '/releases/latest' === $GLOBALS['requests'][0][0], 'No main branch access' );
if ( $plus ) verify( isset( $GLOBALS['requests'][0][1]['headers']['Authorization'] ), 'Private API request authenticated' );
else verify( ! isset( $GLOBALS['requests'][0][1]['headers']['Authorization'] ), 'Public API request anonymous' );
$offer = $updater->updates( (object) array( 'checked' => array( $config['plugin'] => '0.14.0' ) ) );
verify( isset( $offer->response[ $config['plugin'] ] ), 'WordPress update response' );
verify( ! $updater->automatic( true, $offer->response[ $config['plugin'] ] ), 'Automatic updates default off even when upstream enables all plugins' );
$foreign = (object) array( 'response' => array( $config['plugin'] => (object) array( 'package' => 'https://downloads.wordpress.org/foreign.zip' ) ) );
verify( ! isset( $updater->filter_cached( $foreign )->response[ $config['plugin'] ] ), 'Reject cached foreign update source while GitHub channel owns plugin' );
$GLOBALS['options']['auto_update_plugins'] = array( $config['plugin'] );
verify( $updater->automatic( true, $offer->response[ $config['plugin'] ] ), 'Explicit WordPress opt-in respected' );
verify( ! $updater->automatic( false, $offer->response[ $config['plugin'] ] ), 'Respect upstream automatic update veto' );
$GLOBALS['wp_version'] = '6.3'; verify( ! $updater->compatible( $meta ), 'WordPress minimum' ); $GLOBALS['wp_version'] = '6.8';
$incompatible = $meta; $incompatible['requires_php'] = '999.0'; verify( ! $updater->compatible( $incompatible ), 'PHP minimum' );
if ( $plus ) {
	$GLOBALS['basic_version'] = '0.13.2'; verify( ! $updater->compatible( $meta ), 'Basic minimum' );
	$blocked = $updater->filter_cached( $offer ); verify( '' === $blocked->response[ $config['plugin'] ]->package, 'Stale incompatible update package blocked' );
	$GLOBALS['basic_version'] = '0.14.0'; $GLOBALS['basic_active'] = false; verify( ! $updater->compatible( $meta ), 'Inactive Basic blocked' ); $GLOBALS['basic_active'] = true;
	$missing = $config; $missing['token'] = 'UNDEFINED_TOKEN_PLACEHOLDER'; $updater = fixture( $missing );
	verify( 'contacthub_update_token' === $updater->release()->get_error_code() && ! $GLOBALS['requests'], 'Missing token fails without HTTP' );
}
foreach ( array( 401, 403, 404, 429, 500, 'network' ) as $status ) {
	$updater = fixture( $config ); $GLOBALS['queue'] = array( 'network' === $status ? new WP_Error( 'http', 'UNSAFE RAW RESPONSE' ) : response( '', $status ) );
	$error = $updater->release(); verify( is_wp_error( $error ) && false === strpos( $error->get_error_message(), 'UNSAFE' ), 'Sanitize access/network failures' );
	$updater->release(); verify( 1 === count( $GLOBALS['requests'] ), 'Error backoff cache' );
}
foreach ( array( 'draft', 'prerelease', 'tag', 'asset', 'checksum', 'basic' ) as $case ) {
	$updater = fixture( $config ); $r = $release; $m = $manifest;
	if ( 'draft' === $case || 'prerelease' === $case ) $r[ $case ] = true;
	if ( 'tag' === $case ) $r['tag_name'] = 'main';
	if ( 'asset' === $case ) $r['assets'][1]['name'] = 'source.zip';
	if ( 'checksum' === $case ) $m['sha256'] = str_repeat( '0', 64 );
	if ( 'basic' === $case ) { if ( ! $plus ) continue; unset( $m['requires_basic'] ); }
	$GLOBALS['queue'] = array( response( $r ), response( $m ) );
	verify( is_wp_error( $updater->release() ), 'Reject invalid release: ' . $case );
}
foreach ( array( 'https://attacker.invalid/leak', 'http://release-assets.githubusercontent.com/file', 'https://release-assets.githubusercontent.com.attacker.invalid/file', 'https://user@release-assets.githubusercontent.com/file', 'https://api.github.com/repos/other/assets/1' ) as $location ) {
	$updater = fixture( $config ); $GLOBALS['queue'] = array( response( $release ), response( '', 302, array( 'location' => $location ) ) );
	verify( is_wp_error( $updater->release() ) && 2 === count( $GLOBALS['requests'] ), 'Reject hostile redirect before HTTP' );
}
$updater = fixture( $config ); $GLOBALS['queue'] = array( response( $release ), response( '', 302, array( 'location' => 'https://release-assets.githubusercontent.com/manifest' ) ), response( $manifest ) );
verify( ! is_wp_error( $updater->release() ), 'GitHub CDN metadata redirect accepted' );
verify( ! isset( $GLOBALS['requests'][2][1]['headers']['Authorization'] ) && 0 === $GLOBALS['requests'][1][1]['redirection'], 'Never forward token on redirects' );
$updater = fixture( $config ); $GLOBALS['queue'] = array( response( $release ), response( $manifest ), response( '', 302, array( 'location' => 'https://release-assets.githubusercontent.com/package' ) ), response( $package ) );
$file = $updater->download( false, $api . '/releases/assets/102', null, array( 'plugin' => $config['plugin'] ) );
verify( is_string( $file ) && is_file( $file ), 'Verified streaming download' );
verify( ! isset( $GLOBALS['requests'][3][1]['headers']['Authorization'] ), 'ZIP CDN download anonymous' );
class FixtureFilesystem {
	public $folder; public $content;
	public function dirlist( $path ) { return array( $this->folder => array() ); }
	public function get_contents( $path ) { return $this->content; }
}
$GLOBALS['wp_filesystem'] = new FixtureFilesystem(); $GLOBALS['wp_filesystem']->folder = $slug;
$GLOBALS['wp_filesystem']->content = "<?php\n/**\n * Version: 0.15.0\n * Requires at least: 6.4\n * Requires PHP: 7.4\n * Requires ContactHub: 0.14.0\n */\n";
$path = '/fixture/' . $slug . '/'; $extra = array( 'plugin' => $config['plugin'] );
verify( $path === $updater->source( $path, '/fixture/', null, $extra ), 'Keep exact plugin directory' );
verify( is_wp_error( $updater->source( '/fixture/main/', '/fixture/', null, $extra ) ), 'Reject GitHub source ZIP directory' );
verify( '/other-plugin/' === $updater->source( '/other-plugin/', '/fixture/', null, array( 'plugin' => 'other/other.php' ) ), 'Do not intercept next plugin in a bulk update' );
$GLOBALS['wp_filesystem']->content = str_replace( '0.15.0', '0.16.0', $GLOBALS['wp_filesystem']->content );
verify( is_wp_error( $updater->source( $path, '/fixture/', null, $extra ) ), 'Reject header/manifest mismatch' );
$updater = fixture( $config ); $GLOBALS['queue'] = array( response( $release ), response( $manifest ), response( 'tampered' ) );
verify( is_wp_error( $updater->download( false, $api . '/releases/assets/102', null, $extra ) ), 'Reject corrupted download' );
$updater = fixture( $config ); $GLOBALS['queue'] = array( response( $release ), response( $manifest ) );
verify( is_wp_error( $updater->download( false, 'https://attacker.invalid/main.zip', null, $extra ) ), 'Never install package outside published release' );
$updater = fixture( $config ); define( 'CONTACTHUB_UPDATER_TEST_ENABLED', false );
verify( 'contacthub_update_disabled' === $updater->release()->get_error_code(), 'GitHub channel switch off' );
$offer = $updater->filter_cached( $offer ); verify( ! isset( $offer->response[ $config['plugin'] ] ), 'Clear stale GitHub offer on switch' );
foreach ( $GLOBALS['temporary'] ?? array() as $file ) wp_delete_file( $file );
echo $slug . " GitHub updater behavior tests passed.\n";
