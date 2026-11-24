<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the scripts a block brings when it is drawn after the page's
 * script passes: a common region made a shared component and a designed
 * pop-up. pg_block_scripts_needed() tells whether such markup holds a block
 * that brings a script, pg_cf_rich_text_inject() adds its editor once, and
 * get_page_content.php runs the passes again after both.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_block_scripts_needed_markers()
{
	$blocks = array(
		'<button data-pg-theme-switcher="toggle">'          => 'a theme switcher',
		'<div class="row" data-pg-scroller="item">'         => 'a sideways row',
		'<div data-pg-listen="1">'                          => 'a Listen block',
		'<div data-pg-map="{}">'                            => 'a map of places',
		'<div data-pg-course="lesson" hidden>'              => 'a course block',
		'<div data-pg-push="block" hidden>'                 => 'a Notify me block',
		'<div data-pg-fx="hover">'                           => 'an interaction',
		'<span data-bs-toggle="tooltip" title="Hi">'        => 'a tooltip',
		'<a data-bs-toggle="tab" href="#a">'                => 'tabs',
		'<button data-pg-ai-summarize>'                     => 'an in-browser summary',
		'<button data-pg-wishlist="12">'                    => 'a favourite heart',
		'<div data-pg-booking="{}">'                        => 'a booking widget',
		'<textarea id="a" data-pg-rich-text></textarea>'    => 'a rich-text field',
	);
	foreach ($blocks as $html => $what) {
		pg_assert_true(pg_block_scripts_needed($html), $what);
	}
	pg_assert_false(pg_block_scripts_needed('<div class="row"><p>Hello</p></div>'), 'plain markup');
	pg_assert_false(pg_block_scripts_needed(''), 'nothing drawn');
	pg_assert_false(pg_block_scripts_needed('<input type="date" name="d">'), 'a date field is the date control\'s pass');
	pg_assert_false(pg_block_scripts_needed('<a href="/x" data-pg-page-link="4">x</a>'), 'a page link brings no script');
}

function test_rich_text_editor_added_once()
{
	$page = '<html><body><textarea id="note" data-pg-rich-text></textarea>' . pg_cf_rich_text_marker() . '<script>editor()</script></body></html>';
	pg_assert_same($page, pg_cf_rich_text_inject($page), 'a page that has the editor gets no second one');
}

function test_block_scripts_run_after_late_drawing()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');

	$first   = strpos($src, '$content = $pg_block_scripts($content);');
	$regions = strpos($src, '$component_html = pg_common_region_component_html($component_id, $mode, $email);');
	$popups  = strpos($src, '$output_js = $auto_dialog_templates . \'<script>\' . $output_js . \'</script>\';');
	pg_assert_true($first !== false && $regions !== false && $popups !== false, 'the passes, the common region pass and the pop-up templates');

	$again_regions = strpos($src, '$content = $pg_block_scripts($content);', $regions);
	pg_assert_true($first < $regions && $again_regions !== false, 'again after the common region pass');
	pg_assert_true(strpos($src, 'pg_block_scripts_needed($auto_dialog_templates)', $popups) !== false, 'again after the pop-up templates');
}
