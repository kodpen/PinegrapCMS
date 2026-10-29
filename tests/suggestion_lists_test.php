<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the search-as-you-type lists the screens build themselves (ERP
 * account contact search, product bundle search, Create Order, ERP lines)
 * can be used from the keyboard and say when they find nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function suggestion_lists_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_suggestion_lists_erp_contact_search()
{
	$php = suggestion_lists_source('includes/erp/account_form.php');

	pg_assert_contains('id="contact_search" class="form-control" maxlength="100" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="erp_contact_results" aria-expanded="false"', $php, 'the contact box is a combobox naming its list');
	pg_assert_contains('<div class="menu shadow-sm w-100" id="erp_contact_results" data-erp-contact-results></div>', $php, 'the result list has the id the box names');
	pg_assert_contains('<div class="visually-hidden" role="status" aria-live="polite" aria-atomic="true" data-erp-contact-status></div>', $php, 'the card has a polite status line');
	pg_assert_contains('input.setAttribute("aria-expanded", "false");', $php, 'closing the list says so');
	pg_assert_contains('input.setAttribute("aria-expanded", "true");', $php, 'opening the list says so');
	pg_assert_contains('if (event.key === "ArrowDown") { event.preventDefault(); var first = items()[0]; if (first) { first.focus(); } }', $php, 'ArrowDown in the box moves into the results');
	pg_assert_contains('menu.addEventListener("keydown", function (event) {', $php, 'the results listen to the keyboard');
	pg_assert_contains('if (event.key === "Escape") { event.preventDefault(); close(); input.focus(); }', $php, 'Escape on a result closes the list and returns to the box');
	pg_assert_contains('announce(texts.noResults);', $php, 'an empty answer is announced');
	pg_assert_contains("close();\n                input.focus();\n                announce(contact.name + \". \" + texts.pending);", $php, 'after a pick focus goes back to the box and the pick is announced');
}

function test_suggestion_lists_product_bundle()
{
	$js = suggestion_lists_source('assets/js/product_bundle.js');

	pg_assert_contains("status.setAttribute('role', 'status');", $js, 'the script creates a status line');
	pg_assert_contains("search.setAttribute('aria-controls', results.id);", $js, 'the search box names its list');
	pg_assert_contains("document.querySelector('script[data-pg-no-results-label]')", $js, 'the empty text is the translated one from the fields script tag');
	pg_assert_contains("if (!results.querySelector('button')) {", $js, 'an answer with nothing to pick says so');
	pg_assert_contains('.catch(function () {', $js, 'a failed request is not silent');
	pg_assert_false(strpos($js, '.catch(clearResults);') !== false, 'a failed request no longer just empties the list');
	pg_assert_contains("announce(productLabel(product) + ', ' + quantityWord() + ': ' + quantity.value);", $js, 'raising a quantity is announced');
	pg_assert_contains("if (event.key === 'Escape') {", $js, 'Escape closes the list');
	pg_assert_contains("results.addEventListener('keydown', function (event) {", $js, 'the arrows move through the results');
}

function test_suggestion_lists_create_order()
{
	$js = suggestion_lists_source('assets/js/add_order.js');

	pg_assert_contains('announce(t.noProductsEnter);', $js, 'no products is announced');
	pg_assert_contains('announce(t.noCustomers);', $js, 'no customers is announced');
	pg_assert_same(2, substr_count($js, 'announce(t.searchFailed);'), 'both failed searches are announced');
}

function test_suggestion_lists_erp_lines()
{
	$js = suggestion_lists_source('assets/js/erp_invoice_editor.js');

	pg_assert_contains("liveStatus.setAttribute('aria-live', 'polite');", $js, 'the editor creates a polite status line');
	pg_assert_contains('announce(textNoResults);', $js, 'no products is announced');
}
