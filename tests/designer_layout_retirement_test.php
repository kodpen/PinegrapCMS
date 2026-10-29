<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for reaching a classic page's custom layout once the classic page
 * designer is gone: the toolbar's style designer button (Ctrl+G) asks the
 * visual designer to open on the page's layout tab, for the layout editor's
 * role and never on a hosted site, and the designer opens that tab only for
 * the page it lands on when that page draws a custom layout; a page not on
 * a visual design opens the layout editor screen instead.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

// Ctrl+G adds the parameter for a page with a custom layout, for designers
// and administrators, not on a hosted site.
function test_layout_retirement_tab_param()
{
	pg_assert_same('&layout=1', pg_layout_tab_param(7, array(3, 7), 0, false));
	pg_assert_same('&layout=1', pg_layout_tab_param('7', array('7'), 1, false), 'ids as strings');
	pg_assert_same('', pg_layout_tab_param(7, array(3), 1, false), 'no custom layout');
	pg_assert_same('', pg_layout_tab_param(7, array(7), 2, false), 'a manager has no layout tab');
	pg_assert_same('', pg_layout_tab_param(7, array(7), 1, true), 'hosted: the canvas');
	pg_assert_same('', pg_layout_tab_param(0, array(0), 0, false), 'no page');
}

// "Edit Layout" on the form screen: a page whose own style is a visual
// design opens the designer on that page, on its layout tab where the role
// and site allow it; a page not on a visual design opens the layout-only
// editor screen.
function test_layout_retirement_edit_layout_url()
{
	pg_assert_same('edit_system_style.php?id=4&page=7&layout=1', pg_layout_edit_url(7, 4, true, 1, false));
	pg_assert_same('edit_system_style.php?id=4&page=7&layout=1', pg_layout_edit_url('7', '4', true, 0, false), 'ids as strings');
	pg_assert_same('edit_system_style.php?id=4&page=7', pg_layout_edit_url(7, 4, true, 1, true), 'hosted: the canvas');
	pg_assert_same('edit_system_style.php?id=4&page=7', pg_layout_edit_url(7, 4, true, 2, false), 'a manager: the canvas');
	pg_assert_same('edit_page_layout.php?id=7', pg_layout_edit_url(7, 4, false, 1, false), 'classic style');
	pg_assert_same('edit_page_layout.php?id=7', pg_layout_edit_url(7, 0, true, 1, false), 'no own style');
	pg_assert_same('edit_page_layout.php?id=7', pg_layout_edit_url('7', 4, false, 1, true), 'classic style, hosted: read only there');
}

// The designer opens the tab only for the page it lands on.
function test_layout_retirement_open_page_id()
{
	pg_assert_same(7, pg_layout_open_page_id('1', 7, array(3, 7)));
	pg_assert_same(0, pg_layout_open_page_id('', 7, array(7)), 'not asked');
	pg_assert_same(0, pg_layout_open_page_id(null, 7, array(7)), 'not asked');
	pg_assert_same(0, pg_layout_open_page_id('1', 7, array(3)), 'no custom layout');
	pg_assert_same(0, pg_layout_open_page_id('1', 0, array(0)), 'no page');
	pg_assert_same(0, pg_layout_open_page_id(array('1'), 7, array(7)), 'not a scalar');
}

// The button, the screen flag and the editor's opener are wired together.
function test_layout_retirement_wiring()
{
	$output = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
	pg_assert_true(strpos($output, 'pg_layout_tab_param(') !== false, 'the Ctrl+G button asks for the tab');

	$fields = file_get_contents(PG_FUNCTIONS_DIR . '/view_fields.php');
	pg_assert_true(strpos($fields, 'pg_layout_edit_url(') !== false, 'Edit Layout asks for its target');
	pg_assert_true(strpos($fields, 'pg_page_is_visual_design(') !== false, 'Edit Layout checks the page style');

	$properties = file_get_contents(PG_FUNCTIONS_DIR . '/edit_page.php');
	pg_assert_true(strpos($properties, 'pg_layout_edit_url(') !== false, 'page properties: Edit Layout asks for its target');
	pg_assert_true(strpos($properties, 'page_designer.php?url=') === false, 'page properties: no hand-built page designer link');

	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, "'openLayout'") !== false, 'the screen passes the page');
	pg_assert_true(strpos($screen, 'pg_layout_open_page_id(') !== false);

	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($js, 'design.openLayout') !== false, 'the editor opens the tab');
}

// The layout editor screen of a page not on a visual design: the designer's
// gate, a page with a custom layout only, a designed page sent on to the
// designer, the text read and saved through layout_code, read only on a
// hosted site.
function test_layout_retirement_editor_screen()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/edit_page_layout.php');
	pg_assert_true(strpos($src, "validate_area_access(\$user, 'designer');") !== false, 'roles 0-1');
	pg_assert_true(strpos($src, "\$page['layout_type'] !== 'custom'") !== false, 'a custom layout only');
	pg_assert_true(strpos($src, 'check_if_page_type_supports_layout(') !== false, 'a page type with a layout only');
	pg_assert_true(strpos($src, 'pg_layout_edit_url(') !== false, 'a designed page goes to the designer');
	pg_assert_true(strpos($src, 'action: "layout_code"') !== false, 'the designer\'s action');
	pg_assert_true(strpos($src, "\$read_only = pg_hosted();") !== false, 'read only on a hosted site');
	pg_assert_true(strpos($src, 'pg_code_editor_attach(') !== false, 'the code editor bundle');
	pg_assert_true(strpos($src, 'pg_layout_save(') === false, 'no save path of its own');

	$help = file_get_contents(PG_FUNCTIONS_DIR . '/get_help_url.php');
	pg_assert_true(strpos($help, "'edit_page_layout'") !== false, 'help row');
}
