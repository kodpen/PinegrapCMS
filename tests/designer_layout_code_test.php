<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a classic page's custom layout as a code tab of the visual
 * designer: the file name built from the page id alone, the white-space
 * blind comparison with the generated layout that keeps a page unmodified,
 * and the layout_code endpoint, which asks for the classic layout editor's
 * right (role 1 or better) and a form token before it reads the page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

// A file under data/layouts/ is named by the page id and nothing else.
function test_layout_code_file_name()
{
	pg_assert_same('12.php', pg_layout_file_name(12));
	pg_assert_same('12.php', pg_layout_file_name('12'));
	pg_assert_same('', pg_layout_file_name(0));
	pg_assert_same('', pg_layout_file_name('-3'));
	pg_assert_same('', pg_layout_file_name('12abc'));
	pg_assert_same('', pg_layout_file_name('../12'), 'no path');
	pg_assert_same('', pg_layout_file_name('12/../../x'), 'no path');
	pg_assert_same('', pg_layout_file_name(array(12)), 'not a scalar');
	pg_assert_same('', pg_layout_file_name(null));
	pg_assert_same('', pg_layout_file_name(1.5));
}

// Saved unchanged, white space aside, a layout stays generated.
function test_layout_code_same_as_generated()
{
	$generated = "<div class=\"x\">\n    <?=\$field?>\n</div>\n";

	pg_assert_true(pg_layout_same_as_generated($generated, $generated));
	pg_assert_true(pg_layout_same_as_generated(str_replace("\n", "\r\n", $generated), $generated), 'line endings');
	pg_assert_true(pg_layout_same_as_generated("<div class=\"x\"><?=\$field?></div>", $generated), 'indentation');
	pg_assert_false(pg_layout_same_as_generated("<div class=\"y\">\n    <?=\$field?>\n</div>\n", $generated));
	pg_assert_false(pg_layout_same_as_generated('', $generated));
}

function test_layout_code_action_row()
{
	$actions = pg_panel_actions();

	pg_assert_true(isset($actions['layout_code']), 'the action is listed');

	$entry = $actions['layout_code'];
	pg_assert_same('designer.php', $entry['file']);
	pg_assert_same('pg_panel_layout_code', $entry['handler']);
	pg_assert_false($entry['exempt'], 'the general gate runs first');
	pg_assert_true($entry['token']);
	pg_assert_true($entry['write']);
	pg_assert_false(!empty($entry['any_role']), 'no role 2 or 3 request reaches it');

	// The classic layout editor's actions keep the same gate.
	pg_assert_false($actions['get_layout']['exempt']);
	pg_assert_false($actions['update_layout']['exempt']);
}

function test_layout_code_handler_checks()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');

	$function = new ReflectionFunction('pg_panel_layout_code');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	$token = strpos($source, 'validate_token();');
	$role = strpos($source, "if ((int) \$user['role'] > 1)");
	$query = strpos($source, 'FROM page');
	$custom = strpos($source, "\$page['layout_type'] !== 'custom'");
	$save = strpos($source, 'pg_layout_save(');

	pg_assert_true($token !== false, 'the token is checked');
	pg_assert_true($role !== false, 'designers and administrators only');
	pg_assert_true(($token < $role) && ($role < $query), 'both before the table is touched');
	pg_assert_true(($custom !== false) && ($custom < $save), 'only a page drawing a custom layout');

	$classic = (string) file_get_contents(PG_FUNCTIONS_DIR . '/page_designer.php');
	pg_assert_contains("validate_area_access(\$user, 'designer');", $classic, 'the classic layout editor keeps the same right');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("'layoutCode'    => ((int)\$user['role'] <= 1)", $screen, 'the editor offers the tab to the same role');
	pg_assert_contains("'layoutReadOnly' => pg_hosted()", $screen, 'read only on a hosted site');
}

// The save writes only through pg_layout_file_name() and refuses a hosted
// site before anything else.
function test_layout_code_save_path()
{
	$function = new ReflectionFunction('pg_layout_save');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	$hosted = strpos($source, 'pg_hosted()');
	$name = strpos($source, 'pg_layout_file_name(');

	pg_assert_true(($hosted !== false) && ($hosted < $name), 'hosted refused first');
	pg_assert_same(1, substr_count($source, "LAYOUT_DIRECTORY_PATH . '/' . \$file"), 'one path, from the file name');
	pg_assert_false(strpos($source, "\$request") !== false, 'no request value in the path');
}

// A save may change what a visitor sees; a read or a regenerate does not.
function test_layout_code_page_cache_sub_actions()
{
	$sub_actions = pg_page_cache_panel_sub_actions();

	pg_assert_true(isset($sub_actions['layout_code']));
	pg_assert_same(array('save'), $sub_actions['layout_code']['dirty']);
	pg_assert_same(array('read', 'regenerate'), $sub_actions['layout_code']['clean']);
}
