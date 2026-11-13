<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for editing a card on the designer canvas: a picked element whose
 * wrapper is see-through (a card's body, a title or a row inside a card, an
 * item of a flex row) shows its toolbar, put at the element by the chrome
 * placement; only an element that is not selected keeps its toolbar hidden
 * there. The selection's toolbar spot is measured again after the fidelity
 * pass, so a card's cover does not keep a spot taken before it had a height.
 * Read from assets/js/style_designer.src.js.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_canvas_card_edit_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The selected wrapper's toolbar is never hidden inside a card or a flex row.
function test_designer_canvas_card_edit_selected_toolbar_shows()
{
	$source = designer_canvas_card_edit_test_source();
	foreach (array('.card', '.card-body', '.card-header', '.card-footer', '.d-flex', '.d-inline-flex', '.d-md-flex') as $parent) {
		pg_assert_true(strpos($source, "'" . $parent . ' > .sd-wrap > .sd-tb') === false && strpos($source, ' ' . $parent . ' > .sd-wrap > .sd-tb') === false,
			'a selected wrapper in ' . $parent . ' keeps its toolbar');
		pg_assert_true(strpos($source, $parent . ' > .sd-wrap:not(.sd-selected) > .sd-tb') !== false,
			'a wrapper in ' . $parent . ' that is not selected hides its toolbar');
	}
}

// The selection's toolbar spot is measured after the fidelity pass.
function test_designer_canvas_card_edit_spot_after_pass()
{
	$source = designer_canvas_card_edit_test_source();
	$from = strpos($source, '    function _sdPlaceSelectedChrome(');
	pg_assert_true($from !== false, 'the designer has _sdPlaceSelectedChrome()');
	$to = strpos($source, "\n    function ", $from + 10);
	$body = substr($source, $from, $to - $from);
	$flip = strpos($body, '_sdTbFlipAll(');
	$place = strpos($body, '_sdPlaceChromeOf(');
	pg_assert_true($flip !== false && $place !== false && $flip < $place,
		'the selection\'s spot is measured before its chrome is placed');
}
