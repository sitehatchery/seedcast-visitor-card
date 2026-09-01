<?php
/**
 * The settings screen.
 *
 * @package SeedcastVisitorCard\Admin
 */

namespace SeedcastVisitorCard\Admin;

use Seedcast\Core\Church;
use SeedcastVisitorCard\Frontend\Card;
use SeedcastVisitorCard\Frontend\Placement;
use SeedcastVisitorCard\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything a church can change about the card.
 *
 * Deliberately short. Most of what matters, the address and the service times,
 * lives in the shared church settings and is filled in once for the whole
 * suite, so this screen is only about the card itself.
 */
class SettingsPage {

	/**
	 * Settings group.
	 */
	private const GROUP = 'scvc_settings';

	/**
	 * Section id on the shared Seedcast settings page.
	 */
	public const SECTION = 'scvc_general';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_init', [ $this, 'register' ] );
		add_action( 'seedcast_core_register_settings', [ $this, 'register_section' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( SCVC_PLUGIN_FILE ), [ $this, 'action_link' ] );
	}

	/**
	 * Add this plugin's tab to the shared settings page.
	 *
	 * Every Seedcast plugin puts its settings in the same place, under
	 * Settings, Seedcast, as one more tab. A church running three of them
	 * configures all three on one screen rather than hunting through three
	 * separate menus.
	 *
	 * @param object $settings Core settings page.
	 * @return void
	 */
	public function register_section( $settings ): void {
		if ( ! is_object( $settings ) || ! method_exists( $settings, 'add_section' ) ) {
			return;
		}

		$settings->add_section(
			self::SECTION,
			__( 'Visitor Card', 'seedcast-visitor-card' ),
			[ $this, 'render_section' ],
			self::GROUP,
			20
		);
	}

	/**
	 * A settings link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_link( $links ) {
		if ( ! is_array( $links ) ) {
			return $links;
		}

		$links[] = '<a href="' . esc_url( admin_url( self::url() ) ) . '">'
			. esc_html__( 'Settings', 'seedcast-visitor-card' ) . '</a>';

		return $links;
	}

	/**
	 * The settings page URL, pointing at this plugin's tab.
	 *
	 * @return string
	 */
	public static function url(): string {
		if ( class_exists( '\\Seedcast\\Core\\Admin\\Settings' ) ) {
			return \Seedcast\Core\Admin\Settings::menu_link( self::SECTION );
		}
		return 'options-general.php?page=seedcast-settings';
	}

	/**
	 * Register every option.
	 *
	 * @return void
	 */
	public function register(): void {
		$text     = [ 'sanitize_callback' => 'sanitize_text_field' ];
		$textarea = [ 'sanitize_callback' => 'sanitize_textarea_field' ];
		$key      = [ 'sanitize_callback' => 'sanitize_key' ];
		$onoff    = [ 'sanitize_callback' => [ $this, 'sanitize_onoff' ] ];

		/*
		 * scvc_template and scvc_accent are deliberately absent. They are saved
		 * from the card designer, which has its own handler, and registering
		 * them here would be worse than pointless: options.php writes an empty
		 * value for every option in a group that was not posted, so saving this
		 * form would silently wipe a color set on the other screen.
		 */
		/*
		 * Appearance and field options are deliberately absent. They belong to
		 * the card designer, which saves them itself. Registering them here
		 * would be worse than pointless: options.php writes an empty value for
		 * every option in a group that was not posted, so saving this form
		 * would silently wipe a design set on the other screen.
		 */
		$options = [
			'scvc_suspended'         => $onoff,
			'scvc_suspended_until'   => $text,
			'scvc_suspended_message' => $textarea,
			'scvc_notify'            => $onoff,
			'scvc_notify_to'         => [ 'sanitize_callback' => 'sanitize_email' ],
			'scvc_followup'          => $onoff,
			'scvc_followup_subject'  => $text,
			'scvc_followup_body'     => $textarea,
		];

		foreach ( array_keys( Placement::candidates() ) as $type ) {
			$options[ 'scvc_embed_' . $type ] = $onoff;
		}

		foreach ( $options as $name => $args ) {
			register_setting( self::GROUP, $name, $args );
		}
	}

	/**
	 * Force a checkbox to a real one or zero.
	 *
	 * WordPress stores an empty string for a checkbox that was not posted, and
	 * an empty string is not '0'. A default-on setting tested with a not-equals
	 * would therefore read as on again the moment it was switched off. Every
	 * checkbox on this screen has a hidden zero beside it, and this makes the
	 * stored value one of exactly two things regardless.
	 *
	 * @param mixed $value Posted value.
	 * @return string
	 */
	public function sanitize_onoff( $value ): string {
		return '1' === (string) $value ? '1' : '0';
	}

	/**
	 * Keep a color to a hex value.
	 *
	 * @param mixed $value Posted value.
	 * @return string
	 */
	public function sanitize_color( $value ): string {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ? $value : '#2c6e49';
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public function render_section(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="scvc-settings">
			<?php $this->church_notice(); ?>

			<p class="description">
				<?php
				printf(
					/* translators: %s: link to the card designer. */
					esc_html__( 'What the card asks, what it says and how it looks are all set in the %s, where you can see the card as you change it. This page is what happens around it.', 'seedcast-visitor-card' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=seedcast-visitor-card-designer' ) ) . '">'
						. esc_html__( 'card designer', 'seedcast-visitor-card' ) . '</a>'
				);
				?>
			</p>

				<h2><?php esc_html_e( 'Where it appears', 'seedcast-visitor-card' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Add it to', 'seedcast-visitor-card' ); ?></th>
						<td>
							<?php $types = Placement::candidates(); ?>
							<?php if ( $types ) : ?>
								<ul class="scvc-types">
									<?php foreach ( $types as $type => $label ) : ?>
										<li><?php $this->checkbox( 'scvc_embed_' . $type, $label ); ?></li>
									<?php endforeach; ?>
								</ul>
								<p class="description">
									<?php esc_html_e( 'At the end of the page, on single items only. Never on lists, and never on your ordinary posts and pages.', 'seedcast-visitor-card' ); ?>
								</p>
							<?php else : ?>
								<p class="description">
									<?php esc_html_e( 'No other Seedcast plugins are active, so there is nothing to add it to yet. Use the shortcode below in the meantime.', 'seedcast-visitor-card' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'By hand', 'seedcast-visitor-card' ); ?></th>
						<td>
							<p><code>[<?php echo esc_html( Card::SHORTCODE ); ?>]</code></p>
							<p class="description">
								<?php esc_html_e( 'Anywhere you like. There is a matching Visitor Card widget if you build with Elementor.', 'seedcast-visitor-card' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Emails', 'seedcast-visitor-card' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Tell you', 'seedcast-visitor-card' ); ?></th>
						<td>
							<?php $this->checkbox( 'scvc_notify', __( 'Email me when somebody fills in the card', 'seedcast-visitor-card' ) ); ?>
							<p>
								<input type="email" class="regular-text" name="scvc_notify_to"
									value="<?php echo esc_attr( (string) get_option( 'scvc_notify_to', '' ) ); ?>"
									placeholder="<?php echo esc_attr( Settings::notify_to() ); ?>" />
							</p>
							<p class="description">
								<?php esc_html_e( 'Replying to that email replies to the visitor, not to the website.', 'seedcast-visitor-card' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tell them', 'seedcast-visitor-card' ); ?></th>
						<td>
							<?php $this->checkbox( 'scvc_followup', __( 'Send the visitor your details', 'seedcast-visitor-card' ) ); ?>
							<p class="description">
								<?php esc_html_e( 'One email, sent because they asked for it. Not a list, and nothing follows it. Somebody who decides on a Thursday will not remember your address by the weekend, and this is what they will look at on the day.', 'seedcast-visitor-card' ); ?>
							</p>
							<?php
							/*
							 * Filled in rather than left blank with a
							 * placeholder. An empty box reads as optional, and
							 * most people faced with one write nothing, which
							 * means the most useful email this plugin sends
							 * never goes out. Real text can be edited.
							 */
							?>
							<p>
								<label class="scvc-field-label" for="scvc_followup_subject"><?php esc_html_e( 'Subject', 'seedcast-visitor-card' ); ?></label>
								<input type="text" class="large-text" id="scvc_followup_subject" name="scvc_followup_subject"
									value="<?php echo esc_attr( Settings::followup_subject() ); ?>" />
							</p>
							<p>
								<label class="scvc-field-label" for="scvc_followup_body"><?php esc_html_e( 'Message', 'seedcast-visitor-card' ); ?></label>
								<textarea class="large-text" rows="14" id="scvc_followup_body" name="scvc_followup_body"><?php echo esc_textarea( Settings::followup_body() ); ?></textarea>
							</p>
							<p class="description">
								<?php
								printf(
									/* translators: %s: list of available tokens. */
									esc_html__( 'You can use: %s', 'seedcast-visitor-card' ),
									'<code>{name}</code> <code>{day}</code> <code>{church}</code> <code>{address}</code> <code>{city}</code> <code>{state}</code> <code>{locality}</code> <code>{times}</code> <code>{phone}</code> <code>{directions}</code> <code>{events}</code> <code>{note}</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'No service this week', 'seedcast-visitor-card' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Pause the card', 'seedcast-visitor-card' ); ?></th>
						<td>
							<?php $this->checkbox( 'scvc_suspended', __( 'We are not gathering', 'seedcast-visitor-card' ) ); ?>
							<p class="description">
								<?php esc_html_e( 'For a holiday, or a week you have had to cancel. The card is replaced with your message everywhere it appears, so nobody is invited to a service that is not happening.', 'seedcast-visitor-card' ); ?>
							</p>
							<p>
								<label for="scvc_suspended_until"><?php esc_html_e( 'Until', 'seedcast-visitor-card' ); ?></label>
								<input type="date" id="scvc_suspended_until" name="scvc_suspended_until"
									value="<?php echo esc_attr( (string) get_option( 'scvc_suspended_until', '' ) ); ?>" />
							</p>
							<p class="description">
								<?php esc_html_e( 'Set a date and the card comes back by itself the day after. Leave it blank and the pause stays until you switch it off, which is easy to forget.', 'seedcast-visitor-card' ); ?>
							</p>
							<p>
								<textarea class="large-text" rows="2" name="scvc_suspended_message"
									placeholder="<?php echo esc_attr( Settings::suspended_message() ); ?>"><?php echo esc_textarea( (string) get_option( 'scvc_suspended_message', '' ) ); ?></textarea>
							</p>
						</td>
					</tr>
				</table>

		</div>
		<?php
	}

	/**
	 * A nudge to fill in the shared church details, since the visitor's email
	 * is close to useless without them.
	 *
	 * @return void
	 */
	private function church_notice(): void {
		if ( Church::has_details() ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Your church details are not filled in yet.', 'seedcast-visitor-card' ); ?></strong>
				<?php esc_html_e( 'The email a visitor receives is mostly your address, your service times and a directions link, so it will be thin until they are set.', 'seedcast-visitor-card' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( \Seedcast\Core\Admin\Settings::menu_link( 'sc_church' ) ) ); ?>">
					<?php esc_html_e( 'Fill them in', 'seedcast-visitor-card' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * A checkbox with an explicit off value.
	 *
	 * @param string $name  Option name.
	 * @param string $label Visible label.
	 * @return void
	 */
	private function checkbox( string $name, string $label ): void {
		$default = Settings::defaults()[ $name ] ?? '0';
		$value    = (string) get_option( $name, $default );
		?>
		<label>
			<?php // Posted when the box is unticked, so switching something off actually stores an off. ?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( '1', $value ); ?> />
			<?php echo esc_html( $label ); ?>
		</label>
		<?php
	}

	/**
	 * A text row with the resolved default as its placeholder.
	 *
	 * @param string $name        Option name.
	 * @param string $label       Visible label.
	 * @param string $placeholder Resolved default.
	 * @return void
	 */
	private function text_row( string $name, string $label, string $placeholder ): void {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="text" class="large-text" id="<?php echo esc_attr( $name ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( (string) get_option( $name, '' ) ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>" />
			</td>
		</tr>
		<?php
	}
}
