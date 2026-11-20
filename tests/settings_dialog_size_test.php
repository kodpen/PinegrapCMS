<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the Site Settings dialog's desktop size: from 1024px up it
 * floats at most 1240px wide with at least 3rem of the screen visible on
 * each side (never full width), and at most 880px high within Bootstrap's
 * dialog margin, the dvh figure declared after the vh one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_dialog_size_css()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
}

function test_settings_dialog_width_leaves_the_screen_at_both_sides()
{
	$css = settings_dialog_size_css();
	pg_assert_contains("--bs-dialog-width: min(1240px, calc(100vw - 6rem));", $css);
}

function test_settings_dialog_height_vh_then_dvh()
{
	$css = settings_dialog_size_css();
	$vh = strpos($css, 'height: min(880px, calc(100vh - 3.5rem));');
	$dvh = strpos($css, 'height: min(880px, calc(100dvh - 3.5rem));');
	pg_assert_true($vh !== false, 'vh height');
	pg_assert_true($dvh !== false, 'dvh height');
	pg_assert_true($vh < $dvh, 'dvh after vh');
	pg_assert_true(strpos($css, '@supports (height: 100dvh)', (int) $vh) < $dvh, 'dvh inside @supports');
}
