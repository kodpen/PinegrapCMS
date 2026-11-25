<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Mailchimp on Connections: its key, list and sync are set on
 * Mailchimp's page under the gate of Site Settings › E-mail › Sending they
 * came from; the Sending tab no longer draws or writes them, its Campaign
 * mail row shows the connection; the registry row follows the move and an
 * old anchor opens the page. No database and no Mailchimp.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mailchimp_settings.php');

function connections_mailchimp_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// A key-shaped test value, built at run time so no literal looks like a key.
function connections_mailchimp_test_key($dc)
{
	return str_repeat('b', 32) . '-' . $dc;
}

function test_connections_mailchimp_decide()
{
	$stored = connections_mailchimp_test_key('us6');

	$off = pg_mailchimp_settings_decide(array('mailchimp_list_id' => ' abc '), $stored);
	pg_assert_false($off['enabled'], 'no switch, no sync');
	pg_assert_same(array(), $off['marks'], 'a switched-off sync asks for nothing');
	pg_assert_same($stored, $off['key'], 'an empty box keeps the stored key');
	pg_assert_same('abc', $off['list']);

	$on = pg_mailchimp_settings_decide(array('mailchimp' => '1', 'mailchimp_list_id' => 'abc', 'mailchimp_store_id' => 'shop', 'mailchimp_sync_days' => '30', 'mailchimp_sync_limit' => '', 'mailchimp_automation' => '1'), $stored);
	pg_assert_same(array(), $on['marks'], 'a whole account is asked about at Mailchimp');
	pg_assert_same(30, $on['days']);
	pg_assert_same(0, $on['limit'], 'a blank limit is no limit');
	pg_assert_true($on['automation']);

	$cleared = pg_mailchimp_settings_decide(array('mailchimp' => '1', 'mailchimp_key_clear' => '1', 'mailchimp_list_id' => 'abc', 'mailchimp_store_id' => 'shop'), $stored);
	pg_assert_same('', $cleared['key'], 'the tick removes the key');
	pg_assert_true(isset($cleared['marks']['mailchimp_key']), 'and a sync without a key is refused');

	$bad = pg_mailchimp_settings_decide(array('mailchimp' => '1', 'mailchimp_key' => 'abc-evil.example/x'), '');
	pg_assert_same(array('mailchimp_key', 'mailchimp_list_id', 'mailchimp_store_id'), array_keys($bad['marks']), 'a key without its data center, no list, no store');
}

function test_connections_mailchimp_page_card_keeps_the_sending_gate()
{
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('mailchimp', array('manager' => true, 'mail_sending' => true)), 'who may open Sending sets it up');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('mailchimp', array('manager' => true, 'mail_sending' => false)), 'nobody else sees the fields');

	$screen = connections_mailchimp_test_source('connections.php');
	pg_assert_contains("return pg_settings_may_open('mail_sending', \$user);", $screen, 'the gate is the Sending tab\'s');
	pg_assert_contains("in_array(\$action, array('mailchimp_save', 'mailchimp_remove'), true) && connections_mail_sending_may_manage(\$user)", $screen, 'the write checks the gate');
	pg_assert_false(strpos(pg_mailchimp_settings_fields(array('enabled' => true, 'key_stored' => true, 'list' => 'l', 'store' => 's', 'days' => 0, 'limit' => 0, 'automation' => false)), connections_mailchimp_test_key('us6')) !== false, 'the key is never drawn back');
}

function test_connections_mailchimp_left_the_sending_tab()
{
	$card = connections_mailchimp_test_source('includes/settings/mail_sending.php');
	$save = connections_mailchimp_test_source('includes/settings/mail_sending.save.php');

	pg_assert_false(strpos($card, 'name="mailchimp_key"') !== false, 'the tab no longer types the key');
	pg_assert_contains('id="pgsub-mailchimp-connection"', $card, 'Campaign mail shows the connection');
	pg_assert_contains('connections.php?service=mailchimp', $card, 'and leads to its page');
	pg_assert_false(strpos($save, 'mailchimp') !== false, 'the tab\'s save cannot empty MailChimp');

	$email = pg_settings_email_category();
	pg_assert_false(isset($email['sections']['pgset-mailchimp']), 'no MailChimp section in E-mail');
	pg_assert_false(in_array('pgset-mailchimp', $email['tabs']['sending']['sections'], true), 'nor in the Sending tab');
}

function test_connections_mailchimp_registry_and_anchors_follow_the_move()
{
	$registry = pg_connections_registry();

	pg_assert_same('connections.php', $registry['mailchimp']['place'], 'its place is Connections');
	pg_assert_same('#settings/email/pgset-mail-campaign', $registry['mailchimp']['found_at'], 'Campaign mail still finds it');
	pg_assert_same(85, pg_connections_count(), 'the figure stays');

	$away = pg_settings_anchors_to_connections();
	$anchors = pg_settings_legacy_anchors();

	foreach (array('pgset-mailchimp', 'mailchimp_key') as $old) {
		pg_assert_same('mailchimp', $away[$old], $old . ' opens Mailchimp\'s page');
	}

	foreach ($away as $old => $service) {
		pg_assert_true(isset($registry[$service]), $old . ' names a service of the registry');
		pg_assert_false(isset($anchors[$old]), $old . ' is not also a settings anchor');
	}

	pg_assert_contains("data-pgaway=", connections_mailchimp_test_source('includes/settings/modal.php'), 'the dialog carries the map');
	pg_assert_contains("away = JSON.parse(el.getAttribute('data-pgaway') || '{}');", connections_mailchimp_test_source('assets/js/backend.src.js'), 'and its script reads it');
}
