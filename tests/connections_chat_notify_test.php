<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the chat notification targets on Connections: Slack's, Teams'
 * and Telegram's pages save, add and delete only their own targets; the
 * page lists them without drawing an address or token back; the Chat
 * notifications card only leads there; the registry rows follow the move.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/notify_channels_page.php');

if (!function_exists('get_token_field')) {
	function get_token_field() { return '<input type="hidden" name="token" value="t">'; }
}

function connections_chat_notify_test_targets()
{
	return array(
		'aaaaaaaaaaaaaaa1' => array('id' => 'aaaaaaaaaaaaaaa1', 'kind' => 'slack', 'label' => 'Ops', 'url' => 'https://hooks.slack.com/services/T000/B000/SECRETSECRET', 'codes' => array('a'), 'events' => array()),
		'bbbbbbbbbbbbbbb2' => array('id' => 'bbbbbbbbbbbbbbb2', 'kind' => 'telegram', 'label' => 'Bot', 'token' => '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw0', 'chat_id' => '-100123', 'codes' => array('a', 'b'), 'events' => array()),
	);
}

function test_connections_chat_notify_save_touches_own_target_only()
{
	$targets = connections_chat_notify_test_targets();

	$saved = pg_notify_page_apply('notify_save', array('notify_target' => 'aaaaaaaaaaaaaaa1', 'notify_label' => ' Shop ops ', 'notify_codes' => array('b', 'zzz'), 'notify_events' => array('e1')),
		'slack', $targets, array('a', 'b'), array('e1'), 'cccccccccccccccc');
	pg_assert_true($saved['changed'], 'a changed target is written');
	pg_assert_same('Shop ops', $saved['targets']['aaaaaaaaaaaaaaa1']['label'], 'the name is trimmed');
	pg_assert_same(array('b'), $saved['targets']['aaaaaaaaaaaaaaa1']['codes'], 'only known codes are kept');
	pg_assert_same(array('e1'), $saved['targets']['aaaaaaaaaaaaaaa1']['events'], 'events are saved');
	pg_assert_same($targets['aaaaaaaaaaaaaaa1']['url'], $saved['targets']['aaaaaaaaaaaaaaa1']['url'], 'the address is kept');
	pg_assert_same($targets['bbbbbbbbbbbbbbb2'], $saved['targets']['bbbbbbbbbbbbbbb2'], 'another service\'s target is kept as it is');

	$same = pg_notify_page_apply('notify_save', array('notify_target' => 'aaaaaaaaaaaaaaa1', 'notify_label' => 'Ops', 'notify_codes' => array('a')),
		'slack', $targets, array('a', 'b'), array('e1'), 'cccccccccccccccc');
	pg_assert_false($same['changed'], 'an unchanged target writes nothing');
	pg_assert_same('aaaaaaaaaaaaaaa1', $same['id'], 'the test still knows the target');

	$other = pg_notify_page_apply('notify_save', array('notify_target' => 'bbbbbbbbbbbbbbb2', 'notify_label' => 'X'),
		'slack', $targets, array('a', 'b'), array(), 'cccccccccccccccc');
	pg_assert_false($other['changed'], 'Slack\'s page cannot change a Telegram target');
	pg_assert_same('', $other['id'], 'nor test it');
}

function test_connections_chat_notify_add_and_delete()
{
	$targets = connections_chat_notify_test_targets();

	$added = pg_notify_page_apply('notify_add', array('notify_new_label' => 'Team', 'notify_new_url' => 'https://contoso.webhook.office.com/webhookb2/abc', 'notify_new_codes' => array('a')),
		'teams', $targets, array('a'), array(), 'cccccccccccccccc');
	pg_assert_true($added['changed'], 'a Teams address adds a target');
	pg_assert_same('teams', $added['targets']['cccccccccccccccc']['kind'], 'of the page\'s service');
	pg_assert_same(3, count($added['targets']), 'the others are kept');

	$wrong = pg_notify_page_apply('notify_add', array('notify_new_url' => 'https://hooks.slack.com/services/T/B/X'),
		'teams', $targets, array('a'), array(), 'cccccccccccccccc');
	pg_assert_false($wrong['changed'], 'a Slack address on Teams\' page is refused');
	pg_assert_true(isset($wrong['marks']['notify_new_url']), 'and marked on the address');

	$bot = pg_notify_page_apply('notify_add', array('notify_new_token' => 'nope', 'notify_new_chat_id' => '1'),
		'telegram', $targets, array('a'), array(), 'cccccccccccccccc');
	pg_assert_true(isset($bot['marks']['notify_new_token']), 'a bad bot token is marked on the token');

	$deleted = pg_notify_page_apply('notify_delete', array('connection_id' => 'bbbbbbbbbbbbbbb2'),
		'telegram', $targets, array('a'), array(), 'cccccccccccccccc');
	pg_assert_true($deleted['changed'], 'a target of the page\'s service is deleted');
	pg_assert_false(isset($deleted['targets']['bbbbbbbbbbbbbbb2']), 'it is gone');

	$kept = pg_notify_page_apply('notify_delete', array('connection_id' => 'bbbbbbbbbbbbbbb2'),
		'slack', $targets, array('a'), array(), 'cccccccccccccccc');
	pg_assert_false($kept['changed'], 'another service\'s page cannot delete it');

	$odd = pg_notify_page_apply('notify_delete', array('connection_id' => array('bbbbbbbbbbbbbbb2')),
		'telegram', $targets, array('a'), array(), 'cccccccccccccccc');
	pg_assert_false($odd['changed'], 'a list is not an id');
}

function test_connections_chat_notify_page_lists_targets_without_secrets()
{
	$groups = pg_notify_alert_groups(array('a' => array('label' => 'Backup failed'), 'b' => array('label' => 'No sales', 'group' => 'sales')));
	pg_assert_same(2, count($groups), 'groups without a code are left out');

	$html = pg_notify_page_details('slack', connections_chat_notify_test_targets(), $groups, array('e1' => 'A flow run failed'), true, false);
	pg_assert_contains('notify-target-aaaaaaaaaaaaaaa1', $html, 'Slack\'s target is listed');
	pg_assert_false(strpos($html, 'bbbbbbbbbbbbbbb2') !== false, 'a Telegram target is not on Slack\'s page');
	pg_assert_false(strpos($html, 'SECRETSECRET') !== false, 'the webhook address is never drawn back');
	pg_assert_contains('https://hooks.slack.com', $html, 'where it sends is shown');
	pg_assert_contains('data-action="notify_delete"', $html, 'Delete');
	pg_assert_contains('name="submit_test"', $html, 'Save and send a test');
	pg_assert_contains('value="notify_add"', $html, 'Add a target');
	pg_assert_contains('name="notify_new_url"', $html, 'Slack takes an address');
	pg_assert_contains('Cron Jobs', $html, 'the job being off is said');

	$bot = pg_notify_page_details('telegram', connections_chat_notify_test_targets(), $groups, array(), true, true);
	pg_assert_contains('name="notify_new_token"', $bot, 'Telegram takes a bot token');
	pg_assert_false(strpos($bot, 'AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw0') !== false, 'the token is never drawn back');

	$off = pg_notify_page_details('slack', array(), $groups, array(), false, true);
	pg_assert_false(strpos($off, '<form') !== false, 'a site that cannot seal gets a note, no form');
}

function test_connections_chat_notify_card_leads_to_connections()
{
	$counts = pg_notify_target_counts(connections_chat_notify_test_targets());
	pg_assert_same(array('slack' => 1, 'teams' => 0, 'telegram' => 1), $counts, 'targets counted per service');

	$html = pg_notify_settings_card_html($counts);
	pg_assert_contains('connections.php?service=slack', $html, 'Slack\'s line leads to its page');
	pg_assert_contains('connections.php?service=teams', $html, 'Teams\' line too');
	pg_assert_false(strpos($html, '<input') !== false, 'the card has no field left');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/maintenance.save.php');
	pg_assert_false(strpos($source, 'notify_') !== false, 'the Updates and maintenance save writes no target');
}

function test_connections_chat_notify_registry_and_cards()
{
	$registry = pg_connections_registry();

	foreach (array('slack', 'teams', 'telegram') as $kind) {
		pg_assert_same('connections.php', $registry[$kind]['place'], $kind . ' is set on its page');
		pg_assert_same('#settings/maintenance/pgset-chat-notify', $registry[$kind]['found_at'], $kind . ' was found on the card');
		pg_assert_true(in_array('details', pg_connections_screen_page_cards($kind, array('chat_notify' => true)), true), $kind . ' has details under the gate');
		pg_assert_false(in_array('details', pg_connections_screen_page_cards($kind, array('manager' => true)), true), $kind . ' has none without it');
	}
}
