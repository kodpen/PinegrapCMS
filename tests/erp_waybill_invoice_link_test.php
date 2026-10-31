<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where a delivery note's invoice links (erp_waybill_invoice_url(),
 * includes/erp/waybills.php): a cancelled invoice is not linked, a draft goes
 * to the draft editor, an issued one to the invoice page, and the drawer uses
 * the helper like the list and the full page. Also pins the action "Open" on
 * buttons and links to the verb key, since the short key "Open" is a state.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/waybills.php');

function test_erp_waybill_invoice_url_follows_the_invoice_state()
{
	pg_assert_same('', erp_waybill_invoice_url(array('invoice_id' => 0, 'invoice_status' => null)), 'no invoice');
	pg_assert_same('', erp_waybill_invoice_url(array('invoice_id' => 33, 'invoice_status' => null)), 'invoice row gone');
	pg_assert_same('', erp_waybill_invoice_url(array('invoice_id' => 33, 'invoice_status' => 'cancelled')), 'cancelled invoice is not linked');
	pg_assert_same('edit_erp_invoice_draft.php?id=33', erp_waybill_invoice_url(array('invoice_id' => 33, 'invoice_status' => 'draft')), 'draft goes to the draft editor');
	pg_assert_same('edit_erp_invoice.php?id=33', erp_waybill_invoice_url(array('invoice_id' => '33', 'invoice_status' => 'sent')), 'issued invoice');
}

function test_erp_waybill_drawer_uses_the_invoice_state()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/drawer.php');
	$from = strpos($source, 'function erp_drawer_waybill(');
	pg_assert_true($from !== false, 'the drawer function is there');
	$body = substr($source, $from, 6000);

	pg_assert_contains('erp_waybill_invoice_url($waybill)', $body, 'the drawer links through the helper');
	pg_assert_false(strpos($body, "'<a href=\"edit_erp_invoice.php?id=' . (int) \$waybill['invoice_id']") !== false, 'no link built without the state');
}

function test_open_action_uses_the_verb_key()
{
	$places = array(
		'erp_waybills.php' => "lang('Open it')",
		'includes/fn/audit_list.php' => "lang('Open it')",
		'includes/settings/security.php' => "lang('Open it')",
	);
	foreach ($places as $file => $needle) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains($needle, $source, $file . ' uses the verb key');
		pg_assert_false(strpos($source, "lang('Open')") !== false, $file . ' no longer uses the state key');
	}

	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_same(2, substr_count($designer, "esc(_sdT('Close')) : esc(_sdT('Open it'))"), 'both designer toggles use the verb key');
	pg_assert_false(strpos($designer, "esc(_sdT('Close')) : esc(_sdT('Open'))") !== false, 'no toggle reads the state key');
}
