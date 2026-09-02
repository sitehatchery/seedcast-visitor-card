<?php
/**
 * CSV export.
 *
 * @package SeedcastVisitorCard\Admin
 */

namespace SeedcastVisitorCard\Admin;

use SeedcastVisitorCard\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes every card out as a CSV.
 *
 * The church's own contacts, in a format it can take anywhere. Nothing here
 * leaves the site unless somebody clicks the button.
 */
class Export {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_scvc_export', [ $this, 'download' ] );
	}

	/**
	 * Send the file.
	 *
	 * @return void
	 */
	public function download(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot do that.', 'seedcast-visitor-card' ) );
		}
		check_admin_referer( 'scvc_export' );

		$rows = Submissions::all();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header(
			'Content-Disposition: attachment; filename=visitor-cards-'
			. gmdate( 'Y-m-d' ) . '.csv'
		);

		$out = fopen( 'php://output', 'w' );

		// Byte order mark, so Excel opens a UTF-8 file with accented names in
		// it correctly instead of mangling them.
		echo "\xEF\xBB\xBF";

		fputcsv( $out, [
			__( 'Date', 'seedcast-visitor-card' ),
			__( 'Name', 'seedcast-visitor-card' ),
			__( 'Email', 'seedcast-visitor-card' ),
			__( 'Phone', 'seedcast-visitor-card' ),
			__( 'Coming', 'seedcast-visitor-card' ),
			__( 'Heard through', 'seedcast-visitor-card' ),
			__( 'Status', 'seedcast-visitor-card' ),
			__( 'Interested in', 'seedcast-visitor-card' ),
			__( 'Message', 'seedcast-visitor-card' ),
			__( 'Page', 'seedcast-visitor-card' ),
		] );

		foreach ( $rows as $row ) {
			$payload = is_array( $row['payload'] ) ? $row['payload'] : [];

			fputcsv( $out, [
				(string) $row['submitted_at'],
				(string) $row['author_name'],
				(string) $row['author_email'],
				(string) ( $payload['phone'] ?? '' ),
				(string) ( $payload['party'] ?? '' ),
				(string) ( $payload['heard'] ?? '' ),
				self::label_for( Settings::statuses(), (string) ( $payload['status'] ?? '' ) ),
				self::labels_for( Settings::interests(), (array) ( $payload['interests'] ?? [] ) ),
				(string) ( $payload['message'] ?? '' ),
				(string) $row['source_url'],
			] );
		}

		exit;
	}

	/**
	 * The readable label for a stored key.
	 *
	 * Stored keys are written out as the words a church chose, because a CSV
	 * column reading "regular" helps nobody opening it in a spreadsheet.
	 *
	 * @param array  $choices Key to label.
	 * @param string $key     Stored key.
	 * @return string
	 */
	private static function label_for( array $choices, string $key ): string {
		return Settings::choice_label( $choices, $key );
	}

	/**
	 * Readable labels for a list of stored keys.
	 *
	 * @param array $choices Key to label.
	 * @param array $keys    Stored keys.
	 * @return string
	 */
	private static function labels_for( array $choices, array $keys ): string {
		$out = [];
		foreach ( $keys as $key ) {
			$label = self::label_for( $choices, (string) $key );
			if ( '' !== $label ) {
				$out[] = $label;
			}
		}
		return implode( ', ', $out );
	}
}
