<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Google reviews on Connections: its Places API key and Place ID
 * are typed on its page under the gate of Site Settings › Integrations they
 * came from; the Google Reviews card shows the connection and leads there;
 * the registry row follows the move.
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

function connections_google_reviews_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_connections_google_reviews_decide_needs_key_and_place()
{
	$none = array('key' => '', 'place_id' => '');
	$place = 'ChIJN1t_tDeuEmsRUsoyG83frY4';

	$first = pg_grev_page_decide(array('google_reviews_key' => ' AIzaKey ', 'google_reviews_place_id' => $place), $none);
	pg_assert_same(array(), $first['marks'], 'a key and a place connect');
	pg_assert_same('AIzaKey', $first['key'], 'the key is trimmed');
	pg_assert_true($first['changed'], 'a new connection is written');
	pg_assert_false($first['try'], 'Save does not ask Google');

	$no_key = pg_grev_page_decide(array('google_reviews_key' => '', 'google_reviews_place_id' => $place), $none);
	pg_assert_true(isset($no_key['marks']['google_reviews_key']), 'no key stored and none typed is refused');

	$no_place = pg_grev_page_decide(array('google_reviews_key' => 'AIzaKey', 'google_reviews_place_id' => ''), $none);
	pg_assert_true(isset($no_place['marks']['google_reviews_place_id']), 'the Place ID is needed: Disconnect removes a connection');

	$bad = pg_grev_page_decide(array('google_reviews_key' => "AIza Key", 'google_reviews_place_id' => '../x?y'), $none);
	pg_assert_true(isset($bad['marks']['google_reviews_key']), 'a key with a space is not one');
	pg_assert_true(isset($bad['marks']['google_reviews_place_id']), 'a Place ID is letters, digits, _ and - only');

	$odd = pg_grev_page_decide(array('google_reviews_key' => array('x'), 'google_reviews_place_id' => array('y')), $none);
	pg_assert_same(2, count($odd['marks']), 'a list in a box is not a value');
}

function test_connections_google_reviews_decide_keeps_the_stored_key()
{
	$stored = array('key' => 'AIzaOld', 'place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4');

	$same = pg_grev_page_decide(array('google_reviews_key' => '', 'google_reviews_place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4', 'submit_try' => '1'), $stored);
	pg_assert_same(array(), $same['marks'], 'an empty key box keeps the stored key');
	pg_assert_same('AIzaOld', $same['key'], 'the stored key is tried');
	pg_assert_false($same['changed'], 'nothing changed: the kept answers stay');
	pg_assert_true($same['try'], 'Save and try asks Google');

	$moved = pg_grev_page_decide(array('google_reviews_key' => '', 'google_reviews_place_id' => 'ChIJP3Sa8ziYEmsRUKgyFmh9AQM'), $stored);
	pg_assert_true($moved['changed'], 'another place empties the cache');
	pg_assert_same('AIzaOld', $moved['key'], 'with the stored key');

	$rekeyed = pg_grev_page_decide(array('google_reviews_key' => 'AIzaNew', 'google_reviews_place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4'), $stored);
	pg_assert_true($rekeyed['changed'], 'another key empties the cache');
}

function test_connections_google_reviews_page_keeps_the_integrations_gate()
{
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('google_reviews', array('manager' => true, 'integrations' => true)), 'who may open Integrations types the key');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('google_reviews', array('manager' => true, 'integrations' => false)), 'a manager without the category sees no key');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('indexnow', array('integrations' => true)), 'only Google reviews takes the Integrations gate');

	$screen = connections_google_reviews_test_source('connections.php');
	pg_assert_contains("return pg_settings_may_open('api', \$user);", $screen, 'the gate is the Integrations category');
	pg_assert_contains("if (in_array(\$action, array('grev_save', 'grev_remove'), true) && connections_integrations_may_manage(\$user)) {", $screen, 'the writes check the same gate');
	pg_assert_contains("'integrations' => (\$page_id === 'google_reviews') && connections_integrations_may_manage(\$user),", $screen, 'and so does the card');
}

function test_connections_google_reviews_registry_row_moved()
{
	$rows = pg_connections_registry();

	pg_assert_same('connections.php', $rows['google_reviews']['place'], 'typed on Connections');
	pg_assert_same('#settings/api/pgset-google-reviews', $rows['google_reviews']['found_at'], 'the list row keeps the card\'s gate');
	pg_assert_same(array('kind' => 'reader', 'function' => 'pg_grev_configured'), $rows['google_reviews']['state'], 'its state is unchanged');
	pg_assert_same(87, pg_connections_count(), 'no service was added');
}

function test_connections_google_reviews_card_shows_the_connection()
{
	$on = pg_grev_settings_connection_html(true);
	$off = pg_grev_settings_connection_html(false);

	pg_assert_contains('id="pgsub-google-reviews-connection"', $on, 'the connection block');
	pg_assert_contains('connections.php?service=google_reviews', $on, 'leads to its page');
	pg_assert_contains('pg-tone-teal', $on, 'connected is teal');
	pg_assert_contains('pg-tone-gray', $off, 'not connected is gray');
	pg_assert_same(false, strpos($on . $off, 'google_reviews_key'), 'no key box on the card');
	pg_assert_same(false, strpos($on . $off, 'google_reviews_place_id'), 'no Place ID box on the card');
	pg_assert_contains('pg_grev_connections_url()', connections_google_reviews_test_source('edit_feedback_survey.php'), 'the survey\'s "set your place" line leads to the page');
}
