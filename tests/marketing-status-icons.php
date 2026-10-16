<?php
/** Run with: php tests/marketing-status-icons.php. No WordPress installation needed. */
define( 'ABSPATH', __DIR__ );
function __( $value, $domain = '' ) { return $value; }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function verify( $condition, $label ) { if ( ! $condition ) throw new RuntimeException( $label ); }

require __DIR__ . '/../contacthub/includes/class-contacthub-plugin.php';
$badge = new ReflectionMethod( 'ContactHub_Plugin', 'marketing_icon_badge' );
$badge->setAccessible( true );

$allowed = $badge->invoke( null, 'confirmed' );
verify( false !== strpos( $allowed, 'dashicons-thumbs-up' ) && false !== strpos( $allowed, 'is-positive' ), 'Marketable contact uses the green thumbs-up icon' );

$pending = $badge->invoke( null, 'unconfirmed' );
verify( false !== strpos( $pending, 'dashicons-thumbs-down' ) && false !== strpos( $pending, 'is-negative' ), 'Ordinary non-marketable contact keeps the thumbs-down icon' );

foreach ( array( 3, 6, 7, 8, 9, 'unsubscribed', 'hard_bounce', 'spam', 'complained', 'blocked' ) as $status ) {
	$protected = $badge->invoke( null, $status );
	verify( false !== strpos( $protected, 'dashicons-dismiss' ), 'Protected status uses the X icon: ' . $status );
	verify( false !== strpos( $protected, 'is-negative is-protected' ), 'Protected status uses the red protected badge: ' . $status );
	verify( false === strpos( $protected, 'dashicons-thumbs-' ), 'Protected status never uses a thumb icon: ' . $status );
}

echo "Marketing status icon tests passed.\n";
