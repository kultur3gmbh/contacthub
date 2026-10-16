<?php
/** Run with: php tests/marketing-status-icons.php. No WordPress installation needed. */
define( 'ABSPATH', __DIR__ );
function __( $value, $domain = '' ) { return $value; }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return $value; }
function verify( $condition, $label ) { if ( ! $condition ) throw new RuntimeException( $label ); }

class ContactHub_Trash {
	public static function available() { return true; }
	public static function url( $entity, $id = 0, $trashed = false ) { return '/trash/' . $entity . '/' . $id . '/' . ( $trashed ? '1' : '0' ); }
}

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

$trash_link = new ReflectionMethod( 'ContactHub_Plugin', 'render_trash_link' );
$trash_link->setAccessible( true );
ob_start();
$trash_link->invoke( null, 'contacts', 42, true );
$trash_button = ob_get_clean();
verify( false !== strpos( $trash_button, 'contacthub-trash-row-action' ), 'Trash uses the compact list action button' );
verify( false !== strpos( $trash_button, 'dashicons-trash' ), 'Trash action uses the trash icon' );
verify( false !== strpos( $trash_button, 'screen-reader-text' ), 'Icon-only trash action keeps an accessible label' );
verify( false === strpos( $trash_button, '>Move to Trash</a>' ), 'Trash action has no visible text label' );

echo "Marketing status icon tests passed.\n";
