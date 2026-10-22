<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account e-mail pages of the shipped design templates
 * (includes/design_templates/): every template carries a welcome and a
 * password reset e-mail page drawn by an Account E-mail widget, and its
 * registration and forgot password widgets name them.
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
function _test_dtae_templates()
{
	$out = array();
	$files = glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php');
	foreach ($files ? $files : array() as $file) {
		$out[basename($file, '.php')] = _pg_tpl_include($file);
	}
	return $out;
}

// The bindings of $attr ('text', 'href') found anywhere in a tree.
function _test_dtae_bindings($node, $attr)
{
	$found = array();
	if (!is_array($node)) {
		return $found;
	}
	if (isset($node['props']['_bindings'][$attr]) && is_string($node['props']['_bindings'][$attr])) {
		$found[] = $node['props']['_bindings'][$attr];
	}
	foreach ($node as $child) {
		if (is_array($child)) {
			$found = array_merge($found, _test_dtae_bindings($child, $attr));
		}
	}
	return $found;
}

// The template page with $key, null when there is none.
function _test_dtae_page($tpl, $key)
{
	foreach ((isset($tpl['pages']) && is_array($tpl['pages'])) ? $tpl['pages'] : array() as $p) {
		if (isset($p['key']) && $p['key'] === $key) {
			return $p;
		}
	}
	return null;
}

// The widget of $type placed on the template page $page_key, null when none.
function _test_dtae_widget_on($tpl, $page_key, $type)
{
	foreach ((isset($tpl['widgets']) && is_array($tpl['widgets'])) ? $tpl['widgets'] : array() as $key => $w) {
		$t = isset($w['config']['regionType']) ? $w['config']['regionType'] : '';
		if ($t === $type && isset($w['page']) && $w['page'] === $page_key) {
			return array('key' => $key) + $w;
		}
	}
	return null;
}

// The seven built-in templates are all found.
function test_design_templates_account_emails_templates_found()
{
	$tpls = _test_dtae_templates();
	foreach (array('hello-pinegrap', 'pinegrap-bookshop', 'pinegrap-boutique', 'pinegrap-cafe', 'pinegrap-clinic', 'pinegrap-playground', 'pinegrap-store') as $id) {
		pg_assert_true(isset($tpls[$id]) && is_array($tpls[$id]), $id . ' loaded');
	}
}

// Both pages exist as private, unlisted e-mail pages, each placing its
// Account E-mail widget, whose tree is laid out (not the starter).
function test_design_templates_account_emails_pages_exist()
{
	foreach (_test_dtae_templates() as $id => $tpl) {
		foreach (array('email_account_welcome', 'email_password_reset') as $key) {
			$page = _test_dtae_page($tpl, $key);
			pg_assert_true(is_array($page), $id . ' ' . $key . ' page');
			if (!is_array($page)) {
				continue;
			}
			pg_assert_same('private', isset($page['folder']) ? $page['folder'] : '', $id . ' ' . $key . ' folder');
			pg_assert_same(false, isset($page['search']) ? $page['search'] : null, $id . ' ' . $key . ' search');
			pg_assert_same(false, isset($page['sitemap']) ? $page['sitemap'] : null, $id . ' ' . $key . ' sitemap');
			pg_assert_same(true, isset($page['noindex']) ? $page['noindex'] : null, $id . ' ' . $key . ' noindex');

			$w = _test_dtae_widget_on($tpl, $key, 'account_email');
			pg_assert_true(is_array($w), $id . ' ' . $key . ' account_email widget');
			if (!is_array($w)) {
				continue;
			}
			pg_assert_true(is_array($w['tree']), $id . ' ' . $key . ' explicit tree');
			pg_assert_contains('"templateWidget":"' . $w['key'] . '"', json_encode($page['tree']), $id . ' ' . $key . ' placed');
		}
	}
}

// The registration widget names the welcome page and the forgot password
// widget the reset page, each with a subject.
function test_design_templates_account_emails_widgets_wired()
{
	foreach (_test_dtae_templates() as $id => $tpl) {
		$checked = 0;
		foreach ($tpl['widgets'] as $w) {
			$cfg = isset($w['config']) ? $w['config'] : array();
			$type = isset($cfg['regionType']) ? $cfg['regionType'] : '';
			if ($type === 'registration' || $type === 'membership') {
				pg_assert_same('{{tab:email_account_welcome}}', isset($cfg['welcome_email_page_id']) ? $cfg['welcome_email_page_id'] : '', $id . ' ' . $type . ' page');
				pg_assert_true(isset($cfg['welcome_email_subject']) && trim((string)$cfg['welcome_email_subject']) !== '', $id . ' ' . $type . ' subject');
				$checked++;
			} elseif ($type === 'forgot_password') {
				pg_assert_same('{{tab:email_password_reset}}', isset($cfg['reset_email_page_id']) ? $cfg['reset_email_page_id'] : '', $id . ' forgot_password page');
				pg_assert_true(isset($cfg['reset_email_subject']) && trim((string)$cfg['reset_email_subject']) !== '', $id . ' forgot_password subject');
				$checked++;
			}
		}
		pg_assert_true($checked >= 2, $id . ' registration and forgot password widgets found');
	}
}

// The reset e-mail's tree links to the set password address (a body without
// it is not sent) and greets the member; the welcome greets the member and
// links to the account. No visibility flag: the installer's preview drops
// unknown flags.
function test_design_templates_account_emails_trees_bound()
{
	foreach (_test_dtae_templates() as $id => $tpl) {
		$welcome = _test_dtae_widget_on($tpl, 'email_account_welcome', 'account_email');
		$reset = _test_dtae_widget_on($tpl, 'email_password_reset', 'account_email');
		if (!is_array($welcome) || !is_array($reset)) {
			pg_assert_true(false, $id . ' widgets');
			continue;
		}

		pg_assert_true(in_array('__set_password_url', _test_dtae_bindings($reset['tree'], 'href'), true), $id . ' reset link bound');
		pg_assert_true(in_array('__user_first_name', _test_dtae_bindings($reset['tree'], 'text'), true), $id . ' reset greeting');
		pg_assert_true(in_array('__user_first_name', _test_dtae_bindings($welcome['tree'], 'text'), true), $id . ' welcome greeting');
		pg_assert_same(array(), array_diff(_test_dtae_bindings($welcome['tree'], 'href'), array('__my_account_url', '__login_url')), $id . ' welcome links');
		pg_assert_true(count(_test_dtae_bindings($welcome['tree'], 'href')) > 0, $id . ' welcome button bound');
		pg_assert_same(array(), _test_dtae_bindings(array($welcome['tree'], $reset['tree']), 'eo_visible_if'), $id . ' no flags');
	}
}

// The inline style values found anywhere in a tree.
function _test_dtae_styles($node)
{
	$found = array();
	if (!is_array($node)) {
		return $found;
	}
	if (isset($node['name'], $node['value']) && $node['name'] === 'style' && is_string($node['value'])) {
		$found[] = $node['value'];
	}
	foreach ($node as $child) {
		if (is_array($child)) {
			$found = array_merge($found, _test_dtae_styles($child));
		}
	}
	return $found;
}

// Every var() in the e-mails' inline styles carries its own fallback: a
// client that reads var() but blocks the linked stylesheet would otherwise
// draw the button transparent, white text on the white card.
function test_design_templates_account_emails_var_fallbacks()
{
	foreach (_test_dtae_templates() as $id => $tpl) {
		foreach (array('email_account_welcome', 'email_password_reset') as $key) {
			$w = _test_dtae_widget_on($tpl, $key, 'account_email');
			if (!is_array($w)) {
				pg_assert_true(false, $id . ' ' . $key . ' widget');
				continue;
			}
			$styles = _test_dtae_styles($w['tree']);
			pg_assert_true(count($styles) > 0, $id . ' ' . $key . ' inline styles');
			foreach ($styles as $style) {
				pg_assert_same(0, preg_match('/var\(\s*--[\w-]+\s*\)/', $style), $id . ' ' . $key . ' var() fallback in ' . $style);
			}
		}
	}
}

// The installer's "E-postalar" row lists both pages for every template.
function test_design_templates_account_emails_listed_as_email_pages()
{
	foreach (_test_dtae_templates() as $id => $tpl) {
		$pages = pg_design_template_email_pages($tpl);
		pg_assert_true(isset($pages['email_account_welcome']), $id . ' welcome listed');
		pg_assert_true(isset($pages['email_password_reset']), $id . ' reset listed');
	}
}
