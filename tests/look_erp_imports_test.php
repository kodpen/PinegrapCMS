<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the ERP import, matching and settings screens in look A:
 * their card titles are the look's section title, a form that only wrapped
 * a card is the card, cards outside the form and status columns take the
 * recipe card, and the fields sit on the 12-column grid by their kind.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function look_erp_imports_test_read($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function look_erp_imports_test_files()
{
	return array(
		'erp_account_sync_item.php',
		'erp_bank_statement.php',
		'erp_reconciliation.php',
		'erp_inbox_document.php',
		'erp_stock_count.php',
		'erp_settings.php',
		'erp_accounts_import.php',
		'erp_export.php',
		'erp_accountant.php',
	);
}

function test_erp_import_screens_have_the_look_title()
{
	foreach (look_erp_imports_test_files() as $file) {
		$source = look_erp_imports_test_read($file);
		pg_assert_same(false, strpos($source, 'text-uppercase h5 fg-primary fw-bold'), $file . ' keeps no upper-case card title');
		pg_assert_same(false, strpos($source, 'class="small fg-secondary'), $file . ' writes its secondary notes with the look\'s text class');
		pg_assert_contains('pg-title-section', $source, $file . ' titles its cards with the section title');
		pg_assert_true((bool) preg_match("/'extra classes'\\s*=>\\s*'[^']*\\bpg-measure-edit\\b/", $source), $file . ' takes the edit width');
	}

	pg_assert_contains('<div class="pg-group-label mb-3">', look_erp_imports_test_read('erp_settings.php'), 'a placeholder group is titled with the group label');
}

function test_erp_import_forms_are_their_cards()
{
	foreach (array(
		array('erp_settings.php', 'id="erp_late_fee" class="card my-7">'),
		array('erp_settings.php', 'id="erp_efatura_threshold" class="card my-7">'),
		array('erp_settings.php', 'id="erp_rules" class="card my-7">'),
		array('erp_settings.php', 'method="post" class="card my-7">'),
		array('erp_account_sync_item.php', 'id="erp_account_align" class="card my-7">'),
		array('erp_stock_count.php', 'id="erp_stock_count_lines" class="card mb-7">'),
	) as $case) {
		pg_assert_contains($case[1], look_erp_imports_test_read($case[0]), $case[0] . ': the form is the card');
	}
}

function test_erp_import_cards_outside_the_columns_take_the_recipe()
{
	$accountant = look_erp_imports_test_read('erp_accountant.php');
	pg_assert_same(4, substr_count($accountant, '<div class="card pg-card my-7">'), 'the accountant pack\'s four cards are look cards');
	pg_assert_same(2, substr_count(look_erp_imports_test_read('erp_accounts_import.php'), '<div class="card pg-card my-7">'), 'the import result\'s cards are look cards');
	pg_assert_contains('<div class="card pg-card my-7">', look_erp_imports_test_read('erp_inbox_document.php'), 'the totals card is a look card');
	pg_assert_true((bool) preg_match('#2xl:col-3 order-2">\s*<div class="position-sticky" style="top:3.3rem;">\s*<div class="card my-7">#', look_erp_imports_test_read('erp_accounts_import.php')), 'the upload step\'s side card sits in the column\'s box');
}

function test_erp_import_fields_sit_on_the_grid()
{
	$reconciliation = look_erp_imports_test_read('erp_reconciliation.php');
	$export = look_erp_imports_test_read('erp_export.php');

	// A date or a count 3 columns, a select 6, a code 4.
	foreach (array(
		array($reconciliation, '<div class="col-12 md:col-3 my-3">' . "\n" . '                                <div class="form-field">' . "\n" . '                                    <label for="as_of"'),
		array($reconciliation, '<div class="col-12 md:col-3 my-3">' . "\n" . '                                <div class="form-field">' . "\n" . '                                    <label for="reply_days"'),
		array($export, '<div class="col-12 md:col-6 my-3">' . "\n" . '                                <label for="profile"'),
		array($export, '<div class="col-12 md:col-3 my-3">' . "\n" . '                                <label for="from"'),
		array(look_erp_imports_test_read('erp_accounts_import.php'), '<div class="col-12 md:col-6 my-3">' . "\n" . '                                        <label for="default_kind"'),
		array(look_erp_imports_test_read('erp_stock_count.php'), '<div class="col-12 md:col-4">' . "\n" . '                        <label class="form-label" for="code">'),
	) as $case) {
		pg_assert_contains($case[1], $case[0], 'a field takes the span of its kind');
	}
}
