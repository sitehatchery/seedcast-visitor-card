<?php
/**
 * Activation and deactivation.
 *
 * @package SeedcastVisitorCard
 */

namespace SeedcastVisitorCard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sets the site up on activation and leaves everything alone on deactivation.
 *
 * Nothing is deleted here. Cards people have filled in are the church's
 * record of who said they were coming, and switching a plugin off is not the
 * same as asking for that to be thrown away. Uninstall handles removal, and
 * only when the church has said so.
 */
class Installer {

	/**
	 * Run on activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::set_defaults();

		// The shared submissions table, created on demand rather than by every
		// plugin that bundles the library.
		if ( class_exists( '\Seedcast_Core' ) && method_exists( '\Seedcast_Core', 'require_submissions' ) ) {
			\Seedcast_Core::require_submissions();
		}

		update_option( 'scvc_activated_on', current_time( 'Y-m-d' ), false );
	}

	/**
	 * Run on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Nothing to tear down. No cron, no rewrite rules, no temporary state.
	}

	/**
	 * Seed option defaults, without overwriting anything already set.
	 *
	 * add_option rather than update_option throughout: reactivating a plugin
	 * must not reset a church's settings, and this runs on every activation.
	 *
	 * @return void
	 */
	private static function set_defaults(): void {
		foreach ( Settings::defaults() as $key => $value ) {
			add_option( $key, $value );
		}
	}
}
