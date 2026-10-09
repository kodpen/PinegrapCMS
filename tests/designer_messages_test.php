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
