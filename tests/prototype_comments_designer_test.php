<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer's side of prototype comments
 * (includes/fn/prototype_comments.php, includes/panel/designer.php,
 * assets/js/style_designer_comments.js): the threads and the list by page,
 * the bell line's words and address, its hourly counter on the link, the
 * presence stamp kept for designers, and a deleted user's answers erased.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_comments.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy.php');
require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// A prototype_comments row, laid over the given fields.
function prototype_comments_designer_test_row($over = array())
{
	return array_merge(array(
		'id' => 1, 'link_id' => 7, 'style_id' => 31, 'page_id' => 120, 'node_id' => 'n1',
		'pos_x' => 1250, 'pos_y' => 5000, 'parent_id' => 0, 'author_name' => 'Ayşe', 'user_id' => 0,
		'body' => 'Bigger, please.', 'created_at' => 1000, 'resolved_at' => 0, 'resolved_by' => 0,
	), $over);
}

function test_proto_comment_threads_put_answers_under_their_comment()
{
	$rows = array(
		prototype_comments_designer_test_row(array('id' => 5, 'parent_id' => 2, 'author_name' => 'Erdal', 'user_id' => 3, 'body' => 'Done.')),
		prototype_comments_designer_test_row(array('id' => 2)),
		prototype_comments_designer_test_row(array('id' => 4, 'node_id' => 'n9', 'resolved_at' => 1500)),
		prototype_comments_designer_test_row(array('id' => 3, 'parent_id' => 2, 'body' => 'And the colour.')),
		// An answer whose comment is not among the rows is left out.
		prototype_comments_designer_test_row(array('id' => 9, 'parent_id' => 8)),
	);

	$threads = pg_proto_comment_threads($rows);

	pg_assert_same(array(2, 4), array_column($threads, 'id'), 'threads by their first comment, oldest first');
	pg_assert_same(array(3, 5), array_column($threads[0]['answers'], 'id'), 'answers oldest first');
	pg_assert_same(true, $threads[0]['answers'][1]['staff'], 'a staff answer says so');
	pg_assert_same(12.5, $threads[0]['x'], 'the place in percentages');
	pg_assert_same(array(), $threads[1]['answers'], 'a thread without answers');
	pg_assert_same(1500, $threads[1]['resolved_at'], 'resolved kept');
}

function test_proto_comment_list_pages_groups_by_page_with_counts()
{
	$threads = pg_proto_comment_threads(array(
		prototype_comments_designer_test_row(array('id' => 1, 'page_id' => 130)),
		prototype_comments_designer_test_row(array('id' => 2, 'page_id' => 120, 'resolved_at' => 99)),
		prototype_comments_designer_test_row(array('id' => 3, 'page_id' => 120)),
		prototype_comments_designer_test_row(array('id' => 4, 'page_id' => 140)),
	));

	$pages = pg_proto_comment_list_pages($threads, array(120 => 'Home', 130 => 'About'));

	pg_assert_same(array(120, 130, 140), array_column($pages, 'page_id'), 'the design\'s pages in their order, a gone page last');
	pg_assert_same(array('Home', 'About', ''), array_column($pages, 'name'), 'a gone page has no name');
	pg_assert_same(array(1, 1, 1), array_column($pages, 'open'), 'open threads counted');
	pg_assert_same(array(1, 0, 0), array_column($pages, 'resolved'), 'resolved threads counted');
	pg_assert_same(array(3, 2), array_column($pages[0]['threads'], 'id'), 'open threads first, newest first');
	pg_assert_same(array(), pg_proto_comment_list_pages(array(), array(120 => 'Home')), 'no page without comments');
}

// The viewer (assets/js/pg_proto_comments.js, threads()) reads one page of
// one link and numbers its threads by the first comment, oldest first,
// resolved ones counted: the designer's list gives the same numbers, and
// the link's hint where a page has comments from more than one link.
function test_proto_comment_list_pages_numbers_threads_per_link_as_the_viewer_does()
{
	$threads = pg_proto_comment_threads(array(
		prototype_comments_designer_test_row(array('id' => 1, 'link_id' => 7)),
		prototype_comments_designer_test_row(array('id' => 2, 'link_id' => 8)),
		prototype_comments_designer_test_row(array('id' => 3, 'link_id' => 7, 'resolved_at' => 99)),
		prototype_comments_designer_test_row(array('id' => 4, 'link_id' => 7)),
		prototype_comments_designer_test_row(array('id' => 6, 'link_id' => 9, 'page_id' => 130)),
	));

	$pages = pg_proto_comment_list_pages($threads, array(120 => 'Home', 130 => 'About'), array(7 => 'Ab12Cd34', 8 => 'Zz99Yy88'));
	$numbers = array();
	$hints = array();

	foreach ($pages[0]['threads'] as $thread) {
		$numbers[$thread['id']] = $thread['n'];
		$hints[$thread['id']] = $thread['link_hint'];
	}

	ksort($numbers);
	ksort($hints);

	pg_assert_same(array(1 => 1, 2 => 1, 3 => 2, 4 => 3), $numbers, 'numbered per link, oldest first, a resolved thread keeps its number');
	pg_assert_same(array(1 => 'Ab12Cd34', 2 => 'Zz99Yy88', 3 => 'Ab12Cd34', 4 => 'Ab12Cd34'), $hints, 'each thread names its link');
	pg_assert_same(2, $pages[0]['links'], 'a page with two links\' comments');
	pg_assert_same(1, $pages[1]['threads'][0]['n'], 'every page and link numbers from 1');
	pg_assert_same(1, $pages[1]['links'], 'a page with one link');
	pg_assert_same('', $pages[1]['threads'][0]['link_hint'], 'a link without a hint has none');
	pg_assert_same(array(4, 2, 1, 3), array_column($pages[0]['threads'], 'id'), 'still open first, newest first');
}

function test_proto_comment_bell_words_and_address()
{
	$words = pg_proto_comment_bell_words('Home & more', 'Ayşe');

	pg_assert_same('New prototype comment', $words['short'], 'the short title says what happened');
	pg_assert_true(strpos($words['short'], ':') === false, 'no colon in the short title');
	pg_assert_same('Ayşe on Home & more', $words['brief'], 'the brief names the guest and the page');
	pg_assert_true(strpos($words['title'], 'Ayşe') !== false && strpos($words['title'], 'Home & more') !== false, 'the sentence names both');

	$gone = pg_proto_comment_bell_words('', '');
	pg_assert_same('New prototype comment', $gone['short'], 'a deleted comment keeps the title');
	pg_assert_true($gone['brief'] !== '', 'a deleted comment still has a text');

	pg_assert_same('edit_system_style.php?id=31&page=120&comments=1', pg_proto_comment_bell_url(31, 120), 'opens the design with the list');
	pg_assert_same('view_pages.php', pg_proto_comment_bell_url(0, 120), 'no design, the pages list');
}

function test_proto_comment_bell_is_once_an_hour_per_link_on_the_firewall_counter()
{
	$source = pg_test_function_source('pg_proto_comment_bell');

	pg_assert_true(strpos($source, "waf_rate_exceeded((string) (int) \$link['id'], 'prototype-comment-bell', 1, 3600)") !== false, 'the link id is the subject, limit 1, an hour');
	pg_assert_true(strpos($source, "'user_id'] > 0") !== false, 'a staff answer rings no bell');
	pg_assert_true(strpos($source, "created_by") !== false, 'to the link\'s maker');

	$door = (string) file_get_contents(PG_FUNCTIONS_DIR . '/prototype_comment.php');
	pg_assert_true(strpos($door, 'pg_proto_comment_bell($pg_pc_link, $pg_pc_row)') !== false, 'the door rings it after a saved comment');
}

function test_proto_comment_bell_line_is_addressed_and_drawn()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/notifications.php');

	pg_assert_true(strpos($source, "'prototype_comment'") !== false, 'the action is known to the bell');
	pg_assert_true(pg_notification_module('prototype_comment') === 'content', 'a content row');

	pg_assert_same(false, pg_notification_visible(array('action' => 'prototype_comment', 'target_user_id' => 0), array('id' => 3, 'role' => 0)), 'never unaddressed');
	pg_assert_same(false, pg_notification_visible(array('action' => 'prototype_comment', 'target_user_id' => 4), array('id' => 3, 'role' => 0)), 'another person\'s');
	pg_assert_same(true, pg_notification_visible(array('action' => 'prototype_comment', 'target_user_id' => 3), array('id' => 3, 'role' => 1)), 'the maker\'s');
}

function test_proto_comment_presence_stamp_and_actions_are_designers_only()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');

	pg_assert_true(strpos($source, "'proto_comments' => pg_proto_comments_style_stamp(\$co_style)") !== false, 'the presence answer carries the design\'s stamp');
	pg_assert_true((bool) preg_match('/\$co_proto\s*=\s*pg_designer_is_full\(\$user\)\s*&&\s*pg_proto_comments_ready\(\)/', $source), 'only for a designer, on an upgraded site');

	foreach (array('proto_comments', 'proto_comment_answer', 'proto_comment_resolve', 'proto_comment_delete') as $sub) {
		pg_assert_true(strpos($source, "case '" . $sub . "':") !== false, $sub . ' is an action');
		pg_assert_true(!preg_match("/content_ok = array\([^)]*'" . $sub . "'/s", $source), $sub . ' is not opened to a content operator');
	}

	pg_assert_same('SELECT MAX(id) FROM prototype_comments WHERE style_id = 31', pg_proto_comments_style_stamp_sql(31), 'the design\'s newest id (idx_style)');
	pg_assert_same('', pg_proto_comments_style_stamp_sql(0), 'no design, no statement');
}

function test_proto_comment_part_is_handed_to_designers_and_registered_once()
{
	pg_assert_same('assets/js/style_designer_comments.min.js', pg_designer_parts()['comments'], 'the part is listed');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, "(\$access === PG_DESIGNER_ACCESS_FULL && pg_proto_comments_ready()) ? array('comments')") !== false, 'handed over to designers only');

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_same(1, substr_count($core, "_sdPartCall('comments', 'beat'"), 'one line in the core runs the part on the heartbeat');
	pg_assert_same(1, substr_count($core, "_sdPartCall('comments', 'reload'"), 'the Share prototype dialog reads a loaded list again after a link\'s comments go');
	pg_assert_same(2, substr_count($core, "_sdPartCall('comments'"), 'nothing else calls the part');

	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_comments.js');
	pg_assert_true(strpos($part, "StyleDesigner.definePart('comments'") !== false, 'the part registers itself');
	pg_assert_true(strpos($part, 'innerHTML = ') === false || strpos($part, 'esc(') !== false, 'markup is escaped');
}

function test_proto_comment_erase_takes_a_deleted_users_answers()
{
	$tables = pg_privacy_erase_tables();

	pg_assert_true(isset($tables['prototype_comments']), 'an entry for prototype comments');
	pg_assert_same(array('user_id' => 'user_ids'), $tables['prototype_comments']['find'], 'found by the user');
	pg_assert_same('anonymise', $tables['prototype_comments']['action'], 'kept in the thread');
	pg_assert_same(array('author_name' => '{name}', 'user_id' => 0), $tables['prototype_comments']['fields'], 'the name replaced, the user id 0');
}

// "Delete comments" in the Share prototype dialog's links list: each link's
// comments (answers counted), read in one statement on the design.
function test_proto_comments_link_counts_are_read_by_design()
{
	pg_assert_same('SELECT link_id, COUNT(*) AS n FROM prototype_comments WHERE style_id = 31 GROUP BY link_id', pg_proto_comments_link_counts_sql(31), 'counted by link on idx_style');
	pg_assert_same('', pg_proto_comments_link_counts_sql(0), 'no design, no statement');
}

// A revoked link is not in the list, but its comments are kept for the
// designer: it stays listed, newest first, until they are deleted.
function test_proto_comments_unlisted_links_are_the_ones_with_comments()
{
	$listed = array(array('id' => 9), array('id' => 7));

	pg_assert_same(array(8, 4), pg_proto_comments_unlisted_links($listed, array(4 => 2, 7 => 5, 8 => 1)), 'only the links not listed, newest first');
	pg_assert_same(array(), pg_proto_comments_unlisted_links($listed, array(9 => 3)), 'every link with comments is listed');
	pg_assert_same(array(), pg_proto_comments_unlisted_links(array(), array(5 => 0, 0 => 4)), 'no comments, no link 0');
}

function test_proto_comments_links_list_carries_counts_and_revoked_links_with_comments()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');

	$panel = pg_test_function_source('pg_proto_links_panel');

	pg_assert_contains('pg_proto_comments_link_counts($style_id)', $panel, 'the counts are read once for the design');
	pg_assert_contains('pg_proto_comments_unlisted_links(', $panel, 'the revoked links with comments');
	pg_assert_contains('pg_proto_links_revoked_of_design($style_id, ', $panel, 'read only among the design\'s revoked links');
	pg_assert_contains("'comment_count'", $panel, 'each row says how many');

	$revoked = pg_test_function_source('pg_proto_links_revoked_of_design');
	pg_assert_contains("style_id = '\" . (int) \$style_id . \"' AND revoked_at > 0", $revoked, 'another design\'s link or an open one never comes in this way');
}

// The designer's delete takes a link instead of a comment: every comment of
// one link of the design named, written to the Site Log by the link's id and
// hint, never its token.
function test_proto_comments_link_delete_is_the_designers_delete_by_link()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');
	$start = strpos($source, "case 'proto_comment_delete':");
	$block = ($start !== false) ? substr($source, $start, 2500) : '';

	pg_assert_contains("if ((\$sub === 'proto_comment_delete') && !empty(\$request['link_id'])) {", $block, 'a link named instead of a comment');
	pg_assert_contains("pg_proto_comments_delete_link_of_design((int)\$request['link_id'], \$pc_style, (string)\$user['username'])", $block, 'only a link of the design the dialog was opened on');
	pg_assert_true(strpos($block, "'deleted'") !== false, 'the answer says how many went');

	$fn = pg_test_function_source('pg_proto_comments_delete_link_of_design');
	pg_assert_contains("(int) \$link['style_id'] !== (int) \$style_id", $fn, 'another design\'s link is refused');
	pg_assert_contains("pg_proto_comments_delete_link(\$link['id'])", $fn, 'the link\'s comments, open and resolved, with their answers');
	pg_assert_contains('#{var:2} ({var:3}…)', $fn, 'the Site Log names the link by id and hint');
	pg_assert_false((strpos($fn, 'token_hash') !== false) || (strpos($fn, 'token_secret') !== false), 'never the token');
	pg_assert_same(null, pg_proto_comments_delete_link_of_design(0, 31, 'erdal'), 'no link, nothing deleted');
	pg_assert_same(null, pg_proto_comments_delete_link_of_design(7, 0, 'erdal'), 'no design, nothing deleted');
}

function test_proto_comments_link_delete_button_asks_first_and_reads_the_lists_again()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$start = strpos($source, 'function _pgPrototypeShareDialog(');
	$end = strpos($source, 'function _pgTabsSyncViewButton(');
	$dialog = (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';

	pg_assert_true($dialog !== '', 'the dialog found');
	pg_assert_contains('data-ps-comments-delete', $dialog, 'the button');
	pg_assert_contains('l.comment_count > 0', $dialog, 'only on a link with comments');
	pg_assert_contains("l.state === 'revoked'", $dialog, 'a revoked link offers nothing else');
	pg_assert_contains("_pgApiPost('proto_comment_delete', { style_id: styleId, link_id: id })", $dialog, 'the designer\'s delete, by link');

	$ask = strpos($dialog, "title: _sdT('Delete comments')");
	$post = strpos($dialog, "_pgApiPost('proto_comment_delete'");
	pg_assert_true(($ask !== false) && ($post !== false), 'asked and posted');
	pg_assert_contains("if (_sdPartLoaded('comments')) _sdPartCall('comments', 'reload', [])", $dialog, 'an open Comments list is read again');

	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_comments.js');
	pg_assert_contains('reload: function () { return started ? fetchList() : Promise.resolve(); }', $part, 'the part reads its list again on request');
}
