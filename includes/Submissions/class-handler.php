<?php
/**
 * What happens when somebody fills the card in.
 *
 * @package SeedcastVisitorCard\Submissions
 */

namespace SeedcastVisitorCard\Submissions;

use SeedcastVisitorCard\Frontend\Card;
use SeedcastVisitorCard\Frontend\Confirmation;
use SeedcastVisitorCard\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates the posted card, then reacts once the shared engine has stored it.
 *
 * The engine owns the nonce, the honeypot, the captcha and the insert. This
 * class supplies the payload and decides what happens afterwards.
 */
class Handler {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter(
			'seedcast/submission/parse_payload/' . Card::SOURCE . '/' . Card::TYPE,
			[ $this, 'parse_payload' ],
			10,
			2
		);

		add_action( 'seedcast/submission/stored', [ $this, 'on_stored' ], 10, 2 );

		add_filter(
			'seedcast/submission/response/' . Card::SOURCE . '/' . Card::TYPE,
			[ $this, 'response' ],
			10,
			2
		);
	}

	/**
	 * Turn the posted card into the payload that gets stored.
	 *
	 * Returning a WP_Error stops the submission and shows the message to the
	 * visitor, so validation lives here rather than in the engine.
	 *
	 * The nonce has already been verified by the engine before this runs.
	 *
	 * @param array $payload Payload so far.
	 * @param array $post    Posted values, already unslashed and sanitized by the engine.
	 * @return array|\WP_Error
	 */
	public function parse_payload( $payload, $post ) {
		if ( Settings::suspended() ) {
			return new \WP_Error(
				'scvc_suspended',
				esc_html( Settings::suspended_message() )
			);
		}

		$name  = isset( $post['seedcast_author_name'] ) ? sanitize_text_field( $post['seedcast_author_name'] ) : '';
		$email = isset( $post['seedcast_author_email'] ) ? sanitize_email( $post['seedcast_author_email'] ) : '';
		$phone = isset( $post['scvc_phone'] ) ? sanitize_text_field( $post['scvc_phone'] ) : '';

		if ( Settings::uses( 'name' ) && '' === trim( $name ) ) {
			return new \WP_Error(
				'scvc_no_name',
				__( 'Please tell us your name.', 'seedcast-visitor-card' )
			);
		}

		/*
		 * One way to reach them, not both. A church needs some way to say hello
		 * back, but insisting on an email address turns away the people who
		 * would rather be phoned, and insisting on a phone number turns away
		 * everyone else.
		 */
		$wants_email = Settings::uses( 'email' );
		$wants_phone = Settings::uses( 'phone' );

		if ( ( $wants_email || $wants_phone ) && '' === trim( $email ) && '' === trim( $phone ) ) {
			if ( $wants_email && $wants_phone ) {
				$message = __( 'Please leave an email address or a phone number so we can say hello.', 'seedcast-visitor-card' );
			} elseif ( $wants_email ) {
				$message = __( 'Please leave an email address so we can say hello.', 'seedcast-visitor-card' );
			} else {
				$message = __( 'Please leave a phone number so we can say hello.', 'seedcast-visitor-card' );
			}
			return new \WP_Error( 'scvc_no_contact', $message );
		}

		if ( '' !== trim( $email ) && ! is_email( $email ) ) {
			return new \WP_Error(
				'scvc_bad_email',
				__( 'That email address does not look right. Please check it.', 'seedcast-visitor-card' )
			);
		}

		$party = isset( $post['scvc_party'] ) ? absint( $post['scvc_party'] ) : 0;
		if ( $party > 99 ) {
			$party = 99;
		}

		/*
		 * Both choice groups are checked against the offered options rather
		 * than stored as posted. They arrive from a form anyone can rewrite,
		 * and a value that was never on the card should not end up in the
		 * church's records or in a notification email.
		 */
		$status = isset( $post['scvc_status'] ) ? sanitize_key( $post['scvc_status'] ) : '';
		if ( '' !== $status && ! array_key_exists( $status, Settings::statuses() ) ) {
			$status = '';
		}

		$interests = [];
		if ( isset( $post['scvc_interests'] ) && is_array( $post['scvc_interests'] ) ) {
			$offered = Settings::interests();
			foreach ( (array) $post['scvc_interests'] as $choice ) {
				$choice = sanitize_key( $choice );
				if ( array_key_exists( $choice, $offered ) ) {
					$interests[] = $choice;
				}
			}
			$interests = array_values( array_unique( $interests ) );
		}

		return [
			'phone'     => $phone,
			'party'     => $party,
			'heard'     => isset( $post['scvc_heard'] ) ? sanitize_text_field( $post['scvc_heard'] ) : '',
			'message'   => isset( $post['scvc_message'] ) ? sanitize_textarea_field( $post['scvc_message'] ) : '',
			'status'    => $status,
			'interests' => $interests,
		];
	}

	/**
	 * What the visitor sees the moment the card is sent.
	 *
	 * The card is replaced by the confirmation rather than gaining a notice
	 * above it. Somebody who has just pressed a button at the bottom of a long
	 * form is looking at the bottom of a long form, and a message that appears
	 * at the top is a message nobody reads. It also says the right thing: this
	 * is not moderated and nobody is waiting for approval.
	 *
	 * @param array $payload Default response.
	 * @param array $post    Posted values, already unslashed and sanitized by the engine.
	 * @return array
	 */
	public function response( $payload, $post ) {
		$name = isset( $post['seedcast_author_name'] ) ? sanitize_text_field( $post['seedcast_author_name'] ) : '';

		$payload['message'] = Settings::tokens( Settings::confirmation(), $name );
		$payload['replace'] = Card::render( [ 'confirmed' => $name ] );

		return $payload;
	}

	/**
	 * React to a stored submission.
	 *
	 * @param int   $id  Row ID.
	 * @param array $row Stored row.
	 * @return void
	 */
	public function on_stored( $id, $row ): void {
		if ( Card::SOURCE !== ( $row['source'] ?? '' ) || Card::TYPE !== ( $row['type'] ?? '' ) ) {
			return;
		}

		$payload = json_decode( (string) ( $row['payload'] ?? '[]' ), true );
		if ( ! is_array( $payload ) ) {
			$payload = [];
		}

		$name = (string) ( $row['author_name'] ?? '' );

		Confirmation::set( $name );

		$mail = new Mail();
		$mail->notify_church( (int) $id, $row, $payload );
		$mail->thank_visitor( $row, $payload );
	}
}
