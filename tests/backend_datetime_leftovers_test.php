<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the date and time leftovers of assets/js/backend.src.js and
 * assets/css/backend.src.css on the one date-and-time control: the comment
 * screen's publish schedule focuses the box people type in, so the calendar
 * opens; a shipping cut-off row a script adds escapes its stored value; the
 * rules of the old two-part date and time group are gone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function backend_datetime_source($path)
{
	return (string)file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_backend_comment_schedule_focuses_the_control()
{
	$src = backend_datetime_source('assets/js/backend.src.js');
	$found = preg_match('/\nfunction init_edit_comment_publish\(\) \{.*?\n\}\n/s', $src, $m);

	pg_assert_same(1, $found, 'init_edit_comment_publish() found');
	$body = $found ? $m[0] : '';
	pg_assert_same(false, strpos($body, 'publish_date_and_time.focus()'), 'the hidden store is never focused');
	pg_assert_true(strpos($body, "window.pgFields.focus(publish_date_and_time[0]);") !== false, 'the box people type in is focused, which opens the calendar');
}

function test_backend_shipping_cutoff_row_escapes_its_value()
{
	$src = backend_datetime_source('assets/js/backend.src.js');

	pg_assert_same(false, strpos($src, "value=\"' + properties['date_and_time'] + '\""), 'the stored value is never written into the markup raw');
	pg_assert_true(strpos($src, "value=\"' + prepare_content_for_html(String(properties['date_and_time'] || '')) + '\"") !== false, 'the stored value is escaped');
}

function test_backend_css_drops_the_old_date_and_time_group()
{
	$css = backend_datetime_source('assets/css/backend.src.css');

	pg_assert_same(0, preg_match('/\.pg-datetime\b(?!-box)|\.pg-time\b(?!-box)|\.pg-datetime-date\b/', $css), 'no rule of the old .pg-datetime / .pg-time group is left');
}
