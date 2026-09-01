/**
 * Shortcode generator.
 *
 * Builds the shortcode from whatever has been chosen and keeps the preview in
 * step. No jQuery: this screen has one job and does not need a library to do
 * it, and one fewer dependency is one fewer thing to conflict with whatever
 * else a church has installed.
 */
( function () {
	'use strict';

	var code = document.getElementById( 'scvc-sc-code' );
	var copy = document.getElementById( 'scvc-sc-copy' );
	var preview = document.getElementById( 'scvc-sc-preview' );
	var color = document.getElementById( 'scvc-sc-color' );
	var header = document.getElementById( 'scvc-sc-header' );
	var reset = document.getElementById( 'scvc-sc-color-reset' );
	var headerReset = document.getElementById( 'scvc-sc-header-reset' );

	if ( ! code ) {
		return;
	}

	var strings = window.scvcSc || { copied: 'Copied', copy: 'Copy shortcode' };

	// Handed in rather than written here, so the name lives in one place and
	// the generator cannot emit a shortcode the plugin does not register.
	var tag = strings.tag || 'scvc_visitor_card';

	/**
	 * Every control that contributes an attribute.
	 */
	function inputs() {
		return document.querySelectorAll( '[data-param]' );
	}

	/**
	 * Escape a value for use inside double quotes in a shortcode.
	 */
	function clean( value ) {
		return String( value ).replace( /"/g, '' ).trim();
	}

	/**
	 * Rebuild the shortcode.
	 */
	function build() {
		var parts = [];
		var chosenTemplate = '';

		inputs().forEach( function ( el ) {
			var param = el.getAttribute( 'data-param' );
			var value = '';

			if ( 'radio' === el.type ) {
				if ( ! el.checked ) {
					return;
				}
				value = el.value;
				chosenTemplate = value;
			} else {
				value = el.value;
			}

			// A select whose value matches its default means "leave it alone",
			// which is expressed by leaving the attribute out entirely.
			if ( 'SELECT' === el.tagName && value === String( el.getAttribute( 'data-default' ) ) ) {
				return;
			}

			value = clean( value );

			if ( '' === value ) {
				return;
			}

			/*
			 * An attribute is only worth writing out when it differs from the
			 * site default. Emitting template="card" when card is already the
			 * default makes the shortcode look more complicated than it is,
			 * and leaves a value to keep in step by hand if the default ever
			 * changes. Same for a color that has not been moved.
			 */
			if ( 'template' === param && el.hasAttribute( 'checked' ) ) {
				return;
			}

			// A color input always reports lowercase hex, but the stored
			// default came from a settings field somebody could have typed in
			// uppercase, so compare them on equal terms.
			if ( ( 'color' === param || 'header' === param )
				&& value.toLowerCase() === String( el.getAttribute( 'data-default' ) ).toLowerCase() ) {
				return;
			}

			parts.push( param + '="' + value + '"' );
		} );

		code.textContent = '[' + tag + ( parts.length ? ' ' + parts.join( ' ' ) : '' ) + ']';

		updatePreview( chosenTemplate );
	}

	/**
	 * Reflect the chosen wording and style in the preview.
	 *
	 * Only the parts that can change from the shortcode. Everything else is
	 * the real card, rendered server side, so the preview cannot drift from
	 * what a visitor is actually served.
	 */
	function updatePreview( template ) {
		if ( ! preview ) {
			return;
		}

		var card = preview.querySelector( '.scvc-card' );
		if ( ! card ) {
			return;
		}

		if ( template ) {
			card.className = card.className.replace( /scvc-card--\w+/, 'scvc-card--' + template );

			/*
			 * Two templates put the heading in a filled band and the rest do
			 * not, so the preview has to move the heading rather than only
			 * swap a class. Without this, choosing a connection card shows the
			 * new colors but the old layout.
			 */
			var banded = ( 'classic' === template || 'bold' === template );
			var band = card.querySelector( '.scvc-card__band' );
			var heading = card.querySelector( '.scvc-card__heading' );

			if ( banded && ! band && heading ) {
				band = document.createElement( 'div' );
				band.className = 'scvc-card__band';
				card.insertBefore( band, card.firstChild );
				band.appendChild( heading );
			} else if ( ! banded && band && heading ) {
				card.insertBefore( heading, card.firstChild );
				band.parentNode.removeChild( band );
			}
		}

		// The service details are rendered server side, so the preview shows
		// or hides what is already there rather than building it in script.
		var detailsSelect = document.getElementById( 'scvc-sc-details' );
		var detailsBlock = preview.querySelector( '.scvc-card__details' );
		if ( detailsSelect && detailsBlock ) {
			/*
			 * This preview is the card before it is sent, so only the mode
			 * that puts the details on the form shows them here. An empty
			 * value means whatever the Card Designer is set to, which is what
			 * the preview was rendered with.
			 */
			var mode = detailsSelect.value;
			if ( '' !== mode ) {
				detailsBlock.hidden = ( 'always' !== mode );
			}
		}

		swap( '.scvc-card__heading', 'scvc-sc-heading' );
		swap( '.scvc-card__intro', 'scvc-sc-intro' );
		swap( '.sc-btn', 'scvc-sc-button' );

		/*
		 * The stylesheet reads these custom properties, so setting them here
		 * is the same mechanism the front end uses rather than a preview-only
		 * trick. The text colors are worked out the same way the server does,
		 * so a pale card gets dark text here too.
		 */
		if ( color ) {
			card.style.setProperty( '--scvc-accent', color.value );
			card.style.setProperty( '--scvc-on-accent', textOn( color.value ) );
		}
		if ( header ) {
			card.style.setProperty( '--scvc-accent-dark', header.value );
			card.style.setProperty( '--scvc-on-accent-dark', textOn( header.value ) );
		}

		// The header color only does anything on the bold template.
		var headerRow = document.querySelector( '.scvc-sc-header-row' );
		if ( headerRow ) {
			headerRow.classList.toggle( 'is-inactive', 'bold' !== template );
		}

		// Keep the save form in step with whatever is on screen, so the button
		// commits what is being looked at.
	}

	/**
	 * Put a typed value into the preview, or put the placeholder back when the
	 * field has been cleared.
	 */
	function swap( selector, inputId ) {
		var target = preview.querySelector( selector );
		var field = document.getElementById( inputId );

		if ( ! target || ! field ) {
			return;
		}

		var typed = clean( field.value );
		target.textContent = '' !== typed ? typed : field.getAttribute( 'placeholder' );
	}

	inputs().forEach( function ( el ) {
		el.addEventListener( 'input', build );
		el.addEventListener( 'change', function () {
			if ( 'radio' === el.type ) {
				document.querySelectorAll( '.scvc-sc-option' ).forEach( function ( option ) {
					option.classList.remove( 'is-selected' );
				} );
				if ( el.closest( '.scvc-sc-option' ) ) {
					el.closest( '.scvc-sc-option' ).classList.add( 'is-selected' );
				}
			}
			build();
		} );
	} );

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

	if ( reset && color ) {
		reset.addEventListener( 'click', function () {
			color.value = color.getAttribute( 'data-default' );
			build();
		} );
	}

	if ( headerReset && header ) {
		headerReset.addEventListener( 'click', function () {
			header.value = header.getAttribute( 'data-default' );
			build();
		} );
	}

	if ( copy ) {
		copy.addEventListener( 'click', function () {
			var text = code.textContent;

			var done = function () {
				copy.textContent = strings.copied;
				window.setTimeout( function () {
					copy.textContent = strings.copy;
				}, 1600 );
			};

			/*
			 * The clipboard API needs a secure context, and plenty of church
			 * sites are still edited over plain http on a local network. The
			 * textarea fallback is not elegant but it works everywhere, and
			 * silently doing nothing on those sites would be worse.
			 */
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( text ).then( done ).catch( fallback );
			} else {
				fallback();
			}

			function fallback() {
				var scratch = document.createElement( 'textarea' );
				scratch.value = text;
				scratch.setAttribute( 'readonly', '' );
				scratch.style.position = 'absolute';
				scratch.style.left = '-9999px';
				document.body.appendChild( scratch );
				scratch.select();
				try {
					document.execCommand( 'copy' );
					done();
				} catch ( e ) {
					// Nothing sensible left to try. The code is on screen and
					// can be selected by hand.
				}
				document.body.removeChild( scratch );
			}
		} );
	}

	build();
}() );
