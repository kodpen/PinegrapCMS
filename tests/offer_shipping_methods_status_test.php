<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the offer editor carries each shipping method's status and marks
 * a switched-off method in the discount shipping row: checkout offers only
 * enabled methods, so a discount on a disabled one never applies.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_offer_shipping_methods_carry_status()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_offer_f.php');
	$start = strpos($source, 'function _pg_offer_shipping_methods(');
	pg_assert_true($start !== false, '_pg_offer_shipping_methods() exists');
	$end = strpos($source, "\nfunction ", $start + 1);
	$body = substr($source, $start, $end - $start);

	pg_assert_same(1, preg_match('/SELECT id, name, code, status FROM shipping_methods/', $body), 'status selected');
	pg_assert_same(1, preg_match("/'enabled' => \(\(string\) \\\$row\['status'\] === 'enabled'\)/", $body), 'enabled flag set');
}

function test_offer_editor_marks_disabled_shipping_methods()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/offer_editor.js');
	pg_assert_same(1, preg_match("/function methodLabel\(id\)/", $source), 'methodLabel() exists');
	pg_assert_same(1, preg_match("/tagHtml\(methodLabel\(id\)/", $source), 'chip label marks disabled methods');
	pg_assert_same(1, preg_match("/esc\(m\.name\) \+ \(m\.enabled \? '' : ' \[' \+ esc\(L\.disabledMethod\) \+ '\]'\)/", $source), 'add-method option marks disabled methods');

	$page = file_get_contents(PG_FUNCTIONS_DIR . '/edit_offer.php');
	pg_assert_same(1, preg_match("/'disabledMethod'\s*=> lang\('DISABLED'\)/", $page), 'disabledMethod label');
}
