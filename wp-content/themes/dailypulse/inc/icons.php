<?php
/**
 * Inline SVG icons. Inlining avoids an icon-font or sprite request, and the
 * icons inherit the text colour.
 *
 * Line icons are drawn on a 24px grid in the style of Feather/Lucide (MIT/ISC).
 *
 * @package DailyPulse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns an inline SVG icon.
 *
 * @param string $name  Icon name.
 * @param string $class Optional extra class.
 * @return string SVG markup, or an empty string for unknown icons.
 */
function dailypulse_icon( $name, $class = '' ) {
	static $line = array(
		'search'        => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
		'sun'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		'moon'          => '<path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5a8.5 8.5 0 1 0 10.7 10.7z"/>',
		'close'         => '<path d="M18 6 6 18M6 6l12 12"/>',
		'arrow-right'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'arrow-left'    => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
		'arrow-up'      => '<path d="M12 19V5M6 11l6-6 6 6"/>',
		'chevron-left'  => '<path d="m15 18-6-6 6-6"/>',
		'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
		'clock'         => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'link'          => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.8 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
		'external'      => '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'share'         => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
		'camera'        => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3.2"/>',
		'check'         => '<path d="M20 6 9 17l-5-5"/>',
		'rss'           => '<path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/>',
		'instagram'     => '<rect x="2.5" y="2.5" width="19" height="19" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
		'youtube'       => '<path d="M22 8.2a3 3 0 0 0-2.1-2.1C18.1 5.6 12 5.6 12 5.6s-6.1 0-7.9.5A3 3 0 0 0 2 8.2 31 31 0 0 0 1.5 12 31 31 0 0 0 2 15.8a3 3 0 0 0 2.1 2.1c1.8.5 7.9.5 7.9.5s6.1 0 7.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .5-3.8 31 31 0 0 0-.5-3.8z"/><path d="m10 15 5-3-5-3z"/>',
		'telegram'      => '<path d="m22 3-9.5 18-2.5-8-8-2.5z"/><path d="m22 3-12 10"/>',
		'tiktok'        => '<path d="M9.5 12.5a4 4 0 1 0 4 4V3a5.5 5.5 0 0 0 5.5 5.5"/>',
	);

	static $solid = array(
		'play'     => '<path d="M7 4.5v15a.8.8 0 0 0 1.2.7l12-7.5a.8.8 0 0 0 0-1.4l-12-7.5A.8.8 0 0 0 7 4.5z"/>',
		'pause'    => '<rect x="6" y="4" width="4.5" height="16" rx="1.2"/><rect x="13.5" y="4" width="4.5" height="16" rx="1.2"/>',
		'bolt'     => '<path d="M13.5 2 4 13.5h6.5L9.5 22 20 10h-6.8z"/>',
		'facebook' => '<path d="M13.5 22v-8.5h2.9l.5-3.5h-3.4V7.8c0-1 .3-1.8 1.8-1.8H17V2.9c-.3 0-1.4-.2-2.6-.2-2.6 0-4.4 1.6-4.4 4.5V10H7v3.5h3V22z"/>',
		'x'        => '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>',
		'whatsapp' => '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/>',
	);

	$class = trim( 'icon icon--' . $name . ' ' . $class );

	if ( isset( $line[ $name ] ) ) {
		return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $line[ $name ] . '</svg>';
	}

	if ( isset( $solid[ $name ] ) ) {
		return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false">' . $solid[ $name ] . '</svg>';
	}

	return '';
}

/**
 * The pulse line used in the logo and as a placeholder motif.
 *
 * @param string $class Optional class.
 * @return string SVG markup.
 */
function dailypulse_pulse_mark( $class = 'brand__mark' ) {
	return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 44 24" width="44" height="24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path pathLength="100" d="M2 13h8l3.5-9 6 17 4.5-12 2.5 4H42"/></svg>';
}
