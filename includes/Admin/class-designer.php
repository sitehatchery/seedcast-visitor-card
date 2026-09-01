<?php
/**
 * Card Designer.
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
 * Where the card is designed: what it asks, what template it uses, what color
 * it is. Everything set here is the default for every card on the site,
 * including the ones added automatically and the ones placed by shortcode.
 *
 * It exists because somebody wondering how to change the styling will not
 * think to look in a shortcode generator. A generator is for producing a line
 * of text to paste somewhere; this is for deciding what the card is.
 */
class Designer {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_page' ], 12 );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
		add_action( 'admin_post_scvc_save_design', [ $this, 'save' ] );
	}

	/**
	 * Add the submenu.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page(
			'seedcast-visitor-card',
			__( 'Card Designer', 'seedcast-visitor-card' ),
			__( 'Card Designer', 'seedcast-visitor-card' ),
			'manage_options',
			'seedcast-visitor-card-designer',
			[ $this, 'render' ]
		);
	}

	/**
	 * Load the builder assets on this screen.
	 *
	 * @param string $hook Current screen.
	 * @return void
	 */
	public function assets( $hook ): void {
		if ( false === strpos( (string) $hook, 'seedcast-visitor-card-designer' ) ) {
			return;
		}

		wp_enqueue_style( 'scvc-admin', SCVC_PLUGIN_URL . 'assets/css/admin.css', [], SCVC_VERSION );

		// The preview is the real card, so it needs the real stylesheet.
		wp_enqueue_style( 'scvc-card', SCVC_PLUGIN_URL . 'assets/css/visitor-card.css', [], SCVC_VERSION );

		wp_enqueue_script( 'scvc-designer', SCVC_PLUGIN_URL . 'assets/js/designer.js', [], SCVC_VERSION, true );

		// The confirmation carries tokens, so the preview has to know what to
		// put in their place rather than showing {name} to somebody typing.
		wp_localize_script( 'scvc-designer', 'scvcDesigner', [
			'sampleName' => __( 'Sarah', 'seedcast-visitor-card' ),
			'serviceDay' => \Seedcast\Core\Church::service_day_label(),
			'church'     => \Seedcast\Core\Church::name(),
		] );
	}

	/**
	 * Save the design.
	 *
	 * @return void
	 */
	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot do that.', 'seedcast-visitor-card' ) );
		}
		check_admin_referer( 'scvc_save_design' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified immediately above.
		$post = wp_unslash( $_POST );

		$template = isset( $post['scvc_template'] ) ? sanitize_key( $post['scvc_template'] ) : '';
		if ( array_key_exists( $template, Card::templates() ) ) {
			update_option( 'scvc_template', $template );
		}

		/*
		 * Following the theme is stored as no color at all rather than as a
		 * copy of the theme's current color. A copy would stop tracking the
		 * moment somebody changed the theme, and would look like it had broken
		 * rather than like it had been set.
		 */
		$follow = isset( $post['scvc_color_mode'] ) && 'theme' === sanitize_key( $post['scvc_color_mode'] );

		if ( $follow ) {
			update_option( 'scvc_accent', '' );
			update_option( 'scvc_accent_dark', '' );
		} else {
			$accent = isset( $post['scvc_accent'] ) ? sanitize_text_field( $post['scvc_accent'] ) : '';
			if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $accent ) ) {
				update_option( 'scvc_accent', $accent );
			}

			$dark = isset( $post['scvc_accent_dark'] ) ? sanitize_text_field( $post['scvc_accent_dark'] ) : '';
			if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $dark ) ) {
				update_option( 'scvc_accent_dark', $dark );
			}
		}

		update_option( 'scvc_disable_css', isset( $post['scvc_disable_css'] ) && '1' === $post['scvc_disable_css'] ? '1' : '0' );

		foreach ( array_keys( Settings::fields() ) as $field ) {
			// A field the card always asks for is never written as off, even
			// if something posts it that way.
			if ( Settings::is_required_field( $field ) ) {
				update_option( 'scvc_field_' . $field, '1' );
				continue;
			}

			$key = 'scvc_field_' . $field;
			update_option( $key, isset( $post[ $key ] ) && '1' === $post[ $key ] ? '1' : '0' );
		}

		$details_mode = isset( $post['scvc_show_details'] ) ? sanitize_key( $post['scvc_show_details'] ) : 'after';
		if ( array_key_exists( $details_mode, Settings::details_modes() ) ) {
			update_option( 'scvc_show_details', $details_mode );
		}

		foreach ( [ 'scvc_heading', 'scvc_intro', 'scvc_button', 'scvc_confirmation', 'scvc_details_heading' ] as $key ) {
			if ( isset( $post[ $key ] ) ) {
				update_option( $key, sanitize_text_field( $post[ $key ] ) );
			}
		}

		if ( isset( $post['scvc_consent'] ) ) {
			update_option( 'scvc_consent', sanitize_textarea_field( $post['scvc_consent'] ) );
		}

		foreach ( [ 'scvc_status_legend', 'scvc_interests_legend' ] as $key ) {
			if ( isset( $post[ $key ] ) ) {
				update_option( $key, sanitize_text_field( $post[ $key ] ) );
			}
		}

		foreach ( [ 'status', 'interests' ] as $group ) {
			$this->save_choices( $group, $post );
		}

		wp_safe_redirect(
			add_query_arg( 'scvc_saved', '1', admin_url( 'admin.php?page=seedcast-visitor-card-designer' ) )
		);
		exit;
	}

	/**
	 * Rebuild one choice list from the posted rows.
	 *
	 * The order on screen is the order on the card, so the list is written out
	 * exactly as it was arranged rather than sorted or merged. A row whose
	 * words have been cleared is gone; a row whose switch is off is kept but
	 * recorded as off.
	 *
	 * @param string $group status or interests.
	 * @param array  $post  Posted values, already unslashed.
	 * @return void
	 */
	private function save_choices( string $group, array $post ): void {
		$labels_key = 'scvc_' . $group . '_labels';
		$on_key     = 'scvc_' . $group . '_on';

		if ( ! isset( $post[ $labels_key ] ) || ! is_array( $post[ $labels_key ] ) ) {
			return;
		}

		$on     = isset( $post[ $on_key ] ) && is_array( $post[ $on_key ] ) ? $post[ $on_key ] : [];
		$labels = [];
		$off    = [];

		foreach ( $post[ $labels_key ] as $index => $label ) {
			$label = sanitize_text_field( (string) $label );
			if ( '' === trim( $label ) ) {
				continue;
			}

			$labels[] = $label;

			if ( ! isset( $on[ $index ] ) ) {
				$key = sanitize_key( str_replace( ' ', '-', strtolower( $label ) ) );
				if ( '' !== $key ) {
					$off[] = $key;
				}
			}
		}

		update_option( 'scvc_' . $group . '_choices', implode( "\n", $labels ) );
		update_option( 'scvc_' . $group . '_off', implode( ',', array_unique( $off ) ) );
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap scvc-sc-wrap">
			<h1><?php esc_html_e( 'Card Designer', 'seedcast-visitor-card' ); ?></h1>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a display-only flag set by our own redirect.
			if ( isset( $_GET['scvc_saved'] ) ) :
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Saved. Every card on the site now looks like this.', 'seedcast-visitor-card' ); ?></p>
				</div>
			<?php endif; ?>

			<p class="scvc-sc-intro">
				<?php esc_html_e( 'What the card asks and how it looks, everywhere it appears. The preview is the real card, so what you see here is what a visitor gets.', 'seedcast-visitor-card' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'scvc_save_design' ); ?>
				<input type="hidden" name="action" value="scvc_save_design" />

				<div class="scvc-sc-layout">
					<div class="scvc-sc-builder">

						<div class="scvc-sc-card">
							<h2 class="scvc-sc-card__title">
								<span class="scvc-sc-step">1</span>
								<?php esc_html_e( 'Template and color', 'seedcast-visitor-card' ); ?>
							</h2>
							<?php $this->templates(); ?>

							<?php
							$follows = Settings::follows_theme();
							$theme   = Settings::theme_colors();
							?>
							<table class="scvc-sc-table scvc-sc-color-row">
								<tr>
									<th><?php esc_html_e( 'Colors', 'seedcast-visitor-card' ); ?></th>
									<td>
										<p>
											<label>
												<input type="radio" name="scvc_color_mode" value="theme"
													class="scvc-d-color-mode" <?php checked( $follows ); ?> />
												<?php esc_html_e( 'Match the rest of the site', 'seedcast-visitor-card' ); ?>
											</label>
										</p>
										<p class="description">
											<?php
											printf(
												/* translators: %s: link to the shared appearance settings. */
												esc_html__( 'Uses the theme set under %s, so the card changes with everything else rather than needing to be kept in step.', 'seedcast-visitor-card' ),
												'<a href="' . esc_url( admin_url( \Seedcast\Core\Admin\Settings::menu_link( 'sc_appearance' ) ) ) . '">'
													. esc_html__( 'Seedcast, Appearance', 'seedcast-visitor-card' ) . '</a>'
											);
											?>
										</p>
										<p>
											<label>
												<input type="radio" name="scvc_color_mode" value="own"
													class="scvc-d-color-mode" <?php checked( ! $follows ); ?> />
												<?php esc_html_e( 'Use my own colors', 'seedcast-visitor-card' ); ?>
											</label>
										</p>
									</td>
								</tr>
								<tr class="scvc-d-own-colors<?php echo $follows ? ' is-inactive' : ''; ?>">
									<th><label for="scvc-d-color"><?php esc_html_e( 'Accent color', 'seedcast-visitor-card' ); ?></label></th>
									<td>
										<?php
										/*
										 * A color input cannot show "nothing",
										 * so when the card is following the
										 * theme it opens on the theme's own
										 * color. That way the first thing
										 * anyone sees is what the card already
										 * looks like.
										 */
										$accent_value = $follows ? $theme['accent'] : Settings::get( 'scvc_accent' );
										?>
										<input type="color" id="scvc-d-color" name="scvc_accent"
											value="<?php echo esc_attr( $accent_value ); ?>" />
										<p class="description">
											<?php esc_html_e( 'The button, the band across the top and the focus outline. Text is set to whichever of black or white reads against it.', 'seedcast-visitor-card' ); ?>
										</p>
									</td>
								</tr>
								<tr class="scvc-d-own-colors scvc-d-dark-row<?php echo $follows ? ' is-inactive' : ''; ?>">
									<th><label for="scvc-d-color-dark"><?php esc_html_e( 'Header color', 'seedcast-visitor-card' ); ?></label></th>
									<td>
										<?php $dark_value = $follows ? $theme['dark'] : Settings::get( 'scvc_accent_dark' ); ?>
										<input type="color" id="scvc-d-color-dark" name="scvc_accent_dark"
											value="<?php echo esc_attr( $dark_value ); ?>" />
										<p class="description">
											<?php esc_html_e( 'The band across the top of the bold card, which is the only template that uses it.', 'seedcast-visitor-card' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Styles', 'seedcast-visitor-card' ); ?></th>
									<td>
										<input type="hidden" name="scvc_disable_css" value="0" />
										<label class="scvc-switch">
											<input type="checkbox" class="scvc-switch__input" name="scvc_disable_css" value="1"
												<?php checked( ! Settings::styles_enabled() ); ?> />
											<span class="scvc-switch__track" aria-hidden="true"></span>
											<span class="scvc-switch__label"><?php esc_html_e( 'Do not load this plugin\'s stylesheet', 'seedcast-visitor-card' ); ?></span>
										</label>
										<p class="description">
											<?php esc_html_e( 'For themes that style forms themselves. With this on, the template and color above have no effect.', 'seedcast-visitor-card' ); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>

						<div class="scvc-sc-card">
							<h2 class="scvc-sc-card__title">
								<span class="scvc-sc-step">2</span>
								<?php esc_html_e( 'What the card asks', 'seedcast-visitor-card' ); ?>
							</h2>
							<p class="description">
								<?php esc_html_e( 'A name is always required, and then either an email address or a phone number, so you have some way to say hello back.', 'seedcast-visitor-card' ); ?>
							</p>

							<ul class="scvc-d-fields">
								<?php foreach ( Settings::fields() as $key => $field ) : ?>
									<?php $locked = Settings::is_required_field( $key ); ?>
									<li>
										<?php
										/*
										 * A locked field is shown switched on
										 * and disabled rather than left out, so
										 * it is clear the card asks for it. A
										 * disabled input posts nothing, so the
										 * value is carried by the hidden field
										 * beside it.
										 */
										?>
										<input type="hidden" name="scvc_field_<?php echo esc_attr( $key ); ?>"
											value="<?php echo $locked ? '1' : '0'; ?>" />
										<label class="scvc-switch">
											<input type="checkbox" class="scvc-switch__input scvc-d-field"
												<?php echo $locked ? 'disabled' : ''; ?>
												name="scvc_field_<?php echo esc_attr( $key ); ?>" value="1"
												data-field="<?php echo esc_attr( $key ); ?>"
												<?php checked( Settings::uses( $key ) ); ?> />
											<span class="scvc-switch__track" aria-hidden="true"></span>
											<span class="scvc-switch__label"><?php echo esc_html( $field['label'] ); ?></span>
										</label>
										<?php if ( $locked ) : ?>
											<span class="description">
												<?php esc_html_e( 'Always asked', 'seedcast-visitor-card' ); ?>
											</span>
										<?php endif; ?>
										<?php if ( 'status' === $key || 'interests' === $key ) : ?>
											<span class="description">
												<?php esc_html_e( 'Wording below', 'seedcast-visitor-card' ); ?>
											</span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>

							<p class="description">
								<?php esc_html_e( 'The last two are off to start with. A printed connection card can ask everything because somebody is already sitting in the service; a card on your website is being filled in by somebody deciding whether to come, and every extra question loses a few of them.', 'seedcast-visitor-card' ); ?>
							</p>
							<p class="description">
								<?php esc_html_e( 'Prayer requests are not here on purpose. They belong with Praise Report, which handles them properly rather than as a line on a form.', 'seedcast-visitor-card' ); ?>
							</p>

							<?php
							$this->choice_editor(
								'status',
								__( 'Where they are with you', 'seedcast-visitor-card' ),
								Settings::status_legend(),
								Settings::all_statuses(),
								'scvc_status_off'
							);

							$this->choice_editor(
								'interests',
								__( 'What they are interested in', 'seedcast-visitor-card' ),
								Settings::interests_legend(),
								Settings::all_interests(),
								'scvc_interests_off'
							);
							?>
						</div>

						<div class="scvc-sc-card">
							<h2 class="scvc-sc-card__title">
								<span class="scvc-sc-step">3</span>
								<?php esc_html_e( 'What it says', 'seedcast-visitor-card' ); ?>
							</h2>

							<table class="scvc-sc-table">
								<tr>
									<th><label for="scvc_heading"><?php esc_html_e( 'Heading', 'seedcast-visitor-card' ); ?></label></th>
									<td>
										<input type="text" class="large-text scvc-d-text" id="scvc_heading" name="scvc_heading"
											data-preview=".scvc-card__heading"
											value="<?php echo esc_attr( Settings::heading() ); ?>" />
									</td>
								</tr>
								<tr>
									<th><label for="scvc_intro"><?php esc_html_e( 'Opening line', 'seedcast-visitor-card' ); ?></label></th>
									<td>
										<input type="text" class="large-text scvc-d-text" id="scvc_intro" name="scvc_intro"
											data-preview=".scvc-card__intro"
											value="<?php echo esc_attr( Settings::intro() ); ?>" />
									</td>
								</tr>
								<tr>
									<th><label for="scvc_button"><?php esc_html_e( 'Button', 'seedcast-visitor-card' ); ?></label></th>
									<td>
										<input type="text" class="large-text scvc-d-text" id="scvc_button" name="scvc_button"
											data-preview=".sc-btn"
											value="<?php echo esc_attr( Settings::button() ); ?>" />
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'When and where', 'seedcast-visitor-card' ); ?></th>
									<td>
										<?php
										/*
										 * Three states rather than a switch. On
										 * the form the details push the fields
										 * down and usually repeat what is
										 * already on the page; after somebody
										 * has committed they are the most
										 * useful thing on the card.
										 */
										$details_mode = Settings::details_mode();
										?>
										<?php foreach ( Settings::details_modes() as $mode => $label ) : ?>
											<p>
												<label>
													<input type="radio" class="scvc-d-details-mode"
														name="scvc_show_details" value="<?php echo esc_attr( $mode ); ?>"
														<?php checked( $mode, $details_mode ); ?> />
													<?php echo esc_html( $label ); ?>
												</label>
											</p>
										<?php endforeach; ?>
										<p>
											<input type="text" class="large-text" name="scvc_details_heading"
												value="<?php echo esc_attr( Settings::details_heading() ); ?>" />
										</p>
										<p class="description">
											<?php
											printf(
												/* translators: %s: link to the shared church settings. */
												esc_html__( 'Taken from your %s, so a change to your service time follows here without editing anything.', 'seedcast-visitor-card' ),
												'<a href="' . esc_url( admin_url( \Seedcast\Core\Admin\Settings::menu_link( 'sc_church' ) ) ) . '">'
													. esc_html__( 'church details', 'seedcast-visitor-card' ) . '</a>'
											);
											?>
										</p>
									</td>
								</tr>
								<tr>
									<th><label for="scvc_consent"><?php esc_html_e( 'Consent line', 'seedcast-visitor-card' ); ?></label></th>
									<td>
										<textarea class="large-text scvc-d-text" rows="2" id="scvc_consent" name="scvc_consent"
											data-preview=".scvc-card__consent"><?php echo esc_textarea( Settings::consent() ); ?></textarea>
										<p class="description">
											<?php esc_html_e( 'If you have set a privacy policy page, a link to it is added after this automatically.', 'seedcast-visitor-card' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th><label for="scvc_confirmation"><?php esc_html_e( 'After they send it', 'seedcast-visitor-card' ); ?></label></th>
									<td>
										<input type="text" class="large-text scvc-d-confirm-text" id="scvc_confirmation" name="scvc_confirmation"
											value="<?php echo esc_attr( Settings::confirmation() ); ?>" />
										<p class="description">
											<?php
											printf(
												/* translators: 1: the {name} token, 2: the {day} token, 3: the resolved service day. */
												esc_html__( '%1$s becomes their name and %2$s becomes your service day, currently %3$s.', 'seedcast-visitor-card' ),
												'<code>{name}</code>',
												'<code>{day}</code>',
												'<strong>' . esc_html( \Seedcast\Core\Church::service_day_label() ) . '</strong>'
											);
											?>
										</p>
										<p class="description">
											<?php esc_html_e( 'This replaces the card everywhere it appears until that service has passed, then the card comes back for the following week.', 'seedcast-visitor-card' ); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>

						<div class="scvc-sc-card">
							<h2 class="scvc-sc-card__title">
								<span class="scvc-sc-step">4</span>
								<?php esc_html_e( 'What a visitor will see', 'seedcast-visitor-card' ); ?>
							</h2>
							<p class="description">
								<?php esc_html_e( 'Changes here show straight away. Nothing is saved until you press the button below.', 'seedcast-visitor-card' ); ?>
							</p>

							<p class="scvc-d-switch">
								<label>
									<input type="checkbox" id="scvc-d-show-confirmed" />
									<?php esc_html_e( 'Show what they see after sending it instead', 'seedcast-visitor-card' ); ?>
								</label>
							</p>
							<div class="scvc-sc-preview" id="scvc-d-preview">
								<?php
								/*
								 * Rendered with every field present, then the
								 * switched-off ones hidden by the script. A
								 * field that is off would otherwise not be in
								 * the markup, and ticking it here would appear
								 * to do nothing.
								 */
								$scvc_preview = Card::render( [
									'fields'  => array_keys( Settings::fields() ),
									'preview' => true,
								] );

								$scvc_confirmed = Card::render( [
									'preview'   => true,
									// A stand-in name, so the message reads the
									// way it will rather than with a token in it.
									'confirmed' => __( 'Sarah', 'seedcast-visitor-card' ),
								] );

								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Card::render() escapes everything it emits.
								echo $scvc_preview;
								?>
								<div class="scvc-d-confirmed" hidden>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Card::render() escapes everything it emits.
								echo $scvc_confirmed;
								?>
								</div>
							</div>
						</div>

					</div>

					<div class="scvc-sc-output-card">
						<div class="scvc-sc-output-header">
							<h2><?php esc_html_e( 'Save', 'seedcast-visitor-card' ); ?></h2>
						</div>
						<p class="description">
							<?php esc_html_e( 'Applies to every card on the site: the ones added to your sermons and services automatically, and any you have placed yourself.', 'seedcast-visitor-card' ); ?>
						</p>
						<p>
							<button type="submit" class="button button-primary scvc-sc-copy-btn">
								<?php esc_html_e( 'Save the design', 'seedcast-visitor-card' ); ?>
							</button>
						</p>

						<hr class="scvc-sc-divider" />

						<h3><?php esc_html_e( 'Just one placement?', 'seedcast-visitor-card' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'The shortcode generator can override the template, color and wording for a single card without changing the rest.', 'seedcast-visitor-card' ); ?>
						</p>
						<p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=seedcast-visitor-card-shortcodes' ) ); ?>">
								<?php esc_html_e( 'Open the shortcode generator', 'seedcast-visitor-card' ); ?>
							</a>
						</p>

						<hr class="scvc-sc-divider" />

						<h3><?php esc_html_e( 'Emails', 'seedcast-visitor-card' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'What you are sent when somebody fills the card in, and what they are sent back, are in settings.', 'seedcast-visitor-card' ); ?>
						</p>
						<p>
							<a href="<?php echo esc_url( admin_url( SettingsPage::url() ) ); ?>">
								<?php esc_html_e( 'Open settings', 'seedcast-visitor-card' ); ?>
							</a>
						</p>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Editable wording for one of the choice groups.
	 *
	 * A textarea rather than a repeating row of inputs. Churches list different
	 * things, and typing four lines is faster than pressing Add four times.
	 *
	 * @param string $key      status or interests.
	 * @param string $title    Heading for the block.
	 * @param string $legend   Current question.
	 * @param array  $current  Current choices.
	 * @param array  $fallback Default choices.
	 * @return void
	 */
	private function choice_editor( string $key, string $title, string $legend, array $all, string $off_option ): void {
		$off = Settings::switched_off( $off_option );
		?>
		<div class="scvc-d-choices" data-choices="<?php echo esc_attr( $key ); ?>">
			<h3 class="scvc-d-choices__title"><?php echo esc_html( $title ); ?></h3>

			<p>
				<label class="scvc-field-label" for="scvc_<?php echo esc_attr( $key ); ?>_legend">
					<?php esc_html_e( 'Question', 'seedcast-visitor-card' ); ?>
				</label>
				<input type="text" class="large-text"
					id="scvc_<?php echo esc_attr( $key ); ?>_legend"
					name="scvc_<?php echo esc_attr( $key ); ?>_legend"
					value="<?php echo esc_attr( $legend ); ?>" />
			</p>

			<p class="scvc-field-label"><?php esc_html_e( 'Choices', 'seedcast-visitor-card' ); ?></p>

			<ul class="scvc-d-rows">
				<?php
				$i = 0;
				foreach ( $all as $choice_key => $label ) :
					$is_on = ! in_array( (string) $choice_key, $off, true );
					?>
					<li class="scvc-d-row">
						<?php
						/*
						 * Switched off rather than removed. A church that does
						 * not run memberships this year keeps the wording for
						 * the year it does, and anyone who already chose it
						 * still has an answer that reads properly.
						 */
						?>
						<label class="scvc-switch" title="<?php esc_attr_e( 'Show this choice on the card', 'seedcast-visitor-card' ); ?>">
							<input type="checkbox" class="scvc-switch__input scvc-d-choice-on"
								name="scvc_<?php echo esc_attr( $key ); ?>_on[<?php echo esc_attr( (string) $i ); ?>]"
								value="1" <?php checked( $is_on ); ?> />
							<span class="scvc-switch__track" aria-hidden="true"></span>
							<span class="screen-reader-text">
								<?php
								printf(
									/* translators: %s: the choice, for example Baptism. */
									esc_html__( 'Show %s on the card', 'seedcast-visitor-card' ),
									esc_html( (string) $label )
								);
								?>
							</span>
						</label>

						<input type="text" class="scvc-d-choice-label"
							name="scvc_<?php echo esc_attr( $key ); ?>_labels[<?php echo esc_attr( (string) $i ); ?>]"
							value="<?php echo esc_attr( (string) $label ); ?>" />
					</li>
					<?php
					$i++;
				endforeach;
				?>

				<?php
				// Two blank rows, so adding a choice is typing rather than
				// pressing Add first.
				for ( $blank = 0; $blank < 2; $blank++ ) :
					?>
					<li class="scvc-d-row scvc-d-row--new">
						<label class="scvc-switch">
							<input type="checkbox" class="scvc-switch__input scvc-d-choice-on"
								name="scvc_<?php echo esc_attr( $key ); ?>_on[<?php echo esc_attr( (string) $i ); ?>]"
								value="1" checked />
							<span class="scvc-switch__track" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Show this choice on the card', 'seedcast-visitor-card' ); ?></span>
						</label>
						<input type="text" class="scvc-d-choice-label"
							name="scvc_<?php echo esc_attr( $key ); ?>_labels[<?php echo esc_attr( (string) $i ); ?>]"
							value=""
							placeholder="<?php esc_attr_e( 'Add another', 'seedcast-visitor-card' ); ?>" />
					</li>
					<?php
					$i++;
				endfor;
				?>
			</ul>

			<p class="description">
				<?php esc_html_e( 'Switch a choice off to take it off the card without losing the wording. Clear the words to remove it for good. Renaming does not change cards people have already filled in, which keep the words they were given.', 'seedcast-visitor-card' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * The template chooser.
	 *
	 * @return void
	 */
	private function templates(): void {
		$styles = [
			'card'    => [
				'icon' => '🗂',
				'desc' => __( 'A bounded block with a colored edge. The default.', 'seedcast-visitor-card' ),
			],
			'classic' => [
				'icon' => '📋',
				'desc' => __( 'Heading in a band, ruled lines under each field, like a printed card.', 'seedcast-visitor-card' ),
			],
			'bold'    => [
				'icon' => '🎫',
				'desc' => __( 'Heavier band and uppercase labels, closer to a modern connect card.', 'seedcast-visitor-card' ),
			],
			'inline'  => [
				'icon' => '↔',
				'desc' => __( 'No frame, for a theme that already frames its sections.', 'seedcast-visitor-card' ),
			],
			'contact' => [
				'icon' => '✉',
				'desc' => __( 'A plain stacked form, for your contact page.', 'seedcast-visitor-card' ),
			],
		];

		$current = Settings::get( 'scvc_template' );
		?>
		<div class="scvc-sc-options">
			<?php foreach ( Card::templates() as $value => $label ) : ?>
				<label class="scvc-sc-option <?php echo $value === $current ? 'is-selected' : ''; ?>">
					<input type="radio" name="scvc_template" class="scvc-sc-radio scvc-d-template"
						value="<?php echo esc_attr( $value ); ?>" <?php checked( $value, $current ); ?> />
					<span class="scvc-sc-option__icon" aria-hidden="true">
						<?php echo esc_html( (string) ( $styles[ $value ]['icon'] ?? '' ) ); ?>
					</span>
					<span class="scvc-sc-option__body">
						<strong><?php echo esc_html( (string) $label ); ?></strong>
						<span><?php echo esc_html( (string) ( $styles[ $value ]['desc'] ?? '' ) ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
