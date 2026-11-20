<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for IndexNow on Connections: its key is typed or generated on
 * IndexNow's page under the gate of Site Settings › SEO it came from; the
 * SEO card shows the connection and leads there; the registry row and the
 * old anchor follow the move.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/seo.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

function connections_indexnow_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_connections_indexnow_key_format()
{
	pg_assert_true(pg_indexnow_key_valid('abcd1234'), 'eight characters are a key');
	pg_assert_true(pg_indexnow_key_valid(str_repeat('a-1', 42) . 'ab'), '128 letters, digits and dashes are a key');
	pg_assert_false(pg_indexnow_key_valid('abc123'), 'too short');
	pg_assert_false(pg_indexnow_key_valid(str_repeat('a', 129)), 'too long');
	pg_assert_false(pg_indexnow_key_valid('../etc/passwd'), 'no path reaches the file system');
	pg_assert_false(pg_indexnow_key_valid('abcd 1234'), 'no space');
	pg_assert_false(pg_indexnow_key_valid(null), 'not a string');

	$key = pg_indexnow_key_new();
	pg_assert_true(pg_indexnow_key_valid($key), 'a generated key fits the format');
	pg_assert_same(32, strlen($key), 'a generated key has 32 characters');
	pg_assert_true($key !== pg_indexnow_key_new(), 'each generated key is new');
}

function test_connections_indexnow_page_card_keeps_the_seo_gate()
{
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('indexnow', array('manager' => true, 'seo' => true)), 'who may open SEO types the key');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('indexnow', array('manager' => true, 'seo' => false)), 'a manager without SEO sees no key');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('indexnow_other', array('seo' => true)), 'only IndexNow takes the SEO gate');

	$screen = connections_indexnow_test_source('connections.php');
	pg_assert_contains("return pg_settings_may_open('seo', \$user);", $screen, 'the gate is the SEO category');
	pg_assert_contains("in_array(\$action, array('indexnow_save', 'indexnow_remove'), true) && connections_seo_may_manage(\$user)", $screen, 'the write checks the gate');
	pg_assert_contains("isset(\$service['found_at']) && !pg_connections_screen_may_see(\$service['found_at']", $screen, 'the row keeps the gate of the card it came from');
}

function test_connections_indexnow_registry_and_anchor_follow_the_move()
{
	$registry = pg_connections_registry();

	pg_assert_same('connections.php', $registry['indexnow']['place'], 'its place is Connections');
	pg_assert_same('#settings/seo/pgset-seo', $registry['indexnow']['found_at'], 'the SEO card still finds it');
	pg_assert_same(78, pg_connections_count(), 'the figure stays');

	$anchors = pg_settings_legacy_anchors();
	pg_assert_same(array('seo', 'pgset-seo'), $anchors['indexnow_key'], 'an old link to the field lands on the SEO card');
}

function test_connections_indexnow_seo_card_shows_the_connection()
{
	$card = connections_indexnow_test_source('includes/settings/seo.php');
	$save = connections_indexnow_test_source('includes/settings/seo.save.php');

	pg_assert_false(strpos($card, 'name="indexnow_key"') !== false, 'the card no longer types the key');
	pg_assert_contains('id="pgsub-indexnow"', $card, 'the card shows the connection');
	pg_assert_contains('connections.php?service=indexnow', $card, 'and leads to its page');
	pg_assert_contains("lang('Manage in Connections')", $card, 'with Manage in Connections');
	pg_assert_false((strpos($save, 'indexnow_key =') !== false) || (strpos($save, "\$_POST['indexnow_key']") !== false), 'the SEO save cannot empty the key');
}
