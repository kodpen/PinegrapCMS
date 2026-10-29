<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for ERP marks and reasons that a hover popover alone would
 * hide: the invoice cancel reasons shown as text, the accounts list's
 * e-document and over-limit marks, the snoozed reminders bell, the signed
 * quote pen, the repeating expense error, the account sync radio groups and
 * search box, and the accountant link's copy announcement.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_marks_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_erp_marks_names_invoice_cancel_reasons()
{
	$source = erp_marks_names_source('edit_erp_invoice.php');

	pg_assert_true(strpos($source, 'tabindex="0" title="\' . h($cancel_locked)') === false, 'the lock reason is not kept in a hover-only title');
	pg_assert_true(strpos($source, "title=\"' . h((string) \$provider_cancellable['reason'])") === false, 'the provider reason is not kept in a hover-only title');
	pg_assert_contains('<p class="small fg-secondary text-center mx-auto mb-7" style="max-width:40rem" id="erp_cancel_note">\' . h($cancel_note) . \'</p>', $source);
	pg_assert_contains('aria-describedby="erp_cancel_note"', $source);
}

function test_erp_marks_names_accounts_list()
{
	$source = erp_marks_names_source('erp_accounts.php');

	pg_assert_contains('<i class="bi bi-exclamation-triangle-fill fg-warning" aria-hidden="true" title="\' . h($edoc_gap_text) . \'"></i><span class="visually-hidden">\' . h($edoc_gap_text) . \'</span>', $source);
	pg_assert_contains('<span class="badge theme-danger" title="\' . h($credit_text) . \'">\' . lang(\'Over limit\') . \'</span><span class="visually-hidden"> \' . h($credit_text) . \'</span>', $source);
}

function test_erp_marks_names_snoozed_bell()
{
	$source = erp_marks_names_source('erp_invoices.php');

	pg_assert_contains('<i class="bi bi-bell-slash fg-secondary" aria-hidden="true" title="\' . h($snoozed_text) . \'"></i><span class="visually-hidden">\' . h($snoozed_text) . \'</span>', $source);
}

function test_erp_marks_names_signed_pen()
{
	foreach (array('erp_quotes.php', 'edit_erp_quote.php') as $path) {
		$source = erp_marks_names_source($path);

		pg_assert_true(strpos($source, '<i class="bi bi-pen fg-success" title="\' . h(lang(\'Signed\')) . \'" aria-label=') === false, $path . ': no aria-label on a role-less icon');
		pg_assert_true(strpos($source, 'align-middle" title="\' . h(lang(\'Signed\')) . \'" aria-label=') === false, $path . ': no aria-label on a role-less icon');
		pg_assert_contains('aria-hidden="true"></i><span class="visually-hidden">\' . h(lang(\'Signed\')) . \'</span>', $source, $path);
	}
}

function test_erp_marks_names_expense_error()
{
	$source = erp_marks_names_source('erp_expenses.php');

	pg_assert_contains('<div class="small fg-emphasis-warning text-break"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>\' . h((string) $recurrence[\'last_error\']) . \'</div>', $source);
}

function test_erp_marks_names_account_sync()
{
	$source = erp_marks_names_source('erp_account_sync_item.php');

	pg_assert_contains('<div role="radiogroup" aria-label="\' . h($label) . \'">\' . $radios . \'</div>', $source);
	pg_assert_contains('placeholder="\' . h($placeholder) . \'" aria-label="\' . h(lang(\'Search\')) . \'"', $source);
}

function test_erp_marks_names_accountant_copy()
{
	$source = erp_marks_names_source('erp_accountant.php');

	pg_assert_true(strpos($source, 'button.innerHTML = "<i class=\\"bi bi-check-lg\\"') === false, 'the copy button keeps its label');
	pg_assert_contains('<span class="visually-hidden" role="status" aria-live="polite" id="erp_accountant_link_status" data-copied="\' . h(lang(\'Copied\')) . \'" data-refused="\' . h(lang(\'The browser did not let the text be copied. Select it and copy it from here.\')) . \'"></span>', $source);
	pg_assert_contains('status.textContent = status.getAttribute("data-copied");', $source);
}
