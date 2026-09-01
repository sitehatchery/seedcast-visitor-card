<?php
/**
 * The two emails a card sends.
 *
 * @package SeedcastVisitorCard\Submissions
 */

namespace SeedcastVisitorCard\Submissions;

use Seedcast\Core\Church;
use SeedcastVisitorCard\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One email to the church, one to the visitor.
 *
 * Both go through wp_mail, so whatever the site already uses to send mail
 * handles them and this plugin adds no service of its own to configure.
 *
 * The visitor's email is a single message triggered by their own action. It is
 * not a list, there is no sequence behind it, and nothing else is ever sent.
 * That matters for what a church can say honestly on the card.
 */
class Mail {

	/**
	 * Tell the church somebody is coming.
	 *
	 * @param int   $id      Submission ID.
	 * @param array $row     Stored row.
	 * @param array $payload Decoded payload.
	 * @return void
	 */
	public function notify_church( int $id, array $row, array $payload ): void {
		if ( '1' !== Settings::get( 'scvc_notify' ) ) {
			return;
		}

		$to = Settings::notify_to();
		if ( '' === $to || ! is_email( $to ) ) {
			return;
		}

		$name  = trim( (string) ( $row['author_name'] ?? '' ) );
		$email = trim( (string) ( $row['author_email'] ?? '' ) );
		$who   = '' !== $name ? $name : __( 'Someone', 'seedcast-visitor-card' );

		$subject = sprintf(
			/* translators: %s: visitor's name, or "Someone". */
			__( '%s is planning to visit', 'seedcast-visitor-card' ),
			$who
		);

		$lines = [];

		$lines[] = sprintf(
			/* translators: 1: visitor's name or "Someone", 2: day of the week. */
			__( '%1$s filled in the visitor card and is planning to come on %2$s.', 'seedcast-visitor-card' ),
			$who,
			Church::service_day_label()
		);
		$lines[] = '';

		if ( '' !== $name ) {
			$lines[] = __( 'Name:', 'seedcast-visitor-card' ) . ' ' . $name;
		}
		if ( '' !== $email ) {
			$lines[] = __( 'Email:', 'seedcast-visitor-card' ) . ' ' . $email;
		}
		if ( ! empty( $payload['phone'] ) ) {
			$lines[] = __( 'Phone:', 'seedcast-visitor-card' ) . ' ' . $payload['phone'];
		}
		if ( ! empty( $payload['party'] ) ) {
			$lines[] = __( 'Coming:', 'seedcast-visitor-card' ) . ' ' . (int) $payload['party'];
		}
		if ( ! empty( $payload['heard'] ) ) {
			$lines[] = __( 'Heard about you through:', 'seedcast-visitor-card' ) . ' ' . $payload['heard'];
		}
		if ( ! empty( $payload['status'] ) ) {
			$label = Settings::choice_label( Settings::statuses(), (string) $payload['status'] );
			if ( '' !== $label ) {
				$lines[] = Settings::status_legend() . ' ' . $label;
			}
		}
		if ( ! empty( $payload['interests'] ) && is_array( $payload['interests'] ) ) {
			$offered = Settings::interests();
			$chosen  = [];
			foreach ( $payload['interests'] as $key ) {
				$label = Settings::choice_label( $offered, (string) $key );
				if ( '' !== $label ) {
					$chosen[] = $label;
				}
			}
			if ( $chosen ) {
				$lines[] = Settings::interests_legend() . ' ' . implode( ', ', $chosen );
			}
		}
		if ( ! empty( $payload['message'] ) ) {
			$lines[] = '';
			$lines[] = __( 'They said:', 'seedcast-visitor-card' );
			$lines[] = $payload['message'];
		}

		if ( ! empty( $row['source_url'] ) ) {
			$lines[] = '';
			$lines[] = __( 'Filled in from:', 'seedcast-visitor-card' ) . ' ' . $row['source_url'];
		}

		$lines[] = '';
		$lines[] = __( 'See every submission:', 'seedcast-visitor-card' ) . ' '
			. admin_url( 'admin.php?page=seedcast-visitor-card' );

		$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

		/*
		 * Reply-to the visitor, so answering the notification answers the
		 * person rather than the website. This is the whole point of the
		 * email: a pastor should be able to hit reply.
		 */
		if ( '' !== $email && is_email( $email ) ) {
			$headers[] = 'Reply-To: ' . ( '' !== $name ? sprintf( '%s <%s>', $name, $email ) : $email );
		}

		/**
		 * Filter the notification before it is sent.
		 *
		 * @param array $mail    to, subject, message, headers.
		 * @param array $row     Stored row.
		 * @param array $payload Decoded payload.
		 */
		$mail = apply_filters( 'seedcast/visitor_card/notification', [
			'to'      => $to,
			'subject' => $subject,
			'message' => implode( "\n", $lines ),
			'headers' => $headers,
		], $row, $payload );

		wp_mail( $mail['to'], $mail['subject'], $mail['message'], $mail['headers'] );
	}

	/**
	 * Send the visitor what they will need on the day.
	 *
	 * The reason this exists: somebody decides on a Thursday to visit, and by
	 * Sunday cannot remember the church's name, never mind the address. An
	 * email sitting in their inbox with the address, the time and a directions
	 * link is the difference between meaning to come and arriving.
	 *
	 * @param array $row     Stored row.
	 * @param array $payload Decoded payload.
	 * @return void
	 */
	public function thank_visitor( array $row, array $payload ): void {
		if ( '1' !== Settings::get( 'scvc_followup' ) ) {
			return;
		}

		$email = trim( (string) ( $row['author_email'] ?? '' ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return;
		}

		$name = trim( (string) ( $row['author_name'] ?? '' ) );

		$subject = Settings::tokens( Settings::followup_subject(), $name );
		$body    = Settings::tokens( Settings::followup_body(), $name );
		$body = self::church_tokens( $body );

		/*
		 * There is no list to leave, so there is no unsubscribe link to offer.
		 * Saying so plainly is the honest equivalent, and it is what stops
		 * somebody wondering what else they have signed up for.
		 */
		$body .= "\n\n" . str_repeat( '-', 40 ) . "\n";
		$body .= sprintf(
			/* translators: %s: church name. */
			__( 'This is the only email %s will send you about your visit. You are not on a mailing list. If you would rather we did not have your details at all, reply and say so and we will delete them.', 'seedcast-visitor-card' ),
			Church::name()
		);

		$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

		$reply = Church::email();
		if ( '' !== $reply && is_email( $reply ) ) {
			$headers[] = 'Reply-To: ' . $reply;
		}

		/**
		 * Filter the visitor's email before it is sent.
		 *
		 * @param array $mail    to, subject, message, headers.
		 * @param array $row     Stored row.
		 * @param array $payload Decoded payload.
		 */
		$mail = apply_filters( 'seedcast/visitor_card/followup', [
			'to'      => $email,
			'subject' => $subject,
			'message' => $body,
			'headers' => $headers,
		], $row, $payload );

		wp_mail( $mail['to'], $mail['subject'], $mail['message'], $mail['headers'] );
	}

	/**
	 * Fill the church detail tokens.
	 *
	 * Every one comes from the shared church settings, so a church that
	 * changes its service time changes it once and this email follows.
	 *
	 * @param string $text Body text.
	 * @return string
	 */
	private static function church_tokens( string $text ): string {
		$directions = Church::directions_url();
		$events     = Church::events_url();
		$note       = Church::visitor_note();

		$replacements = [
			// The whole address, not the street on its own. City and state are
			// separate fields now, and an email giving only the street sends
			// somebody to the right number in the wrong town.
			'{address}'    => Church::address_block(),
			'{city}'       => Church::city(),
			'{state}'      => Church::state(),
			'{locality}'   => Church::locality(),
			'{times}'      => Church::service_times(),
			'{phone}'      => Church::phone(),
			'{directions}' => '' !== $directions
				? __( 'Directions:', 'seedcast-visitor-card' ) . ' ' . $directions
				: '',
			'{events}'     => '' !== $events
				? __( 'What is coming up:', 'seedcast-visitor-card' ) . ' ' . $events
				: '',
			'{note}'       => $note,
		];

		$text = str_replace( array_keys( $replacements ), array_values( $replacements ), $text );

		// A church that has not filled in its address leaves blank lines where
		// the tokens were. Collapse runs of them rather than sending an email
		// with a hole in the middle.
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( (string) $text );
	}
}
