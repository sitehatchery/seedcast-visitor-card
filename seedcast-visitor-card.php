<?php
/**
 * Plugin Name: Visitor Card
 * Plugin URI:  https://seedcast.ai/visitor-card
 * Description: A visitor card for your church website. Someone thinking about coming can say so, and you will know they are coming.
 * Version:     1.20.0
 * Author:      Seedcast
 * Author URI:  https://seedcast.ai
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: seedcast-visitor-card
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 *
 * @package SeedcastVisitorCard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SCVC_VERSION',          '1.20.0' );
define( 'SCVC_PLUGIN_FILE',      __FILE__ );
define( 'SCVC_PLUGIN_DIR',       plugin_dir_path( __FILE__ ) );
define( 'SCVC_PLUGIN_URL',       plugin_dir_url( __FILE__ ) );

/**
 * The bundled shared library. Every Seedcast plugin carries a copy and the
 * highest version present is the one that runs, so a site with several of them
 * installed uses one library rather than one per plugin.
 */
define( 'SCVC_CORE_VERSION',     '1.26.2' );
define( 'SCVC_CORE_MIN_VERSION', '1.21.0' );

require_once SCVC_PLUGIN_DIR . 'lib/seedcast-core/loader.php';
\Seedcast_Core_Registry::register(
	SCVC_CORE_VERSION,
	SCVC_PLUGIN_DIR . 'lib/seedcast-core/bootstrap.php'
);

require_once SCVC_PLUGIN_DIR . 'includes/class-autoloader.php';
\SeedcastVisitorCard\Autoloader::register();

register_activation_hook( __FILE__, [ '\SeedcastVisitorCard\Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ '\SeedcastVisitorCard\Installer', 'deactivate' ] );

/**
 * Boot the plugin.
 *
 * Runs on plugins_loaded so the shared library has finished version
 * negotiation and Seedcast\Core is available.
 */
function scvc_boot(): void {
	if ( ! class_exists( '\Seedcast\Core\Church' ) ) {
		// The bundled library did not load. Nothing here works without it, and
		// failing quietly is better than a page of fatals.
		return;
	}

	\Seedcast_Core::register_plugin( 'seedcast-visitor-card', [
		'name'        => 'Visitor Card',
		'tagline'     => 'Let people tell you they are coming, and know when they do.',
		'icon'        => 'dashicons-welcome-learn-more',
		'admin'       => 'admin.php?page=seedcast-visitor-card',
		'channel'     => 'free',
		'file'        => plugin_basename( __FILE__ ),
		'version'     => SCVC_VERSION,
		'admin_menu'  => 'seedcast-visitor-card',
		'post_types'  => [],
		// The shared submission engine handles the posting, spam checks and
		// storage. This plugin supplies the form and reacts to what arrives.
		'submissions' => true,
		'min_core'    => SCVC_CORE_MIN_VERSION,
	] );

	/*
	 * Make sure the shared submissions table exists.
	 *
	 * The activation hook is not enough on its own. Activating a plugin
	 * includes its file after plugins_loaded has already fired, so the shared
	 * library never boots during that request and Seedcast_Core does not exist
	 * yet when the activation hook runs. On a site where this is the first
	 * Seedcast plugin, the table would never be created and the Visitors
	 * screen would query something that is not there.
	 *
	 * Cheap to call: it compares one option and returns.
	 */
	if ( method_exists( '\Seedcast_Core', 'require_submissions' ) ) {
		\Seedcast_Core::require_submissions();
	}

	( new \SeedcastVisitorCard\Frontend\Card() )->init();
	( new \SeedcastVisitorCard\Frontend\Placement() )->init();
	( new \SeedcastVisitorCard\Frontend\ElementorCard() )->init();
	( new \SeedcastVisitorCard\Submissions\Handler() )->init();
	( new \SeedcastVisitorCard\Privacy() )->init();

	if ( is_admin() ) {
		( new \SeedcastVisitorCard\Admin\Menu() )->init();
		( new \SeedcastVisitorCard\Admin\SettingsPage() )->init();
		( new \SeedcastVisitorCard\Admin\Submissions() )->init();
		( new \SeedcastVisitorCard\Admin\Designer() )->init();
		( new \SeedcastVisitorCard\Admin\Sources() )->init();
		( new \SeedcastVisitorCard\Admin\ShortcodeGenerator() )->init();
		( new \SeedcastVisitorCard\Admin\Export() )->init();
	}
}
add_action( 'plugins_loaded', 'scvc_boot', 20 );

/**
 * Front-end styles.
 *
 * Enqueued only when a card has actually been rendered, so a page without one
 * carries no extra stylesheet. Card::mark_rendered() sets the flag.
 */
function scvc_enqueue_styles(): void {
	if ( '1' === get_option( 'scvc_disable_css', '0' ) ) {
		return;
	}
	wp_register_style( 'scvc-card', SCVC_PLUGIN_URL . 'assets/css/visitor-card.css', [], SCVC_VERSION );
}
add_action( 'wp_enqueue_scripts', 'scvc_enqueue_styles' );
