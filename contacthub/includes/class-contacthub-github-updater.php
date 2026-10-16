<?php

defined( 'ABSPATH' ) || exit;

/** Public updater contract used by ContactHub Plus; credentials never enter update metadata. */
final class ContactHub_GitHub_Updater {
	private $config;
	private $cache;
	private $install_release;

	public function __construct( array $config ) {
		$this->config = $config;
		$this->cache = $config['slug'] . '_github_release';
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'updates' ) );
		add_filter( 'site_transient_update_plugins', array( $this, 'filter_cached' ) );
		add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
		add_filter( 'auto_update_plugin', array( $this, 'automatic' ), 100, 2 );
		add_filter( 'upgrader_pre_download', array( $this, 'download' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( $this, 'source' ), 10, 4 );
		add_filter( 'plugin_action_links_' . $config['plugin'], array( $this, 'links' ) );
		add_action( 'admin_post_' . $config['slug'] . '_check_updates', array( $this, 'check' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'network_admin_notices', array( $this, 'notice' ) );
		add_action( 'upgrader_process_complete', array( $this, 'clear' ), 10, 2 );
	}

	private function enabled() {
		return ! defined( $this->config['enabled'] ) || false !== constant( $this->config['enabled'] );
	}

	private function error( $code ) {
		$messages = array(
			'token' => __( 'ContactHub Plus updates require a valid CONTACTHUB_PLUS_GITHUB_TOKEN in wp-config.php. The plugin remains usable without update access.', 'contacthub' ),
			'network' => __( 'The GitHub update service could not be reached. Please try again later.', 'contacthub' ),
			'access' => __( 'GitHub denied update access. Check the repository permission and token expiry in wp-config.php.', 'contacthub' ),
			'release' => __( 'No valid published GitHub release with matching update metadata and plugin ZIP was found.', 'contacthub' ),
			'compatibility' => __( 'This update requires a newer WordPress, PHP, or active ContactHub Basic version. Update those requirements first.', 'contacthub' ),
			'archive' => __( 'The update ZIP failed integrity, plugin directory, version, or requirement validation. Nothing was installed.', 'contacthub' ),
			'disabled' => __( 'GitHub updates are disabled for this plugin.', 'contacthub' ),
		);
		return new WP_Error( 'contacthub_update_' . $code, $messages[ $code ] ?? $messages['release'] );
	}

	private function api() { return 'https://api.github.com/repos/kultur3gmbh/' . $this->config['repo']; }
	private function asset_url( $id ) { return $this->api() . '/releases/assets/' . absint( $id ); }
	private function version( $value ) { return is_string( $value ) && 1 === preg_match( '/^\d+\.\d+(?:\.\d+)?$/D', $value ); }

	/** All authenticated requests are constructed locally and have redirects disabled. */
	private function request( $url, $binary = false, $filename = '' ) {
		$headers = array( 'Accept' => $binary ? 'application/octet-stream' : 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28' );
		if ( ! empty( $this->config['token'] ) ) {
			$name = $this->config['token'];
			if ( ! defined( $name ) || ! is_string( constant( $name ) ) || '' === trim( constant( $name ) ) || preg_match( '/[\r\n]/', constant( $name ) ) ) return $this->error( 'token' );
			$headers['Authorization'] = 'Bearer ' . trim( constant( $name ) );
		}
		// No caller-supplied host or path can receive credentials.
		if ( 0 !== strpos( $url, $this->api() . '/releases/' ) ) return $this->error( 'release' );
		for ( $redirect = 0; $redirect < 4; $redirect++ ) {
			$args = array( 'timeout' => $filename ? 120 : 15, 'redirection' => 0, 'sslverify' => true, 'headers' => $headers, 'limit_response_size' => $filename ? 100 * 1024 * 1024 : 256 * 1024 );
			if ( $filename ) { $args['stream'] = true; $args['filename'] = $filename; }
			$response = wp_safe_remote_get( $url, $args );
			if ( is_wp_error( $response ) ) return $this->error( 'network' ); // Never expose HTTP debug/error payloads.
			$code = wp_remote_retrieve_response_code( $response );
			if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
				$next = wp_remote_retrieve_header( $response, 'location' );
				$parts = is_string( $next ) ? wp_parse_url( $next ) : false;
				if ( ! $binary || ! $parts || 'https' !== ( $parts['scheme'] ?? '' ) || ! in_array( $parts['host'] ?? '', array( 'release-assets.githubusercontent.com', 'objects.githubusercontent.com', 'github-releases.githubusercontent.com' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) || ( isset( $parts['port'] ) && 443 !== $parts['port'] ) ) return $this->error( 'release' );
				$url = $next;
				unset( $headers['Authorization'] ); // CDN requests are always anonymous, including subsequent redirects.
				continue;
			}
			if ( 401 === $code || 403 === $code || ( 404 === $code && ! empty( $this->config['token'] ) ) ) return $this->error( 'access' );
			if ( 200 !== $code ) return $this->error( 429 === $code || $code >= 500 ? 'network' : 'release' );
			return $response;
		}
		return $this->error( 'network' );
	}

	private function fail( $error ) {
		set_site_transient( $this->cache, array( 'error' => $error->get_error_code() ), 5 * MINUTE_IN_SECONDS );
		return $error;
	}

	public function release( $force = false ) {
		if ( ! $this->enabled() ) return $this->error( 'disabled' );
		if ( ! empty( $this->config['token'] ) && ( ! defined( $this->config['token'] ) || ! is_string( constant( $this->config['token'] ) ) || '' === trim( constant( $this->config['token'] ) ) ) ) return $this->error( 'token' );
		if ( ! $force ) {
			$cached = get_site_transient( $this->cache );
			if ( is_array( $cached ) ) {
				if ( isset( $cached['error'] ) ) return $this->error( str_replace( 'contacthub_update_', '', $cached['error'] ) );
				return $cached;
			}
		}
		$response = $this->request( $this->api() . '/releases/latest' );
		if ( is_wp_error( $response ) ) return $this->fail( $response );
		$release = json_decode( wp_remote_retrieve_body( $response ), true );
		$version = is_array( $release ) && is_string( $release['tag_name'] ?? null ) ? preg_replace( '/^v/', '', $release['tag_name'] ) : '';
		if ( ! is_array( $release ) || false !== ( $release['draft'] ?? null ) || false !== ( $release['prerelease'] ?? null ) || empty( $release['published_at'] ) || ! $this->version( $version ) || ! is_array( $release['assets'] ?? null ) ) return $this->fail( $this->error( 'release' ) );
		$assets = array();
		foreach ( $release['assets'] as $asset ) {
			if ( ! is_array( $asset ) || 'uploaded' !== ( $asset['state'] ?? '' ) || empty( $asset['id'] ) || ! is_string( $asset['name'] ?? null ) ) continue;
			if ( isset( $assets[ $asset['name'] ] ) ) return $this->fail( $this->error( 'release' ) );
			$assets[ $asset['name'] ] = $asset;
		}
		$zip_name = $this->config['zip_prefix'] . '-' . $version . '.zip';
		$manifest = $assets[ $this->config['slug'] . '-update.json' ] ?? null;
		$zip = $assets[ $zip_name ] ?? null;
		if ( ! $manifest || ! $zip || empty( $zip['size'] ) || $zip['size'] > 100 * 1024 * 1024 ) return $this->fail( $this->error( 'release' ) );
		$response = $this->request( $this->asset_url( $manifest['id'] ), true );
		if ( is_wp_error( $response ) ) return $this->fail( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || $version !== ( $data['version'] ?? '' ) || $this->config['slug'] !== ( $data['slug'] ?? '' ) || ! $this->version( $data['requires'] ?? null ) || ! $this->version( $data['requires_php'] ?? null ) || ! $this->version( $data['tested'] ?? null ) || ! is_string( $data['sha256'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', $data['sha256'] ) || ( ! empty( $this->config['basic'] ) && ! $this->version( $data['requires_basic'] ?? null ) ) || ( isset( $zip['digest'] ) && 'sha256:' . $data['sha256'] !== $zip['digest'] ) ) return $this->fail( $this->error( 'release' ) );
		// Only this allowlist reaches options, plugin information, or the update transient.
		$result = array( 'version' => $version, 'requires' => $data['requires'], 'requires_php' => $data['requires_php'], 'tested' => $data['tested'], 'requires_basic' => empty( $this->config['basic'] ) ? '' : $data['requires_basic'], 'sha256' => $data['sha256'], 'asset_id' => absint( $zip['id'] ), 'size' => absint( $zip['size'] ) );
		set_site_transient( $this->cache, $result, 30 * MINUTE_IN_SECONDS );
		return $result;
	}

	private function basic_version() {
		$plugin = 'contacthub/contacthub.php';
		if ( ! function_exists( 'is_plugin_active' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( ! is_plugin_active( $plugin ) ) return '';
		$file = WP_PLUGIN_DIR . '/' . $plugin;
		$data = is_file( $file ) ? get_file_data( $file, array( 'Version' => 'Version' ) ) : array();
		return $data['Version'] ?? '';
	}

	public function compatible( $release ) {
		global $wp_version;
		if ( is_wp_error( $release ) || ! is_array( $release ) || ! $this->version( $release['requires'] ?? null ) || ! $this->version( $release['requires_php'] ?? null ) || ( ! empty( $this->config['basic'] ) && ! $this->version( $release['requires_basic'] ?? null ) ) ) return false;
		if ( version_compare( $wp_version, $release['requires'], '<' ) || version_compare( PHP_VERSION, $release['requires_php'], '<' ) ) return false;
		if ( ! empty( $this->config['basic'] ) ) {
			$basic = $this->basic_version();
			if ( ! $basic || version_compare( $basic, $release['requires_basic'], '<' ) ) return false;
		}
		return true;
	}

	public function filter_cached( $transient ) {
		if ( ! is_object( $transient ) ) return $transient;
		$plugin = $this->config['plugin'];
		if ( ! $this->enabled() ) {
			foreach ( array( 'response', 'no_update' ) as $key ) if ( isset( $transient->{$key}[ $plugin ] ) && ! empty( $transient->{$key}[ $plugin ]->contacthub_github ) ) unset( $transient->{$key}[ $plugin ] );
		} else {
			foreach ( array( 'response', 'no_update' ) as $key ) {
				if ( isset( $transient->{$key}[ $plugin ] ) && empty( $transient->{$key}[ $plugin ]->contacthub_github ) ) unset( $transient->{$key}[ $plugin ] );
			}
			if ( isset( $transient->response[ $plugin ] ) ) {
				$release = get_site_transient( $this->cache );
				$transient->response[ $plugin ]->package = $this->compatible( $release ) ? $this->asset_url( $release['asset_id'] ) : '';
			}
		}
		return $transient;
	}

	public function updates( $transient ) {
		if ( ! is_object( $transient ) ) return $transient;
		if ( ! $this->enabled() ) return $this->filter_cached( $transient );
		$plugin = $this->config['plugin'];
		// Never accept WordPress.org packages while this GitHub channel owns the plugin.
		unset( $transient->response[ $plugin ], $transient->no_update[ $plugin ] );
		$release = $this->release();
		if ( is_wp_error( $release ) ) return $transient;
		$item = (object) array( 'id' => 'https://github.com/kultur3gmbh/' . $this->config['repo'], 'slug' => $this->config['slug'], 'plugin' => $plugin, 'new_version' => $release['version'], 'url' => 'https://github.com/kultur3gmbh/' . $this->config['repo'] . '/releases', 'package' => $this->compatible( $release ) ? $this->asset_url( $release['asset_id'] ) : '', 'requires' => $release['requires'], 'requires_php' => $release['requires_php'], 'tested' => $release['tested'], 'contacthub_github' => true );
		$installed = $transient->checked[ $plugin ] ?? $this->config['version'];
		$key = version_compare( $release['version'], $installed, '>' ) ? 'response' : 'no_update';
		if ( ! isset( $transient->{$key} ) || ! is_array( $transient->{$key} ) ) $transient->{$key} = array();
		$transient->{$key}[ $plugin ] = $item;
		return $transient;
	}

	public function automatic( $update, $item ) {
		if ( ( $item->plugin ?? '' ) !== $this->config['plugin'] || ! $this->enabled() ) return $update;
		return $update && in_array( $this->config['plugin'], (array) get_site_option( 'auto_update_plugins', array() ), true ) && $this->compatible( $this->release() );
	}

	public function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ( $args->slug ?? '' ) !== $this->config['slug'] || ! $this->enabled() ) return $result;
		$release = $this->release();
		if ( is_wp_error( $release ) ) return $release;
		return (object) array( 'name' => $this->config['name'], 'slug' => $this->config['slug'], 'version' => $release['version'], 'author' => 'Kultur3 GmbH', 'requires' => $release['requires'], 'requires_php' => $release['requires_php'], 'tested' => $release['tested'], 'download_link' => $this->compatible( $release ) ? $this->asset_url( $release['asset_id'] ) : '', 'sections' => array( 'description' => esc_html( $this->compatible( $release ) ? __( 'Published GitHub release for the ContactHub test phase. Automatic updates require administrator opt-in in the WordPress Plugins screen.', 'contacthub' ) : $this->error( 'compatibility' )->get_error_message() ) ) );
	}

	public function download( $reply, $package, $upgrader, $extra ) {
		$target = ( $extra['plugin'] ?? '' ) === $this->config['plugin'];
		$own = is_string( $package ) && 0 === strpos( $package, $this->api() . '/releases/assets/' );
		if ( ! $target && ! $own ) return $reply;
		if ( $own && ! empty( $extra['plugin'] ) && ! $target ) return $this->error( 'release' );
		if ( ! $this->enabled() ) return $own ? $this->error( 'disabled' ) : $reply;
		if ( wp_doing_cron() && ! in_array( $this->config['plugin'], (array) get_site_option( 'auto_update_plugins', array() ), true ) ) return $this->error( 'access' );
		if ( ! current_user_can( 'update_plugins' ) && ! wp_doing_cron() ) return $this->error( 'access' );
		$this->install_release = null;
		if ( dirname( $this->config['plugin'] ) !== $this->config['slug'] ) return $this->error( 'archive' );
		$release = $this->release( true ); // Revalidate even cached offers and withdrawn releases before installation.
		if ( is_wp_error( $release ) ) return $release;
		if ( ! $this->compatible( $release ) ) return $this->error( 'compatibility' );
		if ( $this->asset_url( $release['asset_id'] ) !== $package || ! version_compare( $release['version'], $this->config['version'], '>' ) ) return $this->error( 'release' );
		$file = wp_tempnam( $this->config['slug'] . '.zip' );
		if ( ! $file ) return $this->error( 'archive' );
		$response = $this->request( $package, true, $file );
		if ( is_wp_error( $response ) || ! is_file( $file ) || filesize( $file ) !== $release['size'] || ! hash_equals( $release['sha256'], hash_file( 'sha256', $file ) ) ) {
			wp_delete_file( $file ); return is_wp_error( $response ) ? $response : $this->error( 'archive' );
		}
		$this->install_release = $release;
		return $file;
	}

	public function source( $source, $remote, $upgrader, $extra ) {
		$target = ( $extra['plugin'] ?? '' ) === $this->config['plugin'];
		if ( ( ! $target && ( ! empty( $extra['plugin'] ) || ! $this->install_release ) ) || ! $this->enabled() || is_wp_error( $source ) ) return $source;
		global $wp_filesystem;
		$release = $this->install_release;
		if ( ! $release || ! $this->compatible( $release ) || $this->config['slug'] !== basename( untrailingslashit( $source ) ) ) return $this->error( 'archive' );
		$entries = $wp_filesystem->dirlist( $remote );
		if ( ! is_array( $entries ) || array_keys( $entries ) !== array( $this->config['slug'] ) ) return $this->error( 'archive' );
		$main = trailingslashit( $source ) . basename( $this->config['plugin'] );
		$content = $wp_filesystem->get_contents( $main );
		if ( ! is_string( $content ) || ! $content ) return $this->error( 'archive' );
		$headers = array( 'Version' => $release['version'], 'Requires at least' => $release['requires'], 'Requires PHP' => $release['requires_php'] );
		if ( ! empty( $this->config['basic'] ) ) $headers['Requires ContactHub'] = $release['requires_basic'];
		foreach ( $headers as $name => $expected ) {
			if ( ! preg_match( '/^[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':\s*([^\r\n]*)/mi', substr( $content, 0, 8192 ), $match ) || trim( $match[1] ) !== $expected ) return $this->error( 'archive' );
		}
		return $source; // WordPress replaces this same directory; settings/options remain untouched.
	}

	public function links( $links ) {
		if ( $this->enabled() && current_user_can( 'update_plugins' ) ) $links[] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . $this->config['slug'] . '_check_updates' ), $this->config['slug'] . '_check_updates' ) ) . '">' . esc_html__( 'Check for updates', 'contacthub' ) . '</a>';
		return $links;
	}

	public function check() {
		if ( ! current_user_can( 'update_plugins' ) ) wp_die( esc_html( $this->error( 'access' )->get_error_message() ), '', array( 'response' => 403 ) );
		check_admin_referer( $this->config['slug'] . '_check_updates' );
		delete_site_transient( $this->cache );
		delete_site_transient( 'update_plugins' ); // Bypass WordPress's recent-check throttle for this explicit check.
		wp_update_plugins();
		wp_safe_redirect( self_admin_url( 'plugins.php' ) ); exit;
	}

	public function notice() {
		if ( ! $this->enabled() || ! current_user_can( 'update_plugins' ) || ! in_array( $GLOBALS['pagenow'] ?? '', array( 'plugins.php', 'update-core.php' ), true ) ) return;
		$release = $this->release();
		$error = is_wp_error( $release ) ? $release : ( $this->compatible( $release ) ? null : $this->error( 'compatibility' ) );
		if ( $error ) echo '<div class="notice notice-warning"><p>' . esc_html( $this->config['name'] . ': ' . $error->get_error_message() ) . '</p></div>';
	}

	public function clear( $upgrader, $extra ) {
		if ( in_array( $this->config['plugin'], (array) ( $extra['plugins'] ?? array( $extra['plugin'] ?? '' ) ), true ) ) delete_site_transient( $this->cache );
	}
}
