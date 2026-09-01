<?php
/**
 * Elementor registration.
 *
 * @package SeedcastVisitorCard\Frontend
 */

namespace SeedcastVisitorCard\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the card as an Elementor widget.
 *
 * Church sites are predominantly Elementor, and a pastor placing the card on
 * a page should not have to know what a shortcode is. Everything is deferred
 * until Elementor announces itself, so a site without it loads none of this.
 */
final class ElementorCard {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'elementor/widgets/register', [ $this, 'register' ] );
	}

	/**
	 * Hand the widget to Elementor.
	 *
	 * @param mixed $widgets_manager Elementor widget manager.
	 * @return void
	 */
	public function register( $widgets_manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) || ! is_object( $widgets_manager ) ) {
			return;
		}
		if ( ! method_exists( $widgets_manager, 'register' ) ) {
			return;
		}

		require_once __DIR__ . '/class-elementorcardwidget.php';

		if ( ! class_exists( '\SeedcastVisitorCard\Frontend\ElementorCardWidget' ) ) {
			return;
		}

		$widgets_manager->register( new ElementorCardWidget() );
	}
}
