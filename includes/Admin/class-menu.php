<?php
/**
 * The admin menu.
 *
 * @package SeedcastVisitorCard\Admin
 */

namespace SeedcastVisitorCard\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A top-level menu with the list of cards as its first screen.
 *
 * Top-level rather than tucked under Settings, because the list is something a
 * pastor checks rather than configures, and a screen you look at weekly does
 * not belong three clicks deep.
 */
class Menu {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register' ] );
		add_action( 'admin_menu', [ $this, 'register_settings_link' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
	}

	/**
	 * Add the menu.
	 *
	 * @return void
	 */
	public function register(): void {
		add_menu_page(
			__( 'Visitor Card', 'seedcast-visitor-card' ),
			__( 'Visitor Card', 'seedcast-visitor-card' ),
			'manage_options',
			'seedcast-visitor-card',
			[ $this, 'render_list' ],
			'dashicons-welcome-learn-more',
			26
		);

		/*
		 * Renames the first submenu entry, which WordPress otherwise labels
		 * with the top-level name. "Submissions" rather than "Visitors":
		 * somebody looking for the record of who filled the card in is looking
		 * for submissions, and "Visitors" reads like a statistics screen.
		 */
		add_submenu_page(
			'seedcast-visitor-card',
			__( 'Submissions', 'seedcast-visitor-card' ),
			__( 'Submissions', 'seedcast-visitor-card' ),
			'manage_options',
			'seedcast-visitor-card',
			[ $this, 'render_list' ]
		);

		/*
		 * Settings live on the shared Seedcast page as one more tab, in the
		 * same place as every other plugin in the suite. This entry is a link
		 * to that tab rather than a screen of its own, which is why the last
		 * argument is a URL and not a callback.
		 */
	}

	/**
	 * The settings link, added last so it sits at the bottom of the menu.
	 *
	 * @return void
	 */
	public function register_settings_link(): void {
		// Registered on a later hook so it lands after the Card Designer and
		// the shortcode generator, which add themselves at 12 and 15. Settings
		// is the screen somebody visits least, so it belongs last.
		if ( class_exists( '\\Seedcast\\Core\\Admin\\Settings' ) ) {
			add_submenu_page(
				'seedcast-visitor-card',
				__( 'Settings', 'seedcast-visitor-card' ),
				__( 'Settings', 'seedcast-visitor-card' ),
				'manage_options',
				SettingsPage::url()
			);
		}
	}

	/**
	 * Load admin styles on this plugin's screens only.
	 *
	 * @param string $hook Current screen hook.
	 * @return void
	 */
	public function assets( $hook ): void {
		if ( false === strpos( (string) $hook, 'seedcast-visitor-card' ) ) {
			return;
		}
		wp_enqueue_style(
			'scvc-admin',
			SCVC_PLUGIN_URL . 'assets/css/admin.css',
			[],
			SCVC_VERSION
		);
	}

	/**
	 * The list of cards.
	 *
	 * @return void
	 */
	public function render_list(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Submissions', 'seedcast-visitor-card' ) . '</h1>';
		( new Submissions() )->render();
		echo '</div>';
	}

}
