<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The SEO detail panel and the SEO card of the edit screens: the HTML check
 * resolves the record's links, the buttons work on the product screens, the
 * live counter measures like the score, the panel's score reads in the light
 * theme, a refresh updates every score on the screen, a missing page is not
 * an access denial, and the Turkish check lines read correctly.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function seo_panel_run_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The body of a top-level PHP function, up to the next one.
function seo_panel_run_function($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

// One record's HTML check runs the link step too: without seo_links.php the
// function_exists() guard in the page and catalog passes skipped it silently.
function test_seo_analyze_record_loads_the_link_step()
{
	$body = seo_panel_run_function(seo_panel_run_source('seo_structure.php'), 'pg_seo_analyze_record');

	pg_assert_contains("require_once(dirname(__FILE__) . '/seo_links.php');", $body, 'seo_links.php loaded');
	pg_assert_true(strpos($body, 'seo_links.php') < strpos($body, 'pg_seo_analyze_page('), 'loaded before the pass');
}

// The product and product group screens print the buttons' click handler.
function test_seo_run_script_on_the_product_screens()
{
	foreach (array('edit_product_group.php', 'product_builder.php', 'edit_page.php') as $path) {
		pg_assert_contains('pg_seo_render_run_script()', seo_panel_run_source($path), $path . ' prints the handler');
	}
}

// The live counter measures the trimmed value in code points, as the score
// does with trim() and mb_strlen().
function test_seo_counter_measures_like_the_score()
{
	$source = seo_panel_run_source('assets/js/backend.src.js');
	$start = strpos($source, 'function renderSeoCounter(');
	$body = substr($source, $start, 2500);

	pg_assert_false(strpos($body, 'var len = $input.val().length') !== false, 'not the raw UTF-16 length');
	pg_assert_contains('pgSeoMeasuredLength($input.val())', $body, 'the shared measure');
	pg_assert_contains('function pgSeoMeasuredLength(', $source, 'measure defined');
	pg_assert_contains('pgSeoMeasuredLength', seo_panel_run_source('assets/js/backend.min.js'), 'bundle rebuilt');
}

// The panel's large score uses the band's text shade, not the bar fill.
function test_seo_checklist_score_uses_the_ink_shade()
{
	$body = seo_panel_run_function(seo_panel_run_source('seo.php'), 'pg_seo_render_checklist');

	pg_assert_contains("\$text_style = (\$color['ink'] !== '') ? ' style=\"color:' . \$color['ink'] . '\"' : '';", $body, 'ink');
	pg_assert_false(strpos($body, "' style=\"color:' . \$color['style']") !== false, 'no fill colour on the text');
}

// A refresh hands back the badge and the bar and the handler puts them in
// every place that shows the record's score.
function test_seo_refresh_updates_every_indicator()
{
	$seo = seo_panel_run_source('seo.php');
	$script = seo_panel_run_function($seo, 'pg_seo_render_run_script');
	$fresh = seo_panel_run_function($seo, 'pg_seo_render_fresh_indicators');

	pg_assert_contains('data-pg-seo-fresh="badge"', $fresh, 'badge template');
	pg_assert_contains('data-pg-seo-fresh="bar"', $fresh, 'bar template');
	pg_assert_contains('template[data-pg-seo-fresh]', $script, 'handler reads the templates');
	pg_assert_contains('pg_seo_render_fresh_indicators($record, $type, $id)', seo_panel_run_source('get_seo_analysis.php'), 'fragment sends them');

	pg_assert_contains("data-pg-seo-badge=\"page:' . (int) \$page_id . '\"", seo_panel_run_source('edit_page.php'), 'Edit Page badge');
	pg_assert_contains("data-pg-seo-badge=\"product_group:' . (int) \$_GET['id'] . '\"", seo_panel_run_source('edit_product_group.php'), 'group badge');
	pg_assert_contains("data-pg-seo-badge=\"product:' . (int) \$seo_record_id . '\"", seo_panel_run_source('product_builder.php'), 'product badge');

	// The lists' SEO column carries the bar under the record's key.
	pg_assert_contains("pg_list_table_seo_cell('page', \$page['page_id'], \$page)", seo_panel_run_source('includes/fn/page_list.php'), 'pages list bar');
	pg_assert_contains("pg_list_table_seo_cell('product', \$id, \$product)", seo_panel_run_source('includes/fn/product_list.php'), 'products list bar');
	pg_assert_contains('data-pg-seo-bar="\' . h($key) . \'"', seo_panel_run_function(seo_panel_run_source('includes/fn/list_table.php'), 'pg_list_table_seo_cell'), 'the cell names the key');
}

// The template markup is built from the same badge and bar renderers.
function test_seo_fresh_indicators_markup()
{
	$html = pg_seo_render_fresh_indicators(array('seo_score' => 0, 'seo_analysis' => ''), 'page', 12);

	pg_assert_contains('<template data-pg-seo-fresh="badge" data-pg-seo-key="page:12">', $html, 'badge key');
	pg_assert_contains('<template data-pg-seo-fresh="bar" data-pg-seo-key="page:12">', $html, 'bar key');
	pg_assert_same('', pg_seo_render_fresh_indicators(array(), 'other', 12), 'unknown type');
}

// A page id that does not exist answers like a missing product and logs nothing.
function test_seo_analysis_missing_page_is_not_found()
{
	$source = seo_panel_run_source('get_seo_analysis.php');
	$start = strpos($source, "if (\$type == 'page') {");
	$block = substr($source, $start, strpos($source, '} else {', $start) - $start);

	pg_assert_contains("if (\$seo_page_folder === NULL) {\n        exit(lang('Sorry, we could not find that record.'));", $block, 'not found first');
	pg_assert_false(strpos($block, '($seo_page_folder === NULL) ||') !== false, 'not joined to the access check');
	pg_assert_contains('if (!check_edit_access($seo_page_folder)) {', $block, 'access check kept');
}

// Turkish lines of the checklist.
function test_seo_checklist_turkish_lines()
{
	$tr = array();

	foreach (array('n', 'o', 'p', 't') as $shard) {
		$tr += (array) json_decode(seo_panel_run_source('includes/local/tr/' . $shard . '.json'), true);
	}

	pg_assert_same('Başlık bir dolgu değeri', $tr['Title is a filler value'], 'filler');
	pg_assert_same('Başlık bir dolgu değeri değil', $tr['Title is not a filler value'], 'not filler');
	pg_assert_same('Öne çıkarılan anahtar kelimeler: {var:1} terim', $tr['Promoted keywords: {var:1} terms'], 'keywords');
	pg_assert_same('Kullanımdan kalkmış biçim etiketleri var', $tr['Obsolete presentational tags are used'], 'tags');
	pg_assert_contains('Öne çıkarılan anahtar kelime yok', $tr['No promoted keywords: add 3-8 to surface this record in site search and in the tag cloud'], 'no keywords');
}
