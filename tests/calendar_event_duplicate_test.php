<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A duplicated calendar event (duplicate_calendar_event.php) starts
 * unpublished, named "… (copy)", with its locations copied, so it does not
 * show twice on the site before it is edited; the location check asks on its
 * first save.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function calendar_event_duplicate_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/duplicate_calendar_event.php');
}

function test_calendar_event_copy_name()
{
	pg_assert_same(lang(array('string' => '{var:1} (copy)', 'vars' => 'Concert')), pg_calendar_event_copy_name('Concert'), 'the name gets the copy suffix');

	$long = str_repeat('ş', 100);
	$copy = pg_calendar_event_copy_name($long);

	pg_assert_true(mb_strlen($copy) <= 100, 'the copy fits the 100-character column');
	pg_assert_same(lang(array('string' => '{var:1} (copy)', 'vars' => '')), mb_substr($copy, -mb_strlen(lang(array('string' => '{var:1} (copy)', 'vars' => '')))), 'the suffix is kept, the name is shortened');
}

function test_calendar_event_duplicate_starts_unpublished_with_its_locations()
{
	$source = calendar_event_duplicate_test_source();

	pg_assert_contains('pg_calendar_event_copy_name(', $source, 'the copy is renamed');
	pg_assert_contains("\$calendar_event['published'] = 0;", $source, 'the copy is unpublished');
	pg_assert_true(strpos($source, "(\$user['role'] == 3) && (\$user['publish_calendar_events'] == FALSE)") === false, 'for every user, not only one without publish rights');
	pg_assert_contains('INSERT INTO calendar_events_calendar_event_locations_xref', $source, 'its locations are copied');
}
