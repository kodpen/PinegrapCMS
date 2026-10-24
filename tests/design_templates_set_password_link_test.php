<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "New Account" block of the shipped design templates
 * (includes/design_templates/): the set password link is a button bound to
 * __new_account_set_password_url and shown only while the link is live; the
 * password note token is not printed as if it were a password.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every shipped template file, as the template loader reads it: id => array.
function _test_dtspl_templates()
{
	$out = array();
	$files = glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php');
	foreach ($files ? $files : array() as $file) {
		$out[basename($file, '.php')] = _pg_tpl_include($file);
	}
	return $out;
}

// The _bindings maps of every node in a tree.
function _test_dtspl_bindings($node)
{
	$found = array();
	if (!is_array($node)) {
		return $found;
	}
	if (isset($node['props']['_bindings']) && is_array($node['props']['_bindings'])) {
		$found[] = $node['props']['_bindings'];
	}
	foreach ($node as $child) {
		if (is_array($child)) {
			$found = array_merge($found, _test_dtspl_bindings($child));
		}
	}
	return $found;
}

// No template prints the password note token: it holds a sentence with a
// link, which the old "Temporary password" span showed in monospace.
function test_design_templates_set_password_link_no_password_token()
{
	foreach (_test_dtspl_templates() as $id => $tpl) {
		foreach (_test_dtspl_bindings($tpl) as $b) {
			pg_assert_true(!in_array('__new_account_password', $b, true), $id . ' binds __new_account_password');
		}
	}
}

// Every template that names a new account offers the set password link as an
// href, shown only while has_set_password_link holds.
function test_design_templates_set_password_link_bound_with_flag()
{
	$checked = 0;
	foreach (_test_dtspl_templates() as $id => $tpl) {
		$bindings = _test_dtspl_bindings($tpl);
		$names_account = false;
		$links = 0;
		foreach ($bindings as $b) {
			if (isset($b['text']) && $b['text'] === '__new_account_email') {
				$names_account = true;
			}
			if (isset($b['href']) && $b['href'] === '__new_account_set_password_url') {
				$links++;
				pg_assert_same('has_set_password_link', isset($b['eo_visible_if']) ? $b['eo_visible_if'] : '', $id . ' link visibility');
			}
		}
		if (!$names_account) {
			continue;
		}
		$checked++;
		pg_assert_true($links > 0, $id . ' has a set password link');
	}
	pg_assert_true($checked >= 8, 'templates with a new account block checked');
}
