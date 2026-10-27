<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Make designable" on a classic menu, menu sequence, ad or tag
 * cloud region node (SD_REGION_WIDGETS in style_designer.js): each region
 * type maps to a registered widget kind and to the setting its renderer
 * reads the region's name from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_region_widget_convert_map()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_true((bool) preg_match('/var SD_REGION_WIDGETS = \{(.*?)\};/s', $js, $m), 'the map is there');
	preg_match_all("/(\w+):\s*\{ kind: '(\w+)',\s*key: '(\w+)' \}/", $m[1], $rows, PREG_SET_ORDER);
	pg_assert_same(array('menu', 'menu_sequence', 'ad', 'tag_cloud'), array_column($rows, 1), 'the four data-driven regions');

	$labels = pg_sw_type_labels();
	$renderers = array(
		'menu_region'          => '/includes/fn/widgets_menu.php',
		'menu_sequence_region' => '/includes/fn/widgets_menu.php',
		'ad_region'            => '/includes/fn/widgets_ad_region.php',
		'tag_cloud_region'     => '/includes/fn/widgets_tag_cloud.php',
	);
	foreach ($rows as $row) {
		pg_assert_true(isset($labels[$row[2]]), $row[1] . ': a registered kind');
		$src = (string) file_get_contents(PG_FUNCTIONS_DIR . $renderers[$row[2]]);
		pg_assert_contains("\$cfg['" . $row[3] . "']", $src, $row[1] . ': the renderer reads ' . $row[3]);
	}
	pg_assert_contains("_sdRegionWidgetPropsHtml(n) +", $js, 'offered in the Region panel');
}
