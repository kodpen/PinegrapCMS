<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A liveform message is HTML: the values the messages of the ERP screens
 * carry from stored records, panel input and remote services go in through
 * h(). Each row is a file, one line of it and how many times that line
 * occurs, so an escape that is taken out again is noticed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_message_values_erp_are_escaped()
{
	$lines = array(
		array('add_erp_expense.php', '\'vars\' => array(h($currency), prepare_form_data_for_output($expense_date, \'date\')),', 1),
		array('add_erp_invoice.php', '$liveform->mark_error(\'_error\', h($result[\'error\']));', 1),
		array('add_erp_invoice.php', '$liveform_document->add_notice(lang(array(\'string\' => \'Invoice {var:1} created.\', \'vars\' => h($result[\'full_number\']))));', 1),
		array('add_erp_invoice.php', '$liveform_order->mark_error(\'_error\', h($result[\'error\']));', 1),
		array('add_erp_manual_invoice.php', '$liveform->mark_error($result[\'field\'] ?? \'_error\', h($result[\'error\']));', 2),
		array('add_erp_manual_invoice.php', '$liveform_document->add_notice(lang(array(\'string\' => \'Invoice {var:1} created.\', \'vars\' => h($result[\'full_number\']))));', 1),
		array('add_erp_purchase_order.php', '$liveform->mark_error($result[\'field\'] ?? \'_error\', h($result[\'error\']));', 1),
		array('add_erp_quote.php', '$liveform->mark_error($result[\'field\'] ?? \'_error\', h($result[\'error\']));', 1),
		array('add_erp_receipt.php', '$liveform->mark_error(\'_error\', h($result[\'error\']));', 1),
		array('add_erp_receipt.php', '\'vars\' => array(h($currency), prepare_form_data_for_output($doc_date_sql, \'date\')),', 1),
		array('add_erp_return.php', '$liveform->mark_error(\'_error\', h($result[\'error\']));', 1),
		array('add_erp_return.php', '$liveform_document->add_notice(lang(array(\'string\' => \'Return {var:1} created.\', \'vars\' => h($result[\'full_number\']))));', 1),
		array('add_erp_return.php', '$liveform_order->add_notice(lang(array(\'string\' => \'Return {var:1} created.\', \'vars\' => h($result[\'full_number\']))));', 1),
		array('add_erp_waybill.php', '$liveform->mark_error(\'_error\', h($result[\'error\']));', 1),
		array('add_erp_waybill.php', '$liveform_document->add_notice(lang(array(\'string\' => \'Delivery note {var:1} created.\', \'vars\' => h($result[\'full_number\']))));', 1),
		array('add_erp_waybill.php', '$liveform_document->add_notice(lang(array(\'string\' => \'Invoice {var:1} already has delivery note {var:2}.\', \'vars\' => array(h($invoice[\'full_number\']), h($existing_for_invoice[0][\'full_number\'])))));', 1),
		array('add_erp_waybill.php', '$liveform_document->add_notice(lang(array(\'string\' => \'Order #{var:1} already has delivery note {var:2}.\', \'vars\' => array(($order_number !== \'\') ? $order_number : (string) $order_id, h($existing[0][\'full_number\'])))));', 1),
		array('edit_erp_invoice.php', '$liveform->add_notice(h($provider_cancel[\'message\']));', 1),
		array('edit_erp_invoice.php', '$liveform->add_notice(h($result[\'message\']));', 5),
		array('edit_erp_invoice.php', '$liveform->mark_error(\'_error\', h($provider_cancel[\'error\']));', 1),
		array('edit_erp_invoice.php', '$liveform->mark_error(\'_error\', h($result[\'error\']));', 6),
		array('edit_erp_invoice_draft.php', '$liveform->mark_error($saved[\'field\'] ?? \'_error\', h($saved[\'error\']));', 1),
		array('edit_erp_invoice_draft.php', '$liveform->mark_error(\'_error\', $issued[\'success\'] ? erp_db_error() : h($issued[\'error\']));', 1),
		array('edit_erp_invoice_draft.php', '$liveform_document->add_notice(lang(array(\'string\' => \'Invoice {var:1} created.\', \'vars\' => h($issued[\'full_number\']))));', 1),
		array('edit_erp_purchase_order.php', '$liveform->mark_error($result[\'field\'] ?? \'_error\', h($result[\'error\']));', 1),
		array('edit_erp_quote.php', '$liveform->mark_error($result[\'field\'] ?? \'_error\', h($result[\'error\']));', 1),
		array('edit_erp_waybill.php', '$liveform->mark_error(\'_error\', h($result[\'error\']));', 1),
		array('edit_erp_waybill.php', '$liveform_document->add_notice(lang(array(\'string\' => \'Delivery note {var:1} has been invoiced.\', \'vars\' => h($waybill[\'full_number\']))));', 1),
		array('edit_erp_waybill.php', '$liveform_draft->add_notice(lang(array(\'string\' => \'The lines of delivery note {var:1} are on this draft. Fill in the prices and issue the invoice.\', \'vars\' => h($waybill[\'full_number\']))));', 1),
		array('erp_inbox.php', '$liveform->mark_error(\'_error\', h($result[\'error\']));', 1),
		array('erp_inbox_document.php', '$liveform->mark_error(\'_error\', h($result[\'error\']));', 4),
		array('erp_receipt.php', '$liveform->mark_error(\'_error\', h($error));', 1),
		array('erp_receipt.php', '$liveform->mark_error(\'_error\', h($result[\'error\']));', 1),
		array('erp_reconciliation.php', '$liveform->add_notice(lang(array(\'string\' => \'The reconciliation letter was e-mailed to {var:1}.\', \'vars\' => h($to))));', 1),
	);

	foreach ($lines as $line) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $line[0]);

		pg_assert_same($line[2], substr_count($source, $line[1]), $line[0] . ': ' . $line[1]);
	}
}
