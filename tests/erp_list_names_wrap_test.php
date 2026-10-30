<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for ERP list cells that hold account, supplier, till and
 * product names or IBANs: they wrap (text-break) inside their max-width
 * instead of being cut, and cells inside DataTables (table.chart, whose td
 * is nowrap) also carry text-wrap. The account drawer title wraps too.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_list_names_wrap_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function erp_list_names_wrap_pins()
{
	return array(
		'includes/erp/drawer.php' => array(
			'<h5 class="drawer-title text-break me-auto" id="erp_drawer_title"></h5>',
		),
		'erp_accounts.php' => array(
			'<td style="max-width:220px" class="text-wrap text-break align-middle chart_label\' . $status_class . \'">\' . h($account[\'title\']) . $output_edoc_flag . \'</td>',
		),
		'erp_aging.php' => array(
			'<td style="max-width:280px" class="text-wrap text-break align-middle chart_label">\' . h($account[\'title\'])',
		),
		'erp_cash.php' => array(
			'<td style="max-width:220px" class="text-wrap text-break align-middle">\' . h($till[\'iban\']) . \'</td>',
		),
		'erp_cheques.php' => array(
			'<td style="max-width:240px" class="text-break">\' . h((string) $row[\'account_title\']) . (((string) $row[\'drawer\'] !== \'\') ? \'<div class="small fg-secondary text-break">',
		),
		'erp_dashboard.php' => array(
			'<td class="text-break" style="max-width:200px">\' . h($till[\'name\']) . \'</td>',
			'<td class="text-break" style="max-width:200px">\' . h($till[\'name\']) . \' <span class="small">\' . h(lang(\'not added in\')) . \'</span></td>',
			'<td class="text-break" style="max-width:220px"><a href="edit_erp_account.php?id=\' . (int) $account[\'id\']',
			'<td class="text-break" style="max-width:220px">\' . (((int) $movement[\'account_id\'] > 0)',
			'<td class="text-break fg-secondary" style="max-width:160px">\' . h((string) $movement[\'till_name\']) . \'</td>',
		),
		'erp_inbox.php' => array(
			'<td style="max-width:280px" class="align-middle text-wrap text-break">',
			'<div class="text-break">\' . h($row[\'supplier_title\']) . \'</div>',
		),
		'erp_invoice_recurrences.php' => array(
			'<td style="max-width:260px" class="text-break">\' . h((string) $row[\'account_title\']) . \'</td>',
		),
		'erp_purchase_orders.php' => array(
			'<td style="max-width:280px" class="text-break">\' . h((string) $row[\'account_title\']) . \'</td>',
		),
		'erp_quotes.php' => array(
			'<td style="max-width:280px" class="text-break">\' . h((string) $row[\'account_title\']) . \'</td>',
		),
		'erp_stock.php' => array(
			'<td style="max-width:280px" class="text-wrap text-break align-middle">\' . h($name)',
		),
		'erp_account_sync.php' => array(
			'return \'<div class="text-wrap text-break">\' . h(((string) $row[\'title\'] !== \'\')',
			'<td style="max-width:320px" class="align-middle text-wrap text-break"><a class="pg-link-body-emphasis" href="edit_erp_account.php?id=\' . (int) $row[\'a_id\'] . \'">',
			'<td style="max-width:320px" class="align-middle text-wrap text-break"><a class="pg-link-body-emphasis" href="edit_erp_account.php?id=\' . (int) $account[\'id\'] . \'">',
			'<td style="max-width:320px" class="align-middle text-wrap text-break">\' . h($account[\'title\']) . \'</td>',
		),
		'erp_expenses.php' => array(
			'<a class="pg-link-body-emphasis text-decoration-none text-break" href="edit_erp_expense.php?id=\' . (int) $recurrence[\'source_expense_id\'] . $output_send_to . \'">\' . h($label) . \'</a>',
			'<span class="text-break">\' . h(($periods[(int) $recurrence[\'every_months\']] ?? \'\')',
		),
	);
}

function test_erp_list_names_wrap_cells_break()
{
	foreach (erp_list_names_wrap_pins() as $path => $pins) {
		$source = erp_list_names_wrap_source($path);

		foreach ($pins as $pin) {
			pg_assert_contains($pin, $source, $path);
		}
	}
}

function test_erp_list_names_wrap_no_cut_name_cells()
{
	$cut = array(
		'includes/erp/drawer.php' => array('drawer-title text-truncate'),
		'erp_accounts.php' => array('text-truncate align-middle chart_label'),
		'erp_aging.php' => array('text-nowrap text-truncate align-middle chart_label'),
		'erp_cash.php' => array('text-truncate align-middle">\' . h($till[\'iban\'])'),
		'erp_cheques.php' => array('class="text-truncate">\' . h((string) $row[\'account_title\'])', 'fg-secondary text-truncate">\' . h((string) $row[\'drawer\'])'),
		'erp_dashboard.php' => array('<td class="text-truncate'),
		'erp_inbox.php' => array('<div class="text-truncate">\' . h($row[\'supplier_title\'])', 'align-middle text-truncate">'),
		'erp_invoice_recurrences.php' => array('class="text-truncate">\' . h((string) $row[\'account_title\'])'),
		'erp_purchase_orders.php' => array('class="text-truncate">\' . h((string) $row[\'account_title\'])'),
		'erp_quotes.php' => array('class="text-truncate">\' . h((string) $row[\'account_title\'])'),
		'erp_stock.php' => array('class="text-truncate align-middle">\' . h($name)'),
		'erp_account_sync.php' => array('<div class="text-truncate">', 'align-middle text-truncate'),
		'erp_expenses.php' => array('text-truncate" href="edit_erp_expense.php', '<span class="text-truncate">'),
	);

	foreach ($cut as $path => $needles) {
		$source = erp_list_names_wrap_source($path);

		foreach ($needles as $needle) {
			pg_assert_true(strpos($source, $needle) === false, $path . ': a name cell is still cut: ' . $needle);
		}
	}
}
