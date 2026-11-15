<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP lists in the look's list recipe: pg_pill() and its tones, the plain
 * list tables' class, the status pills in place of badges, the Expenses
 * filter card's labels.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once dirname(__DIR__) . '/pinegrap/includes/fn/output.php';

function erp_list_look_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_erp_list_look_pill_tones()
{
	pg_assert_same('teal', pg_pill_tone('success'));
	pg_assert_same('amber', pg_pill_tone('warning'));
	pg_assert_same('rose', pg_pill_tone('danger'));
	pg_assert_same('blue', pg_pill_tone('primary'));
	pg_assert_same('violet', pg_pill_tone('info'));
	pg_assert_same('gray', pg_pill_tone('secondary'));
	pg_assert_same('gray', pg_pill_tone('inverse'));
	pg_assert_same('teal', pg_pill_tone('teal'), 'a tone passes through');
	pg_assert_same('gray', pg_pill_tone('" onclick="x'), 'anything else is gray');
}

function test_erp_list_look_pill_markup()
{
	pg_assert_same('<span class="pg-pill pg-tone-rose">A &amp; B</span>', pg_pill('A & B', 'danger'));
	pg_assert_same('<span class="pg-pill pg-tone-gray" title="x">C</span>', pg_pill('C', 'secondary', 'title="x"'));
}

function test_erp_list_look_tables()
{
	$files = array(
		'erp_expenses.php' => 1, 'erp_expense_categories.php' => 1, 'erp_cheques.php' => 1,
		'erp_bank_statements.php' => 1, 'erp_purchase_orders.php' => 1, 'erp_quotes.php' => 1,
		'erp_invoice_recurrences.php' => 1, 'erp_stock_counts.php' => 1, 'erp_audit.php' => 1,
		'erp_stock.php' => 2,
	);

	foreach ($files as $file => $count) {
		$source = erp_list_look_source($file);
		pg_assert_same($count, substr_count($source, '<table class="table erp-list-table '), $file . ': its list tables take the list recipe');
	}

	$css = erp_list_look_source('assets/css/backend.src.css');
	pg_assert_contains('.erp-list-table > thead > tr > th {', $css);
	pg_assert_contains("  font-size: var(--pg-text-meta);\n  font-weight: var(--pg-weight-strong);\n  color: var(--pg-fg-3);", $css, 'the heading: 12 px, 550, third text');
}

function test_erp_list_look_no_badges()
{
	$files = array(
		'erp_expenses.php', 'erp_cheques.php', 'erp_bank_statements.php', 'erp_purchase_orders.php',
		'erp_quotes.php', 'erp_invoice_recurrences.php', 'erp_stock_counts.php', 'erp_audit.php',
		'erp_accounts.php', 'erp_invoices.php', 'erp_waybills.php', 'erp_inbox.php',
		'erp_account_sync.php', 'erp_stock_minimums.php',
	);

	foreach ($files as $file) {
		$source = erp_list_look_source($file);
		// The e-document menu's count badges are counts, not statuses.
		pg_assert_same(0, preg_match_all('/class="badge (?!rounded-pill theme-\' \. \(\(\$edoc_counts)/', $source), $file . ': no status badge left');
		pg_assert_contains('pg_pill(', $source, $file . ': status pills');
	}
}

function test_erp_list_look_filter_labels()
{
	$source = erp_list_look_source('erp_expenses.php');
	pg_assert_same(6, substr_count($source, '<label class="pg-field-label" for='), 'the filter card labels');
	pg_assert_false(strpos($source, 'form-label small fg-secondary') !== false, 'no old label left');
}
