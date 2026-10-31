<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Visitors and privacy category of Site Settings: the Visitor Tracking and
 * Cookie Consent cards left SEO for it and the Legal Documents card left
 * Ecommerce, with the fields they post and the columns their save writes
 * unchanged, and the old links to the three cards land on it.
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

function settings_privacy_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// One category's entry in pg_settings_categories(), cut out of the source:
// the list asks the database, so it is read rather than called.
function settings_privacy_test_category($key)
{
	$source = settings_privacy_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        '" . $key . "' => array(");

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        ),\n", $start);

	return substr($source, $start, $end - $start);
}

// The field names a card file posts.
function settings_privacy_test_names($source)
{
	preg_match_all('/\bname="([a-z0-9_]+)(?:\[\])?"/', $source, $match);
	$names = array_values(array_unique($match[1]));
	sort($names);

	return $names;
}

function test_settings_privacy_category_follows_seo()
{
	$source = settings_privacy_test_source('includes/settings/registry.php');
	$seo = strpos($source, "\n        'seo' => array(");
	$privacy = strpos($source, "\n        'privacy' => array(");
	$languages = strpos($source, "\n        'languages' => array(");

	pg_assert_true($privacy !== false, 'a privacy category');
	pg_assert_true(($seo < $privacy) && ($privacy < $languages), 'read after SEO, before Languages');
}

function test_settings_privacy_category_holds_its_three_cards()
{
	$privacy = settings_privacy_test_category('privacy');
	$seo = settings_privacy_test_category('seo');
	$commerce = settings_privacy_test_category('commerce');

	pg_assert_contains("'label' => lang('Visitors and privacy')", $privacy, 'its label');
	pg_assert_contains("'pgset-analytics' => lang('Visitor Tracking')", $privacy, 'the Visitor Tracking card');
	pg_assert_contains("'pgset-cookies'   => lang('Cookie Consent')", $privacy, 'the Cookie Consent card');
	pg_assert_contains("'pgset-legal'     => lang('Legal Documents')", $privacy, 'the Legal Documents card');
	pg_assert_true(strpos($privacy, "'pgset-analytics'") < strpos($privacy, "'pgset-cookies'"), 'visitor tracking first');
	pg_assert_true(strpos($privacy, "'pgset-cookies'") < strpos($privacy, "'pgset-legal'"), 'legal documents last');

	foreach (array('pgset-analytics', 'pgset-cookies') as $section) {
		pg_assert_same(false, strpos($seo, $section), $section . ' no longer in SEO');
	}

	pg_assert_same(false, strpos($commerce, 'pgset-legal'), 'no Legal Documents card in Ecommerce');
}

function test_settings_privacy_old_links_land_on_the_cards()
{
	$anchors = pg_settings_legacy_anchors();

	pg_assert_same(array('privacy', 'pgset-analytics'), $anchors['pgset-analytics'], 'settings_seo.php#pgset-analytics');
	pg_assert_same(array('privacy', 'pgset-cookies'), $anchors['pgset-cookies'], 'settings_seo.php#pgset-cookies');
	pg_assert_same(array('privacy', 'pgset-legal'), $anchors['pgset-legal'], 'settings_commerce.php#pgset-legal');
}

function test_settings_privacy_cards_moved_out_of_seo_and_ecommerce()
{
	$seo = settings_privacy_test_source('includes/settings/seo.php');
	$commerce = settings_privacy_test_source('includes/settings/commerce.php');
	$privacy = settings_privacy_test_source('includes/settings/privacy.php');

	foreach (array('pgset-analytics', 'pgset-cookies') as $card) {
		pg_assert_same(false, strpos($seo, 'id="' . $card . '"'), $card . ' no longer drawn by SEO');
		pg_assert_contains('<div id="' . $card . '" class="pg-set-card">', $privacy, $card . ' drawn here');
	}

	pg_assert_same(false, strpos($commerce, 'id="pgset-legal"'), 'no Legal Documents card drawn by Ecommerce');
	pg_assert_contains('<div id="pgset-legal" class="pg-set-card">', $privacy, 'the Legal Documents card');
	pg_assert_contains('if ($legal_documents_ready) {', $privacy, 'drawn once the schema has its fields, as before');
	pg_assert_contains("require(PG_FUNCTIONS_DIR . '/includes/settings/privacy_analytics.php');", $privacy, 'the Google Analytics figures go with the card');
	pg_assert_same(false, strpos($seo, '_analytics.php'), 'not required by SEO');
	pg_assert_contains("if (!defined('PG_SETTINGS_ENTRY')) {", $privacy, 'the gate constant');
	pg_assert_contains('Pinegrap - Enterprise Website Platform', $privacy, 'the header block');

	$moved = settings_privacy_test_names($privacy);

	foreach (array('visitor_tracking', 'tracking_code_duration', 'pay_per_click_flag', 'google_analytics', 'stats_url', 'google_analytics_web_property_id', 'visitor_retention_days', 'visitor_ip_days', 'visitor_summary', 'cookie_consent', 'cookie_consent_policy_url', 'ecommerce_legal_documents', 'ecommerce_kvkk_page_id', 'ecommerce_kvkk_consent_text') as $field) {
		pg_assert_true(in_array($field, $moved, true), $field . ' drawn by Visitors and privacy');
	}

	pg_assert_same(array(), array_values(array_intersect($moved, settings_privacy_test_names($seo))), 'no field drawn by both it and SEO');
	pg_assert_same(array(), array_values(array_intersect($moved, settings_privacy_test_names($commerce))), 'no field drawn by both it and Ecommerce');
}

function test_settings_privacy_save_writes_what_the_cards_post()
{
	$seo = settings_privacy_test_source('includes/settings/seo.save.php');
	$commerce = settings_privacy_test_source('includes/settings/commerce.save.php');
	$privacy = settings_privacy_test_source('includes/settings/privacy.save.php');

	foreach (array('visitor_tracking', 'tracking_code_duration', 'pay_per_click_flag', 'stats_url', 'google_analytics', 'google_analytics_web_property_id', 'visitor_retention_days', 'visitor_summary', 'visitor_ip_days', 'cookie_consent', 'cookie_consent_policy_url') as $column) {
		pg_assert_contains($column . ' = ', $privacy, $column . ' written by Visitors and privacy');
		pg_assert_same(false, strpos($seo, $column . ' = '), $column . ' no longer written by SEO');
	}

	foreach (array('ecommerce_legal_documents', 'ecommerce_kvkk_page_id', 'ecommerce_kvkk_consent_text', 'pg_legal_document_unknown_placeholders(') as $piece) {
		pg_assert_contains($piece, $privacy, $piece . ' in Visitors and privacy');
		pg_assert_same(false, strpos($commerce, $piece), $piece . ' no longer in Ecommerce');
	}

	// The same checks, still before anything is written.
	$update = strpos($privacy, 'db("UPDATE config');

	foreach (array("mark_error('cookie_consent_policy_url'", "mark_error('visitor_retention_days'", "mark_error('visitor_ip_days'", 'mark_error($legal_field', 'pg_visitor_cleanup_reset();') as $check) {
		$at = strpos($privacy, $check);
		pg_assert_true(($at !== false) && ($at < $update), $check . ' before the write');
	}

	pg_assert_true(strpos($privacy, 'mark_error($legal_field') < strpos($privacy, 'pg_visitor_cleanup_reset();'), 'every check before the cleanup reset');
	pg_assert_contains("array_key_exists('ga_card', \$_POST)", $privacy, 'the Google Analytics figures saved here');
	pg_assert_same(false, strpos($seo, 'ga_card'), 'not by SEO');
	pg_assert_contains('last_modified_user_id', $privacy, 'the save stamps who changed it');
	pg_assert_contains("if (!defined('PG_SETTINGS_ENTRY')) {", $privacy, 'the gate constant');
}

function test_settings_privacy_has_a_page()
{
	$page = settings_privacy_test_source('settings_privacy.php');

	pg_assert_contains("define('PG_SETTINGS_KEY', 'privacy');", $page, 'the page path of the category');
	pg_assert_contains("include('includes/settings/screen.php');", $page, 'drawn by the shared screen');
}

function test_settings_privacy_links_name_the_new_place()
{
	pg_assert_contains("pg_settings_url('privacy') . '?ga_connect=1#pgset-analytics'", settings_privacy_test_source('includes/settings/privacy_analytics.php'), 'Google comes back to the card');
	pg_assert_contains('settings_privacy.php#pgset-analytics', settings_privacy_test_source('database_engine.php'), 'the Database Engine link');
	pg_assert_contains("pg_settings_link('privacy', 'pgset-analytics')", settings_privacy_test_source('includes/dashboard/widgets/widget_32.php'), 'the dashboard widget link');
}
