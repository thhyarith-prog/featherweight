/* Featherweight → Alt text: list, edit, fill from file names, Smart AI (Premium). No dependencies. */
( function () {
	'use strict';

	var A = window.FeatherweightAlt || {};
	var t = A.i18n || {};
	var $ = function ( id ) {
		return document.getElementById( id );
	};
	var list = $( 'fwa-list' );
	if ( ! list ) {
		return;
	}
	var status = $( 'fwa-status' );
	var page = 1;
	var pages = 1;
	var bulk = null; // { done, stop }

	function fmt( s ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( s ).replace( /%(\d\$)?[sd]/g, function ( m, pos ) {
			return String( pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ i++ ] );
		} );
	}
	function el( tag, cls, text ) {
		var e = document.createElement( tag );
		if ( cls ) {
			e.className = cls;
		}
		if ( text != null ) {
			e.textContent = text;
		}
		return e;
	}
	function post( action, data ) {
		var b = new FormData();
		b.append( 'action', 'featherweight_alt_' + action );
		b.append( 'nonce', A.nonce );
		Object.keys( data || {} ).forEach( function ( k ) {
			b.append( k, data[ k ] );
		} );
		return fetch( A.ajax, { method: 'POST', credentials: 'same-origin', body: b } )
			.then( function ( r ) {
				return r.json();
			} )
			.catch( function () {
				return { success: false, data: { message: t.error } };
			} );
	}
	function say( text, undoToken ) {
		status.textContent = text || '';
		if ( undoToken ) {
			var u = el( 'button', 'button-link fwa-undo', t.undo );
			u.type = 'button';
			u.addEventListener( 'click', function () {
				u.disabled = true;
				post( 'undo', { token: undoToken } ).then( function ( j ) {
					if ( j && j.success ) {
						counts( j.data.counts );
						say( t.undone );
						load( 1 );
					} else {
						say( ( j && j.data && j.data.message ) || t.error );
					}
				} );
			} );
			status.appendChild( document.createTextNode( ' ' ) );
			status.appendChild( u );
		}
	}
	function counts( c ) {
		if ( ! c ) {
			return;
		}
		var pct = c.total ? Math.floor( ( 100 * ( c.total - c.missing ) ) / c.total ) : 100;
		$( 'fwa-missing' ).textContent = c.missing.toLocaleString();
		$( 'fwa-pct' ).textContent = pct + '%';
		$( 'fwa-ring-fg' ).setAttribute( 'stroke-dashoffset', String( 402.1 * ( 1 - pct / 100 ) ) );
		var count = document.querySelector( '.pd-tabs .pd-count' );
		if ( count ) {
			count.textContent = c.missing.toLocaleString();
			count.hidden = ! c.missing;
		}
		[ 'fwa-ai-all', 'fwa-files' ].forEach( function ( id ) {
			if ( $( id ) && ! bulk ) {
				$( id ).disabled = ! c.missing;
			}
		} );
		pages = Math.max( 1, Math.ceil( c.missing / 20 ) );
	}

	function row( it ) {
		var li = el( 'li', 'fwa-item' );
		li.dataset.id = it.id;
		var img = el( 'img', 'fwa-thumb' );
		img.src = it.thumb;
		img.alt = '';
		img.width = 72;
		img.height = 72;
		img.loading = 'lazy';
		li.appendChild( img );

		var body = el( 'div', 'fwa-body' );
		var name = el( 'a', 'fwa-file', it.file );
		name.href = it.edit;
		body.appendChild( name );
		if ( it.used_on ) {
			body.appendChild( el( 'span', 'fwa-meta', fmt( t.usedOn, it.used_on ) ) );
		}
		var field = el( 'div', 'fwa-field' );
		var input = el( 'input', 'regular-text' );
		input.type = 'text';
		input.maxLength = 250;
		input.value = it.alt || '';
		input.placeholder = it.suggest || '';
		input.setAttribute( 'aria-label', fmt( t.altFor, it.file ) );
		field.appendChild( input );
		var save = el( 'button', 'button', t.save );
		save.type = 'button';
		field.appendChild( save );
		var ai = null;
		if ( A.premium ) {
			ai = el( 'button', 'button fwa-ai', t.writeAi );
			ai.type = 'button';
			field.appendChild( ai );
		}
		body.appendChild( field );

		var hint = el( 'span', 'fwa-hint' );
		if ( it.suggest ) {
			var use = el( 'button', 'button-link', t.useIt + ': ' + it.suggest );
			use.type = 'button';
			use.addEventListener( 'click', function () {
				input.value = it.suggest;
				input.focus();
			} );
			hint.appendChild( use );
		} else {
			hint.textContent = A.premium ? t.noSuggestAi : t.noSuggest;
		}
		body.appendChild( hint );
		var msg = el( 'span', 'fwa-msg' );
		msg.setAttribute( 'role', 'status' );
		body.appendChild( msg );
		li.appendChild( body );

		function done( j ) {
			if ( j && j.success ) {
				input.value = j.data.alt;
				li.classList.add( 'is-done' );
				msg.textContent = t.saved;
				counts( j.data.counts );
			} else {
				msg.textContent = ( j && j.data && j.data.message ) || t.error;
			}
		}
		save.addEventListener( 'click', function () {
			if ( ! input.value.trim() ) {
				input.focus();
				return;
			}
			save.setAttribute( 'aria-busy', 'true' );
			post( 'save', { id: it.id, alt: input.value } ).then( function ( j ) {
				save.removeAttribute( 'aria-busy' );
				done( j );
			} );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				save.click();
			}
		} );
		if ( ai ) {
			ai.addEventListener( 'click', function () {
				ai.setAttribute( 'aria-busy', 'true' );
				ai.textContent = t.writing;
				msg.textContent = '';
				post( 'ai', { id: it.id } ).then( function ( j ) {
					ai.removeAttribute( 'aria-busy' );
					ai.textContent = t.writeAi;
					done( j );
				} );
			} );
		}
		return li;
	}

	function load( p ) {
		list.setAttribute( 'aria-busy', 'true' );
		return post( 'list', { page: p } ).then( function ( j ) {
			list.removeAttribute( 'aria-busy' );
			list.textContent = '';
			if ( ! j || ! j.success ) {
				list.appendChild( el( 'li', 'pd-empty', t.error ) );
				return;
			}
			counts( j.data.counts );
			page = j.data.page;
			if ( ! j.data.items.length && page > 1 ) {
				return load( page - 1 );
			}
			if ( ! j.data.items.length ) {
				list.appendChild( el( 'li', 'pd-empty', t.none ) );
			}
			j.data.items.forEach( function ( it ) {
				list.appendChild( row( it ) );
			} );
			pager();
		} );
	}

	function pager() {
		var box = $( 'fwa-pager' );
		box.textContent = '';
		if ( pages < 2 ) {
			return;
		}
		var prev = el( 'button', 'button', t.prev );
		prev.type = 'button';
		prev.disabled = page <= 1;
		prev.addEventListener( 'click', function () {
			load( page - 1 );
		} );
		var next = el( 'button', 'button', t.next );
		next.type = 'button';
		next.disabled = page >= pages;
		next.addEventListener( 'click', function () {
			load( page + 1 );
		} );
		box.appendChild( prev );
		box.appendChild( el( 'span', 'fwa-page', fmt( t.page, page, pages ) ) );
		box.appendChild( next );
	}

	var files = $( 'fwa-files' );
	files.addEventListener( 'click', function () {
		files.setAttribute( 'aria-busy', 'true' );
		post( 'files', {} ).then( function ( j ) {
			files.removeAttribute( 'aria-busy' );
			if ( j && j.success ) {
				counts( j.data.counts );
				say( j.data.filled ? ( j.data.filled === 1 ? t.filled1 : fmt( t.filled, j.data.filled ) ) : t.filledNone, j.data.undo );
				load( 1 );
			} else {
				say( t.error );
			}
		} );
	} );

	/* Premium: one image at a time, oldest list first, until none are left or Stop is pressed. */
	var all = $( 'fwa-ai-all' );
	if ( all ) {
		var stopBtn = el( 'button', 'button', t.stop );
		stopBtn.type = 'button';
		stopBtn.hidden = true;
		all.parentNode.insertBefore( stopBtn, status );
		stopBtn.addEventListener( 'click', function () {
			if ( bulk ) {
				bulk.stop = true;
			}
		} );
		all.addEventListener( 'click', function () {
			bulk = { done: 0, stop: false };
			all.disabled = true;
			files.disabled = true;
			stopBtn.hidden = false;
			window.addEventListener( 'beforeunload', guard );
			next();
		} );
	}
	function guard( e ) {
		e.preventDefault();
		e.returnValue = '';
	}
	function finish( text ) {
		window.removeEventListener( 'beforeunload', guard );
		stopBtn.hidden = true;
		bulk = null;
		say( text );
		load( 1 );
	}
	function next() {
		if ( bulk.stop ) {
			return finish( fmt( t.bulkStop, bulk.done ) );
		}
		// The server hands out the next image Smart AI hasn't failed on in the last day.
		post( 'next', {} ).then( function ( j ) {
			if ( ! j || ! j.success ) {
				return finish( t.error );
			}
			counts( j.data.counts );
			if ( ! j.data.id ) {
				return finish( bulk.done === 1 ? t.bulkDone1 : fmt( t.bulkDone, bulk.done ) );
			}
			say( fmt( t.bulkRun, bulk.done, j.data.counts.missing ) );
			post( 'ai', { id: j.data.id } ).then( function ( r ) {
				var code = ( r && r.data && r.data.code ) || '';
				if ( r && r.success ) {
					bulk.done++;
				} else if ( [ 'no_key', 'invalid_premium_key', 'relay_not_configured', 'relay_key_invalid', 'unreachable' ].indexOf( code ) > -1 ) {
					return finish( ( r.data && r.data.message ) || t.error );
				}
				setTimeout( next, code === 'busy' ? 5000 : 150 );
			} );
		} );
	}

	load( 1 );
} )();
