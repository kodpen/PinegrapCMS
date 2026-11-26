<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the login, password, profile and e-mail preferences pages' own
 * layout files turned into their widgets' design elements: every starting-site text
 * converts with nothing dropped, the controls keep their classic names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layout_convert.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

// Every node of a tree, depth first, the root left out.
function _test_afl_nodes($nodes)
{
	$out = array();
	foreach ((array)$nodes as $n) {
		$out[] = $n;
		if (!empty($n['children'])) $out = array_merge($out, _test_afl_nodes($n['children']));
	}
	return $out;
}

// The values of one binding over the tree, in order.
function _test_afl_bound($tree, $prop)
{
	$out = array();
	foreach (_test_afl_nodes($tree['children']) as $n) {
		if (isset($n['props']['_bindings'][$prop])) $out[] = $n['props']['_bindings'][$prop];
	}
	return $out;
}

// The control names of the tree's inputs, selects and buttons, in order.
function _test_afl_names($tree)
{
	$out = array();
	foreach (_test_afl_nodes($tree['children']) as $n) {
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if (($a['name'] ?? '') === 'name') $out[] = (string)$a['value'];
		}
	}
	return $out;
}

// The starting sites' files: page id => type, both sites.
function _test_afl_pages()
{
	return array(895 => 'login', 981 => 'forgot password', 980 => 'set password', 979 => 'change password', 975 => 'my account profile', 978 => 'email preferences');
}

function _test_afl_site($site, $id)
{
	return (string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/' . $site . '/layouts/' . $id . '.php');
}

function _test_afl_template($type)
{
	return pg_layout_strip_template_header((string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/' . str_replace(' ', '_', $type) . '.php'));
}

// The flags of the nodes shown only under one, in tree order.
function _test_afl_flags($tree)
{
	return _test_afl_bound($tree, 'eo_visible_if');
}

// Every starting-site text (6 types, 12 files over both sites): nothing
// dropped, no marker or <form> left, the classic control names kept.
function test_afl_starting_site_texts()
{
	$expect = array(
		'login' => array('names' => array('email', 'password', 'remember_me'), 'flags' => array('shows_remember_me', 'has_forgot_password_link'),
			'sections' => array('google_signin'), 'href' => array('__forgot_password_url')),
		'forgot password' => array('names' => array('email'), 'flags' => array(), 'sections' => array(), 'href' => array()),
		'set password' => array('names' => array('new_password', 'password_hint'), 'flags' => array(), 'sections' => array('password_rules'), 'href' => array()),
		'change password' => array('names' => array('email_address', 'current_password', 'new_password', 'new_password_verify', 'password_hint'),
			'flags' => array(), 'sections' => array('password_rules'), 'href' => array('__my_account_url')),
		'my account profile' => array('names' => array('salutation', 'first_name', 'last_name', 'suffix', 'title', 'business_phone', 'mobile_phone',
			'home_phone', 'business_fax', 'timezone', 'company', 'business_address_1', 'business_address_2', 'business_city', 'business_state',
			'business_state', 'business_zip_code', 'business_country'), 'flags' => array(), 'sections' => array(), 'href' => array('__my_account_url')),
		'email preferences' => array('names' => array('email_address', 'opt_in', 'contact_group_^^__group_id^^'),
			'flags' => array('has_contact_groups', 'group_has_description'), 'sections' => array(), 'href' => array('__my_account_url')),
	);
	foreach (array('english_default', 'turkish_default') as $site) {
		foreach (_test_afl_pages() as $id => $type) {
			$label = $site . ' ' . $id . ' ' . $type;
			$r = pg_layout_convert($type, _test_afl_site($site, $id), array());
			$json = json_encode($r['tree']);
			pg_assert_true($r['ok'], $label . ': converts');
			pg_assert_same(array(), $r['dropped'], $label . ': nothing dropped');
			pg_assert_false((bool)preg_match('~pg-(vis|loop|section|row|raw)|<form|<\?~', $json), $label . ': no marker, form tag or PHP left');
			$names = _test_afl_names($r['tree']);
			// The Turkish set password page asks for the password twice.
			if ($site === 'turkish_default' && $type === 'set password') $names = array_values(array_diff($names, array('new_password_verify')));
			pg_assert_same($expect[$type]['names'], $names, $label . ': the classic control names');
			pg_assert_same($expect[$type]['flags'], _test_afl_flags($r['tree']), $label . ': the flags');
			pg_assert_same($expect[$type]['sections'], _test_afl_bound($r['tree'], 'section'), $label . ': the sections');
			pg_assert_same($expect[$type]['href'], _test_afl_bound($r['tree'], 'href'), $label . ': the bound links');
		}
	}
}

// Forgot password: the hint screen forgot_password.php shows no more is
// left out unreported, its words with it.
function test_afl_forgot_password_hint_screen()
{
	$r = pg_layout_convert('forgot password', _test_afl_site('english_default', 981), array());
	$json = json_encode($r['tree']);
	pg_assert_contains('Send Email', $json);
	pg_assert_false(strpos($json, 'I Remember') !== false || strpos($json, 'Password Hint') !== false, 'the hint screen is not read');
}

// Set password: the account's address is the widget's value.
function test_afl_set_password_email()
{
	$r = pg_layout_convert('set password', _test_afl_site('english_default', 980), array());
	pg_assert_same(array('__email'), _test_afl_bound($r['tree'], 'text'));
}

// E-mail preferences: the lists are the widget's list under
// has_contact_groups, shown always (the classic script that showed them
// once the opt-in was ticked is gone), the description under its row flag.
function test_afl_email_preferences_list()
{
	$r = pg_layout_convert('email preferences', _test_afl_site('turkish_default', 978), array());
	$json = json_encode($r['tree']);
	pg_assert_false(strpos($json, 'display: none') !== false, 'the lists are shown');
	$loops = array_values(array_filter(_test_afl_nodes($r['tree']['children']), function ($n) { return $n['type'] === 'loop_area'; }));
	pg_assert_same(1, count($loops), 'one list');
	pg_assert_contains('contact_group_^^__group_id^^', json_encode($loops[0]), 'the row box the widget names');
	pg_assert_contains('group_has_description', json_encode($loops[0]), 'the row flag is in the row');
	$wrap = null;
	foreach (_test_afl_nodes($r['tree']['children']) as $n) {
		if (($n['props']['_bindings']['eo_visible_if'] ?? '') === 'has_contact_groups') $wrap = $n;
	}
	pg_assert_true($wrap !== null && strpos(json_encode($wrap), 'loop_area') !== false, 'the list is under has_contact_groups');
}

// The stock templates: only the profile's account security block drops
// (no element in the profile widget: it is the account security widget).
function test_afl_templates()
{
	$want = array('login' => array(), 'forgot password' => array(), 'set password' => array(), 'change password' => array(),
		'my account profile' => array("\$account_security ?? ''"), 'email preferences' => array());
	foreach ($want as $type => $pieces) {
		$r = pg_layout_convert($type, _test_afl_template($type), array());
		pg_assert_true($r['ok'], $type);
		pg_assert_same($pieces, array_map(function ($d) { return $d['piece']; }, $r['dropped']), $type . ': dropped');
	}
	$r = pg_layout_convert('change password', _test_afl_template('change password'), array());
	pg_assert_same(array('password_not_set', 'has_password'), _test_afl_flags($r['tree']), 'the current password only for an account that has one');
	$r = pg_layout_convert('email preferences', _test_afl_template('email preferences'), array());
	pg_assert_same(array('shows_opt_in_sms', 'has_contact_groups', 'group_has_description'), _test_afl_flags($r['tree']));
	pg_assert_same(array('__opt_in_label', '__opt_in_sms_label', '__group_name'), array_values(array_intersect(_test_afl_bound($r['tree'], 'text'), array('__opt_in_label', '__opt_in_sms_label', '__group_name'))));
}
