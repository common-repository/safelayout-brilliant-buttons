jQuery( document ).ready( function( $ ) {
	$( "#deactivate-safelayout-brilliant-buttons" ).click( function ( e ) {
		e.preventDefault();
		$( "#sl-bb-feedback-modal" ).css( 'display', 'block' );
	});
	$( "#sl-bb-feedback-modal" ).click( function ( e ) {
		if ( e.target === this ) {
			e.preventDefault();
			hideModal();
		}
	});
	$( document ).on( 'keydown', function ( e ) {
		if ( e.keyCode === 27 && $( "#sl-bb-feedback-modal" ).css( 'display' ) != 'none' ) { // ESC
			hideModal();
		}
	});
	function hideModal() {
		$( "#sl-bb-feedback-loader" ).css( 'display', 'none' );
		$( "#sl-bb-feedback-modal" ).css( 'display', 'none' );
	}
	$( "#sl-bb-feedback-submit" ).click( function ( e ) {
		e.preventDefault();
		$( "#sl-bb-feedback-loader" ).css( 'display', 'block' );
		var id = $( "[name='sl-bb-feedback-radio']:checked" ).attr( "id" );
		var type = $( "[name='sl-bb-feedback-radio']:checked" ).val() || '';
		var text = '';
		if ( id != 'sl-bb-feedback-item1' ) {
			text = $( "#" + id + "-text" ).val() || '';
		}
		$.post( slbbButtonsAjax.ajax_url, {
			_ajax_nonce: slbbButtonsAjax.nonce,
			action: "slbb_buttons_feedback",
			type: type,
			text: text
		}, function() {
			$( "#sl-bb-feedback-loader-msg" ).html( $( "#sl-bb-feedback-loader-msg-tr" ).html() );
			setTimeout( function(){$( '#sl-bb-feedback-modal' ).fadeTo( 1000, 0, function () {hideModal()} )}, 500 );
			window.location = $( "#deactivate-safelayout-brilliant-buttons" ).attr( "href" );
		});
	});
	$( "#sl-bb-feedback-skip" ).click( function ( e ) {
		e.preventDefault();
		$( "#sl-bb-feedback-modal" ).css( 'display', 'none' );
		window.location = $( "#deactivate-safelayout-brilliant-buttons" ).attr( "href" );
	});
	$( "[name='sl-bb-feedback-radio']" ).change( function() {
		$( "#sl-bb-feedback-item2-text,#sl-bb-feedback-item5-text,#sl-bb-feedback-item6-text" ).css( 'display', 'none' );
		if ( this.id != 'sl-bb-feedback-item1' ) {
			$( "#" + this.id + "-text" ).css( 'display', 'initial' );
		}
	});
	$( "#sl-bb-rate-later,#sl-bb-rate-already" ).click( function ( e ) {
		e.preventDefault();
		$.post( slbbButtonsAjax.ajax_url, {
			_ajax_nonce: slbbButtonsAjax.nonce,
			action: "slbb_buttons_rate_reminder",
			type: this.id
		});
		var el = $( "#sl-bb-rate-reminder" );
		el.fadeTo(100, 0, function () {
			el.slideUp(100, function () {
				el.remove();
			});
		});
	});
	$( "#sl-bb-upgrade-later" ).click( function ( e ) {
		e.preventDefault();
		$.post( slbbButtonsAjax.ajax_url, {
			_ajax_nonce: slbbButtonsAjax.nonce,
			action: "slbb_buttons_upgrade",
		});
		var el = $( "#sl-bb-upgrade-reminder" );
		el.fadeTo(100, 0, function () {
			el.slideUp(100, function () {
				el.remove();
			});
		});
	});
});