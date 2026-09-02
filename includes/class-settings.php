<?php
/**
 * Settings: what the card asks, what it says, and where it appears.
 *
 * @package SeedcastVisitorCard
 */

namespace SeedcastVisitorCard;

use Seedcast\Core\Church;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One place that knows every option and its default.
 *
 * The settings screen, the installer and the front end all read from here, so
 * a default cannot drift between the form that sets it and the code that uses
 * it. Getting that wrong is quiet: the option reads one way on a fresh install
 * and another after the first save, and nothing reports it.
 */
final class Settings {

	/**
	 * Which fields the card asks for.
	 *
	 * All on by default. A church that wants a shorter card can turn any of
	 * them off, but the useful default is to ask and let people skip what they
	 * would rather not answer.
	 *
	 * @return array<string, array{label:string,default:string}>
	 */
	public static function fields(): array {
		return [
			/*
			 * Always asked and always required. It was switchable, which meant
			 * the form could be set to hide a field it then marked with an
			 * asterisk and refused to submit without. A card with no name on
			 * it also tells a church almost nothing: they cannot look out for
			 * somebody whose name they do not have.
			 */
			'name'      => [
				'label'    => __( 'Name', 'seedcast-visitor-card' ),
				'default'  => '1',
				'required' => true,
			],
			'email'     => [
				'label'   => __( 'Email', 'seedcast-visitor-card' ),
				'default' => '1',
			],
			'phone'     => [
				'label'   => __( 'Phone', 'seedcast-visitor-card' ),
				'default' => '1',
			],
			'party'     => [
				'label'   => __( 'How many are coming', 'seedcast-visitor-card' ),
				'default' => '1',
			],
			'heard'     => [
				'label'   => __( 'How they heard about you', 'seedcast-visitor-card' ),
				'default' => '1',
			],
			'message'   => [
				'label'   => __( 'Anything they want you to know', 'seedcast-visitor-card' ),
				'default' => '1',
			],
			'status'    => [
				'label'   => __( 'Where they are with you', 'seedcast-visitor-card' ),
				'default' => '0',
			],
			'interests' => [
				'label'   => __( 'What they are interested in', 'seedcast-visitor-card' ),
				'default' => '0',
			],
		];
	}

	/**
	 * The choices offered for where somebody is with the church.
	 *
	 * Off by default, and worth saying why. The card's job is to let a
	 * stranger say they are coming, and every question added between that
	 * decision and the button costs some of them. A church that wants a fuller
	 * connection card can switch these on; one that just wants to know who is
	 * coming should not have to switch them off.
	 *
	 * @return array<string, string>
	 */
	public static function statuses(): array {
		return self::enabled_only( self::all_statuses(), 'scvc_status_off' );
	}

	/**
	 * Every status choice, including the ones switched off.
	 *
	 * @return array<string, string>
	 */
	public static function all_statuses(): array {
		return self::choices(
			'scvc_status_choices',
			self::default_statuses(),
			'seedcast/visitor_card/statuses'
		);
	}

	/**
	 * The wording used until a church writes its own.
	 *
	 * @return array<int, string>
	 */
	public static function default_statuses(): array {
		return [
			__( 'First visit', 'seedcast-visitor-card' ),
			__( 'Second visit', 'seedcast-visitor-card' ),
			__( 'Regular attendee', 'seedcast-visitor-card' ),
			__( 'Member', 'seedcast-visitor-card' ),
		];
	}

	/**
	 * The question above the status choices.
	 *
	 * @return string
	 */
	public static function status_legend(): string {
		$value = trim( self::get( 'scvc_status_legend' ) );
		return '' !== $value ? $value : __( 'Where are you with us?', 'seedcast-visitor-card' );
	}

	/**
	 * The question above the interest choices.
	 *
	 * @return string
	 */
	public static function interests_legend(): string {
		$value = trim( self::get( 'scvc_interests_legend' ) );
		return '' !== $value ? $value : __( 'I would like to know more about', 'seedcast-visitor-card' );
	}

	/**
	 * The words for a stored choice, even after the list has changed.
	 *
	 * A church that renames "Serving" to "Volunteering" leaves every card
	 * already filled in pointing at a key nobody offers any more. Dropping
	 * those silently would quietly rewrite what people told you, so the stored
	 * key is turned back into readable words instead.
	 *
	 * @param array  $choices Current choices.
	 * @param string $key     Stored key.
	 * @return string
	 */
	public static function choice_label( array $choices, string $key ): string {
		if ( '' === $key ) {
			return '';
		}
		if ( isset( $choices[ $key ] ) ) {
			return (string) $choices[ $key ];
		}

		// Somebody chose this before it was switched off or renamed. The words
		// are still known in the full list, so use them rather than falling
		// back to a tidied-up key.
		foreach ( [ self::all_statuses(), self::all_interests() ] as $all ) {
			if ( isset( $all[ $key ] ) ) {
				return (string) $all[ $key ];
			}
		}

		return ucfirst( str_replace( '-', ' ', $key ) );
	}

	/**
	 * Turn a stored list of lines into keyed choices.
	 *
	 * Keys are derived from the words, so a church that renames a choice gets
	 * a new key rather than quietly changing the meaning of an old one. Cards
	 * already filled in keep whatever they were given, and anything showing
	 * those values falls back to the stored key when its label has gone.
	 *
	 * @param string $option   Option holding one choice per line.
	 * @param array  $fallback Default choices.
	 * @param string $filter   Filter name.
	 * @return array<string, string>
	 */
	private static function choices( string $option, array $fallback, string $filter ): array {
		$raw = trim( self::get( $option ) );

		if ( '' === $raw ) {
			$lines = $fallback;
		} else {
			$lines = preg_split( '/\r\n|\r|\n/', $raw );
			$lines = is_array( $lines ) ? $lines : $fallback;
		}

		$out = [];
		foreach ( $lines as $line ) {
			$label = trim( (string) $line );
			if ( '' === $label ) {
				continue;
			}

			$key = sanitize_key( str_replace( ' ', '-', strtolower( $label ) ) );
			if ( '' === $key ) {
				continue;
			}

			// Two labels that reduce to the same key would otherwise silently
			// become one choice.
			$unique = $key;
			$n      = 2;
			while ( isset( $out[ $unique ] ) ) {
				$unique = $key . '-' . $n;
				$n++;
			}

			$out[ $unique ] = $label;
		}

		return (array) apply_filters( $filter, $out );
	}

	/**
	 * The choices offered for what somebody wants to know more about.
	 *
	 * @return array<string, string>
	 */
	public static function interests(): array {
		return self::enabled_only( self::all_interests(), 'scvc_interests_off' );
	}

	/**
	 * Every interest choice, including the ones switched off.
	 *
	 * @return array<string, string>
	 */
	public static function all_interests(): array {
		return self::choices(
			'scvc_interests_choices',
			self::default_interests(),
			'seedcast/visitor_card/interests'
		);
	}

	/**
	 * The keys a church has switched off.
	 *
	 * @param string $option Option holding the switched-off keys.
	 * @return array<int, string>
	 */
	public static function switched_off( string $option ): array {
		$raw = trim( self::get( $option ) );
		if ( '' === $raw ) {
			return [];
		}
		return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ), 'strlen' ) );
	}

	/**
	 * Drop the choices that are switched off.
	 *
	 * Switched off rather than deleted, so a church that does not run
	 * memberships this year still has the wording if it starts. Deleting the
	 * line would work too, but then it has to be remembered and retyped, and
	 * anyone who already chose it keeps pointing at a key nothing explains.
	 *
	 * @param array  $choices All choices.
	 * @param string $option  Option holding the switched-off keys.
	 * @return array<string, string>
	 */
	private static function enabled_only( array $choices, string $option ): array {
		$off = self::switched_off( $option );
		if ( ! $off ) {
			return $choices;
		}
		return array_diff_key( $choices, array_flip( $off ) );
	}

	/**
	 * The wording used until a church writes its own.
	 *
	 * @return array<int, string>
	 */
	public static function default_interests(): array {
		return [
			__( 'Membership', 'seedcast-visitor-card' ),
			__( 'Baptism', 'seedcast-visitor-card' ),
			__( 'Small groups', 'seedcast-visitor-card' ),
			__( 'Serving', 'seedcast-visitor-card' ),
			__( 'Volunteering', 'seedcast-visitor-card' ),
		];
	}

	/**
	 * Every option and its default.
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		$defaults = [
			// Appearance.
			'scvc_template'          => 'card',
			/*
			 * When and where.
			 *
			 * After they send it, by default. On the form it pushes the fields
			 * down the page and repeats what a church has usually already put
			 * on the page the card is sitting on. Once somebody has committed
			 * it is the most useful thing on the card, because that is the
			 * moment they need the address.
			 */
			'scvc_show_details'      => 'after',
			'scvc_details_heading'   => '',
			/*
			 * Empty on purpose. Empty means the card takes its colors from the
			 * suite theme under Settings, Seedcast, Appearance, which is where
			 * a church has already chosen how everything else looks. A color
			 * here is an override for a church that wants this one thing
			 * different, not the starting point.
			 */
			'scvc_accent'            => '',
			'scvc_accent_dark'       => '',
			'scvc_status_legend'     => '',
			'scvc_status_choices'    => '',
			'scvc_status_off'        => '',
			'scvc_interests_legend'  => '',
			'scvc_interests_choices' => '',
			'scvc_interests_off'     => '',
			'scvc_disable_css'       => '0',

			// Words. Left empty here and resolved at render time, so a church
			// that never touches settings still gets sensible copy, and one
			// that does gets exactly what it typed.
			'scvc_heading'           => '',
			'scvc_intro'             => '',
			'scvc_button'            => '',
			'scvc_confirmation'      => '',
			'scvc_consent'           => '',

			// Behavior.
			'scvc_suspended'         => '0',
			'scvc_suspended_until'   => '',
			'scvc_suspended_message' => '',
			'scvc_notify'            => '1',
			'scvc_notify_to'         => '',
			'scvc_followup'          => '1',
			'scvc_followup_subject'  => '',
			'scvc_followup_body'     => '',
			'scvc_thanks_page'       => '0',
		];

		foreach ( array_keys( self::fields() ) as $key ) {
			$defaults[ 'scvc_field_' . $key ] = self::fields()[ $key ]['default'];
		}

		return $defaults;
	}

	/**
	 * Read an option, falling back to its declared default.
	 *
	 * @param string $key Option name.
	 * @return string
	 */
	public static function get( string $key ): string {
		$defaults = self::defaults();
		return (string) get_option( $key, $defaults[ $key ] ?? '' );
	}

	/**
	 * Whether a card field is switched on.
	 *
	 * @param string $field Field key.
	 * @return bool
	 */
	public static function uses( string $field ): bool {
		if ( self::is_required_field( $field ) ) {
			return true;
		}
		return '1' === self::get( 'scvc_field_' . $field );
	}

	/**
	 * Whether a field is part of the card whatever the settings say.
	 *
	 * @param string $field Field key.
	 * @return bool
	 */
	public static function is_required_field( string $field ): bool {
		$fields = self::fields();
		return ! empty( $fields[ $field ]['required'] );
	}

	/**
	 * The text color to use on top of a given background.
	 *
	 * Worked out from the background rather than asked for. A color that reads
	 * well on a dark navy is invisible on a tan, and nobody picking an accent
	 * is thinking about what will sit on it.
	 *
	 * Deliberately not a setting. Offering a text color is offering red on
	 * tan, and there is no version of that worth having: it is a way to make
	 * the card unreadable, which is the exact problem this is here to avoid.
	 *
	 * @param string $background Hex background color.
	 * @return string Hex text color.
	 */
	public static function text_on( string $background ): string {
		return self::is_light( $background ) ? '#1d2327' : '#ffffff';
	}

	/**
	 * Whether the card is following the suite theme rather than its own colors.
	 *
	 * @return bool
	 */
	public static function follows_theme(): bool {
		return '' === trim( self::get( 'scvc_accent' ) )
			&& '' === trim( self::get( 'scvc_accent_dark' ) );
	}

	/**
	 * The colors the suite theme is currently using, for the color pickers.
	 *
	 * A picker cannot show "nothing", so it has to open on something. Opening
	 * on the theme's own accent means the first thing somebody sees is what
	 * the card already looks like, rather than a color it is not using.
	 *
	 * @return array{accent:string,dark:string}
	 */
	public static function theme_colors(): array {
		/*
		 * Read off the shared stylesheet rather than guessed. Two of these do
		 * not set a primary of their own and inherit the root value, and the
		 * dark theme's primary is a light color, which is easy to assume the
		 * wrong way round.
		 */
		$root_primary = '#1a1a2e';

		$themes = [
			'light'   => [ 'accent' => '#c9975a', 'dark' => $root_primary ],
			'dark'    => [ 'accent' => '#e0b872', 'dark' => '#e2e8f0' ],
			'warm'    => [ 'accent' => '#d3b673', 'dark' => '#0c0c0e' ],
			'minimal' => [ 'accent' => '#111827', 'dark' => $root_primary ],
		];

		$theme = (string) get_option( 'seedcast_theme', 'light' );

		return $themes[ $theme ] ?? $themes['light'];
	}

	/**
	 * Whether a color is light enough to need dark text on it.
	 *
	 * Decided by comparing the two contrast ratios rather than by a luminance
	 * cutoff picked by eye. A cutoff sounds equivalent and is not: a mid gold
	 * such as #c9a961 sits at a luminance of about 0.42, which a halfway
	 * threshold calls dark and hands white text, giving a contrast ratio near
	 * 2.2 to 1 against 9.3 to 1 for black. The ratios are the thing that
	 * matters, so they are what is compared.
	 *
	 * @param string $hex Hex color.
	 * @return bool
	 */
	public static function is_light( string $hex ): bool {
		$luminance = self::luminance( $hex );

		// WCAG relative contrast, where L1 is the lighter of the two.
		$against_white = 1.05 / ( $luminance + 0.05 );
		$against_black = ( $luminance + 0.05 ) / 0.05;

		return $against_black > $against_white;
	}

	/**
	 * The relative luminance of a color, as defined by WCAG.
	 *
	 * Weighted per channel rather than averaged, because the eye is far more
	 * sensitive to green than to blue: an average calls a saturated blue light
	 * when it reads as almost black.
	 *
	 * @param string $hex Hex color.
	 * @return float Between 0 and 1.
	 */
	public static function luminance( string $hex ): float {
		$hex = ltrim( trim( $hex ), '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( strlen( $hex ) < 6 || ! ctype_xdigit( substr( $hex, 0, 6 ) ) ) {
			// Unreadable: treat as black, which gives white text, which is
			// what the card looked like before any of this.
			return 0.0;
		}

		$channels = [];
		foreach ( [ 0, 2, 4 ] as $offset ) {
			$value      = hexdec( substr( $hex, $offset, 2 ) ) / 255;
			$channels[] = $value <= 0.03928
				? $value / 12.92
				: pow( ( $value + 0.055 ) / 1.055, 2.4 );
		}

		return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
	}

	/**
	 * Whether the plugin CSS should load.
	 *
	 * @return bool
	 */
	public static function styles_enabled(): bool {
		return '1' !== self::get( 'scvc_disable_css' );
	}

	/**
	 * The heading above the card.
	 *
	 * @return string
	 */
	public static function heading(): string {
		$value = trim( self::get( 'scvc_heading' ) );
		return '' !== $value ? $value : __( 'Coming to visit us?', 'seedcast-visitor-card' );
	}

	/**
	 * The line under the heading.
	 *
	 * @return string
	 */
	public static function intro(): string {
		$value = trim( self::get( 'scvc_intro' ) );
		return '' !== $value ? $value : __( 'Let us know and we will be looking for you.', 'seedcast-visitor-card' );
	}

	/**
	 * The submit button label.
	 *
	 * @return string
	 */
	public static function button(): string {
		$value = trim( self::get( 'scvc_button' ) );
		return '' !== $value ? $value : __( 'Tell us you are coming', 'seedcast-visitor-card' );
	}

	/**
	 * Whether the card shows when and where the church meets.
	 *
	 * @return bool
	 */
	public static function details_mode(): string {
		$value = self::get( 'scvc_show_details' );

		// '1' and '0' are what this setting held when it was a switch.
		if ( '1' === $value ) {
			return 'after';
		}
		if ( '0' === $value ) {
			return 'never';
		}

		return in_array( $value, [ 'after', 'always', 'never' ], true ) ? $value : 'after';
	}

	/**
	 * The three ways the service details can be shown.
	 *
	 * @return array<string, string>
	 */
	public static function details_modes(): array {
		return [
			'after'  => __( 'After they send the card', 'seedcast-visitor-card' ),
			'always' => __( 'On the card as well', 'seedcast-visitor-card' ),
			'never'  => __( 'Never', 'seedcast-visitor-card' ),
		];
	}

	/**
	 * Whether the details belong on the card in a given state.
	 *
	 * @param bool $confirmed Whether the card has been sent.
	 * @return bool
	 */
	public static function shows_details( bool $confirmed = false ): bool {
		$mode = self::details_mode();

		if ( 'never' === $mode ) {
			return false;
		}
		if ( 'always' === $mode ) {
			return true;
		}

		return $confirmed;
	}

	/**
	 * The small heading above the service details.
	 *
	 * @return string
	 */
	public static function details_heading(): string {
		$value = trim( self::get( 'scvc_details_heading' ) );
		return '' !== $value ? $value : __( 'When and where', 'seedcast-visitor-card' );
	}

	/**
	 * The consent line under the form.
	 *
	 * @return string
	 */
	public static function consent(): string {
		$value = trim( self::get( 'scvc_consent' ) );
		if ( '' !== $value ) {
			return $value;
		}
		return __( 'We will only use this to get in touch about your visit. We will not sell or share it.', 'seedcast-visitor-card' );
	}

	/**
	 * The message shown after someone has filled the card in.
	 *
	 * {name} is replaced with what they gave, {day} with the church's service
	 * day. A church meeting on Saturday gets Saturday, because the day comes
	 * from the shared church settings rather than being assumed.
	 *
	 * @return string
	 */
	public static function confirmation(): string {
		$value = trim( self::get( 'scvc_confirmation' ) );
		if ( '' !== $value ) {
			return $value;
		}
		return __( 'We cannot wait to see you on {day}, {name}!', 'seedcast-visitor-card' );
	}

	/**
	 * The subject of the email a visitor receives.
	 *
	 * @return string
	 */
	public static function followup_subject(): string {
		$value = trim( self::get( 'scvc_followup_subject' ) );
		if ( '' !== $value ) {
			return $value;
		}
		return sprintf(
			/* translators: %s: church name. */
			__( 'We are looking forward to meeting you at %s', 'seedcast-visitor-card' ),
			Church::name()
		);
	}

	/**
	 * The body of the email a visitor receives.
	 *
	 * Returned filled in rather than blank, so the settings screen shows real
	 * text somebody can edit. A placeholder reads as an example of what you
	 * could write, and most people faced with an empty box write nothing,
	 * which means the most useful email this plugin sends never goes out.
	 *
	 * @return string
	 */
	public static function followup_body(): string {
		$value = trim( self::get( 'scvc_followup_body' ) );
		if ( '' !== $value ) {
			return $value;
		}
		return self::default_followup_body();
	}

	/**
	 * The wording used until a church writes its own.
	 *
	 * @return string
	 */
	public static function default_followup_body(): string {
		$lines = [];

		$lines[] = __( 'Hello {name},', 'seedcast-visitor-card' );
		$lines[] = '';
		$lines[] = __( 'Thank you for letting us know you are planning to visit. We are looking forward to meeting you.', 'seedcast-visitor-card' );
		$lines[] = '';
		$lines[] = __( 'Here are the details so you have them on the day:', 'seedcast-visitor-card' );
		$lines[] = '';
		$lines[] = '{church}';
		$lines[] = '{address}';
		$lines[] = '{times}';
		$lines[] = '';
		$lines[] = '{directions}';
		$lines[] = '{note}';
		$lines[] = '';
		$lines[] = __( 'If you have any questions before then, just reply to this message.', 'seedcast-visitor-card' );

		return implode( "\n", $lines );
	}

	/**
	 * The message shown in place of the card when services are suspended.
	 *
	 * @return string
	 */
	public static function suspended_message(): string {
		$value = trim( self::get( 'scvc_suspended_message' ) );
		if ( '' !== $value ) {
			return $value;
		}
		return __( 'We are not gathering this week. Please check back soon.', 'seedcast-visitor-card' );
	}

	/**
	 * Whether the card is switched off because there is no upcoming service.
	 *
	 * @return bool
	 */
	public static function suspended(): bool {
		if ( '1' !== self::get( 'scvc_suspended' ) ) {
			return false;
		}

		/*
		 * A pause set for a holiday should end by itself. Somebody who has to
		 * remember to switch the card back on is somebody who will find it off
		 * in March, and every visitor in between was told there was no service.
		 */
		$until = trim( self::get( 'scvc_suspended_until' ) );
		if ( '' === $until ) {
			return true;
		}

		try {
			$end = new \DateTimeImmutable( $until . ' 23:59:59', wp_timezone() );
		} catch ( \Exception $e ) {
			return true;
		}

		return $end->getTimestamp() >= time();
	}

	/**
	 * The date a pause runs until, if one was set and has not passed.
	 *
	 * @return string
	 */
	public static function suspended_until(): string {
		return trim( self::get( 'scvc_suspended_until' ) );
	}

	/**
	 * Where a notification about a new card should go.
	 *
	 * @return string
	 */
	public static function notify_to(): string {
		$value = trim( self::get( 'scvc_notify_to' ) );
		if ( '' !== $value && is_email( $value ) ) {
			return $value;
		}

		// The church's own contact address if there is one, the site admin
		// otherwise. Both come from settings a church has probably already
		// filled in, so this works without any configuration here.
		return Church::email();
	}

	/**
	 * Fill {name} and {day} in a string.
	 *
	 * @param string $text Text containing tokens.
	 * @param string $name Visitor's name, may be empty.
	 * @return string
	 */
	public static function tokens( string $text, string $name = '' ): string {
		$day = Church::service_day_label();

		if ( '' === trim( $name ) ) {
			/*
			 * Somebody gave a phone number and no name. Dropping the token
			 * would leave "We cannot wait to see you on Sunday, !" so the
			 * whole clause goes, punctuation included.
			 */
			$text = preg_replace( '/,\s*\{name\}/u', '', $text );
			$text = str_replace( '{name}', '', $text );
		} else {
			$text = str_replace( '{name}', $name, $text );
		}

		$text = str_replace( '{day}', $day, $text );
		$text = str_replace( '{church}', Church::name(), $text );

		return trim( (string) $text );
	}
}
