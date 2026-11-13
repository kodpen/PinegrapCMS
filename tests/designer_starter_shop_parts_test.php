<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two shop starters of the designer (_buildStarterTree(), "Load
 * Default Layout", also what the design templates' 'starter' widgets get):
 * the catalog listing's product card kept its three buttons on one line, so
 * between 576 and 991px Add to Cart stuck out of every card and scrolled the
 * page sideways at 768px; the cart link's count badge sat on the button's
 * top corner, half above it, and was cut by the top of the page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The text of one `case '<kind>':` block of _buildStarterTree().
function _test_starter_case($kind)
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$start = strpos($source, "            case '" . $kind . "': {");
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\n            case '", $start + 10);
	return substr($source, $start, ($end === false) ? 4000 : $end - $start);
}

// The product card's action row wraps, and Add to Cart keeps its label on
// one line.
function test_catalog_listing_starter_buttons_wrap()
{
	$case = _test_starter_case('catalog_listing');

	pg_assert_contains("cssClass: 'd-flex flex-wrap gap-2 mt-2', customName: _sdT('Action buttons')", $case, 'action row wraps');
	pg_assert_true((bool) preg_match("/cssClass: 'text-nowrap',\\s*text: _sdT\\('Add to Cart'\\)/", $case), 'Add to Cart label on one line');
}

// The cart link's count sits inside the button, not on its top corner.
function test_cart_link_starter_badge_inside_button()
{
	$case = _test_starter_case('cart_link');

	pg_assert_true($case !== '', 'cart_link starter found');
	pg_assert_true(strpos($case, "'position-absolute top-0 start-100 translate-middle badge") === false, 'no corner badge');
	pg_assert_contains("cssClass: 'badge rounded-pill text-bg-danger',\n                            _bindings: { text: '__cart_count'", $case, 'count badge in the button');
}
