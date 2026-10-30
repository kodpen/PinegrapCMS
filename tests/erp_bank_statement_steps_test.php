<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the bank statement steps: a refused column choice keeps
 * the columns chosen and marks the missing select, a refused upload keeps the
 * bank account, and Take in, Take the lines in and Record the suggested
 * lines are sent once.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_bank_statement_steps_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function erp_bank_statement_steps_send_once($source, $what)
{
	pg_assert_contains('window.addEventListener("submit", function (event) {', $source, $what . ': window submit listener');
	pg_assert_contains('if (sent.indexOf(form) !== -1) { event.preventDefault(); return; }', $source, $what . ': a later submit is cancelled');
	pg_assert_contains('if (event.defaultPrevented) { return; }', $source, $what . ': a cancelled submit does not count');
	pg_assert_contains('form.setAttribute("aria-busy", "true");', $source, $what . ': busy form');
	pg_assert_contains('window.addEventListener("pageshow", function (event) {', $source, $what . ': reset on a page brought back');
	pg_assert_contains('if (!event.persisted) { return; }', $source, $what . ': only a persisted page');
	pg_assert_contains("escape_javascript(lang('Working on it. Wait for the answer before leaving this page.'))", $source, $what . ': status sentence');
}

function test_erp_bank_statement_steps_columns_kept()
{
	$source = erp_bank_statement_steps_source('erp_bank_statement.php');
	$map = substr($source, strpos($source, "if (\$action === 'map') {"), 1600);

	// The rule is checked on the screen before the build, and the posted
	// columns go to the session with the missing select marked.
	pg_assert_contains('$liveform->add_fields_to_session();', $map, 'posted columns kept');
	pg_assert_contains("\$liveform->mark_error('col_date', \$message);", $map, 'date select marked');
	pg_assert_contains("\$liveform->mark_error('col_amount', \$message);", $map, 'amount select marked');
	pg_assert_true(strpos($map, '$liveform->add_fields_to_session();') < strpos($map, 'erp_bank_statement_build('), 'refused before the build');

	pg_assert_contains("\$posted = \$liveform->field_in_session('col_date');", $source, 'posted values preferred');
	pg_assert_contains("(\$liveform->check_field_error(\$name) ? ' is-invalid' : '')", $source, 'missing select marked invalid');
	pg_assert_contains("\$chosen('date_order', 'date_order')", $source, 'date order kept');
	pg_assert_contains("\$posted ? !empty(\$liveform->get_field_value('header')) : !empty(\$mapping['header'])", $source, 'header box kept');
}

function test_erp_bank_statement_steps_till_kept()
{
	$source = erp_bank_statement_steps_source('erp_bank_statements.php');
	$post = substr($source, strpos($source, 'if ($_POST) {'), 800);

	pg_assert_contains('$liveform->add_fields_to_session();', $post, 'posted till kept before any refusal');
	pg_assert_true(strpos($post, '$liveform->add_fields_to_session();') < strpos($post, 'go($self);'), 'kept before the first refusal');
	pg_assert_contains("((string) \$till['id'] === (string) \$liveform->get_field_value('cash_account_id')) ? ' selected' : ''", $source, 'till selected again');
}

function test_erp_bank_statement_steps_send_once()
{
	$list = erp_bank_statement_steps_source('erp_bank_statements.php');
	erp_bank_statement_steps_send_once($list, 'Take in');
	pg_assert_contains('enctype="multipart/form-data" class="card my-7" data-erp-send-once>', $list, 'Take in form sends once');
	pg_assert_contains('data-erp-send-once-status', $list, 'Take in status line');

	$one = erp_bank_statement_steps_source('erp_bank_statement.php');
	erp_bank_statement_steps_send_once($one, 'Take the lines in');
	pg_assert_contains('<form method="post" action="erp_bank_statement.php" class="card my-7" data-erp-send-once>', $one, 'Take the lines in form sends once');
	pg_assert_contains("'btn-solid theme-success', ' data-erp-send-once')", $one, 'Record the suggested lines sends once');
	pg_assert_contains('<span class="small fg-secondary" role="status" aria-live="polite" data-erp-send-once-status></span></div>', $one, 'Take the lines in status line');
	pg_assert_contains("((\$attributes !== '') ? '<span class=\"small fg-secondary\" role=\"status\" aria-live=\"polite\" data-erp-send-once-status></span>' : '')", $one, 'Record the suggested lines status line');
	// Split again and the per-line forms keep the window guard only.
	pg_assert_contains("lang('Split again'), 'btn-outline theme-secondary')", $one, 'Split again unchanged');
}
