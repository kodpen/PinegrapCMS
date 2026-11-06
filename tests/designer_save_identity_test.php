<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a content-level save of the visual editor may not change
 * about a page (pg_designer_restricted_identity(), includes/fn/designer.php):
 * its name, folder, home flag and design come back from the stored row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_designer_restricted_identity_keeps_the_stored_design()
{
	$stored = array('page_name' => 'about', 'page_folder' => '4', 'page_home' => 'no', 'page_style' => '7');
	$sent = array('page_id' => 12, 'page_name' => 'renamed', 'page_folder' => 9, 'page_home' => 1, 'page_title' => 'New title');

	$r = pg_designer_restricted_identity($sent, 3, $stored);

	pg_assert_same(7, $r['style_id'], 'a page sent with another design stays on its own');
	pg_assert_same('about', $r['page']['page_name'], 'the name is the stored one');
	pg_assert_same(4, $r['page']['page_folder'], 'the folder is the stored one');
	pg_assert_same(0, $r['page']['page_home'], 'the home flag is the stored one');
	pg_assert_same('New title', $r['page']['page_title'], 'the title is content and stays as sent');
}

function test_designer_restricted_identity_without_a_stored_row()
{
	$sent = array('page_id' => 12, 'page_name' => 'x');
	$r = pg_designer_restricted_identity($sent, 3, null);

	pg_assert_same(3, $r['style_id'], 'no stored row: the design asked for');
	pg_assert_same('x', $r['page']['page_name'], 'and the page as sent');
}

function test_designer_restricted_identity_keeps_a_design_when_the_stored_has_none()
{
	$stored = array('page_name' => 'about', 'page_folder' => '4', 'page_home' => 'yes', 'page_style' => '0');
	$r = pg_designer_restricted_identity(array('page_id' => 12), 3, $stored);

	pg_assert_same(3, $r['style_id'], 'a stored page on no design takes the one it is saved in');
	pg_assert_same(1, $r['page']['page_home'], 'the stored home flag');
}
