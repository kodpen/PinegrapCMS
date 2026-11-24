<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the scripts a common region made a shared component brings: the
 * block scripts' passes of get_page_content.php run again once the common
 * region pass or a designed pop-up drew a component, which happens after
 * their first run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _cr_scripts_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
}

function test_common_region_scripts_passes_in_one_closure()
{
	$src = _cr_scripts_source();
	$start = strpos($src, '$pg_block_scripts = function ($content, $rich_text = true)');
	$end = strpos($src, '$content = $pg_block_scripts($content);');
	pg_assert_true($start !== false && $end !== false && $start < $end, 'the passes are one closure, run once in place');
	$body = ($start !== false && $end !== false) ? substr($src, $start, $end - $start) : '';
	foreach (array('pg_theme_switcher_inject', 'pg_scroller_inject', 'pg_listen_inject', 'pg_place_list_inject',
		'pg_course_blocks_inject', 'pg_site_push_block_inject', 'pg_fx_inject', 'pg_bs_tips_inject',
		'pg_ai_summary_inject', 'pg_wishlist_inject', 'pg_booking_widget_inject', 'pg_cf_rich_text_inject') as $fn) {
		pg_assert_contains($fn . '(', $body, $fn . ' runs in both runs');
	}
	pg_assert_contains('&& $rich_text && strpos($content, \'data-pg-rich-text\')', $body, 'the rich-text editor is loaded once');
}

function test_common_region_scripts_second_run_after_components()
{
	$src = _cr_scripts_source();
	$drawn = strpos($src, '$component_html = pg_common_region_component_html($component_id, $mode, $email);' . "\n" . '                $pg_block_scripts_again = true;');
	pg_assert_true($drawn !== false, 'the common region pass marks a drawn component');

	$again = strpos($src, '$content = $pg_block_scripts($content, $pg_block_scripts_rich_text);');
	$popup = strrpos($src, '$output_js = $auto_dialog_templates . \'<script>\' . $output_js . \'</script>\';');
	pg_assert_true($again !== false && $popup !== false && $popup < $again && $drawn < $again, 'the second run follows the region pass and the pop-up templates');
	pg_assert_contains('if ($pg_block_scripts_again || (!$email && ($auto_dialog_templates !== \'\'))) {', $src, 'only a page that drew one runs it');
}

function test_common_region_scripts_passes_run_twice_without_doubling()
{
	// The passes the second run repeats add their file only once.
	$page = '<html><head></head><body><div data-pg-theme-switcher></div><div data-pg-scroller="item"></div></body></html>';
	$once = pg_scroller_inject(pg_theme_switcher_inject($page), '/pinegrap/assets/js/pg_scroller.js?v=1');
	pg_assert_same($once, pg_scroller_inject(pg_theme_switcher_inject($once), '/pinegrap/assets/js/pg_scroller.js?v=1'), 'a second run adds nothing');
}
