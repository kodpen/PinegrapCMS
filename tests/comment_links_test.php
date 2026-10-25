<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the links that lead to one comment (includes/fn/comments.php):
 * the query a mail or panel link to a comment ends with
 * (pg_comment_view_query()) and the address add_comment.php sends the writer
 * back to after a post (pg_comment_return_url()). Both name the comment with
 * comment=<id>, so a comments widget opens the page that holds it; the
 * classic comments block ignores the parameter. Then the places that build
 * those links: add_comment.php, the comment e-mails in mail.php and the
 * "Go To Comment" links of the panel.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comment_links_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The query of a link to one comment: what the page needs to show its item,
// then every comment (the featured filter is left) and the comment itself.
function test_comment_links_view_query()
{
	$rows = array(
		array('', 5, '?comments=all&comment=5'),
		array('?r=ab12', 5, '?r=ab12&comments=all&comment=5'),
		array('?id=3', '7', '?id=3&comments=all&comment=7'),
		array('/blue-shirt', 9, '/blue-shirt?comments=all&comment=9'),
		array(array('?r=x'), 5, '?comments=all&comment=5'),
		array(null, 5, '?comments=all&comment=5'),
		array('', 'x', '?comments=all&comment=0'),
	);

	foreach ($rows as $row) {
		pg_assert_same($row[2], pg_comment_view_query($row[0], $row[1]), var_export($row[0], true) . ', ' . var_export($row[1], true));
	}
}

// The address a writer comes back to: the page they posted from, without the
// reply, the page number and an earlier comment, with the new comment named.
// While the thread has featured comments the view shows them all.
function test_comment_links_return_url()
{
	$rows = array(
		array('/topic?reply_to=3&comments_page=2&comment=9&r=ab', 12, false, '/topic?r=ab&comment=12'),
		array('/topic', 12, false, '/topic?comment=12'),
		array('/topic?comments=featured&x=1', 12, true, '/topic?x=1&comments=all&comment=12'),
		array('/topic?x=1', 12, true, '/topic?x=1&comments=all&comment=12'),
		array('/topic?comments=all&x=1', 12, false, '/topic?comments=all&x=1&comment=12'),
		array('/p?a[]=1&a[]=2', 4, false, '/p?a%5B0%5D=1&a%5B1%5D=2&comment=4'),
		array('/p?q=a+b&w=%C3%BC', 4, false, '/p?q=a+b&w=%C3%BC&comment=4'),
		array('/a/b.php?page=x', '4', false, '/a/b.php?page=x&comment=4'),
		array('?a=1', 4, false, '?a=1&comment=4'),
		array('http:///x', 4, false, 'http:///x?comment=4'),
		array('http:///x?y=1', 4, false, 'http:///x?y=1&comment=4'),
	);

	foreach ($rows as $row) {
		pg_assert_same($row[3], pg_comment_return_url($row[0], $row[1], $row[2]), $row[0] . ($row[2] ? ', featured' : ''));
	}
}

// add_comment.php sends the writer back through pg_comment_return_url() and
// mails the administrators a link that names the comment; the comment's
// three mail links still name the configured host.
function test_comment_links_add_comment_wiring()
{
	$source = comment_links_test_source('add_comment.php');

	pg_assert_contains('$query_string = pg_comment_view_query(get_query_string_for_page_url($page_type, $item_id, $item_type), $comment_id);', $source, 'the administrators\' link');
	pg_assert_contains("URL_SCHEME . HOSTNAME_SETTING . PATH . get_page_name(\$page_id) . \$query_string . '#c-' . \$comment_id", $source, 'still on the configured host');
	pg_assert_contains("header('Location: ' . URL_SCHEME . HOSTNAME . pg_comment_return_url(pg_safe_redirect_path(\$send_to), \$comment_id, (\$row[0] > 0)) . '#c-' . \$comment_id);", $source, 'the redirect after a post');
	pg_assert_same(0, substr_count($source, 'comments=all'), 'no address is put together by hand');
	pg_assert_same(3, substr_count($source, 'URL_SCHEME . HOSTNAME_SETTING . PATH'), 'add_comment.php mail links');
}

// The administrators (when a scheduled comment is published), the submitter
// and the watchers get a link that names the comment.
function test_comment_links_mail_wiring()
{
	$mail = comment_links_test_source('includes/fn/mail.php');

	pg_assert_same(3, substr_count($mail, 'pg_comment_view_query('), 'three comment mails');
	pg_assert_same(0, substr_count($mail, 'comments=all'), 'no address is put together by hand');
	pg_assert_contains("get_query_string_for_page_url(((\$comment['item_type'] == 'submitted_form') ? 'form item view' : \$page['type']), \$comment['item_id'], \$comment['item_type'])", $mail, 'a record\'s comment links with its ?r=');
	pg_assert_contains("pg_comment_view_query('?r=' . \$reference_code, \$comment_id) . '#c-' . \$comment_id", $mail, 'the submitter\'s link');
}

// The panel's "Go To Comment" links (the comment screen and the comment
// list, where the notification leads) name the comment, escaped.
function test_comment_links_panel_wiring()
{
	foreach (array('edit_comment.php', 'view_comments.php') as $file) {
		$source = comment_links_test_source($file);
		pg_assert_same(4, substr_count($source, 'href="\' . h(OUTPUT_PATH . get_page_name('), $file . ': four escaped links');
		pg_assert_same(4, substr_count($source, 'pg_comment_view_query('), $file . ': each names the comment');
		pg_assert_contains("pg_comment_view_query('?r=' . \$form_reference_code", $source, $file . ': a record\'s link keeps its ?r=');
	}
}
