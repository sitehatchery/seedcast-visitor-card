<?php
/**
 * Shortcode generator.
 *
 * @package SeedcastVisitorCard\Admin
 */

namespace SeedcastVisitorCard\Admin;

use SeedcastVisitorCard\Frontend\Card;
use SeedcastVisitorCard\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a shortcode by picking options rather than by remembering attribute
 * syntax, in the same shape as the Sermon Library generator so somebody
 * running both meets one screen twice rather than two screens once.
 *
 * The preview matters more here than it does there. Most of what this
 * shortcode does is decided in settings, so the useful question on this screen
 * is not "what will the shortcode say" but "what will the visitor see", and
 * the answer to that is rendered live.
 */
class ShortcodeGenerator {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_page' ], 15 );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
	}

	/**
	 * Add the submenu.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page(
			'seedcast-visitor-card',
			__( 'Shortcode Generator', 'seedcast-visitor-card' ),
			__( 'Shortcodes', 'seedcast-visitor-card' ),
			'edit_posts',
			'seedcast-visitor-card-shortcodes',
			[ $this, 'render' ]
		);
	}

	/**
	 * Load the builder script on this screen only.
	 *
	 * @param string $hook Current screen.
	 * @return void
	 */
	public function assets( $hook ): void {
		if ( false === strpos( (string) $hook, 'seedcast-visitor-card-shortcodes' ) ) {
			return;
		}

		wp_enqueue_style( 'scvc-admin', SCVC_PLUGIN_URL . 'assets/css/admin.css', [], SCVC_VERSION );

		// The preview is the real card, so it needs the real stylesheet.
		wp_enqueue_style( 'scvc-card', SCVC_PLUGIN_URL . 'assets/css/visitor-card.css', [], SCVC_VERSION );
		wp_enqueue_script( 'scvc-shortcodes', SCVC_PLUGIN_URL . 'assets/js/shortcodes.js', [], SCVC_VERSION, true );

		wp_localize_script( 'scvc-shortcodes', 'scvcSc', [
			'copied' => __( 'Copied', 'seedcast-visitor-card' ),
			'copy'   => __( 'Copy shortcode', 'seedcast-visitor-card' ),
			'tag'    => Card::SHORTCODE,
		] );
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		?>
		<div class="wrap scvc-sc-wrap">
			<h1><?php esc_html_e( 'Shortcode Generator', 'seedcast-visitor-card' ); ?></h1>
			<p class="scvc-sc-intro">
				<?php esc_html_e( 'Set the options you want, then copy the shortcode into any page or post. Leave something blank and the card uses your settings.', 'seedcast-visitor-card' ); ?>
			</p>

			<div class="scvc-sc-layout">
				<div class="scvc-sc-builder">

					<div class="scvc-sc-card">
						<h2 class="scvc-sc-card__title">
							<span class="scvc-sc-step">1</span>
							<?php esc_html_e( 'Template and color', 'seedcast-visitor-card' ); ?>
						</h2>
						<div class="scvc-sc-options">
							<?php
							$styles = [
								'card'    => [
									'icon'  => '🗂',
									'label' => __( 'Card', 'seedcast-visitor-card' ),
									'desc'  => __( 'A bounded block with a colored edge. The default.', 'seedcast-visitor-card' ),
								],
								'classic' => [
									'icon'  => '📋',
									'label' => __( 'Classic connection card', 'seedcast-visitor-card' ),
									'desc'  => __( 'Heading in a band, ruled lines under each field, like a printed card.', 'seedcast-visitor-card' ),
								],
								'bold'    => [
									'icon'  => '🎫',
									'label' => __( 'Bold connection card', 'seedcast-visitor-card' ),
									'desc'  => __( 'Heavier band and uppercase labels, closer to a modern connect card.', 'seedcast-visitor-card' ),
								],
								'inline'  => [
									'icon'  => '↔',
									'label' => __( 'Inline', 'seedcast-visitor-card' ),
									'desc'  => __( 'No frame, for a theme that already frames its sections.', 'seedcast-visitor-card' ),
								],
								'contact' => [
									'icon'  => '✉',
									'label' => __( 'Contact form', 'seedcast-visitor-card' ),
									'desc'  => __( 'A plain stacked form, for your contact page.', 'seedcast-visitor-card' ),
								],
							];
							$current = Settings::get( 'scvc_template' );
							foreach ( $styles as $value => $style ) :
								?>
								<label class="scvc-sc-option <?php echo $value === $current ? 'is-selected' : ''; ?>">
									<input type="radio" name="scvc_template_choice" class="scvc-sc-radio"
										value="<?php echo esc_attr( $value ); ?>" data-param="template"
										<?php checked( $value, $current ); ?> />
									<span class="scvc-sc-option__icon" aria-hidden="true"><?php echo esc_html( $style['icon'] ); ?></span>
									<span class="scvc-sc-option__body">
										<strong><?php echo esc_html( $style['label'] ); ?></strong>
										<span><?php echo esc_html( $style['desc'] ); ?></span>
									</span>
								</label>
							<?php endforeach; ?>
						</div>

						<table class="scvc-sc-table scvc-sc-color-row">
							<?php
							/*
							 * When the card is following the suite theme there
							 * is no saved color, and a color input with an
							 * empty value falls back to black. These open on
							 * the color the card is actually using instead.
							 */
							$theme_colors = Settings::theme_colors();
							$accent_now   = Settings::follows_theme()
								? $theme_colors['accent']
								: Settings::get( 'scvc_accent' );
							$header_now   = Settings::follows_theme()
								? $theme_colors['dark']
								: Settings::get( 'scvc_accent_dark' );
							?>
							<tr>
								<th><label for="scvc-sc-color"><?php esc_html_e( 'Accent color', 'seedcast-visitor-card' ); ?></label></th>
								<td>
									<input type="color" id="scvc-sc-color" class="scvc-sc-color"
										data-param="color"
										data-default="<?php echo esc_attr( $accent_now ); ?>"
										value="<?php echo esc_attr( $accent_now ); ?>" />
									<button type="button" class="button-link scvc-sc-reset" id="scvc-sc-color-reset">
										<?php esc_html_e( 'Back to your default', 'seedcast-visitor-card' ); ?>
									</button>
									<p class="description">
										<?php esc_html_e( 'The button, the band across the top and the focus outline. Text is set to whichever of black or white reads against it.', 'seedcast-visitor-card' ); ?>
									</p>
								</td>
							</tr>
							<tr class="scvc-sc-header-row">
								<th><label for="scvc-sc-header"><?php esc_html_e( 'Header color', 'seedcast-visitor-card' ); ?></label></th>
								<td>
									<input type="color" id="scvc-sc-header" class="scvc-sc-color"
										data-param="header"
										data-default="<?php echo esc_attr( $header_now ); ?>"
										value="<?php echo esc_attr( $header_now ); ?>" />
									<button type="button" class="button-link scvc-sc-reset" id="scvc-sc-header-reset">
										<?php esc_html_e( 'Back to your default', 'seedcast-visitor-card' ); ?>
									</button>
									<p class="description">
										<?php esc_html_e( 'The band across the top of the bold card, which is the only template that uses it.', 'seedcast-visitor-card' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="scvc-sc-details"><?php esc_html_e( 'When and where', 'seedcast-visitor-card' ); ?></label></th>
								<td>
									<select id="scvc-sc-details" data-param="details" data-default="">
										<option value=""><?php esc_html_e( 'Same as everywhere else', 'seedcast-visitor-card' ); ?></option>
										<?php foreach ( Settings::details_modes() as $mode => $label ) : ?>
											<option value="<?php echo esc_attr( $mode ); ?>"><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description">
										<?php esc_html_e( 'Worth leaving off on a page that already lists your times.', 'seedcast-visitor-card' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th><label for="scvc-sc-class"><?php esc_html_e( 'Extra CSS class', 'seedcast-visitor-card' ); ?></label></th>
								<td>
									<input type="text" id="scvc-sc-class" class="regular-text" data-param="class"
										placeholder="<?php esc_attr_e( 'Optional', 'seedcast-visitor-card' ); ?>" />
								</td>
							</tr>
						</table>

						<p class="description scvc-sc-scope">
							<?php
							printf(
								/* translators: %s: link to the card designer. */
								esc_html__( 'These change this one card only. To change every card, use the %s.', 'seedcast-visitor-card' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=seedcast-visitor-card-designer' ) ) . '">'
									. esc_html__( 'card designer', 'seedcast-visitor-card' ) . '</a>'
							);
							?>
						</p>

						<?php if ( ! Settings::styles_enabled() ) : ?>
							<p class="description scvc-sc-css-note">
								<?php esc_html_e( 'This plugin\'s stylesheet is switched off in settings, so none of this applies on the front end.', 'seedcast-visitor-card' ); ?>
							</p>
						<?php endif; ?>
					</div>

					<div class="scvc-sc-card">
						<h2 class="scvc-sc-card__title">
							<span class="scvc-sc-step">2</span>
							<?php esc_html_e( 'Change the wording, just here', 'seedcast-visitor-card' ); ?>
						</h2>
						<p class="description">
							<?php esc_html_e( 'Only for this one placement. Leave blank to use what you set in Settings, which is usually what you want.', 'seedcast-visitor-card' ); ?>
						</p>
						<table class="scvc-sc-table">
							<tr>
								<th><label for="scvc-sc-heading"><?php esc_html_e( 'Heading', 'seedcast-visitor-card' ); ?></label></th>
								<td>
									<input type="text" id="scvc-sc-heading" class="regular-text" data-param="heading"
										placeholder="<?php echo esc_attr( Settings::heading() ); ?>" />
								</td>
							</tr>
							<tr>
								<th><label for="scvc-sc-intro"><?php esc_html_e( 'Opening line', 'seedcast-visitor-card' ); ?></label></th>
								<td>
									<input type="text" id="scvc-sc-intro" class="regular-text" data-param="intro"
										placeholder="<?php echo esc_attr( Settings::intro() ); ?>" />
								</td>
							</tr>
							<tr>
								<th><label for="scvc-sc-button"><?php esc_html_e( 'Button', 'seedcast-visitor-card' ); ?></label></th>
								<td>
									<input type="text" id="scvc-sc-button" class="regular-text" data-param="button"
										placeholder="<?php echo esc_attr( Settings::button() ); ?>" />
								</td>
							</tr>
						</table>
					</div>

					<div class="scvc-sc-card">
						<h2 class="scvc-sc-card__title">
							<span class="scvc-sc-step">3</span>
							<?php esc_html_e( 'What a visitor will see', 'seedcast-visitor-card' ); ?>
						</h2>
						<p class="description">
							<?php esc_html_e( 'Your live card, with your own wording and color. It is not interactive here.', 'seedcast-visitor-card' ); ?>
						</p>
						<div class="scvc-sc-preview" id="scvc-sc-preview">
							<?php
							// The real renderer, so the preview cannot drift from
							// what a visitor actually gets.
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Card::render() escapes everything it emits.
							echo Card::render( [ 'preview' => true ] );
							?>
						</div>
					</div>

				</div>

				<div class="scvc-sc-output-card">
					<div class="scvc-sc-output-header">
						<h2><?php esc_html_e( 'Your shortcode', 'seedcast-visitor-card' ); ?></h2>
					</div>
					<div class="scvc-sc-output-code-wrap">
						<code class="scvc-sc-code" id="scvc-sc-code">[<?php echo esc_html( Card::SHORTCODE ); ?>]</code>
					</div>
					<button type="button" class="button button-primary scvc-sc-copy-btn" id="scvc-sc-copy">
						<?php esc_html_e( 'Copy shortcode', 'seedcast-visitor-card' ); ?>
					</button>

					<hr class="scvc-sc-divider" />

					<h3><?php esc_html_e( 'Where to put it', 'seedcast-visitor-card' ); ?></h3>
					<ol class="scvc-sc-instructions">
						<li><?php esc_html_e( 'Your what to expect page.', 'seedcast-visitor-card' ); ?></li>
						<li><?php esc_html_e( 'Your contact page, using the contact form style.', 'seedcast-visitor-card' ); ?></li>
						<li><?php esc_html_e( 'Anywhere a first-time visitor is likely to be reading.', 'seedcast-visitor-card' ); ?></li>
					</ol>
					<p class="description">
						<?php esc_html_e( 'It already appears on your sermons and services automatically, so you do not need it there.', 'seedcast-visitor-card' ); ?>
					</p>

					<hr class="scvc-sc-divider" />

					<h3><?php esc_html_e( 'Attributes', 'seedcast-visitor-card' ); ?></h3>
					<table class="scvc-sc-ref-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Attribute', 'seedcast-visitor-card' ); ?></th>
								<th><?php esc_html_e( 'Does what', 'seedcast-visitor-card' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>template</code></td>
								<td><?php esc_html_e( 'card, inline or contact', 'seedcast-visitor-card' ); ?></td>
							</tr>
							<tr>
								<td><code>heading</code></td>
								<td><?php esc_html_e( 'Replaces the heading', 'seedcast-visitor-card' ); ?></td>
							</tr>
							<tr>
								<td><code>intro</code></td>
								<td><?php esc_html_e( 'Replaces the opening line', 'seedcast-visitor-card' ); ?></td>
							</tr>
							<tr>
								<td><code>button</code></td>
								<td><?php esc_html_e( 'Replaces the button', 'seedcast-visitor-card' ); ?></td>
							</tr>
							<tr>
								<td><code>color</code></td>
								<td><?php esc_html_e( 'A hex color, for this card only', 'seedcast-visitor-card' ); ?></td>
							</tr>
							<tr>
								<td><code>header</code></td>
								<td><?php esc_html_e( 'The band color on the bold template', 'seedcast-visitor-card' ); ?></td>
							</tr>
							<tr>
								<td><code>details</code></td>
								<td><?php esc_html_e( 'after, always or never, for the service details', 'seedcast-visitor-card' ); ?></td>
							</tr>
							<tr>
								<td><code>class</code></td>
								<td><?php esc_html_e( 'Adds a CSS class', 'seedcast-visitor-card' ); ?></td>
							</tr>
						</tbody>
					</table>

					<hr class="scvc-sc-divider" />

					<h3><?php esc_html_e( 'Using Elementor?', 'seedcast-visitor-card' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Search for Visitor Card in the widget panel. Same options, no shortcode needed.', 'seedcast-visitor-card' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}
}
