<?php
/**
 * Remembering that somebody has already told us they are coming.
 *
 * @package SeedcastVisitorCard\Frontend
 */

namespace SeedcastVisitorCard\Frontend;

use Seedcast\Core\Church;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A first-party cookie holding the name somebody gave, so every place the card
 * appears shows the confirmation instead of asking again.
 *
 * Deliberately narrow. It records one thing the visitor did on purpose, it is
 * set only after they submit, and it is read only to decide between two pieces
 * of markup. Nothing here watches anyone: a visitor who never fills the card
 * in is never given a cookie at all.
 *
 * It expires once the service they were told about has passed, so a card that
 * says "see you Sunday" stops saying it on Monday and becomes a form again for
 * the following week.
 */
final class Confirmation {

	/**
	 * Cookie name.
	 */
	public const COOKIE = 'scvc_coming';

	/**
	 * Cached read, so several cards on one page do not each parse it.
	 *
	 * @var string|null|false
	 */
	private static $cache = false;

	/**
	 * Whether this visitor is somebody testing the card rather than using it.
	 *
	 * An administrator filling the card in is checking that it works, and will
	 * want to do it again in a minute. Remembering them turns the card into a
	 * confirmation everywhere it appears, on their own site, until the service
	 * has passed, and the only way back is to find and delete a cookie.
	 *
	 * They still see the confirmation the moment they send it, because that
	 * comes back with the response rather than from the cookie. They just get
	 * the form again on the next page, which is what testing needs.
	 *
	 * @return bool
	 */
	public static function is_tester(): bool {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}

		/**
		 * Filter who the card declines to remember.
		 *
		 * @param bool $tester Whether to skip remembering this visitor.
		 */
		return (bool) apply_filters(
			'seedcast/visitor_card/is_tester',
			current_user_can( 'manage_options' )
		);
	}

	/**
	 * The name on the current confirmation, or null if there is not one.
	 *
	 * An empty string is a valid answer: it means somebody submitted without
	 * giving a name, which is allowed when they leave a phone number instead.
	 * They should still see the confirmation.
	 *
	 * @return string|null
	 */
	public static function current(): ?string {
		if ( false !== self::$cache ) {
			return self::$cache;
		}

		self::$cache = null;

		/*
		 * Front end only. The cookie says what one visitor did on the public
		 * site, and it has nothing to say about an admin screen. Reading it in
		 * the admin means whoever tested the card sees their own confirmation
		 * wherever the card is drawn, which is confusing at best and, on a
		 * screen for designing the form, actively in the way.
		 */
		if ( is_admin() || self::is_tester() ) {
			return self::$cache;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading our own cookie to choose between two pieces of markup; nothing is written or acted on.
		if ( ! isset( $_COOKIE[ self::COOKIE ] ) ) {
			return self::$cache;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );

		$parts = explode( '|', $raw, 2 );
		if ( count( $parts ) !== 2 ) {
			return self::$cache;
		}

		$expires = (int) $parts[0];

		/*
		 * Checked here as well as by the browser. A cookie's own expiry is set
		 * by the browser's clock, and the service day is worked out from the
		 * site's, so on a device whose clock is wrong the two disagree. The
		 * site's answer is the one that matters.
		 */
		if ( $expires > 0 && $expires < time() ) {
			return self::$cache;
		}

		self::$cache = $parts[1];

		return self::$cache;
	}

	/**
	 * Record that somebody has told us they are coming.
	 *
	 * @param string $name Name they gave, may be empty.
	 * @return void
	 */
	public static function set( string $name ): void {
		$expires = Church::next_service_timestamp();

		// A clock or configuration that produces a time already gone would
		// otherwise set a cookie that never shows. A week is the sensible
		// floor: it is one service either way.
		if ( $expires <= time() ) {
			$expires = time() + WEEK_IN_SECONDS;
		}

		self::$cache = $name;

		// Nothing is written for somebody testing, so their next page shows
		// the form again.
		if ( self::is_tester() ) {
			return;
		}

		if ( headers_sent() ) {
			// Submitted over AJAX after output began. The confirmation still
			// shows for this response through the cache above; the visitor
			// simply sees the form again on the next page.
			return;
		}

		setcookie(
			self::COOKIE,
			$expires . '|' . $name,
			[
				'expires'  => $expires,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			]
		);
	}

	/**
	 * Forget the confirmation.
	 *
	 * @return void
	 */
	public static function clear(): void {
		self::$cache = null;

		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE,
			'',
			[
				'expires'  => time() - HOUR_IN_SECONDS,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			]
		);
	}
}
