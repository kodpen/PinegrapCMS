<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the look's switch in backend.src.css: the recipe switch
 * (.pg-switch) and Bootstrap's .switch in a settings card or the report
 * settings drawer share one drawing, a white 18 px thumb with its shadow on
 * the grey track or the accent gradient, with a focus ring and a disabled
 * state of their own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function look_switch_test_css()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
}

function test_look_switches_share_one_drawing()
{
	$css = look_switch_test_css();

	pg_assert_contains(".pg-set-card .switch,\n#report_settings .switch {\n  --bs-switch-height: 22px;", $css, 'one track for both Bootstrap switches');
	pg_assert_contains(".pg-set-card .switch::before,\n#report_settings .switch::before {", $css, 'one thumb for both');
	pg_assert_contains("dark on a checked row, and fades\n     it on a disabled one. */\n  background-color: #ffffff;\n  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);\n  opacity: 1;", $css, 'a white thumb with its shadow, never faded alone');
	pg_assert_contains(".pg-set-card .switch:has(input:checked),\n#report_settings .switch:has(input:checked) {\n  background-image: linear-gradient(180deg, var(--pg-accent-1), var(--pg-accent-2));", $css, 'the accent gradient when on');
	pg_assert_same(1, substr_count($css, '#report_settings .switch {'), 'the drawer has no copy of its own');
}

function test_look_switches_draw_focus_and_disabled()
{
	$css = look_switch_test_css();

	pg_assert_contains(".pg-set-card .switch:has(input:focus-visible):not(:has(.is-invalid, .is-valid)),\n#report_settings .switch:has(input:focus-visible):not(:has(.is-invalid, .is-valid)) {", $css, 'a keyboard focus ring');
	pg_assert_contains(".pg-set-card .switch input:focus-visible,\n#report_settings .switch input:focus-visible {\n  outline: 0;\n}", $css, 'no square outline on the input inside');
	pg_assert_contains("box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.25), 0 0 0 4px color-mix(in srgb, var(--pg-accent) 24%, transparent);", $css, 'the ring keeps the track shadow');
	pg_assert_contains(".pg-set-card .switch:has(input:disabled),\n#report_settings .switch:has(input:disabled) {\n  opacity: 0.5;", $css, 'a disabled switch is faded as a whole');
	pg_assert_contains(".pg-switch:disabled {\n  opacity: 0.5;\n  cursor: default;\n}", $css, 'the recipe switch too');
}
