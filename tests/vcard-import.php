<?php

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}

function __( $text ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_email( $value ) { return filter_var( trim( (string) $value ), FILTER_SANITIZE_EMAIL ); }
function remove_accents( $value ) { return strtr( $value, array( 'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U' ) ); }
function wp_json_encode( $value ) { return json_encode( $value ); }

require_once dirname( __DIR__ ) . '/contacthub/includes/class-contacthub-data-tools.php';

$fixture = "BEGIN:VCARD\r\nVERSION:3.0\r\nN:Muster;Anna;;Frau;Dr.\r\nFN:Frau Anna Muster\r\nEMAIL;TYPE=INTERNET;TYPE=PREF:anna@example.test\r\nEMAIL;TYPE=HOME:anna.private@example.test\r\nTEL;TYPE=CELL:+43 660 123456\r\nTEL;TYPE=WORK:+43 1 5551234\r\nORG:Beispiel GmbH;Vertrieb\r\nTITLE:Leitung\r\nADR;TYPE=WORK:;;Hauptstraße 1;Wien;Wien;1010;AT\r\nNOTE:Erste Zeile\\nZweite Zeile\r\nUID:apple-123\r\nEND:VCARD\r\nBEGIN:VCARD\r\nVERSION:2.1\r\nN;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:M=C3=BCller;J=C3=B6rg;;;\r\nTEL:+49 30 1234\r\nEND:VCARD\r\n";

$records = ContactHub_Data_Tools::parse_vcard_contents( $fixture );
if ( is_wp_error( $records ) || 2 !== count( $records ) ) { fwrite( STDERR, "Expected two vCard records.\n" ); exit( 1 ); }
if ( 'Anna' !== $records[0]['first_name'] || 'Muster' !== $records[0]['last_name'] || 'ms' !== $records[0]['salutation'] ) { fwrite( STDERR, "Name or salutation parsing failed.\n" ); exit( 1 ); }
if ( 'anna@example.test' !== $records[0]['email'] || 'Beispiel GmbH' !== $records[0]['company'] || '1010' !== $records[0]['postcode'] ) { fwrite( STDERR, "Contact field parsing failed.\n" ); exit( 1 ); }
if ( "Erste Zeile\nZweite Zeile" !== $records[0]['note'] ) { fwrite( STDERR, "Escaped note parsing failed.\n" ); exit( 1 ); }
if ( 4 !== count( $records[0]['contact_channels'] ) ) { fwrite( STDERR, "Expected every vCard email address and telephone number to be retained.\n" ); exit( 1 ); }
$channel_map = array();
foreach ( $records[0]['contact_channels'] as $channel ) $channel_map[ $channel['type'] . ':' . $channel['value'] ] = $channel;
if ( empty( $channel_map['email:anna@example.test']['is_primary'] ) || 'private' !== $channel_map['email:anna.private@example.test']['label'] ) { fwrite( STDERR, "Email channel preference or type parsing failed.\n" ); exit( 1 ); }
if ( 'mobile' !== $channel_map['phone:+43 660 123456']['label'] || 'work' !== $channel_map['phone:+43 1 5551234']['label'] ) { fwrite( STDERR, "Telephone channel type parsing failed.\n" ); exit( 1 ); }
if ( 'Jörg' !== $records[1]['first_name'] || 'Müller' !== $records[1]['last_name'] ) { fwrite( STDERR, "Quoted-printable parsing failed.\n" ); exit( 1 ); }
if ( $records[0]['source_id'] === $records[1]['source_id'] ) { fwrite( STDERR, "Stable source identifiers must differ.\n" ); exit( 1 ); }

echo "vCard parser checks passed.\n";
