<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product suggestions of the ERP invoice and waybill line
 * editors: the list leaves the lines table (its .table-responsive wrapper
 * scrolls and contains layout, so it clipped the list) for the overlay host
 * while it is open, is placed fixed beside the search box with a width and a
 * height limit, and is reachable from the keyboard. The placement is worked
 * out by resultsPlacement() in assets/js/erp_invoice_editor.js, run here
 * under Node; the wiring is pinned in the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_product_results_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/erp_invoice_editor.js');
}

// The body of resultsPlacement(), cut from the editor script.
function erp_product_results_test_placement_function()
{
	$source = erp_product_results_test_source();

	if (!preg_match('/\n    function resultsPlacement\(.*?\n    }\n/s', $source, $match)) {
		return '';
	}

	return $match[0];
}

// Runs resultsPlacement() under Node over each case; null without Node.
function erp_product_results_test_place(array $cases)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgerp');

	if ($file === false) {
		return null;
	}

	$script = erp_product_results_test_placement_function() . "\n"
		. 'var cases = ' . json_encode($cases) . ";\n"
		. 'console.log(JSON.stringify(cases.map(function (c) { return resultsPlacement(c.rect, c.width, c.height); })));' . "\n";
	file_put_contents($file, $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$json = json_decode(implode("\n", $out), true);

	return is_array($json) ? $json : array();
}

function test_erp_product_results_placement_function_exists()
{
	pg_assert_true(erp_product_results_test_placement_function() !== '', 'the editor script has resultsPlacement()');
}

function test_erp_product_results_open_below_the_box_with_a_height_limit()
{
	$count = 1;
	$result = erp_product_results_test_place(array(
		array('rect' => array('left' => 100, 'top' => 200, 'bottom' => 231, 'width' => 400), 'width' => 1280, 'height' => 900),
	));

	if ($result === null) {
		return;
	}

	pg_assert_true(count($result) === $count, 'resultsPlacement() ran under Node');

	if (count($result) !== $count) {
		return;
	}

	pg_assert_same(100, $result[0]['left'], 'aligned with the box');
	pg_assert_same(233, $result[0]['top'], 'just under the box');
	pg_assert_same(400, $result[0]['width'], 'as wide as the box');
	pg_assert_same(320, $result[0]['maxHeight'], 'at most 320px high');
	pg_assert_same(false, $result[0]['above'], 'below');
}

function test_erp_product_results_open_above_near_the_bottom_edge()
{
	$count = 1;
	$result = erp_product_results_test_place(array(
		array('rect' => array('left' => 100, 'top' => 800, 'bottom' => 831, 'width' => 400), 'width' => 1280, 'height' => 900),
	));

	if ($result === null) {
		return;
	}

	pg_assert_true(count($result) === $count, 'resultsPlacement() ran under Node');

	if (count($result) !== $count) {
		return;
	}

	pg_assert_same(true, $result[0]['above'], 'above the box when the room below is short');
	pg_assert_same(102, $result[0]['bottom'], 'its bottom edge just over the box');
	pg_assert_same(320, $result[0]['maxHeight'], 'at most 320px high');
}

function test_erp_product_results_narrow_box_and_phone_width()
{
	$count = 2;
	$result = erp_product_results_test_place(array(
		array('rect' => array('left' => 300, 'top' => 100, 'bottom' => 131, 'width' => 140), 'width' => 1280, 'height' => 900),
		array('rect' => array('left' => 200, 'top' => 100, 'bottom' => 131, 'width' => 160), 'width' => 320, 'height' => 640),
	));

	if ($result === null) {
		return;
	}

	pg_assert_true(count($result) === $count, 'resultsPlacement() ran under Node');

	if (count($result) !== $count) {
		return;
	}

	pg_assert_same(320, $result[0]['width'], 'a narrow box still gets a readable list');
	pg_assert_same(304, $result[1]['width'], 'never wider than the viewport less its margins');
	pg_assert_same(8, $result[1]['left'], 'moved left to stay on screen');
}

function test_erp_product_results_menu_moves_to_the_overlay_host()
{
	$source = erp_product_results_test_source();

	pg_assert_true(strpos($source, 'pgUI.overlayHost(input)') !== false, 'the open list goes to the overlay host');
	pg_assert_true(strpos($source, "position = 'fixed'") !== false, 'placed fixed');
	pg_assert_true(strpos($source, '--bs-menu-max-height') !== false && strpos($source, '--bs-menu-overflow-y') !== false, 'scrolls inside its height limit');
	pg_assert_true(strpos($source, "aria-expanded") !== false && strpos($source, "aria-controls") !== false, 'the search box says the list is open and which it is');
	pg_assert_true(strpos($source, "'ArrowDown'") !== false, 'ArrowDown reaches the results');
}

function test_erp_product_results_menus_have_ids()
{
	foreach (array('includes/erp/invoice_form.php', 'includes/erp/waybill_form.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_true(strpos($source, '<div class="menu shadow-sm" id="\' . h($id(\'product_results\')) . \'" data-erp-product-results') !== false, $file . ': the results menu has an id');
	}
}
