<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the Workspace channel menu (channelMenu() in
 * assets/js/workspace.js): its items are the channel's own settings and
 * actions only, laid against an allowed list of their label keys.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The channel menu holds only the channel's own settings and actions, plus a
// customer channel's sales quote (it is about the channel's own customer).
// Creating or changing other records is a / command or the record's own
// screen, never an item here: a new key is added only for an item that
// belongs to the channel itself (docs/memory/workspace/README.md key rules).
function workspace_channel_menu_test_allowed()
{
	return array(
		'edit_channel', 'sales_quote_new',
		'notify_all', 'notify_mentions', 'notify_none',
		'voice_speak', 'claude_allow', 'ai_allow', 'voice_allow',
		'make_private', 'make_public', 'grp_move_channel',
		'tpl_apply', 'tpl_from_channel', 'share_start',
		'era_list', 'era_clear', 'archive', 'unarchive', 'leave_channel',
	);
}

// The body of channelMenu() in workspace.js, or '' when it is not found.
function workspace_channel_menu_test_body()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/workspace.js');

	if (!preg_match('/\n        channelMenu: function \(\) \{\n(.*?)\n        \},\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

// The label keys of every item() the menu draws. A label is read up to its
// icon argument ('bi-…'); a label built from a list (option[1]) takes the
// t() keys of the list literal that precedes it. A label with no key is
// returned as '?' so it fails the comparison.
function workspace_channel_menu_test_keys($body)
{
	$keys = array();
	$offset = 0;

	while (($at = strpos($body, 'item(', $offset)) !== false) {
		$offset = $at + 5;

		// The helper's own definition, not a call.
		if (substr($body, $at - 9, 9) === 'function ') {
			continue;
		}

		$icon = strpos($body, "'bi-", $offset);
		$label = substr($body, $offset, ($icon === false ? strlen($body) : $icon) - $offset);

		if (strpos($label, 'option[1]') !== false && preg_match_all('/\[\[[^\n]*\]\]\.forEach/', substr($body, 0, $at), $lists)) {
			$label = end($lists[0]);
		}

		if (!preg_match_all("/\\bt\\('([a-z0-9_]+)'/", $label, $found)) {
			$keys[] = '?';
			continue;
		}

		foreach ($found[1] as $key) {
			$keys[] = $key;
		}
	}

	$keys = array_values(array_unique($keys));
	sort($keys);

	return $keys;
}

function test_workspace_channel_menu_items_are_the_channels_own()
{
	$body = workspace_channel_menu_test_body();

	pg_assert_true($body !== '', 'channelMenu() is found in workspace.js');

	$allowed = workspace_channel_menu_test_allowed();
	sort($allowed);

	pg_assert_same($allowed, workspace_channel_menu_test_keys($body), 'the channel menu draws exactly the allowed items');
}

function test_workspace_channel_menu_has_no_make_products()
{
	$body = workspace_channel_menu_test_body();

	pg_assert_false(strpos($body, 'bulk_make_products') !== false, 'no "Make products" item in the channel menu');
	pg_assert_false(strpos($body, 'bulkMakeProducts') !== false, 'the channel menu opens no product window');
}

function test_workspace_channel_menu_reader_catches_a_foreign_item()
{
	$body = "            item(t('edit_channel'), 'bi-pencil', function () {});\n"
		. "            item(t('bulk_make_products'), 'bi-box-seam', function () {});\n"
		. "            item(label, 'bi-x', function () {});\n";

	pg_assert_same(array('?', 'bulk_make_products', 'edit_channel'), workspace_channel_menu_test_keys($body), 'an unlisted key and a keyless label are both read');
}
