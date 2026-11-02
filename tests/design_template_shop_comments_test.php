<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the comment threads of the templates with a shop (includes/
 * design_templates/pinegrap-store.php, pinegrap-boutique.php,
 * pinegrap-bookshop.php, hello-pinegrap.php): a blog post's comments and a
 * conversation's replies are drawn by the Comments widget, not the fixed
 * comments block; the reply e-mail draws its comment with a Comments widget
 * card before the System region, which stays; the product reviews ship a
 * layout worded as reviews. Each thread is drawn by the renderer's own steps
 * for a member, a visitor and a staff member, and the e-mail card as an
 * e-mail draws it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The templates on the Comments widget: id => the version that moved them.
function _shop_comments_templates()
{
	return array(
		'pinegrap-store' => '1.2.0',
		'pinegrap-boutique' => '1.2.0',
		'pinegrap-bookshop' => '1.2.0',
		'hello-pinegrap' => '2.6.0',
	);
}

function _shop_comments_tpl($id)
{
	static $cache = array();
	if (!isset($cache[$id])) {
		$cache[$id] = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
	}
	return $cache[$id];
}

function _shop_comments_page($tpl, $key)
{
	foreach ($tpl['pages'] as $p) {
		if (isset($p['key']) && $p['key'] === $key) {
			return $p;
		}
	}
	return null;
}

// Every node of a tree, in document order.
function _shop_comments_nodes($node)
{
	$out = array();
	if (!is_array($node)) {
		return $out;
	}
	if (isset($node['type'])) {
		$out[] = $node;
	}
	foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
		$out = array_merge($out, _shop_comments_nodes($child));
	}
	return $out;
}

// The widgets a page places and the regions it holds, in order ('#<type>'
// for a region).
function _shop_comments_order($tree)
{
	$order = array();
	foreach (_shop_comments_nodes($tree) as $n) {
		if ($n['type'] === 'shared_ref' && isset($n['props']['templateWidget'])) {
			$order[] = $n['props']['templateWidget'];
		}
		if ($n['type'] === 'region' && isset($n['props']['regionType'])) {
			$order[] = '#' . $n['props']['regionType'];
		}
	}
	return $order;
}

function _shop_comments_binding($node, $key)
{
	return isset($node['props']['_bindings'][$key]) ? (string) $node['props']['_bindings'][$key] : '';
}

// The threads: widget key => its page, the page's own view widget above it,
// its options, whether a comment can be answered and shared, whether the
// thread can be followed.
function _shop_comments_threads()
{
	return array(
		'post_comments' => array(
			'page' => 'blog_post', 'view' => 'blog_post', 'reply' => true, 'watch' => true,
			'options' => array('order' => 'oldest', 'featured' => 'first', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20),
		),
		'conversation_replies' => array(
			'page' => 'conversation', 'view' => 'conversation', 'reply' => false, 'watch' => false,
			'options' => array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0),
		),
	);
}

// No page, widget or shared part of the template holds the fixed comments
// block; every comments widget is the kind its place needs.
function test_shop_comments_no_fixed_block()
{
	foreach (_shop_comments_templates() as $id => $version) {
		$tpl = _shop_comments_tpl($id);
		pg_assert_true(version_compare($tpl['version'], $version, '>='), $id . ': the version moved');

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
		foreach ($trees as $where => $tree) {
			foreach (_shop_comments_nodes($tree) as $n) {
				pg_assert_false($n['type'] === 'region' && isset($n['props']['regionType']) && $n['props']['regionType'] === 'comments_block',
					$id . ': no fixed comments block in ' . $where);
			}
		}

		$kinds = array();
		foreach ($tpl['widgets'] as $key => $w) {
			if (isset($w['config']['regionType']) && $w['config']['regionType'] === 'comments') {
				$kinds[$key] = pg_sw_widget_kind($w['config']);
			}
		}
		ksort($kinds);
		pg_assert_same(array('conversation_replies' => 'comments', 'email_comment' => 'comments', 'post_comments' => 'comments', 'product_reviews' => 'comments'),
			$kinds, $id . ': the four comments widgets');

		// The product reviews ship their own layout, worded as reviews like
		// the page's comments label (#4812: the starter said comment).
		pg_assert_true(is_array($tpl['widgets']['product_reviews']['tree']), $id . ': the reviews ship their layout');
		pg_assert_same(array('order' => 'newest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 10),
			pg_comments_widget_options($tpl['widgets']['product_reviews']['config']), $id . ': and their options');
	}
}

// Each thread under its page's view, with its options, a layout that ships
// with the template and flags its mode draws.
function test_shop_comments_threads()
{
	$row = array_keys(pg_comments_widget_row_flags(array('id' => '1'), false, array()));
	$flags = array_merge(array_keys(pg_comments_widget_thread_flags(array())), $row);
	$off = pg_comments_mode_off_bindings('thread')['flags'];
	foreach (_shop_comments_templates() as $id => $version) {
		$tpl = _shop_comments_tpl($id);
		foreach (_shop_comments_threads() as $key => $t) {
			$w = $tpl['widgets'][$key];
			pg_assert_same($t['page'], $w['page'], $id . ' ' . $key . ': its page');
			pg_assert_same($t['options'], pg_comments_widget_options($w['config']), $id . ' ' . $key . ': its options');
			pg_assert_true(empty($w['requires']), $id . ' ' . $key . ': also without the shop');

			$page = _shop_comments_page($tpl, $t['page']);
			pg_assert_true(!empty($page['comments']['email_page']), $id . ' ' . $key . ': its page takes comments and e-mails them');
			$order = _shop_comments_order($page['tree']);
			$at = array_search($key, $order, true);
			pg_assert_true($at !== false && $at > 0 && $order[$at - 1] === $t['view'], $id . ' ' . $key . ': right under the page\'s view');

			pg_assert_true(is_array($w['tree']), $id . ' ' . $key . ': ships its layout');
			$actions = array();
			$values = array();
			$loops = 0;
			$forms = 0;
			foreach (_shop_comments_nodes($w['tree']) as $n) {
				$flag = _shop_comments_binding($n, 'eo_visible_if');
				if ($flag !== '') {
					pg_assert_true(in_array($flag, $flags, true), $id . ' ' . $key . ': ' . $flag . ' is a thread flag');
					pg_assert_false(in_array($flag, $off, true), $id . ' ' . $key . ': ' . $flag . ' is not left out');
				}
				if (_shop_comments_binding($n, 'action') !== '') {
					$actions[] = _shop_comments_binding($n, 'action');
				}
				if (_shop_comments_binding($n, 'value') !== '') {
					$values[] = _shop_comments_binding($n, 'value');
				}
				$loops += ($n['type'] === 'loop_area') ? 1 : 0;
				if (_shop_comments_binding($n, 'action') === 'comment_form') {
					$forms++;
					$first = isset($n['children'][0]['props']['contentType']) ? $n['children'][0]['props']['contentType'] : '';
					pg_assert_same('messages', $first, $id . ' ' . $key . ': the form\'s notices first');
				}
			}
			pg_assert_same(1, $loops, $id . ' ' . $key . ': one comment card');
			pg_assert_same(1, $forms, $id . ' ' . $key . ': one form');
			foreach (array('comment_submit', 'comment_allow_new_form', 'comment_close', 'comment_reopen') as $a) {
				pg_assert_true(in_array($a, $actions, true), $id . ' ' . $key . ': ' . $a);
			}
			foreach (array('comment_message', 'comment_name', 'comment_name_type', 'comment_file') as $v) {
				pg_assert_true(in_array($v, $values, true), $id . ' ' . $key . ': ' . $v);
			}
			foreach (array('comment_reply', 'comment_share', 'comment_reply_cancel') as $a) {
				pg_assert_same($t['reply'], in_array($a, $actions, true), $id . ' ' . $key . ': ' . $a . ($t['reply'] ? '' : ' left out'));
			}
			foreach (array('comment_watch_form', 'comment_watch', 'comment_unwatch') as $a) {
				pg_assert_same($t['watch'], in_array($a, $actions, true), $id . ' ' . $key . ': ' . $a . ($t['watch'] ? '' : ' left out'));
			}
			pg_assert_same($t['watch'], in_array('comment_watcher', $values, true), $id . ' ' . $key . ': the follow box' . ($t['watch'] ? '' : ' left out'));
		}
	}
}

// What the renderer hands a thread's draw for a visitor ('member', 'staff'
// or 'out', signed out on a page that asks to sign in): comment 1, featured,
// with comment 2 answering it and comment 3 answering comment 2, then
// comment 4.
function _shop_comments_thread_data($cfg, $who)
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
function _shop_comments_draw($tree, $data)
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

// Each thread drawn for a member, a visitor and a staff member.
function test_shop_comments_threads_draw()
{
	foreach (_shop_comments_templates() as $id => $version) {
		$tpl = _shop_comments_tpl($id);
		foreach (_shop_comments_threads() as $key => $t) {
			$w = $tpl['widgets'][$key];
			$at = $id . ' ' . $key . ': ';

			$html = _shop_comments_draw($w['tree'], _shop_comments_thread_data($w['config'], 'member'));
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
				// The rows come oldest first; nothing is pinned above them.
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

			$out = _shop_comments_draw($w['tree'], _shop_comments_thread_data($w['config'], 'out'));
			pg_assert_false(strpos($out, '<form'), $at . 'no form until signed in');
			pg_assert_same(1, preg_match('#<a\b[^>]*href="/login\?send_to=%2Fpost%23software_add_comment"[^>]*>#', $out), $at . 'the sign-in link');
			pg_assert_false(strpos($out, '^^'), $at . 'no token left');

			$staff = _shop_comments_draw($w['tree'], _shop_comments_thread_data($w['config'], 'staff'));
			pg_assert_contains('id="software_allow_or_disallow_new_comments"', $staff, $at . 'the staff form');
			pg_assert_contains('href="/software/edit_comment.php?id=1"', $staff, $at . 'Edit on each comment');
			pg_assert_false(strpos($staff, 'pg-edit-chip'), $at . 'no second pencil beside the design\'s Edit');
			pg_assert_contains('Publish now', $staff, $at . 'the publish options');
			pg_assert_false(strpos($staff, '^^'), $at . 'no token left');
		}
	}
}

// The reply e-mail: the card right before the System region, which stays;
// drawn as an e-mail draws it, with the one link to the comment that makes
// the e-mail take it.
function test_shop_comments_email_card()
{
	foreach (_shop_comments_templates() as $id => $version) {
		$tpl = _shop_comments_tpl($id);
		$w = $tpl['widgets']['email_comment'];
		pg_assert_same('email_reply', $w['page'], $id . ': on the reply e-mail');
		pg_assert_same(array('order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0),
			pg_comments_widget_options($w['config']), $id . ': flat and alone');
		pg_assert_true(is_array($w['tree']), $id . ': ships its layout (the installer\'s preview shows the e-mail)');
		pg_assert_true(isset(pg_design_template_email_pages($tpl)['email_reply']), $id . ': and lists it');
		$preview = pg_design_template_preview_html($id, 'email_reply');
		pg_assert_contains(h(lang('View or Reply')), $preview, $id . ': with the card');
		pg_assert_false(strpos(preg_replace('/href="[^"]*"/', '', $preview), '^^') !== false, $id . ': and no ^^ outside an address');

		foreach (array('blog_post', 'conversation') as $key) {
			$page = _shop_comments_page($tpl, $key);
			pg_assert_same('{{tab:email_reply}}', $page['comments']['email_page'], $id . ' ' . $key . ': e-mails through it');
		}
		$order = _shop_comments_order(_shop_comments_page($tpl, 'email_reply')['tree']);
		$at = array_search('#system', $order, true);
		pg_assert_true($at !== false, $id . ': the System region stays');
		pg_assert_same('email_comment', ($at !== false && $at > 0) ? $order[$at - 1] : null, $id . ': the card right before it');

		$links = 0;
		foreach (_shop_comments_nodes($w['tree']) as $n) {
			pg_assert_false(isset($n['props']['contentType']) && $n['props']['contentType'] === 'messages', $id . ': no Messages node in an e-mail');
			pg_assert_false(isset($n['props']['tag']) && in_array($n['props']['tag'], array('form', 'input', 'select', 'textarea', 'button'), true), $id . ': no form, field or button');
			pg_assert_false(_shop_comments_binding($n, 'action') !== '', $id . ': no control');
			$links += (_shop_comments_binding($n, 'href') === '__comment_url') ? 1 : 0;
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
		pg_assert_true(pg_comments_email_decision($comment, $page, 9), $id . ': the post\'s e-mail page draws its comment');

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
}

// The product page's reviews use one word: every text of the widget's
// layout says review, as the page's comments label does, never comment
// (#4812). The page's label is the template's own.
function test_shop_comments_product_reviews_say_review()
{
	require_once(__DIR__ . '/helpers/language_map.php');
	foreach (_shop_comments_templates() as $id => $version) {
		$tpl = _shop_comments_tpl($id);
		$page = _shop_comments_page($tpl, 'product');
		pg_assert_same(lang('Review'), $page['comments']['label'], $id . ': the product page labels reviews');
		pg_assert_true(version_compare($tpl['version'], array('pinegrap-store' => '1.2.2', 'pinegrap-boutique' => '1.2.2', 'pinegrap-bookshop' => '1.3.3', 'hello-pinegrap' => '2.6.1')[$id], '>='), $id . ': the version moved');

		$texts = array();
		$flags = array();
		$actions = array();
		foreach (_shop_comments_nodes($tpl['widgets']['product_reviews']['tree']) as $n) {
			if (isset($n['props']['text']) && is_string($n['props']['text']) && empty($n['props']['_bindings']['text'])) {
				$texts[] = $n['props']['text'];
			}
			$flags[] = _shop_comments_binding($n, 'eo_visible_if');
			$actions[] = _shop_comments_binding($n, 'action');
		}
		$map = pg_test_language_map('tr');
		foreach (array('No reviews yet. Be the first to review this product.', 'Review this product', 'Your review', 'Sign in to write a review.', 'Submit review', 'Stop new reviews', 'Allow new reviews') as $words) {
			pg_assert_true(in_array(lang($words), $texts, true), $id . ': says ' . $words);
			// In Turkish too: "değerlendirme", never "yorum".
			pg_assert_true(isset($map[$words]) && mb_stripos($map[$words], 'değerlendir') !== false && mb_stripos($map[$words], 'yorum') === false, $id . ': Turkish ' . $words);
		}
		foreach ($texts as $text) {
			pg_assert_false((bool)preg_match('/\bcomments?\b/i', $text), $id . ': no comment wording: ' . $text);
		}
		pg_assert_true(in_array('rating_on', $flags, true), $id . ': the stars can be given');
		foreach (array('comment_submit', 'comment_reply', 'comment_close', 'comment_reopen', 'comment_watch') as $a) {
			pg_assert_true(in_array($a, $actions, true), $id . ': ' . $a);
		}
	}
}

// A page whose Catalog Detail opens with the starter (whose Reviews tab holds
// the fixed comments block) places a comments widget on the page itself, so
// the Catalog Detail drops that tab and the save census does not warn of a
// block beside the widget (#4901).
function test_shop_comments_starter_product_page_saves_without_warning()
{
	foreach (array_keys(_shop_comments_templates()) as $id) {
		$tpl = _shop_comments_tpl($id);
		$found = 0;
		foreach ($tpl['pages'] as $p) {
			$refs = array();
			foreach (_shop_comments_nodes($p['tree']) as $n) {
				if ($n['type'] === 'shared_ref' && isset($n['props']['templateWidget'])) $refs[] = $n['props']['templateWidget'];
			}
			$starter = array();
			$thread = 0;
			foreach ($refs as $key) {
				$w = $tpl['widgets'][$key];
				$type = isset($w['config']['regionType']) ? pg_sw_widget_kind($w['config']) : '';
				if ($type === 'catalog_item_view' && $w['tree'] === 'starter') $starter[] = $key;
				if ($type === 'comments') $thread++;
			}
			if (!$starter) continue;
			$found++;
			pg_assert_true($thread > 0, $id . ': page ' . $p['key'] . ' places a comments widget beside the starter product view');

			// The census of the saved page: the starter's block counted in the
			// product view, the widget on the page.
			$facts = pg_comments_page_census_facts(array('refs' => array(1 => 1, 2 => 1), 'blocks' => 0), array(
				1 => array('type' => 'catalog_item_view', 'census' => array('refs' => array(), 'blocks' => 1)),
				2 => array('type' => 'comments', 'census' => array('refs' => array(), 'blocks' => 0)),
			));
			pg_assert_same(array(), pg_comments_save_warning_codes($facts + array('comments_on' => true)), $id . ': page ' . $p['key'] . ' saves without the block warning');
		}
		pg_assert_true($found > 0, $id . ': a product page with the starter product view');
	}
}
