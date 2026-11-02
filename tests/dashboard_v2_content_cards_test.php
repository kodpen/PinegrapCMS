<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's visitor and content cards (includes/
 * dashboard/v2/cards/gsearch.php, ga.php, content.php, comments.php,
 * files.php, activity.php): their pure helpers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/layout.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/run.php');

foreach (array('gsearch', 'ga', 'content', 'comments', 'files', 'activity') as $pg_card_id) {
	require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/' . $pg_card_id . '.php');
}

function test_dashboard_v2_gsearch_days()
{
	pg_assert_same(1, pg_dashboard_v2_gsearch_days(0));
	pg_assert_same(7, pg_dashboard_v2_gsearch_days(1));
	pg_assert_same(28, pg_dashboard_v2_gsearch_days(2));
	pg_assert_same(7, pg_dashboard_v2_gsearch_days(9), 'unknown');
}

// A smaller position is the better one.
function test_dashboard_v2_position_change_tone()
{
	pg_assert_same(null, pg_dashboard_v2_position_change(5.0, 0.0));
	pg_assert_same(null, pg_dashboard_v2_position_change(0.0, 5.0));
	pg_assert_same('good', pg_dashboard_v2_position_change(4.0, 6.5)['tone']);
	pg_assert_same('bad', pg_dashboard_v2_position_change(6.5, 4.0)['tone']);
	pg_assert_same('', pg_dashboard_v2_position_change(4.02, 4.0)['tone'], 'rounds to no change');
}

// The first sources are kept, the rest folded, shares add up to 100.
function test_dashboard_v2_source_shares()
{
	pg_assert_same(array(), pg_dashboard_v2_source_shares(array(), 3, 'Other'));
	pg_assert_same(array(), pg_dashboard_v2_source_shares(array(array('name' => 'a', 'value' => 0)), 3, 'Other'));

	$rows = pg_dashboard_v2_source_shares(array(
		array('name' => 'google', 'value' => 1),
		array('name' => 'direct', 'value' => 1),
		array('name' => 'social', 'value' => 1),
		array('name' => 'mail', 'value' => 0),
	), 2, 'Other');

	pg_assert_same(array('google', 'direct', 'Other'), array_column($rows, 'name'));
	pg_assert_same(100, array_sum(array_column($rows, 'share')));
	pg_assert_same(array(34, 33, 33), array_column($rows, 'share'));
}

function test_dashboard_v2_page_state()
{
	pg_assert_same('live', pg_dashboard_v2_page_state(false, 2000, 1000));
	pg_assert_same('draft', pg_dashboard_v2_page_state(true, 0, 1000));
	pg_assert_same('draft', pg_dashboard_v2_page_state(true, 900, 1000), 'time passed');
	pg_assert_same('scheduled', pg_dashboard_v2_page_state(true, 2000, 1000));
}

function test_dashboard_v2_comment_preview_and_initials()
{
	pg_assert_same('Great & tasty recipe', pg_dashboard_v2_comment_preview("<p>Great &amp;\n  tasty</p> <b>recipe</b>"));
	pg_assert_same('abcd…', pg_dashboard_v2_comment_preview('abcdefgh', 5));
	pg_assert_same('ŞK', pg_dashboard_v2_initials('şule  kaya demir'));
	pg_assert_same('?', pg_dashboard_v2_initials('  '));
}

function test_dashboard_v2_file_parts()
{
	pg_assert_same('image', pg_dashboard_v2_file_group('JPG'));
	pg_assert_same('document', pg_dashboard_v2_file_group('pdf'));
	pg_assert_same('other', pg_dashboard_v2_file_group('zip'));

	$ring = pg_dashboard_v2_file_parts(array(
		array('type' => 'jpg', 'bytes' => '100'),
		array('type' => 'png', 'bytes' => '50'),
		array('type' => 'pdf', 'bytes' => '30'),
		array('type' => 'mp4', 'bytes' => '20'),
	), null);

	pg_assert_same(200.0, (float) $ring['used']);
	pg_assert_same(array('image' => 150.0, 'document' => 30.0, 'other' => 20.0), $ring['parts']);

	$ring = pg_dashboard_v2_file_parts(array(), 500.0);
	pg_assert_same(500.0, $ring['parts']['free']);
}

function test_dashboard_v2_activity_rows()
{
	pg_assert_same('content', pg_dashboard_v2_activity_chip('update'));
	pg_assert_same('security', pg_dashboard_v2_activity_chip('log'));
	pg_assert_same('', pg_dashboard_v2_activity_chip('order'), 'only under All');

	$rows = pg_dashboard_v2_activity_rows(array(
		'form' => array('items' => array(
			array('ts' => 100, 'sort' => 'form:1', 'name' => 'Ay&#351;e', 'meta' => 'Contact &middot; R-1', 'href' => 'edit_submitted_form.php?id=1'),
		)),
		'log' => array('items' => array(
			array('ts' => 200, 'sort' => 'log:2', 'name' => '<a href="x">IP blocked</a>', 'meta' => '', 'href' => 'view_log.php?a=1&amp;b=2'),
			array('ts' => 100, 'sort' => 'log:0', 'name' => 'older', 'meta' => '', 'href' => ''),
		)),
	), 2);

	pg_assert_same(2, count($rows), 'limit');
	pg_assert_same('IP blocked', $rows[0]['text']);
	pg_assert_same('security', $rows[0]['chip']);
	pg_assert_same('view_log.php?a=1&b=2', $rows[0]['url']);
	pg_assert_same('Ayşe', $rows[1]['text'], 'tie broken by sort key');
	pg_assert_same('Contact · R-1', $rows[1]['note']);
	pg_assert_false(isset($rows[0]['sort']));
}
