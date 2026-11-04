<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the explorer's "Where used" sub-action for pages
 * (includes/explorer/handle_page_usage.php): which links count as links to
 * the page's address, which columns choose a page, the site settings that
 * hold the page's id and the cap on what is listed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// The handler is loaded with the explorer and called by its case, behind
// the folder check.
function test_explorer_page_usage_handler_wired()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	$handler = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_page_usage.php');
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/explorer.php');

	pg_assert_true(function_exists('pg_explorer_handle_page_usage'), 'handler loaded');
	pg_assert_contains("case 'explorer_page_usage':\n            pg_explorer_handle_page_usage(\$type, \$request, \$user, \$folders_that_user_has_access_to);\n            break;", $explorer, 'called by its case');
	pg_assert_contains("case 'explorer_page_usage':", $panel, 'routed by the panel action');
	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $handler, 'gate');
	pg_assert_contains("pg_explorer_folder_visible(\$usage_page['page_folder'], \$folders_that_user_has_access_to) == false", $handler, 'the folder check');
	pg_assert_true(in_array('explorer_page_usage', pg_page_cache_panel_sub_actions()['file_explorer']['clean'], true), 'reads only: the page cache stays');
}

// A link is "/<name>" ending where an address ends; a longer name or a
// deeper path is another address.
function test_explorer_page_link_found()
{
	pg_assert_true(pg_explorer_page_link_found('<a href="/about">About</a>', 'about'), 'site path');
	pg_assert_true(pg_explorer_page_link_found("<a href='https://example.com/about?x=1'>", 'about'), 'full address with a query');
	pg_assert_true(pg_explorer_page_link_found('{"href":"\\/about#team"}', 'about'), 'JSON-escaped path with a fragment');
	pg_assert_true(pg_explorer_page_link_found('/About', 'about'), 'case does not count');
	pg_assert_true(pg_explorer_page_link_found('/shop/about', 'about'), 'a site under a path');
	pg_assert_true(pg_explorer_page_link_found('/about', 'about'), 'the whole text');
	pg_assert_false(pg_explorer_page_link_found('<a href="/about-us">', 'about'), 'a longer name');
	pg_assert_false(pg_explorer_page_link_found('<a href="/about/team">', 'about'), 'a deeper path');
	pg_assert_false(pg_explorer_page_link_found('all about us', 'about'), 'a word, no address');
	pg_assert_false(pg_explorer_page_link_found('<a href="/a.b">', 'a-b'), 'the name is matched literally');
	pg_assert_true(pg_explorer_page_link_found('<a href="/a.b">', 'a.b'), 'a dot in the name');
	pg_assert_false(pg_explorer_page_link_found('/about', ''), 'no name');
	pg_assert_false(pg_explorer_page_link_found(null, 'about'), 'no text');
}

// "<something>_page_id" and the products' two older columns choose a page;
// the owner's page_id and look-alikes do not.
function test_explorer_page_usage_pointer_columns()
{
	pg_assert_same(
		array('next_page_id', 'confirmation_page_id', 'send_to_page', 'email_page'),
		pg_explorer_page_usage_pointer_columns(array('page_id', 'next_page_id', 'confirmation_alternative_page', 'confirmation_page_id', 'maximum_number_of_results_per_page', 'send_to_page', 'email_page', 'name')),
		'pointer columns');
	pg_assert_same(array(), pg_explorer_page_usage_pointer_columns(array()), 'none');
}

// A setting counts when it holds the page's id; a page id of 0 matches
// nothing (every unset setting holds 0).
function test_explorer_page_usage_setting_hits()
{
	$labels = array('a_page_id' => 'A', 'b_page_id' => 'B', 'c_page_id' => 'C');
	$config = array('a_page_id' => '7', 'b_page_id' => '0', 'c_page_id' => 7);

	pg_assert_same(
		array(array('name' => 'A', 'note' => '', 'url' => ''), array('name' => 'C', 'note' => '', 'url' => '')),
		pg_explorer_page_usage_setting_hits($config, $labels, 7),
		'matches by id');
	pg_assert_same(array(), pg_explorer_page_usage_setting_hits($config, $labels, 0), 'no page');
	pg_assert_same(array(), pg_explorer_page_usage_setting_hits(array(), $labels, 7), 'a column the database lacks');
}

// Rows the filter drops do not count; past the cap the answer says more.
function test_explorer_page_usage_take()
{
	$rows = array(array('n' => 1), array('n' => 2), array('n' => 3), array('n' => 4));
	$odd = function ($row) { return ($row['n'] % 2) === 1; };
	$item = function ($row) { return $row['n']; };

	pg_assert_same(array('items' => array(1, 3), 'truncated' => false), pg_explorer_page_usage_take($rows, $odd, $item, 40), 'filtered');
	pg_assert_same(array('items' => array(1), 'truncated' => true), pg_explorer_page_usage_take($rows, $odd, $item, 1), 'capped');
	pg_assert_same(array('items' => array(), 'truncated' => false), pg_explorer_page_usage_take(false, $odd, $item, 40), 'a failed query');
}
