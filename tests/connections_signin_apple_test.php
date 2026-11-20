<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Sign in with Apple's application on Connections: it is typed on
 * the service's page under the gate of the Accounts and sign-in settings it
 * came from; the sign-in card keeps the switch and leads to the page; the
 * checks are the ones the card ran; the registry keeps the service's place,
 * as the switch stays on the card.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/signin.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

function connections_signin_apple_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_connections_signin_apple_decide_reads_the_typed_values()
{
	$decision = pg_signin_apple_app_decide(array(
		'apple_client_id'   => ' com.example.signin ',
		'apple_team_id'     => ' abcde12345',
		'apple_key_id'      => 'zyxwv98765 ',
		'apple_private_key' => '',
	), true);

	pg_assert_same(array(), $decision['marks'], 'well-formed values pass');
	pg_assert_same('com.example.signin', $decision['client_id'], 'trimmed');
	pg_assert_same('ABCDE12345', $decision['team_id'], 'Apple\'s IDs are upper case');
	pg_assert_same('ZYXWV98765', $decision['key_id'], 'both of them');
	pg_assert_same('', $decision['private_key'], 'an empty key keeps the stored one');

	$missing = pg_signin_apple_app_decide(array(), true);
	pg_assert_same(array(), $missing['marks'], 'a post without the fields marks nothing');
	pg_assert_same('', $missing['client_id'], 'and reads empty values');
}

function test_connections_signin_apple_decide_marks_what_the_card_marked()
{
	$decision = pg_signin_apple_app_decide(array(
		'apple_client_id'   => 'com example',
		'apple_team_id'     => 'ABC',
		'apple_key_id'      => 'ABCDE-1234',
		'apple_private_key' => 'not a key',
	), true);

	pg_assert_same(array('apple_client_id', 'apple_team_id', 'apple_key_id', 'apple_private_key'), array_keys($decision['marks']), 'each refused field is marked');

	$unsealed = pg_signin_apple_app_decide(array('apple_client_id' => 'com.example.signin'), false);
	pg_assert_same(array('apple_private_key'), array_keys($unsealed['marks']), 'no encryption key: nothing can be kept');
	pg_assert_same(array(), pg_signin_apple_app_decide(array('apple_team_id' => 'ABCDE12345'), false)['marks'], 'IDs alone need no sealing');
}

function test_connections_signin_apple_card_keeps_the_sign_in_gate()
{
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('signin_apple', array('manager' => true, 'signin' => true)), 'the application on its page');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('signin_apple', array('manager' => true, 'signin' => false)), 'not without the sign-in settings\' right');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('signin_microsoft', array('manager' => true, 'signin' => true)), 'Microsoft\'s application is on Microsoft\'s page');

	$screen = connections_signin_apple_test_source('connections.php');
	$save = strpos($screen, "if (in_array(\$action, array('apple_save', 'apple_remove'), true)) {");
	pg_assert_true($save !== false, 'the save action is there');
	pg_assert_contains('if (connections_signin_may_manage($user)) {', substr($screen, $save, 300), 'the save asks the gate first');
	pg_assert_contains("'signin'       => ((\$page_id === 'signin_apple') && connections_signin_may_manage(\$user)),", $screen, 'and so does the drawing');
}

function test_connections_signin_apple_fields_moved_to_the_page()
{
	$screen = connections_signin_apple_test_source('connections.php');

	foreach (array('apple_client_id', 'apple_team_id', 'apple_key_id', 'apple_private_key') as $field) {
		pg_assert_contains('name="' . $field . '"', $screen, $field . ' is typed on the page');
	}

	$card = connections_signin_apple_test_source('includes/settings/security.php');
	pg_assert_false(strpos($card, 'name="apple_client_id"') !== false, 'the sign-in card no longer types the Services ID');
	pg_assert_false(strpos($card, 'name="apple_private_key"') !== false, 'nor the key');
	pg_assert_false(strpos($card, 'signin_microsoft_redirect') !== false, 'Microsoft\'s return address is on Microsoft\'s page');
	pg_assert_contains('name="signin_apple"', $card, 'the switch stays');
	pg_assert_contains('href="connections.php?service=signin_apple"', $card, 'a row leads to the page');

	$save = connections_signin_apple_test_source('includes/settings/security.save.php');
	pg_assert_false(strpos($save, 'apple_client_id') !== false, 'saving the sign-in settings leaves the application alone');
	pg_assert_contains("db(\"UPDATE config SET signin_providers = '\"", $save, 'the switches are still saved there');

	pg_assert_same(array('security', 'pgset-signin'), pg_settings_legacy_anchors()['signin_apple_row'] ?? null, 'an old link to the fields lands on the card\'s row');
	pg_assert_same('#settings/security/pgset-signin', pg_connections_registry()['signin_apple']['place'] ?? null, 'the switch keeps the service\'s place');
	pg_assert_same(78, pg_connections_count(), 'the figure stays');
}
