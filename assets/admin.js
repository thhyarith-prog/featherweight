/* Featherweight admin: one-click bulk optimization. No dependencies. */
( function () {
	'use strict';

	var F = window.Featherweight || {};
	var t = F.i18n || {};
	var $ = function ( id ) {
		return document.getElementById( id );
	};
	if ( ! $( 'fw-start' ) ) {
		return;
	}
	var sleep = function ( ms ) {
		return new Promise( function ( s ) {
			setTimeout( s, ms );
		} );
	};
	var fmt = function ( b ) {
		return b > 1048576 ? ( b / 1048576 ).toFixed( 2 ) + ' MB' : ( b / 1024 ).toFixed( 1 ) + ' KB';
	};
	var saved = 0;
	var lastLeft = 1;
	var videoWaits = 0;
	var running = false;

	[ [ 'fw-q', 'fw-q-o' ], [ 'fw-c', 'fw-c-o' ] ].forEach( function ( p ) {
		$( p[ 0 ] ).addEventListener( 'input', function ( e ) {
			$( p[ 1 ] ).value = e.target.value;
		} );
	} );

	function post( action ) {
		return fetch( F.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams( { action: action, _ajax_nonce: F.nonce } ),
		} ).then( function ( r ) {
			return r.text().then( function ( txt ) {
				if ( '-1' === txt.trim() || 403 === r.status ) {
					throw new Error( t.expired ); // Nonce expired: WordPress answers "-1".
				}
				try {
					return JSON.parse( txt );
				} catch ( e ) {
					throw new Error( t.error );
				}
			} );
		} );
	}

	function addLog( type, kind, msg ) {
		var e = $( 'fw-empty' );
		if ( e ) {
			e.remove();
		}
		var li = document.createElement( 'li' );
		var tick = document.createElement( 'span' );
		tick.className = 'pd-tick' + ( 'ok' === type ? '' : ' pd-tick--' + type );
		tick.setAttribute( 'aria-hidden', 'true' );
		tick.textContent = 'ok' === type ? '✓' : ( 'run' === type ? '…' : '!' );
		var sr = document.createElement( 'span' );
		sr.className = 'screen-reader-text';
		sr.textContent = ( 'ok' === type ? t.statusOk : ( 'run' === type ? t.statusRun : t.statusWarn ) ) + ': ';
		var m = document.createElement( 'span' );
		m.className = 'm';
		m.textContent = msg;
		li.appendChild( tick );
		li.appendChild( sr );
		li.appendChild( m );
		if ( kind ) {
			var k = document.createElement( 'span' );
			k.className = 'k pd-badge';
			k.textContent = 'video' === kind ? t.video : t.image;
			li.appendChild( k );
		}
		$( 'fw-log' ).prepend( li );
	}

	function setProgress( left ) {
		var pct = Math.max( 0, Math.min( 100, Math.round( ( 1 - left / F.total ) * 100 ) ) );
		$( 'fw-pct' ).textContent = pct + '%';
		$( 'fw-head-pct' ).textContent = pct + '%';
		$( 'fw-ring-fg' ).style.strokeDashoffset = 402.1 * ( 1 - pct / 100 );
		$( 'fw-ring' ).setAttribute( 'aria-label', t.optimized.replace( '%s', pct ).replace( '%%', '%' ) );
		return pct;
	}

	function setStatus( text, busy, done ) {
		var s = $( 'fw-status' );
		s.textContent = '';
		s.classList.toggle( 'is-done', !! done );
		if ( busy ) {
			var sp = document.createElement( 'span' );
			sp.className = 'fw-spin';
			sp.setAttribute( 'aria-hidden', 'true' );
			s.appendChild( sp );
		}
		s.appendChild( document.createTextNode( text ) );
	}

	async function run() {
		running = true;
		$( 'fw-start' ).disabled = true;
		$( 'fw-reset' ).disabled = true;
		setStatus( t.running, true );
		var fails = 0;
		var pct = 0;
		while ( running ) {
			var r;
			try {
				r = await post( 'featherweight_batch' );
				fails = 0;
			} catch ( e ) {
				if ( ++fails > 5 ) {
					addLog( 'warn', '', t.lost );
					break;
				}
				await sleep( 3000 );
				continue;
			}
			if ( ! r.success ) {
				addLog( 'warn', '', t.error + ' ' + ( 'string' === typeof r.data ? r.data : ( r.data && r.data.message ) || '' ) );
				break;
			}
			var d = r.data;
			saved += d.saved;
			( d.log || [] ).forEach( function ( l ) {
				addLog( l.t, l.k, l.m );
			} );
			$( 'fw-saved' ).textContent = fmt( saved );
			$( 'fw-img' ).textContent = d.images;
			$( 'fw-vid' ).textContent = d.videos + d.videosRunning;
			var left = d.images + d.videos + d.videosRunning;
			pct = setProgress( left );
			lastLeft = left;
			if ( ! left ) {
				setStatus( t.done, false, true );
				$( 'fw-start' ).hidden = true;
				$( 'fw-hero-t' ).textContent = t.allDone;
				$( 'fw-hero-p' ).textContent = t.doneLead;
				addLog( 'ok', '', t.savedTotal.replace( '%s', fmt( saved ) ) );
				break;
			}
			if ( ! d.images && d.videosRunning ) {
				if ( ++videoWaits > 450 ) { // About 30 minutes on one video: stop polling, the server keeps converting.
					setStatus( t.paused );
					break;
				}
				setStatus( t.videoRunning, true );
				await sleep( 4000 );
			} else {
				videoWaits = 0;
			}
		}
		running = false;
		$( 'fw-reset' ).disabled = false;
		if ( lastLeft > 0 ) { // Not "pct < 100": 1 of 300 left still rounds to 100%.
			$( 'fw-start' ).disabled = false;
			if ( ! $( 'fw-status' ).classList.contains( 'is-done' ) ) {
				setStatus( t.paused );
			}
		}
	}

	$( 'fw-start' ).addEventListener( 'click', function () {
		$( 'fw-start' ).hidden = false;
		if ( ! running ) {
			run();
		}
	} );
	$( 'fw-reset' ).addEventListener( 'click', async function () {
		if ( ! window.confirm( t.confirmReset ) ) {
			return;
		}
		$( 'fw-reset' ).disabled = true;
		$( 'fw-reset' ).setAttribute( 'aria-busy', 'true' );
		try {
			var r = await post( 'featherweight_reset' );
			if ( r && r.success ) {
				window.location.reload();
				return;
			}
			addLog( 'warn', '', t.error );
		} catch ( e ) {
			addLog( 'warn', '', e.message );
		}
		$( 'fw-reset' ).disabled = false;
		$( 'fw-reset' ).removeAttribute( 'aria-busy' );
	} );
	window.addEventListener( 'beforeunload', function ( e ) {
		if ( running ) {
			e.preventDefault();
			e.returnValue = t.leave;
		}
	} );
} )();
