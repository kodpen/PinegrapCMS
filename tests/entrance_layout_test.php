<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the registration and membership entrance pages' own layout
 * files turned into their widgets' design elements: the sign-up form is
 * the widget, the login form a dropped piece the Login widget draws, the
 * guest block the widget's guest section; and the guest's way on the
 * widget's page (its button, the small form after the widget's form).
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

function _test_ent_nodes($nodes)
{
	$out = array();
	foreach ((array)$nodes as $n) {
		$out[] = $n;
		if (!empty($n['children'])) $out = array_merge($out, _test_ent_nodes($n['children']));
	}
	return $out;
}

function _test_ent_bound($tree, $prop)
{
	$out = array();
	foreach (_test_ent_nodes($tree['children']) as $n) {
		if (isset($n['props']['_bindings'][$prop])) $out[] = $n['props']['_bindings'][$prop];
	}
	return $out;
}

function _test_ent_attr($n, $name)
{
	foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
		if (($a['name'] ?? '') === $name) return (string)$a['value'];
	}
	return null;
}

function _test_ent_names($tree)
{
	$out = array();
	foreach (_test_ent_nodes($tree['children']) as $n) {
		$name = _test_ent_attr($n, 'name');
		if ($name !== null) $out[] = $name;
	}
	return $out;
}

function _test_ent_site($site)
{
	return (string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/' . $site . '/layouts/126.php');
}

function _test_ent_template($type)
{
	return pg_layout_strip_template_header((string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/' . str_replace(' ', '_', $type) . '.php'));
}

// The node bound to the guest section, or null.
function _test_ent_guest($tree)
{
	foreach (_test_ent_nodes($tree['children']) as $n) {
		if (($n['props']['_bindings']['section'] ?? '') === 'guest') return $n;
	}
	return null;
}

function test_ent_types_mapped()
{
	pg_assert_true(in_array('registration entrance', pg_layout_convert_types(), true), 'registration entrance');
	pg_assert_true(in_array('membership entrance', pg_layout_convert_types(), true), 'membership entrance');
}

// Both starting sites' registration entrance (page 126): the sign-up form
// is the widget's tree; the login form, its forgot link and sign-in
// buttons are the one dropped piece the Login widget draws; the guest
// block is the guest section with its button bound to the guest action.
function test_ent_starting_sites()
{
	foreach (array('english_default', 'turkish_default') as $site) {
		$r = pg_layout_convert('registration entrance', _test_ent_site($site), array());
		pg_assert_true($r['ok'], $site . ': converts');
		pg_assert_same(array('login_form'), array_values(array_unique(array_column($r['dropped'], 'code'))), $site . ': only the login piece drops');
		pg_assert_same(array('$login_system', '$forgot_password_url', "\$google_signin ?? ''"), array_column($r['dropped'], 'piece'), $site . ': its parts');
		$json = json_encode($r['tree']);
		pg_assert_false((bool)preg_match('~pg-(vis|loop|section|cut|row|raw)|<form|<\?|data-pg-form~', $json), $site . ': no marker, form tag or PHP left');
		pg_assert_false(strpos($json, 'login_remember_me') !== false || strpos($json, 'login_email_address') !== false, $site . ': nothing of the login form');
		pg_assert_same(array('first_name', 'last_name', 'username', 'email', 'password', 'password_verify', 'password_hint', 'register_remember_me', 'opt_in'),
			array_values(array_diff(_test_ent_names($r['tree']), array(''))), $site . ': the sign-up controls by their classic names');
		pg_assert_same(array('shows_remember_me'), _test_ent_bound($r['tree'], 'eo_visible_if'), $site . ': the remember box under its flag');
		pg_assert_same(array('__opt_in_label'), _test_ent_bound($r['tree'], 'text'), $site . ': the consent words');
		$sections = _test_ent_bound($r['tree'], 'section');
		sort($sections);
		pg_assert_same(array('captcha', 'guest', 'password_rules'), $sections, $site . ': the sections');
		$guest = _test_ent_guest($r['tree']);
		pg_assert_true($guest !== null, $site . ': the guest section');
		pg_assert_same(array('continue_as_guest'), _test_ent_bound(array('children' => array($guest)), 'action'), $site . ': its one button bound to the guest action');
		pg_assert_contains('Continue as Guest', json_encode($guest), $site . ': its words kept');
	}
	// The register tab's error script: the widget prints its own errors.
	$r = pg_layout_convert('registration entrance', _test_ent_site('english_default'), array());
	pg_assert_false(strpos(json_encode($r['tree']), 'check_form_errors') !== false || strpos(json_encode($r['tree']), 'trigger') !== false, 'the tab script is not read');
	// The login tab goes whole: the guest and sign-up tabs stay in the list.
	$tabs = null;
	foreach (_test_ent_nodes($r['tree']['children']) as $n) {
		if (($n['props']['tag'] ?? '') === 'ul') $tabs = $n;
	}
	pg_assert_same(2, count($tabs['children']), 'the guest and register tabs');
	pg_assert_false(strpos(json_encode($tabs), '"Login"') !== false, 'the login tab title went with its form');
}

// The stock templates: the login column goes, the rest is the widget's.
function test_ent_templates()
{
	$r = pg_layout_convert('registration entrance', _test_ent_template('registration entrance'), array());
	pg_assert_true($r['ok']);
	pg_assert_same(array('$login_system', "\$google_signin ?? ''", '$forgot_password_url'), array_column($r['dropped'], 'piece'));
	pg_assert_same(array('first_name', 'last_name', 'username', 'email', 'password', 'password_verify', 'password_hint', 'register_remember_me', 'opt_in'),
		_test_ent_names($r['tree']));
	$guest = _test_ent_guest($r['tree']);
	pg_assert_true($guest !== null && strpos(json_encode($guest), 'Continue as a Guest') !== false, 'the guest heading and button');
	pg_assert_false(strpos(json_encode($r['tree']), '"Login"') !== false, 'the login heading went with its column');

	$r = pg_layout_convert('membership entrance', _test_ent_template('membership entrance'), array());
	pg_assert_true($r['ok']);
	pg_assert_same(array('login_form'), array_values(array_unique(array_column($r['dropped'], 'code'))));
	pg_assert_same(array('member_id', 'first_name', 'last_name', 'username', 'email_address', 'password', 'password_verify', 'password_hint', 'register_remember_me', 'opt_in'),
		_test_ent_names($r['tree']));
	pg_assert_contains('^^__member_id_label^^*', json_encode($r['tree'], JSON_UNESCAPED_UNICODE), 'the membership number label');
	pg_assert_true(_test_ent_guest($r['tree']) === null, 'no guest on the membership entrance');
	pg_assert_false(strpos(json_encode($r['tree']), 'name":"u"') !== false, 'no login control');
}

// The dropped line names the Login widget.
function test_ent_dropped_line()
{
	$rows = pg_layout_dropped_rows(array(array('piece' => '$login_system', 'line' => 12, 'code' => 'login_form')));
	pg_assert_contains('Login widget', $rows[0]['text']);
}

function _test_ent_tree($with_guest)
{
	$kids = array(pg_di_mk('semantic', array('tag' => 'input', '_attrs' => array(array('name' => 'name', 'value' => 'email')))));
	if ($with_guest) {
		$kids[] = pg_di_mk('semantic', array('tag' => 'div', 'cssClass' => 'guest', '_bindings' => array('section' => 'guest')), array(
			pg_di_mk('semantic', array('tag' => 'button', 'text' => 'Continue', '_bindings' => array('action' => 'continue_as_guest'))),
		));
	}
	return pg_di_root($kids);
}

// The guest section: gone without allow_guest; with it the button keeps
// its place and submits the guest form, never the sign-up form (type
// submit, a form attribute naming the guest form); the section binding is
// spent.
function test_ent_guest_section()
{
	$tree = _test_ent_tree(true);
	pg_assert_false(_pg_member_guest_apply($tree, false, 'pg-guest-form-7'), 'not allowed: no button');
	pg_assert_true(_test_ent_guest($tree) === null, 'not allowed: the section goes');

	$tree = _test_ent_tree(true);
	pg_assert_true(_pg_member_guest_apply($tree, true, 'pg-guest-form-7'), 'allowed: the tree has the button');
	$button = null;
	foreach (_test_ent_nodes($tree['children']) as $n) {
		if (($n['props']['tag'] ?? '') === 'button') $button = $n;
	}
	pg_assert_same('pg-guest-form-7', _test_ent_attr($button, 'form'));
	pg_assert_same('submit', _test_ent_attr($button, 'type'));
	pg_assert_true(_test_ent_guest($tree) === null, 'the section binding is spent, its node kept');
	pg_assert_contains('"guest"', json_encode($tree), 'the section node stays');

	// An empty guest section: the site's own button in it.
	$tree = pg_di_root(array(pg_di_mk('semantic', array('tag' => 'div', '_bindings' => array('section' => 'guest')))));
	pg_assert_true(_pg_member_guest_apply($tree, true, 'pg-guest-form-7'));
	pg_assert_contains('form=\"pg-guest-form-7\"', json_encode($tree));
}

// The small form after the widget's: the classic guest post, with its own
// button only when the tree has none (a page the run moved before the
// widget had the section: today such a guest has no way on).
function test_ent_guest_form()
{
	$html = _pg_member_guest_form_html('pg-guest-form-7', '/software/registration_entrance.php', '/checkout', '<input type="hidden" name="token" value="t">', false);
	pg_assert_contains('<form id="pg-guest-form-7" action="/software/registration_entrance.php" method="post"', $html);
	pg_assert_contains('name="continue" value="true"', $html);
	pg_assert_contains('name="send_to" value="/checkout"', $html);
	pg_assert_contains('name="allow_guest" value="true"', $html);
	pg_assert_contains('name="token" value="t"', $html);
	pg_assert_false(strpos($html, '<button') !== false, 'the tree holds the button');

	$tree = _test_ent_tree(false);
	pg_assert_false(_pg_member_guest_apply($tree, true, 'pg-guest-form-7'), 'a tree with no guest section');
	$html = _pg_member_guest_form_html('pg-guest-form-7', '/software/registration_entrance.php', '', '', true);
	pg_assert_contains('<button type="submit"', $html, 'the form brings its own button');
	pg_assert_contains('Continue as a Guest', $html);
}
