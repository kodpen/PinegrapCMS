<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the comments widget in a comment e-mail (the submitter and
 * watcher e-mails of includes/fn/mail.php): which comment an e-mail page's
 * widget may draw and for which e-mail, the one comment it draws (links,
 * dates, stars, no forms), the check that it drew the comment with its
 * link before the fixed comment markup is left out, and the wiring in
 * mail.php, get_page_content.php and the renderer. An e-mail page that
 * places no comments widget is drawn exactly as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comments_widget_email_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// Whether the functions a test calls exist. A missing one is a failure, and
// the test stops there instead of ending the run.
function comments_widget_email_test_has($names)
{
	$ok = true;

	foreach ((array) $names as $name) {
		$ok = pg_assert_true(function_exists($name), $name . '() exists') && $ok;
	}

	return $ok;
}

// The source of a PHP function, '' when it does not exist.
function comments_widget_email_test_php_body($name)
{
	if (!function_exists($name)) {
		return '';
	}

	$fn = new ReflectionFunction($name);
	$lines = file($fn->getFileName());

	return implode('', array_slice($lines, $fn->getStartLine() - 1, $fn->getEndLine() - $fn->getStartLine() + 1));
}

// The thread page's row, as the renderer reads it.
function comments_widget_email_test_page($over = array())
{
	return $over + array(
		'page_id' => '5',
		'page_name' => 'topic',
		'page_folder' => '1',
		'page_type' => 'standard',
		'comments' => '1',
		'comments_label' => '',
		'comments_message' => 'Be kind.',
		'comments_rating' => '0',
		'comments_allow_new_comments' => '1',
		'comments_disallow_new_comment_message' => '',
		'comments_automatic_publish' => '1',
		'comments_allow_user_to_select_name' => '0',
		'comments_require_login_to_comment' => '0',
		'comments_allow_file_attachments' => '0',
		'comments_show_submitted_date_and_time' => '0',
		'comments_administrator_email_to_email_address' => '',
		'comments_administrator_email_conditional_administrators' => '0',
		'comments_submitter_email_page_id' => '8',
		'comments_watcher_email_page_id' => '9',
		'comments_watchers_managed_by_submitter' => '0',
	);
}

// One comment, as pg_comments_email_rows() reads it.
function comments_widget_email_test_comment($over = array())
{
	return $over + array(
		'id' => '12',
		'page_id' => '5',
		'item_type' => '',
		'item_id' => '0',
		'name' => 'Ann',
		'message' => 'Hello there',
		'file_name' => null,
		'file_size' => null,
		'published' => '1',
		'publish_date_and_time' => '0000-00-00 00:00:00',
		'publish_cancel' => '0',
		'featured' => '0',
		'rating' => '0',
		'verified_order_id' => '0',
		'seller_reply' => '',
		'seller_reply_at' => '0',
		'username' => 'ann',
		'user_role' => '3',
		'user_id' => '4',
		'user_badge' => '0',
		'user_badge_label' => '',
		'created_timestamp' => '1761300000',
		'parent_id' => '0',
	);
}

// -- which comment, for which e-mail -------------------------------------------

// Only a published comment of a page's own thread or of a form record, and
// only for the thread page's own comment e-mail page: the watcher e-mail
// page, or the submitter e-mail page for a record's comment.
function test_comments_widget_email_decision_table()
{
	if (!comments_widget_email_test_has('pg_comments_email_decision')) {
		return;
	}

	$page = comments_widget_email_test_page();
	$c = function ($over = array()) {
		return comments_widget_email_test_comment($over);
	};
	$record = array('item_type' => 'submitted_form', 'item_id' => '3');

	$rows = array(
		array('the page\'s own thread, the watcher page', $c(), $page, 9, true),
		array('the watcher page as digits', $c(), $page, '9', true),
		array('a record\'s comment, the submitter page', $c($record), $page, 8, true),
		array('a record\'s comment, the watcher page', $c($record), $page, 9, true),
		array('the page\'s own thread mailed through the submitter page alone', $c(), $page, 8, false),
		array('one page for both e-mails', $c(), comments_widget_email_test_page(array('comments_submitter_email_page_id' => '9')), 9, true),
		array('published given as a number', $c(array('published' => 1)), $page, 9, true),
		array('not published', $c(array('published' => '0')), $page, 9, false),
		array('not published, as a number', $c(array('published' => 0)), $page, 9, false),
		array('no published column', array_diff_key($c(), array('published' => 1)), $page, 9, false),
		array('a product review', $c(array('item_type' => 'product', 'item_id' => '3')), $page, 9, false),
		array('a product group\'s', $c(array('item_type' => 'product_group', 'item_id' => '3')), $page, 9, false),
		array('an event\'s', $c(array('item_type' => 'calendar_event', 'item_id' => '3')), $page, 9, false),
		array('an e-mail page that is neither', $c(), $page, 7, false),
		array('another page\'s row', $c(), comments_widget_email_test_page(array('page_id' => '6')), 9, false),
		array('a page row without an id', $c(array('page_id' => '0')), comments_widget_email_test_page(array('page_id' => '0')), 9, false),
		array('e-mail page 0', $c(), $page, 0, false),
		array('e-mail page with letters', $c(), $page, '9abc', false),
		array('e-mail page null', $c(), $page, null, false),
		array('e-mail page as a list', $c(), $page, array(9), false),
		array('a comment that is not a row', 'x', $page, 9, false),
		array('a page that is not a row', $c(), null, 9, false),
		array('a comment with id 0', $c(array('id' => '0')), $page, 9, false),
		array('a page without comment e-mail pages', $c(), comments_widget_email_test_page(array('comments_submitter_email_page_id' => '0', 'comments_watcher_email_page_id' => '0')), 9, false),
	);

	foreach ($rows as $row) {
		pg_assert_same($row[4], pg_comments_email_decision($row[1], $row[2], $row[3]), $row[0]);
	}
}

// The one comment as the flat layout draws it: depth 0, no replies, quoting
// the comment it answers when that one is given.
function test_comments_widget_email_entry()
{
	if (!comments_widget_email_test_has('pg_comments_email_entry')) {
		return;
	}

	$row = comments_widget_email_test_comment(array('parent_id' => '9'));
	$parent = comments_widget_email_test_comment(array('id' => '9', 'name' => 'Bob'));

	$entry = pg_comments_email_entry($row, $parent, 'first');
	pg_assert_same($row, $entry['row'], 'the row');
	pg_assert_same(12, $entry['id'], 'its id');
	pg_assert_same(9, $entry['parent_id'], 'the comment it answers');
	pg_assert_same(0, $entry['depth'], 'depth 0');
	pg_assert_same(12, $entry['root_id'], 'its own root');
	pg_assert_same(0, $entry['replies'], 'no replies drawn');
	pg_assert_true($entry['quoted'], 'it quotes the comment it answers');
	pg_assert_false($entry['featured'], 'not featured');

	$none = pg_comments_email_entry($row, null, 'first');
	pg_assert_same(0, $none['parent_id'], 'no parent given');
	pg_assert_false($none['quoted'], 'no quote');

	$self = pg_comments_email_entry($row, comments_widget_email_test_comment(), 'first');
	pg_assert_same(0, $self['parent_id'], 'a parent with the comment\'s own id is none');
	pg_assert_false($self['quoted'], 'and is not quoted');

	$featured = comments_widget_email_test_comment(array('featured' => '1'));
	pg_assert_true(pg_comments_email_entry($featured, null, 'first')['featured'], 'featured, first');
	pg_assert_true(pg_comments_email_entry($featured, null, 'filter')['featured'], 'featured, filter');
	pg_assert_false(pg_comments_email_entry($featured, null, 'none')['featured'], 'featured, none ignores the mark');
	pg_assert_false(pg_comments_email_entry($row, null, 'first')['featured'], 'not featured');

	pg_assert_same(0, pg_comments_email_entry('x', null, 'first')['id'], 'not a row');
}

// The links of the comment, absolute, as the fixed e-mail's "View or Reply"
// link builds them.
function test_comments_widget_email_link_values()
{
	if (!comments_widget_email_test_has(array('pg_comments_email_link_values', 'pg_comments_email_entry'))) {
		return;
	}

	$base = 'https://example.com/topic';
	$row = comments_widget_email_test_comment(array('parent_id' => '9'));
	$parent = comments_widget_email_test_comment(array('id' => '9', 'name' => 'Bob', 'message' => "The first\n\nquestion"));
	$entry = pg_comments_email_entry($row, $parent, 'first');
	$alone = pg_comments_email_entry($row, null, 'first');

	$keys = array_keys(pg_comments_widget_link_values(array(), null, false, '', array(), false));
	$plain = pg_comments_email_link_values($base, '', $alone, null, false);
	pg_assert_same($keys, array_keys($plain), 'the link keys of a live page, in order');
	pg_assert_same('https://example.com/topic?comments=all&comment=12#c-12', $plain['__comment_url'], 'the permalink');
	pg_assert_same($base . pg_comment_view_query('', 12) . '#c-12', $plain['__comment_url'], 'built as the fixed e-mail builds it');
	pg_assert_same('', $plain['__comment_reply_url'], 'no reply link when replies are not taken there');
	pg_assert_same('https://example.com/topic?reply_to=12#software_add_comment', pg_comments_email_link_values($base, '', $alone, null, true)['__comment_reply_url'], 'the reply link');

	$record = pg_comments_email_link_values($base, '?r=abc', $entry, $parent, true);
	pg_assert_same('https://example.com/topic?r=abc&comments=all&comment=12#c-12', $record['__comment_url'], 'a record\'s permalink keeps its ?r=');
	pg_assert_same('https://example.com/topic?r=abc&reply_to=12#software_add_comment', $record['__comment_reply_url'], 'and so does its reply link');
	pg_assert_same('9', $record['__comment_parent_id'], 'the comment answered');
	pg_assert_same('Bob', $record['__comment_parent_name'], 'its name');
	pg_assert_same('https://example.com/topic?r=abc&comments=all&comment=9#c-9', $record['__comment_parent_url'], 'its permalink, absolute');
	pg_assert_same('The first question', $record['__comment_quote'], 'the quote');
	pg_assert_same('0', $record['__comment_depth'], 'depth');
	pg_assert_same('0', $record['__comment_reply_count'], 'replies');

	$nameless = pg_comments_email_link_values($base, '', $entry, comments_widget_email_test_comment(array('id' => '9', 'name' => '')), false);
	pg_assert_same('Anonymous', $nameless['__comment_parent_name'], 'a nameless parent reads Anonymous');

	foreach (array('__comment_parent_id', '__comment_parent_name', '__comment_parent_url', '__comment_quote') as $key) {
		pg_assert_same('', $plain[$key], 'no parent: ' . $key);
	}

	$unquoted = $entry;
	$unquoted['quoted'] = false;
	pg_assert_same('', pg_comments_email_link_values($base, '', $unquoted, $parent, false)['__comment_quote'], 'an entry that does not quote has no quote');
	pg_assert_same('', pg_comments_email_link_values($base, '', array(), null, true)['__comment_url'], 'no comment, no link');
}

// A reply link only when the thread page takes comments, takes new ones,
// replies are stored and the thread page draws the widget.
function test_comments_widget_email_reply_ok()
{
	if (!comments_widget_email_test_has('pg_comments_email_reply_ok')) {
		return;
	}

	for ($i = 0; $i < 16; $i++) {
		$args = array((bool) ($i & 1), (bool) ($i & 2), (bool) ($i & 4), (bool) ($i & 8));
		pg_assert_same($i === 15, pg_comments_email_reply_ok($args[0], $args[1], $args[2], $args[3]), 'case ' . json_encode($args));
	}

	pg_assert_false(pg_comments_email_reply_ok(1, '1', true, 0), 'a falsy fact is enough to say no');
}

// The thread's words in an e-mail: one comment, the thread page's label,
// intro and closed message; every visitor value empty.
function test_comments_widget_email_thread_values()
{
	if (!comments_widget_email_test_has('pg_comments_email_thread_values')) {
		return;
	}

	$values = pg_comments_email_thread_values(pg_comments_settings(comments_widget_email_test_page()));
	pg_assert_same(array('__comments_title', '__comments_count', '__comments_label', '__comments_label_plural', '__comments_intro',
		'__comments_closed_message', '__comments_show_all_url', '__comments_show_featured_url', '__login_url', '__comment_author_name',
		'__reply_to_name', '__watcher_count_text'), array_keys($values), 'the twelve thread tokens');
	pg_assert_same('1 Comment', $values['__comments_title'], 'the title');
	pg_assert_same('1', $values['__comments_count'], 'one comment');
	pg_assert_same('Comment', $values['__comments_label'], 'the label');
	pg_assert_same('Comments', $values['__comments_label_plural'], 'its plural');
	pg_assert_same('Be kind.', $values['__comments_intro'], 'the intro');
	pg_assert_same("We're sorry. New comments are no longer being accepted.", $values['__comments_closed_message'], 'the default closed message');

	foreach (array('__comments_show_all_url', '__comments_show_featured_url', '__login_url', '__comment_author_name', '__reply_to_name', '__watcher_count_text') as $key) {
		pg_assert_same('', $values[$key], $key . ' is empty');
	}

	$own = pg_comments_email_thread_values(pg_comments_settings(comments_widget_email_test_page(array('comments_label' => 'Answer', 'comments_disallow_new_comment_message' => 'Closed now.'))));
	pg_assert_same('1 Answer', $own['__comments_title'], 'the page\'s label');
	pg_assert_same('Answers', $own['__comments_label_plural'], 'and its plural');
	pg_assert_same('Closed now.', $own['__comments_closed_message'], 'the page\'s closed message');
}

// The thread flags of an e-mail: no visitor, no form, no state.
function test_comments_widget_email_thread_flags()
{
	$all = array(
		'email' => true, 'signed_in' => true, 'staff' => true, 'require_login' => true, 'replying' => true, 'is_watcher' => true,
		'manage_watchers' => true, 'featured_view' => true, 'has_featured' => true, 'pages' => 3, 'count' => 1,
		'rating_on' => true, 'files_on' => true, 'watching_on' => true, 'allow_new' => true, 'name_mode' => 'typed',
	);
	$true = array('is_email', 'has_comments', 'rating_on', 'files_on', 'watching_on');

	foreach (array('open' => $all, 'closed' => array('allow_new' => false) + $all) as $name => $facts) {
		$flags = pg_comments_widget_thread_flags($facts);
		pg_assert_same(23, count($flags), $name . ': 23 thread flags');
		$keys = array_keys($flags);
		pg_assert_same('is_email', end($keys), $name . ': is_email last');

		foreach ($flags as $flag => $value) {
			pg_assert_same(in_array($flag, $true, true), $value, $name . ': ' . $flag);
		}
	}

	$live = pg_comments_widget_thread_flags(array('email' => false) + $all);
	pg_assert_false($live['is_email'], 'not an e-mail');
	pg_assert_true($live['can_comment'] && $live['is_staff'] && $live['is_replying'], 'a live staff member keeps the form');
	pg_assert_false(pg_comments_widget_thread_flags(array())['is_email'], 'no facts: not an e-mail');
}

// Stars as characters: mail clients drop inline SVG.
function test_comments_widget_email_stars_text()
{
	if (!comments_widget_email_test_has('pg_comments_stars_text_html')) {
		return;
	}

	pg_assert_same('<span class="pg-comment-stars" role="img" aria-label="Rated 4 out of 5">&#9733;&#9733;&#9733;&#9733;&#9734;</span>', pg_comments_stars_text_html(4), 'four of five');
	pg_assert_same('<span class="pg-comment-stars" role="img" aria-label="Rated 1 out of 5">&#9733;&#9734;&#9734;&#9734;&#9734;</span>', pg_comments_stars_text_html('1'), 'one, as digits');
	pg_assert_same(5, substr_count(pg_comments_stars_text_html(5), '&#9733;'), 'five filled');

	foreach (array(0, 6, '4x', null, true, -1, '', array(4), 4.5) as $bad) {
		pg_assert_same('', pg_comments_stars_text_html($bad), 'no stars for ' . var_export($bad, true));
	}
}

// -- no forms in an e-mail -----------------------------------------------------

function comments_widget_email_test_node($type, $props = array(), $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

// Forms, fields, buttons, the "Replying to" box and the cancel control go;
// a field's label and a wrapper left empty go with them; links stay.
function test_comments_widget_email_drops_and_strip()
{
	if (!comments_widget_email_test_has(array('pg_comments_email_drops', 'pg_comments_email_strip'))) {
		return;
	}

	$n = 'comments_widget_email_test_node';
	$attrs = function ($name, $value) {
		return array(array('name' => $name, 'value' => $value));
	};
	$table = array(
		'form' => array($n('semantic', array('tag' => 'form'), array($n('semantic', array('tag' => 'div', 'text' => 'in form')))), true),
		'label' => array($n('semantic', array('tag' => 'label', 'text' => 'Name', '_attrs' => $attrs('for', 'f_name'))), false),
		'input' => array($n('semantic', array('tag' => 'input', 'id' => 'f_name')), true),
		'textarea' => array($n('semantic', array('tag' => 'textarea')), true),
		'select' => array($n('semantic', array('tag' => 'SELECT')), true),
		'button' => array($n('semantic', array('tag' => 'button', 'text' => 'Send')), true),
		'btn button' => array($n('component', array('componentType' => 'btn', 'btnElement' => 'button', 'text' => 'B')), true),
		'btn input' => array($n('component', array('componentType' => 'btn', 'btnElement' => 'input', 'text' => 'I')), true),
		'btn link' => array($n('component', array('componentType' => 'btn', 'btnElement' => 'a', 'text' => 'Read')), false),
		'btn default' => array($n('component', array('componentType' => 'btn', 'text' => 'Read')), false),
		'reply link' => array($n('content', array('contentType' => 'link', 'text' => 'Reply', '_bindings' => array('action' => 'comment_reply'))), false),
		'replying box' => array($n('semantic', array('tag' => 'div', '_bindings' => array('eo_visible_if' => 'is_replying')), array($n('semantic', array('tag' => 'span', 'text' => 'Replying to')))), true),
		'cancel link' => array($n('content', array('contentType' => 'link', 'text' => 'Cancel', '_bindings' => array('action' => 'comment_reply_cancel'))), true),
		'input wrapper' => array($n('semantic', array('tag' => 'div', 'cssClass' => 'mb-3'), array($n('semantic', array('tag' => 'input', 'id' => 'f_other')))), false),
		'form wrapper' => array($n('semantic', array('tag' => 'div', 'cssClass' => 'card'), array($n('semantic', array('tag' => 'form')))), false),
		'text' => array($n('semantic', array('tag' => 'p', 'text' => 'Unrelated')), false),
		'staff box' => array($n('semantic', array('tag' => 'div', '_bindings' => array('eo_visible_if' => 'is_staff'))), false),
	);

	$children = array();

	foreach ($table as $name => $row) {
		pg_assert_same($row[1], pg_comments_email_drops($row[0]), 'drops: ' . $name);
		$children[] = $row[0];
	}

	foreach (array(null, 'x', array(), array('type' => 'semantic')) as $bad) {
		pg_assert_false(pg_comments_email_drops($bad), 'not a node: ' . var_export($bad, true));
	}

	$tree = $n('root', array(), array($n('container', array(), $children)));
	pg_comments_email_strip($tree);
	$left = array();

	foreach ($tree['children'][0]['children'] as $child) {
		$left[] = isset($child['props']['text']) ? $child['props']['text'] : (isset($child['props']['customName']) ? $child['props']['customName'] : $child['type']);
	}

	pg_assert_same(array('Read', 'Read', 'Reply', 'Unrelated', 'semantic'), $left, 'only the links, the text and the staff box stay');

	$none = 'x';
	pg_comments_email_strip($none);
	pg_assert_same('x', $none, 'not a tree');
}

// -- the comment's own values ----------------------------------------------------

// Dates as the sender gives them (absolute), the file on the site's address,
// stars as text, nothing for staff.
function test_comments_widget_email_row_values()
{
	if (!comments_widget_email_test_has('pg_comments_email_row_values')) {
		return;
	}

	$site = 'https://example.com/';
	$row = comments_widget_email_test_comment(array('file_name' => 'my photo.jpg', 'file_size' => '2048', 'rating' => '4', 'seller_reply' => 'Thanks', 'seller_reply_at' => '1761300100'));
	$flags = array('comment_has_file' => true, 'comment_has_rating' => true, 'comment_has_seller_reply' => true);
	$dates = array('date' => '24 October 2026 10:00', 'seller_reply_date' => '24 October 2026 10:01');

	$values = pg_comments_email_row_values($row, $flags, $site, $dates);
	pg_assert_same('24 October 2026 10:00', $values['__comment_date'], 'the date as given');
	pg_assert_same('24 October 2026 10:01', $values['__comment_seller_reply_date'], 'the store\'s reply date as given');
	pg_assert_same('https://example.com/my%20photo.jpg', $values['__comment_file_url'], 'the file on the site\'s address');
	pg_assert_same(0, strpos($values['__comment_file_url'], $site), 'absolute');
	pg_assert_same(pg_comments_stars_text_html(4), $values['__comment_stars_html'], 'text stars');

	foreach (array('__comment_staff_name', '__comment_edit_url', '__comment_status') as $key) {
		pg_assert_same('', $values[$key], $key . ' is empty');
	}

	$plain = pg_comments_email_row_values($row, array(), $site, array());
	pg_assert_same('', $plain['__comment_file_url'], 'no file flag, no file');
	pg_assert_same('', $plain['__comment_stars_html'], 'no rating flag, no stars');
	pg_assert_same('', $plain['__comment_date'], 'no date given');
	pg_assert_same('', $plain['__comment_seller_reply_date'], 'no reply date given');
	pg_assert_same('', pg_comments_email_row_values($row, array('comment_has_file' => true), array('x'), array())['__comment_file_url'], 'no file link without the site\'s address');
	pg_assert_same('', pg_comments_email_row_values($row, array('comment_has_file' => true), '', array())['__comment_file_url'], 'nor with an empty one');
	pg_assert_same('', pg_comments_email_row_values(comments_widget_email_test_comment(), array('comment_has_file' => true), $site, array())['__comment_file_url'], 'no file name, no link');
}

// -- the draw --------------------------------------------------------------------

// What the renderer hands the draw, from a comment, the comment it answers,
// the thread page's row, the widget's options and the sender's facts.
function comments_widget_email_test_data($over = array(), $env = array())
{
	$comment = comments_widget_email_test_comment((isset($over['comment']) ? $over['comment'] : array()) + array(
		'item_type' => 'submitted_form', 'item_id' => '3', 'parent_id' => '9', 'name' => 'Ann ^^__comment_id^^', 'message' => 'hi ^^__comment_id^^ <b>',
	));
	$parent = array_key_exists('parent', $over) ? $over['parent'] : comments_widget_email_test_comment(array('id' => '9', 'name' => 'Bob', 'message' => 'The question'));
	$page = comments_widget_email_test_page(isset($over['page']) ? $over['page'] : array());

	return pg_comments_email_draw_data($comment, $parent, $page, isset($over['cfg']) ? $over['cfg'] : array(), $env + array(
		'widget_id' => 4,
		'allow_new' => true,
		'reply_ok' => true,
		'base' => 'https://example.com/topic',
		'query' => '?r=abc',
		'site' => 'https://example.com/',
		'dates' => array('date' => '24 October 2026 10:00', 'seller_reply_date' => ''),
		'badge_label' => '',
	));
}

// The data of the one row and the thread, as the renderer draws them.
function test_comments_widget_email_draw_data()
{
	if (!comments_widget_email_test_has('pg_comments_email_draw_data')) {
		return;
	}

	$data = comments_widget_email_test_data();
	pg_assert_same(4, $data['widget_id'], 'the widget');
	pg_assert_true($data['thread_flags']['is_email'], 'drawn for an e-mail');
	pg_assert_false($data['thread_flags']['can_comment'], 'no form');
	pg_assert_same(pg_comments_email_thread_values(pg_comments_settings(comments_widget_email_test_page())), $data['thread_values'], 'the thread values');
	pg_assert_same(1, count($data['rows']), 'one row');

	$row = $data['rows'][0];
	pg_assert_same(12, $row['id'], 'the comment');
	pg_assert_same(0, $row['depth'], 'depth 0');
	pg_assert_true($row['reply_ok'], 'it may be answered');
	pg_assert_same(date('Y-m-d H:i:s', 1761300000), $row['datetime'], 'the time for ^^__comment_datetime^^, always');
	pg_assert_true($row['flags']['comment_is_root'] && $row['flags']['comment_has_quote'], 'a root that quotes its parent');
	pg_assert_false($row['flags']['comment_can_edit'] || $row['flags']['comment_is_pending'] || $row['flags']['comment_is_own_pending'], 'nobody\'s staff or pending flags');
	pg_assert_same('Ann ^^__comment_id^^', $row['values']['__comment_name'], 'the name, raw (the draw escapes it)');
	pg_assert_same('24 October 2026 10:00', $row['values']['__comment_date'], 'the absolute date');
	pg_assert_same('https://example.com/topic?r=abc&comments=all&comment=12#c-12', $row['values']['__comment_url'], 'the permalink');
	pg_assert_same('https://example.com/topic?r=abc&reply_to=12#software_add_comment', $row['values']['__comment_reply_url'], 'the reply link');
	pg_assert_same('The question', $row['values']['__comment_quote'], 'the quote');
	pg_assert_same('', $row['values']['__comment_staff_name'] . $row['values']['__comment_edit_url'] . $row['values']['__comment_status'], 'nothing for staff');
	pg_assert_false($data['form']['can_comment'], 'the form is not drawn');
	pg_assert_true($data['controls']['can_reply'], 'the reply link is wired');
	pg_assert_false($data['controls']['can_watch'] || $data['controls']['is_staff'] || $data['controls']['replying'], 'no watch, staff or replying control');
	pg_assert_same(array(), $data['sections'], 'no server section');
	pg_assert_same(array(), $data['slots'], 'no form slot');
	pg_assert_false($data['edit_chips'], 'no edit chips');

	$closed = comments_widget_email_test_data(array(), array('reply_ok' => false, 'allow_new' => false));
	pg_assert_same('', $closed['rows'][0]['values']['__comment_reply_url'], 'no reply link when it may not be answered');
	pg_assert_false($closed['controls']['can_reply'], 'the reply control is dropped');

	$alone = comments_widget_email_test_data(array('parent' => null));
	pg_assert_false($alone['rows'][0]['flags']['comment_has_quote'], 'no parent, no quote');

	$undated = comments_widget_email_test_data(array('comment' => array('created_timestamp' => '0')));
	pg_assert_same('', $undated['rows'][0]['datetime'], 'no time without a timestamp');

	$featured = comments_widget_email_test_data(array('comment' => array('featured' => '1'), 'cfg' => array('featured' => 'none')));
	pg_assert_false($featured['rows'][0]['flags']['comment_is_featured'], 'featured none ignores the mark');
	$badge = comments_widget_email_test_data(array('comment' => array('user_badge' => '1')), array('badge_label' => 'Moderator'));
	pg_assert_same('Moderator', $badge['rows'][0]['values']['__comment_badge'], 'the site\'s badge label');
}

// An attachment and stars in an e-mail: the file on the site's absolute
// address in place of the page's relative one, the stars as characters.
// Drawn in a PHP process of its own: a live comment's values name the file
// on OUTPUT_PATH, which the tests after this one must not find defined.
function test_comments_widget_email_draw_data_file_and_stars()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcwemail');

	if ($file === false) {
		return;
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('PG_TEST_RUNNER', true);\n"
		. "define('OUTPUT_PATH', '/site/');\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. 'require ' . var_export(__FILE__, true) . ";\n"
		. "\$data = comments_widget_email_test_data(array('comment' => array('rating' => '4', 'file_name' => 'a b.pdf', 'file_size' => '2048'), 'page' => array('comments_rating' => '1', 'comments_allow_file_attachments' => '1')));\n"
		. "list(\$html) = comments_widget_email_test_drawn(pg_comments_default_tree(), \$data);\n"
		. "echo json_encode(array('values' => \$data['rows'][0]['values'], 'flags' => \$data['thread_flags'], 'html' => \$html));\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$res = json_decode(implode("\n", $out), true);

	pg_assert_same(0, $code, 'drawn: ' . implode("\n", array_slice($out, 0, 8)));
	if (!is_array($res)) {
		pg_assert_true(false, 'the process answers');
		return;
	}

	pg_assert_same('https://example.com/a%20b.pdf', $res['values']['__comment_file_url'], 'the file on the site\'s absolute address, not /site/');
	pg_assert_same('a b.pdf', $res['values']['__comment_file_name'], 'its name');
	pg_assert_same('2 KB', $res['values']['__comment_file_size'], 'its size');
	pg_assert_same(pg_comments_stars_text_html(4), $res['values']['__comment_stars_html'], 'text stars');
	pg_assert_true($res['flags']['rating_on'] && $res['flags']['files_on'], 'the thread page\'s features');
	pg_assert_contains('href="https://example.com/a%20b.pdf"', $res['html'], 'the attachment link');
	pg_assert_contains('&#9733;&#9733;&#9733;&#9733;&#9734;', $res['html'], 'the stars');
	pg_assert_false(strpos($res['html'], '<svg'), 'no drawing');
	pg_assert_false(strpos($res['html'], '/site/'), 'no relative link');
}

// Strips and draws a tree as the e-mail branch does, inside an e-mail's render
// context, with a form state in the session that must stay where it is.
function comments_widget_email_test_drawn($tree, $data)
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('software' => array('liveforms' => array('add_comment' => array(5 => array('message' => array('value' => 'kept'))))));
	$ctx = pg_sw_render_context(array('email' => true, 'page_id' => 9, 'comment_id' => 12));
	pg_comments_email_strip($tree);
	pg_comments_drop_mode_bindings($tree, 'thread');
	$html = pg_comments_widget_draw($tree, $data);
	pg_sw_render_context($ctx);
	$left = $_SESSION;

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}

	return array($html, $left);
}

// The default layout, drawn for an e-mail: the comment with its quote and
// absolute links, no form, no field, no button, no sign-in note.
function test_comments_widget_email_draw_end_to_end()
{
	if (!comments_widget_email_test_has(array('pg_comments_email_draw_data', 'pg_comments_email_strip', 'pg_comments_email_drew'))) {
		return;
	}

	$data = comments_widget_email_test_data();
	list($html, $session) = comments_widget_email_test_drawn(pg_comments_default_tree(), $data);

	foreach (array('<form', '<input', '<textarea', '<select', '<button', 'name="token"', 'Add a comment', 'Sign in', 'Replying to', 'data-pg-comment-replying') as $needle) {
		pg_assert_false(strpos($html, $needle), 'no ' . $needle);
	}

	pg_assert_same(1, substr_count($html, ' id="c-12"'), 'the comment once');
	pg_assert_contains('id="c-12" data-pg-comment-depth="0"', $html, 'at depth 0');
	pg_assert_contains('href="https://example.com/topic?r=abc&amp;reply_to=12#software_add_comment"', $html, 'the reply link, absolute');
	pg_assert_contains('href="https://example.com/topic?r=abc&amp;comments=all&amp;comment=12#c-12"', $html, 'the share link reaches the comment');
	pg_assert_contains('href="https://example.com/topic?r=abc&amp;comments=all&amp;comment=9#c-9"', $html, 'the quote links to the comment answered');
	pg_assert_contains('The question', $html, 'the quote');
	pg_assert_contains('>Bob<', $html, 'the name answered');
	pg_assert_contains('hi &#94;&#94;__comment_id&#94;&#94; &lt;b&gt;', $html, 'the message escaped once, carets as entities');
	pg_assert_contains('Ann &#94;&#94;__comment_id&#94;&#94;', $html, 'the name too');
	pg_assert_contains('24 October 2026 10:00', $html, 'the absolute date');
	pg_assert_contains('>1 Comment</h2>', $html, 'the heading');
	pg_assert_false(strpos($html, 'No comments yet.'), 'no empty note');
	pg_assert_false(strpos($html, "We're sorry"), 'no closed note');
	pg_assert_false(strpos($html, '^^'), 'no token left');
	pg_assert_true(pg_comments_email_drew($html, 12, $data['rows'][0]['values']['__comment_url']), 'the comment and its link are drawn');
	pg_assert_same('kept', $session['software']['liveforms']['add_comment'][5]['message']['value'], 'the sender\'s form state is left alone');

	$closed = comments_widget_email_test_data(array(), array('reply_ok' => false, 'allow_new' => false));
	list($closed_html) = comments_widget_email_test_drawn(pg_comments_default_tree(), $closed);
	pg_assert_false(strpos($closed_html, 'reply_to='), 'no reply link when it may not be answered');
	pg_assert_true(pg_comments_email_drew($closed_html, 12, $closed['rows'][0]['values']['__comment_url']), 'still drawn with its link');
}

// A node bound to a token only a list of recent comments fills
// (__comment_thread_title, __comment_thread_url, __comment_label) leaves an
// e-mail with what it holds, as it leaves the thread on the page, instead of
// printing an empty element.
function test_comments_widget_email_drops_list_tokens()
{
	if (!comments_widget_email_test_has(array('pg_comments_email_draw_data', 'pg_comments_email_strip', 'pg_comments_drop_mode_bindings'))) {
		return;
	}

	$data = comments_widget_email_test_data();
	$tree = comments_widget_email_test_card(array('type' => 'semantic', 'props' => array('tag' => 'span', 'class' => 'pg-test-list-only', '_bindings' => array('text' => '__comment_thread_title')), 'children' => array()));
	list($html) = comments_widget_email_test_drawn($tree, $data);
	pg_assert_false(strpos($html, 'pg-test-list-only'), 'the list token\'s node is left out');
	pg_assert_true(pg_comments_email_drew($html, 12, $data['rows'][0]['values']['__comment_url']), 'the comment and its link are drawn');
}

// The draw may drop the comment: a list shown only to a visitor, or a design
// without a link to the comment. The renderer then draws nothing and the
// fixed markup stays, so an e-mail never goes without the comment and its
// link.
function test_comments_widget_email_draw_without_the_comment()
{
	if (!comments_widget_email_test_has(array('pg_comments_email_draw_data', 'pg_comments_email_strip', 'pg_comments_email_drew'))) {
		return;
	}

	$data = comments_widget_email_test_data();
	$url = $data['rows'][0]['values']['__comment_url'];

	foreach (array('is_signed_in', 'is_signed_out', 'comments_open', 'can_comment', 'is_staff', 'featured_view', 'no_comments') as $flag) {
		$tree = pg_comments_default_tree();
		$tree['children'][0]['children'][1]['props']['_bindings'] = array('eo_visible_if' => $flag);
		list($html) = comments_widget_email_test_drawn($tree, $data);
		pg_assert_false(strpos($html, ' id="c-12"'), 'a list shown only when ' . $flag . ' has no comment in an e-mail');
		pg_assert_false(pg_comments_email_drew($html, 12, $url), 'not drawn: ' . $flag);
	}

	$tree = pg_comments_default_tree();
	$tree['children'][0]['children'][1]['props']['_bindings'] = array('eo_visible_if' => 'has_comments');
	list($html) = comments_widget_email_test_drawn($tree, $data);
	pg_assert_true(pg_comments_email_drew($html, 12, $url), 'a list shown with comments is drawn');

	// The comment's actions without the share link: no link to the comment.
	$no_link = pg_comments_default_tree();
	$card = &$no_link['children'][0]['children'][1]['children'][0]['children'][0]['children'][1]['children'][0]['children'];
	$actions = count($card) - 1;
	pg_assert_same('comment_share', $card[$actions]['children'][1]['props']['_bindings']['action'], 'the share link is where the starter has it');
	array_pop($card[$actions]['children']);
	unset($card);
	list($html) = comments_widget_email_test_drawn($no_link, $data);
	pg_assert_contains(' id="c-12"', $html, 'the comment is drawn');
	pg_assert_false(pg_comments_email_drew($html, 12, $url), 'but without its link it does not count as drawn');

	// A share control drawn as a button goes with the other buttons.
	$button = pg_comments_default_tree();
	$card = &$button['children'][0]['children'][1]['children'][0]['children'][0]['children'][1]['children'][0]['children'];
	$card[count($card) - 1]['children'][1] = array('type' => 'semantic', 'props' => array('tag' => 'button', 'text' => 'Share', '_bindings' => array('action' => 'comment_share')), 'children' => array());
	unset($card);
	list($html) = comments_widget_email_test_drawn($button, $data);
	pg_assert_false(strpos($html, '<button'), 'no button');
	pg_assert_false(pg_comments_email_drew($html, 12, $url), 'a share button is no link to the comment');
}

// The default tree with $node added at the end of the comment card's body,
// or with the card's Share link taken out first ($share false).
function comments_widget_email_test_card($node, $share = true)
{
	$tree = pg_comments_default_tree();
	$body = &$tree['children'][0]['children'][1]['children'][0]['children'][0]['children'][1]['children'][0]['children'];

	if (!$share) {
		array_pop($body[count($body) - 1]['children']);
	}

	if ($node !== null) {
		$body[] = $node;
	}

	unset($body);

	return $tree;
}

// The default tree with the comment list moved into a box with $props.
function comments_widget_email_test_list_in($props)
{
	$tree = pg_comments_default_tree();
	$tree['children'][0]['children'][1] = array('type' => 'semantic', 'props' => $props, 'children' => array($tree['children'][0]['children'][1]));

	return $tree;
}

// The canvas's e-mail state draws the widget only when the e-mail would
// draw the comment from it: _sdCommentsEmailDrawsComment() in
// style_designer.js answers what the server's draw (strip, draw, drew)
// answers, for the ways a design links to the comment and the boxes, flags,
// sections and controls that take a link or the list away. A flag a comment
// may lack (here comment_has_quote, on a comment that quotes none) never
// counts on the canvas, which cannot know the comment.
function test_comments_widget_email_draws_comment_twin()
{
	if (!comments_widget_email_test_has(array('pg_comments_email_drew', 'comments_widget_designer_test_node'))) {
		return;
	}

	$n = function ($type, $props, $children = array()) {
		return array('type' => $type, 'props' => $props, 'children' => $children);
	};
	$share = $n('semantic', array('tag' => 'a', 'text' => 'Link', '_bindings' => array('action' => 'comment_share')));
	$box = function ($bindings, $child = null) use ($n, $share) {
		return $n('semantic', array('tag' => 'div', '_bindings' => $bindings), array(($child === null) ? $share : $child));
	};
	$markup = '<a href="^^__comment_url^^">it</a>';
	$no_loop = pg_comments_default_tree();
	array_splice($no_loop['children'][0]['children'], 1, 1);
	$empty_loop = pg_comments_default_tree();
	$empty_loop['children'][0]['children'][1]['children'][0]['children'] = array();
	$loop_flag = pg_comments_default_tree();
	$loop_flag['children'][0]['children'][1]['children'][0]['props']['_bindings'] = array('eo_visible_if' => 'is_signed_in');

	$cases = array(
		'starter' => array(pg_comments_default_tree(), true),
		'no share link' => array(comments_widget_email_test_card(null, false), false),
		'share as a button' => array(comments_widget_email_test_card($n('semantic', array('tag' => 'button', 'text' => 'x', '_bindings' => array('action' => 'comment_share'))), false), false),
		'share as a button component' => array(comments_widget_email_test_card($n('component', array('componentType' => 'btn', 'btnElement' => 'button', '_bindings' => array('action' => 'comment_share'))), false), false),
		'share as a link component' => array(comments_widget_email_test_card($n('component', array('componentType' => 'btn', 'btnElement' => 'a', '_bindings' => array('action' => 'comment_share'))), false), true),
		'share as a link' => array(comments_widget_email_test_card($n('content', array('contentType' => 'link', 'text' => 'x', '_bindings' => array('action' => 'comment_share'))), false), true),
		'share on a div' => array(comments_widget_email_test_card($n('semantic', array('tag' => 'div', 'text' => 'x', '_bindings' => array('action' => 'comment_share'))), false), false),
		'<a> bound to the address' => array(comments_widget_email_test_card($n('semantic', array('tag' => 'a', 'text' => 'x', '_bindings' => array('href' => '__comment_url'))), false), true),
		'<a> bound with a format' => array(comments_widget_email_test_card($n('semantic', array('tag' => 'a', 'text' => 'x', '_bindings' => array('href' => '__comment_url'), '_bindFormats' => array('href' => 'Y'))), false), false),
		'link reading the address' => array(comments_widget_email_test_card($n('content', array('contentType' => 'link', 'text' => 'x', 'href' => '^^__comment_url^^')), false), true),
		'link reading it, bound elsewhere' => array(comments_widget_email_test_card($n('content', array('contentType' => 'link', 'text' => 'x', 'href' => '^^__comment_url^^', '_bindings' => array('href' => '__comment_parent_url'))), false), false),
		'button component bound to it' => array(comments_widget_email_test_card($n('component', array('componentType' => 'btn', 'text' => 'x', '_bindings' => array('href' => '__comment_url'))), false), true),
		'<a> with a space before it' => array(comments_widget_email_test_card($n('semantic', array('tag' => 'a', 'text' => 'x', 'href' => ' ^^__comment_url^^')), false), false),
		'div bound to it' => array(comments_widget_email_test_card($n('semantic', array('tag' => 'div', 'text' => 'x', '_bindings' => array('href' => '__comment_url'))), false), false),
		'picture with it' => array(comments_widget_email_test_card($n('content', array('contentType' => 'image', 'src' => 'a.png', 'href' => '^^__comment_url^^')), false), false),
		'custom HTML' => array(comments_widget_email_test_card($n('content', array('contentType' => 'custom_html', 'html' => $markup)), false), true),
		'custom HTML, single quotes' => array(comments_widget_email_test_card($n('content', array('contentType' => 'custom_html', 'html' => "<a href='^^__comment_url^^'>it</a>")), false), false),
		'heading words' => array(comments_widget_email_test_card($n('content', array('contentType' => 'heading', 'tag' => 'h3', 'text' => 'See ' . $markup)), false), true),
		'bound heading words' => array(comments_widget_email_test_card($n('content', array('contentType' => 'heading', 'tag' => 'h3', 'text' => $markup, '_bindings' => array('text' => '__comment_name'))), false), true),
		'semantic paragraph words' => array(comments_widget_email_test_card($n('semantic', array('tag' => 'p', 'text' => 'See ' . $markup)), false), false),
		'share under is_signed_in' => array(comments_widget_email_test_card($box(array('eo_visible_if' => 'is_signed_in')), false), false),
		'share under has_comments' => array(comments_widget_email_test_card($box(array('eo_visible_if' => 'has_comments')), false), true),
		'share under is_email' => array(comments_widget_email_test_card($box(array('eo_visible_if' => 'is_email')), false), true),
		'share under comment_is_root' => array(comments_widget_email_test_card($box(array('eo_visible_if' => 'comment_is_root')), false), true),
		'share under comment_is_reply' => array(comments_widget_email_test_card($box(array('eo_visible_if' => 'comment_is_reply')), false), false),
		'share under comment_has_rating' => array(comments_widget_email_test_card($box(array('eo_visible_if' => 'comment_has_rating')), false), false),
		'share under comment_has_quote' => array(comments_widget_email_test_card($box(array('eo_visible_if' => 'comment_has_quote')), false), false, true),
		'share under an unknown flag' => array(comments_widget_email_test_card($box(array('eo_visible_if' => 'whatever')), false), false),
		'share in a comments section' => array(comments_widget_email_test_card($box(array('section' => 'comment_pagination')), false), false),
		'share in another section' => array(comments_widget_email_test_card($box(array('section' => 'foo')), false), true),
		'share in the watch form' => array(comments_widget_email_test_card($box(array('action' => 'comment_watch_form')), false), false),
		'share in the staff form' => array(comments_widget_email_test_card($box(array('action' => 'comment_allow_new_form')), false), false),
		'share in a watch control' => array(comments_widget_email_test_card($box(array('action' => 'comment_unwatch')), false), false),
		'share in the reply control' => array(comments_widget_email_test_card($box(array('action' => 'comment_reply')), false), false),
		'share in a div posting comments' => array(comments_widget_email_test_card($box(array('action' => 'comment_form')), false), true),
		'share in a form' => array(comments_widget_email_test_card($n('semantic', array('tag' => 'form'), array($share)), false), false),
		'share in the replying box' => array(comments_widget_email_test_card($box(array('eo_visible_if' => 'is_replying')), false), false),
		'share in a box bound to a list token' => array(comments_widget_email_test_card($box(array('text' => '__comment_thread_title')), false), false),
		'link bound to the thread address' => array(comments_widget_email_test_card($n('semantic', array('tag' => 'a', 'text' => 'x', '_bindings' => array('href' => '__comment_thread_url'))), false), false),
		'list in a box bound to a list token: the default card' => array(comments_widget_email_test_list_in(array('tag' => 'div', '_bindings' => array('text' => '__comment_label'))), true),
		'list under is_signed_in' => array(comments_widget_email_test_list_in(array('tag' => 'div', '_bindings' => array('eo_visible_if' => 'is_signed_in'))), false),
		'list under has_comments' => array(comments_widget_email_test_list_in(array('tag' => 'div', '_bindings' => array('eo_visible_if' => 'has_comments'))), true),
		'list under comment_is_root' => array(comments_widget_email_test_list_in(array('tag' => 'div', '_bindings' => array('eo_visible_if' => 'comment_is_root'))), false),
		'list in a comments section' => array(comments_widget_email_test_list_in(array('tag' => 'div', '_bindings' => array('section' => 'comment_captcha'))), false),
		'list in another section' => array(comments_widget_email_test_list_in(array('tag' => 'div', '_bindings' => array('section' => 'foo'))), true),
		'list in a watch control' => array(comments_widget_email_test_list_in(array('tag' => 'div', '_bindings' => array('action' => 'comment_watch'))), false),
		'list in the share control' => array(comments_widget_email_test_list_in(array('tag' => 'div', '_bindings' => array('action' => 'comment_share'))), false),
		'list in the watch form' => array(comments_widget_email_test_list_in(array('tag' => 'div', '_bindings' => array('action' => 'comment_watch_form'))), true),
		'list in a form: the default card' => array(comments_widget_email_test_list_in(array('tag' => 'form')), true),
		'a flag on the loop area itself' => array($loop_flag, true),
		'no loop area: the default card' => array($no_loop, true),
		'an empty loop area: the default card' => array($empty_loop, true),
	);

	$data = comments_widget_email_test_data();
	$plain = comments_widget_email_test_data(array('parent' => null));
	$trees = array();

	foreach ($cases as $name => $case) {
		$trees[$name] = $case[0];
	}

	$res = comments_widget_designer_test_node(array('_sdCommentsModeOffBindings', '_sdCommentsEmailDrops', '_sdCommentsEmailDrawsComment'),
		'var trees = ' . json_encode($trees) . ";\nvar flags = " . json_encode($data['thread_flags']) . ";\nvar out = {};\n"
		. "Object.keys(trees).forEach(function (k) { out[k] = _sdCommentsEmailDrawsComment(trees[k], flags); });\n"
		. "process.stdout.write(JSON.stringify(out));\n");
	if ($res === null) {
		return;
	}

	foreach ($cases as $name => $case) {
		$use = empty($case[2]) ? $data : $plain;
		list($html) = comments_widget_email_test_drawn($case[0], $use);
		pg_assert_same($case[1], pg_comments_email_drew($html, 12, $use['rows'][0]['values']['__comment_url']), 'the server: ' . $name);
		pg_assert_same($case[1], isset($res[$name]) ? $res[$name] : null, 'the canvas: ' . $name);
	}

	list($quoted) = comments_widget_email_test_drawn($cases['share under comment_has_quote'][0], $data);
	pg_assert_true(pg_comments_email_drew($quoted, 12, $data['rows'][0]['values']['__comment_url']), 'a comment that quotes one does draw it: the flag depends on the comment');
}

// ^^__comment_datetime^^ in an e-mail: the comment's time in the site's
// zone, in the design's format; without one, or with the relative one, in
// the default display shape. "3 hours ago" is wrong once the e-mail is read.
function test_comments_widget_email_fill_datetime()
{
	if (!comments_widget_email_test_has('pg_comments_email_fill_datetime')) {
		return;
	}

	$at = '2025-10-24 05:00:00';
	$ts = strtotime($at);
	$default = h(date(pg_sw_default_date_format('date and time'), $ts));

	pg_assert_same('<span>' . $default . '</span>', pg_comments_email_fill_datetime('<span>^^__comment_datetime^^%%relative%%</span>', $at), 'the relative format gives the default shape');
	pg_assert_same('<span>' . $default . '</span>', pg_comments_email_fill_datetime('<span>^^__comment_datetime^^</span>', $at), 'no format, the default shape');
	pg_assert_same('<b>' . h(date('Y-m-d H:i T', $ts)) . '</b>', pg_comments_email_fill_datetime('<b>^^__comment_datetime^^%%Y-m-d H:i T%%</b>', $at), 'the design\'s format, in the site\'s zone');
	pg_assert_same('24.10.2025 &amp; 05:00', pg_comments_email_fill_datetime('^^__comment_datetime^^%%d.m.Y &amp; H:i%%', $at), 'a format the editor stored with entities, escaped once');
	pg_assert_same('<time datetime="' . h(date('c', $ts)) . '">' . $default . '</time>',
		pg_comments_email_fill_datetime('<time datetime="^^__comment_datetime^^%%c%%">^^__comment_datetime^^%%relative%%</time>', $at), 'every slot, an attribute too');
	pg_assert_same('^^__comment_name^^ ^^__comment_date^^ ^^__comment_datetimes^^', pg_comments_email_fill_datetime('^^__comment_name^^ ^^__comment_date^^ ^^__comment_datetimes^^', $at), 'other tokens stay');
	pg_assert_same('^^__comment_datetime^^%%relative%%', pg_comments_email_fill_datetime('^^__comment_datetime^^%%relative%%', ''), 'no time: left to the sweep');
	pg_assert_same('^^__comment_datetime^^', pg_comments_email_fill_datetime('^^__comment_datetime^^', 'not a time'), 'a time that does not read: left to the sweep');
	pg_assert_same(null, pg_comments_email_fill_datetime(null, $at), 'no markup');
	pg_assert_false(strpos(pg_comments_email_fill_datetime('^^__comment_datetime^^%%relative%%', date('Y-m-d H:i:s')), '<time'), 'no relative time tag');

	$draw = comments_widget_email_test_php_body('pg_comments_widget_draw');
	$email = strpos($draw, "if (!empty(\$comments_thread_flags['is_email']))");
	$fill = strpos($draw, 'pg_comments_email_fill_datetime(');
	$tokens = strpos($draw, 'pg_sw_apply_tokens(');
	pg_assert_true(($email !== false) && ($fill !== false) && ($tokens !== false) && ($email < $fill) && ($fill < $tokens), 'an e-mail\'s draw fills the time itself, a page\'s through the record date rules');
}

// The sender's request may be signed in with a zone of its own (a moderator
// publishing in edit_comment.php, a member posting a reply): the comment's
// time still reads in the site's zone, never as a relative time with the
// sender's zone in its tooltip. Drawn in a PHP process of its own, which
// defines who is signed in.
function test_comments_widget_email_datetime_ignores_the_sender()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcwemail');

	if ($file === false) {
		return;
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('PG_TEST_RUNNER', true);\n"
		. "define('USER_LOGGED_IN', true);\n"
		. "define('USER_TIMEZONE', 'Asia/Tokyo');\n"
		. "define('DATE_FORMAT', 'month_day');\n"
		. "define('TIME_FORMAT', 'twelve_hours');\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. 'require ' . var_export(__FILE__, true) . ";\n"
		. "date_default_timezone_set('America/Chicago');\n"
		. "\$span = function (\$format) { return array('type' => 'semantic', 'props' => array('tag' => 'span', 'cssClass' => 'when-' . strlen(\$format), 'text' => 'when', '_bindings' => array('text' => '__comment_datetime'), '_bindFormats' => array('text' => \$format)), 'children' => array()); };\n"
		. "\$tree = comments_widget_email_test_card(\$span('relative'));\n"
		. "\$body = &\$tree['children'][0]['children'][1]['children'][0]['children'][0]['children'][1]['children'][0]['children'];\n"
		. "\$body[] = \$span('M j Y g:i A T');\n"
		. "unset(\$body);\n"
		. "\$data = comments_widget_email_test_data();\n"
		. "list(\$html) = comments_widget_email_test_drawn(\$tree, \$data);\n"
		. "echo json_encode(array('html' => \$html, 'drew' => pg_comments_email_drew(\$html, 12, \$data['rows'][0]['values']['__comment_url']), 'default' => h(date(pg_sw_default_date_format('date and time'), 1761300000))));\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$res = json_decode(implode("\n", $out), true);

	pg_assert_same(0, $code, 'drawn: ' . implode("\n", array_slice($out, 0, 8)));
	if (!is_array($res)) {
		pg_assert_true(false, 'the process answers');
		return;
	}

	pg_assert_contains('<span class="when-8" data-pg-bind="text:comment_datetime">' . $res['default'] . '</span>', $res['html'], 'the relative format writes the time in the default shape');
	pg_assert_contains('<span class="when-13" data-pg-bind="text:comment_datetime">Oct 24 2025 5:00 AM CDT</span>', $res['html'], 'a format with the zone: the site\'s');
	pg_assert_false(strpos($res['html'], '<time'), 'no relative time tag');
	pg_assert_false(strpos($res['html'], 'JST'), 'nothing in the sender\'s zone');
	pg_assert_false(strpos($res['html'], '7:00 PM'), 'not the sender\'s hour');
	pg_assert_true($res['drew'], 'the comment is still drawn with its link');
}

// The check itself: the row's anchor and an escaped link to the permalink.
function test_comments_widget_email_drew()
{
	if (!comments_widget_email_test_has('pg_comments_email_drew')) {
		return;
	}

	$url = 'https://example.com/topic?comments=all&comment=12#c-12';
	$link = '<a href="https://example.com/topic?comments=all&amp;comment=12#c-12">View</a>';

	pg_assert_true(pg_comments_email_drew('<div id="c-12" data-pg-comment-depth="0">x ' . $link . '</div>', 12, $url), 'anchor and link');
	pg_assert_true(pg_comments_email_drew('<div id="c-12">x</div>' . $link, '12', $url), 'the id as digits');
	pg_assert_false(pg_comments_email_drew('<div id="c-123">x ' . $link . '</div>', 12, $url), 'another comment\'s anchor');
	pg_assert_false(pg_comments_email_drew('<div>x ' . $link . '</div>', 12, $url), 'no anchor');
	pg_assert_false(pg_comments_email_drew('<div id="c-12">x</div>', 12, $url), 'no link');
	pg_assert_false(pg_comments_email_drew('<div id="c-12"><a href="' . $url . '">x</a></div>', 12, $url), 'a link that was not escaped');
	pg_assert_false(pg_comments_email_drew('<div id="c-12">' . $link . '</div>', 0, $url), 'no comment id');
	pg_assert_false(pg_comments_email_drew('<div id="c-12">' . $link . '</div>', 12, ''), 'no address');
	pg_assert_false(pg_comments_email_drew(null, 12, $url), 'no markup');
	pg_assert_false(pg_comments_email_drew('', 12, $url), 'empty markup');
}

// -- the fixed markup ----------------------------------------------------------

// The fixed comment markup goes into the e-mail page's System region unless
// a comments widget drew the comment on that page in this pass: a record
// that does not name the page keeps it, whatever else it holds.
function test_comments_widget_email_keeps_fixed_markup_table()
{
	if (!comments_widget_email_test_has('pg_comments_email_keeps_fixed_markup')) {
		return;
	}

	$rows = array(
		array(true, 9, array(), true, 'an e-mail, nothing drawn'),
		array(true, 9, array(5 => true, 12 => true), true, 'other pages drawn'),
		array(true, '9', array(9 => true), false, 'the widget drew the comment on this e-mail page'),
		array(true, 9, array(9 => true), false, 'the same, the id as a number'),
		array(false, 9, array(9 => true), true, 'not an e-mail'),
		array(true, 0, array(0 => true), true, 'no page'),
		array(true, 9, null, true, 'no record'),
		array(true, 9, 'x', true, 'a record that is not a list'),
		array(1, 9, array(9 => true), false, 'a truthy e-mail flag'),
	);

	$found = pg_comments_widget_pages(array(9 => true));

	foreach ($rows as $row) {
		pg_assert_same($row[3], pg_comments_email_keeps_fixed_markup($row[0], $row[1], $row[2]), $row[4]);
	}

	pg_comments_widget_pages($found);
}

// An e-mail page that places no comments widget is drawn as before, byte for
// byte: mail.php makes the same get_page_content() call, which appends the
// same fixed markup, since only a comments widget's draw writes the record
// that leaves it out, and only the e-mail branch writes it in an e-mail.
function test_comments_widget_email_page_without_widget_unchanged()
{
	$mail = comments_widget_email_test_source('includes/fn/mail.php');

	foreach (array('send_comment_email_to_custom_form_submitter' => '$comments_submitter_email_page_id', 'send_comment_email_to_watchers' => '$comments_watcher_email_page_id') as $fn => $var) {
		$body = comments_widget_email_test_php_body($fn);
		$old = '$body = get_page_content(' . $var . ', $system_content = \'\', $extra_system_content, $mode = \'preview\', $email = true);';
		$check = 'if (pg_comments_page_has_widget(' . $var . ')) {';
		$new = '$body = get_page_content(' . $var . ', $system_content = \'\', $extra_system_content, $mode = \'preview\', $email = true, array(\'comment_id\' => (int) $comment_id));';
		pg_assert_same(1, substr_count($body, $old), $fn . ': the old call, unchanged');
		pg_assert_same(1, substr_count($body, $check), $fn . ': asked once whether the page places a comments widget');
		pg_assert_same(1, substr_count($body, $new), $fn . ': the comment named only for such a page');

		$at_check = strpos($body, $check);
		$at_new = strpos($body, $new);
		$at_else = strpos($body, '} else {', (int) $at_new);
		$at_old = strpos($body, $old);
		pg_assert_true(($at_check !== false) && ($at_new !== false) && ($at_else !== false) && ($at_old !== false)
			&& ($at_check < $at_new) && ($at_new < $at_else) && ($at_else < $at_old), $fn . ': the old call is the branch for any other page');
		pg_assert_same(1, substr_count($body, "\$extra_system_content = '<div class=\"comment\">"), $fn . ': the fixed markup is still built');
		pg_assert_true(strpos($body, "\$extra_system_content = '<div class=\"comment\">") < $at_check, $fn . ': before the page is drawn');
	}

	pg_assert_same(2, substr_count($mail, 'get_page_content($comments_submitter_email_page_id, '), 'the submitter e-mail: two calls, one runs');
	pg_assert_same(2, substr_count($mail, 'get_page_content($comments_watcher_email_page_id, '), 'the watcher e-mail: two calls, one runs');

	// Only a comments widget claims a page: the live renderer and the e-mail
	// branch are the only calls.
	$claims = array();
	$dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));

	foreach ($dir as $file) {
		if (substr((string) $file, -4) !== '.php') {
			continue;
		}

		$count = preg_match_all('/(?<!function )\bpg_comments_widget_claim\(\$/', (string) file_get_contents((string) $file));

		if ($count > 0) {
			$claims[str_replace('\\', '/', substr((string) $file, strlen(PG_FUNCTIONS_DIR) + 1))] = $count;
		}
	}

	pg_assert_same(array('includes/fn/widgets_comments.php' => 2), $claims, 'two calls, both in the comments widget');
	pg_assert_contains('pg_comments_widget_claim($page_id)', comments_widget_email_test_php_body('_render_system_widget_comments'), 'one in the live renderer');
	pg_assert_contains('pg_comments_widget_claim($email_page)', comments_widget_email_test_php_body('_pg_comments_widget_email'), 'one in the e-mail branch');

	if (function_exists('pg_comments_email_keeps_fixed_markup')) {
		$found = pg_comments_widget_pages(array());
		pg_assert_true(pg_comments_email_keeps_fixed_markup(true, 9, pg_comments_widget_pages()), 'a pass in which no comments widget drew keeps the fixed markup');
		pg_comments_widget_pages($found);
	}
}

// The submitter and watcher e-mail functions with the comments widget's
// branch folded back into the one call it stands beside. Returns the text
// and how many branches were folded.
function comments_widget_email_test_fold($body, $var)
{
	$pattern = '/\n([ \t]*)\/\/ An e-mail page that places a comments widget[^\n]*\n(?:[ \t]*\/\/[^\n]*\n)*'
		. '[ \t]*if \(pg_comments_page_has_widget\(' . preg_quote($var, '/') . '\)\) \{\n[^\n]*\n'
		. '[ \t]*\} else \{\n[ \t]*(\$body = get_page_content\(' . preg_quote($var, '/') . ', [^\n]*\);)\n[ \t]*\}\n/';
	$count = 0;
	$folded = preg_replace($pattern, "\n\$1\$2\n", $body, -1, $count);

	return array(is_string($folded) ? $folded : '', $count);
}

// Byte for byte: with the comments widget's branch folded back, each comment
// e-mail function is the text it had before an e-mail page could draw the
// comment in a comments widget (sha1 and length of that text). Everything an
// e-mail page without a comments widget gets - the queries, the recipients,
// the subject, the fixed comment markup and the get_page_content() call - is
// that code. A change to these functions on purpose updates the two values
// in the same commit.
function test_comments_widget_email_mail_functions_folded_back()
{
	$frozen = array(
		'send_comment_email_to_custom_form_submitter' => array('$comments_submitter_email_page_id', 'c8310a63128602a260e7e1327116fbed583dc38a', 5373),
		'send_comment_email_to_watchers' => array('$comments_watcher_email_page_id', 'fde9a34e813e7d8a892984a105256d345870ae3c', 6585),
	);

	foreach ($frozen as $fn => $want) {
		list($folded, $count) = comments_widget_email_test_fold(comments_widget_email_test_php_body($fn), $want[0]);
		pg_assert_same(1, $count, $fn . ': one widget branch, folded back');
		pg_assert_same($want[2], strlen($folded), $fn . ': the length of the function an e-mail page without the widget runs');
		pg_assert_same($want[1], sha1($folded), $fn . ': the function an e-mail page without the widget runs, byte for byte');
	}

	// The fold itself: a branch for another page variable, or a widget call
	// that names no comment, is not taken for the widget's branch.
	$sample = "x\n        // An e-mail page that places a comments widget is told.\n        if (pg_comments_page_has_widget(\$a)) {\n"
		. "            \$body = get_page_content(\$a, 'w');\n        } else {\n            \$body = get_page_content(\$a, 'o');\n        }\ny";
	pg_assert_same(array("x\n        \$body = get_page_content(\$a, 'o');\ny", 1), comments_widget_email_test_fold($sample, '$a'), 'the else branch takes the block\'s place');
	pg_assert_same(0, comments_widget_email_test_fold($sample, '$b')[1], 'another page variable: nothing folded');
}

// An e-mail render that names no comment - every e-mail but the submitter
// and watcher e-mails of a page that places the widget - prints nothing and
// claims nothing in a comments widget, as before, so its page keeps the
// fixed markup. No query runs on the way.
function test_comments_widget_email_render_without_a_comment()
{
	$tree = json_encode(pg_comments_default_tree());
	$contexts = array(
		'no comment named' => array('email' => true, 'page_id' => 9),
		'comment 0' => array('email' => true, 'page_id' => 9, 'comment_id' => 0),
		'comment with letters' => array('email' => true, 'page_id' => 9, 'comment_id' => '12abc'),
		'comment below 0' => array('email' => true, 'page_id' => 9, 'comment_id' => -12),
		'comment as a list' => array('email' => true, 'page_id' => 9, 'comment_id' => array(12)),
		'no e-mail page' => array('email' => true, 'page_id' => 0, 'comment_id' => 12),
	);

	foreach ($contexts as $name => $ctx) {
		$found = pg_comments_widget_pages(array());
		$prev = pg_sw_render_context($ctx);
		$html = _render_system_widget_comments($tree, 4, array('regionType' => 'comments'), 'preview');
		$record = pg_comments_widget_pages($found);
		pg_sw_render_context($prev);

		pg_assert_same('', $html, $name . ': nothing drawn');
		pg_assert_same(array(), $record, $name . ': nothing claimed');
		pg_assert_true(pg_comments_email_keeps_fixed_markup(true, 9, $record), $name . ': the fixed markup stays');
	}

	// In an e-mail the live renderer, which claims a page before it reads
	// its thread, is never reached.
	$renderer = comments_widget_email_test_php_body('_render_system_widget_comments');
	$email = strpos($renderer, "if (!empty(\$ctx['email'])) {");
	$claim = strpos($renderer, 'pg_comments_widget_claim($page_id)');
	pg_assert_true(($email !== false) && ($claim !== false) && ($email < $claim), 'the e-mail check comes before the live claim');

	// Only mail.php names a comment to an e-mail page.
	$named = array();
	$dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));

	foreach ($dir as $file) {
		if (substr((string) $file, -4) !== '.php') {
			continue;
		}

		$count = preg_match_all('/\bget_page_content\([^\n]*\'comment_id\'/', (string) file_get_contents((string) $file));

		if ($count > 0) {
			$named[str_replace('\\', '/', substr((string) $file, strlen(PG_FUNCTIONS_DIR) + 1))] = $count;
		}
	}

	pg_assert_same(array('includes/fn/mail.php' => 2), $named, 'the submitter and watcher e-mails, nothing else');
}

// -- wiring ------------------------------------------------------------------------

// The renderer hands an e-mail to its own branch, which reads no session,
// request or viewer, draws before it claims and claims only what it drew.
function test_comments_widget_email_renderer_wiring()
{
	$renderer = comments_widget_email_test_php_body('_render_system_widget_comments');
	$email_check = strpos($renderer, "if (!empty(\$ctx['email'])) {");
	pg_assert_true($email_check !== false, 'the renderer asks whether it draws an e-mail');
	if ($email_check !== false) {
		$after = ltrim(substr($renderer, $email_check + strlen("if (!empty(\$ctx['email'])) {")));
		pg_assert_same(0, strpos($after, 'return _pg_comments_widget_email($tree, (int) $widget_id, $cfg, $ctx);'), 'and hands it to the e-mail branch');
	}
	pg_assert_false(strpos($renderer, 'keep their fixed markup for now'), 'the e-mail no longer prints nothing');

	$branch = comments_widget_email_test_php_body('_pg_comments_widget_email');
	pg_assert_true($branch !== '', 'the e-mail branch');
	if ($branch === '') {
		return;
	}

	foreach (array('get_token_field(', 'liveform', '$_SESSION', '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', 'check_edit_access(', '_pg_acct_signed_in(', 'pg_comments_viewer(', 'USER_',
		'get_captcha_fields(', 'get_request_uri(', '_pg_sw_current_path(', '_pg_sw_query_params(', 'pg_comments_page_override(', '_pg_inject_messages_node(', 'remove_form(', 'HOSTNAME . ', 'OUTPUT_PATH') as $needle) {
		pg_assert_false(strpos($branch, $needle), 'the e-mail branch does not use ' . $needle);
	}

	pg_assert_contains("\$ctx['comment_id']", $branch, 'the comment comes from the render context');
	pg_assert_contains('URL_SCHEME . HOSTNAME_SETTING . PATH', $branch, 'links on the configured host');

	$order = array('pg_comments_email_rows(', 'pg_comments_email_decision(', 'pg_comments_email_draw_data(', 'pg_comments_email_strip(', "pg_comments_drop_mode_bindings(\$tree, 'thread');",
		'pg_comments_widget_draw(', 'pg_comments_email_drew(', 'pg_comments_widget_claim(');
	$last = -1;

	foreach ($order as $call) {
		$at = strpos($branch, $call);
		pg_assert_true(($at !== false) && ($at > $last), $call . ' in order');
		$last = ($at === false) ? $last : $at;
	}

	pg_assert_same(1, substr_count($branch, 'pg_comments_widget_claim('), 'one claim');
	pg_assert_same(1, substr_count($branch, 'pg_comments_widget_draw('), 'one draw');

	$data = comments_widget_email_test_php_body('pg_comments_email_draw_data');
	pg_assert_contains("_pg_comments_row_text(\$comment, 'created_timestamp')", $data, 'the time is the comment\'s');
	pg_assert_contains("date('Y-m-d H:i:s', \$created)", $data, 'and fills ^^__comment_datetime^^');
	pg_assert_contains("\$env['badge_label']", $data, 'the badge label comes from the renderer');
	pg_assert_contains("defined('BADGE_LABEL')", $branch, 'which reads the site\'s');
	pg_assert_contains("'timezone_type' => 'site'", $branch, 'absolute dates in the site\'s time zone, as the fixed markup');
	pg_assert_contains("'format' => 'plain_text'", $branch, 'as plain text: the token escape would print a <time> tag as words');
	pg_assert_same(1, substr_count($branch, 'get_absolute_time('), 'one date writer for both dates');
}

// get_page_content.php leaves the fixed markup out only when the widget drew
// the comment on this e-mail page in this pass.
function test_comments_widget_email_extra_content_wiring()
{
	$page = comments_widget_email_test_source('get_page_content.php');

	pg_assert_same(1, substr_count($page, '$system_output .= $extra_system_content;'), 'appended in one place');
	pg_assert_same(1, substr_count($page, "if ((\$extra_system_content != '') && (\$primary_system_region == TRUE) && pg_comments_email_keeps_fixed_markup(\$email, \$page_id, \$pg_cw_pages)) {"), 'only while no widget drew the comment on this e-mail page');

	$guard = strpos($page, 'pg_comments_email_keeps_fixed_markup($email, $page_id, $pg_cw_pages)');
	$append = strpos($page, '$system_output .= $extra_system_content;');
	$pass = strpos($page, 'pg_comments_widget_pages($pg_cw_prev)');
	pg_assert_true(($guard !== false) && ($append !== false) && ($guard < $append), 'the guard opens the append');
	pg_assert_true(($pass !== false) && ($pass < $guard), 'after the widget pass handed back its record');
}

// mail.php names the comment to an e-mail page that places a comments
// widget; the administrators' plain text e-mail has no page.
function test_comments_widget_email_mail_wiring()
{
	$mail = comments_widget_email_test_source('includes/fn/mail.php');

	pg_assert_same(2, substr_count($mail, "array('comment_id' => (int) \$comment_id)"), 'the submitter and watcher e-mails name the comment');
	pg_assert_same(2, substr_count($mail, "\$extra_system_content = '<div class=\"comment\">"), 'both still build the fixed markup');
	pg_assert_contains("pg_comment_view_query('?r=' . \$reference_code, \$comment_id) . '#c-' . \$comment_id", $mail, 'the submitter\'s View or Reply link, unchanged');
	pg_assert_contains("URL_SCHEME . HOSTNAME_SETTING . PATH . \$page_name . \$query_string . '#c-' . \$comment_id", $mail, 'the watchers\' View or Reply link, unchanged');
	pg_assert_false(strpos(comments_widget_email_test_php_body('send_comment_email_to_administrators'), 'get_page_content('), 'the administrators\' e-mail draws no page');

	$widgets = comments_widget_email_test_source('includes/fn/widgets.php');
	$doc = strpos($widgets, 'function pg_sw_render_context(');
	pg_assert_true(($doc !== false) && (strpos(substr($widgets, max(0, $doc - 2000), 2000), "'comment_id'") !== false), 'the render context names comment_id');
}
