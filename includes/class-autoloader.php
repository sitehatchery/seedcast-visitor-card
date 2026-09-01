<?php
/**
 * Class autoloader.
 *
 * @package SeedcastVisitorCard
 */

namespace SeedcastVisitorCard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps namespaced class names to files under includes/.
 *
 * Filenames are the class name lowercased with a class- prefix, so
 * SeedcastVisitorCard\Admin\SettingsPage lives at
 * includes/Admin/class-settingspage.php. No hyphenation inside the name and
 * therefore no map of exceptions: the sermon plugin needs one because its
 * filenames were written before the rule settled, and this plugin has no such
 * history to carry.
 */
class Autoloader {

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( [ new self(), 'load' ] );
	}

	/**
	 * Load a class file.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public function load( string $class_name ): void {
		$prefix = 'SeedcastVisitorCard\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$base     = array_pop( $parts );

		$dir = SCVC_PLUGIN_DIR . 'includes/';
		if ( $parts ) {
			$dir .= implode( '/', $parts ) . '/';
		}

		$file = $dir . 'class-' . strtolower( $base ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
