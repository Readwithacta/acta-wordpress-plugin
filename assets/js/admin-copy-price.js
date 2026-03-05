/**
 * Acta admin copy-price button.
 * Copies the price snippet to clipboard when the user clicks the copy button.
 */
( function() {
	'use strict';

	var COPIED_DURATION_MS = 2000;
	var SNIPPET_TEXT = '<div id="acta-price" data-price="ENTER_PRICE_HERE"></div>';
	var ICON_SVG = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';

	function showCopiedFeedback( btn ) {
		btn.innerHTML = '<span style="font-size:11px;">Copied!</span>';
		setTimeout( function() {
			btn.innerHTML = ICON_SVG;
		}, COPIED_DURATION_MS );
	}

	function doCopy( btn ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( SNIPPET_TEXT ).then( function() {
				showCopiedFeedback( btn );
			} );
		} else {
			var ta = document.createElement( 'textarea' );
			ta.value = SNIPPET_TEXT;
			ta.style.position = 'fixed';
			ta.style.left = '-9999px';
			document.body.appendChild( ta );
			ta.select();
			document.execCommand( 'copy' );
			document.body.removeChild( ta );
			showCopiedFeedback( btn );
		}
	}

	var btn = document.querySelector( '.acta-copy-price-btn' );
	if ( btn ) {
		btn.addEventListener( 'click', function() {
			doCopy( btn );
		} );
	}
} )();
