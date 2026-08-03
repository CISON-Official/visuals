( function ( $ ) {
	'use strict';

	$( function () {
		// Countdown-style refresh isn't necessary server-side (cooldown days are
		// computed on each page load), but we do add a lightweight confirm on
		// exam registration since it immediately redirects to a payment page.
		$( '.cmp-exam-register form' ).on( 'submit', function ( e ) {
			var checked = $( this ).find( 'input[name="selected_exams[]"]:checked' ).length;
			if ( checked === 0 ) {
				e.preventDefault();
				window.alert( cmpPublic && cmpPublic.selectAtLeastOne ? cmpPublic.selectAtLeastOne : 'Please select at least one examination.' );
			}
		} );
	} );
} )( jQuery );
