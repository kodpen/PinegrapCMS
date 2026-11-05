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
