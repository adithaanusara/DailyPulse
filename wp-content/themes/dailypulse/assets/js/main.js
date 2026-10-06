/**
 * DailyPulse — front-end behaviour.
 *
 * Plain JavaScript, loaded once with `defer`. Every feature looks for its own
 * markup and does nothing if it is not on the page, and every feature has a
 * working no-JavaScript baseline in the templates.
 */
( function () {
	'use strict';

	const doc = document;
	const root = doc.documentElement;
	const config = window.dailypulseConfig || {};
	const i18n = config.i18n || {};
	const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	const $ = ( selector, context = doc ) => context.querySelector( selector );
	const $$ = ( selector, context = doc ) => Array.from( context.querySelectorAll( selector ) );

	/** Runs a callback at most once per animation frame. */
	const onFrame = ( callback ) => {
		let queued = false;

		return () => {
			if ( queued ) {
				return;
			}
			queued = true;
			requestAnimationFrame( () => {
				queued = false;
				callback();
			} );
		};
	};

	/* ------------------------------------------------------------------
	 * Light / dark theme
	 * ---------------------------------------------------------------- */

	function initTheme() {
		const buttons = $$( '[data-theme-toggle]' );
		const themeColor = $( 'meta[name="theme-color"]' );

		const apply = ( theme ) => {
			root.dataset.theme = theme;

			if ( themeColor ) {
				themeColor.content = theme === 'dark' ? '#0f1115' : '#ffffff';
			}

			buttons.forEach( ( button ) => {
				button.setAttribute( 'aria-label', theme === 'dark' ? i18n.toLight : i18n.toDark );
			} );
		};

		const stored = () => {
			try {
				return localStorage.getItem( 'dailypulse-theme' );
			} catch ( error ) {
				return null;
			}
		};

		apply( root.dataset.theme === 'dark' ? 'dark' : 'light' );

		buttons.forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				const next = root.dataset.theme === 'dark' ? 'light' : 'dark';

				try {
					localStorage.setItem( 'dailypulse-theme', next );
				} catch ( error ) {
					// Private browsing: the choice simply lasts for this page.
				}

				if ( ! doc.startViewTransition || reducedMotion.matches ) {
					apply( next );
					return;
				}

				// Reveal the new theme as a circle growing from the button.
				const rect = button.getBoundingClientRect();
				const x = rect.left + rect.width / 2;
				const y = rect.top + rect.height / 2;
				const radius = Math.hypot( Math.max( x, innerWidth - x ), Math.max( y, innerHeight - y ) );

				root.classList.add( 'is-theme-switching' );

				const transition = doc.startViewTransition( () => apply( next ) );

				transition.ready
					.then( () => {
						root.animate(
							{ clipPath: [ `circle(0px at ${ x }px ${ y }px)`, `circle(${ radius }px at ${ x }px ${ y }px)` ] },
							{ duration: 600, easing: 'cubic-bezier(0.4, 0, 0.2, 1)', pseudoElement: '::view-transition-new(root)' }
						);
					} )
					.catch( () => {} );

				transition.finished.finally( () => root.classList.remove( 'is-theme-switching' ) );
			} );
		} );

		// Keep following the device until the visitor makes a choice of their own.
		window.matchMedia( '(prefers-color-scheme: dark)' ).addEventListener( 'change', ( event ) => {
			if ( ! stored() && config.scheme === 'auto' ) {
				apply( event.matches ? 'dark' : 'light' );
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * Header: hides while reading down, returns on the way up
	 * ---------------------------------------------------------------- */

	function initHeader() {
		const header = $( '[data-header]' );

		if ( ! header ) {
			return;
		}

		let last = window.scrollY;

		const update = () => {
			const y = window.scrollY;

			header.classList.toggle( 'is-scrolled', y > 8 );

			if ( Math.abs( y - last ) > 6 ) {
				header.classList.toggle( 'is-hidden', y > last && y > 320 && ! header.contains( doc.activeElement ) );
				last = y;
			}
		};

		window.addEventListener( 'scroll', onFrame( update ), { passive: true } );
		header.addEventListener( 'focusin', () => header.classList.remove( 'is-hidden' ) );
		update();
	}

	/* ------------------------------------------------------------------
	 * Relative times ("12 minutes ago"), kept current on cached pages
	 * ---------------------------------------------------------------- */

	let relativeFormat = null;

	function refreshTimes( context = doc ) {
		if ( ! config.relativeDates || ! window.Intl || ! Intl.RelativeTimeFormat ) {
			return;
		}

		if ( ! relativeFormat ) {
			try {
				relativeFormat = new Intl.RelativeTimeFormat( config.locale || undefined, { numeric: 'always' } );
			} catch ( error ) {
				relativeFormat = new Intl.RelativeTimeFormat( undefined, { numeric: 'always' } );
			}
		}

		$$( 'time[data-ago]', context ).forEach( ( el ) => {
			const then = Date.parse( el.dateTime );

			if ( Number.isNaN( then ) ) {
				return;
			}

			const seconds = ( Date.now() - then ) / 1000;
			let text;

			if ( seconds < 0 || seconds >= 86400 ) {
				text = el.dataset.date;
			} else if ( seconds < 60 ) {
				text = i18n.justNow;
			} else if ( seconds < 3600 ) {
				text = relativeFormat.format( -Math.floor( seconds / 60 ), 'minute' );
			} else {
				text = relativeFormat.format( -Math.floor( seconds / 3600 ), 'hour' );
			}

			if ( text && el.textContent !== text ) {
				el.textContent = text;
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * Story feed endpoint (filter tabs, Load more, live search)
	 * ---------------------------------------------------------------- */

	function fetchFeed( params, signal ) {
		const url = new URL( config.feedUrl, window.location.href );

		Object.keys( params ).forEach( ( key ) => {
			if ( params[ key ] !== '' && params[ key ] !== undefined && params[ key ] !== null ) {
				url.searchParams.set( key, params[ key ] );
			}
		} );

		return fetch( url, { signal, headers: { Accept: 'application/json' } } ).then( ( response ) => {
			if ( ! response.ok ) {
				throw new Error( 'Feed request failed: ' + response.status );
			}
			return response.json();
		} );
	}

	/* ------------------------------------------------------------------
	 * Ticker
	 * ---------------------------------------------------------------- */

	function initTicker() {
		const ticker = $( '[data-ticker]' );

		if ( ! ticker ) {
			return;
		}

		const items = $$( '.ticker__item', ticker );

		if ( items.length < 2 ) {
			return;
		}

		const toggle = $( '[data-ticker-toggle]', ticker );
		const interval = Math.max( 3, parseInt( ticker.dataset.interval, 10 ) || 5 ) * 1000;
		let index = 0;
		let timer = null;
		let userPaused = false;

		const go = ( next ) => {
			const from = items[ index ];

			index = ( next + items.length ) % items.length;

			const to = items[ index ];

			if ( from === to ) {
				return;
			}

			from.classList.remove( 'is-active' );
			from.classList.add( 'is-leaving' );
			window.setTimeout( () => from.classList.remove( 'is-leaving' ), 600 );
			to.classList.remove( 'is-leaving' );
			to.classList.add( 'is-active' );
		};

		const stop = () => {
			window.clearInterval( timer );
			timer = null;
		};

		const start = () => {
			stop();

			if ( ! userPaused && ! doc.hidden ) {
				timer = window.setInterval( () => go( index + 1 ), interval );
			}
		};

		const step = ( direction ) => {
			go( index + direction );
			start();
		};

		$( '[data-ticker-prev]', ticker )?.addEventListener( 'click', () => step( -1 ) );
		$( '[data-ticker-next]', ticker )?.addEventListener( 'click', () => step( 1 ) );

		toggle?.addEventListener( 'click', () => {
			userPaused = ! userPaused;
			ticker.classList.toggle( 'is-user-paused', userPaused );
			toggle.setAttribute( 'aria-label', userPaused ? i18n.play : i18n.pause );
			start();
		} );

		// Nobody can read a headline that changes under their pointer.
		ticker.addEventListener( 'mouseenter', stop );
		ticker.addEventListener( 'mouseleave', start );
		ticker.addEventListener( 'focusin', stop );
		ticker.addEventListener( 'focusout', start );
		doc.addEventListener( 'visibilitychange', start );

		start();
	}

	/* ------------------------------------------------------------------
	 * Hero slider
	 * ---------------------------------------------------------------- */

	function initHero() {
		const hero = $( '[data-hero]' );

		if ( ! hero ) {
			return;
		}

		const slides = $$( '[data-hero-slide]', hero );

		if ( slides.length < 2 ) {
			return;
		}

		const stage = $( '.hero__stage', hero );
		const dots = $$( '[data-hero-go]', hero );
		const rail = $$( '[data-hero-rail]', hero );
		const counter = $( '[data-hero-count]', hero );
		const toggle = $( '[data-hero-toggle]', hero );
		const autoplay = hero.dataset.autoplay === '1' && ! reducedMotion.matches;
		const pauses = new Set();
		let index = 0;

		const setPaused = ( reason, paused ) => {
			pauses[ paused ? 'add' : 'delete' ]( reason );
			hero.classList.toggle( 'is-paused', pauses.size > 0 );
		};

		const show = ( next ) => {
			index = ( next + slides.length ) % slides.length;

			slides.forEach( ( slide, i ) => {
				slide.classList.toggle( 'is-active', i === index );
				slide.toggleAttribute( 'inert', i !== index );
			} );

			dots.forEach( ( dot, i ) => {
				dot.classList.toggle( 'is-active', i === index );
				dot.classList.toggle( 'is-done', i < index );
				dot.setAttribute( 'aria-current', i === index ? 'true' : 'false' );
			} );

			rail.forEach( ( item, i ) => item.classList.toggle( 'is-active', i === index ) );

			if ( counter ) {
				counter.textContent = String( index + 1 ).padStart( 2, '0' );
			}
		};

		if ( autoplay ) {
			// The progress bar is the clock: when its animation ends, move on.
			hero.addEventListener( 'animationend', ( event ) => {
				if ( event.animationName === 'dp-hero-progress' ) {
					show( index + 1 );
				}
			} );

			[ stage, $( '.hero__rail', hero ) ].forEach( ( area ) => {
				if ( ! area ) {
					return;
				}
				area.addEventListener( 'mouseenter', () => setPaused( 'hover', true ) );
				area.addEventListener( 'mouseleave', () => setPaused( 'hover', false ) );
			} );

			hero.addEventListener( 'focusin', () => setPaused( 'focus', true ) );
			hero.addEventListener( 'focusout', () => setPaused( 'focus', false ) );
			doc.addEventListener( 'visibilitychange', () => setPaused( 'hidden', doc.hidden ) );

			toggle?.addEventListener( 'click', () => {
				const paused = ! hero.classList.contains( 'is-user-paused' );

				hero.classList.toggle( 'is-user-paused', paused );
				toggle.setAttribute( 'aria-label', paused ? i18n.play : i18n.pause );
				setPaused( 'user', paused );
			} );
		} else {
			hero.classList.add( 'is-static' );
			toggle?.remove();
		}

		$( '[data-hero-prev]', hero )?.addEventListener( 'click', () => show( index - 1 ) );
		$( '[data-hero-next]', hero )?.addEventListener( 'click', () => show( index + 1 ) );
		dots.forEach( ( dot, i ) => dot.addEventListener( 'click', () => show( i ) ) );

		// Pointing at (or tabbing to) a headline in the rail previews its slide.
		rail.forEach( ( item, i ) => {
			item.addEventListener( 'pointerenter', ( event ) => {
				if ( event.pointerType === 'mouse' && i !== index ) {
					show( i );
				}
			} );
			item.addEventListener( 'focusin', () => {
				if ( i !== index ) {
					show( i );
				}
			} );
		} );

		stage.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'ArrowLeft' || event.key === 'ArrowRight' ) {
				event.preventDefault();
				show( index + ( event.key === 'ArrowRight' ? 1 : -1 ) );
			}
		} );

		// Swipe.
		let startX = 0;
		let startY = 0;

		stage.addEventListener(
			'touchstart',
			( event ) => {
				startX = event.changedTouches[ 0 ].clientX;
				startY = event.changedTouches[ 0 ].clientY;
			},
			{ passive: true }
		);

		stage.addEventListener(
			'touchend',
			( event ) => {
				const dx = event.changedTouches[ 0 ].clientX - startX;
				const dy = event.changedTouches[ 0 ].clientY - startY;

				if ( Math.abs( dx ) > 44 && Math.abs( dx ) > Math.abs( dy ) * 1.4 ) {
					show( index + ( dx < 0 ? 1 : -1 ) );
				}
			},
			{ passive: true }
		);
	}

	/* ------------------------------------------------------------------
	 * Latest feed: filter tabs and Load more
	 * ---------------------------------------------------------------- */

	function initFeed() {
		const feed = $( '[data-feed]' );

		if ( ! feed || ! config.feedUrl || ! window.fetch ) {
			return;
		}

		const grid = $( '[data-feed-grid]', feed );
		const tabList = $( '.tabs', feed );
		const tabs = $$( '[data-feed-tab]', feed );
		const more = $( '[data-feed-more]', feed );
		const status = $( '[data-feed-status]', feed );
		const perPage = feed.dataset.perPage;
		const exclude = feed.dataset.exclude || '';
		const cache = new Map();
		let state = { cat: '0', page: 1, pages: parseInt( feed.dataset.pages, 10 ) || 1 };
		let sequence = 0;

		// What the server rendered is the first cache entry, so "All" never needs a request.
		cache.set( '0:1', Promise.resolve( { html: grid.innerHTML, pages: state.pages, total: grid.children.length } ) );

		const request = ( cat, page ) => {
			const key = cat + ':' + page;

			if ( ! cache.has( key ) ) {
				cache.set(
					key,
					fetchFeed( { category: cat, page, per_page: perPage, exclude: cat === '0' ? exclude : '' } ).catch( ( error ) => {
						cache.delete( key );
						throw error;
					} )
				);
			}

			return cache.get( key );
		};

		const message = ( text ) => {
			const p = doc.createElement( 'p' );
			p.className = 'feed__message';
			p.textContent = text;
			return p;
		};

		const announce = ( text ) => {
			if ( status ) {
				status.textContent = text;
			}
		};

		const updateMore = () => {
			if ( more ) {
				more.parentElement.hidden = state.page >= state.pages;
			}
		};

		const moveIndicator = ( tab ) => {
			if ( tabList && tab ) {
				tabList.style.setProperty( '--x', tab.offsetLeft + 'px' );
				tabList.style.setProperty( '--w', tab.offsetWidth + 'px' );
			}
		};

		const activeTab = () => tabs.find( ( tab ) => tab.getAttribute( 'aria-current' ) === 'true' );

		const select = ( tab ) => {
			const cat = tab.dataset.cat;

			if ( cat === state.cat ) {
				return;
			}

			tabs.forEach( ( other ) => {
				if ( other === tab ) {
					other.setAttribute( 'aria-current', 'true' );
				} else {
					other.removeAttribute( 'aria-current' );
				}
			} );
			moveIndicator( tab );

			const token = ++sequence;
			// Only dim the grid if the answer is not already there, to avoid a flicker.
			const busy = window.setTimeout( () => grid.classList.add( 'is-loading' ), 90 );

			state = { cat, page: 1, pages: 1 };
			grid.setAttribute( 'aria-busy', 'true' );

			request( cat, 1 )
				.then( ( data ) => {
					if ( token !== sequence ) {
						return;
					}

					state.pages = data.pages || 1;
					grid.innerHTML = data.html || '';

					if ( ! grid.children.length ) {
						grid.append( message( i18n.noStories ) );
					}

					refreshTimes( grid );
					announce( ( i18n.updated || '' ).replace( '%s', data.total ) + ' — ' + tab.dataset.label );
				} )
				.catch( () => {
					if ( token === sequence ) {
						grid.replaceChildren( message( i18n.loadError ) );
						announce( i18n.loadError );
					}
				} )
				.finally( () => {
					window.clearTimeout( busy );

					if ( token === sequence ) {
						grid.classList.remove( 'is-loading' );
						grid.setAttribute( 'aria-busy', 'false' );
						updateMore();
					}
				} );
		};

		tabs.forEach( ( tab ) => {
			tab.addEventListener( 'click', ( event ) => {
				// Let modified clicks open the category page in a new tab as usual.
				if ( event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0 ) {
					return;
				}
				event.preventDefault();
				select( tab );
			} );

			// Start fetching as soon as the intent is visible, so the click feels instant.
			const warm = () => request( tab.dataset.cat, 1 ).catch( () => {} );
			tab.addEventListener( 'pointerenter', warm );
			tab.addEventListener( 'focus', warm );
			tab.addEventListener( 'touchstart', warm, { passive: true } );
		} );

		if ( tabList ) {
			moveIndicator( activeTab() );
			tabList.classList.add( 'is-ready' );
			// Animate only after the pill has been placed, so it does not slide in from the corner.
			requestAnimationFrame( () => requestAnimationFrame( () => tabList.classList.add( 'is-animated' ) ) );
			window.addEventListener( 'resize', onFrame( () => moveIndicator( activeTab() ) ) );
			doc.fonts?.ready.then( () => moveIndicator( activeTab() ) );
		}

		more?.addEventListener( 'click', ( event ) => {
			event.preventDefault();

			if ( more.classList.contains( 'is-loading' ) ) {
				return;
			}

			const token = sequence;
			const next = state.page + 1;

			more.classList.add( 'is-loading' );

			request( state.cat, next )
				.then( ( data ) => {
					if ( token !== sequence ) {
						return;
					}

					const before = grid.children.length;

					grid.insertAdjacentHTML( 'beforeend', data.html || '' );
					state.page = next;
					state.pages = data.pages || next;
					refreshTimes( grid );
					announce( ( i18n.updated || '' ).replace( '%s', grid.children.length ) );

					// Hand keyboard focus to the first new story instead of leaving it on a button that may vanish.
					const first = grid.children[ before ];
					first?.querySelector( '.card__link' )?.focus( { preventScroll: true } );
				} )
				.catch( () => announce( i18n.loadError ) )
				.finally( () => {
					more.classList.remove( 'is-loading' );
					updateMore();
				} );
		} );
	}

	/* ------------------------------------------------------------------
	 * Search dialog with live results
	 * ---------------------------------------------------------------- */

	function initSearch() {
		const dialog = $( '[data-search]' );

		if ( ! dialog || typeof dialog.showModal !== 'function' ) {
			return;
		}

		const input = $( 'input[type="search"]', dialog );
		const results = $( '[data-search-results]', dialog );
		let timer = null;
		let controller = null;

		const open = () => {
			if ( ! dialog.open ) {
				dialog.showModal();
				input.select();
			}
		};

		$$( '[data-search-open]' ).forEach( ( trigger ) => {
			trigger.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				open();
			} );
		} );

		$( '[data-search-close]', dialog )?.addEventListener( 'click', () => dialog.close() );

		// A click on the backdrop lands on the dialog element itself.
		dialog.addEventListener( 'click', ( event ) => {
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );

		doc.addEventListener( 'keydown', ( event ) => {
			const el = doc.activeElement;
			const typing = el && ( /^(INPUT|TEXTAREA|SELECT)$/.test( el.tagName ) || el.isContentEditable );
			const slash = event.key === '/' && ! typing && ! event.metaKey && ! event.ctrlKey && ! event.altKey;
			const commandK = event.key.toLowerCase() === 'k' && ( event.metaKey || event.ctrlKey );

			if ( slash || commandK ) {
				event.preventDefault();
				open();
			}
		} );

		if ( ! config.feedUrl || ! window.fetch ) {
			return;
		}

		const note = ( text ) => {
			const p = doc.createElement( 'p' );
			p.className = 'search-dialog__message';
			p.textContent = text;
			results.replaceChildren( p );
		};

		input.addEventListener( 'input', () => {
			const query = input.value.trim();

			window.clearTimeout( timer );
			controller?.abort();

			if ( query.length < 2 ) {
				results.replaceChildren();
				return;
			}

			timer = window.setTimeout( () => {
				controller = new AbortController();

				fetchFeed( { search: query, per_page: 5, layout: 'row' }, controller.signal )
					.then( ( data ) => {
						if ( data.html ) {
							results.innerHTML = data.html;
							refreshTimes( results );
						} else {
							note( i18n.noResults );
						}
					} )
					.catch( ( error ) => {
						if ( error.name !== 'AbortError' ) {
							note( i18n.loadError );
						}
					} );
			}, 220 );
		} );
	}

	/* ------------------------------------------------------------------
	 * Sideways scroller (dark band)
	 * ---------------------------------------------------------------- */

	function initScrollers() {
		$$( '[data-scroller]' ).forEach( ( scroller ) => {
			const track = $( '[data-scroller-track]', scroller );
			const prev = $( '[data-scroller-prev]', scroller );
			const next = $( '[data-scroller-next]', scroller );

			if ( ! track || ! prev || ! next ) {
				return;
			}

			const update = () => {
				const max = track.scrollWidth - track.clientWidth - 2;

				prev.disabled = track.scrollLeft <= 2;
				next.disabled = track.scrollLeft >= max;
			};

			const move = ( direction ) => {
				track.scrollBy( { left: direction * track.clientWidth * 0.8, behavior: reducedMotion.matches ? 'auto' : 'smooth' } );
			};

			prev.addEventListener( 'click', () => move( -1 ) );
			next.addEventListener( 'click', () => move( 1 ) );
			track.addEventListener( 'scroll', onFrame( update ), { passive: true } );
			window.addEventListener( 'resize', onFrame( update ) );
			update();
		} );
	}

	/* ------------------------------------------------------------------
	 * Scroll reveal
	 * ---------------------------------------------------------------- */

	function initReveal() {
		const items = $$( '[data-reveal]' );

		if ( ! items.length || ! ( 'IntersectionObserver' in window ) || reducedMotion.matches ) {
			return;
		}

		const viewport = window.innerHeight;
		const waiting = [];

		// Whatever is already on screen is shown as it is; only the rest gets the entrance.
		items.forEach( ( item ) => {
			if ( item.getBoundingClientRect().top < viewport * 0.95 ) {
				item.classList.add( 'is-in' );
			} else {
				waiting.push( item );
			}
		} );

		root.classList.add( 'reveal-ready' );

		const observer = new IntersectionObserver(
			( entries ) => {
				entries.forEach( ( entry ) => {
					if ( entry.isIntersecting ) {
						entry.target.classList.add( 'is-in' );
						observer.unobserve( entry.target );
					}
				} );
			},
			{ rootMargin: '0px 0px -8% 0px' }
		);

		waiting.forEach( ( item ) => observer.observe( item ) );
	}

	/* ------------------------------------------------------------------
	 * Reading progress and back-to-top
	 * ---------------------------------------------------------------- */

	function initProgress() {
		const bar = $( '[data-progress]' );
		const body = $( '.story__body' );
		const toTop = $( '[data-to-top]' );

		if ( ! bar && ! toTop ) {
			return;
		}

		const update = () => {
			const y = window.scrollY;
			const scrollable = root.scrollHeight - window.innerHeight;

			if ( toTop ) {
				toTop.classList.toggle( 'is-visible', y > 700 );
				toTop.style.setProperty( '--p', scrollable > 0 ? Math.min( 1, y / scrollable ).toFixed( 3 ) : 0 );
			}

			if ( bar && body ) {
				// Progress through the article text itself, not the whole page.
				const rect = body.getBoundingClientRect();
				const start = window.innerHeight * 0.3;
				const total = rect.height;
				const done = Math.min( 1, Math.max( 0, ( start - rect.top ) / total ) );

				bar.style.setProperty( '--p', done.toFixed( 3 ) );
			}
		};

		window.addEventListener( 'scroll', onFrame( update ), { passive: true } );
		window.addEventListener( 'resize', onFrame( update ) );
		update();

		toTop?.addEventListener( 'click', () => {
			window.scrollTo( { top: 0, behavior: reducedMotion.matches ? 'auto' : 'smooth' } );
			$( '.skip-link' )?.focus( { preventScroll: true } );
		} );
	}

	/* ------------------------------------------------------------------
	 * Share buttons
	 * ---------------------------------------------------------------- */

	function initShare() {
		$$( '[data-copy-link]' ).forEach( ( button ) => {
			const label = button.getAttribute( 'aria-label' );

			button.addEventListener( 'click', () => {
				const done = () => {
					button.classList.add( 'is-done' );
					button.setAttribute( 'aria-label', i18n.copied || label );
					window.setTimeout( () => {
						button.classList.remove( 'is-done' );
						button.setAttribute( 'aria-label', label );
					}, 2200 );
				};

				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( button.dataset.copyLink ).then( done ).catch( () => {} );
				}
			} );

			// Without clipboard access (plain http on some browsers) the button cannot work.
			if ( ! navigator.clipboard ) {
				button.closest( 'li' ).hidden = true;
			}
		} );

		$$( '[data-native-share]' ).forEach( ( button ) => {
			if ( ! navigator.share ) {
				return;
			}

			button.closest( 'li' ).hidden = false;
			button.addEventListener( 'click', () => {
				navigator.share( { title: button.dataset.title, url: button.dataset.url } ).catch( () => {} );
			} );
		} );
	}

	/* ------------------------------------------------------------------
	 * Gallery lightbox
	 * ---------------------------------------------------------------- */

	function initGallery() {
		const dialog = $( '[data-lightbox]' );
		const items = $$( '[data-gallery-item]' );

		if ( ! dialog || ! items.length || typeof dialog.showModal !== 'function' ) {
			return;
		}

		const image = $( '.lightbox__img', dialog );
		const caption = $( '.lightbox__caption', dialog );
		const count = $( '[data-lightbox-count]', dialog );
		let index = 0;

		const show = ( next ) => {
			index = ( next + items.length ) % items.length;

			const item = items[ index ];

			// Re-trigger the entrance animation for each photo.
			image.style.animation = 'none';
			void image.offsetWidth;
			image.style.animation = '';

			image.src = item.href;
			image.alt = item.dataset.alt || '';
			caption.textContent = item.dataset.caption || '';
			caption.hidden = ! item.dataset.caption;
			count.textContent = index + 1 + ' / ' + items.length;

			// Fetch the neighbour so the next arrow press is instant.
			if ( items.length > 1 ) {
				new Image().src = items[ ( index + 1 ) % items.length ].href;
			}
		};

		items.forEach( ( item, i ) => {
			item.addEventListener( 'click', ( event ) => {
				if ( event.metaKey || event.ctrlKey || event.shiftKey ) {
					return;
				}
				event.preventDefault();
				show( i );
				dialog.showModal();
			} );
		} );

		$( '[data-lightbox-close]', dialog )?.addEventListener( 'click', () => dialog.close() );
		$( '[data-lightbox-prev]', dialog )?.addEventListener( 'click', () => show( index - 1 ) );
		$( '[data-lightbox-next]', dialog )?.addEventListener( 'click', () => show( index + 1 ) );

		dialog.addEventListener( 'click', ( event ) => {
			if ( event.target === dialog || event.target.classList.contains( 'lightbox__figure' ) ) {
				dialog.close();
			}
		} );

		dialog.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'ArrowLeft' ) {
				show( index - 1 );
			} else if ( event.key === 'ArrowRight' ) {
				show( index + 1 );
			}
		} );

		// Return focus to the thumbnail that is now showing, not the one first opened.
		dialog.addEventListener( 'close', () => items[ index ]?.focus() );

		let startX = 0;

		dialog.addEventListener( 'touchstart', ( event ) => ( startX = event.changedTouches[ 0 ].clientX ), { passive: true } );
		dialog.addEventListener(
			'touchend',
			( event ) => {
				const dx = event.changedTouches[ 0 ].clientX - startX;

				if ( Math.abs( dx ) > 50 ) {
					show( index + ( dx < 0 ? 1 : -1 ) );
				}
			},
			{ passive: true }
		);
	}

	/* ------------------------------------------------------------------
	 * Card → story image transition
	 * ---------------------------------------------------------------- */

	function initStoryTransitions() {
		if ( ! ( 'CSSViewTransitionRule' in window ) || reducedMotion.matches ) {
			return;
		}

		const clear = () => {
			$$( '[data-vt]' ).forEach( ( el ) => {
				el.style.viewTransitionName = '';
				el.removeAttribute( 'data-vt' );
			} );
		};

		// Name only the clicked card's image, so it alone grows into the story's lead image.
		doc.addEventListener( 'click', ( event ) => {
			const link = event.target.closest && event.target.closest( '.card__link, .hero__title a' );
			const image = link && link.closest( '.card, .hero__slide' )?.querySelector( '.card__media img, .hero__media img' );

			if ( image ) {
				clear();
				image.style.viewTransitionName = 'story-media';
				image.setAttribute( 'data-vt', '' );
			}
		} );

		// Coming back (including from the back/forward cache), let it shrink home, then forget it.
		window.addEventListener( 'pagereveal', ( event ) => {
			if ( event.viewTransition ) {
				event.viewTransition.finished.finally( clear );
			} else {
				clear();
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * Start
	 * ---------------------------------------------------------------- */

	const features = [
		initTheme,
		initHeader,
		initTicker,
		initHero,
		initFeed,
		initSearch,
		initScrollers,
		initReveal,
		initProgress,
		initShare,
		initGallery,
		initStoryTransitions,
	];

	// One feature failing must never take the others down with it.
	features.forEach( ( feature ) => {
		try {
			feature();
		} catch ( error ) {
			if ( window.console ) {
				console.error( error );
			}
		}
	} );

	refreshTimes();
	window.setInterval( refreshTimes, 60000 );
}() );
