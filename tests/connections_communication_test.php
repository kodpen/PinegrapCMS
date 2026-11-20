<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Communication group on Connections: the WhatsApp and İYS
 * accounts are set up on their pages under the gate of Site Settings ›
 * Communication they came from; the cards show the connection and lead
 * there; the registry rows and the old anchors follow the move.
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

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');

function connections_communication_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_connections_communication_page_cards_keep_the_communication_gate()
{
	foreach (array('whatsapp', 'iys') as $id) {
		pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'contact' => true)), $id . ': who may open Communication types the account');
		pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'contact' => false)), $id . ': a manager without the category sees no account');
	}

	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('netgsm', array('contact' => true)), 'the SMS providers are not moved in this step');

	$screen = connections_communication_test_source('connections.php');
	pg_assert_contains("return pg_settings_may_open('contact', \$user);", $screen, 'the gate is the Communication category');
	pg_assert_contains("array('whatsapp_save', 'whatsapp_remove', 'iys_save', 'iys_remove'), true) && connections_contact_may_manage(\$user)", $screen, 'the write checks the gate');
	pg_assert_contains("'contact'      => in_array(\$page_id, array('whatsapp', 'iys'), true) && connections_contact_may_manage(\$user)", $screen, 'the details card takes the same right as the write');
	pg_assert_contains('is_array($contact_decision) && !$liveform->check_form_errors()', $screen, 'a refused account writes nothing');
}

function test_connections_communication_registry_and_anchors_follow_the_move()
{
	$registry = pg_connections_registry();

	pg_assert_same('connections.php', $registry['whatsapp']['place'], 'WhatsApp\'s place is Connections');
	pg_assert_same('#settings/contact/pgset-whatsapp', $registry['whatsapp']['found_at'], 'the WhatsApp card still finds it');
	pg_assert_same('connections.php', $registry['iys']['place'], 'İYS\'s place is Connections');
	pg_assert_same('#settings/contact/pgset-iys', $registry['iys']['found_at'], 'the İYS card still finds it');
	pg_assert_same('#settings/contact/pgset-sms', $registry['netgsm']['place'], 'the SMS providers stay on their card');
	pg_assert_same(78, pg_connections_count(), 'the figure stays');

	$anchors = pg_settings_legacy_anchors();
	pg_assert_same(array('contact', 'pgset-whatsapp'), $anchors['whatsapp_app_id'], 'an old link to the WhatsApp fields lands on the card');
	pg_assert_same(array('contact', 'pgset-iys'), $anchors['iys_user'], 'an old link to the İYS fields lands on the card');
}

function test_connections_communication_cards_show_the_connection()
{
	$app = array(
		'enabled' => true, 'secrets_unreadable' => false, 'app_secret' => 'secret-value-0123456789', 'token' => 'token-value-0123456789abcdef', 'app_id' => '1234567890123',
		'phone_number_id' => '106540352242922', 'account_id' => '102290129340398', 'verify_token' => 'c0ffee', 'checked' => 1, 'display_phone_number' => '+90 532', 'verified_name' => 'Shop', 'quality_rating' => 'GREEN',
	);
	$card = pg_whatsapp_settings_card_html($app, '');

	pg_assert_contains('id="pgsub-whatsapp-connection"', $card, 'the card shows the connection');
	pg_assert_contains('connections.php?service=whatsapp', $card, 'and leads to its page');
	pg_assert_false(strpos($card, 'name="whatsapp_') !== false, 'no account field on the card');
	pg_assert_false(strpos($card, $app['token']) !== false, 'no secret on the card');

	$contact = connections_communication_test_source('includes/settings/contact.php');
	$save = connections_communication_test_source('includes/settings/contact.save.php');
	$iys = connections_communication_test_source('includes/iys/settings.php');

	pg_assert_contains('pg_iys_settings_card_html()', $contact, 'the İYS card shows the connection');
	pg_assert_contains('connections.php?service=iys', $iys, 'and leads to its page');
	pg_assert_false(strpos($save, 'pg_whatsapp_settings_check(') !== false || strpos($save, 'pg_iys_settings_check(') !== false, 'the pane\'s save cannot change either account');
	pg_assert_contains('pg_whatsapp_channel_check($_POST', $save, 'the WhatsApp channel stays the pane\'s');
	pg_assert_contains('pg_sms_settings_check($_POST', $save, 'the SMS account stays the pane\'s');
}

function test_connections_communication_page_form_draws_no_secret()
{
	$app = array(
		'enabled' => true, 'secrets_unreadable' => false, 'app_secret' => 'secret-value-0123456789', 'token' => 'token-value-0123456789abcdef', 'app_id' => '1234567890123',
		'phone_number_id' => '106540352242922', 'account_id' => '102290129340398', 'verify_token' => 'c0ffee', 'checked' => 0, 'display_phone_number' => '', 'verified_name' => '', 'quality_rating' => '',
	);
	$html = pg_whatsapp_settings_html($app, array('refusal' => '', 'webhook_url' => 'https://shop.test/whatsapp_webhook.php', 'test_to' => ''));

	pg_assert_contains('class="pg-switch" role="switch" name="whatsapp_enabled"', $html, 'the switch in the page\'s look');
	pg_assert_contains('name="whatsapp_app_secret" id="whatsapp_app_secret" value=""', $html, 'the secret is never drawn back');
	pg_assert_contains('name="submit_save"', $html, 'Save');
	pg_assert_contains('name="whatsapp_test"', $html, 'and the test message');
	pg_assert_false(strpos($html, 'form-control') !== false, 'no Site Settings field look on the page');
	pg_assert_same(substr_count($html, '<div'), substr_count($html, '</div>'), 'every div closed');
}
