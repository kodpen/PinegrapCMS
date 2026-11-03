<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The SEO card of the edit screens and the SEO Detail drawer of the list
 * screens: the two refresh buttons work on every screen that draws them,
 * the HTML pass also refreshes the internal links, every score indicator
 * of the record follows a refresh, the panel score reads its band ink, the
 * live counter measures what the score measures, and a missing page is not
 * logged as an access refusal.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function seo_card_refresh_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The body of a PHP function, from its declaration to the next one.
function seo_card_refresh_function($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 10);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// The product screens printed the buttons without their click handler.
function test_seo_card_product_screens_print_the_run_script()
{
	foreach (array('product_builder.php', 'edit_product_group.php', 'edit_page.php') as $file) {
		pg_assert_true(strpos(seo_card_refresh_source($file), 'pg_seo_render_run_script()') !== false,
			$file . ' prints the refresh buttons\' handler');
	}
}

// A single-record pass loaded the structure code but not the link code, so
// the link step was skipped silently.
function test_seo_card_record_pass_loads_the_link_code()
{
	$body = seo_card_refresh_function(seo_card_refresh_source('seo_structure.php'), 'pg_seo_analyze_record');

	pg_assert_true($body !== '', 'pg_seo_analyze_record() is found');
	pg_assert_true(strpos($body, "require_once(dirname(__FILE__) . '/seo_links.php');") !== false,
		'pg_seo_analyze_record() loads seo_links.php');
}

// The panel figure was written in the band fill (#fd7e14, 2.36:1).
function test_seo_card_panel_score_reads_the_band_ink()
{
	$body = seo_card_refresh_function(seo_card_refresh_source('seo.php'), 'pg_seo_render_checklist');

	pg_assert_true(strpos($body, "' style=\"color:' . \$color['style']") === false, 'the panel score has no fill colour');
	pg_assert_true(strpos($body, "' style=\"color:' . \$color['ink']") !== false, 'the panel score reads the band ink');
}

// A refresh replaced the checklist and left the card badge and the list row
// on the old score.
function test_seo_card_refresh_updates_every_score_of_the_record()
{
	$seo = seo_card_refresh_source('seo.php');
	$script = seo_card_refresh_function($seo, 'pg_seo_render_run_script');
	$analysis = seo_card_refresh_source('get_seo_analysis.php');

	pg_assert_true(strpos(seo_card_refresh_function($seo, 'pg_seo_render_checklist'), 'data-seo-record=') !== false,
		'the panel names its record');
	pg_assert_true(strpos($analysis, '<template data-seo-slot="badge">') !== false, 'the fragment carries the new badge');
	pg_assert_true(strpos($analysis, '<template data-seo-slot="bar">') !== false, 'the fragment carries the new bar');
	pg_assert_true(strpos($script, '[data-seo-slot]') !== false, 'the handler fills every slot of the record');

	foreach (array('edit_page.php', 'product_builder.php', 'edit_product_group.php', 'view_pages.php', 'includes/fn/product_list.php') as $file) {
		pg_assert_true(strpos(seo_card_refresh_source($file), 'pg_seo_slot_attributes(') !== false,
			$file . ' marks its score indicators');
	}
}

function test_seo_card_slot_attributes()
{
	require_once(PG_FUNCTIONS_DIR . '/seo.php');

	pg_assert_same(' data-seo-record="page:12" data-seo-slot="badge"', pg_seo_slot_attributes('page', 12, 'badge'));
	pg_assert_same(' data-seo-record="product_group:7" data-seo-slot="bar"', pg_seo_slot_attributes('product_group', '7', 'bar'));
}

// The counter counted surrounding spaces and UTF-16 units; the score trims
// and counts code points.
function test_seo_card_counter_measures_like_the_score()
{
	$source = seo_card_refresh_source('assets/js/backend.src.js');
	$start = strpos($source, 'function renderSeoCounter(');
	$body = substr($source, $start, 3000);

	pg_assert_true(strpos($body, 'var len = $input.val().length') === false, 'the counter does not count the raw value');
	pg_assert_true(strpos($body, 'Array.from(') !== false, 'the counter counts code points');
	pg_assert_true(strpos($body, '/^[ \\t\\n\\r\\0\\x0B]+|[ \\t\\n\\r\\0\\x0B]+$/g') !== false, 'the counter trims what PHP trim() trims');
}

// A page id that names no page answered "Access denied." and logged a refusal.
function test_seo_card_missing_page_is_not_a_refusal()
{
	$source = seo_card_refresh_source('get_seo_analysis.php');

	pg_assert_true(strpos($source, "(\$seo_page_folder === NULL) || !check_edit_access(") === false,
		'a missing page is not handled as a refusal');
	pg_assert_true(preg_match('/if \(\$seo_page_folder === NULL\) \{\s*exit\(lang\(\'Sorry, we could not find that record\.\'\)\);/', $source) === 1,
		'a missing page answers not found');
}
