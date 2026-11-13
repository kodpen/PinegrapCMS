<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a round picture stays round on the designer canvas: a bound
 * picture's SVG stand-in takes the node's own box and brings no size of its
 * own, and every round picture of the editor's starters has equal sides (or
 * a size class), so a photo that is not square is drawn as a circle on the
 * canvas and on the published page alike.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_canvas_round_picture_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// No canvas rule gives the SVG stand-in of a bound picture a minimum or a
// fixed size: a 120x60 floor drew the 40x40 author photo of the Google
// reviews card and the 64x64 member photo of a team card as ovals.
function test_designer_canvas_picture_stand_in_has_no_size_of_its_own()
{
	$source = designer_canvas_round_picture_test_source();

	pg_assert_same(0, preg_match_all('/img\[src\^="data:image\/svg\+xml"\][^\']*\{[^}]*(?:min-|max-)?(?:width|height|aspect-ratio)\s*:/', $source, $m),
		'a canvas rule sizes the picture stand-in: ' . implode(' | ', isset($m[0]) ? $m[0] : array()));
}

// Every round image node of the editor's starters has equal width and height
// attributes or a size class with equal sides (pg-circle-*): without one the
// picture takes a photo's own shape, an oval when the photo is not square.
function test_designer_starter_round_pictures_have_equal_sides()
{
	$source = designer_canvas_round_picture_test_source();
	$found = preg_match_all("/createNode\\('content', \\{ contentType: 'image'[^}]*?cssClass: '[^']*\\brounded-circle\\b[^}]*\\}/", $source, $matches);

	pg_assert_true($found >= 5, 'the starters have round pictures');

	foreach ($matches[0] as $node) {
		if (preg_match("/cssClass: '[^']*\\bpg-circle-\\d+/", $node)) {
			continue;
		}
		$w = preg_match("/\\bwidth: '(\\d+)'/", $node, $wm) ? $wm[1] : '';
		$h = preg_match("/\\bheight: '(\\d+)'/", $node, $hm) ? $hm[1] : '';
		pg_assert_true($w !== '' && $w === $h, 'a round starter picture has equal sides: ' . $node);
	}

	// The Google reviews author photo: equal sides and a covering fit.
	pg_assert_contains("width: '40', height: '40',\n                                                    cssClass: 'rounded-circle flex-shrink-0 object-fit-cover',", $source);
}
