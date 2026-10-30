<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the barcode scanner listeners on the stock increase and
 * decrease screens and on Create Order: keys typed into a field (the header
 * search, the quick note, a settings dialog) are not collected as a scan, a
 * scan is dropped while a dialog is open, and a blank scan is never sent.
 * A unit test cannot type on the screen, so these pin the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The options object, as the browser gets it, passed to $(document).scannerDetection() / jQuery(document).scannerDetection().
function barcode_scanner_fields_options($file)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	// The stock screens print their script from a single-quoted PHP string.
	if (substr($file, -4) === '.php') {
		$source = str_replace("\\'", "'", $source);
	}
	$start = strpos($source, '(document).scannerDetection({');
	pg_assert_true($start !== false, $file . ' binds the scanner on the document');
	$end = strpos($source, '});', $start);
	pg_assert_true($end !== false, $file . ' closes the scanner options');
	return substr($source, $start, $end - $start);
}

function barcode_scanner_fields_ignore($options, $file)
{
	pg_assert_true(
		strpos($options, "ignoreIfFocusOn: 'input, textarea, select, [contenteditable=\"true\"], [contenteditable=\"\"]'") !== false,
		$file . ' ignores keys typed into fields'
	);
}

// The stock screens: fields are ignored, an open dialog or a blank code
// returns before anything is written or posted.
function test_barcode_scanner_fields_stock_screens()
{
	foreach (array('barcode_increase_inventory.php', 'barcode_decrease_inventory.php') as $file) {
		$options = barcode_scanner_fields_options($file);
		barcode_scanner_fields_ignore($options, $file);
		$guard = strpos($options, "if ((window.pgUI && pgUI.anyOpen('dialog')) || String(barcode).trim() === '') {");
		pg_assert_true($guard !== false, $file . ' drops a scan in a dialog or a blank one');
		$post = strpos($options, 'request_barcode(barcode,quantity);');
		pg_assert_true($post !== false && $guard < $post, $file . ' checks before it posts');
		pg_assert_true($guard < strpos($options, '$("input#scanner_input").val(barcode);'), $file . ' checks before it writes Last Scan');
	}
}

// Create Order: fields ignored, dialog check kept, a blank code is not added.
function test_barcode_scanner_fields_create_order()
{
	$options = barcode_scanner_fields_options('assets/js/add_order.js');
	barcode_scanner_fields_ignore($options, 'add_order.js');
	$guard = strpos($options, "if (pgUI.anyOpen('dialog') || String(code).trim() === '') {");
	pg_assert_true($guard !== false, 'add_order.js drops a scan in a dialog or a blank one');
	pg_assert_true($guard < strpos($options, 'addItem('), 'add_order.js checks before it adds');
}
