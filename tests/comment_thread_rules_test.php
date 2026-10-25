<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the rules a page shows a comment thread by (includes/fn/
 * comments.php): the page's comment settings (pg_comments_settings()),
 * whether the thread takes new comments (pg_comments_allow_new()), who reads
 * it (pg_comments_viewer()), which comments they read and the statements
 * that read them (pg_comments_rows()), and the order a thread is drawn in
 * (pg_comments_thread_order()). The classic comments block of
 * get_page_content.php and add_comment.php ask these helpers; each decision
 * and statement is compared with a frozen copy of the block's code from
 * before the rules moved, so the block's HTML builder gets the same values
 * from the same statements.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// escape() asks the connection class; without a database it falls back to
// addslashes() once the class exists.
function comment_thread_rules_test_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

// A statement with its whitespace runs folded into one space: the helpers
// are indented differently from the block they came out of.
function comment_thread_rules_test_squash($sql)
{
	return preg_replace('/\s+/', ' ', trim((string) $sql));
}

// A fetch stub for the helpers' $fetch: records every statement and answers
// the next canned answer (a list of assoc rows), or none when they run out.
function comment_thread_rules_test_fetch(array $answers, array &$queries)
{
	return function ($sql) use (&$answers, &$queries) {
		$queries[] = $sql;

		return $answers ? array_shift($answers) : array();
	};
}

function comment_thread_rules_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function comment_thread_rules_test_column(array $entries, $key)
{
	$out = array();

	foreach ($entries as $entry) {
		$out[] = $entry[$key];
	}

	return $out;
}

// -- frozen oracle -----------------------------------------------------------
//
// A copy of the classic comments block's code (get_page_content.php) and of
// add_comment.php's allow-new query from before the rules moved into
// includes/fn/comments.php: the yardstick the helpers are held to. Never edit
// it to match the helpers. The only changes are the parameters standing in
// for the block's variables ($_SESSION['software']['added_comments'] is
// $session, null when unset; USER_LOGGED_IN and USER_ID are $logged_in and
// $user_id; $_GET is $get; the allow_new_comments_for_items row is $row_or_null).

// The block's WHERE clause after the published filter.
function comment_thread_rules_test_legacy_where($item_page, $item_type, $item_id, $edit_access, $form_editor, $comments_for_search_index, $session)
{
	$sql_where = '';

	if ($item_page) {
		$sql_where = " AND (item_id = '" . escape($item_id) . "') AND (item_type = '" . escape($item_type) . "')";
	}

	// If the visitor does not have edit access to this page,
	// and the visitor is not the form editor,
	// then prepare sql to restrict which comments should appear
	if ((!$edit_access && !$form_editor) || $comments_for_search_index) {
		$sql_added_comments = "";

		// if session array is set then loop through comments and add them to sql statement
		if (!$comments_for_search_index && (isset($session) == TRUE)) {
			// loop through added comments and add each comment id to the sql statement
			foreach ($session as $added_comment) {
				if ($sql_added_comments != '') {
					$sql_added_comments .= " OR ";
				}

				$sql_added_comments .= "(comments.id = '" . escape($added_comment) . "')";
			}

			// if there was an added comment, then add wrapper around added comment(s)
			if ($sql_added_comments != '') {
				$sql_added_comments = " OR ($sql_added_comments)";
			}
		}

		$sql_where .= " AND ((comments.published = '1')$sql_added_comments)";
	}

	return $sql_where;
}

// Whether the visitor is the shown record's form editor.
function comment_thread_rules_test_legacy_form_editor($comments_page_type, $form_editor_username, $logged_in, $user_id, $form_editor_user_id)
{
	$form_editor = false;

	// If the visitor is the form editor, then remember that for later.
	if (
		($comments_page_type == 'form item view')
		&& ($form_editor_username != '')
		&& ($logged_in)
		&& ($user_id == $form_editor_user_id)
	) {
		$form_editor = true;
	}

	return $form_editor;
}

// Whether the thread takes new comments; $row_or_null is the
// allow_new_comments_for_items row, null when the query found none.
function comment_thread_rules_test_legacy_allow_new($item_id, $row_or_null, $page_value)
{
	// assume that new comments are not allowed, until we find out otherwise
	$allow_new_comments = FALSE;

	// if these are comments for a specific item, then determine if new comments are allowed for the item
	if ($item_id != 0) {
		// if a result was found, then use the result
		if ($row_or_null !== null) {
			$row = $row_or_null;

			// if new comments are are allowed then remember that
			if ($row['allow_new_comments'] == 1) {
				$allow_new_comments = TRUE;
			}

		// else a result was not found, so use value from page
		} else {
			// if new comments are allowed for the page, then remember that
			if ($page_value == 1) {
				$allow_new_comments = TRUE;
			}
		}

	// else these are comments just for the page, not for an item, so use value from page
	} else {
		// if new comments are allowed for the page, then remember that
		if ($page_value == 1) {
			$allow_new_comments = TRUE;
		}
	}

	return $allow_new_comments;
}

// The allow-new query the block and add_comment.php sent.
function comment_thread_rules_test_legacy_allow_new_sql($page_id, $item_type, $item_id)
{
	return
		"SELECT allow_new_comments
		FROM allow_new_comments_for_items
		WHERE
			(page_id = '" . escape($page_id) . "')
			AND (item_id = '" . escape($item_id) . "')
			AND (item_type = '" . escape($item_type) . "')";
}

// Whether featured comments exist, and whether only they are shown.
function comment_thread_rules_test_legacy_featured($featured_count, array $get)
{
	$row = array($featured_count);

	// Assume that there are not any featured comments until we find out otherwise.
	$featured_comments_exist = false;

	// If there are featured comments then remember that.
	if ($row[0] > 0) {
		$featured_comments_exist = true;
	}

	// Assume that we are not just showing featured comments until we find out otherwise.
	$featured = false;

	// If featured comments exist and the comments view is not set in the query string
	// or it is set to "featured", then add filter to SQL where clause so query only gets
	// featured comments, and remember that we are only showing featured comments.
	if (
		($featured_comments_exist == true)
		&&
		(
			(isset($get['comments']) == false)
			|| ($get['comments'] == 'featured')
		)
	) {
		$featured = true;
	}

	return array($featured_comments_exist, $featured);
}

// The three statements: the count, the featured count and the rows.
function comment_thread_rules_test_legacy_statements($page_id, $sql_where, $featured)
{
	$statements = array();

	// Get the total number of comments.  We will output this further below.
	// We do this before dealing with the featured comments because we want a total number of featured and non-featured comments.
	$statements[] = "SELECT COUNT(*) FROM comments WHERE comments.page_id = '" . escape($page_id) . "'$sql_where";

	// Determine if there are featured comments.  We will use this to decide if we need to filter
	// for featured comments and if we need to output different view links for "all" and "featured".
	$statements[] =
		"SELECT COUNT(*)
		FROM comments
		WHERE
			(comments.page_id = '" . escape($page_id) . "')
			$sql_where
			AND comments.featured = '1'";

	if ($featured) {
		$sql_where .= " AND (comments.featured = '1')";
	}

	// get comments data
	$statements[] =
		"SELECT
			comments.id,
			comments.name,
			comments.message,
			files.name as file_name,
			files.size as file_size,
			comments.published,
			comments.publish_date_and_time,
			comments.publish_cancel,
			comments.featured,
			comments.rating,
			" . (pg_reviews_ready() ? "comments.verified_order_id," : "0 AS verified_order_id,") . "
			" . (pg_review_replies_ready() ? "comments.seller_reply, comments.seller_reply_at," : "'' AS seller_reply, 0 AS seller_reply_at,") . "
			user.user_username as username,
			user.user_role,
			user.user_id,
			user.user_badge,
			user.user_badge_label,
			comments.created_timestamp
		FROM comments
		LEFT JOIN files ON comments.file_id = files.id
		LEFT JOIN user ON comments.created_user_id = user.user_id
		WHERE comments.page_id = '" . escape($page_id) . "'$sql_where
		ORDER BY comments.created_timestamp ASC";

	return $statements;
}

// -- settings ----------------------------------------------------------------

// A page row that is not one, or has none of the columns, reads as comments
// off, every switch off, every text empty and every id 0.
function test_comment_thread_rules_settings_defaults()
{
	$expected = array(
		'enabled' => false,
		'label' => '',
		'message' => '',
		'rating' => false,
		'allow_new' => false,
		'closed_message' => '',
		'auto_publish' => false,
		'select_name' => false,
		'require_login' => false,
		'attachments' => false,
		'show_date' => false,
		'admin_email' => '',
		'conditional_admins' => false,
		'submitter_email_page_id' => 0,
		'watcher_email_page_id' => 0,
		'watchers_by_submitter' => false,
	);

	foreach (array(null, false, '', 'x', 5, array()) as $row) {
		pg_assert_same($expected, pg_comments_settings($row), 'page row ' . var_export($row, true));
	}
}

// Each switch keeps the operator the block and the handlers test it with:
// comments and automatic publish compare with '1', the other switches with
// 1, the stars are on when not empty. Ids are whole numbers, never below 0;
// texts pass through as strings.
function test_comment_thread_rules_settings_switches()
{
	$switches = array(
		'enabled' => array('comments', '1'),
		'auto_publish' => array('comments_automatic_publish', '1'),
		'allow_new' => array('comments_allow_new_comments', 1),
		'select_name' => array('comments_allow_user_to_select_name', 1),
		'require_login' => array('comments_require_login_to_comment', 1),
		'attachments' => array('comments_allow_file_attachments', 1),
		'show_date' => array('comments_show_submitted_date_and_time', 1),
		'conditional_admins' => array('comments_administrator_email_conditional_administrators', 1),
		'watchers_by_submitter' => array('comments_watchers_managed_by_submitter', 1),
	);

	$values = array(
		array('1', true),
		array(1, true),
		array('0', false),
		array('', false),
		array('2', false),
		array(null, false),
		array(array(), false),
		array(array('1'), false),
	);

	foreach ($switches as $key => $switch) {
		list($column, $operand) = $switch;

		foreach ($values as $value) {
			pg_assert_same($value[1], pg_comments_settings(array($column => $value[0]))[$key], $key . ' with ' . var_export($value[0], true));
		}

		// '1abc' tells the two operators apart on PHP 7 ('1abc' == 1 there),
		// while '1abc' == '1' is false on every version.
		pg_assert_same('1abc' == $operand, pg_comments_settings(array($column => '1abc'))[$key], $key . ' keeps its operator');
	}

	foreach (array(array('1', true), array(1, true), array('2', true), array('5', true), array('0', false), array('', false), array(null, false), array(array(), false), array(array(1), false)) as $value) {
		pg_assert_same($value[1], pg_comments_settings(array('comments_rating' => $value[0]))['rating'], 'rating with ' . var_export($value[0], true));
	}

	foreach (array('submitter_email_page_id' => 'comments_submitter_email_page_id', 'watcher_email_page_id' => 'comments_watcher_email_page_id') as $key => $column) {
		foreach (array(array('12', 12), array(12, 12), array('0', 0), array('', 0), array('-3', 0), array('abc', 0), array(null, 0), array(true, 0), array(array(12), 0)) as $value) {
			pg_assert_same($value[1], pg_comments_settings(array($column => $value[0]))[$key], $key . ' with ' . var_export($value[0], true));
		}
	}

	foreach (array('label' => 'comments_label', 'message' => 'comments_message', 'closed_message' => 'comments_disallow_new_comment_message', 'admin_email' => 'comments_administrator_email_to_email_address') as $key => $column) {
		foreach (array(array('Answer', 'Answer'), array(' <b>x</b> ', ' <b>x</b> '), array(5, '5'), array('', ''), array(null, ''), array(array('x'), '')) as $value) {
			pg_assert_same($value[1], pg_comments_settings(array($column => $value[0]))[$key], $key . ' with ' . var_export($value[0], true));
		}
	}

	// A second blank <system> region of the page gets the rows of the first
	// as its comments switch, which keeps it from drawing comments again.
	pg_assert_false(pg_comments_settings(array('comments' => array()))['enabled'], 'no rows');
	pg_assert_false(pg_comments_settings(array('comments' => array(array('id' => '1'))))['enabled'], 'some rows');

	$all = pg_comments_settings(array(
		'comments' => '1',
		'comments_label' => 'Answer',
		'comments_rating' => '1',
		'comments_allow_new_comments' => '1',
		'comments_watcher_email_page_id' => '31',
	));
	pg_assert_true($all['enabled'] && $all['rating'] && $all['allow_new'], 'a page row with comments on');
	pg_assert_same('Answer', $all['label']);
	pg_assert_same(31, $all['watcher_email_page_id']);
}

// -- allow new ---------------------------------------------------------------

// The item's own switch decides when the thread is an item's and the item
// has a row; otherwise the page's switch. The same answer as the block for
// every item id, row and page switch.
function test_comment_thread_rules_allow_new_decision()
{
	$item_ids = array(0, '0', '', null, 7, '7', 'abc', '12abc', array());
	$rows = array(null, '1', '0', '', '2');

	foreach ($item_ids as $item_id) {
		foreach ($rows as $row) {
			foreach (array(true, false) as $page) {
				$label = var_export(array($item_id, $row, $page), true);
				$got = pg_comments_allow_new_decision($item_id, ($row !== null) && is_scalar($item_id) && ($item_id != 0) ? $row : null, $page);

				if (!is_scalar($item_id) && ($item_id !== null)) {
					// Not a value (a forged address or post): read as no item,
					// where escape() used to stop the request with a TypeError.
					pg_assert_same($page, $got, 'non-scalar id gives the page switch: ' . $label);
					continue;
				}

				$legacy = comment_thread_rules_test_legacy_allow_new($item_id, ($row === null) ? null : array('allow_new_comments' => $row), $page ? '1' : '0');
				pg_assert_same($legacy, $got, $label);
			}
		}
	}

	pg_assert_false(pg_comments_allow_new_decision(7, array('1'), true), 'a row value that is not one reads as closed');
	pg_assert_true(pg_comments_allow_new_decision(0, '0', true), 'a page thread ignores any item setting');
}

// The reader asks allow_new_comments_for_items only for an item thread, with
// the statement the block and add_comment.php sent; the first row decides.
function test_comment_thread_rules_allow_new_reader()
{
	comment_thread_rules_test_db_stub();

	foreach (array(0, '0', array(), null) as $item_id) {
		$queries = array();
		pg_assert_true(pg_comments_allow_new('5', '', $item_id, true, comment_thread_rules_test_fetch(array(array(array('allow_new_comments' => '0'))), $queries)), 'page open: ' . var_export($item_id, true));
		pg_assert_same(0, count($queries), 'no query for ' . var_export($item_id, true));
		pg_assert_false(pg_comments_allow_new('5', '', $item_id, false, comment_thread_rules_test_fetch(array(), $queries)), 'page closed: ' . var_export($item_id, true));
	}

	$queries = array();
	pg_assert_true(pg_comments_allow_new('5', 'submitted_form', 7, true, comment_thread_rules_test_fetch(array(array()), $queries)), 'no row: the page decides (open)');
	pg_assert_same(1, count($queries), 'one query');
	pg_assert_same(comment_thread_rules_test_squash(comment_thread_rules_test_legacy_allow_new_sql('5', 'submitted_form', 7)), comment_thread_rules_test_squash($queries[0]), 'the legacy statement');

	$queries = array();
	pg_assert_false(pg_comments_allow_new('5', 'submitted_form', '7', false, comment_thread_rules_test_fetch(array(array()), $queries)), 'no row: the page decides (closed)');

	$queries = array();
	pg_assert_false(pg_comments_allow_new('5', 'submitted_form', 7, true, comment_thread_rules_test_fetch(array(array(array('allow_new_comments' => '0'))), $queries)), 'the item row closes an open page');
	pg_assert_true(pg_comments_allow_new('5', 'calendar_event', 7, false, comment_thread_rules_test_fetch(array(array(array('allow_new_comments' => '1'))), $queries)), 'the item row opens a closed page');
	pg_assert_true(pg_comments_allow_new('5', 'product', 7, false, comment_thread_rules_test_fetch(array(array(array('allow_new_comments' => '1'), array('allow_new_comments' => '0'))), $queries)), 'the first row decides');
	pg_assert_false(pg_comments_allow_new('5', 'product', 7, true, comment_thread_rules_test_fetch(array(array(array('other' => '1'))), $queries)), 'a row without the column reads as closed');

	$queries = array();
	pg_comments_allow_new("5'", "x'y", "7'", true, comment_thread_rules_test_fetch(array(array()), $queries));
	pg_assert_same(comment_thread_rules_test_squash(comment_thread_rules_test_legacy_allow_new_sql("5'", "x'y", "7'")), comment_thread_rules_test_squash($queries[0]), 'values escaped as before');
}

// -- viewer ------------------------------------------------------------------

// The form editor is the signed-in user the shown record names, on a record
// thread only, as the block decided it. The comments of this session keep
// their order and duplicates; the search index reads none of them.
function test_comment_thread_rules_viewer_decision()
{
	foreach (array('form item view', 'standard') as $page_type) {
		foreach (array('', 'bob', '0', null) as $username) {
			foreach (array(array(false, ''), array(true, 5), array(true, '5'), array(true, 6)) as $login) {
				foreach (array('5', 5, '', null, '6') as $editor_id) {
					$label = var_export(array($page_type, $username, $login, $editor_id), true);
					$legacy = comment_thread_rules_test_legacy_form_editor($page_type, $username, $login[0], $login[1], $editor_id);
					$record = ($page_type == 'form item view') ? array('form_editor_user_id' => $editor_id, 'form_editor_username' => $username) : null;
					$viewer = pg_comments_viewer_decision(false, $record, false, $login[0] ? $login[1] : 0, array());
					pg_assert_same($legacy, $viewer['form_editor'], $label);
				}
			}
		}
	}

	$viewer = pg_comments_viewer_decision(1, null, 0, 0, array(9, '4', 9, 0, -2, 'x', '3abc', true, null, array(5), 2.0));
	pg_assert_same(array('staff' => true, 'form_editor' => false, 'own_ids' => array(9, 4, 9, 3, 2), 'search_index' => false), $viewer, 'own ids keep order and duplicates, junk dropped');

	pg_assert_same(array(), pg_comments_viewer_decision(false, null, false, 0, 'x')['own_ids'], 'a session value that is not a list');
	pg_assert_same(array(), pg_comments_viewer_decision(false, null, false, 0, null)['own_ids'], 'no session value');

	$viewer = pg_comments_viewer_decision(true, array('form_editor_user_id' => '5', 'form_editor_username' => 'ed'), true, 5, array(41));
	pg_assert_same(array('staff' => true, 'form_editor' => true, 'own_ids' => array(), 'search_index' => true), $viewer, 'the search index keeps staff, reads no own ids');

	pg_assert_false(pg_comments_viewer_decision(false, array('form_editor_user_id' => true, 'form_editor_username' => 'ed'), false, 1, array())['form_editor'], 'an editor id that is not a value');
	pg_assert_false(pg_comments_viewer_decision(false, array('form_editor_user_id' => '5', 'form_editor_username' => array('ed')), false, 5, array())['form_editor'], 'a username that is not a value');
	pg_assert_false(pg_comments_viewer_decision(false, array(), false, 5, array())['form_editor'], 'a record without an editor');
}

// The reader takes the signed-in user and this session's comments from the
// request; signed out, the form editor is nobody.
function test_comment_thread_rules_viewer_reader()
{
	$had_session = isset($_SESSION);
	$previous = $had_session ? $_SESSION : null;

	try {
		$_SESSION['software']['added_comments'] = array(4, '7', 'x', 0);

		$viewer = pg_comments_viewer(true, null, false);
		pg_assert_same(array('staff' => true, 'form_editor' => false, 'own_ids' => array(4, 7), 'search_index' => false), $viewer, 'staff with two comments of this session');

		$viewer = pg_comments_viewer(true, null, true);
		pg_assert_same(array(), $viewer['own_ids'], 'the search index reads none');
		pg_assert_true($viewer['staff'], 'and keeps staff');

		if (!(defined('USER_LOGGED_IN') && USER_LOGGED_IN)) {
			pg_assert_false(pg_comments_viewer(false, array('form_editor_user_id' => '0', 'form_editor_username' => 'ed'), false)['form_editor'], 'signed out is no form editor');
		}

		$_SESSION['software']['added_comments'] = 'x';
		pg_assert_same(array(), pg_comments_viewer(false, null, false)['own_ids'], 'a session value that is not a list');

		unset($_SESSION['software']['added_comments']);
		pg_assert_same(array(), pg_comments_viewer(false, null, false)['own_ids'], 'no session value');
	} finally {
		if ($had_session) {
			$_SESSION = $previous;
		} else {
			unset($_SESSION);
		}
	}
}

// -- which comments ----------------------------------------------------------

// The thread filter and the visibility filter, put together, are the block's
// WHERE clause for every thread, viewer and session.
function test_comment_thread_rules_visible_and_thread_sql()
{
	comment_thread_rules_test_db_stub();

	$threads = array(array('submitted_form', 12), array('', '0'), array('product', '7'), array("x'y", null));
	$sessions = array(null, array(), array(5, 9), array('5', 9), array(12));
	$cases = 0;

	foreach (array(true, false) as $item_page) {
		foreach ($threads as $thread) {
			foreach (array(false, true) as $edit) {
				foreach (array(false, true) as $form_editor) {
					foreach (array(false, true) as $search_index) {
						foreach ($sessions as $session) {
							$legacy = comment_thread_rules_test_legacy_where($item_page, $thread[0], $thread[1], $edit, $form_editor, $search_index, $session);

							$viewer = pg_comments_viewer_decision($edit, null, $search_index, 0, $session);
							$viewer['form_editor'] = $form_editor;

							$got = pg_comments_thread_sql($item_page ? array('item_type' => $thread[0], 'item_id' => $thread[1]) : null) . pg_comments_visible_sql($viewer);

							pg_assert_same($legacy, $got, var_export(array($item_page, $thread, $edit, $form_editor, $search_index, $session), true));
							$cases++;
						}
					}
				}
			}
		}
	}

	pg_assert_same(320, $cases, 'every combination');

	pg_assert_same('', pg_comments_thread_sql('x'), 'a thread that is not one');
	pg_assert_same(" AND (item_id = '') AND (item_type = '')", pg_comments_thread_sql(array()), 'a thread without a key');
	pg_assert_same(" AND (item_id = '') AND (item_type = '')", pg_comments_thread_sql(array('item_type' => array('x'), 'item_id' => array(7))), 'values that are not values');
	pg_assert_same(" AND ((comments.published = '1'))", pg_comments_visible_sql('x'), 'a viewer that is not one is nobody');
}

// With the 'filter' mode, the featured comments alone are shown while some
// exist and ?comments is unset or 'featured', as the block decided; the
// widget's other modes never filter.
function test_comment_thread_rules_featured_filter()
{
	foreach (array(true, false) as $has) {
		foreach (array('__unset__', 'featured', 'all', '', array('featured'), 'FEATURED', '0') as $value) {
			$get = ($value === '__unset__') ? array() : array('comments' => $value);
			$legacy = comment_thread_rules_test_legacy_featured($has ? '1' : '0', $get);
			$view = isset($get['comments']) ? $get['comments'] : null;

			pg_assert_same($legacy[0], $has, 'has featured');
			pg_assert_same($legacy[1], pg_comments_featured_filter($has, 'filter', $view), var_export(array($has, $value), true));

			foreach (array('first', 'none', '', null) as $mode) {
				pg_assert_false(pg_comments_featured_filter($has, $mode, $view), 'mode ' . var_export($mode, true));
			}
		}
	}
}

// The snapshot table: for each viewer and state, the statements the rows
// helper sends are the block's, and it hands back what the block held in
// $number_of_comments, $featured_comments_exist, $featured and $comments.
function test_comment_thread_rules_states()
{
	comment_thread_rules_test_db_stub();

	$published = array('id' => '31', 'name' => 'Ann', 'message' => 'First', 'published' => '1', 'featured' => '0', 'created_timestamp' => '1700000000');
	$same_second = array('id' => '32', 'name' => 'Bob', 'message' => 'Same second', 'published' => '1', 'featured' => '0', 'created_timestamp' => '1700000000');
	$featured_row = array('id' => '33', 'name' => 'Cem', 'message' => 'Best', 'published' => '1', 'featured' => '1', 'created_timestamp' => '1700000100');
	$own_pending = array('id' => '41', 'name' => 'Me', 'message' => 'Mine', 'published' => '0', 'featured' => '0', 'created_timestamp' => '1700000200');
	$other_pending = array('id' => '42', 'name' => 'Eve', 'message' => 'Theirs', 'published' => '0', 'featured' => '0', 'created_timestamp' => '1700000300');
	$scheduled = array('id' => '43', 'name' => 'Staff', 'message' => 'Later', 'published' => '0', 'publish_date_and_time' => '2099-01-01 10:00:00', 'publish_cancel' => '1', 'featured' => '0', 'created_timestamp' => '1700000400');

	$base = array(
		'page_id' => '5',
		'item_page' => false,
		'item_type' => '',
		'item_id' => '0',
		'staff' => false,
		'page_type' => 'standard',
		'editor' => array('', ''),
		'login' => array(false, ''),
		'session' => null,
		'search_index' => false,
		'get' => array(),
		'count' => '2',
		'featured_count' => '0',
		'rows' => array($published, $same_second),
	);

	$cases = array(
		'signed out' => array(),
		'member with a pending comment' => array(
			'item_page' => true, 'item_type' => 'submitted_form', 'item_id' => '12', 'page_type' => 'form item view',
			'editor' => array('3', 'ed'), 'login' => array(true, '7'), 'session' => array(41),
			'count' => '3', 'rows' => array($published, $same_second, $own_pending),
		),
		'staff' => array(
			'staff' => true, 'count' => '4', 'rows' => array($published, $same_second, $other_pending, $scheduled),
		),
		'form editor' => array(
			'item_page' => true, 'item_type' => 'submitted_form', 'item_id' => '12', 'page_type' => 'form item view',
			'editor' => array('7', 'ed'), 'login' => array(true, '7'),
			'count' => '3', 'rows' => array($published, $same_second, $other_pending),
		),
		'featured' => array(
			'count' => '3', 'featured_count' => '1', 'rows' => array($featured_row),
		),
		'featured, ?comments=featured' => array(
			'count' => '3', 'featured_count' => '1', 'get' => array('comments' => 'featured'), 'rows' => array($featured_row),
		),
		'featured, ?comments=all' => array(
			'count' => '3', 'featured_count' => '1', 'get' => array('comments' => 'all'), 'rows' => array($published, $same_second, $featured_row),
		),
		'scheduled, staff' => array(
			'staff' => true, 'count' => '3', 'rows' => array($published, $same_second, $scheduled),
		),
		'scheduled, signed out' => array(),
		'failed post' => array(),
		'search index' => array(
			'item_page' => true, 'item_type' => 'submitted_form', 'item_id' => '12', 'page_type' => 'form item view',
			'editor' => array('3', 'ed'), 'login' => array(true, '3'), 'staff' => true, 'session' => array(41), 'search_index' => true,
		),
		'catalog detail product' => array(
			'item_page' => true, 'item_type' => 'product', 'item_id' => '9', 'session' => array('41', 44),
		),
		'calendar event' => array(
			'item_page' => true, 'item_type' => 'calendar_event', 'item_id' => '3',
		),
	);

	$signed_out = null;

	foreach ($cases as $name => $overrides) {
		$case = array_merge($base, $overrides);

		$form_editor = comment_thread_rules_test_legacy_form_editor($case['page_type'], $case['editor'][1], $case['login'][0], $case['login'][1], $case['editor'][0]);
		$where = comment_thread_rules_test_legacy_where($case['item_page'], $case['item_type'], $case['item_id'], $case['staff'], $form_editor, $case['search_index'], $case['session']);
		$featured = comment_thread_rules_test_legacy_featured($case['featured_count'], $case['get']);
		$statements = array_map('comment_thread_rules_test_squash', comment_thread_rules_test_legacy_statements($case['page_id'], $where, $featured[1]));

		$record = ($case['page_type'] == 'form item view') ? array('form_editor_user_id' => $case['editor'][0], 'form_editor_username' => $case['editor'][1]) : null;
		$viewer = pg_comments_viewer_decision($case['staff'], $record, $case['search_index'], $case['login'][0] ? $case['login'][1] : 0, $case['session']);
		pg_assert_same($form_editor, $viewer['form_editor'], $name . ': form editor');

		$queries = array();
		$fetch = comment_thread_rules_test_fetch(array(
			array(array('COUNT(*)' => $case['count'])),
			array(array('COUNT(*)' => $case['featured_count'])),
			$case['rows'],
		), $queries);

		$thread = $case['item_page'] ? array('item_type' => $case['item_type'], 'item_id' => $case['item_id']) : null;
		$read = pg_comments_rows($case['page_id'], $thread, $viewer, array('featured' => 'filter', 'view' => isset($case['get']['comments']) ? $case['get']['comments'] : null), $fetch);

		pg_assert_same($statements, array_map('comment_thread_rules_test_squash', $queries), $name . ': the statements');
		pg_assert_same((int) $case['count'], $read['count'], $name . ': count');
		pg_assert_same($featured[0], $read['has_featured'], $name . ': featured comments exist');
		pg_assert_same($featured[1], $read['featured_view'], $name . ': only the featured shown');
		pg_assert_same($case['rows'], $read['rows'], $name . ': the rows as read');

		if ($name === 'signed out') {
			$signed_out = $statements;
			pg_assert_contains(" AND ((comments.published = '1')) ORDER BY", $statements[2], 'signed out reads the published only');
		}

		if (($name === 'failed post') || ($name === 'scheduled, signed out')) {
			// A failed post's form state belongs to the block's liveform,
			// not to the rules: the thread reads as it did before the post.
			pg_assert_same($signed_out, $statements, $name . ': the same statements as signed out');
		}

		if ($name === 'member with a pending comment') {
			pg_assert_contains("AND (item_id = '12') AND (item_type = 'submitted_form') AND ((comments.published = '1') OR ((comments.id = '41')))", $statements[2], 'the member reads their own pending comment');
		}

		if (($name === 'staff') || ($name === 'form editor') || ($name === 'scheduled, staff')) {
			pg_assert_false(strpos($statements[2], "comments.published = '1'") !== false, $name . ': reads every comment');
		}

		if ($name === 'search index') {
			pg_assert_contains("AND (item_type = 'submitted_form') AND ((comments.published = '1')) ORDER BY", $statements[2], 'the search index reads the published only');
		}

		if ($featured[1]) {
			pg_assert_contains("AND (comments.featured = '1') ORDER BY", $statements[2], $name . ': the rows of the featured only');
			pg_assert_same(1, substr_count($statements[1], "featured = '1'"), $name . ': the featured count filters once');
		}
	}

	// The rows statement is the third whatever the counts read; a failed
	// count (no row) reads 0, as the block's string '0' did.
	$queries = array();
	$read = pg_comments_rows(5, null, pg_comments_viewer_decision(false, null, false, 0, null), array(), comment_thread_rules_test_fetch(array(array(), array(array('COUNT(*)' => null))), $queries));
	pg_assert_same(array('count' => 0, 'has_featured' => false, 'featured_view' => false, 'rows' => array()), $read, 'no counts, no rows');
	pg_assert_same(3, count($queries), 'three statements');

	// Rows keyed otherwise come back as a list.
	$queries = array();
	$read = pg_comments_rows(5, null, array('staff' => true), array('featured' => 'none'), comment_thread_rules_test_fetch(array(array(array('COUNT(*)' => '1')), array(array('COUNT(*)' => '1')), array(31 => $published)), $queries));
	pg_assert_same(array($published), $read['rows'], 'rows as a list');
	pg_assert_true($read['has_featured'], 'featured exist');
	pg_assert_false($read['featured_view'], 'mode none never filters');
	pg_assert_false(strpos($queries[2], "AND (comments.featured = '1')") !== false, 'no filter in mode none');
}

// The default SELECT list is the block's, without parent_id; the widget asks
// for parent_id, which reads 0 until 11.0.31 has run.
function test_comment_thread_rules_rows_columns()
{
	comment_thread_rules_test_db_stub();

	$viewer = pg_comments_viewer_decision(false, null, false, 0, null);
	$legacy = comment_thread_rules_test_squash(comment_thread_rules_test_legacy_statements('5', comment_thread_rules_test_legacy_where(false, '', '0', false, false, false, null), false)[2]);

	$queries = array();
	pg_comments_rows('5', null, $viewer, array(), comment_thread_rules_test_fetch(array(), $queries));
	pg_assert_same($legacy, comment_thread_rules_test_squash($queries[2]), 'the default statement');
	pg_assert_false(strpos($queries[2], 'parent_id') !== false, 'no parent_id by default');

	$queries = array();
	pg_comments_rows('5', null, $viewer, array('parent_id' => false), comment_thread_rules_test_fetch(array(), $queries));
	pg_assert_same($legacy, comment_thread_rules_test_squash($queries[2]), 'parent_id off');

	$queries = array();
	pg_comments_rows('5', null, $viewer, array('parent_id' => true), comment_thread_rules_test_fetch(array(), $queries));
	pg_assert_false(pg_comment_replies_ready(), 'no column without a database');
	pg_assert_same(
		str_replace('comments.created_timestamp FROM comments', 'comments.created_timestamp, 0 AS parent_id FROM comments', $legacy),
		comment_thread_rules_test_squash($queries[2]),
		'parent_id asked for before 11.0.31');
}

// The widget also asks which thread each row belongs to (a page-level read
// holds every comment on the page): item_type and item_id come after
// created_timestamp, and after parent_id when both are asked. The default
// statement stays the block's.
function test_comment_thread_rules_rows_thread_key()
{
	comment_thread_rules_test_db_stub();

	$viewer = pg_comments_viewer_decision(false, null, false, 0, null);
	$legacy = comment_thread_rules_test_squash(comment_thread_rules_test_legacy_statements('5', comment_thread_rules_test_legacy_where(false, '', '0', false, false, false, null), false)[2]);

	$queries = array();
	pg_comments_rows('5', null, $viewer, array('thread_key' => true), comment_thread_rules_test_fetch(array(), $queries));
	pg_assert_same(
		str_replace('comments.created_timestamp FROM comments', 'comments.created_timestamp, comments.item_type, comments.item_id FROM comments', $legacy),
		comment_thread_rules_test_squash($queries[2]),
		'the thread key after the time');

	$queries = array();
	pg_comments_rows('5', null, $viewer, array('parent_id' => true, 'thread_key' => true), comment_thread_rules_test_fetch(array(), $queries));
	pg_assert_same(
		str_replace('comments.created_timestamp FROM comments', 'comments.created_timestamp, 0 AS parent_id, comments.item_type, comments.item_id FROM comments', $legacy),
		comment_thread_rules_test_squash($queries[2]),
		'after parent_id when both are asked');

	$queries = array();
	pg_comments_rows('5', null, $viewer, array('thread_key' => false), comment_thread_rules_test_fetch(array(), $queries));
	pg_assert_same($legacy, comment_thread_rules_test_squash($queries[2]), 'thread_key off');
	pg_assert_same(2, substr_count($queries[0] . $queries[1], 'COUNT(*)'), 'the counts are not touched');
	pg_assert_false(strpos($queries[0] . $queries[1], 'item_type') !== false, 'nor do they read the key');
}

// -- thread order ------------------------------------------------------------

function comment_thread_rules_test_rows()
{
	return array(
		array('id' => '1', 'parent_id' => '0', 'name' => 'a'),
		array('id' => '2', 'parent_id' => '1', 'name' => 'b'),
		array('id' => '3', 'parent_id' => '0', 'name' => 'c'),
		array('id' => '4', 'parent_id' => '2', 'name' => 'd'),
		array('id' => '5', 'parent_id' => '4', 'name' => 'e'),
		array('id' => '6', 'parent_id' => '99', 'name' => 'f'),
		array('id' => '7', 'parent_id' => '1', 'name' => 'g'),
	);
}

// Flat: the rows in the order read, each with the parent it answers, its
// top-level comment and how many replies it has.
function test_comment_thread_rules_order_flat()
{
	$rows = comment_thread_rules_test_rows();
	$flat = pg_comments_thread_order($rows, 'flat', 2);

	pg_assert_same(array(1, 2, 3, 4, 5, 6, 7), comment_thread_rules_test_column($flat, 'id'), 'order read');
	pg_assert_same(array(0, 1, 0, 2, 4, 0, 1), comment_thread_rules_test_column($flat, 'parent_id'), 'parents');
	pg_assert_same(array(0, 0, 0, 0, 0, 0, 0), comment_thread_rules_test_column($flat, 'depth'), 'no depth');
	pg_assert_same(array(1, 1, 3, 1, 1, 6, 1), comment_thread_rules_test_column($flat, 'root_id'), 'top-level comment');
	pg_assert_same(array(2, 1, 0, 1, 0, 0, 0), comment_thread_rules_test_column($flat, 'replies'), 'replies');
	pg_assert_same($rows, comment_thread_rules_test_column($flat, 'row'), 'the rows untouched');
	pg_assert_same(array('row', 'id', 'parent_id', 'depth', 'root_id', 'replies'), array_keys($flat[0]), 'entry keys');
}

// Threaded: depth-first under each top-level comment, replies in the order
// read, the depth capped at max_depth (1-3) while each reply keeps its parent.
function test_comment_thread_rules_order_threaded()
{
	$rows = comment_thread_rules_test_rows();
	$threaded = pg_comments_thread_order($rows, 'threaded', 2);

	pg_assert_same(array(1, 2, 4, 5, 7, 3, 6), comment_thread_rules_test_column($threaded, 'id'), 'depth-first');
	pg_assert_same(array(0, 1, 2, 2, 1, 0, 0), comment_thread_rules_test_column($threaded, 'depth'), 'capped at 2');
	pg_assert_same(array(1, 1, 1, 1, 1, 3, 6), comment_thread_rules_test_column($threaded, 'root_id'), 'top-level comment');
	pg_assert_same(array(2, 1, 1, 0, 0, 0, 0), comment_thread_rules_test_column($threaded, 'replies'), 'replies');
	pg_assert_same(array(0, 1, 2, 4, 1, 0, 0), comment_thread_rules_test_column($threaded, 'parent_id'), 'parents');
	pg_assert_same($rows[4], $threaded[3]['row'], 'the row goes with its entry');

	$one = pg_comments_thread_order($rows, 'threaded', 1);
	pg_assert_same(array(0, 1, 1, 1, 1, 0, 0), comment_thread_rules_test_column($one, 'depth'), 'capped at 1');
	pg_assert_same(array(0, 1, 2, 4, 1, 0, 0), comment_thread_rules_test_column($one, 'parent_id'), 'a capped reply keeps its parent');

	foreach (array(0, 'x', null, -1, true, array(2)) as $max_depth) {
		pg_assert_same($one, pg_comments_thread_order($rows, 'threaded', $max_depth), 'clamps to 1: ' . var_export($max_depth, true));
	}

	pg_assert_same(array(0, 1, 2, 3, 1, 0, 0), comment_thread_rules_test_column(pg_comments_thread_order($rows, 'threaded', 5), 'depth'), 'clamps to 3');
	pg_assert_same(array(0, 1, 2, 3, 1, 0, 0), comment_thread_rules_test_column(pg_comments_thread_order($rows, 'threaded', '3'), 'depth'), 'a numeric string');

	foreach (array('tree', '', null, 'THREADED', array('threaded')) as $layout) {
		pg_assert_same(pg_comments_thread_order($rows, 'flat', 2), pg_comments_thread_order($rows, $layout, 2), 'an unknown layout is flat: ' . var_export($layout, true));
	}
}

// A parent that is not among the rows (deleted, someone else's pending one,
// cut by the featured filter) or the comment itself makes a top-level
// comment; a loop ends; duplicate ids answer to the first; the input shape is
// forgiven.
function test_comment_thread_rules_order_orphans_loops()
{
	$orphans = pg_comments_thread_order(array(
		array('id' => 5, 'parent_id' => 4),
		array('id' => 3, 'parent_id' => 3),
		array('id' => 8, 'parent_id' => '12abc'),
		array('id' => 9, 'parent_id' => -1),
	), 'threaded', 3);
	pg_assert_same(array(5, 3, 8, 9), comment_thread_rules_test_column($orphans, 'id'), 'roots in the order read');
	pg_assert_same(array(0, 0, 0, 0), comment_thread_rules_test_column($orphans, 'parent_id'), 'no parent');
	pg_assert_same(array(0, 0, 0, 0), comment_thread_rules_test_column($orphans, 'depth'), 'top level');
	pg_assert_same(array(5, 3, 8, 9), comment_thread_rules_test_column($orphans, 'root_id'), 'their own top-level comment');

	$loop = array(
		array('id' => 1, 'parent_id' => 2),
		array('id' => 2, 'parent_id' => 1),
		array('id' => 4, 'parent_id' => 2),
	);

	foreach (array('threaded', 'flat') as $layout) {
		$entries = pg_comments_thread_order($loop, $layout, 3);
		$ids = comment_thread_rules_test_column($entries, 'id');
		sort($ids);
		pg_assert_same(array(1, 2, 4), $ids, $layout . ': each row once');
	}

	$threaded = pg_comments_thread_order($loop, 'threaded', 3);
	pg_assert_same(array(1, 2, 4), comment_thread_rules_test_column($threaded, 'id'), 'row 1 becomes the top-level comment');
	pg_assert_same(array(0, 1, 2), comment_thread_rules_test_column($threaded, 'parent_id'), 'the loop is cut at row 1');
	pg_assert_same(array(0, 1, 2), comment_thread_rules_test_column($threaded, 'depth'), 'depths');
	pg_assert_same(array(1, 1, 1), comment_thread_rules_test_column($threaded, 'root_id'), 'one thread');

	$long = pg_comments_thread_order(array(
		array('id' => 1, 'parent_id' => 3),
		array('id' => 2, 'parent_id' => 1),
		array('id' => 3, 'parent_id' => 2),
		array('id' => 4, 'parent_id' => 0),
	), 'threaded', 3);
	pg_assert_same(array(1, 2, 3, 4), comment_thread_rules_test_column($long, 'id'), 'a longer loop ends');

	$duplicates = pg_comments_thread_order(array(
		array('id' => '1', 'parent_id' => '0'),
		array('id' => '1', 'parent_id' => '0'),
		array('id' => '2', 'parent_id' => '1'),
	), 'threaded', 3);
	pg_assert_same(array(1, 2, 1), comment_thread_rules_test_column($duplicates, 'id'), 'a reply answers the first of two ids');
	pg_assert_same(array(1, 0, 0), comment_thread_rules_test_column($duplicates, 'replies'), 'the first has the reply');

	$classic = array(array('id' => '31', 'name' => 'a'), array('id' => '32', 'name' => 'b'), array('id' => '30', 'name' => 'c'));
	foreach (array('threaded', 'flat') as $layout) {
		$entries = pg_comments_thread_order($classic, $layout, 3);
		pg_assert_same(array(31, 32, 30), comment_thread_rules_test_column($entries, 'id'), $layout . ': rows without parent_id stay in order');
		pg_assert_same(array(0, 0, 0), comment_thread_rules_test_column($entries, 'parent_id'), $layout . ': all top-level');
	}

	pg_assert_same(array(), pg_comments_thread_order(null, 'threaded', 2), 'not a list');
	pg_assert_same(array(), pg_comments_thread_order('x', 'flat', 2), 'a string');
	pg_assert_same(array(), pg_comments_thread_order(array(), 'threaded', 2), 'no rows');

	$mixed = pg_comments_thread_order(array('x', array('id' => '7', 'parent_id' => '0'), null, array('id' => '8', 'parent_id' => '7')), 'threaded', 2);
	pg_assert_same(array(7, 8), comment_thread_rules_test_column($mixed, 'id'), 'entries that are not rows are skipped');
	pg_assert_same(array(0, 1), comment_thread_rules_test_column($mixed, 'depth'), 'and do not shift the links');

	$no_id = pg_comments_thread_order(array(array('name' => 'x', 'parent_id' => '0'), array('id' => '2', 'parent_id' => '0')), 'threaded', 2);
	pg_assert_same(array(0, 2), comment_thread_rules_test_column($no_id, 'id'), 'a row without an id reads 0');
}

// -- wiring ------------------------------------------------------------------

// The classic block asks the shared rules once each, in the order it needs
// them, keeps its HTML and keeps the rows in $comments.
function test_comment_thread_rules_page_wiring()
{
	$page = comment_thread_rules_test_source('get_page_content.php');

	foreach (array('pg_comments_settings(', 'pg_comments_viewer(', 'pg_comments_rows(', 'pg_comments_allow_new(', 'pg_comments_thread_sql(') as $call) {
		pg_assert_same(1, substr_count($page, $call), $call . ' once');
	}

	pg_assert_same(0, substr_count($page, 'pg_comments_thread_order('), 'the block keeps the order read');

	foreach (array('FROM allow_new_comments_for_items', '$sql_added_comments', 'SELECT COUNT(*) FROM comments WHERE comments.page_id') as $gone) {
		pg_assert_same(0, substr_count($page, $gone), 'gone: ' . $gone);
	}

	pg_assert_same(1, substr_count($page, '$comments = $comments_read[\'rows\'];'), 'the rows stay in $comments');
	pg_assert_same(1, substr_count($page, '$form_editor = $comments_viewer[\'form_editor\'];'), 'the block keeps $form_editor');
	pg_assert_same(2, substr_count($page, '$comments_rating != false'), 'the stars follow the drawn page');
	pg_assert_same(1, substr_count($page, "\$system_region_properties['comments'] == '1'"), 'only the product markup reads the switch itself');

	$order = array(
		'pg_comments_settings(',
		'pg_sw_record_comment_context($system_region_properties',
		'pg_comments_viewer(',
		'pg_comments_rows(',
		"\$output_comments = '';",
		'pg_comments_allow_new(',
		"\$output_allow_new_comments_form = '';",
	);
	$previous = -1;

	foreach ($order as $needle) {
		$at = strpos($page, $needle);
		pg_assert_true(($at !== false) && ($at > $previous), 'in order: ' . $needle);
		$previous = ($at === false) ? $previous : $at;
	}
}

// add_comment.php asks the same allow-new rule after the comments-on gate and
// before it refuses a closed thread.
function test_comment_thread_rules_add_comment_wiring()
{
	$add = comment_thread_rules_test_source('add_comment.php');

	pg_assert_same(1, substr_count($add, 'pg_comments_allow_new('), 'asked once');
	pg_assert_same(0, substr_count($add, 'FROM allow_new_comments_for_items'), 'no copy of its own');

	$gate = strpos($add, 'if ($comments == 0) {');
	$ask = strpos($add, 'pg_comments_allow_new(');
	$refuse = strpos($add, 'if ($allow_new_comments == FALSE) {');
	pg_assert_true(($gate !== false) && ($ask !== false) && ($refuse !== false) && ($gate < $ask) && ($ask < $refuse), 'between the gates');
}
