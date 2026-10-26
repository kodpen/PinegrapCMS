<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the passkeys of the designed account security widget
 * (includes/fn/widgets_account.php): the part's flags, which mirror the
 * classic section's states, one row of the passkeys list, the wiring of the
 * forms in the list and of the add form, and the list's source as the
 * lists read it (includes/fn/widgets_lists.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function account_security_passkeys_test_attrs($node)
{
	$out = array();
	foreach ((isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array() as $a) {
		$out[$a['name']] = $a['value'];
	}
	return $out;
}

/* ---------------------------------------------------------------- flags */

function test_account_security_passkeys_flags_follow_the_classic_states()
{
	$proofs = array('password' => true, 'code' => true);

	// Before the upgrade: nothing at all.
	$f = pg_sec_passkey_flags(false, true, '', 0, false, true, $proofs, 20);
	pg_assert_false($f['passkeys_shown']);
	pg_assert_false($f['passkey_can_add']);
	pg_assert_false($f['passkey_remove_needs_password']);

	// Passkeys cannot work here and the account has none: silent, unless
	// it is opened at another host name.
	pg_assert_false(pg_sec_passkey_flags(true, false, 'unavailable', 0, false, true, $proofs, 20)['passkeys_shown']);
	$host = pg_sec_passkey_flags(true, false, 'host', 0, false, true, $proofs, 20);
	pg_assert_true($host['passkeys_shown']);
	pg_assert_true($host['passkey_unavailable']);
	pg_assert_false($host['passkey_can_add']);

	// Kept passkeys stay listed where they cannot be added.
	$kept = pg_sec_passkey_flags(true, false, 'unavailable', 2, false, true, $proofs, 20);
	pg_assert_true($kept['has_passkeys']);
	pg_assert_true($kept['passkey_unavailable']);
	pg_assert_true($kept['passkey_can_change']);

	// Two-step verification off: adding waits for it.
	$off = pg_sec_passkey_flags(true, true, '', 0, false, false, array('password' => true, 'code' => false), 20);
	pg_assert_true($off['no_passkeys']);
	pg_assert_true($off['passkey_needs_mfa']);
	pg_assert_false($off['passkey_can_add']);
	pg_assert_false($off['passkey_remove_needs_code']);

	// On, with room: the add form.
	$on = pg_sec_passkey_flags(true, true, '', 3, false, true, $proofs, 20);
	pg_assert_true($on['passkey_can_add']);
	pg_assert_false($on['passkey_limit']);
	pg_assert_true($on['passkey_remove_needs_password']);
	pg_assert_true($on['passkey_remove_needs_code']);

	// Full.
	$full = pg_sec_passkey_flags(true, true, '', 20, false, true, $proofs, 20);
	pg_assert_true($full['passkey_limit']);
	pg_assert_false($full['passkey_can_add']);

	// An operator signed in as the member: the list, nothing to press.
	$as = pg_sec_passkey_flags(true, true, '', 3, true, true, $proofs, 20);
	pg_assert_true($as['has_passkeys']);
	pg_assert_false($as['passkey_can_add']);
	pg_assert_false($as['passkey_needs_mfa']);
	pg_assert_false($as['passkey_can_change']);
	pg_assert_false($as['passkey_remove_needs_password']);
}

/* ------------------------------------------------------------------ row */

function test_account_security_passkeys_row()
{
	$row = pg_sec_passkey_row(array('id' => '7', 'name' => 'Telefonum', 'backup_state' => 1, 'last_used_at' => 1761300000),
		'01.10.2026', '2 days ago');
	pg_assert_same(array('__passkey_name' => 'Telefonum', '__passkey_id' => '7', '__passkey_added' => '01.10.2026',
		'__passkey_last_used' => '2 days ago'), $row['tokens']);
	pg_assert_same(array('passkey_synced' => true, 'passkey_used' => true, 'passkey_never_used' => false), $row['flags']);

	// The name comes first: the A to Z order compares it.
	pg_assert_same('__passkey_name', key($row['tokens']));

	$never = pg_sec_passkey_row(array('id' => 3, 'name' => '', 'backup_state' => 0, 'last_used_at' => 0), '01.10.2026', 'ignored');
	pg_assert_same(lang('Passkey'), $never['tokens']['__passkey_name']);
	pg_assert_same('', $never['tokens']['__passkey_last_used']);
	pg_assert_same(array('passkey_synced' => false, 'passkey_used' => false, 'passkey_never_used' => true), $never['flags']);
}

/* --------------------------------------------------------------- wiring */

function test_account_security_passkeys_forms_are_wired()
{
	$button = function ($action) {
		return array('type' => 'semantic', 'props' => array('tag' => 'button', '_bindings' => array('action' => $action)), 'children' => array());
	};
	$form = function ($kids) {
		return array('type' => 'semantic', 'props' => array('tag' => 'form', '_bindings' => array('action' => 'account_security_form')), 'children' => $kids);
	};
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		$form(array($button('logout_all'))),
		array('type' => 'loop_area', 'props' => array('source' => 'security.passkeys'), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
				$form(array($button('passkey_rename'))),
				$form(array($button('passkey_remove'))),
			)),
		)),
		array('type' => 'semantic', 'props' => array('tag' => 'form', '_bindings' => array('action' => 'passkey_add_form'),
			'_attrs' => array(array('name' => 'action', 'value' => 'elsewhere.php'))), 'children' => array()),
	));

	$found = 0;
	$add_found = 0;
	_pg_sec_apply_action_bindings($tree, array('action_url' => '/software/account_security.php', 'hidden_html' => '<i>hidden</i>'), $found, $add_found);

	pg_assert_same(3, $found);
	pg_assert_same(1, $add_found);

	// A form outside the list carries no passkey.
	pg_assert_same('<i>hidden</i>', $tree['children'][0]['children'][0]['props']['html']);

	// The forms in the list carry the row's passkey.
	$rename = $tree['children'][1]['children'][0]['children'][0];
	pg_assert_same('<i>hidden</i><input type="hidden" name="passkey_id" value="^^__passkey_id^^">', $rename['children'][0]['props']['html']);
	pg_assert_same(array('type' => 'submit', 'name' => 'pg_security_action', 'value' => 'passkey_rename'),
		account_security_passkeys_test_attrs($rename['children'][1]));
	$remove = $tree['children'][1]['children'][0]['children'][1];
	pg_assert_same('passkey_remove', account_security_passkeys_test_attrs($remove['children'][1])['value']);

	// The add form: its own address and marker, the token first, the
	// message line last.
	$add = $tree['children'][2];
	pg_assert_same(array('action' => '/software/account_security.php', 'method' => 'post', 'autocomplete' => 'off', 'data-pg-passkey-add' => ''),
		account_security_passkeys_test_attrs($add));
	pg_assert_same(2, count($add['children']));
	pg_assert_true(strpos($add['children'][1]['props']['html'], 'data-pg-passkey-message') !== false);
}

/* ---------------------------------------------------------------- list */

function test_account_security_passkeys_list_source()
{
	$sources = pg_sw_kind_lists('account_security');
	pg_assert_same(array('security.passkeys'), array_keys($sources));
	pg_assert_same(array('account.orders'), array_keys(pg_sw_kind_lists('my_account')));

	pg_assert_true(_pg_sec_is_passkey_list(array('type' => 'loop_area', 'props' => array('source' => 'security.passkeys'))));
	pg_assert_false(_pg_sec_is_passkey_list(array('type' => 'loop_area', 'props' => array())));
	pg_assert_false(_pg_sec_is_passkey_list(array('type' => 'loop_area', 'props' => array('source' => 'account.orders'))));

	// Every list of passkeys is replaced by what the drawing answers, at
	// any depth; nothing else is touched.
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
			array('type' => 'loop_area', 'props' => array('source' => 'security.passkeys'), 'children' => array()),
		)),
		array('type' => 'loop_area', 'props' => array(), 'children' => array()),
	));
	_pg_sec_draw_passkey_lists($tree, function ($list) {
		return '<p>rows</p>';
	});
	pg_assert_same(array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => '<p>rows</p>'), 'children' => array()),
		$tree['children'][0]['children'][0]);
	pg_assert_same('loop_area', $tree['children'][1]['type']);
}
