<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account security widget's passkey list
 * (pg_sw_list_security_sources(), pg_sw_list_passkey_rows() in
 * includes/fn/widgets_account.php): the source, its rows, and a row's
 * rename and remove forms wired by _pg_sec_apply_action_bindings() and
 * drawn by the finish pass.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _widget_pk_node($type, $props, $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

function _widget_pk_box($tag, $class, $children = array(), $extra = array())
{
	return _widget_pk_node('semantic', array('tag' => $tag, 'cssClass' => $class) + $extra, $children);
}

function _widget_pk_texts($html, $class)
{
	preg_match_all('#<[a-z][a-z0-9]* class="' . preg_quote($class, '#') . '"[^>]*>(.*?)</#s', $html, $m);
	return $m[1];
}

function _widget_pk_passkeys()
{
	return array(
		array('id' => 5, 'name' => 'Phone <A&B>', 'created_at' => 1792400000, 'last_used_at' => 0, 'backup_state' => true),
		array('id' => 9, 'name' => '', 'created_at' => 1792300000, 'last_used_at' => 0, 'backup_state' => false),
	);
}

// What the widget does: the bindings wired, the static flags applied, the
// tree drawn in an account_security scope, the rows published, the
// account's id filled, swept, then the finish pass.
function _widget_pk_finished($tree, $flags, $user_id, $account_id)
{
	$found = 0;
	_pg_sec_apply_action_bindings($tree, array('action_url' => '/software/account_security.php',
		'hidden_html' => '<input type="hidden" name="token" value="t">', 'token_html' => '<input type="hidden" name="token" value="t">'), $found);
	_eo_apply_visibility_bindings($tree, $flags);
	$scope = pg_sw_finish_open(array('sid' => 44, 'kind' => 'account_security', 'email' => false));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		pg_sw_list_publish('security', pg_sw_list_passkey_rows($user_id, _widget_pk_passkeys(), 'd.m.Y'));
		$html = pg_sw_sweep_tokens(pg_sw_fill_longest($html, array('account_id' => (string) $account_id)));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function _widget_pk_tree()
{
	return _widget_pk_node('root', array(), array(
		_widget_pk_box('div', 'passkeys', array(_widget_pk_node('loop_area', array('source' => 'security.passkeys'), array(
			_widget_pk_box('div', 'row', array(
				_widget_pk_box('span', 'pkname', array(), array('text' => 'Sample', '_bindings' => array('text' => '__passkey_name'))),
				_widget_pk_box('span', 'synced', array(), array('text' => 'Synced',
					'_bindings' => array('show_if' => '__passkey_is_synced'), '_bindParams' => array('show_if' => array('op' => 'not_empty')))),
				_widget_pk_box('form', 'remove', array(
					_widget_pk_box('input', 'pw', array(), array('_attrs' => array(array('name' => 'name', 'value' => 'current_password')),
						'_bindings' => array('eo_visible_if' => 'passkey_asks_password'))),
					_widget_pk_box('input', 'newname', array(), array('_attrs' => array(array('name' => 'name', 'value' => 'name'),
						array('name' => 'value', 'value' => '{{__passkey_name}}')))),
					_widget_pk_box('button', 'rm', array(), array('text' => 'Remove', '_bindings' => array('action' => 'passkey_remove'))),
				), array('_bindings' => array('action' => 'account_security_form'))),
			)),
		)))),
		_widget_pk_box('form', 'add', array(), array('_bindings' => array('action' => 'passkey_add_form'))),
	));
}

function test_widget_lists_passkeys_source()
{
	$sources = pg_sw_list_security_sources();
	pg_assert_same(array('security.passkeys'), array_keys($sources));
	pg_assert_same(array('family', 'key', 'label', 'arg', 'arg_required', 'tokens'), array_keys($sources['security.passkeys']));
	pg_assert_true(isset(pg_sw_list_sources()['security.passkeys']), 'a list source');
	pg_assert_same(array('security.passkeys'), array_keys(pg_sw_kind_lists('account_security')));
	pg_assert_true(!isset(pg_sw_kind_lists('my_account')['security.passkeys']), 'not in the my account widget');

	$rows = pg_sw_list_passkey_rows('7', _widget_pk_passkeys(), 'd.m.Y');
	$tokens = array();
	foreach ($sources['security.passkeys']['tokens'] as $pair) {
		$tokens[] = $pair[0];
	}
	pg_assert_same(array(7), array_keys($rows['security.passkeys']));
	foreach ($rows['security.passkeys'][7] as $row) {
		pg_assert_same($tokens, array_keys($row), 'row keys');
	}
	$first = $rows['security.passkeys'][7][0];
	$second = $rows['security.passkeys'][7][1];
	pg_assert_same('5', $first['__passkey_id']);
	pg_assert_same('Phone <A&B>', $first['__passkey_name'], 'raw: the pass escapes once');
	pg_assert_same(date('d.m.Y', 1792400000), $first['__passkey_added']);
	pg_assert_same('1', $first['__passkey_is_synced']);
	pg_assert_same('', $second['__passkey_is_synced']);
	pg_assert_same(lang('Passkey'), $second['__passkey_name'], 'an unnamed passkey');
	pg_assert_same(lang('Never'), $second['__passkey_last_used']);
}

function test_widget_lists_passkeys_end_to_end()
{
	$flags = array('passkey_asks_password' => true);
	$out = _widget_pk_finished(_widget_pk_tree(), $flags, 7, 7);
	pg_assert_same(array('Phone &lt;A&amp;B&gt;', h(lang('Passkey'))), _widget_pk_texts($out, 'pkname'), 'one row each, escaped once');
	pg_assert_same(1, count(_widget_pk_texts($out, 'synced')), 'the synced badge on the synced one only');
	pg_assert_true(strpos($out, 'name="pg_passkey_remove" value="5"') !== false, 'the remove button carries its passkey');
	pg_assert_true(strpos($out, 'name="pg_passkey_remove" value="9"') !== false, 'and the second its own');
	pg_assert_true(strpos($out, 'value="Phone &lt;A&amp;B&gt;"') !== false, 'the name field holds the name');
	pg_assert_same(2, substr_count($out, 'name="current_password"'), 'the password field in each row');
	pg_assert_same(3, substr_count($out, 'action="/software/account_security.php"'), 'each row form posts to account_security.php, the add form names it too');
	pg_assert_true(preg_match('#<form class="add"[^>]*\bhidden\b[^>]*data-pg-passkey-add#', $out) === 1, 'the add form is hidden and marked');

	// A Google-only account is not asked for its password.
	$out = _widget_pk_finished(_widget_pk_tree(), array('passkey_asks_password' => false), 7, 7);
	pg_assert_same(0, substr_count($out, 'name="current_password"'));

	// Another account's key reads no rows.
	$out = _widget_pk_finished(_widget_pk_tree(), $flags, 7, 8);
	pg_assert_same(array(), _widget_pk_texts($out, 'pkname'));
}

// Adding without two-step verification needs the e-mailed code's field in
// the tree; a tree saved before it keeps saying two-step comes first.
function test_widget_passkeys_add_state()
{
	$state = function ($mfa, $email_field, $count = 0, $live = true, $site_ok = true, $mfa_ready = true) {
		return pg_sw_passkey_add_state($live, $site_ok, $mfa_ready, $mfa, $email_field, $count);
	};
	pg_assert_same(array('passkey_can_add' => true, 'passkey_needs_mfa' => false, 'passkey_limit' => false), $state(true, false));
	pg_assert_same(array('passkey_can_add' => true, 'passkey_needs_mfa' => false, 'passkey_limit' => false), $state(false, true), 'the e-mailed code');
	pg_assert_same(array('passkey_can_add' => false, 'passkey_needs_mfa' => true, 'passkey_limit' => false), $state(false, false), 'an older tree');
	pg_assert_same(array('passkey_can_add' => false, 'passkey_needs_mfa' => false, 'passkey_limit' => true), $state(false, true, PG_PASSKEYS_MAX));
	pg_assert_same(array('passkey_can_add' => false, 'passkey_needs_mfa' => false, 'passkey_limit' => false), $state(false, true, 0, false), 'signed in as the member');
	pg_assert_same(array('passkey_can_add' => false, 'passkey_needs_mfa' => false, 'passkey_limit' => false), $state(true, true, 0, true, false), 'not on this site');
	pg_assert_same(array('passkey_can_add' => false, 'passkey_needs_mfa' => false, 'passkey_limit' => false), $state(false, true, 0, true, true, false), 'before the upgrade');
}

function test_widget_passkeys_tree_has_email_code()
{
	$field = _widget_pk_box('input', 'ec', array(), array('_attrs' => array(array('name' => 'type', 'value' => 'text'), array('name' => 'name', 'value' => 'email_code'))));
	pg_assert_true(!_pg_sec_tree_has_email_code(_widget_pk_tree()), 'the older tree');
	$tree = _widget_pk_tree();
	$tree['children'][1]['children'][] = $field;
	pg_assert_true(_pg_sec_tree_has_email_code($tree), 'a field named email_code');
}
