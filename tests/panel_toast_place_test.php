<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for where pgToast() opens in backend.src.js: on the body under
 * the panel's top bar, below the bar and under its stacking level, so a
 * lasting error toast does not cover the bell, the account button or the
 * header menus they open; inside an open dialog at the top and above it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_toast_place_slice($start, $end)
{
	$js = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/backend.src.js');
	$from = strpos($js, $start);
	pg_assert_true($from !== false, 'found: ' . $start);
	$to = strpos($js, $end, (int) $from + strlen($start));
	pg_assert_true($to !== false, 'found: ' . $end);
	return (string) substr($js, (int) $from, (int) $to - (int) $from);
}

function test_panel_toast_opens_below_the_top_bar()
{
	$place = panel_toast_place_slice('function placeToastContainer(', 'window.pgToast = function');
	pg_assert_contains("c.parentNode === document.body", $place, 'the offset applies only on the body, not inside a dialog');
	pg_assert_contains("document.querySelector('.software-navbar.fixed-top')", $place, 'the offset applies only under the top bar');
	pg_assert_contains("'var(--pg-navbar-h)'", $place, 'the container starts under the bar');
	pg_assert_contains("'1025'", $place, 'under the bar (1030), above sticky bars (1020)');

	$toast = panel_toast_place_slice('window.pgToast = function', 'window.pgConfirm = function');
	pg_assert_true(strpos($toast, 'pgUI.hostLayer(container);') < strpos($toast, 'placeToastContainer(container);'), 'placed after hostLayer() picks the host');

	$ensure = panel_toast_place_slice('function ensureToastContainer(', 'window.pgToast = function');
	pg_assert_false(strpos($ensure, "c.style.zIndex = '1090';\n            c.setAttribute") !== false, 'the container no longer always sits above the bar');
}
