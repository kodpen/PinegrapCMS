<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for recipient suggestions in To, Cc and Bcc of New e-mail and Reply
 * by e-mail (includes/workspace/mail_suggest.php): the typed word asked for,
 * the addresses a box has heard from or written to, the order and the cap,
 * the bounce mark, the action and the script's wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mail_suggest.php');

function test_workspace_mail_suggest_prefix()
{
	pg_assert_same('ay', ws_mail_suggest_prefix('Ay'), 'lower case');
	pg_assert_same('mehmet@', ws_mail_suggest_prefix('zeynep@example.com, Mehmet@'), 'the word after the last comma');
	pg_assert_same('ali', ws_mail_suggest_prefix('a@b.co;  ali '), 'a semicolon separates too');
	pg_assert_same('', ws_mail_suggest_prefix('a'), 'one character is too short');
	pg_assert_same('', ws_mail_suggest_prefix('zeynep@example.com, '), 'nothing typed after the comma');
	pg_assert_same('', ws_mail_suggest_prefix(array('x')), 'not a string');
	pg_assert_same(WS_MAIL_SUGGEST_PREFIX_MAX, strlen(ws_mail_suggest_prefix(str_repeat('a', 300))), 'cut to the longest');
}

function test_workspace_mail_suggest_list_addresses()
{
	$text = 'Zeynep Kaya <Zeynep@Example.com>, mehmet@example.com, "Ali, Veli" <ali@example.org>, not an address';

	pg_assert_same(array('zeynep@example.com' => 'Zeynep Kaya'), ws_mail_suggest_list_addresses($text, 'zey'), 'by the address');
	pg_assert_same(array('zeynep@example.com' => 'Zeynep Kaya'), ws_mail_suggest_list_addresses($text, 'kay'), 'by a word of the name');
	pg_assert_same(array('ali@example.org' => 'Ali, Veli'), ws_mail_suggest_list_addresses($text, 'ali'), 'a quoted name with a comma');
	pg_assert_same(array(), ws_mail_suggest_list_addresses($text, 'xample'), 'only the start of a word');
}

function test_workspace_mail_suggest_merge()
{
	$contacts = array(array('address' => 'Zeynep@example.com', 'name' => 'Zeynep Kaya'));
	$heard = array('zeynep@example.com' => 'Z. K.', 'zeki@example.com' => '', 'zehra@example.com' => 'Zehra');
	$out = ws_mail_suggest_merge($contacts, $heard, array('zeki@example.com' => true));

	pg_assert_same(array(
		array('address' => 'zeynep@example.com', 'name' => 'Zeynep Kaya', 'bounced' => false),
		array('address' => 'zeki@example.com', 'name' => '', 'bounced' => true),
		array('address' => 'zehra@example.com', 'name' => 'Zehra', 'bounced' => false),
	), $out, 'contacts first, each address once, the bounced marked');

	$many = array();

	for ($i = 0; $i < 30; $i++) {
		$many['a' . $i . '@example.com'] = '';
	}

	pg_assert_same(WS_MAIL_SUGGEST_MAX, count(ws_mail_suggest_merge(array(), $many, array())), 'at most ten');
	pg_assert_same(10, WS_MAIL_SUGGEST_MAX, 'the cap');
	pg_assert_same(array(), ws_mail_suggest_merge(array(array('address' => 'not an address', 'name' => 'X')), array(), array()), 'a contact without a usable address');
}

function test_workspace_mail_suggest_wiring()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

	pg_assert_true(isset(pg_panel_actions()['ws_mail_suggest']), 'a panel action');
	pg_assert_contains("case 'ws_mail_suggest':", file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php'), 'handled');

	$script = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_mail.js');

	pg_assert_contains("h.api('ws_mail_suggest'", $script, 'the script asks the action');
	pg_assert_contains("suggest(h, to.field)", $script, 'To of New e-mail');
	pg_assert_contains("suggest(h, cc.field)", $script, 'Cc');
	pg_assert_contains("suggest(h, bcc.field)", $script, 'Bcc');
}
