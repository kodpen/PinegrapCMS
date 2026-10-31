<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source-reading test for the command palette's style results: the search
 * endpoint (includes/panel/search.php) sends the style's type, limited to
 * 'system' and 'custom', and the palette (assets/js/backend.src.js) opens
 * edit_<type>_style.php?id= like view_styles.php, or the style list when
 * the type is missing. It once linked page_designer.php?style_id=, a screen
 * that read no such parameter and is now gone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_panel_search_style_result_carries_its_type()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/search.php');
	pg_assert_contains('style_type AS style_type', $php, 'the query reads the style type');
	pg_assert_contains("in_array(\$r['style_type'], array('system', 'custom'), true)", $php, 'only system and custom pass');
	pg_assert_contains("array('style_type' => \$style_type)", $php, 'the type rides the extra argument');
}

function test_panel_palette_style_link_opens_the_edit_screen()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	pg_assert_false(strpos($js, 'page_designer.php?style_id=') !== false, 'no page_designer.php?style_id= link');
	pg_assert_contains("'/edit_' + r.style_type + '_style.php?id=' + r.id", $js, 'the edit screen of its type');
	pg_assert_contains("'/view_styles.php'", $js, 'the style list without a type');
}
