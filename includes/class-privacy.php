<?php
/**
 * Personal data export and erasure.
 *
 * @package SeedcastVisitorCard
 */

namespace SeedcastVisitorCard;

use Seedcast\Core\Install;
use SeedcastVisitorCard\Admin\Submissions;
use SeedcastVisitorCard\Frontend\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the cards into WordPress's own privacy tools.
 *
 * A visitor who asks to be removed should be removed, and a church should not
 * have to know where this plugin keeps things in order to do it. Registering
 * here means the standard Tools screens handle it, which is also where anyone
 * looks first.
 */
class Privacy {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_eraser' ] );
		add_action( 'admin_init', [ $this, 'declare_policy' ] );
	}

	/**
	 * Add the exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['seedcast-visitor-card'] = [
			'exporter_friendly_name' => __( 'Visitor Card', 'seedcast-visitor-card' ),
			'callback'               => [ $this, 'export' ],
		];
		return $exporters;
	}

	/**
	 * Add the eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['seedcast-visitor-card'] = [
			'eraser_friendly_name' => __( 'Visitor Card', 'seedcast-visitor-card' ),
			'callback'             => [ $this, 'erase' ],
		];
		return $erasers;
	}

	/**
	 * Export everything held about an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public function export( $email, $page = 1 ) {
		$items = [];

		foreach ( self::rows_for( (string) $email ) as $row ) {
			$payload = json_decode( (string) $row['payload'], true );
			$payload = is_array( $payload ) ? $payload : [];

			$data = [
				[
					'name'  => __( 'Date', 'seedcast-visitor-card' ),
					'value' => (string) $row['submitted_at'],
				],
				[
					'name'  => __( 'Name', 'seedcast-visitor-card' ),
					'value' => (string) $row['author_name'],
				],
				[
					'name'  => __( 'Email', 'seedcast-visitor-card' ),
					'value' => (string) $row['author_email'],
				],
			];

			foreach ( [ 'phone', 'party', 'heard', 'message' ] as $key ) {
				if ( ! empty( $payload[ $key ] ) ) {
					$data[] = [
						'name'  => ucfirst( $key ),
						'value' => (string) $payload[ $key ],
					];
				}
			}

			$items[] = [
				'group_id'    => 'seedcast-visitor-card',
				'group_label' => __( 'Visitor cards', 'seedcast-visitor-card' ),
				'item_id'     => 'scvc-' . (int) $row['id'],
				'data'        => $data,
			];
		}

		return [
			'data' => $items,
			'done' => true,
		];
	}

	/**
	 * Delete everything held about an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public function erase( $email, $page = 1 ) {
		global $wpdb;

		$rows = self::rows_for( (string) $email );

		if ( $rows ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE source = %s AND type = %s AND author_email = %s',
					Install::submissions_table(),
					Card::SOURCE,
					Card::TYPE,
					$email
				)
			);
		}

		return [
			'items_removed'  => count( $rows ),
			'items_retained' => false,
			'messages'       => [],
			'done'           => true,
		];
	}

	/**
	 * Rows belonging to one email address.
	 *
	 * @param string $email Email address.
	 * @return array<int, array>
	 */
	private static function rows_for( string $email ): array {
		global $wpdb;

		if ( '' === $email ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE source = %s AND type = %s AND author_email = %s ORDER BY submitted_at ASC',
				Install::submissions_table(),
				Card::SOURCE,
				Card::TYPE,
				$email
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Suggest privacy policy wording.
	 *
	 * @return void
	 */
	public function declare_policy(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . __( 'When you fill in our visitor card we store the name and contact details you give us, along with anything you write in the message, so we can get in touch about your visit. We keep this on our own website and do not sell or share it.', 'seedcast-visitor-card' ) . '</p>';
		$content .= '<p>' . __( 'If you gave an email address we send you one message with our address and service times. It is not a mailing list and nothing else follows it.', 'seedcast-visitor-card' ) . '</p>';
		$content .= '<p>' . __( 'Your browser remembers that you filled the card in, so you are not asked again before the service. That is only set once you have sent the card.', 'seedcast-visitor-card' ) . '</p>';

		wp_add_privacy_policy_content( __( 'Visitor Card', 'seedcast-visitor-card' ), wp_kses_post( $content ) );
	}
}
