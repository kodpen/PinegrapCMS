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

// The Send the code button is a plain button the script finds by its mark,
// whatever the node carried; the code field travels as email_code.
function test_widget_passkeys_send_code_button()
{
	$tree = _widget_pk_tree();
	$tree['children'][1]['children'][] = _widget_pk_box('button', 'send', array(), array('text' => 'Send',
		'_attrs' => array(array('name' => 'type', 'value' => 'submit'), array('name' => 'name', 'value' => 'x')),
		'_bindings' => array('action' => 'passkey_send_code')));
	$out = _widget_pk_finished($tree, array('passkey_asks_password' => true), 7, 7);
	pg_assert_true(preg_match('#<button class="send" type="button" data-pg-passkey-send(="")?>#', $out) === 1, 'a plain marked button: ' . $out);
	pg_assert_true(strpos($out, 'name="x"') === false, 'its own name is dropped');
}

// Only the add form's email_code field says adding is offered without two-step
// verification: the new recovery codes form carries a field of that name too.
function test_widget_passkeys_email_code_field_of_the_add_form_only()
{
	$field = _widget_pk_box('input', 'ec', array(), array('_attrs' => array(array('name' => 'type', 'value' => 'text'), array('name' => 'name', 'value' => 'email_code'))));
	$tree = _widget_pk_tree();
	$tree['children'][] = _widget_pk_box('form', 'recovery', array($field), array('_bindings' => array('action' => 'account_security_form')));
	pg_assert_false(_pg_sec_tree_has_email_code($tree), 'the recovery form\'s field');
	$tree['children'][1]['children'][] = _widget_pk_box('div', 'group', array($field));
	pg_assert_true(_pg_sec_tree_has_email_code($tree), 'nested in the add form');
}

// New recovery codes by e-mail: for an account whose second factor is its
// passkeys, never with TOTP on, while codes are shown or for an operator.
function test_widget_passkeys_email_recovery_state()
{
	pg_assert_true(pg_sw_mfa_email_recovery(true, false, false, 2), 'passkeys, no TOTP');
	pg_assert_false(pg_sw_mfa_email_recovery(true, false, true, 2), 'TOTP on: its own regenerate');
	pg_assert_false(pg_sw_mfa_email_recovery(true, false, false, 0), 'no passkey: no second factor');
	pg_assert_false(pg_sw_mfa_email_recovery(true, true, false, 2), 'fresh codes shown');
	pg_assert_false(pg_sw_mfa_email_recovery(false, false, false, 2), 'an operator, or no tables');
}

// The recovery Send the code button is a plain marked button, never a submit;
// the regenerate button posts its action.
function test_widget_passkeys_recovery_send_button()
{
	$tree = _widget_pk_tree();
	$tree['children'][] = _widget_pk_box('form', 'recovery', array(
		_widget_pk_box('button', 'send', array(), array('text' => 'Send',
			'_attrs' => array(array('name' => 'type', 'value' => 'submit'), array('name' => 'name', 'value' => 'x')),
			'_bindings' => array('action' => 'mfa_recovery_send_code'))),
		_widget_pk_box('button', 'go', array(), array('text' => 'New', '_bindings' => array('action' => 'mfa_recovery_regenerate'))),
	), array('_bindings' => array('action' => 'account_security_form', 'eo_visible_if' => 'mfa_email_recovery')));

	$out = _widget_pk_finished($tree, array('mfa_email_recovery' => true), 7, 7);
	pg_assert_true(preg_match('#<button class="send" type="button" data-pg-recovery-send(="")?>#', $out) === 1, 'a plain marked button: ' . $out);
	pg_assert_contains('name="pg_security_action" value="mfa_recovery_regenerate"', $out, 'the regenerate action');

	$out = _widget_pk_finished($tree, array('mfa_email_recovery' => false), 7, 7);
	pg_assert_false(strpos($out, 'data-pg-recovery-send') !== false, 'not offered: no form');
	pg_assert_contains('body.append("purpose", "recovery");', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/passkeys.php'), 'the code is asked for new recovery codes');
	pg_assert_contains("if (strpos(\$rendered, 'data-pg-recovery-send') !== false) \$rendered .= pg_mfa_recovery_send_script();", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_account.php'), 'its script comes with the button');
}

// The editor's starter tree offers the new recovery codes form under its flag,
// with the e-mailed code field, its Send button, the sentence's place and the
// regenerate button; its email_code field leaves the add form's rule alone.
function test_widget_passkeys_starter_tree_recovery_form()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$at = strpos($js, "asForm(_sdT('New Codes by E-mail Form'), 'mfa_email_recovery'");
	pg_assert_true($at !== false, 'the form under its flag');
	$form = substr($js, (int) $at, 3000);
	foreach (array("_bindings: { text: '__mfa_recovery_left_text' }", "asPassword('_rc_password')", "{ name: 'name', value: 'email_code' }",
		"_bindings: { action: 'mfa_recovery_send_code' }", "{ name: 'data-pg-recovery-sent', value: '' }",
		"asButton(_sdT('New recovery codes'), 'mfa_recovery_regenerate'") as $needle) {
		pg_assert_contains($needle, $form, $needle);
	}
	pg_assert_contains("['mfa_recovery_send_code',  _sdT(", $js, 'the action is offered');
}
