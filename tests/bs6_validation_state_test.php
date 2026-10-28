<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the panel's invalid and valid field state on Bootstrap 6:
 * Bootstrap marks a field only through --bs-danger-border /
 * --bs-success-border, which the bridge fills with the pale -line tones, so
 * the panel draws the state with the accent itself and keeps it on focus.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// backend.src.css with the comments taken out and whitespace collapsed.
function bs6_validation_state_test_code()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	$css = preg_replace('~/\*.*?\*/~s', '', $css);
	return preg_replace('/\s+/', ' ', $css);
}

// The selectors of one state's rules, in the order the stylesheet lists them.
function bs6_validation_state_test_selectors($state)
{
	return array(
		'.form-control.is-' . $state,
		'.form-adorn:has(.form-ghost.is-' . $state . ')',
		'.chip-input:has(.form-ghost.is-' . $state . ')',
		':is(.check, .radio).is-' . $state,
		'.switch:has(input.is-' . $state . ')',
	);
}

function test_bs6_validation_state_rules()
{
	$css = bs6_validation_state_test_code();
	$states = array('invalid' => 'danger', 'valid' => 'success');

	foreach ($states as $state => $accent) {
		$selectors = bs6_validation_state_test_selectors($state);

		$rest = implode(', ', $selectors) . ' { border-color: var(--pg-' . $accent . '); }';
		pg_assert_true(strpos($css, $rest) !== false, $state . ' border at rest');

		$focus = array(
			$selectors[0] . ':focus',
			$selectors[0] . ':focus-visible',
			$selectors[1] . ':focus-within',
			$selectors[2] . ':focus-within',
			$selectors[3] . ':focus',
			$selectors[3] . ':focus-visible',
			$selectors[4] . ':focus-within',
		);
		$ring = implode(', ', $focus) . ' { border-color: var(--pg-' . $accent . '); outline-color: var(--pg-' . $accent . '); box-shadow: 0 0 0 var(--pg-focus-ring-width) color-mix(in srgb, var(--pg-' . $accent . ') 35%, transparent); }';
		pg_assert_true(strpos($css, $ring) !== false, $state . ' border and ring on focus');

		// After the panel's blue focus rule, which has the same weight.
		pg_assert_true(strpos($css, $rest) > strpos($css, '.form-control:focus, .form-control.form-adorn:focus-within'), $state . ' after the focus rule');
	}
}

function test_bs6_validation_state_dead_v5_names_gone()
{
	$css = bs6_validation_state_test_code();

	pg_assert_same(0, preg_match_all('/--bs-form-(in)?valid-/', $css), 'v6 reads no --bs-form-(in)valid-* names');
	// Alerts, badges and theme-* borders still read the pale bridge values.
	pg_assert_true(strpos($css, '--bs-danger-border: var(--pg-danger-line);') !== false, 'danger border bridge kept');
	pg_assert_true(strpos($css, '--bs-success-border: var(--pg-success-line);') !== false, 'success border bridge kept');
}
