<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the switch thumb on Bootstrap 6. v6's .switch::before
 * reads only --bs-switch-indicator-bg, so a checked switch sets that to the
 * dark on-accent ink (a white thumb on the light blue track read 2.2:1) and
 * the blue focus knob is kept for unchecked switches (on the blue track the
 * thumb vanished).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_bs6_switch_thumb_reads_on_accent_when_checked()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');

	pg_assert_contains(".switch:has(input:checked) {\n  --bs-switch-indicator-bg: var(--pg-on-accent);\n}", $css, 'checked thumb');
	pg_assert_contains(".switch:focus-within:not(:has(input:checked)) {\n  --bs-switch-indicator-bg: var(--pg-primary);\n}", $css, 'focus knob only while unchecked');
	pg_assert_same(false, strpos($css, '--bs-switch-checked-indicator-bg'), 'no dead v5 variable');
	pg_assert_same(false, strpos($css, ".switch:focus-within {\n"), 'no focus knob on a checked switch');
}
