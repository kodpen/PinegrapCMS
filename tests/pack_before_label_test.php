<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the checked pack before a label (includes/fn/shipment_labels.php):
 * the refusal while the site switch is on and the address's pack is not
 * closed as packed, that every label path reads it through the one check,
 * and the API's answer to it. No database is reached.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipment_labels.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/pick_pack.php');

function test_pack_before_label_refusal()
{
	pg_assert_same('', pg_shipment_label_pack_refusal(false, null)['code'], 'switch off: no pack needed');
	pg_assert_same('', pg_shipment_label_pack_refusal(false, PG_PACK_OPEN)['code'], 'switch off: an open pack does not matter');
	pg_assert_same('', pg_shipment_label_pack_refusal(true, PG_PACK_PACKED)['code'], 'a checked pack passes');
	pg_assert_same('not_packed', pg_shipment_label_pack_refusal(true, null)['code'], 'no pack row yet');
	pg_assert_same('not_packed', pg_shipment_label_pack_refusal(true, PG_PACK_OPEN)['code'], 'a pack still open');
	pg_assert_same('not_packed', pg_shipment_label_pack_refusal(true, PG_PACK_SHORT)['code'], 'a short pack is not checked');
	pg_assert_true(pg_shipment_label_pack_refusal(true, null)['error'] !== '', 'the refusal says why');
}

function test_pack_before_label_read_in_the_one_check()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/shipment_labels.php');
	$start = strpos($source, 'function pg_shipment_label_create_check(');
	$end = strpos($source, "\nfunction ", $start + 1);
	$body = substr($source, $start, $end - $start);

	pg_assert_true(strpos($body, 'pg_shipment_label_pack_required()') !== false, 'the check reads the switch');
	pg_assert_true(strpos($body, '!$return && pg_shipment_label_pack_required()') !== false, 'a return label is not held back');
	pg_assert_true(strpos($source, "pg_config_text('pack_before_label') === '1'") !== false, 'the switch is the config_text row');

	// Create Label, Create Labels, the automatic label and the API all reach it.
	pg_assert_true(strpos($source, '$check = pg_shipment_label_create_check($ship_to_id, $return, $chosen);') !== false, 'create() under its lock');
	pg_assert_true(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/shipment_label_auto.php'), 'pg_shipment_label_create_check(') !== false, 'the automatic run');
}

function test_pack_before_label_api_answer()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/orders.php');

	pg_assert_same(array(409, 'pack_not_checked', 'x'), api_order_label_refusal(array('code' => 'not_packed', 'error' => 'x')));
}
