<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of tools/memory_index.php: the module field of a
 * memory file's frontmatter, the size a memory file is measured by and the
 * generated list of includes/fn/ modules.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The tool returns before its command line part when PG_TEST_RUNNER is set.
require_once(dirname(__DIR__) . '/tools/memory_index.php');

function test_memory_index_module_names_parsed()
{
	pg_assert_same(array('comments'), memory_index_module_names('comments', $error));
	pg_assert_same('', $error);
	pg_assert_same(array('privacy', 'audit'), memory_index_module_names('privacy, audit', $error));
	pg_assert_same(array('a11y', 'widgets_lists_menu'), memory_index_module_names(' a11y ,widgets_lists_menu,a11y', $error), 'a name given twice is kept once');
}

function test_memory_index_module_names_refused()
{
	pg_assert_same(null, memory_index_module_names('', $error), 'an empty field');
	pg_assert_true($error !== '');
	pg_assert_same(null, memory_index_module_names('comments,', $error), 'an empty name');
	pg_assert_same(null, memory_index_module_names('comments.php', $error), 'a file name, not a module name');
	pg_assert_same(null, memory_index_module_names('fn/comments', $error), 'a path');
	pg_assert_same(null, memory_index_module_names('Comments', $error), 'module files are lower case');
	pg_assert_same(null, memory_index_module_names('[[comments]]', $error));
}

// The module line is index metadata: a file at the size limit can still carry
// it, and a line of the same text in the body still counts.
function test_memory_index_counted_bytes_leave_out_module_line()
{
	$head = "---\nname: a\ndescription: b\n";
	$body = "---\n\n## Q?\n- A.\n";

	pg_assert_same(strlen($head . $body), memory_index_counted_bytes($head . $body));
	pg_assert_same(strlen($head . $body), memory_index_counted_bytes($head . "module: comments, audit\n" . $body));
	pg_assert_same(strlen($head . $body) + 17, memory_index_counted_bytes($head . $body . "module: comments\n"));
	pg_assert_same(5, memory_index_counted_bytes("plain"));
}

function test_memory_index_module_list_sorted()
{
	$list = memory_index_module_list(array(
		'widget-lists' => array('widgets_lists'),
		'comment-replies' => array('comments'),
		'comment-thread-rules' => array('comments'),
		'comments-widget-pages' => array('widgets_comments', 'comments'),
		'accessibility-report' => array('a11y'),
	));

	pg_assert_true(strncmp($list, "---\nname: fn-modules\ndescription: ", 34) === 0, 'starts with its own frontmatter');
	pg_assert_contains("\n- `a11y`: [[accessibility-report]]\n- `comments`: [[comment-replies]], [[comment-thread-rules]], [[comments-widget-pages]]\n- `widgets_comments`: [[comments-widget-pages]]\n- `widgets_lists`: [[widget-lists]]\n", $list);
	pg_assert_contains('[[fn-module-map]]', $list);
	pg_assert_same("\n", substr($list, -1));
	pg_assert_true(substr($list, -2) !== "\n\n", 'one newline at the end');
}

function test_memory_index_module_list_same_for_any_input_order()
{
	$a = memory_index_module_list(array('b' => array('y', 'x'), 'a' => array('x')));
	$b = memory_index_module_list(array('a' => array('x'), 'b' => array('x', 'y')));

	pg_assert_same($a, $b);
	pg_assert_contains("- `x`: [[a]], [[b]]\n- `y`: [[b]]\n", $a);
}

function test_memory_index_module_list_empty()
{
	pg_assert_contains('No memory file names a module yet.', memory_index_module_list(array()));
}
