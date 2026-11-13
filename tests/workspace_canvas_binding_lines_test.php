<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an application's binding line with Undo
 * (includes/workspace/canvas_binding_lines.php): the access codes, the
 * application's name, the words, the refs read back, when Undo still
 * applies, and the hooks of the panel.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_binding_lines.php');

function test_workspace_canvas_binding_access_codes()
{
	foreach (array('', 'read', 'write') as $access) {
		pg_assert_same($access, ws_canvas_binding_access_of(ws_canvas_binding_access_code($access)), 'round trip: ' . $access);
	}

	pg_assert_same(1, ws_canvas_binding_access_code(''), 'no binding is 1, never 0 (ws_refs skips 0)');
	pg_assert_same(1, ws_canvas_binding_access_code('admin'), 'an unknown access reads as none');
	pg_assert_same('', ws_canvas_binding_access_of(0));

	foreach (ws_canvas_binding_ref_types() as $type) {
		pg_assert_true(strlen($type) <= 20, 'fits ws_refs.ref_type: ' . $type);
	}
}

function test_workspace_canvas_binding_agent_name()
{
	$tags = array(7 => array('handle' => 'stock', 'name' => 'Stock bot'));

	pg_assert_same('@stock', ws_canvas_binding_agent_name(7, $tags, 'Stock app'), 'an agent by its handle');
	pg_assert_same('Sales sync', ws_canvas_binding_agent_name(8, $tags, 'Sales *sync*'), 'else the application, marks stripped');
	pg_assert_same('An application', ws_canvas_binding_agent_name(9, $tags, ''), 'a nameless one');
}

function test_workspace_canvas_binding_line_text()
{
	pg_assert_same('@stock bound Products to Sales board · reads', ws_canvas_binding_line_text('@stock', 'Products', 'Sales board', 'read'));
	pg_assert_same('@stock bound Orders to Sales board · reads, writes', ws_canvas_binding_line_text('@stock', 'Orders', 'Sales board', 'write'));
	pg_assert_same('@stock bound a table to Sales board · reads', ws_canvas_binding_line_text('@stock', '', 'Sales board', 'read'), 'a table the reader does not reach is not named');
}

function test_workspace_canvas_binding_lines_from_refs()
{
	$lines = ws_canvas_binding_lines_from_refs(array(
		array('source_id' => 5, 'ref_type' => 'canvas_bind', 'ref_id' => 40),
		array('source_id' => 5, 'ref_type' => 'canvas_bind_table', 'ref_id' => 3),
		array('source_id' => 5, 'ref_type' => 'canvas_bind_now', 'ref_id' => 3),
		array('source_id' => 5, 'ref_type' => 'canvas_bind_was', 'ref_id' => 2),
		array('source_id' => 5, 'ref_type' => 'canvas_bind_app', 'ref_id' => 7),
		array('source_id' => 6, 'ref_type' => 'canvas_bind', 'ref_id' => 41),
		array('source_id' => 6, 'ref_type' => 'task', 'ref_id' => 9),
	));

	pg_assert_same(array(5), array_keys($lines), 'only complete lines');
	pg_assert_same(array('canvas' => 40, 'table' => 3, 'now' => 'write', 'was' => 'read', 'app' => 7, 'undone' => 0), $lines[5]);
}

function test_workspace_canvas_binding_line_current()
{
	$line = array('canvas' => 40, 'table' => 3, 'now' => 'write', 'was' => '', 'app' => 7, 'undone' => 0);
	$binding = array('access' => 'write', 'bound_by_kind' => 'app', 'bound_by_id' => 7);

	pg_assert_true(ws_canvas_binding_line_current($line, $binding), 'as the application set it');
	pg_assert_true(!ws_canvas_binding_line_current($line, null), 'taken off since');
	pg_assert_true(!ws_canvas_binding_line_current($line, array('access' => 'read') + $binding), 'access changed since');
	pg_assert_true(!ws_canvas_binding_line_current($line, array('bound_by_kind' => 'user', 'bound_by_id' => 7) + $binding), 'a person bound it since');
	pg_assert_true(!ws_canvas_binding_line_current($line, array('bound_by_id' => 8) + $binding), 'another application bound it since');
	pg_assert_true(!ws_canvas_binding_line_current(array('undone' => 2) + $line, $binding), 'undone already');
}

function test_workspace_canvas_binding_hooks()
{
	$root = dirname(__DIR__) . '/pinegrap';
	$tables = file_get_contents($root . '/includes/workspace/canvas_tables.php');
	$js = file_get_contents($root . '/assets/js/workspace.js');

	pg_assert_true(strpos($tables, 'ws_canvas_binding_line_post(') !== false, 'an application\'s binding writes its line');
	pg_assert_true(strpos(file_get_contents($root . '/includes/workspace/messages.php'), "'canvas_binding' =>") !== false, 'the payload carries it');
	pg_assert_true(strpos(file_get_contents($root . '/includes/workspace/actions.php'), "case 'ws_canvas_binding_undo':") !== false, 'the Undo action');
	pg_assert_true(strpos($js, "api('ws_canvas_binding_undo'") !== false && strpos($js, 'message.canvas_binding.text') !== false, 'the line and Undo are drawn');
	pg_assert_true(strpos(file_get_contents($root . '/assets/js/workspace.min.js'), 'ws_canvas_binding_undo') !== false, 'the bundle is built');

	$strings = ws_canvas_binding_js_strings();
	pg_assert_true(isset($strings['page_bind_undo'], $strings['page_bind_undone_by']));
}
