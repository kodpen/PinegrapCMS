<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The SEO category of Site Settings as cards: Site Icon, Social Sharing and
 * Structured Data each have their own card, Product Feeds (with Google
 * Merchant Center) moved to Ecommerce with the fields it posts and the save
 * it runs unchanged, and the AI bot switches have one place, the AI Crawlers
 * card, which the Firewall card only reports and links to. The structured
 * data seller country and the Merchant Center country never share a field.
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

function settings_seo_sharing_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// One category's entry in pg_settings_categories(), cut out of the source:
// the list asks the database, so it is read rather than called.
function settings_seo_sharing_category($key)
{
	$source = settings_seo_sharing_source('includes/settings/registry.php');
	$start = strpos($source, "\n        '" . $key . "' => array(");

	if ($start === false) {
		return '';
	}

	return substr($source, $start, strpos($source, "\n        ),\n", $start) - $start);
}

// The field names a source posts.
function settings_seo_sharing_names($source)
{
	preg_match_all('/\bname="([a-z0-9_]+)(?:\[\])?"/', $source, $match);
	$names = array_values(array_unique($match[1]));
	sort($names);

	return $names;
}

function test_settings_seo_sharing_cards_in_order()
{
	$seo = settings_seo_sharing_category('seo');
	$order = array('pgset-seo', 'pgset-site-icon', 'pgset-social', 'pgset-structured', 'pgset-ai');
	$last = -1;

	foreach ($order as $section) {
		$at = strpos($seo, "'" . $section . "'");
		pg_assert_true(($at !== false) && ($at > $last), $section . ' listed in reading order');
		$last = $at;
	}

	pg_assert_same(false, strpos($seo, 'pgset-feeds'), 'no Product Feeds in SEO');

	$cards = settings_seo_sharing_source('includes/settings/seo.php');
	$drawn = array(
		'pgset-seo'        => '<div id="pgset-seo" class="pg-set-card">',
		'pgset-site-icon'  => "'pgset-site-icon'  => array(lang('Site Icon'), \$output_site_icon_picker . \$output_app_icon_picker)",
		'pgset-social'     => "'pgset-social'     => array(lang('Social Sharing'), \$output_share_image_pickers)",
		'pgset-structured' => "'pgset-structured' => array(lang('Structured Data'), \$output_structured_data_section)",
		'pgset-ai'         => '<div id="pgset-ai" class="pg-set-card">',
	);
	$last = -1;

	foreach ($drawn as $card => $marker) {
		$at = strpos($cards, $marker);
		pg_assert_true(($at !== false) && ($at > $last), $card . ' drawn in reading order');
		$last = $at;
	}

	pg_assert_contains('<div id="\' . $seo_card_id . \'" class="pg-set-card">', $cards, 'each drawn as a card');

	// The blocks prep.php builds are cards of their own, not parts of the
	// Search Engine Optimization card.
	$seo_card = substr($cards, 0, strpos($cards, "'pgset-site-icon'"));

	foreach (array('$output_site_icon_picker', '$output_share_image_pickers', '$output_structured_data_section') as $block) {
		pg_assert_same(false, strpos($seo_card, $block), $block . ' not in the SEO card');
	}
}

function test_settings_seo_sharing_feeds_in_ecommerce()
{
	$commerce = settings_seo_sharing_category('commerce');

	pg_assert_contains("'pgset-feeds'", $commerce, 'Product Feeds in Ecommerce');
	pg_assert_contains("'product_feed_excluded_groups' => 1", $commerce, 'its list field');

	$seo = settings_seo_sharing_source('includes/settings/seo.php');
	$cards = settings_seo_sharing_source('includes/settings/commerce.php');
	$merchant = settings_seo_sharing_source('includes/settings/commerce_merchant.php');

	pg_assert_same(false, strpos($seo, 'id="pgset-feeds"'), 'not drawn by SEO');
	pg_assert_contains('<div id="pgset-feeds" class="pg-set-card">', $cards, 'drawn by Ecommerce');
	pg_assert_contains("require(PG_FUNCTIONS_DIR . '/includes/settings/commerce_merchant.php');", $cards, 'with Merchant Center');
	pg_assert_same(false, strpos($seo, '_merchant.php'), 'Merchant Center not required by SEO');
	pg_assert_contains("pg_settings_url('commerce') . '?merchant_connect=1#pgset-feeds'", $merchant, 'Google comes back to the card');

	foreach (array('product_feed_seller_name', 'product_feed_default_brand', 'product_feed_seller_url', 'product_feed_privacy_policy_url', 'product_feed_terms_url', 'product_feed_excluded_groups', 'product_feed_build', 'product_feed_new_address') as $field) {
		pg_assert_contains('name="' . $field, $cards, $field . ' drawn by Ecommerce');
		pg_assert_same(false, strpos($seo, 'name="' . $field), $field . ' no longer drawn by SEO');
	}

	$seo_save = settings_seo_sharing_source('includes/settings/seo.save.php');
	$save = settings_seo_sharing_source('includes/settings/commerce.save.php');

	foreach (array('pg_product_feed_valid_url(', 'pg_product_feed_save_from_screen(', 'pg_merchant_save_from_screen(', "post_value('merchant_center_country')", "post_value('product_feed_build')") as $piece) {
		pg_assert_contains($piece, $save, $piece . ' in the Ecommerce save');
		pg_assert_same(false, strpos($seo_save, $piece), $piece . ' no longer in the SEO save');
	}

	// The address check refuses before Ecommerce writes a column.
	pg_assert_true(strpos($save, 'pg_product_feed_valid_url(') < strpos($save, 'db("UPDATE config'), 'checked before the write');

	pg_assert_contains('settings_commerce.php#pgset-feeds', settings_seo_sharing_source('includes/fn/system_status.php'), 'System Status links the new place');
}

function test_settings_seo_sharing_old_links_land_on_the_cards()
{
	$anchors = pg_settings_legacy_anchors();

	pg_assert_same(array('commerce', 'pgset-feeds'), $anchors['pgset-feeds'], 'settings_seo.php#pgset-feeds');
	pg_assert_same(array('seo', 'pgset-site-icon'), $anchors['pgsub-site-icon'], '#pgsub-site-icon');
	pg_assert_same(array('seo', 'pgset-site-icon'), $anchors['pgsub-app'], '#pgsub-app');
	pg_assert_same(array('seo', 'pgset-social'), $anchors['pgsub-social'], '#pgsub-social');
	pg_assert_same(array('seo', 'pgset-structured'), $anchors['pgsub-structured'], '#pgsub-structured');
}

function test_settings_seo_sharing_ai_bots_in_one_place()
{
	$seo = settings_seo_sharing_source('includes/settings/seo.php');
	$firewall = settings_seo_sharing_source('includes/settings/firewall.php');

	pg_assert_contains('$output_waf_ai_block', $seo, 'the switches on the AI Crawlers card');
	pg_assert_same(false, strpos($firewall, '$output_waf_ai_block'), 'not on the Firewall card');
	pg_assert_same(array(), array_values(array_intersect(array('waf_allow_ai_fetchers', 'waf_allow_ai_search', 'waf_refresh_ranges'), settings_seo_sharing_names($firewall))), 'no AI bot control on the Firewall card');
	pg_assert_contains("pg_settings_link('seo', 'pgset-ai')", $firewall, 'the Firewall card links there');
	pg_assert_same(false, strpos($seo, "pg_settings_link('firewall', 'pgset-waf')"), 'no link back from the AI Crawlers card');

	$seo_save = settings_seo_sharing_source('includes/settings/seo.save.php');
	$firewall_save = settings_seo_sharing_source('includes/settings/firewall.save.php');

	foreach (array('waf_allow_ai_fetchers = ', 'waf_allow_ai_search = ', "post_value('waf_refresh_ranges')", 'pg_waf_refresh_ai_ranges(true)') as $piece) {
		pg_assert_contains($piece, $seo_save, $piece . ' in the SEO save');
		pg_assert_same(false, strpos($firewall_save, $piece), $piece . ' no longer in the Firewall save');
	}
}

// The structured data seller country and the Merchant Center country are two
// settings: each card posts its own field and each save reads only its own,
// so saving one never overwrites the other.
function test_settings_seo_sharing_two_countries_two_fields()
{
	$seo = settings_seo_sharing_source('includes/settings/seo.php') . settings_seo_sharing_source('includes/settings/prep.php');
	$merchant = settings_seo_sharing_source('includes/settings/commerce_merchant.php');
	$seo_save = settings_seo_sharing_source('includes/settings/seo.save.php');
	$save = settings_seo_sharing_source('includes/settings/commerce.save.php');

	pg_assert_contains('name="merchant_country" id="merchant_country"', $seo, 'the structured data field');
	pg_assert_contains('name="merchant_center_country" id="merchant_center_country"', $merchant, 'the Merchant Center field');
	pg_assert_same(false, strpos($merchant, 'name="merchant_country"'), 'Merchant Center does not post the structured data field');

	pg_assert_contains("post_value('merchant_country')", $seo_save, 'SEO reads its own field');
	pg_assert_same(false, strpos($seo_save, 'merchant_center_country'), 'SEO does not read the Merchant Center field');
	pg_assert_contains("post_value('merchant_center_country')", $save, 'Ecommerce reads its own field');
	pg_assert_same(false, strpos($save, "post_value('merchant_country')"), 'Ecommerce does not read the structured data field');
	pg_assert_same(false, strpos($save, 'merchant_country = '), 'Ecommerce does not write config.merchant_country');
}
