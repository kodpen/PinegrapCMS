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
			'data-pg-open>\' . h($account[\'title\']) . \'</a>\' . $output_edoc_flag, \'align-middle text-wrap text-break\'',
		),
		'erp_aging.php' => array(
			'<td class="text-wrap text-break" style="max-width:280px" data-sort="\' . h($account[\'title\']) . \'">',
		),
		'erp_cash.php' => array(
			'\'iban\' => pg_list_cell(h($till[\'iban\']), \'align-middle text-wrap text-break\'),',
		),
		'erp_cheques.php' => array(
			'<td style="max-width:240px" class="text-break pg-cell-wrap">\' . h((string) $row[\'account_title\']) . (((string) $row[\'drawer\'] !== \'\') ? \'<div class="small fg-secondary text-break">',
		),
		'erp_dashboard.php' => array(
			'<td class="text-break" style="max-width:200px">\' . h($till[\'name\']) . \'</td>',
			'<td class="text-break" style="max-width:200px">\' . h($till[\'name\']) . \' <span class="small">\' . h(lang(\'not added in\')) . \'</span></td>',
			'<td class="text-break" style="max-width:220px"><a href="edit_erp_account.php?id=\' . (int) $account[\'id\']',
			'<td class="text-break" style="max-width:220px">\' . (((int) $movement[\'account_id\'] > 0)',
			'<div class="small fg-secondary">\' . h((string) $movement[\'till_name\']) . \'</div>',
		),
		'erp_inbox.php' => array(
			'<div class="text-break">\' . h($row[\'supplier_title\']) . \'</div><div class="small fg-secondary">\' . h($row[\'supplier_tax_number\']) . \'</div>\', \'align-middle text-wrap text-break\'',
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
			'#erp_stock_moves" data-pg-open>\' . h($name) . \'</a>\'',
			'\'align-middle text-wrap text-break\', array(\'data-pg-sort-value\' => $name)',
		),
		'erp_stock_minimums.php' => array(
			'\'align-middle text-wrap text-break\', array(\'data-pg-sort-value\' => $label)',
		),
		'erp_stock_counts.php' => array(
			'<td class="text-wrap text-break" style="max-width:280px"><a class="pg-link-body-emphasis fw-semibold" href="erp_stock_count.php?id=',
		),
		'erp_stock_count.php' => array(
			'<td class="text-wrap text-break" style="max-width:14rem">\' . h($name)',
		),
		'erp_account_sync.php' => array(
			'. \'<div class="small fg-secondary">\' . h(implode(\' · \', $sub)) . \'</div>\', \'align-middle text-wrap text-break\'',
			'. \'<div class="small fg-secondary">\' . h($row[\'a_tax_number\']) . \'</div>\', \'align-middle text-wrap text-break\'',
			'h($account[\'title\'])), \'align-middle text-wrap text-break\'',
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
