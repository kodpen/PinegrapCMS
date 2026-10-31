<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the custom form Grant Offer fields of a page-type save: only a
 * user with the commerce right writes them; for anyone else they stay out
 * of the saved row, so the stored offer is kept.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_type_pane.php');

function test_page_type_offer_properties_with_commerce_right()
{
	$post = array('custom_form_offer' => '1', 'custom_form_offer_id' => '7',
		'custom_form_offer_days' => '30', 'custom_form_offer_eligibility' => 'everyone');

	pg_assert_same(array('offer' => '1', 'offer_id' => '7', 'offer_days' => '30', 'offer_eligibility' => 'everyone'),
		pg_page_type_offer_properties($post, true));

	pg_assert_same(array('offer' => 0, 'offer_id' => '', 'offer_days' => '', 'offer_eligibility' => ''),
		pg_page_type_offer_properties(array(), true), 'switched off by a user with the right');
}

function test_page_type_offer_properties_without_commerce_right_keeps_stored()
{
	pg_assert_same(array(), pg_page_type_offer_properties(array(), false), 'fields not drawn, nothing written');

	pg_assert_same(array(), pg_page_type_offer_properties(array('custom_form_offer' => '1',
		'custom_form_offer_id' => '9', 'custom_form_offer_days' => '1', 'custom_form_offer_eligibility' => 'new'), false),
		'a crafted post changes nothing');
}

function test_page_type_save_writes_offer_only_through_the_rights_helper()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_type_pane.php');
	$start = strpos($source, 'function pg_page_type_properties_for_save(');
	pg_assert_true($start !== false);
	$body = substr($source, $start, strpos($source, "\nfunction ", $start + 10) - $start);

	pg_assert_true(strpos($body, 'pg_page_type_offer_properties($_POST, USER_MANAGE_ECOMMERCE)') !== false);
	pg_assert_true(strpos($body, "\$properties['offer") === false, 'no offer key set outside the helper');
	pg_assert_true(strpos($body, 'custom_form_offer') === false, 'no offer field read outside the helper');
}
