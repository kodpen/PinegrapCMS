<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel's light transitions (pg_preloader_markup() and the Loading
 * curtain block of backend.src.css): no curtain on the way out, a delayed
 * thin bar held until the page goes, an arrival curtain that stays clear for
 * a fast screen, and the logo only on a screen that holds the curtain.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_transitions_test_css()
{
	$css = file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	$start = strpos($css, '/* ── Loading curtain');
	$end = strpos($css, '/* ══', $start);
	return substr($css, $start, $end - $start);
}

function test_panel_transitions_markup()
{
	$html = pg_preloader_markup();
	pg_assert_contains('id="pg-preloader"', $html, 'arrival curtain printed');
	pg_assert_contains('id="pg-progress"', $html, 'exit bar printed');
	pg_assert_contains("root.classList.add('pg-preloader-held')", $html, 'a held screen is marked before the first paint');
	// No exit curtain, no timer or interaction exits.
	pg_assert_true(strpos($html, 'setTimeout(hide, 6000)') === false, 'no 6 s exit timer');
	pg_assert_true(strpos($html, "'pointerdown', 'keydown', 'wheel'") === false, 'no interaction exits');
	pg_assert_true(strpos($html, 'pgPreloaderShow') === false, 'the curtain is not raised on the way out');
	// The bar waits 150 ms and is dropped when the click or submit was cancelled.
	pg_assert_contains('}, 150);', $html, 'bar delayed 150 ms');
	pg_assert_contains('defaultPrevented', $html, 'a cancelled click shows nothing');
	pg_assert_contains("getAttribute('formtarget')", $html, 'the submitter target is read');
	pg_assert_contains("navigation.addEventListener('navigate'", $html, 'script navigations are seen');
	pg_assert_contains("addEventListener('beforeunload'", $html, 'a cancelled leave prompt is seen');
}

function test_panel_transitions_css()
{
	$css = panel_transitions_test_css();
	pg_assert_true($css !== '', 'block found');
	// Arrival: clear for 120 ms, then a 100 ms fade.
	pg_assert_true((bool) preg_match('/\.pg-preloader \{[^}]*opacity: 0;[^}]*animation: pg-preloader-arrive \.1s ease \.12s forwards, pg-preloader-failsafe 0s linear 10s forwards;/', $css), 'arrival curtain clear for 120 ms');
	// The logo shows only on a held screen.
	pg_assert_true((bool) preg_match('/\.pg-preloader-logo \{[^}]*display: none;/', $css), 'logo hidden by default');
	pg_assert_contains('.pg-preloader-held .pg-preloader-logo', $css, 'logo on a held screen');
	// Reduced motion keeps the delays and drops only the motion.
	pg_assert_true((bool) preg_match('/prefers-reduced-motion[\s\S]*\.pg-preloader-mark \{ animation: pg-preloader-appear 0s linear \.3s forwards; \}/', $css), 'reduced motion keeps the mark delay');
	pg_assert_contains('.pg-progress.is-on', $css, 'exit bar');
	pg_assert_contains('cursor: progress', $css, 'waiting cursor');
}
