<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the API applications screen layout (api_settings.php): the
 * applications list, the OAuth clients and the event notifications in a form
 * column, and a Summary in a sticky status column written after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function api_settings_layout_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/api_settings.php');
}

function test_api_settings_has_form_and_status_columns()
{
	$source = api_settings_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($form < $status, 'form column before status column');
	pg_assert_contains('<div class="position-sticky mt-5 lg:mt-0" style="top:3.3rem;">', $source, 'the status column sticks below the top bar');

	foreach (array('id="api_rows"', '$oauth_clients_card . \'', '$webhook_card . \'') as $part) {
		$at = strpos($source, $part, (int) strpos($source, '<main id="content"'));
		pg_assert_true($at !== false && $at > $form && $at < $status, $part . ' sits in the form column');
	}
}

function test_api_settings_status_column_reads_the_counts()
{
	$source = api_settings_layout_source();
	$status = substr($source, (int) strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">'));
	$status = substr($status, 0, (int) strpos($status, '</main>'));

	pg_assert_contains("lang('Summary')", $status, 'the summary card has its title');
	foreach (array('count($apps)', "\$summary_counts['on']", "\$summary_counts['off']", "\$summary_counts['expired']", "\$summary_counts['unused']", '$summary_requests', '$summary_failures', 'count($webhook_all)') as $count) {
		pg_assert_contains($count, $status, $count . ' is shown');
	}
	pg_assert_contains('The secret cannot be read back anywhere.', $status, 'the key note sits in the status column');
}

function test_api_settings_summary_counts_every_row_once()
{
	$source = api_settings_layout_source();
	$loop = substr($source, (int) strpos($source, 'foreach ($apps as $app) {'));
	$loop = substr($loop, 0, (int) strpos($loop, '$app_rows .= \''));

	pg_assert_contains("\$summary_counts[(\$status_label === lang('Not in use')) ? 'unused' : 'expired']++;", $loop, 'a warning row counts as expired or not in use');
	pg_assert_contains('$summary_counts[$status_class]++;', $loop, 'an active or disabled row counts by its class');
	pg_assert_contains("\$summary_requests += \$stats['requests'];", $loop);
	pg_assert_contains("\$summary_failures += \$stats['failures'];", $loop);
}

function api_settings_drawer_view_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/api/app_drawer_view.php');
}

function api_settings_drawer_tab_source($tab)
{
	$source = api_settings_drawer_view_source();
	$pane = substr($source, (int) strpos($source, 'id="' . $tab . '"'));

	return substr($pane, 0, (int) strpos($pane, '<div class="tab-pane fade"', 10));
}

function test_api_settings_drawer_general_fields_are_form_fields()
{
	$general = api_settings_drawer_tab_source('d_general');

	pg_assert_contains('<label class="form-label pg-field-label" for="d_name">', $general, 'the Name label names its box');
	pg_assert_contains('<label class="form-label pg-field-label" for="d_description">', $general, 'the Description label names its box');
	pg_assert_true(substr_count($general, '<div class="col-12 sm:col-6 form-field">') >= 2, 'name and description stack on a phone and pair from sm');
	foreach (array('id="d_secret_block"', 'id="d_nosecret_block"', 'id="d_key_row"') as $block) {
		pg_assert_true(preg_match('/<div class="pg-card-inner-row[^"]*" ' . preg_quote($block, '/') . '>/', $general) === 1, $block . ' is a row of an inner card');
	}
	pg_assert_true(strpos($general, '<div class="mb-5">') === false, 'no bare stacked block is left on the Overview tab');
}

function test_api_settings_drawer_advanced_fields_are_form_fields()
{
	$advanced = api_settings_drawer_view_source();
	$advanced = substr($advanced, (int) strpos($advanced, 'function api_app_drawer_advanced_html('));
	$advanced = substr($advanced, 0, (int) strpos($advanced, "\n}\n"));

	pg_assert_contains('<label class="form-label pg-field-label" for="d_ip">', $advanced, 'the address list label names its box');
	pg_assert_contains('<label class="form-label pg-field-label" for="d_rate">', $advanced, 'the rate label names its box');
	pg_assert_true(substr_count($advanced, '<div class="col-12 sm:col-6 form-field">') === 2, 'the expiry and the rate stack on a phone and pair from sm');
	pg_assert_true(strpos($advanced, '<div class="col-6">') === false, 'no fixed half column is left');
}

function api_settings_new_dialog_source()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/api/app_new_view.php');
	$dialog = substr($source, (int) strpos($source, '<dialog class="drawer drawer-end" id="api_new"'));

	return substr($dialog, 0, (int) strpos($dialog, '</dialog>'));
}

function test_api_settings_new_dialog_fields_are_form_fields()
{
	$dialog = api_settings_new_dialog_source();

	pg_assert_contains('<div class="col-12 sm:col-6 form-field">' . "\n\t\t\t\t\t\t" . '<label class="form-label pg-field-label" for="n_name">', $dialog, 'Name is a form field');
	pg_assert_contains('<div class="col-12 sm:col-6 form-field">' . "\n\t\t\t\t\t\t" . '<label class="form-label pg-field-label" for="n_description">', $dialog, 'Description is a form field');
	pg_assert_true(strpos($dialog, '<div class="mb-5">') === false, 'no bare stacked block is left in the dialog');
}

function test_api_settings_new_dialog_kind_is_a_named_choice_group()
{
	$dialog = api_settings_new_dialog_source();
	$view = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/api/app_new_view.php');

	pg_assert_contains('<legend class="visually-hidden">\' . lang(\'What connects to this site?\') . \'</legend>', $dialog, 'the starting points are a fieldset named by its legend');
	pg_assert_contains('<div class="form-field form-field-card">', $view, 'each starting point is a choice card');
	pg_assert_contains('aria-describedby="\' . $id . \'_help \' . $id . \'_acts"', $view, 'a card is described by its text and its acts-as lines');
	pg_assert_contains('<div class="form-text mt-1" id="\' . $id . \'_help">', $view, 'its text is a form text');
	pg_assert_contains('<div role="group" aria-labelledby="n_scope_title" class="mb-5">', $dialog, 'the permission rows are a group named by their title');
	pg_assert_contains('id="n_scope_title"', $dialog);
}

function test_api_settings_sheets_and_guide_boxes_are_labelled()
{
	$source = api_settings_drawer_view_source();

	pg_assert_contains('<div class="pg-list-row-title" id="\' . $id . \'_label">\' . h($label) . \'</div>', $source, 'each one-time value is named by its row');
	pg_assert_contains('api_app_value_box($id, $value, $id . \'_label\', $masked)', $source, 'and its Copy button says what it copies');
	pg_assert_contains('<div class="pg-list-row-title"\' . (($step[\'value_id\'] !== \'\') ? \' id="\' . $id . \'_label"\' : \'\') . \'>', $source, 'a guide step with a value names it');
	pg_assert_true(strpos($source, '<div class="pg-start-copy mb-5">') === false, 'no value box carries its own spacing');
}
