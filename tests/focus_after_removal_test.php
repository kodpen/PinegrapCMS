<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a panel control which removes, hides or replaces the focused
 * element hands focus to a neighbour instead of dropping it to the page top:
 * ERP invoice and waybill lines, product bundle rows, the contact photo, the
 * API applications result sheet and its delete confirm, and the Modify
 * Selected price method select, which keeps focus on itself.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function focus_after_removal_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_focus_after_removal_erp_line()
{
	$js = focus_after_removal_source('assets/js/erp_invoice_editor.js');

	pg_assert_contains('var neighbour = list[list.indexOf(row) + 1] || list[list.indexOf(row) - 1];', $js, 'a removed line picks the next line, else the previous one');
	pg_assert_contains("var target = neighbour ? field(neighbour, 'product_name') : null;", $js, 'the neighbour is read before the row goes');
	pg_assert_contains("row.parentNode.removeChild(row);\n            if (target) {\n                target.focus();", $js, 'focus moves to the neighbour\'s product name');
}

function test_focus_after_removal_bundle_row()
{
	$js = focus_after_removal_source('assets/js/product_bundle.js');

	pg_assert_contains("var next = row.nextElementSibling ? row.nextElementSibling.querySelector('input[name=\"bundle_item_quantity[]\"]') : null;", $js, 'the next row\'s quantity is read before the row goes');
	pg_assert_contains('(next || search).focus();', $js, 'focus moves to the next quantity, else the search box');
}

function test_focus_after_removal_contact_photo()
{
	foreach (array('add_contact.php', 'edit_contact.php') as $file) {
		$php = focus_after_removal_source($file);

		pg_assert_contains('photos.addEventListener("click", function (event) {', $php, $file . ' listens on the photo container');
		pg_assert_contains('}, true);', $php, $file . ' listens in the capture phase, before the inline remove');
		pg_assert_contains('photos.closest(".card").querySelector(".card-footer button").focus();', $php, $file . ' focuses Change Image');
	}
}

function test_focus_after_removal_api_sheet()
{
	$php = focus_after_removal_source('api_settings.php');

	// Step 3 is the New application drawer, modal by itself; closing it
	// takes the values out and focuses New application.
	pg_assert_contains('var focus = (number === 3) ? document.querySelector("#n_step_3 button, #n_done")', $php, 'step 3 takes focus');
	pg_assert_contains('if (document.getElementById("n_step_3") && !doneAck.checked) { e.preventDefault(); }', $php, 'a shown secret keeps the sheet open until it is copied');
	pg_assert_contains('var newButton = document.querySelector("[data-bs-target=\\"#api_new\\"]");', $php, 'Close focuses New application');
	pg_assert_contains('document.querySelector("#d_delete_confirm .pg-button-danger").focus();', $php, 'Delete focuses Yes, delete');
	pg_assert_contains("ask.classList.remove(\"d-none\");\n\t\task.focus();", $php, 'Cancel focuses Delete this application');
}

function test_focus_after_removal_price_method_keeps_focus()
{
	$php = focus_after_removal_source('includes/templates/edit_products.php');

	pg_assert_true(strpos($php, '$price_value_input.focus()') === false, 'changing the price method leaves focus on the select');
}
