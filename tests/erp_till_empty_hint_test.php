<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the line the ERP till pickers show when no usable till is set up
 * yet (erp_no_till_hint(), includes/erp/cash.php): it names what is missing
 * and links to Open a Till, so a picker that holds nothing to choose is not
 * left for the save to refuse.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/cash.php');

function test_erp_no_till_hint_is_empty_when_enough_tills_exist()
{
	pg_assert_same('', erp_no_till_hint(1), 'one till is enough for a receipt');
	pg_assert_same('', erp_no_till_hint(3), 'more tills are enough');
	pg_assert_same('', erp_no_till_hint(2, 'transfer'), 'two tills are enough for a transfer');
	pg_assert_same('', erp_no_till_hint(1, 'cheque'), 'one bank account besides the portfolio is enough');
}

function test_erp_no_till_hint_names_the_missing_till_and_links_to_open_one()
{
	$none = erp_no_till_hint(0);
	pg_assert_contains('No till or bank account is set up yet.', $none, 'no till at all');
	pg_assert_contains('href="add_erp_till.php"', $none, 'links to Open a Till');
	pg_assert_contains('Open a Till', $none, 'the link says what it does');

	pg_assert_contains('No till or bank account is set up yet.', erp_no_till_hint(0, 'transfer'), 'a transfer with no till');
	$one = erp_no_till_hint(1, 'transfer');
	pg_assert_contains('A transfer needs a second till or bank account.', $one, 'a transfer with one till asks for a second');
	pg_assert_contains('href="add_erp_till.php"', $one, 'the transfer hint links too');

	$cheque = erp_no_till_hint(0, 'cheque');
	pg_assert_contains('No bank account is set up besides the till the cheque is kept in.', $cheque, 'the cheque steps need a till other than the portfolio');
	pg_assert_contains('href="add_erp_till.php"', $cheque, 'the cheque hint links too');
}

// Each screen whose save needs a till prints the hint from the same active-till
// query that fills its picker.
function test_erp_till_screens_print_the_hint()
{
	$screens = array(
		'add_erp_receipt.php' => 'erp_no_till_hint($till_count)',
		'add_erp_transfer.php' => "erp_no_till_hint(\$till_count, 'transfer')",
		'erp_bank_statements.php' => 'erp_no_till_hint($till_count)',
		'erp_cheque.php' => "erp_no_till_hint(\$till_count, 'cheque')",
		'add_erp_expense.php' => 'erp_no_till_hint($till_count)',
	);

	foreach ($screens as $file => $call) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains($call, $source, $file . ' prints the hint');
		pg_assert_contains('$till_count++', $source, $file . ' counts the tills it offers');
	}
}

// The refusals stay as they are: the API returns the same texts.
function test_erp_till_refusals_are_kept()
{
	$cash = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/cash.php');
	pg_assert_contains("lang('Choose a till or bank account.')", $cash, 'receipt refusal');
	pg_assert_contains("lang('Choose both accounts.')", $cash, 'transfer refusal');
	pg_assert_contains("lang('Choose two different accounts.')", $cash, 'transfer refusal for one till');
}
