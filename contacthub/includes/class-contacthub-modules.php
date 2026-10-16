<?php

defined( 'ABSPATH' ) || exit;

/**
 * Small public registry used by optional ContactHub extensions.
 *
 * The registry contains only module metadata and never licensing secrets or
 * customer data. A future license service may filter module availability,
 * while Basic remains fully usable without it.
 */
final class ContactHub_Modules {
	private static $modules = array();

	public static function register( $id, $args = array() ) {
		$id = sanitize_key( $id );
		if ( ! $id ) {
			return false;
		}
		$defaults = array(
			'name'         => $id,
			'package'      => 'external',
			'version'      => '',
			'enabled'      => true,
			'dependencies' => array(),
		);
		$module = wp_parse_args( is_array( $args ) ? $args : array(), $defaults );
		$module['id'] = $id;
		$module['enabled'] = (bool) $module['enabled'];
		$module['dependencies'] = array_values( array_filter( array_map( 'sanitize_key', (array) $module['dependencies'] ) ) );
		self::$modules[ $id ] = $module;
		return true;
	}

	public static function get( $id ) {
		$id = sanitize_key( $id );
		return isset( self::$modules[ $id ] ) ? self::$modules[ $id ] : false;
	}

	public static function all() {
		return self::$modules;
	}

	public static function enabled( $id ) {
		$module = self::get( $id );
		$enabled = $module && ! empty( $module['enabled'] );
		return (bool) apply_filters( 'contacthub_module_enabled', $enabled, sanitize_key( $id ), $module );
	}
}

