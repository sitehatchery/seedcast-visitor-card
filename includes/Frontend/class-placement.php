<?php
/**
 * Where the card shows up without being asked for.
 *
 * @package SeedcastVisitorCard\Frontend
 */

namespace SeedcastVisitorCard\Frontend;

use Seedcast\Core\Registry;
use SeedcastVisitorCard\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appends the card to the content a first-time visitor is most likely to be
 * reading: a sermon, a bulletin, a song. Somebody who has just listened to a
 * message is closer to visiting than somebody on the contact page, and asking
 * them there is the whole idea.
 *
 * Only on single views of Seedcast content, never on archives, never on
 * ordinary posts and pages. A plugin that injects a form into unrelated
 * content is how a plugin earns a one-star review, so everything outside the
 * suite is placed by hand with the shortcode or the Elementor widget.
 */
class Placement {

	/**
	 * Post types that never get the card, whatever else is switched on.
	 *
	 * Praise Report carries its own forms. Two forms stacked on one page asks
	 * a visitor to choose between telling you they are coming and sharing an
	 * answered prayer, which is not a choice worth making them make.
	 *
	 * @var array<int, string>
	 */
	private const NEVER = [
		'seedcast_praise',
		'scpr_praise',
		'scpr_report',
	];

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'the_content', [ $this, 'append' ], 20 );

		/*
		 * Sermon Library and Bulletin Library each give the sidebar its own
		 * hook, which is a better home than the foot of the content: somebody
		 * who has just finished listening should not have to scroll past the
		 * transcript and the rest of the series to find the card, and on a
		 * service page the card should sit alongside the day's programs and
		 * announcements rather than under them. When either of these hooks
		 * fires, the content placement stands down so the card appears once.
		 */
		add_action( 'scsl_sermon_sidebar_end', [ $this, 'sermon_sidebar' ] );
		add_action( 'scbl_service_sidebar_end', [ $this, 'service_sidebar' ] );
	}

	/**
	 * The Sermon Library release that added the sidebar hook.
	 */
	private const SERMON_HOOK_SINCE = '2.51.0';

	/**
	 * The Bulletin Library release that added the sidebar hook.
	 */
	private const SERVICE_HOOK_SINCE = '3.4.1';

	/**
	 * Whether the sermon sidebar will render the card on this request.
	 *
	 * Decided up front rather than discovered afterwards. The content filter
	 * runs before the sidebar does, so a flag set when the sidebar fires would
	 * be set too late and the card would appear twice.
	 *
	 * @param string $type Post type being viewed.
	 * @return bool
	 */
	private static function sidebar_handles( string $type ): bool {
		if ( 'scsl_sermon' === $type ) {
			if ( ! defined( 'SCSL_VERSION' ) ) {
				return false;
			}
			return version_compare( SCSL_VERSION, self::SERMON_HOOK_SINCE, '>=' );
		}

		if ( 'scbl_service' === $type ) {
			if ( ! defined( 'SCBL_VERSION' ) ) {
				return false;
			}
			return version_compare( SCBL_VERSION, self::SERVICE_HOOK_SINCE, '>=' );
		}

		return false;
	}

	/**
	 * Render the card in the Sermon Library sidebar.
	 *
	 * @param int $post_id Sermon being displayed.
	 * @return void
	 */
	public function sermon_sidebar( $post_id = 0 ): void {
		$type = (string) get_post_type( (int) $post_id );

		if ( '' === $type || ! self::enabled_for( $type ) ) {
			return;
		}

		/**
		 * Filter placement in the sermon sidebar specifically.
		 *
		 * @param bool $show    Whether to render.
		 * @param int  $post_id Sermon ID.
		 */
		if ( ! apply_filters( 'seedcast/visitor_card/sermon_sidebar', true, (int) $post_id ) ) {
			return;
		}

		/*
		 * The template a church chose in the designer, not one picked here.
		 * This used to force the inline style on the grounds that a sidebar is
		 * narrow, which meant somebody could design a card, save it, and find
		 * the sermon pages ignoring the design entirely. The narrowness is a
		 * layout problem and belongs in the stylesheet, which the sidebar
		 * class handles.
		 */
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Card::render() escapes everything it emits.
		echo Card::render( [ 'class' => 'scvc-card--sidebar' ] );
	}

	/**
	 * Render the card in the Bulletin Library service sidebar.
	 *
	 * @param int $post_id Service being displayed.
	 * @return void
	 */
	public function service_sidebar( $post_id = 0 ): void {
		$type = (string) get_post_type( (int) $post_id );

		if ( '' === $type || ! self::enabled_for( $type ) ) {
			return;
		}

		/**
		 * Filter placement in the service sidebar specifically.
		 *
		 * @param bool $show    Whether to render.
		 * @param int  $post_id Service ID.
		 */
		if ( ! apply_filters( 'seedcast/visitor_card/service_sidebar', true, (int) $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Card::render() escapes everything it emits.
		echo Card::render( [ 'class' => 'scvc-card--sidebar' ] );
	}

	/**
	 * Post types the card can be attached to on this site.
	 *
	 * Read from the suite registry, so a church that adds another Seedcast
	 * plugin later gets its post types offered without this list being
	 * updated. Falls back to the known sermon types when the registry has
	 * nothing, which is the case on a site running this plugin alone.
	 *
	 * @return array<string, string> Post type to label.
	 */
	public static function candidates(): array {
		$types = [];

		if ( class_exists( '\Seedcast\Core\Registry' ) ) {
			foreach ( Registry::instance()->get_plugins() as $slug => $plugin ) {
				if ( 'seedcast-visitor-card' === $slug ) {
					continue;
				}
				foreach ( (array) ( $plugin['post_types'] ?? [] ) as $type ) {
					if ( in_array( $type, self::NEVER, true ) ) {
						continue;
					}
					$object = get_post_type_object( $type );

					if ( ! $object || ! self::has_single_view( $object ) ) {
						continue;
					}

					$types[ $type ] = $object->labels->name ?? $type;
				}
			}
		}

		return $types;
	}

	/**
	 * Whether a post type has a page of its own for the card to sit on.
	 *
	 * Some post types exist only to hold data that another template pulls in:
	 * a program, a schedule row, a set of service times. They are registered
	 * as post types but never rendered as a page, so offering to add the card
	 * to them offers something that cannot happen, and switching it on would
	 * look broken.
	 *
	 * @param \WP_Post_Type $object Post type object.
	 * @return bool
	 */
	private static function has_single_view( $object ): bool {
		if ( empty( $object->public ) || empty( $object->publicly_queryable ) ) {
			return false;
		}

		// A type with no rewrite and no query var cannot be reached on its own
		// URL, whatever else it declares.
		if ( empty( $object->rewrite ) && empty( $object->query_var ) ) {
			return false;
		}

		/**
		 * Filter whether a post type can carry the card.
		 *
		 * @param bool          $can    Whether it has a page of its own.
		 * @param \WP_Post_Type $object Post type object.
		 */
		return (bool) apply_filters( 'seedcast/visitor_card/has_single_view', true, $object );
	}

	/**
	 * Whether the card should be appended to a given post type.
	 *
	 * On by default for suite content, because that is why somebody installs
	 * this alongside the rest. Any type can be switched off individually.
	 *
	 * @param string $type Post type.
	 * @return bool
	 */
	public static function enabled_for( string $type ): bool {
		if ( in_array( $type, self::NEVER, true ) ) {
			return false;
		}
		/*
		 * Choosing which content to add the card to is the whole setting. A
		 * master switch on top of a list of checkboxes just gives two ways to
		 * say the same thing, and a state where the list looks on but nothing
		 * appears.
		 */
		return '0' !== get_option( 'scvc_embed_' . $type, '1' );
	}

	/**
	 * Append the card to the content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function append( $content ) {
		if ( ! is_string( $content ) ) {
			return $content;
		}

		// Single views only. In a loop on an archive, is_singular() is still
		// true for the queried object, so the main-query check is what keeps
		// the card off listing pages.
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}



		if ( is_admin() || is_feed() || wp_doing_ajax() ) {
			return $content;
		}

		$type = (string) get_post_type();
		if ( ! isset( self::candidates()[ $type ] ) ) {
			return $content;
		}

		if ( ! self::enabled_for( $type ) ) {
			return $content;
		}

		// The sermon sidebar is a better place and will render it there. On an
		// older Sermon Library, or a theme whose template does not fire the
		// hook, this stays the placement so the card still appears.
		if ( self::sidebar_handles( $type ) ) {
			return $content;
		}

		/**
		 * Filter automatic placement for one post.
		 *
		 * @param bool   $show Whether to append.
		 * @param string $type Post type.
		 */
		if ( ! apply_filters( 'seedcast/visitor_card/auto_embed', true, $type ) ) {
			return $content;
		}

		return $content . Card::render();
	}
}
