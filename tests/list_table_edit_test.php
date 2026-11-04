<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for quick edit beyond a list's first fields
 * (includes/fn/list_table_edit.php): the answer drawn from a row read
 * again, and the Pages list's switches decided by the bulk edit's rules.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Behind their gates these files only declare functions.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_bulk.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

function test_list_table_edit_answer_draws_the_cell_and_the_ones_beside_it()
{
	$row = array('id' => '7', 'class' => '', 'cells' => array(
		'id' => array('html' => '<a>SKU</a>', 'class' => 'align-middle', 'attrs' => array('data-pg-edit' => 'name')),
		'weight' => array('html' => '2 lb', 'class' => 'align-middle text-center'),
		'last_modified' => array('html' => 'now', 'class' => ''),
	));

	pg_assert_same(
		array('html' => '2 lb', 'class' => 'align-middle text-center', 'cells' => array('last_modified' => array('html' => 'now', 'class' => ''))),
		pg_list_table_edit_answer($row, 'weight', array('last_modified', 'missing', 'weight')),
		'the edited cell, then the others the row has');
	pg_assert_same(array(), pg_list_table_edit_answer(null, 'weight', array('last_modified')), 'a row no longer on the list draws nothing');
}

function test_page_quick_edit_columns_are_the_list_switches()
{
	pg_assert_same(array('searchable', 'comments', 'sitemap'), array_keys(pg_page_quick_edit_columns()), 'the three switches');

	$columns = pg_list_table_columns(pg_page_list_columns(array('custom_form' => false, 'previewing_other_theme' => false, 'style_heading' => 'Page Style')));
	$editable = array();

	foreach ($columns as $key => $column) {
		if ($column['editable'] !== '') {
			$editable[] = $key;
		}
	}

	pg_assert_same(array_keys(pg_page_quick_edit_columns()), $editable, 'each switch column is editable');
	pg_assert_same('1', pg_page_quick_edit_value('searchable', array('page_search' => '1')), 'searchable reads page_search');
	pg_assert_same('0', pg_page_quick_edit_value('sitemap', array('sitemap' => '0')), 'an off switch opens as No');
	pg_assert_same('0', pg_page_quick_edit_value('comments', array()), 'a missing value opens as No');
}

function test_page_quick_decide_follows_the_bulk_rules()
{
	$page = array('page_id' => 3, 'page_type' => 'standard', 'page_folder' => 1, 'page_search' => 0, 'sitemap' => 0, 'noindex' => 0, 'nofollow' => 0, 'is_visual' => 0);

	$decided = pg_page_quick_decide($page, 'searchable', '1', 2);
	pg_assert_same(array('page_search' => 1), $decided['decision']['columns'], 'searchable writes page_search');
	pg_assert_same('add', $decided['decision']['tag_cloud'], 'its keywords join the tag cloud');
	pg_assert_true($decided['decision']['stale'], 'the SEO score goes stale');

	$decided = pg_page_quick_decide($page, 'comments', '1', 2);
	pg_assert_same(array('comments' => 1), $decided['decision']['columns'], 'comments write comments');
	pg_assert_true(!$decided['decision']['stale'], 'comments leave the SEO score alone');

	pg_assert_same(array('sitemap' => 1), pg_page_quick_decide($page, 'sitemap', '1', 2)['decision']['columns'], 'a standard page joins the site map');
	pg_assert_true(!pg_page_quick_decide(array('page_type' => 'logout') + $page, 'sitemap', '1', 2)['ok'], 'a type the site map does not list is refused');
	pg_assert_true(!pg_page_quick_decide(array('noindex' => 1) + $page, 'sitemap', '1', 2)['ok'], 'a page closed to search engines is refused');
	pg_assert_true(pg_page_quick_decide(array('noindex' => 1, 'sitemap' => 1) + $page, 'sitemap', '0', 2)['ok'], 'taking a page off the site map is always allowed');
	pg_assert_true(!pg_page_quick_decide($page, 'page_name', 'x', 2)['ok'], 'another field is refused');
}
