<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two panel addresses that lost part of their value. A refused
 * Edit Event save rebuilt its redirect with send_to unencoded, so a site
 * event page's address (/event?id=12&recurrence_number=3) was cut at its
 * first '&' and the back arrow opened the first occurrence. Mass Edit's Get
 * Edit Form appended "?tags=" to the whole address, which after the skip
 * link (#content) only changed the fragment and did not reload the page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_calendar_event_refused_save_keeps_whole_send_to()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_calendar_event.php');

	pg_assert_contains("\$query_string_send_to = '&send_to=' . urlencode(\$liveform->get_field_value('send_to'));", $source, 'refused save redirect encodes send_to');
	pg_assert_same(0, preg_match("/'&send_to=' \\. \\\$liveform->get_field_value\\('send_to'\\)/", $source), 'send_to added to the redirect unencoded');
}

function test_mass_edit_get_form_replaces_query_and_fragment()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/mass_edit.php');

	pg_assert_contains('window.location.href = window.location.pathname + "?tags=" + encodeURIComponent(String($("select#page_options").val() || ""));', $source, 'Get Edit Form builds a new address from the path');
	pg_assert_same(false, strpos($source, 'window.location.href +='), 'Get Edit Form appends to the current address');
}
