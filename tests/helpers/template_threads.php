<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Checks for the comment threads a design template ships on the Comments
 * widget (the Café, Clinic and Playground templates; the hello template's
 * Google Reviews): no fixed comments block left, each thread under its
 * page's record view with its options and a layout of its own, each drawn
 * by the renderer's own steps for a member, a visitor and a staff member,
 * and the reply e-mail's card before the System region, drawn as an e-mail
 * draws it. The shop templates keep their own table
 * (design_template_shop_comments_test.php).
 *
 * Not a test file (tools/test.php runs tests/*_test.php only): a test
 * requires it with require_once(__DIR__ . '/helpers/template_threads.php').
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A template file as the installer reads it, once.
function _tpl_threads_tpl($id)
{
	static $cache = array();
	if (!isset($cache[$id])) {
		$cache[$id] = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
	}
	return $cache[$id];
}

function _tpl_threads_page($tpl, $key)
{
	foreach ($tpl['pages'] as $p) {
		if (isset($p['key']) && $p['key'] === $key) {
			return $p;
		}
	}
	return null;
}

// Every node of a tree, in document order.
function _tpl_threads_nodes($node)
{
	$out = array();
	if (!is_array($node)) {
		return $out;
	}
	if (isset($node['type'])) {
		$out[] = $node;
	}
	foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
		$out = array_merge($out, _tpl_threads_nodes($child));
	}
	return $out;
}

// The widgets a tree places and the regions it holds, in order ('#<type>'
// for a region).
function _tpl_threads_order($tree)
{
	$order = array();
	foreach (_tpl_threads_nodes($tree) as $n) {
		if ($n['type'] === 'shared_ref' && isset($n['props']['templateWidget'])) {
			$order[] = $n['props']['templateWidget'];
		}
		if ($n['type'] === 'region' && isset($n['props']['regionType'])) {
			$order[] = '#' . $n['props']['regionType'];
		}
	}
	return $order;
}

function _tpl_threads_binding($node, $key)
{
	return isset($node['props']['_bindings'][$key]) ? (string) $node['props']['_bindings'][$key] : '';
}

// Every tree of a template: its pages, widgets and shared parts.
function _tpl_threads_trees($tpl)
{
	$trees = array();
	foreach ($tpl['pages'] as $p) {
		$trees['page ' . $p['key']] = $p['tree'];
	}
	foreach ($tpl['widgets'] as $key => $w) {
		$trees['widget ' . $key] = $w['tree'];
	}
	foreach ((isset($tpl['shared']) ? $tpl['shared'] : array()) as $key => $s) {
		$trees['shared ' . $key] = $s['tree'];
	}
	return $trees;
}

// No tree of the template holds the fixed comments block; its comments
// widgets are exactly $kinds (widget key => kind).
function _tpl_threads_check_no_fixed_block($id, $kinds)
{
	$tpl = _tpl_threads_tpl($id);
	foreach (_tpl_threads_trees($tpl) as $where => $tree) {
		foreach (_tpl_threads_nodes($tree) as $n) {
			pg_assert_false($n['type'] === 'region' && isset($n['props']['regionType']) && $n['props']['regionType'] === 'comments_block',
				$id . ': no fixed comments block in ' . $where);
		}
	}
	$found = array();
	foreach ($tpl['widgets'] as $key => $w) {
		if (isset($w['config']['regionType']) && $w['config']['regionType'] === 'comments') {
			$found[$key] = pg_sw_widget_kind($w['config']);
		}
	}
	ksort($found);
	ksort($kinds);
	pg_assert_same($kinds, $found, $id . ': its comments widgets');
}

// One thread: $t holds page, view (the page's record view right above it),
// options, reply (Reply and Share on each comment) and watch (the follow
// boxes). Its page takes comments and e-mails them; the layout ships with
// the template and uses only flags its thread mode draws.
function _tpl_threads_check_thread($id, $key, $t)
{
	$tpl = _tpl_threads_tpl($id);
	$at = $id . ' ' . $key . ': ';
	$w = $tpl['widgets'][$key];
	pg_assert_same($t['page'], $w['page'], $at . 'its page');
	pg_assert_same($t['options'], pg_comments_widget_options($w['config']), $at . 'its options');
	pg_assert_true(empty($w['requires']), $at . 'also without the shop');

	$page = _tpl_threads_page($tpl, $t['page']);
	pg_assert_true(!empty($page['comments']['email_page']), $at . 'its page takes comments and e-mails them');
	$order = _tpl_threads_order($page['tree']);
	$pos = array_search($key, $order, true);
	pg_assert_true($pos !== false && $pos > 0 && $order[$pos - 1] === $t['view'], $at . 'right under the page\'s view');

	pg_assert_true(is_array($w['tree']), $at . 'ships its layout');
	$row = array_keys(pg_comments_widget_row_flags(array('id' => '1'), false, array()));
	$flags = array_merge(array_keys(pg_comments_widget_thread_flags(array())), $row);
	$off = pg_comments_mode_off_bindings('thread')['flags'];
	$actions = array();
	$values = array();
	$loops = 0;
	$forms = 0;
	foreach (_tpl_threads_nodes($w['tree']) as $n) {
		$flag = _tpl_threads_binding($n, 'eo_visible_if');
		if ($flag !== '') {
			pg_assert_true(in_array($flag, $flags, true), $at . $flag . ' is a thread flag');
			pg_assert_false(in_array($flag, $off, true), $at . $flag . ' is not left out');
		}
		if (_tpl_threads_binding($n, 'action') !== '') {
			$actions[] = _tpl_threads_binding($n, 'action');
		}
		if (_tpl_threads_binding($n, 'value') !== '') {
			$values[] = _tpl_threads_binding($n, 'value');
		}
		$loops += ($n['type'] === 'loop_area') ? 1 : 0;
		if (_tpl_threads_binding($n, 'action') === 'comment_form') {
			$forms++;
			$first = isset($n['children'][0]['props']['contentType']) ? $n['children'][0]['props']['contentType'] : '';
			pg_assert_same('messages', $first, $at . 'the form\'s notices first');
		}
	}
	pg_assert_same(1, $loops, $at . 'one comment card');
	pg_assert_same(1, $forms, $at . 'one form');
	foreach (array('comment_submit', 'comment_allow_new_form', 'comment_close', 'comment_reopen') as $a) {
		pg_assert_true(in_array($a, $actions, true), $at . $a);
	}
	foreach (array('comment_message', 'comment_name', 'comment_name_type', 'comment_file') as $v) {
		pg_assert_true(in_array($v, $values, true), $at . $v);
	}
	foreach (array('comment_reply', 'comment_share', 'comment_reply_cancel') as $a) {
		pg_assert_same($t['reply'], in_array($a, $actions, true), $at . $a . ($t['reply'] ? '' : ' left out'));
	}
	foreach (array('comment_watch_form', 'comment_watch', 'comment_unwatch') as $a) {
		pg_assert_same($t['watch'], in_array($a, $actions, true), $at . $a . ($t['watch'] ? '' : ' left out'));
	}
	pg_assert_same($t['watch'], in_array('comment_watcher', $values, true), $at . 'the follow box' . ($t['watch'] ? '' : ' left out'));
}

// What the renderer hands a thread's draw for a visitor ('member', 'staff'
// or 'out', signed out on a page that asks to sign in): comment 1, featured,
// with comment 2 answering it and comment 3 answering comment 2, then
// comment 4.
function _tpl_threads_data($cfg, $who)
{
	$member = ($who !== 'out');
	$staff = ($who === 'staff');
	$raw = array(
		array('id' => '4', 'parent_id' => '0', 'published' => '1', 'featured' => '0', 'name' => 'Sam Whitaker', 'message' => 'A later word'),
		array('id' => '1', 'parent_id' => '0', 'published' => '1', 'featured' => '1', 'name' => 'Priya Raman', 'message' => 'Great guide'),
		array('id' => '2', 'parent_id' => '1', 'published' => '1', 'featured' => '0', 'name' => 'Jordan Lee', 'message' => 'Agreed'),
		array('id' => '3', 'parent_id' => '2', 'published' => '1', 'featured' => '0', 'name' => 'Marcus Bell', 'message' => 'Me too'),
	);
	$by_id = array();
	foreach ($raw as $r) {
		$by_id[(int) $r['id']] = $r;
	}
	$opts = pg_comments_widget_options($cfg);
	$facts = array(
		'allow_new' => true, 'signed_in' => $member, 'require_login' => true, 'count' => count($raw),
		'featured_mode' => $opts['featured'], 'has_featured' => true, 'staff' => $staff, 'name_mode' => $staff ? 'typed' : 'fixed',
		'rating_on' => false, 'files_on' => false, 'watching_on' => true, 'pages' => 1, 'manage_watchers' => $staff,
	);
	$flags = pg_comments_widget_thread_flags($facts);
	$state = array('is_staff' => $staff, 'sees_pending' => $staff, 'own_ids' => array(), 'rating_on' => false, 'badge_label' => '');
	$rows = array();
	foreach (pg_comments_widget_order($raw, $cfg) as $entry) {
		$cid = $entry['id'];
		$parent = ($entry['parent_id'] > 0) ? $by_id[$entry['parent_id']] : null;
		$rows[] = array(
			'id' => $cid,
			'flags' => pg_comments_widget_row_flags($entry['row'], $entry['featured'], $state,
				array('depth' => $entry['depth'], 'quoted' => $entry['quoted'], 'replies' => $entry['replies'])),
			'values' => array(
				'__comment_id' => (string) $cid, '__comment_anchor' => 'c-' . $cid, '__comment_name' => $entry['row']['name'],
				'__comment_staff_name' => $staff ? 'user' . $cid : '', '__comment_message_html' => $entry['row']['message'],
				'__comment_date' => '2 hours ago', '__comment_badge' => '', '__comment_status' => '',
				'__comment_edit_url' => $staff ? '/software/edit_comment.php?id=' . $cid : '',
				'__comment_url' => '/post?r=abc&comment=' . $cid . '#c-' . $cid,
				'__comment_reply_url' => $member ? '/post?r=abc&reply_to=' . $cid . '#software_add_comment' : '',
				'__comment_parent_id' => $parent ? (string) $parent['id'] : '', '__comment_parent_name' => $parent ? $parent['name'] : '',
				'__comment_parent_url' => $parent ? '#c-' . $parent['id'] : '',
				'__comment_quote' => ($parent && $entry['quoted']) ? 'Quoting ' . $parent['message'] : '',
				'__comment_depth' => (string) $entry['depth'], '__comment_reply_count' => (string) $entry['replies'],
			),
			'datetime' => '',
			'depth' => $entry['depth'],
			'reply_ok' => $member,
		);
	}
	$sections = array('comment_captcha' => '', 'comment_rating_input' => '', 'comment_publish' => '', 'comment_watchers' => '', 'comment_pagination' => '');
	if ($staff) {
		$sections['comment_publish'] = '<div class="pg-test-publish">Publish now</div>';
	}
	return array(
		'widget_id' => 7,
		'thread_flags' => $flags,
		'thread_values' => array(
			'__comments_title' => '4 comments', '__comments_count' => '4', '__comments_label' => 'Comment', '__comments_label_plural' => 'Comments',
			'__comments_intro' => '', '__comments_closed_message' => '', '__comments_show_all_url' => '', '__comments_show_featured_url' => '',
			'__login_url' => $flags['must_sign_in'] ? '/login?send_to=%2Fpost%23software_add_comment' : '',
			'__comment_author_name' => ($member && !$staff) ? 'Casey Morgan' : '', '__reply_to_name' => '',
			'__watcher_count_text' => '2 people get an e-mail when a comment is added.',
		),
		'rows' => $rows,
		'form' => array(
			'can_comment' => $flags['can_comment'],
			'action_url' => '/software/add_comment.php',
			'hidden_html' => $flags['can_comment'] ? pg_comments_form_hidden_html('', '/post', 5, 'submitted_form', 3, 0) : '',
			'multipart' => false,
			'label_slot' => 'pg_comment_form_label',
			'watcher_checked' => true,
			'publish_default' => $staff ? 'now' : '',
			'state' => array('files_on' => false, 'watcher_box' => ($member && !$staff), 'name_mode' => $facts['name_mode'],
				'rating_on' => false, 'captcha_on' => false, 'can_publish' => $staff),
		),
		'controls' => array(
			'can_reply' => $member, 'replying' => false, 'cancel_url' => '/post?r=abc#software_add_comment',
			'share_copied' => 'Link copied.', 'share_prompt' => 'Copy the link to this comment:',
			'can_watch' => $flags['is_watching'] || $flags['not_watching'], 'is_watcher' => false,
			'watch_action_url' => '/software/add_or_remove_watcher.php',
			'watch_hidden_html' => pg_comments_small_form_hidden_html('', '/post', 5, 'submitted_form', 3, 'add'),
			'is_staff' => $staff, 'allow_new' => true,
			'allow_action_url' => '/software/allow_or_disallow_new_comments.php',
			'allow_hidden_html' => pg_comments_small_form_hidden_html('', '/post', 5, 'submitted_form', 3, 'disallow'),
		),
		'sections' => $sections,
		'slots' => array('pg_comment_form_message' => '', 'pg_comment_form_name' => '', 'pg_comment_form_name_options' => '', 'pg_comment_form_label' => 'comment'),
		'edit_chips' => $staff,
	);
}

// Draws a thread tree by the renderer's own steps (_render_system_widget_comments()),
// with a session of its own put back after.
function _tpl_threads_draw($tree, $data)
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();
	pg_comments_drop_mode_bindings($tree, 'thread');
	_pg_inject_messages_node($tree, 'add_comment');
	pg_comments_stamp_form_messages($tree);
	$html = pg_comments_widget_draw($tree, $data);
	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
	return $html;
}

// A thread drawn for a member, a signed-out visitor and a staff member.
function _tpl_threads_check_draws($id, $key, $t)
{
	$tpl = _tpl_threads_tpl($id);
	$w = $tpl['widgets'][$key];
	$at = $id . ' ' . $key . ': ';

	$html = _tpl_threads_draw($w['tree'], _tpl_threads_data($w['config'], 'member'));
	pg_assert_contains('<form id="software_add_comment"', $html, $at . 'the member\'s form');
	pg_assert_contains('name="message"', $html, $at . 'the message box');
	pg_assert_contains('name="page_id" value="5"', $html, $at . 'the hidden fields');
	pg_assert_contains('4 comments', $html, $at . 'the title');
	foreach (array(1, 2, 3, 4) as $cid) {
		pg_assert_same(1, substr_count($html, ' id="c-' . $cid . '"'), $at . 'comment ' . $cid . ' once');
	}
	if ($t['options']['layout'] === 'threaded') {
		pg_assert_contains('id="c-2" data-pg-comment-depth="1"', $html, $at . 'an answer under its comment');
		pg_assert_contains('id="c-3" data-pg-comment-depth="2"', $html, $at . 'one level more');
	} else {
		pg_assert_contains('id="c-3" data-pg-comment-depth="0"', $html, $at . 'one after another');
		pg_assert_true(strpos($html, 'id="c-4"') < strpos($html, 'id="c-1"') && strpos($html, 'id="c-2"') < strpos($html, 'id="c-3"'), $at . 'in the order written');
	}
	if ($t['options']['featured'] === 'first') {
		pg_assert_true(strpos($html, 'id="c-1"') < strpos($html, 'id="c-4"'), $at . 'the featured comment first');
	}
	pg_assert_same($t['reply'], strpos($html, 'data-pg-comment-reply="1"') !== false, $at . 'Reply' . ($t['reply'] ? '' : ' left out'));
	pg_assert_same($t['reply'], strpos($html, 'data-pg-comment-share=') !== false, $at . 'Share' . ($t['reply'] ? '' : ' left out'));
	pg_assert_same($t['watch'], strpos($html, 'id="software_watcher"') !== false, $at . 'the follow form' . ($t['watch'] ? '' : ' left out'));
	pg_assert_contains('Casey Morgan', $html, $at . 'the member posts under the profile\'s name');
	pg_assert_false(strpos($html, 'software_allow_or_disallow_new_comments'), $at . 'no staff form for a member');
	pg_assert_false(strpos($html, 'edit_comment.php'), $at . 'no Edit for a member');
	pg_assert_false(strpos($html, '^^'), $at . 'no token left');

	$out = _tpl_threads_draw($w['tree'], _tpl_threads_data($w['config'], 'out'));
	pg_assert_false(strpos($out, '<form'), $at . 'no form until signed in');
	pg_assert_same(1, preg_match('#<a\b[^>]*href="/login\?send_to=%2Fpost%23software_add_comment"[^>]*>#', $out), $at . 'the sign-in link');
	pg_assert_false(strpos($out, '^^'), $at . 'no token left');

	$staff = _tpl_threads_draw($w['tree'], _tpl_threads_data($w['config'], 'staff'));
	pg_assert_contains('id="software_allow_or_disallow_new_comments"', $staff, $at . 'the staff form');
	pg_assert_contains('href="/software/edit_comment.php?id=1"', $staff, $at . 'Edit on each comment');
	pg_assert_false(strpos($staff, 'pg-edit-chip'), $at . 'no second pencil beside the design\'s Edit');
	pg_assert_contains('Publish now', $staff, $at . 'the publish options');
	pg_assert_false(strpos($staff, '^^'), $at . 'no token left');
}

// The reply e-mail of $id: its Comments widget card (widget key $key)
// right before the System region, which stays; one link to the comment and
// nothing a mail program cannot show; listed among the template's e-mails
// and drawn in the installer's preview; drawn as an e-mail draws it, so the
// e-mail takes the widget's comment. $pages: the pages that e-mail through
// it.
function _tpl_threads_check_email_card($id, $key, $mail_page, $pages)
{
	$tpl = _tpl_threads_tpl($id);
	$w = $tpl['widgets'][$key];
	pg_assert_same($mail_page, $w['page'], $id . ': on the reply e-mail');
	pg_assert_same(array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0),
		pg_comments_widget_options($w['config']), $id . ': flat and alone');
	pg_assert_true(is_array($w['tree']), $id . ': ships its layout (the installer\'s preview shows the e-mail)');
	pg_assert_true(isset(pg_design_template_email_pages($tpl)[$mail_page]), $id . ': and lists it');
	$preview = pg_design_template_preview_html($id, $mail_page);
	pg_assert_contains(h(lang('View or Reply')), $preview, $id . ': with the card');
	pg_assert_false(strpos(preg_replace('/href="[^"]*"/', '', $preview), '^^') !== false, $id . ': and no ^^ outside an address');

	foreach ($pages as $page_key) {
		$page = _tpl_threads_page($tpl, $page_key);
		pg_assert_same('{{tab:' . $mail_page . '}}', $page['comments']['email_page'], $id . ' ' . $page_key . ': e-mails through it');
	}
	$order = _tpl_threads_order(_tpl_threads_page($tpl, $mail_page)['tree']);
	$pos = array_search('#system', $order, true);
	pg_assert_true($pos !== false, $id . ': the System region stays');
	pg_assert_same($key, ($pos !== false && $pos > 0) ? $order[$pos - 1] : null, $id . ': the card right before it');

	$links = 0;
	foreach (_tpl_threads_nodes($w['tree']) as $n) {
		pg_assert_false(isset($n['props']['contentType']) && $n['props']['contentType'] === 'messages', $id . ': no Messages node in an e-mail');
		pg_assert_false(isset($n['props']['tag']) && in_array($n['props']['tag'], array('form', 'input', 'select', 'textarea', 'button'), true), $id . ': no form, field or button');
		pg_assert_false(_tpl_threads_binding($n, 'action') !== '', $id . ': no control');
		$links += (_tpl_threads_binding($n, 'href') === '__comment_url') ? 1 : 0;
	}
	pg_assert_same(1, $links, $id . ': one link to the comment');

	$page = array(
		'page_id' => '5', 'page_name' => 'blog-post', 'page_folder' => '1', 'page_type' => 'standard', 'comments' => '1', 'comments_label' => 'Comment',
		'comments_message' => '', 'comments_rating' => '0', 'comments_allow_new_comments' => '1', 'comments_disallow_new_comment_message' => '',
		'comments_automatic_publish' => '1', 'comments_allow_user_to_select_name' => '0', 'comments_require_login_to_comment' => '1',
		'comments_allow_file_attachments' => '0', 'comments_show_submitted_date_and_time' => '1', 'comments_administrator_email_to_email_address' => '',
		'comments_administrator_email_conditional_administrators' => '0', 'comments_submitter_email_page_id' => '9', 'comments_watcher_email_page_id' => '9',
		'comments_watchers_managed_by_submitter' => '0',
	);
	$comment = array(
		'id' => '12', 'page_id' => '5', 'item_type' => 'submitted_form', 'item_id' => '3', 'name' => 'Jordan Lee', 'message' => 'Thank you for the tips.',
		'file_name' => null, 'file_size' => null, 'published' => '1', 'publish_date_and_time' => '0000-00-00 00:00:00', 'publish_cancel' => '0',
		'featured' => '0', 'rating' => '0', 'verified_order_id' => '0', 'seller_reply' => '', 'seller_reply_at' => '0', 'username' => 'jordan',
		'user_role' => '3', 'user_id' => '4', 'user_badge' => '0', 'user_badge_label' => '', 'created_timestamp' => '1761300000', 'parent_id' => '9',
	);
	$parent = array_merge($comment, array('id' => '9', 'name' => 'Priya Raman', 'message' => 'Which size did you take?', 'parent_id' => '0'));
	pg_assert_true(pg_comments_email_decision($comment, $page, 9), $id . ': the page\'s e-mail page draws its comment');

	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();
	$ctx = pg_sw_render_context(array('email' => true, 'page_id' => 9, 'comment_id' => 12));
	$data = pg_comments_email_draw_data($comment, $parent, $page, $w['config'], array(
		'widget_id' => 4, 'allow_new' => true, 'reply_ok' => true, 'base' => 'https://example.com/blog-post', 'query' => '?r=abc',
		'site' => 'https://example.com/', 'dates' => array('date' => 'the date', 'seller_reply_date' => ''), 'badge_label' => '',
	));
	$tree = $w['tree'];
	pg_comments_email_strip($tree);
	pg_comments_drop_mode_bindings($tree, 'thread');
	$html = pg_comments_widget_draw($tree, $data);
	pg_sw_render_context($ctx);
	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
	$url = $data['rows'][0]['values']['__comment_url'];
	pg_assert_same(1, substr_count($html, ' id="c-12"'), $id . ': the comment, once');
	pg_assert_contains('href="' . h($url) . '"', $html, $id . ': View or Reply opens it');
	pg_assert_contains(h(lang('View or Reply')), $html, $id . ': its words');
	pg_assert_contains('Which size did you take?', $html, $id . ': the comment it answers');
	pg_assert_contains('Thank you for the tips.', $html, $id . ': the comment\'s words');
	pg_assert_contains('the date', $html, $id . ': its date');
	pg_assert_false(strpos($html, '^^'), $id . ': no token left');
	pg_assert_true(pg_comments_email_drew($html, 12, $url), $id . ': the e-mail takes the widget\'s comment');
}

// A Google Reviews widget of $id (key $key) placed on $page_key inside the
// section named $section (its layer name: `_label`, or `customName` in a
// template whose builders name nodes so), after the sample words: a layout
// of its own that shows only while Google returns reviews, with the author
// link and Google's attribution, drawn by the renderer for a place and not
// at all without one; the installer's preview, a first visit, leaves it out.
function _tpl_threads_check_google_reviews($id, $key, $page_key, $section)
{
	$tpl = _tpl_threads_tpl($id);
	$w = $tpl['widgets'][$key];
	pg_assert_same('google_reviews', pg_sw_widget_kind($w['config']), $id . ': a Google Reviews widget');
	pg_assert_same($page_key, $w['page'], $id . ': on the ' . $page_key . ' page');
	pg_assert_true(is_array($w['tree']), $id . ': ships its layout (the page stays in the installer\'s preview)');

	$placed = 0;
	foreach (_tpl_threads_nodes(_tpl_threads_page($tpl, $page_key)['tree']) as $n) {
		$name = isset($n['props']['_label']) ? $n['props']['_label'] : (isset($n['props']['customName']) ? $n['props']['customName'] : '');
		if ($name === $section) {
			$order = _tpl_threads_order($n);
			$placed += in_array($key, $order, true) ? 1 : 0;
		}
	}
	pg_assert_same(1, $placed, $id . ': beside the sample words, in their section');

	$top = $w['tree']['children'];
	pg_assert_same(1, count($top), $id . ': one block');
	pg_assert_same('has_reviews', _tpl_threads_binding($top[0], 'eo_visible_if'), $id . ': shown only while Google returns reviews');
	$known = array('has_reviews', 'no_reviews', 'review_has_photo', 'review_has_text');
	$bound = array();
	$loops = 0;
	foreach (_tpl_threads_nodes($w['tree']) as $n) {
		$flag = _tpl_threads_binding($n, 'eo_visible_if');
		if ($flag !== '') {
			pg_assert_true(in_array($flag, $known, true), $id . ': ' . $flag . ' is a Google Reviews flag');
		}
		foreach (array('text', 'href', 'src') as $b) {
			if (_tpl_threads_binding($n, $b) !== '') {
				$bound[] = _tpl_threads_binding($n, $b);
			}
		}
		$loops += ($n['type'] === 'loop_area') ? 1 : 0;
		if (isset($n['props']['target']) && $n['props']['target'] === '_blank') {
			pg_assert_same('noopener', isset($n['props']['rel']) ? $n['props']['rel'] : '', $id . ': a new window without the opener');
		}
	}
	pg_assert_same(1, $loops, $id . ': one review card');
	foreach (array('__review_author_name', '__review_author_url', '__review_text', '__review_rating', '__review_url', '__place_maps_url', '__google_attribution', '__place_rating') as $token) {
		pg_assert_true(in_array($token, $bound, true), $id . ': ' . $token);
	}

	$place = array(
		'name' => 'Kodpen Café', 'rating' => 4.6, 'count' => 128, 'maps_url' => 'https://maps.google.com/?cid=123',
		'reviews' => array(
			array('author' => 'Ayla K.', 'author_url' => 'https://www.google.com/maps/contrib/1', 'photo' => '', 'rating' => 5,
				'text' => 'Lovely <b>coffee</b>', 'relative' => '2 weeks ago', 'url' => 'https://www.google.com/maps/reviews/1'),
		),
	);
	$tree = json_encode($w['tree']);
	$html = _render_system_widget_google_reviews($tree, 7, $w['config'], 'preview', $place);
	pg_assert_contains('Ayla K.', $html, $id . ': the author');
	pg_assert_contains('href="https://www.google.com/maps/contrib/1"', $html, $id . ': linked to Google Maps');
	pg_assert_contains('Lovely &lt;b&gt;coffee&lt;/b&gt;', $html, $id . ': the words, escaped');
	pg_assert_contains('4.6', $html, $id . ': the rating');
	pg_assert_contains('>Google Maps<', $html, $id . ': Google\'s attribution');
	pg_assert_false(strpos($html, '^^'), $id . ': no token left');
	pg_assert_same('', _render_system_widget_google_reviews($tree, 7, $w['config'], 'preview', null), $id . ': nothing until set up');
	$none = $place;
	$none['reviews'] = array();
	pg_assert_same('', trim(strip_tags(_render_system_widget_google_reviews($tree, 7, $w['config'], 'preview', $none))), $id . ': nothing while Google has no review');

	$preview = pg_design_template_preview_html($id, $page_key);
	pg_assert_true($preview !== '', $id . ': the ' . $page_key . ' page stays in the preview');
	pg_assert_false(strpos($preview, 'Google Maps') !== false, $id . ': which shows a first visit, without Google\'s reviews');
}
