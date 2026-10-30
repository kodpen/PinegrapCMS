<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source-level tests: a refused save of the accountant settings
 * (erp_accountant.php) and a refused Pay or Repeat of an expense
 * (edit_erp_expense.php) keep what the user chose. The refusal stores the
 * posted fields, and the form reads them back instead of the stored values.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The PHP between the first $needle and the next go( call.
function erp_refused_forms_branch($source, $needle)
{
	$start = strpos($source, $needle);

	if ($start === false) {
		return '';
	}

	$end = strpos($source, 'go(', $start);

	return (string) substr($source, $start, ($end === false) ? null : ($end - $start));
}

function test_erp_accountant_settings_refusal_keeps_choices()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_accountant.php');

	$branch = erp_refused_forms_branch($source, 'if (!$saved[\'success\']) {');
	pg_assert_contains('$liveform->add_fields_to_session();', $branch, 'the refused settings save stores the posted fields');

	pg_assert_contains('$liveform->get_field_value(\'accountant_email\')', $source, 'the e-mail box reads the posted address');
	pg_assert_contains('$liveform->get_field_value(\'accountant_monthly\')', $source, 'the Monthly switch reads the posted choice');
	pg_assert_contains('$liveform->get_field_value(\'accountant_pdfs\')', $source, 'the PDFs switch reads the posted choice');
	pg_assert_contains('name="accountant_email" value="\' . h($settings_form[\'email\']) . \'"', $source, 'the e-mail box draws from the kept settings');
	pg_assert_contains('($settings_form[\'monthly\'] ? \' checked\' : \'\')', $source, 'the Monthly switch draws from the kept settings');
	pg_assert_contains('($settings_form[\'pdfs\'] ? \' checked\' : \'\')', $source, 'the PDFs switch draws from the kept settings');
	pg_assert_false(strpos($source, 'name="accountant_email" value="\' . h($settings[\'email\']) . \'"') !== false, 'the e-mail box no longer reads only the stored address');
}

function test_erp_expense_pay_and_repeat_refusals_keep_choices()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_expense.php');

	$pay_date = erp_refused_forms_branch($source, 'if (($paid_date !== \'\') && (validate_date($paid_date) == false)) {');
	pg_assert_contains('$liveform->add_fields_to_session();', $pay_date, 'a pay date refusal stores the posted fields');

	$pay = erp_refused_forms_branch($source, 'if (!$result[\'success\']) {');
	pg_assert_contains('$liveform->add_fields_to_session();', $pay, 'a pay refusal stores the posted fields');

	$repeat_end = erp_refused_forms_branch($source, 'if (($repeat_end !== \'\') && (validate_date($repeat_end) == false)) {');
	pg_assert_contains('$liveform->add_fields_to_session();', $repeat_end, 'a repeat end date refusal stores the posted fields');

	$repeat = erp_refused_forms_branch($source, 'if (!$repeat[\'success\']) {');
	pg_assert_contains('$liveform->add_fields_to_session();', $repeat, 'a repeat refusal stores the posted fields');

	// Each form reads back only its own post, so a refused Pay leaves Repeat alone.
	pg_assert_contains('(string) $liveform->get_field_value(\'erp_action\') === $form', $source, 'kept values follow the form that was sent');
	pg_assert_contains('$till_options($kept(\'pay\') ? (int) $liveform->get_field_value(\'cash_account_id\') : 0)', $source, 'Paid from keeps the chosen till');
	pg_assert_contains('$method_options($kept(\'pay\') ? (string) $liveform->get_field_value(\'payment_method\') : \'cash\')', $source, 'Payment Method keeps the chosen method');
	pg_assert_contains('$repeat_every = (int) $liveform->get_field_value(\'repeat_every\');', $source, 'How often keeps the chosen period');
	pg_assert_contains('$liveform->get_field_value(\'repeat_due_days\')', $source, 'Due after keeps the typed days');
	pg_assert_contains('$liveform->get_field_value(\'repeat_pay\')', $source, 'Write the next ones as paid keeps its switch');
}
