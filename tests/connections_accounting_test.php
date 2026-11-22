<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the e-document providers on Connections: Paraşüt's and Logo
 * İşbaşı's connection is set on their pages under the gate of Site
 * Settings › Accounting; the E-Invoice card keeps the provider choice and
 * the Paraşüt switch, shows each provider's connection and takes only a
 * connected one.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');

function connections_accounting_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function connections_accounting_test_fields()
{
	return array(
		array('name' => 'username', 'type' => 'text', 'required' => true),
		array('name' => 'password', 'type' => 'password', 'required' => true),
		array('name' => 'environment', 'type' => 'select', 'options' => array('test' => 'Test', 'live' => 'Live')),
	);
}

function test_connections_accounting_pages_and_registry()
{
	pg_assert_same(array_keys(erp_edoc_drivers()), pg_connections_screen_accounting_pages(), 'the screen knows every e-document provider');

	$registry = pg_connections_registry();

	foreach (pg_connections_screen_accounting_pages() as $id) {
		pg_assert_same('connections.php', $registry[$id]['place'], $id . ' is set on its page');
		pg_assert_same('#settings/accounting/pgset-invoice', $registry[$id]['found_at'], $id . ' is still found at the E-Invoice card');
		pg_assert_same('edoc', $registry[$id]['state']['kind'], $id . ' is connected while it is the provider in use');
		pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'accounting' => true)), $id . '\'s details on its page');
		pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'accounting' => false)), $id . ': not without the category\'s right');
		pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards($id, array('manager' => true, 'commerce' => true)), $id . ': the Ecommerce right is not enough');
	}

	pg_assert_same(84, pg_connections_count(), 'no service is added');
}

function test_connections_accounting_page_post_reads_the_boxes()
{
	$fields = connections_accounting_test_fields();

	$page = erp_edoc_page_post('isbasi', $fields, array(
		'edoc_isbasi_username'    => 'shop',
		'edoc_isbasi_password'    => '',
		'edoc_isbasi_environment' => 'live',
		'edoc_parasut_username'   => 'other',
	));
	pg_assert_same(array('username' => 'shop', 'password' => '', 'environment' => 'live'), $page['posted'], 'its own boxes only');
	pg_assert_same(array(), $page['cleared']);

	$page = erp_edoc_page_post('isbasi', $fields, array(
		'edoc_isbasi_username'          => array('shop'),
		'edoc_isbasi_environment'       => 'staging',
		'edoc_isbasi_password_clear'    => '1',
		'edoc_isbasi_username_clear'    => '1',
	));
	pg_assert_same(array(), $page['posted'], 'an array and an option the select does not offer are left out');
	pg_assert_same(array('password'), $page['cleared'], 'only a password box is cleared by its tick');
}

function test_connections_accounting_page_missing_follows_the_merge()
{
	$fields = connections_accounting_test_fields();
	$stored = array('username' => 'shop', 'password' => 'secret', 'environment' => 'test');
	$none = array('posted' => array(), 'cleared' => array());

	pg_assert_same(array(), erp_edoc_page_missing($fields, $stored, $none), 'nothing missing while all is stored');
	pg_assert_same(array(), erp_edoc_page_missing($fields, $stored, array('posted' => array('password' => ''), 'cleared' => array())), 'an empty password box keeps the stored one');
	pg_assert_same(array('password'), erp_edoc_page_missing($fields, $stored, array('posted' => array(), 'cleared' => array('password'))), 'a removed password is missing');
	pg_assert_same(array('username', 'password'), erp_edoc_page_missing($fields, array(), $none), 'nothing stored');
	pg_assert_same(array('username'), erp_edoc_page_missing($fields, $stored, array('posted' => array('username' => '  '), 'cleared' => array())), 'a blank text box is missing');
}

function test_connections_accounting_card_takes_only_a_connected_provider()
{
	pg_assert_same(array('activate' => false, 'refusal' => ''), erp_edoc_pick_decide('parasut', 'parasut', false, 'Paraşüt'), 'the provider in use stays, connected or not');
	pg_assert_same(array('activate' => true, 'refusal' => ''), erp_edoc_pick_decide('', 'isbasi', false, ''), 'Off is always taken');
	pg_assert_same(array('activate' => true, 'refusal' => ''), erp_edoc_pick_decide('isbasi', '', true, 'Logo İşbaşı'), 'a connected provider is taken');

	$refused = erp_edoc_pick_decide('isbasi', 'parasut', false, 'Logo İşbaşı');
	pg_assert_false($refused['activate'], 'a provider not connected is not taken');
	pg_assert_contains('Logo İşbaşı', $refused['refusal']);
}

function test_connections_accounting_card_shows_the_connection()
{
	$on = pg_edoc_card_connection_html('isbasi', 'Logo İşbaşı', true);
	pg_assert_contains('id="pgsub-edoc-isbasi-connection"', $on);
	pg_assert_contains('connections.php?service=isbasi', $on, 'it leads to its page');
	pg_assert_contains('pg-tone-teal', $on, 'Connected');

	$off = pg_edoc_card_connection_html('parasut', 'Paraşüt', false);
	pg_assert_contains('connections.php?service=parasut', $off);
	pg_assert_contains('pg-tone-gray', $off, 'Not connected');
}

function test_connections_accounting_keys_left_the_card()
{
	$card = connections_accounting_test_source('includes/settings/accounting.php');
	foreach (array('name="parasut_client_id"', 'name="parasut_client_secret"', 'name="parasut_auth_code"', 'name="parasut_password"', 'pg-parasut-authorize') as $gone) {
		pg_assert_false(strpos($card, $gone) !== false, 'the E-Invoice card draws no ' . $gone);
	}
	pg_assert_contains('$parasut_card_connection', $card, 'it shows Paraşüt\'s connection');

	$prep = connections_accounting_test_source('includes/settings/prep.php');
	pg_assert_false(strpos($prep, 'erp_edoc_fields(') !== false, 'no driver box is drawn for the card');
	pg_assert_false(strpos($prep, '$erp_edoc_name') !== false, 'nor named for it');
	pg_assert_contains('pg_edoc_card_connection_html(', $prep);

	$save = connections_accounting_test_source('includes/settings/accounting.save.php');
	pg_assert_contains('erp_edoc_pick_decide(', $save, 'the card refuses a provider not connected');
	pg_assert_contains('Connect it on its page of Connections, then switch it on here.', $save, 'and the Paraşüt switch while Paraşüt is not connected');

	$screen = connections_accounting_test_source('connections.php');
	pg_assert_contains("array('edoc_save', 'edoc_remove', 'parasut_save', 'parasut_remove')", $screen);
	pg_assert_contains("pg_settings_may_open('accounting', \$user)", $screen, 'the Accounting gate');
	pg_assert_contains('pg-edoc-test" data-pg-fields="parasut_"', connections_accounting_test_source('includes/fn/parasut.php'), 'Paraşüt\'s Connect button tries the code');
	pg_assert_contains('.connections-app-form', connections_accounting_test_source('assets/js/backend.src.js'), 'the Connect button works on the page');
}
