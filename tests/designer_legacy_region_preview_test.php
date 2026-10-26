<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the canvas preview of a classic page's system region drawn by
 * its custom layout: the markers the page render puts around the page's own
 * system region while the flag is on, the cut that takes the region out of
 * a whole page, and the designer/legacy_region_preview endpoint, which asks
 * for the layout editor's right and draws in the background.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

function _legacy_region_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

function test_legacy_region_cut()
{
	$page = '<html><body><nav>menu</nav><div class="c">'
		. '<!--pg-layout-region-start--> <div class="software_catalog">items</div> <!--pg-layout-region-end-->'
		. '</div><!--pg-layout-region-start-->second<!--pg-layout-region-end--></body></html>';

	pg_assert_same('<div class="software_catalog">items</div>', pg_layout_region_cut($page), 'the first region, trimmed');
	pg_assert_same("a\nb", pg_layout_region_cut("<!--pg-layout-region-start-->a\nb<!--pg-layout-region-end-->"), 'across lines');
	pg_assert_same('', pg_layout_region_cut('<html><body>no region</body></html>'), 'a tree without the region');
	pg_assert_same('', pg_layout_region_cut('<!--pg-layout-region-start-->cut short'), 'no end marker');
	pg_assert_same('', pg_layout_region_cut(''));
}

// Off unless a preview turns it on, so no visitor's page carries a marker.
function test_legacy_region_marking_flag()
{
	pg_assert_false(pg_layout_region_marking());
	pg_assert_true(pg_layout_region_marking(true));
	pg_assert_true(pg_layout_region_marking());
	pg_assert_false(pg_layout_region_marking(false));
	pg_assert_false(pg_layout_region_marking());
}

// Only the page's own system region is marked, and only under the flag.
function test_legacy_region_render_marks_primary_region()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');

	pg_assert_contains(
		"if (\$primary_system_region && function_exists('pg_layout_region_marking') && pg_layout_region_marking()) {",
		$source
	);
	$mark = strpos($source, "'<!--pg-layout-region-start-->' . \$system_output . '<!--pg-layout-region-end-->'");
	$replace = strpos($source, "pg_regex_replacement(\$system_output), \$content);");
	pg_assert_true(($mark !== false) && ($replace !== false) && ($mark < $replace), 'marked before it is put in place');
}

// A background render whose borrowed request state always comes back.
function test_legacy_region_preview_background_render()
{
	$source = _legacy_region_source('pg_layout_region_preview');

	$gate = strpos($source, 'pg_layout_custom_page_ids(array($page_id))');
	$render = strpos($source, 'get_page_content(');
	pg_assert_true(($gate !== false) && ($gate < $render), 'only a page drawing a custom layout');
	pg_assert_contains('pg_seo_rendering(true);', $source);
	pg_assert_contains('pg_error_throws(true);', $source);
	pg_assert_contains("'preview'", $source, 'never edit mode');

	$finally = strpos($source, '} finally {');
	pg_assert_true($finally !== false);
	$tail = substr($source, $finally);
	foreach (array('ob_end_clean();', 'pg_layout_region_marking(false);', 'pg_seo_rendering($saved_seo);',
		'pg_error_throws($saved_throw);', '$_GET = $saved_get;') as $restore) {
		pg_assert_contains($restore, $tail, $restore);
	}

	pg_assert_same('', pg_layout_region_preview(0));
	pg_assert_same('', pg_layout_region_preview(-4));
}

// Designers and administrators, as the layout tab; a content-level operator
// never reaches it.
function test_legacy_region_preview_endpoint()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');

	$source = _legacy_region_source('pg_panel_designer');

	$content_ok = substr($source, strpos($source, '$content_ok = array('));
	$content_ok = substr($content_ok, 0, strpos($content_ok, ');'));
	pg_assert_false(strpos($content_ok, 'legacy_region_preview') !== false, 'not opened to content editors');

	$case = strpos($source, "case 'legacy_region_preview':");
	pg_assert_true($case !== false);
	$branch = substr($source, $case, strpos($source, 'break;', $case) - $case);
	$role = strpos($branch, "if ((int)\$user['role'] > 1)");
	$render = strpos($branch, 'pg_layout_region_preview(');
	pg_assert_true(($role !== false) && ($role < $render), 'the role before the render');
	pg_assert_true(strpos($source, 'validate_token();') < $case, 'the token is checked');

	$sub_actions = pg_page_cache_panel_sub_actions();
	pg_assert_true(in_array('legacy_region_preview', $sub_actions['designer']['clean'], true), 'a read keeps the page cache');
}

// The canvas draws it only for a page in the layout list, keeps it out of
// the tree, and drops what would run.
function test_legacy_region_preview_canvas()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');

	pg_assert_contains("if (node.props.regionType === 'system') _sdLegacyRegionAppend(doc, rg);", $js);

	$start = strpos($js, 'function _sdLegacyRegionAppend(');
	$body = substr($js, $start, strpos($js, "\n    }\n", $start) - $start);
	pg_assert_contains("(_design.layoutPages || []).indexOf(p.page_id) === -1", $body);
	pg_assert_contains("_pgApiPost('legacy_region_preview'", $body);
	pg_assert_contains("querySelectorAll('script')", $body);
	pg_assert_contains("a.name.slice(0, 2) === 'on'", $body);
	pg_assert_contains("box.setAttribute('inert', '');", $body);
	pg_assert_false(strpos($body, 'data-sd-id') !== false, 'never selectable');
}
