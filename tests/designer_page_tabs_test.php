<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the visual designer builds a page tab from the page the
 * server sends (assets/js/style_designer.src.js): when the editor opens
 * (_pgTabsInit()) and when a page of another design is opened as a tab
 * (_pgTabsAttachLoaded()). A page setting the tab does not take from the
 * server is shown empty and is sent back empty on the next save; for the
 * publish time and the time to come off the site an empty value clears the
 * stored time.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of a function in the designer source, from its declaration up to
// the next function declared at the same depth.
function designer_page_tabs_test_body($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, "\n    function " . $name . '(');

	if ($from === false) {
		return '';
	}

	$to = strpos($source, "\n    function ", $from + 1);

	return ($to === false) ? substr($source, $from) : substr($source, $from, $to - $from);
}

// The page settings a tab carries and the save sends (_PG_PAGE_FIELDS).
function designer_page_tabs_test_fields()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	if (preg_match('/var _PG_PAGE_FIELDS = \[(.*?)\];/s', $source, $m) !== 1) {
		return array();
	}

	preg_match_all("/'([a-z_]+)'/", $m[1], $keys);

	return $keys[1];
}

// Every page setting the save sends is taken from the server's page in both
// places a tab is built.
function test_designer_page_tabs_take_every_page_field_from_the_server()
{
	$fields = designer_page_tabs_test_fields();

	pg_assert_true(in_array('page_publish_at', $fields, true), 'the field list is found');

	foreach (array('_pgTabsInit', '_pgTabsAttachLoaded') as $name) {
		$body = designer_page_tabs_test_body($name);
		pg_assert_true($body !== '', $name . ' is found');

		foreach ($fields as $field) {
			pg_assert_true(preg_match('/\b' . $field . ': \(?sp\.' . $field . '\b/', $body) === 1, $name . ' takes ' . $field);
		}
	}
}
