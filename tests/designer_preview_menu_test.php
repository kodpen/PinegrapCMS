<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visual designer's Preview menu beside Publish (includes/
 * designer_screen.php, assets/js/style_designer.src.js): which rows it shows
 * for the page on screen (_sdPreviewMenuRows()), and that the canvas bar and
 * the page toolbar no longer carry Preview, Open on Site and View Page as
 * buttons of their own. The pure function runs under Node; skipped where
 * there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_preview_menu_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// One function of style_designer.js, from its declaration to its closing
// brace.
function designer_preview_menu_fn($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

function test_designer_preview_menu_rows_by_page_state()
{
	$fn = designer_preview_menu_fn(designer_preview_menu_source(), '_sdPreviewMenuRows');

	if (!pg_assert_true($fn !== '', 'style_designer.js has _sdPreviewMenuRows()')) {
		return;
	}

	if (!function_exists('exec')) {
		return;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$cases = array(
		// A page not on the site yet, no prototype, no share links.
		array(),
		array('live' => false, 'play' => false, 'share' => false),
		// A page on the site.
		array('live' => true, 'play' => false, 'share' => false),
		// A saved draft: the prototype plays and may be shared.
		array('live' => false, 'play' => true, 'share' => true),
		// Share links without a page the viewer can open yet.
		array('live' => false, 'play' => false, 'share' => true),
		array('live' => true, 'play' => true, 'share' => true),
	);
	$file = tempnam(sys_get_temp_dir(), 'pgpvm');
	file_put_contents($file, $fn . "\n"
		. 'var cases = ' . json_encode($cases) . ';' . "\n"
		. 'console.log(JSON.stringify(cases.map(function (c) { return _sdPreviewMenuRows(c); })));' . "\n");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);

	if (!pg_assert_true(is_array($res), 'the rows come back as a list')) {
		return;
	}

	pg_assert_same(array('sd-vb-preview'), $res[0], 'Preview in a new window is always offered');
	pg_assert_same(array('sd-vb-preview'), $res[1], 'a page not on the site has no Open on the site');
	pg_assert_same(array('sd-vb-preview', 'sd-vb-live'), $res[2], 'a page on the site opens there as saved');
	pg_assert_same(array('sd-vb-preview', 'sd-vb-play', 'sd-vb-share'), $res[3], 'a saved draft plays and shares the prototype');
	pg_assert_same(array('sd-vb-preview', 'sd-vb-share'), $res[4], 'sharing does not wait for a page to play');
	pg_assert_same(array('sd-vb-preview', 'sd-vb-live', 'sd-vb-play', 'sd-vb-share'), $res[5], 'the rows keep the drawing\'s order');
}

function test_designer_preview_menu_replaces_the_bar_buttons()
{
	$js = designer_preview_menu_source();
	$bar = designer_preview_menu_fn($js, 'createCanvasIframe');

	pg_assert_true($bar !== '', 'style_designer.js has createCanvasIframe()');
	pg_assert_true(strpos($bar, 'id="sd-vb-preview"') === false, 'the canvas bar has no Preview button of its own');
	pg_assert_true(strpos($bar, 'id="sd-vb-live"') === false, 'the canvas bar has no Open on Site button of its own');

	$menu = designer_preview_menu_fn($js, '_sdPreviewMenuBuild');

	foreach (array('sd-vb-preview', 'sd-vb-live', 'sd-vb-play', 'sd-vb-share') as $id) {
		pg_assert_true((bool) preg_match('/id="' . $id . '"[^>]* role="menuitem"/', $menu), '#' . $id . ' is a row of the Preview menu');
	}

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, 'id="sd-view-page"') === false, 'View Page leaves the toolbar: Open on the site is the same thing');
	$btn = strpos($screen, 'id="sd-preview-menu-btn"');
	$publish = strpos($screen, 'id="sd-ajax-save"');
	pg_assert_true($btn !== false && $publish !== false && $btn < $publish, 'the Preview menu button stands before Publish');
}
