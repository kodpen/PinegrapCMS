<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the stacking level of the menus that drop out of a site's
 * account bar (includes/fn/widgets_account.php, widgets_language.php): the
 * login region's account menu and the language switcher's list come down
 * over a sticky header (Bootstrap 5's .sticky-top, 1020) placed under the
 * bar, which hid their first rows while they had Bootstrap's 1000.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_header_menu_level_sits_between_sticky_and_fixed()
{
	$z = pg_sw_header_menu_z_index();
	pg_assert_true($z > 1020, 'above .sticky-top (1020)');
	pg_assert_true($z < 1030, 'below .fixed-top (1030), the offcanvas and the modal');
}

function test_lift_header_menus_marks_every_dropdown_menu()
{
	$tree = array('tag' => 'div', 'props' => array('cssClass' => 'd-flex'), 'children' => array(
		array('tag' => 'div', 'props' => array('cssClass' => 'dropdown'), 'children' => array(
			array('tag' => 'a', 'props' => array('cssClass' => 'dropdown-toggle'), 'children' => array()),
			array('tag' => 'ul', 'props' => array('cssClass' => 'dropdown-menu dropdown-menu-end shadow-sm'), 'children' => array(
				array('tag' => 'li', 'props' => array('cssClass' => ''), 'children' => array()),
			)),
		)),
		array('tag' => 'ul', 'props' => array('cssClass' => 'dropdown-menu', 'inlineStyle' => 'min-width: 14rem;'), 'children' => array()),
		array('tag' => 'ul', 'props' => array('cssClass' => 'dropdown-menu', 'inlineStyle' => 'z-index: 2000'), 'children' => array()),
		array('tag' => 'ul', 'props' => array('cssClass' => 'dropdown-menu-like'), 'children' => array()),
	));
	pg_sw_lift_header_menus($tree);
	$z = 'z-index:' . pg_sw_header_menu_z_index();

	pg_assert_same($z, $tree['children'][0]['children'][1]['props']['inlineStyle'], 'a nested menu gets the level');
	pg_assert_false(isset($tree['children'][0]['children'][0]['props']['inlineStyle']), 'its toggle is left alone');
	pg_assert_false(isset($tree['props']['inlineStyle']), 'the wrapper is left alone');
	pg_assert_same('min-width: 14rem;' . $z, $tree['children'][1]['props']['inlineStyle'], 'added after the node\'s own style');
	pg_assert_same('z-index: 2000', $tree['children'][2]['props']['inlineStyle'], 'a level the design set itself is kept');
	pg_assert_false(isset($tree['children'][3]['props']['inlineStyle']), 'only the whole class name counts');

	$again = $tree;
	pg_sw_lift_header_menus($again);
	pg_assert_same($tree, $again, 'a second pass changes nothing');
}

function test_login_region_and_language_switcher_use_the_level()
{
	$account = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_account.php');
	pg_assert_contains("_pg_member_drop_empty_links(\$tree, \$links);\n    // The account menu comes down over a sticky header that follows the bar.\n    pg_sw_lift_header_menus(\$tree);", $account, 'the login region lifts its menus before drawing');

	$rows = array(
		array('code' => 'tr', 'label' => 'Türkçe', 'url' => '/tr/', 'current' => true),
		array('code' => 'en', 'label' => 'English', 'url' => '/en/', 'current' => false),
	);
	$options = array('display' => 'label', 'variant' => 'link', 'outline' => false, 'size' => 'sm', 'icon' => 'none', 'attrs' => array(), 'class' => '', 'style' => '', 'align' => 'end');
	$html = pg_language_switcher_html($options, $rows);
	pg_assert_contains('<ul class="dropdown-menu dropdown-menu-end" style="z-index:' . pg_sw_header_menu_z_index() . '"', $html, 'the language list carries the level');
}
