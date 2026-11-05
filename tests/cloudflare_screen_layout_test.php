<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Cloudflare Tools screen layout (cloudflare.php): period,
 * analytics, DNS records and logs in a form column, the zone switches and
 * Clear Cache in a sticky status column written after it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function cloudflare_screen_layout_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/cloudflare.php');

	return substr($source, (int) strpos($source, 'print pg_page_shell('));
}

function test_cloudflare_screen_has_form_and_status_columns()
{
	$source = cloudflare_screen_layout_source();

	$form = strpos($source, '<div class="col-12 lg:col-8 2xl:col-9 order-1">');
	$status = strpos($source, '<div class="col-12 lg:col-4 2xl:col-3 order-2">');

	pg_assert_true($form !== false, 'the form column is there');
	pg_assert_true($status !== false, 'the status column is there');
	pg_assert_true($form < $status, 'form column before status column');
	pg_assert_contains('<div class="position-sticky my-7" style="top:3.3rem;">', $source, 'the status column sticks below the top bar');

	foreach (array('nav nav-tabs', "pg_cloudflare_analytics_card(\$analytics, 'requests')", '$image_vary_card', "pg_list_table_search_box('cloudflare_dns')", '$audit_rows', '$fw_rows') as $part) {
		$at = strpos($source, $part);
		pg_assert_true($at !== false && $at > $form && $at < $status, $part . ' sits in the form column');
	}

	foreach (array('id="cf_dev"', 'id="cf_uam"', 'id="cf_https"', 'id="cf_brotli"', 'id="cf_rocket"', 'value="clear_cache"') as $part) {
		$at = strpos($source, $part);
		pg_assert_true($at !== false && $at > $status, $part . ' sits in the status column');
	}
}

function test_cloudflare_screen_analytics_fill_the_row()
{
	$source = cloudflare_screen_layout_source();

	pg_assert_true(strpos($source, '<div class="md:col-2"><div class="card text-center">') === false, 'no analytics card is a fixed sixth of the row');
	pg_assert_true(substr_count($source, '<div class="col-6 md:col-4 xl:col"><div class="card h-100">') === 5, 'the five analytics cards share the row');
	pg_assert_contains('<span class="pg-btn-label">\' . lang(\'Clear Cache\') . \'</span>', $source, 'the Clear Cache label is in its span');
}

function test_cloudflare_screen_date_fields_are_labelled_form_fields()
{
	$source = cloudflare_screen_layout_source();

	foreach (array('cf_start_date' => 'start_date', 'cf_end_date' => 'end_date', 'cf_log_start' => 'log_start', 'cf_log_end' => 'log_end') as $id => $name) {
		pg_assert_contains('<label for="' . $id . '" class="form-label">', $source, $name . ' has its label');
		pg_assert_contains('<input type="date" data-pg-date id="' . $id . '" name="' . $name . '"', $source, $name . ' carries the id its label names');
	}

	pg_assert_true(substr_count($source, '<div class="form-field">' . "\n") >= 4, 'the four date fields sit in form-field boxes');
	pg_assert_true(strpos($source, '<form method="get" class="mb-7">\'.$period_fields.\'') !== false, 'Refresh Logs keeps a custom analytics period');
	pg_assert_contains('<input type="hidden" name="log_start" value="\'.$log_start_val.\'">', $source, 'Apply keeps the log range');
}

function test_cloudflare_screen_dns_and_logs_cards_have_headings()
{
	$source = cloudflare_screen_layout_source();

	pg_assert_true(strpos($source, '<label class="form-label mb-0 fw-bold">') === false, 'no card title is a label without a field');
	pg_assert_contains('<section class="card my-7" aria-labelledby="cf_dns_title">', $source, 'the DNS card is named by its heading');
	pg_assert_contains('<section class="card my-7" aria-labelledby="cf_logs_title">', $source, 'the logs card is named by its heading');
	pg_assert_contains('<span class="pg-btn-label">\' . lang(\'Add Record\') . \'</span>', $source, 'the Add Record label is in its span');
	pg_assert_true(strpos($source, '<th>') === false, 'every log table heading is a column header');
}

function test_cloudflare_screen_dns_dialogs_use_labelled_form_fields()
{
	$source = cloudflare_screen_layout_source();

	foreach (array('add', 'edit') as $prefix) {
		$start = strpos($source, '<dialog class="dialog" id="' . $prefix . 'Modal"');
		pg_assert_true($start !== false, $prefix . ' dialog is there');
		$dialog = substr($source, $start, strpos($source, '</dialog>', $start) - $start);

		pg_assert_contains('<div class="row g-5">', $dialog, $prefix . ' dialog lays its fields on a grid');
		pg_assert_true(strpos($dialog, '<div class="mb-5') === false, $prefix . ' dialog has no stacked mb-5 boxes left');

		foreach (array('type', 'ttl', 'name', 'content', 'priority', 'service', 'proto', 'priority2', 'weight', 'port', 'target', 'flags', 'tag', 'value') as $field) {
			$id = $prefix . '_' . $field;
			pg_assert_contains('<label for="' . $id . '" class="form-label">', $dialog, $id . ' has a form label');
			pg_assert_true(preg_match('/<(input|select)[^>]* id="' . $id . '" class="form-control"/', $dialog) === 1, $id . ' is a form control');
		}

		pg_assert_contains('<input class="check" type="checkbox" name="proxied" id="' . $prefix . '_proxied"><label for="' . $prefix . '_proxied">', $dialog, $prefix . ' Proxied is a labelled check');

		// The type groups toggle d-none on their column, a box with no display
		// of its own: Bootstrap 6 utilities carry no !important, so d-none on
		// the inner .row would race the grid's display:flex.
		foreach (array('type-mx', 'type-srv', 'type-caa') as $group) {
			pg_assert_contains('<div class="col-12 type-extra ' . $group . ' d-none">', $dialog, $prefix . ' ' . $group . ' hides on its column');
		}
		pg_assert_contains('<div class="col-12 common-content">', $dialog, $prefix . ' Content toggles on its column');
		pg_assert_contains('<div class="col-12 proxied-only">', $dialog, $prefix . ' Proxied toggles on its column');
	}
}
