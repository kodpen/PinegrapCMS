<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the hero component of the visual designer
 * (_render_component_html(), includes/fn/designer.php): a style typed into
 * the Attributes panel is escaped where it joins the hero's own background,
 * like every other component's.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_designer_hero_user_style_is_escaped()
{
	$attrs = array(array('name' => 'style', 'value' => 'color:red" onmouseover="alert(1)'));
	$html = _render_tree_node(array('type' => 'component', 'props' => array('componentType' => 'hero', 'bgColor' => '#000', '_attrs' => $attrs), 'children' => array()), 0, 0);

	pg_assert_contains('style="background:#000;color:red&quot; onmouseover=&quot;alert(1)"', $html);
	pg_assert_false(strpos($html, '" onmouseover="') !== false, 'no attribute of its own');
}
