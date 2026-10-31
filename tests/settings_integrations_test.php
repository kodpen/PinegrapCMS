<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The API category of Site Settings is named Integrations: it took the
 * Google Reviews card from Communication, with its fields, its check and its
 * save, and it links the screens that connect the site to other services,
 * each under the gate of the screen itself.
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

function settings_integrations_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function settings_integrations_test_category($key)
{
	$source = settings_integrations_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        '" . $key . "' => array(");

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        ),\n", $start);

	return substr($source, $start, $end - $start);
}

function settings_integrations_test_urls($links)
{
	$urls = array();

	foreach ($links as $link) {
		$urls[] = $link['url'];
	}

	return $urls;
}

function test_settings_integrations_is_the_api_key_renamed()
{
	$api = settings_integrations_test_category('api');

	pg_assert_contains("'label' => lang('Integrations')", $api, 'the label');
	pg_assert_true(strpos($api, "'pgset-api'") < strpos($api, "'pgset-api-storage'"), 'Application API, then Uploads & Logs');
	pg_assert_true(strpos($api, "'pgset-api-storage'") < strpos($api, "'pgset-google-reviews'"), 'then Google Reviews');
	pg_assert_true(strpos($api, "'pgset-google-reviews'") < strpos($api, "'pgset-integration-links'"), 'then the links');
	pg_assert_contains("'pgset-google-reviews' => pg_settings_keywords(", $api, 'with its search words');
	pg_assert_same(false, strpos(settings_integrations_test_category('contact'), 'pgset-google-reviews'), 'no longer in Communication');
	pg_assert_same('settings_api.php', pg_settings_url('api'), 'the key, and so its links, stay');
}

function test_settings_integrations_old_links_land_on_google_reviews()
{
	$anchors = pg_settings_legacy_anchors();

	pg_assert_same(array('api', 'pgset-google-reviews'), $anchors['pgset-google-reviews'], 'settings_contact.php#pgset-google-reviews');
}

function test_settings_integrations_google_reviews_card_moved()
{
	$contact = settings_integrations_test_source('includes/settings/contact.php');
	$cards = settings_integrations_test_source('includes/settings/api.php');

	pg_assert_same(false, strpos($contact, 'id="pgset-google-reviews"'), 'not drawn in Communication');
	pg_assert_same(false, strpos($contact, 'pg_grev_settings_fields('), 'nor its fields');
	pg_assert_contains('id="pgset-google-reviews"', $cards, 'drawn here');
	pg_assert_contains('pg_grev_settings_fields()', $cards, 'with its fields');
	pg_assert_contains('id="pgset-integration-links"', $cards, 'and the links card');
	pg_assert_contains('pg_settings_integration_links($user)', $cards, 'from the gated list');
}

function test_settings_integrations_google_reviews_save_moved()
{
	$save = settings_integrations_test_source('includes/settings/api.save.php');
	$contact = settings_integrations_test_source('includes/settings/contact.save.php');

	pg_assert_same(false, strpos($contact, 'pg_grev_settings_'), 'Communication no longer checks or saves it');

	$check = strpos($save, 'pg_grev_settings_check($_POST, $liveform)');
	$stop = strpos($save, 'if ($liveform->check_form_errors())');
	$write = strpos($save, 'db("UPDATE config SET');
	$keep = strpos($save, 'pg_grev_settings_save($google_reviews_post, $liveform)');

	pg_assert_true($check !== false, 'checked here');
	pg_assert_true(($check < $stop) && ($stop < $write), 'a refused key or Place ID stops the save before anything is written');
	pg_assert_true($keep > $write, 'saved after the API columns');
}

function test_settings_integrations_links_follow_each_screen_gate()
{
	$manager = settings_integrations_test_urls(pg_settings_integration_links(array('id' => 1, 'role' => 2), array('ecommerce' => true, 'workspace_settings' => false)));
	pg_assert_same(array('api_settings.php', 'connections.php', 'marketplace_settings.php'), $manager, 'a manager, store on, no workspace right');

	$no_store = settings_integrations_test_urls(pg_settings_integration_links(array('id' => 1, 'role' => 2), array('ecommerce' => false, 'workspace_settings' => true)));
	pg_assert_same(array('api_settings.php', 'connections.php', 'workspace_settings.php'), $no_store, 'store off, workspace settings right');

	pg_assert_same(array(), pg_settings_integration_links(array('id' => 1, 'role' => 3), array('ecommerce' => true, 'workspace_settings' => true)), 'nothing for a role the settings do not admit');
}
