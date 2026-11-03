<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the editor's blank tab (pg_designer_blank_page() in
 * includes/fn/designer.php): empty in add mode, the frame tree when a
 * classic style with no page of its own is opened in the designer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_designer_blank_page_empty()
{
	$p = pg_designer_blank_page(1, null);
	pg_assert_same('new1', $p['key']);
	pg_assert_same(0, $p['page_id']);
	pg_assert_same(1, $p['page_draft']);
	pg_assert_same(null, $p['tree']);
	pg_assert_same(0, $p['converted']);
	pg_assert_same('edit', $p['access']);
}

function test_designer_blank_page_classic_frame()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'shared_ref', 'props' => array('sharedId' => 7, 'sharedName' => 'Site'), 'children' => array()),
	));
	$p = pg_designer_blank_page(0, $tree);
	pg_assert_same($tree, $p['tree']);
	pg_assert_same(1, $p['converted']);
	pg_assert_same(0, $p['page_draft']);
}
