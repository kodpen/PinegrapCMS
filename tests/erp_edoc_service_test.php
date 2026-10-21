<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the e-document service
 * (includes/erp/edoc/service.php): when a new send would duplicate a record
 * the provider already holds.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/service.php');

// A record the provider holds is never created a second time: a draft there,
// and one a later step failed on while its id is still on the row (the
// conversion job that failed at Paraşüt). Without an id nothing was written
// there, and a rejected document is answered with a new one.
function test_erp_edoc_invoice_held_refusal()
{
	pg_assert_contains('already at the provider as a draft', erp_edoc_invoice_held_refusal(array('edoc_status' => 'created', 'edoc_external_id' => '5001')));

	$held = erp_edoc_invoice_held_refusal(array('edoc_status' => 'error', 'edoc_external_id' => '5001'));
	pg_assert_contains('still holds the document (id 5001)', $held, 'a failed later step keeps the record');
	pg_assert_contains('Ask after its status', $held);
	pg_assert_contains('id 5001', erp_edoc_invoice_held_refusal(array('edoc_status' => 'error', 'edoc_external_id' => ' 5001 ')), 'the id trimmed');

	pg_assert_same('', erp_edoc_invoice_held_refusal(array('edoc_status' => 'error', 'edoc_external_id' => '')), 'a send that failed before the provider wrote anything');
	pg_assert_same('', erp_edoc_invoice_held_refusal(array('edoc_status' => 'error')), 'an old row without the column');
	pg_assert_same('', erp_edoc_invoice_held_refusal(array('edoc_status' => 'none', 'edoc_external_id' => '')));
	pg_assert_same('', erp_edoc_invoice_held_refusal(array('edoc_status' => 'rejected', 'edoc_external_id' => '5001')), 'a rejected document is answered with a new one');
}
