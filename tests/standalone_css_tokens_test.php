<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Stylesheets of pages that load Bootstrap 6 without backend.src.css (the
 * workspace guest and approval pages) carry their own --pg-* alias block.
 * Bootstrap defines no --pg-* name, so every name such a sheet reads must
 * be declared in that sheet, or the declaration turns to 'unset'.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function standalone_css_tokens_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_standalone_css_tokens_declared()
{
	foreach (array('assets/css/workspace_approval.css', 'assets/css/workspace_guest.css') as $path) {
		$source = standalone_css_tokens_source($path);

		pg_assert_contains(":root,\n[data-bs-theme] {", $source, $path);

		preg_match_all('/var\(\s*(--pg-[a-z0-9-]+)/', $source, $reads);
		preg_match_all('/(--pg-[a-z0-9-]+)\s*:/', $source, $declared);

		$missing = array_values(array_diff(array_unique($reads[1]), $declared[1]));
		pg_assert_same(array(), $missing, $path . ': undeclared --pg-* names');
	}
}

function test_standalone_css_tokens_approval_no_bs_reads()
{
	$source = standalone_css_tokens_source('assets/css/workspace_approval.css');

	// Only the alias block may name Bootstrap's variables.
	$rules = preg_replace('/:root,\s*\[data-bs-theme\]\s*\{[^}]*\}/', '', $source);
	pg_assert_true(strpos($rules, 'var(--bs-') === false, 'rule reads a --bs-* variable');
}
