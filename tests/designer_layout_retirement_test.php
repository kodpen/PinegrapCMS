<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for reaching a classic page's custom layout once the classic page
 * designer is gone: the toolbar's style designer button (Ctrl+G) asks the
 * visual designer to open on the page's layout tab, for the layout editor's
 * role and never on a hosted site, and the designer opens that tab only for
 * the page it lands on when that page draws a custom layout.
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

	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, "'openLayout'") !== false, 'the screen passes the page');
	pg_assert_true(strpos($screen, 'pg_layout_open_page_id(') !== false);

	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_true(strpos($js, 'design.openLayout') !== false, 'the editor opens the tab');
}
