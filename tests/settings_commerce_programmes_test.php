<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Ecommerce, Affiliate programme, Referral programme and
 * Product feeds opened: the ticked group list that replaced the feed's
 * multi-select, its filter script, and the three cards without popovers or
 * old switches, every field under its old name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_settings_fold.php');

// The part of commerce.php from one card's id to the next card or the next
// part's heading comment.
function settings_commerce_programmes_test_card($id)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.php');
	$open = '<div id="' . $id . '" class="pg-set-card">';
	$start = strpos($source, $open);

	if ($start === false) {
		return '';
	}

	$ends = array(strlen($source));

	foreach (array('class="pg-set-card">', "\n// ── ") as $mark) {
		$end = strpos($source, $mark, $start + strlen($open));

		if ($end !== false) {
			$ends[] = $end;
		}
	}

	return substr($source, $start, min($ends) - $start);
}

// No popover, no old switch, nothing opened by a switch.
function settings_commerce_programmes_test_plain($html, $what)
{
	pg_assert_true($html !== '', $what . ' is drawn');
	pg_assert_false(strpos($html, 'popover') !== false, $what . ' has no popover');
	pg_assert_false(strpos($html, 'class="switch') !== false, $what . ' has no old switch');
	pg_assert_false(strpos($html, 'collapse-switcher') !== false, $what . ' opens nothing by a switch');
}

function test_settings_commerce_programmes_group_ticks()
{
	$html = pg_commerce_settings_group_ticks('groups', 'Leave out', array(4 => 'Shoes', 9 => 'Shoes › <Kids>'), array(9), 'A line', 'Filter');

	pg_assert_contains('<legend class="form-label pg-field-label">Leave out</legend>', $html, 'the legend');
	pg_assert_contains('<input type="checkbox" class="pg-checkbox" value="4" id="groups_4" name="groups[]"/>', $html, 'a group posts its id under the old array name');
	pg_assert_contains('id="groups_9" name="groups[]" checked="checked"/>', $html, 'a chosen group is ticked');
	pg_assert_contains('Shoes › &lt;Kids&gt;</label>', $html, 'the path escaped');
	pg_assert_contains('<fieldset aria-describedby="groups_help">', $html, 'the fieldset names its line');
	pg_assert_contains('id="groups_help">A line</p>', $html, 'the line under the list');
	pg_assert_false(strpos($html, 'data-pg-commerce-filter') !== false, 'two groups, no filter');

	$many = array();

	for ($i = 1; $i <= 9; $i++) {
		$many[$i] = 'Group ' . $i;
	}

	$html = pg_commerce_settings_group_ticks('groups', 'Leave out', $many, array(), '', 'Filter groups');
	pg_assert_contains('<input type="search" class="form-control" data-pg-commerce-filter="groups_list" aria-label="Filter groups" placeholder="Filter groups" autocomplete="off"/>', $html, 'more than eight groups, a filter above');
	pg_assert_contains('id="groups_list"', $html, 'the filter names the list');
	pg_assert_contains('data-pg-filter-text="group 9"', $html, 'each tick carries its words in lower case');
	pg_assert_contains('<fieldset><legend', $html, 'no line, a plain fieldset');

	$eight = array_slice($many, 0, 8, true);
	pg_assert_false(strpos(pg_commerce_settings_group_ticks('groups', 'L', $eight, array(), '', 'F'), 'type="search"') !== false, 'eight groups, no filter');
}

function test_settings_commerce_programmes_filter_script()
{
	$script = pg_commerce_settings_reveal_script();

	pg_assert_contains('data-pg-commerce-filter', $script, 'the pane\'s script filters the ticked list');
	pg_assert_contains('data-pg-filter-text', $script, 'it reads each tick\'s words');
}

function test_settings_commerce_programmes_affiliate_card()
{
	$html = settings_commerce_programmes_test_card('pgset-affiliate');
	settings_commerce_programmes_test_plain($html, 'Affiliate programme');

	foreach (array('affiliate_default_commission_rate', 'affiliate_contact_group_id', 'affiliate_email_address', 'affiliate_group_offer_id') as $name) {
		pg_assert_contains('name="' . $name . '"', $html, $name . ' keeps its name');
	}

	pg_assert_contains("pg_commerce_settings_switch('affiliate_program'", $html, 'the programme is a pg-switch');
	pg_assert_contains("pg_commerce_settings_switch('affiliate_automatic_approval'", $html, 'automatic approval is a pg-switch');
}

function test_settings_commerce_programmes_referral_card()
{
	$html = settings_commerce_programmes_test_card('pgset-referral');
	settings_commerce_programmes_test_plain($html, 'Referral programme');

	foreach (array('referral_offer_id', 'referral_reward', 'referral_reward_days', 'referral_smallest', 'referral_month_cap') as $name) {
		pg_assert_contains('name="' . $name . '"', $html, $name . ' keeps its name');
	}

	pg_assert_contains("pg_commerce_settings_switch('referral_on'", $html, 'the programme is a pg-switch');
	pg_assert_contains("pg_commerce_settings_group(lang('For the friend'))", $html, 'the friend\'s fields are a group');
	pg_assert_contains("pg_commerce_settings_group(lang('For the customer'))", $html, 'the customer\'s fields are a group');
	pg_assert_true(strpos($html, "lang('For the friend')") < strpos($html, 'name="referral_smallest"'), 'the smallest order is the friend\'s');
	pg_assert_true(strpos($html, "lang('For the customer')") < strpos($html, 'name="referral_reward"'), 'the reward is the customer\'s');
}

function test_settings_commerce_programmes_feeds_card()
{
	$html = settings_commerce_programmes_test_card('pgset-feeds');
	settings_commerce_programmes_test_plain($html, 'Product feeds');

	foreach (array('product_feed_seller_name', 'product_feed_default_brand', 'product_feed_seller_url', 'product_feed_privacy_policy_url', 'product_feed_terms_url', 'product_feed_build') as $name) {
		pg_assert_contains('name="' . $name . '"', $html, $name . ' keeps its name');
	}

	pg_assert_contains("pg_commerce_settings_group_ticks('product_feed_excluded_groups'", $html, 'the groups left out are a ticked list');
	pg_assert_false(strpos($html, '<select multiple') !== false, 'no Ctrl multi-select');
	pg_assert_contains("pg_commerce_settings_group(lang('Seller'))", $html, 'the seller fields are a group');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.php');
	pg_assert_contains("pg_commerce_settings_switch('product_feed_' . \$product_feeds_format", $source, 'each format is a pg-switch');
	pg_assert_false(strpos($source, 'onclick="return confirm(') !== false, 'New address asks in a drawer, not a browser box');
	pg_assert_contains('name="product_feed_new_address"', $source, 'New address still posts its format');
}
