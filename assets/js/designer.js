/**
 * Card Designer.
 *
 * Keeps the preview in step with the controls. The preview is server rendered
 * to begin with, so this only has to move what changes rather than rebuild the
 * card, which is what stops the preview drifting from the real thing.
 */
( function () {
	'use strict';

	var preview = document.getElementById( 'scvc-d-preview' );
	var colorInput = document.getElementById( 'scvc-d-color' );
	var darkInput = document.getElementById( 'scvc-d-color-dark' );

	if ( ! preview ) {
		return;
	}

	/**
	 * Templates that put the heading in a filled band across the top.
	 */
	var BANDED = [ 'classic', 'bold' ];

	/**
	 * Every card in the preview, since both the form and the confirmation are
	 * rendered and one is hidden. Template and color changes have to reach
	 * both, or switching views would show the old design.
	 */
	function cards() {
		return preview.querySelectorAll( '.scvc-card' );
	}

	function card() {
		return preview.querySelector( '.scvc-card' );
	}

	function chosenTemplate() {
		var checked = document.querySelector( '.scvc-d-template:checked' );
		return checked ? checked.value : '';
	}

	/**
	 * Apply the chosen template.
	 */
	function applyTemplate() {
		var template = chosenTemplate();
		if ( ! template ) {
			return;
		}

		cards().forEach( function ( el ) {
			applyTemplateTo( el, template );
		} );
	}

	function applyTemplateTo( el, template ) {
		el.className = el.className.replace( /scvc-card--\w+/, 'scvc-card--' + template );

		/*
		 * The banded templates move the heading inside a band, which a class
		 * swap alone cannot do. Without this, choosing a connection card shows
		 * the new colors on the old layout.
		 */
		var banded = BANDED.indexOf( template ) !== -1;
		var band = el.querySelector( '.scvc-card__band' );
		var heading = el.querySelector( '.scvc-card__heading' );

		if ( banded && ! band && heading ) {
			band = document.createElement( 'div' );
			band.className = 'scvc-card__band';
			el.insertBefore( band, el.firstChild );
			band.appendChild( heading );
		} else if ( ! banded && band && heading ) {
			el.insertBefore( heading, el.firstChild );
			band.parentNode.removeChild( band );
		}
	}

	/**
	 * Apply the chosen color.
	 *
	 * Sets the same custom property the stylesheet reads, rather than a
	 * preview-only override.
	 */
	/**
	 * Whether a color is light enough to need dark text on it.
	 *
	 * Compares the two contrast ratios rather than using a luminance cutoff.
	 * A cutoff sounds equivalent and is not: a mid gold sits around 0.42, which
	 * a halfway threshold calls dark and hands white text, giving roughly
	 * 2.2 to 1 against 9.3 to 1 for black.
	 *
	 * Kept identical to the PHP in class-settings.php, so the preview and the
	 * real card never disagree.
	 */
	function isLight( hex ) {
		var luminance = relativeLuminance( hex );
		var againstWhite = 1.05 / ( luminance + 0.05 );
		var againstBlack = ( luminance + 0.05 ) / 0.05;

		return againstBlack > againstWhite;
	}

	function relativeLuminance( hex ) {
		hex = String( hex ).replace( '#', '' );

		if ( 3 === hex.length ) {
			hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
		}
		if ( hex.length < 6 ) {
			return 0;
		}

		var channels = [ 0, 2, 4 ].map( function ( offset ) {
			var value = parseInt( hex.substr( offset, 2 ), 16 ) / 255;
			return value <= 0.03928
				? value / 12.92
				: Math.pow( ( value + 0.055 ) / 1.055, 2.4 );
		} );

		return ( 0.2126 * channels[0] ) + ( 0.7152 * channels[1] ) + ( 0.0722 * channels[2] );
	}

	function textOn( hex ) {
		return isLight( hex ) ? '#1d2327' : '#ffffff';
	}

	function applyColor() {
		cards().forEach( function ( el ) {
			if ( colorInput ) {
				el.style.setProperty( '--scvc-accent', colorInput.value );
				el.style.setProperty( '--scvc-on-accent', textOn( colorInput.value ) );
			}
			if ( darkInput ) {
				el.style.setProperty( '--scvc-accent-dark', darkInput.value );
				el.style.setProperty( '--scvc-on-accent-dark', textOn( darkInput.value ) );
			}
		} );
	}

	/**
	 * The header color only does anything on the bold template, so the row is
	 * dimmed rather than hidden on the others. Hiding it would make the
	 * control appear and disappear as templates are tried, which reads as a
	 * fault; dimming says it is there but not in use.
	 */
	function applyDarkRelevance() {
		var row = document.querySelector( '.scvc-d-dark-row' );
		if ( row ) {
			row.classList.toggle(
				'is-inactive',
				'bold' !== chosenTemplate() || followingTheme()
			);
		}
	}

	/**
	 * Whether the card is set to follow the suite theme.
	 */
	function followingTheme() {
		var mode = document.querySelector( '.scvc-d-color-mode:checked' );
		return ! mode || 'theme' === mode.value;
	}

	/**
	 * Dim the two pickers when the card is following the theme, and put them
	 * back to the theme's colors so the preview keeps matching.
	 */
	function applyColorMode() {
		var following = followingTheme();

		document.querySelectorAll( '.scvc-d-own-colors' ).forEach( function ( row ) {
			row.classList.toggle( 'is-inactive', following );
		} );

		applyDarkRelevance();
	}

	/**
	 * Show or hide a field in the preview.
	 *
	 * Hidden rather than removed, so unticking and reticking puts the field
	 * back exactly where it was instead of at the end.
	 */
	function applyFields() {
		var el = card();
		if ( ! el ) {
			return;
		}

		document.querySelectorAll( '.scvc-d-field' ).forEach( function ( input ) {
			var field = input.getAttribute( 'data-field' );
			var target = null;

			if ( 'name' === field ) {
				target = el.querySelector( '#seedcast_author_name' );
			} else if ( 'email' === field ) {
				target = el.querySelector( '#seedcast_author_email' );
			} else if ( 'status' === field ) {
				target = el.querySelector( '.scvc-choices [name="scvc_status"]' );
			} else if ( 'interests' === field ) {
				target = el.querySelector( '.scvc-choices [name="scvc_interests[]"]' );
			} else {
				target = el.querySelector( '#scvc_' + field );
			}

			if ( ! target ) {
				return;
			}

			var row = target.closest( '.sc-form__row' );
			if ( row ) {
				row.style.display = input.checked ? '' : 'none';
			}
		} );
	}

	/**
	 * Dim the wording editor for a group that is switched off, so it is clear
	 * which of the two blocks is in play without the page jumping as boxes
	 * appear and disappear.
	 */
	function applyChoiceRelevance() {
		[ 'status', 'interests' ].forEach( function ( key ) {
			var block = document.querySelector( '[data-choices="' + key + '"]' );
			var toggle = document.querySelector( '.scvc-d-field[data-field="' + key + '"]' );
			if ( block && toggle ) {
				block.classList.toggle( 'is-inactive', ! toggle.checked );
			}
		} );
	}

	/**
	 * Put typed wording into the preview.
	 *
	 * Emptying a field falls back to the wording the card would actually use,
	 * so the preview never shows a blank heading that the front end would fill
	 * in anyway.
	 */
	function applyText() {
		document.querySelectorAll( '.scvc-d-text' ).forEach( function ( input ) {
			var selector = input.getAttribute( 'data-preview' );
			if ( ! selector ) {
				return;
			}

			preview.querySelectorAll( selector ).forEach( function ( target ) {
				var typed = input.value.trim();

				if ( '' !== typed ) {
					target.textContent = typed;
					target.hidden = false;
					return;
				}

				/*
				 * Nothing typed. The heading and opening line are dropped
				 * entirely when blank rather than shown empty, which is what
				 * the front end does, so the preview has to as well.
				 */
				target.hidden = true;
			} );
		} );
	}

	/**
	 * Put the typed confirmation into its preview, tokens filled in.
	 *
	 * The same substitution the front end does, so somebody typing sees the
	 * sentence a visitor will read rather than one with {name} in it.
	 */
	function applyConfirmText() {
		var input = document.querySelector( '.scvc-d-confirm-text' );
		var target = preview.querySelector( '.scvc-card__confirmed' );

		if ( ! input || ! target ) {
			return;
		}

		var strings = window.scvcDesigner || {};
		var text = input.value.trim();

		if ( '' === text ) {
			return;
		}

		var name = strings.sampleName || 'Sarah';

		// An empty name drops the clause it sits in, punctuation included,
		// which is what the front end does for somebody who left only a phone
		// number. Here there is always a name, so only the tokens are filled.
		text = text.split( '{name}' ).join( name );
		text = text.split( '{day}' ).join( strings.serviceDay || '' );
		text = text.split( '{church}' ).join( strings.church || '' );

		target.textContent = text;
	}

	/**
	 * Switch between the card and the message shown after sending it.
	 */
	function applyConfirmedView() {
		var toggle = document.getElementById( 'scvc-d-show-confirmed' );
		var block = preview.querySelector( '.scvc-d-confirmed' );
		var first = preview.querySelector( '.scvc-card' );

		if ( ! toggle || ! block || ! first ) {
			return;
		}

		block.hidden = ! toggle.checked;
		first.hidden = toggle.checked;
	}

	/**
	 * Show or hide the service details in the preview.
	 */
	function applyDetails() {
		var chosen = document.querySelector( '.scvc-d-details-mode:checked' );
		if ( ! chosen ) {
			return;
		}

		var mode = chosen.value;
		var confirmedBlock = preview.querySelector( '.scvc-d-confirmed' );

		preview.querySelectorAll( '.scvc-card__details' ).forEach( function ( block ) {
			// The two previews are drawn separately, so each is judged on
			// whether it is the one shown after sending.
			var afterSending = !! ( confirmedBlock && confirmedBlock.contains( block ) );
			var show = 'always' === mode || ( 'after' === mode && afterSending );

			block.hidden = ! show;
		} );
	}

	function refresh() {
		applyTemplate();
		applyColor();
		applyFields();
		applyColorMode();
		applyChoiceRelevance();
		applyText();
		applyDetails();
		applyConfirmText();
		applyConfirmedView();
	}

	document.querySelectorAll( '.scvc-d-template' ).forEach( function ( input ) {
		input.addEventListener( 'change', function () {
			document.querySelectorAll( '.scvc-sc-option' ).forEach( function ( option ) {
				option.classList.remove( 'is-selected' );
			} );
			if ( input.closest( '.scvc-sc-option' ) ) {
				input.closest( '.scvc-sc-option' ).classList.add( 'is-selected' );
			}
			refresh();
		} );
	} );

	document.querySelectorAll( '.scvc-d-color-mode' ).forEach( function ( input ) {
		input.addEventListener( 'change', function () {
			applyColorMode();
			applyColor();
		} );
	} );

	if ( colorInput ) {
		colorInput.addEventListener( 'input', function () {
			// Moving a picker means wanting that color, so stop following.
			var own = document.querySelector( '.scvc-d-color-mode[value="own"]' );
			if ( own && ! own.checked ) {
				own.checked = true;
				applyColorMode();
			}
			applyColor();
		} );
	}

	if ( darkInput ) {
		darkInput.addEventListener( 'input', function () {
			var own = document.querySelector( '.scvc-d-color-mode[value="own"]' );
			if ( own && ! own.checked ) {
				own.checked = true;
				applyColorMode();
			}
			applyColor();
		} );
	}

	document.querySelectorAll( '.scvc-d-field' ).forEach( function ( input ) {
		input.addEventListener( 'change', function () {
			applyFields();
			applyChoiceRelevance();
		} );
	} );

	document.querySelectorAll( '.scvc-d-text' ).forEach( function ( input ) {
		input.addEventListener( 'input', applyText );
	} );

	document.querySelectorAll( '.scvc-d-details-mode' ).forEach( function ( input ) {
		input.addEventListener( 'change', function () {
			applyDetails();

			// Choosing the mode that only shows them after sending is easier
			// to judge on the view that shows them.
			var toggle = document.getElementById( 'scvc-d-show-confirmed' );
			if ( 'after' === input.value && toggle && ! toggle.checked ) {
				toggle.checked = true;
				applyConfirmedView();
				applyDetails();
			}
		} );
	} );

	/**
	 * A blank row stops looking blank once it has something in it, so the
	 * switch beside it stops looking inactive.
	 */
	document.querySelectorAll( '.scvc-d-row--new .scvc-d-choice-label' ).forEach( function ( input ) {
		input.addEventListener( 'input', function () {
			var row = input.closest( '.scvc-d-row' );
			if ( row ) {
				row.classList.toggle( 'scvc-d-row--new', '' === input.value.trim() );
			}
		} );
	} );

	var confirmInput = document.querySelector( '.scvc-d-confirm-text' );
	if ( confirmInput ) {
		confirmInput.addEventListener( 'input', function () {
			applyConfirmText();

			// Somebody editing this wants to see it, so show that view.
			var toggle = document.getElementById( 'scvc-d-show-confirmed' );
			if ( toggle && ! toggle.checked ) {
				toggle.checked = true;
				applyConfirmedView();
			}
		} );
	}

	var confirmedToggle = document.getElementById( 'scvc-d-show-confirmed' );
	if ( confirmedToggle ) {
		confirmedToggle.addEventListener( 'change', applyConfirmedView );
	}

	refresh();
}() );
