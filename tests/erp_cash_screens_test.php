<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Cash and Bank screens and their doors: a receipt, payment
 * or transfer is never written to a till that is missing or taken off
 * (erp_cash_till_row_refusal(), includes/erp/cash.php), the read-only
 * accountant is offered no write on the cash screens, the receipt form's
 * invoice list follows the chosen account, and the small wording fixes.
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

// The body of one function in a source file, up to the next top-level function.
function erp_cash_screens_test_body($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function erp_cash_screens_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_erp_cash_till_row_refusal()
{
	pg_assert_same('', erp_cash_till_row_refusal(array('id' => 3, 'is_active' => 1)), 'an active till takes the movement');
	pg_assert_same('', erp_cash_till_row_refusal(array('id' => 3, 'is_active' => '1')), 'database strings are read as numbers');
	pg_assert_same(lang('That till or bank account is not active.'), erp_cash_till_row_refusal(array('id' => 3, 'is_active' => 0)), 'a till taken off is refused');
	pg_assert_same(lang('That till or bank account could not be found.'), erp_cash_till_row_refusal(null), 'a missing till is refused');
	pg_assert_same('', erp_cash_till_row_refusal(array('id' => 3, 'is_active' => 0), true), 'a cheque step may still leave a till taken off');
	pg_assert_same(lang('That till or bank account could not be found.'), erp_cash_till_row_refusal(null, true), 'but never a missing one');
}

function test_erp_cash_doors_ask_the_till_gate()
{
	pg_assert_true(strpos(erp_cash_screens_test_body('includes/erp/cash.php', 'erp_post_receipt_check'), "erp_cash_till_refusal(\$cash_account_id, !empty(\$data['allow_inactive_till']))") !== false, 'the receipt check asks the till gate');
	pg_assert_true(strpos(erp_cash_screens_test_body('includes/erp/cash.php', 'erp_post_transfer'), "erp_cash_till_refusal(\$till_id, !empty(\$data['allow_inactive_till']))") !== false, 'the transfer asks it for both tills');

	// Only a cheque's later steps move money already in a till; its entry
	// still needs an active till.
	$step = erp_cash_screens_test_source('includes/erp/cheques.php');
	pg_assert_same(2, substr_count($step, "'allow_inactive_till' => true"), 'the two step postings of a cheque skip the active check');
}

function test_erp_cash_screens_hide_writes_from_the_accountant()
{
	foreach (array('erp_cash.php', 'edit_erp_till.php', 'erp_receipt.php') as $file) {
		pg_assert_true(strpos(erp_cash_screens_test_source($file), "\$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;") !== false, $file . ' reads the read-only right');
	}

	pg_assert_true(strpos(erp_cash_screens_test_source('erp_cash.php'), "(\$readonly ? '' : '<a class=\"btn btn-sm btn-solid theme-primary rounded-pill px-5\" href=\"add_erp_receipt.php?direction=collection\"") !== false, 'the list hides the write buttons');
	pg_assert_true(strpos(erp_cash_screens_test_source('edit_erp_till.php'), "(\$readonly ? '<fieldset disabled>'") !== false, 'the till form is shown disabled, without its save bar');
	pg_assert_true(strpos(erp_cash_screens_test_source('erp_receipt.php'), "} elseif (\$readonly) {\n    \$output_cancel = '';") !== false, 'the receipt view offers no cancel');

	foreach (array('add_erp_till.php', 'add_erp_transfer.php', 'add_erp_receipt.php') as $file) {
		pg_assert_true(strpos(erp_cash_screens_test_source($file), "USER_ERP_READONLY && !validate_erp_access(\$user, 'write')") !== false, $file . ' refuses the accountant on arrival');
	}
}

function test_erp_cash_receipt_invoice_list_follows_the_account()
{
	$source = erp_cash_screens_test_source('add_erp_receipt.php');

	pg_assert_true(strpos($source, 'LIMIT 200") ') === false && strpos($source, ">= 200") !== false, 'the 200 cap is per account');
	pg_assert_true(strpos($source, 'id="erp_receipt_invoice_accounts"') !== false, 'the invoice owners reach the page script');
	pg_assert_true(strpos($source, 'window.jQuery(document).on("change", "#account_id", filter);') !== false, 'the list is filtered when the account changes');
}

function test_erp_cash_screen_wording()
{
	pg_assert_true(strpos(erp_cash_screens_test_source('add_erp_receipt.php'), "lang('Card')] = 'card';") !== false, 'the form names the method as every report does');
	pg_assert_true(strpos(erp_cash_screens_test_source('erp_receipt.php'), "lang('The receipt has been cancelled.')") !== false, 'no "0 invoice(s) reopened"');
	pg_assert_true(strpos(erp_cash_screens_test_source('edit_erp_till.php'), "erp_money_input((int) \$till['opening_balance'])") !== false, 'the opening balance box holds a plain figure');
}

// The till form refuses an opening figure that is not a number (erp_kurus()
// would save it as 0) and an IBAN that fails the ISO 13616 check, and
// stores the IBAN upper case without spaces.
function test_erp_cash_screens_till_form_checks_opening_and_iban()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/erp/till_form.php');

	$opening = lang('Enter the opening balance as a number.');
	pg_assert_same('', erp_till_opening_refusal(''), 'an empty opening figure is 0');
	pg_assert_same('', erp_till_opening_refusal('-1.234,56'), 'a negative figure with marks passes');
	pg_assert_same('', erp_till_opening_refusal(' 1 234.56 '), 'spaces as thousands marks pass');
	pg_assert_same($opening, erp_till_opening_refusal('abc'), 'letters are refused');
	pg_assert_same($opening, erp_till_opening_refusal('1O0,00'), 'a letter O typed for a zero is refused');
	pg_assert_same($opening, erp_till_opening_refusal(','), 'a mark without digits is refused');

	pg_assert_same('TR330006100519786457841326', erp_till_iban_normalise('tr33 0006 1005 1978 6457 8413 26'), 'stored upper case without spaces');

	$iban = lang('This IBAN is not valid. Check it for a typing mistake.');
	pg_assert_same('', erp_till_iban_refusal(''), 'a till without an IBAN passes');
	pg_assert_same('', erp_till_iban_refusal('tr33 0006 1005 1978 6457 8413 26'), 'a valid Turkish IBAN passes as typed');
	pg_assert_same('', erp_till_iban_refusal('GB82 WEST 1234 5698 7654 32'), 'a valid IBAN with letters passes');
	pg_assert_same('', erp_till_iban_refusal('MT84 MALT 0110 0001 2345 MTLC AST0 01S'), 'a 31-character IBAN passes');
	pg_assert_same($iban, erp_till_iban_refusal('TR33 0006 1005 1978 6457 8413 27'), 'a wrong check digit is refused');
	pg_assert_same($iban, erp_till_iban_refusal('12345'), 'a number that is not an IBAN is refused');

	foreach (array('add_erp_till.php', 'edit_erp_till.php') as $file) {
		$source = erp_cash_screens_test_source($file);
		pg_assert_contains("mark_error('opening_balance', \$refusal)", $source, $file . ': the opening figure is checked');
		pg_assert_contains("mark_error('iban', \$refusal)", $source, $file . ': the IBAN is checked');
		pg_assert_contains('erp_till_iban_normalise(', $source, $file . ': the IBAN is stored normalised');
	}
}

// The create screen lays the till form out as a form column, a sticky status
// column holding the Status field and a sticky save bar; the edit screen keeps
// Status inside the form card, so the field is drawn exactly once on each.
function test_erp_cash_screens_add_till_wide_layout()
{
	$source = erp_cash_screens_test_source('add_erp_till.php');
	pg_assert_contains('lg:col-8 2xl:col-9 order-1', $source, 'the form column');
	pg_assert_contains('lg:col-4 2xl:col-3 order-2', $source, 'the status column');
	pg_assert_contains('erp_till_form_cards($liveform, false, false)', $source, 'the form card without Status');
	pg_assert_contains('erp_till_form_status_card($liveform)', $source, 'Status in the status column');
	pg_assert_true(strpos($source, 'order-1') < strpos($source, 'order-2'), 'the status column follows the form column in source order');

	$edit = erp_cash_screens_test_source('edit_erp_till.php');
	pg_assert_true(strpos($edit, 'erp_till_form_status_card(') === false, 'the edit screen keeps Status in the form card');

	$cards = erp_cash_screens_test_body('includes/erp/till_form.php', 'erp_till_form_cards');
	pg_assert_contains('$with_status ?', $cards, 'Status is left out only on request');
	pg_assert_contains('erp_till_form_status_field($liveform)', erp_cash_screens_test_body('includes/erp/till_form.php', 'erp_till_form_status_card'), 'the card draws the same select');
}
