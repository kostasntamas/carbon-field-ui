( function () {
	'use strict';

	function slugify( value ) {
		return value
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' );
	}

	// The field inputs are React-controlled, so the value has to be set
	// through the native setter and followed by a real "input" event —
	// otherwise React's internal state never sees the change.
	function setControlledValue( input, value ) {
		var setter = Object.getOwnPropertyDescriptor( window.HTMLInputElement.prototype, 'value' ).set;
		setter.call( input, value );
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}

	document.addEventListener( 'focusout', function ( event ) {
		var labelInput = event.target;

		if ( ! labelInput.matches || ! labelInput.matches( '[data-cfui-label-field]' ) ) {
			return;
		}

		var row = labelInput.closest( '.cf-complex__group' );
		if ( ! row ) {
			return;
		}

		var nameInput = row.querySelector( '[data-cfui-name-field]' );
		if ( ! nameInput || nameInput.value.trim() !== '' ) {
			return; // never override a name the user already typed
		}

		var slug = slugify( labelInput.value );
		if ( slug ) {
			setControlledValue( nameInput, slug );
		}
	}, true );
}() );
