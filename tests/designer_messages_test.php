<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Messages node of system widgets: a node prints and uses up
 * only the liveform it is named for (_render_tree_node, case 'messages'),
 * and every widget renderer names its node (_pg_inject_messages_node()).
 * An unnamed node prints every form's messages, so on a page with two
 * widgets the first one drawn took the other's errors.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A widget tree with a Messages node, named the way the renderer names it.
function _test_msg_tree($form_name)
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'container', 'props' => array(), 'children' => array(
			array('type' => 'content', 'props' => array('contentType' => 'messages', 'cssClass' => ''), 'children' => array()),
		)),
	));
	_pg_inject_messages_node($tree, $form_name);
	return $tree;
}

// The my account widget is drawn above the account security widget on the
// same page: the wrong-code error of the security widget's form is left
// for the security widget, which prints it and uses it up.
function test_designer_messages_named_node_leaves_other_forms()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('software' => array('liveforms' => array(
		'my_account_profile' => array(0 => array('code' => array('error' => true, 'error_message' => 'Wrong code'))),
	)));

	$first = _render_tree_node(_test_msg_tree('my_account'), 0);
	pg_assert_same(false, strpos($first, 'Wrong code'), 'my account widget does not print it');
	pg_assert_true(isset($_SESSION['software']['liveforms']['my_account_profile']), 'and does not use it up');

	$second = _render_tree_node(_test_msg_tree('my_account_profile'), 0);
	pg_assert_contains('Wrong code', $second, 'security widget prints it');
	pg_assert_false(isset($_SESSION['software']['liveforms']['my_account_profile']), 'and uses it up');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

// The comments widget's form carries its Messages node first: a refused
// comment's error prints there and the add_comment state is used up, while
// another form's error waits for its own widget.
function test_designer_messages_comments_form_uses_add_comment()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('software' => array('liveforms' => array(
		'add_comment' => array(12 => array('message' => array('error' => true, 'error_message' => 'A comment is required.'))),
		'my_account_profile' => array(0 => array('code' => array('error' => true, 'error_message' => 'Wrong code'))),
	)));

	$tree = pg_comments_default_tree();
	_pg_inject_messages_node($tree, 'add_comment');
	$html = _render_tree_node($tree, 0);

	pg_assert_contains('A comment is required.', $html, 'the comment form prints its error');
	pg_assert_true(strpos($html, 'A comment is required.') > strpos($html, '<form'), 'inside the form');
	pg_assert_same(false, strpos($html, 'Wrong code'), 'not another form\'s');
	pg_assert_false(isset($_SESSION['software']['liveforms']['add_comment']), 'the add_comment state is used up');
	pg_assert_true(isset($_SESSION['software']['liveforms']['my_account_profile']), 'the other is left');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

// The comments widget's small forms post to handlers of their own: the
// Messages node in the watch form is re-stamped add_or_remove_watcher, the
// one in the open-or-close form allow_or_disallow_new_comments. Each prints
// only its own form's messages and uses them up; the comment form keeps
// add_comment's.
function test_designer_messages_comments_small_forms_restamped()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('software' => array('liveforms' => array(
		'add_comment' => array(12 => array('message' => array('error' => true, 'error_message' => 'A comment is required.'))),
		'add_or_remove_watcher' => array(12 => array('notices' => array('You will now be notified when a comment is added.'))),
		'allow_or_disallow_new_comments' => array(12 => array('notices' => array('New comments are closed.'))),
	)));

	$tree = pg_comments_default_tree();
	_pg_inject_messages_node($tree, 'add_comment');
	pg_comments_stamp_form_messages($tree);
	$html = _render_tree_node($tree, 0);

	$error = strpos($html, 'A comment is required.');
	$notice = strpos($html, 'You will now be notified when a comment is added.');
	$submit = strpos($html, 'Add comment</button>');
	pg_assert_true(($error !== false) && ($error > strpos($html, '<form')) && ($submit !== false) && ($error < $submit), 'the comment form prints its error');
	pg_assert_same(1, substr_count($html, 'You will now be notified when a comment is added.'), 'the watcher notice prints once');
	pg_assert_true(($notice !== false) && ($notice > $submit), 'in the watch form, after the comment form');
	pg_assert_false(isset($_SESSION['software']['liveforms']['add_comment']), 'the add_comment state is used up');
	pg_assert_false(isset($_SESSION['software']['liveforms']['add_or_remove_watcher']), 'the watcher state is used up');
	pg_assert_same(false, strpos($html, 'New comments are closed.'), 'the open-or-close form of the starter shows no messages');
	pg_assert_true(isset($_SESSION['software']['liveforms']['allow_or_disallow_new_comments']), 'and leaves them');

	$allow = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'form', '_bindings' => array('action' => 'comment_allow_new_form')), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
				array('type' => 'content', 'props' => array('contentType' => 'messages', 'cssClass' => ''), 'children' => array()),
			)),
		)),
		array('type' => 'content', 'props' => array('contentType' => 'messages', 'cssClass' => ''), 'children' => array()),
	));
	_pg_inject_messages_node($allow, 'add_comment');
	pg_comments_stamp_form_messages($allow);
	pg_assert_same('allow_or_disallow_new_comments', $allow['children'][0]['children'][0]['children'][0]['props']['formName'], 'a node deep in the form is stamped');
	pg_assert_same('add_comment', $allow['children'][1]['props']['formName'], 'one outside keeps add_comment');

	$allow_html = _render_tree_node($allow, 0);
	pg_assert_same(1, substr_count($allow_html, 'New comments are closed.'), 'printed by its own form');
	pg_assert_false(isset($_SESSION['software']['liveforms']['allow_or_disallow_new_comments']), 'and used up');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

// The formName of every Messages node of a tree, depth first.
function _test_msg_form_names($node, &$out)
{
	if (!is_array($node)) {
		return;
	}
	if (isset($node['type'], $node['props']['contentType']) && ($node['type'] === 'content') && ($node['props']['contentType'] === 'messages')) {
		$out[] = isset($node['props']['formName']) ? (string) $node['props']['formName'] : '';
	}
	foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
		_test_msg_form_names($child, $out);
	}
}

// A design that deleted the comment form's Messages node but kept the watch
// form's: _pg_inject_messages_node() finds that one and adds none, and the
// re-stamping hands it to the watch form. The widget still gets a node for
// add_comment, at the top of its container where _pg_inject_messages_node()
// puts one into a tree that has none, so a refused comment's error prints.
function test_designer_messages_comments_keeps_an_add_comment_node()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('software' => array('liveforms' => array(
		'add_comment' => array(12 => array('message' => array('error' => true, 'error_message' => 'A comment is required.'))),
		'add_or_remove_watcher' => array(12 => array('notices' => array('You will now be notified when a comment is added.'))),
	)));

	$tree = pg_comments_default_tree();
	pg_assert_same('messages', $tree['children'][0]['children'][3]['children'][4]['children'][0]['props']['contentType'], 'the starter form opens with its Messages node');
	array_shift($tree['children'][0]['children'][3]['children'][4]['children']);

	_pg_inject_messages_node($tree, 'add_comment');
	pg_comments_stamp_form_messages($tree);

	$names = array();
	_test_msg_form_names($tree, $names);
	pg_assert_same(array('add_comment', 'add_or_remove_watcher'), $names, 'one node for the comment form, one for the watch form');
	pg_assert_same('add_comment', $tree['children'][0]['children'][0]['props']['formName'], 'at the top of the container');

	$html = _render_tree_node($tree, 0);
	pg_assert_same(1, substr_count($html, 'A comment is required.'), 'the refused comment\'s error prints');
	pg_assert_same(1, substr_count($html, 'You will now be notified when a comment is added.'), 'the watcher notice prints once');
	pg_assert_false(isset($_SESSION['software']['liveforms']['add_comment']), 'the add_comment state is used up');

	// A tree whose first child is no container gets the node first.
	$bare = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'form', '_bindings' => array('action' => 'comment_watch_form')), 'children' => array(
			array('type' => 'content', 'props' => array('contentType' => 'messages', 'cssClass' => ''), 'children' => array()),
		)),
	));
	_pg_inject_messages_node($bare, 'add_comment');
	pg_comments_stamp_form_messages($bare);
	pg_assert_same('add_comment', $bare['children'][0]['props']['formName'], 'first in the root');
	pg_assert_same('add_or_remove_watcher', $bare['children'][1]['children'][0]['props']['formName'], 'the watch form keeps its own');

	// The default layout keeps its two nodes and gets no third.
	$starter = pg_comments_default_tree();
	_pg_inject_messages_node($starter, 'add_comment');
	pg_comments_stamp_form_messages($starter);
	$names = array();
	_test_msg_form_names($starter, $names);
	pg_assert_same(array('add_comment', 'add_or_remove_watcher'), $names, 'the default layout: its own two');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

// A list of recent comments drawn before a topic's thread: its Messages node
// is named 'comments_recent', which nothing posts to, so a refused comment's
// error stays for the thread drawn after it, which prints it and uses it up.
function test_designer_messages_recent_comments_leave_add_comment()
{
	if (!function_exists('pg_comments_recent_default_tree')) {
		pg_assert_true(false, 'pg_comments_recent_default_tree() exists');
		return;
	}

	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('software' => array('liveforms' => array(
		'add_comment' => array(12 => array('message' => array('error' => true, 'error_message' => 'A comment is required.'))),
	)));

	$list = pg_comments_recent_default_tree();
	$names = array();
	_test_msg_form_names($list, $names);
	pg_assert_same(array(), $names, 'the default list has no Messages node');

	// One an older layout carries is named for the list, not left unnamed.
	$list['children'][0]['children'][] = array('type' => 'content', 'props' => array('contentType' => 'messages', 'cssClass' => ''), 'children' => array());
	_pg_inject_messages_node($list, 'comments_recent');
	$names = array();
	_test_msg_form_names($list, $names);
	pg_assert_same(array('comments_recent'), $names, 'stamped comments_recent');

	$first = _render_tree_node($list, 0);
	pg_assert_same(false, strpos($first, 'A comment is required.'), 'the list prints no comment error');
	pg_assert_true(isset($_SESSION['software']['liveforms']['add_comment']), 'and leaves it');

	$thread = pg_comments_default_tree();
	_pg_inject_messages_node($thread, 'add_comment');
	pg_assert_contains('A comment is required.', _render_tree_node($thread, 0), 'the thread drawn after it prints it');
	pg_assert_false(isset($_SESSION['software']['liveforms']['add_comment']), 'and uses it up');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

// Every system widget renderer names its Messages node, and none with an
// empty name.
function test_designer_messages_every_renderer_names_its_node()
{
	$files = glob(PG_FUNCTIONS_DIR . '/includes/fn/widgets*.php');
	pg_assert_true(count($files) > 0, 'widget modules found');

	$renderers = 0;
	foreach ($files as $file) {
		$code = file_get_contents($file);
		$parts = preg_split('/^function\s+/m', $code);
		foreach ($parts as $part) {
			if (!preg_match('/^(_render_system_widget_\w+)\s*\(/', $part, $m)) {
				continue;
			}
			$renderers++;
			$name = $m[1];
			pg_assert_true(strpos($part, '_pg_inject_messages_node(') !== false, $name . ' names its Messages node');
			pg_assert_same(0, preg_match('/_pg_inject_messages_node\(\s*\$\w+\s*,\s*\'\'\s*\)/', $part), $name . ' gives it a name');
		}
	}
	pg_assert_true($renderers >= 25, 'renderers found: ' . $renderers);
}
