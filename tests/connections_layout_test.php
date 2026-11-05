<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Connected accounts screen layout (connections.php): the
 * provider cards in a form column, a Summary of every provider in a sticky
 * status column written after it, and the application fields as form-field
 * boxes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function connections_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/connections.php');
}

function test_connections_has_form_and_status_columns()
{
	$source = connections_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($form < $status, 'form column before status column');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'the status box sticks below the top bar');
	pg_assert_contains('\' . $cards . \'', substr($source, $form, $status - $form), 'the provider cards sit in the form column');
	pg_assert_contains('\' . $summary_rows . \'', substr($source, $status), 'the Summary sits in the status column');
	pg_assert_true(strpos($source, 'class="col-12 2xl:col-6"') === false, 'provider cards are no longer paired side by side');
}

function test_connections_summary_lists_every_provider()
{
	$source = connections_layout_source();

	pg_assert_contains('id="connections-summary"', $source, 'the Summary card has its id');
	pg_assert_contains("lang('Summary')", $source, 'the Summary card has its title');
	pg_assert_contains('href="#connections-\' . h($provider_key) . \'"', $source, 'a Summary row jumps to its provider card');
	pg_assert_contains('id="connections-\' . h($provider_key) . \'"', $source, 'each provider card has the id the Summary jumps to');
	pg_assert_contains('$app_badge', substr($source, (int) strpos($source, '$summary_rows .=')), 'a Summary row shows the application state');
}

function test_connections_application_fields_are_form_field_boxes()
{
	$source = connections_layout_source();
	$form = substr($source, (int) strpos($source, '$app_form = \''), 6000);

	foreach (array('redirect_', 'client_id_', 'client_secret_') as $id) {
		pg_assert_true(preg_match('~<div class="form-field[^"]*">\s*<label class="form-label" for="' . preg_quote($id, '~') . '~', $form) === 1, $id . ' sits in a form-field box');
	}

	pg_assert_true(strpos($source, 'form-control-sm') === false, 'the fields use the regular control size');
	pg_assert_true(preg_match('~</div>\s*<div class="form-text mb-0 connections-copy-status"~', $form) === 1, 'the copy status still follows the input group the script reads');
}
