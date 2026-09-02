<?php
/**
 * The visitor card itself.
 *
 * @package SeedcastVisitorCard\Frontend
 */

namespace SeedcastVisitorCard\Frontend;

use Seedcast\Core\Church;
use Seedcast\Core\Frontend\FormRenderer;
use SeedcastVisitorCard\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the card, or the confirmation that replaces it once somebody has
 * filled it in.
 *
 * The form chrome comes from the shared library: the nonce, the honeypot, the
 * captcha and the posting endpoint are all handled there, so this class is
 * only concerned with what a visitor is actually asked.
 */
class Card {

	/**
	 * Shortcode tag.
	 */
	public const SHORTCODE = 'scvc_visitor_card';

	/**
	 * What the shortcode was called first.
	 *
	 * Kept registered so a card already placed on a page does not turn into
	 * the literal text of a shortcode when this plugin updates. Unprefixed
	 * names are the wrong shape for a plugin, since any other plugin could
	 * claim the same one, which is why the prefixed name is the real one now.
	 */
	public const SHORTCODE_LEGACY = 'visitor_card';

	/**
	 * Source and type recorded against every submission, so the shared table
	 * can hold these alongside other plugins' submissions and still be
	 * queried apart.
	 */
	public const SOURCE = 'seedcast-visitor-card';
	public const TYPE   = 'visitor';

	/**
	 * Templates a church can choose between.
	 *
	 * @return array<string, string>
	 */
	public static function templates(): array {
		return [
			'card'    => __( 'Card', 'seedcast-visitor-card' ),
			'classic' => __( 'Classic connection card', 'seedcast-visitor-card' ),
			'bold'    => __( 'Bold connection card', 'seedcast-visitor-card' ),
			'inline'  => __( 'Inline', 'seedcast-visitor-card' ),
			'contact' => __( 'Contact form', 'seedcast-visitor-card' ),
		];
	}

	/**
	 * Templates that put the heading in a filled band across the top, the way
	 * a printed connection card does.
	 *
	 * @return array<int, string>
	 */
	private static function banded(): array {
		return [ 'classic', 'bold' ];
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_shortcode( self::SHORTCODE, [ $this, 'shortcode' ] );

		// Only if nothing else has claimed it. Another plugin using a name
		// this general has as good a claim to it as we ever did.
		if ( ! shortcode_exists( self::SHORTCODE_LEGACY ) ) {
			add_shortcode( self::SHORTCODE_LEGACY, [ $this, 'shortcode' ] );
		}
	}

	/**
	 * Shortcode handler.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			[
				'template' => Settings::get( 'scvc_template' ),
				'heading'  => '',
				'intro'    => '',
				'button'   => '',
				'color'    => '',
				'header'   => '',
				'details'  => '',
				'class'    => '',
			],
			is_array( $atts ) ? $atts : [],
			self::SHORTCODE
		);

		return self::render( [
			'template' => sanitize_key( $atts['template'] ),
			'heading'  => sanitize_text_field( $atts['heading'] ),
			'intro'    => sanitize_text_field( $atts['intro'] ),
			'button'   => sanitize_text_field( $atts['button'] ),
			'color'    => sanitize_text_field( $atts['color'] ),
			'header'   => sanitize_text_field( $atts['header'] ),
			'details'  => self::details_attribute( (string) $atts['details'] ),
			'class'    => sanitize_html_class( $atts['class'] ),
		] );
	}

	/**
	 * Render the card.
	 *
	 * @param array $args Overrides for template, heading, intro, button, class.
	 * @return string
	 */
	public static function render( array $args = [] ): string {
		$args = wp_parse_args( $args, [
			'template' => Settings::get( 'scvc_template' ),
			'heading'  => '',
			'intro'    => '',
			'button'   => '',
			'color'    => '',
			'header'   => '',
			'class'    => '',
			// An explicit list of fields to render, for the designer preview,
			// which shows every field and hides the ones that are switched
			// off. Without it, a field that is off would not be in the markup
			// at all and ticking it could not show anything.
			'fields'   => null,
			/*
			 * Render as a picture of the card rather than a working one: no
			 * form element, no required attributes, nothing that submits.
			 *
			 * The admin screens sit inside their own form, and a form inside a
			 * form is invalid HTML. The browser hoists the inner one out, and
			 * saving the outer form then tries to validate the preview's
			 * required fields, so pressing Save asks you to fill in a card you
			 * were only looking at.
			 */
			'preview'  => false,
			/*
			 * Force the confirmation, for the designer. The real card shows it
			 * only to somebody who has filled it in, which makes the message
			 * most visitors end up looking at the one thing that cannot be
			 * checked before it goes out.
			 */
			'confirmed' => null,
			// null means follow the setting; '1' or '0' overrides it for one
			// placement, so a card on a page that already lists the times can
			// leave them off without changing the rest.
			'details'   => null,
		] );

		if ( ! array_key_exists( $args['template'], self::templates() ) ) {
			$args['template'] = 'card';
		}

		/**
		 * Suppress the card entirely.
		 *
		 * For a template or another plugin that wants to place it by hand.
		 *
		 * @param bool  $show Whether to render.
		 * @param array $args Render arguments.
		 */
		if ( ! apply_filters( 'seedcast/visitor_card/show', true, $args ) ) {
			return '';
		}

		self::enqueue();

		// No gathering to come to. Say so rather than taking a name for a
		// service that is not happening.
		if ( Settings::suspended() ) {
			return self::wrap(
				$args,
				'<p class="scvc-card__suspended">' . esc_html( Settings::suspended_message() ) . '</p>'
			);
		}

		/*
		 * A preview never reads the cookie. An admin who filled the card in on
		 * the front end to test it would otherwise find the Card Designer
		 * showing their own confirmation instead of the form, which looks like
		 * the form has gone and is impossible to design around. The designer
		 * asks for the confirmation explicitly when it wants to show it.
		 */
		$confirmed = null !== $args['confirmed']
			? (string) $args['confirmed']
			: ( empty( $args['preview'] ) ? Confirmation::current() : null );
		if ( null !== $confirmed ) {
			/*
			 * The opening line is an instruction, and they have just followed
			 * it. "Let us know and we will be looking for you" reads as though
			 * nothing happened when it sits above the message saying it did.
			 */
			$args['intro'] = ' ';

			/*
			 * The details matter more here than anywhere. Somebody has just
			 * said they are coming, and the next thing they want is the
			 * address. They also get it by email, but only if they left one.
			 */
			return self::wrap(
				$args,
				'<p class="scvc-card__confirmed">'
					. esc_html( Settings::tokens( Settings::confirmation(), $confirmed ) )
					. '</p>'
					. self::details( $args, true )
			);
		}

		ob_start();
		self::form( $args );
		$inner = self::details( $args, false ) . (string) ob_get_clean();

		return self::wrap( $args, $inner );
	}

	/**
	 * Wrap rendered contents in the card shell.
	 *
	 * @param array  $args  Render arguments.
	 * @param string $inner Inner markup, already escaped.
	 * @return string
	 */
	private static function wrap( array $args, string $inner ): string {
		$heading = '' !== $args['heading'] ? $args['heading'] : Settings::heading();
		// A single space means deliberately empty, as opposed to not set.
		$intro = '' !== trim( $args['intro'] ) ? $args['intro'] : ( ' ' === $args['intro'] ? '' : Settings::intro() );

		$classes = 'scvc-card scvc-card--' . $args['template'];
		if ( '' !== $args['class'] ) {
			$classes .= ' ' . $args['class'];
		}

		$style = '';
		if ( Settings::styles_enabled() ) {
			/*
			 * Nothing is emitted unless a color has actually been chosen. With
			 * no override the stylesheet falls through to the suite's own
			 * tokens, so the card matches whatever theme is set under
			 * Settings, Seedcast, Appearance rather than carrying a color of
			 * its own that has to be kept in step by hand.
			 */
			$accent = self::pick_color( $args['color'], Settings::get( 'scvc_accent' ) );
			$dark   = self::pick_color( $args['header'], Settings::get( 'scvc_accent_dark' ) );

			$vars  = [];
			$theme = Settings::theme_colors();

			/*
			 * The background only gets written when it has been overridden, so
			 * an unset card genuinely inherits the suite token. The text color
			 * is always written, computed from whichever background will
			 * actually be used, because the theme's own accent is a mid gold
			 * that needs dark text and the stylesheet default is white.
			 */
			if ( '' !== $accent ) {
				$vars[] = '--scvc-accent:' . $accent;
			}
			$vars[] = '--scvc-on-accent:' . Settings::text_on( '' !== $accent ? $accent : $theme['accent'] );

			if ( '' !== $dark ) {
				$vars[] = '--scvc-accent-dark:' . $dark;
			}
			$vars[] = '--scvc-on-accent-dark:' . Settings::text_on( '' !== $dark ? $dark : $theme['dark'] );

			if ( $vars ) {
				$style = ' style="' . esc_attr( implode( ';', $vars ) ) . '"';
			}
		}

		$banded = in_array( $args['template'], self::banded(), true );

		ob_start();
		?>
		<?php // data-sc-replace tells the shared script what to swap out when the card is sent. ?>
		<div class="<?php echo esc_attr( $classes ); ?>" data-sc-replace<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from a validated hex color above. ?>>
			<?php
			/*
			 * The heading only leaves the body when it has a band of its own to
			 * live in. It used to sit outside the body on every template, which
			 * meant the body's padding indented the opening line and everything
			 * under it while the heading stayed hard against the edge, so the
			 * two never lined up.
			 */
			?>
			<?php if ( $banded && '' !== $heading ) : ?>
				<div class="scvc-card__band">
					<p class="scvc-card__heading"><?php echo esc_html( $heading ); ?></p>
				</div>
			<?php endif; ?>

			<div class="scvc-card__body">
			<?php if ( ! $banded && '' !== $heading ) : ?>
				<p class="scvc-card__heading"><?php echo esc_html( $heading ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $intro ) : ?>
				<p class="scvc-card__intro"><?php echo esc_html( $intro ); ?></p>
			<?php endif; ?>
			<?php
			// Callers pass markup they have already escaped.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $inner;
			?>
			<?php
			/*
			 * Only ever seen by somebody who can change the site. Without it,
			 * the card behaving differently for them than for a visitor is a
			 * mystery, and the natural conclusion is that the confirmation is
			 * broken.
			 */
			if ( ! empty( $args['preview'] ) || ! Confirmation::is_tester() ) {
				// Not shown in the designer preview, where it would be noise.
				$show_note = false;
			} else {
				$show_note = true;
			}
			?>
			<?php if ( $show_note ) : ?>
				<p class="scvc-card__tester">
					<?php esc_html_e( 'You are signed in as an administrator, so this card will not remember you. Send it as often as you like: you will see the confirmation each time, and the form again on the next page. Visitors see the confirmation until the service has passed.', 'seedcast-visitor-card' ); ?>
				</p>
			<?php endif; ?>
			</div><!-- /.scvc-card__body -->
		</div>
		<?php
		$html = (string) ob_get_clean();

		/**
		 * Filter the rendered card.
		 *
		 * @param string $html Rendered markup.
		 * @param array  $args Render arguments.
		 */
		return (string) apply_filters( 'seedcast/visitor_card/html', $html, $args );
	}

	/**
	 * Read the details attribute, in either the old or the new spelling.
	 *
	 * It used to take 1 and 0. Those still work, so a shortcode already on a
	 * page does not change meaning: 1 meant show it, which is now the mode
	 * that shows it after sending, and 0 meant never.
	 *
	 * @param string $value Attribute value.
	 * @return string|null Mode, or null to follow the setting.
	 */
	private static function details_attribute( string $value ): ?string {
		$value = strtolower( trim( $value ) );

		if ( '' === $value ) {
			return null;
		}
		if ( '0' === $value ) {
			return 'never';
		}
		if ( '1' === $value ) {
			return 'after';
		}

		return in_array( $value, [ 'after', 'always', 'never' ], true ) ? $value : null;
	}

	/**
	 * When and where the church meets.
	 *
	 * Comes from the shared church settings, so it is filled in once for the
	 * whole suite and every card follows a change to the service time rather
	 * than needing to be edited.
	 *
	 * @param array $args Render arguments.
	 * @return string
	 */
	private static function details( array $args, bool $confirmed = false ): string {
		if ( null === $args['details'] ) {
			$wanted = Settings::shows_details( $confirmed );
		} elseif ( 'never' === $args['details'] ) {
			$wanted = false;
		} elseif ( 'always' === $args['details'] ) {
			$wanted = true;
		} else {
			// 'after', or the 1 and 0 the attribute used to take.
			$wanted = $confirmed;
		}

		if ( ! $wanted ) {
			return '';
		}

		$day        = Church::service_day_label();
		$times      = Church::service_times_lines();
		$address    = Church::address_one_line();
		$directions = Church::directions_url();

		/*
		 * The day on its own is not enough to be worth a box. The service day
		 * option defaults to Sunday whether or not anyone has set it, so a
		 * church that has filled in nothing would otherwise get a panel headed
		 * "When and where" that says only "Sunday", which is a guess presented
		 * as a fact. Times or an address are things somebody actually entered.
		 */
		if ( ! $times && '' === $address ) {
			return '';
		}

		ob_start();
		?>
		<div class="scvc-card__details">
			<p class="scvc-card__details-heading"><?php echo esc_html( Settings::details_heading() ); ?></p>

			<?php if ( '' !== $day || $times ) : ?>
				<p class="scvc-card__when">
					<?php if ( '' !== $day ) : ?>
						<span class="scvc-card__day"><?php echo esc_html( $day ); ?></span>
					<?php endif; ?>
					<?php foreach ( $times as $time ) : ?>
						<span class="scvc-card__time"><?php echo esc_html( $time ); ?></span>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<?php if ( '' !== $address ) : ?>
				<p class="scvc-card__where">
					<?php if ( '' !== $directions ) : ?>
						<a href="<?php echo esc_url( $directions ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( $address ); ?>
						</a>
					<?php else : ?>
						<?php echo esc_html( $address ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php $note = Church::visitor_note(); ?>
			<?php if ( '' !== $note ) : ?>
				<p class="scvc-card__note"><?php echo esc_html( $note ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * The form fields between the shared open and close.
	 *
	 * @param array $args Render arguments.
	 * @return void
	 */
	private static function form( array $args ): void {
		/**
		 * Whether a field should be rendered.
		 *
		 * @param string $field Field key.
		 * @return bool
		 */
		$wants = static function ( string $field ) use ( $args ): bool {
			if ( is_array( $args['fields'] ) ) {
				return in_array( $field, $args['fields'], true );
			}
			return Settings::uses( $field );
		};

		$template = locate_template( [ 'seedcast/visitor-card-form.php' ] );
		if ( $template ) {
			// A theme taking the fields over entirely. $args is in scope.
			include $template;
			return;
		}

		$preview = ! empty( $args['preview'] );

		if ( $preview ) {
			echo '<div class="sc-form scvc-card__preview-form" aria-hidden="true">';
		} else {
			FormRenderer::open( self::SOURCE, self::TYPE, [] );
		}

		/**
		 * Fires before the card's own fields.
		 *
		 * @param array $args Render arguments.
		 */
		do_action( 'seedcast/visitor_card/before_fields', $args );

		if ( $wants( 'name' ) ) {
			self::text_row(
				'seedcast_author_name',
				__( 'Your name', 'seedcast-visitor-card' ),
				'text',
				true,
				'name',
				$preview
			);
		}

		if ( $wants( 'email' ) ) {
			self::text_row(
				'seedcast_author_email',
				__( 'Email', 'seedcast-visitor-card' ),
				'email',
				// Required only when there is no phone field to fall back on.
				! $wants( 'phone' ),
				'email',
				$preview
			);
		}

		if ( $wants( 'phone' ) ) {
			self::text_row(
				'scvc_phone',
				__( 'Phone', 'seedcast-visitor-card' ),
				'tel',
				// Required only when it is the sole way to reach them, since
				// otherwise an email address will do.
				! $wants( 'email' ),
				'tel',
				$preview
			);
		}

		/*
		 * The rule is one or the other, which nothing on the form said. A
		 * visitor filling in neither was told so only after pressing the
		 * button, having been given no reason to think either was needed.
		 */
		if ( $wants( 'email' ) && $wants( 'phone' ) ) {
			echo '<p class="sc-form__help scvc-card__either">'
				. esc_html__( 'An email address or a phone number, whichever suits you. We need one so we can say hello.', 'seedcast-visitor-card' )
				. '</p>';
		}

		if ( $wants( 'party' ) ) {
			?>
			<div class="sc-form__row scvc-row">
				<label class="sc-form__label" for="scvc_party">
					<?php esc_html_e( 'How many are coming?', 'seedcast-visitor-card' ); ?>
				</label>
				<input type="number" id="scvc_party" name="scvc_party" min="1" max="99" inputmode="numeric" />
			</div>
			<?php
		}

		if ( $wants( 'heard' ) ) {
			self::text_row(
				'scvc_heard',
				__( 'How did you hear about us?', 'seedcast-visitor-card' ),
				'text',
				false,
				'off'
			);
		}

		if ( $wants( 'message' ) ) {
			?>
			<div class="sc-form__row scvc-row">
				<label class="sc-form__label" for="scvc_message">
					<?php esc_html_e( 'Anything you would like us to know?', 'seedcast-visitor-card' ); ?>
				</label>
				<textarea id="scvc_message" name="scvc_message" rows="3"></textarea>
			</div>
			<?php
		}

		if ( $wants( 'status' ) ) {
			self::choice_group(
				'scvc_status',
				Settings::status_legend(),
				Settings::statuses(),
				'radio'
			);
		}

		if ( $wants( 'interests' ) ) {
			self::choice_group(
				'scvc_interests',
				Settings::interests_legend(),
				Settings::interests(),
				'checkbox'
			);
		}

		/**
		 * Fires after the card's own fields, before the consent line.
		 *
		 * @param array $args Render arguments.
		 */
		do_action( 'seedcast/visitor_card/after_fields', $args );

		$consent = Settings::consent();
		if ( '' !== $consent ) {
			echo '<p class="scvc-card__consent">' . esc_html( $consent );

			$policy = (int) get_option( 'wp_page_for_privacy_policy' );
			if ( $policy > 0 && 'publish' === get_post_status( $policy ) ) {
				echo ' <a href="' . esc_url( (string) get_permalink( $policy ) ) . '">'
					. esc_html__( 'Privacy policy', 'seedcast-visitor-card' )
					. '</a>';
			}

			echo '</p>';
		}

		$button = '' !== $args['button'] ? $args['button'] : Settings::button();

		if ( $preview ) {
			// A span, not a button. A disabled button still reads as a control
			// to a screen reader, and there is nothing here to press.
			echo '<span class="sc-btn scvc-card__preview-btn">' . esc_html( $button ) . '</span>';
			echo '</div>';
			return;
		}

		FormRenderer::close( $button );
	}

	/**
	 * The first of two values that is a usable hex color.
	 *
	 * @param string $preferred Value from the placement.
	 * @param string $fallback  Site default.
	 * @return string
	 */
	private static function pick_color( string $preferred, string $fallback ): string {
		if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', trim( $preferred ) ) ) {
			return trim( $preferred );
		}
		if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', trim( $fallback ) ) ) {
			return trim( $fallback );
		}

		// Neither is a color. An empty string means no variable is written and
		// the stylesheet falls through to the suite theme.
		return '';
	}

	/**
	 * A group of radios or checkboxes.
	 *
	 * A fieldset with a legend rather than a bare heading, so a screen reader
	 * announces what the options belong to instead of reading five unlabeled
	 * choices in a row.
	 *
	 * @param string $name    Field name.
	 * @param string $legend  Group label.
	 * @param array  $choices Value to label.
	 * @param string $type    radio or checkbox.
	 * @return void
	 */
	private static function choice_group( string $name, string $legend, array $choices, string $type ): void {
		if ( ! $choices ) {
			return;
		}

		$type       = 'checkbox' === $type ? 'checkbox' : 'radio';
		$input_name = 'checkbox' === $type ? $name . '[]' : $name;
		?>
		<fieldset class="sc-form__row scvc-row scvc-choices">
			<legend class="sc-form__label"><?php echo esc_html( $legend ); ?></legend>
			<div class="scvc-choices__list">
				<?php foreach ( $choices as $value => $label ) : ?>
					<label class="scvc-choice">
						<input type="<?php echo esc_attr( $type ); ?>"
							name="<?php echo esc_attr( $input_name ); ?>"
							value="<?php echo esc_attr( (string) $value ); ?>" />
						<span><?php echo esc_html( (string) $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * One labeled input.
	 *
	 * @param string $name     Field name and id.
	 * @param string $label    Visible label.
	 * @param string $type     Input type.
	 * @param bool   $required Whether it is required.
	 * @param string $autocomplete Autocomplete token.
	 * @return void
	 */
	private static function text_row( string $name, string $label, string $type, bool $required, string $autocomplete, bool $preview = false ): void {
		// Nothing in a preview should be required: it is never submitted, and
		// a required field inside an admin form blocks that form from saving.
		if ( $preview ) {
			$required = false;
		}
		?>
		<div class="sc-form__row scvc-row">
			<label class="sc-form__label" for="<?php echo esc_attr( $name ); ?>">
				<?php echo esc_html( $label ); ?>
				<?php if ( $required ) : ?>
					<span class="sc-form__req" aria-hidden="true">*</span>
					<span class="sc-screen-reader-text"><?php esc_html_e( '(required)', 'seedcast-visitor-card' ); ?></span>
				<?php endif; ?>
			</label>
			<input
				type="<?php echo esc_attr( $type ); ?>"
				id="<?php echo esc_attr( $name ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				autocomplete="<?php echo esc_attr( $autocomplete ); ?>"
				<?php echo $required ? 'required' : ''; ?> />
		</div>
		<?php
	}

	/**
	 * Load the stylesheet, once, and only when a card is on the page.
	 *
	 * @return void
	 */
	private static function enqueue(): void {
		if ( ! Settings::styles_enabled() ) {
			return;
		}
		if ( wp_style_is( 'scvc-card', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_style( 'scvc-card' );
	}
}
