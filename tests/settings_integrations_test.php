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

// An emptied or mistyped Log Retention box is refused before the write, not
// stored as 1 day, which let the next purge empty the API log.
function test_settings_integrations_log_retention_refused_not_clamped()
{
	$save = settings_integrations_test_source('includes/settings/api.save.php');
	$mark = strpos($save, "\$liveform->mark_error('api_log_retention_days'");

	pg_assert_true(($mark !== false) && ($mark < strpos($save, 'if ($liveform->check_form_errors())')), 'refused before anything is written');
	pg_assert_contains('!ctype_digit($api_log_retention_text)', $save);
	pg_assert_same(false, strpos($save, "max(1, min((int) post_value('api_log_retention_days')"), 'no silent clamp to 1');
}

// The tool groups as pg_settings_tool_groups() draws them for a manager
// with the store on, Cloudflare configured for an administrator.
function settings_integrations_test_tools($store, $cloudflare)
{
	$connections = array(
		array('label' => 'Application Access', 'icon' => 'bi-key', 'url' => 'api_settings.php'),
		array('label' => 'Connected accounts', 'icon' => 'bi-cloud-check', 'url' => 'connections.php'),
	);

	if ($store) {
		$connections[] = array('label' => 'Marketplaces', 'icon' => 'bi-buildings', 'url' => 'marketplace_settings.php');
	}

	$infrastructure = array(array('label' => 'Backup Manager', 'icon' => 'bi-archive', 'url' => 'backups.php'));

	if ($cloudflare) {
		$infrastructure[] = array('label' => 'Cloudflare Tools', 'icon' => 'bi-cloud', 'url' => 'cloudflare.php');
	}

	return array(
		array('label' => 'Monitoring', 'icon' => 'bi-activity', 'items' => array(array('label' => 'Site Log', 'icon' => 'bi-card-list', 'url' => 'view_log.php'))),
		array('label' => 'Connections', 'icon' => 'bi-plug', 'items' => $connections),
		array('label' => 'Jobs', 'icon' => 'bi-tools', 'items' => array(array('label' => 'Purge Cache', 'icon' => 'bi-trash3', 'url' => 'purge_cache.php?token=x', 'confirm' => 'Sure?'))),
		array('label' => 'Infrastructure', 'icon' => 'bi-hdd-stack', 'items' => $infrastructure),
	);
}

// The links are Application Access and the tool screens the registry of
// outside services names as places, each under the gate it has on System
// tools.
function test_settings_integrations_links_follow_each_screen_gate()
{
	$manager = settings_integrations_test_urls(pg_settings_integration_links(array('id' => 1, 'role' => 2), array('tools' => settings_integrations_test_tools(true, false), 'workspace_settings' => false)));
	pg_assert_same(array('api_settings.php', 'connections.php', 'marketplace_settings.php'), $manager, 'a manager, store on, no workspace right');

	$no_store = settings_integrations_test_urls(pg_settings_integration_links(array('id' => 1, 'role' => 2), array('tools' => settings_integrations_test_tools(false, false), 'workspace_settings' => true)));
	pg_assert_same(array('api_settings.php', 'connections.php', 'settings_workspace_team.php'), $no_store, 'store off, workspace settings right');

	$administrator = settings_integrations_test_urls(pg_settings_integration_links(array('id' => 1, 'role' => 0), array('tools' => settings_integrations_test_tools(true, true), 'workspace_settings' => false)));
	pg_assert_same(array('api_settings.php', 'connections.php', 'marketplace_settings.php', 'cloudflare.php'), $administrator, 'Cloudflare Tools where System tools shows it');

	pg_assert_same(array(), pg_settings_integration_links(array('id' => 1, 'role' => 3), array('tools' => settings_integrations_test_tools(true, true), 'workspace_settings' => true)), 'nothing for a role the settings do not admit');
}

// Every System tools screen the registry names as a place is linked when the
// user may open it.
function test_settings_integrations_links_cover_the_registry_screens()
{
	$urls = settings_integrations_test_urls(pg_settings_integration_links(array('id' => 1, 'role' => 0), array('tools' => settings_integrations_test_tools(true, true), 'workspace_settings' => false)));

	foreach (pg_connections_registry() as $id => $row) {
		if ((substr($row['place'], 0, 1) !== '#') && !in_array($row['place'], pg_connections_other_screens(), true)) {
			pg_assert_true(in_array($row['place'], $urls, true), $id . ': ' . $row['place'] . ' is linked');
		}
	}
}
