<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the customer signature drawn on the quote screen
 * (edit_erp_quote.php, action sign): the form carries the hash of the quote
 * it showed and the signature is refused when the quote was changed since,
 * as the guest link refuses it (erp_quote_link_shown_matches()), so the
 * customer never signs lines or totals they were not shown.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_quote_panel_sign_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_quote.php');
}

function test_erp_quote_panel_sign_form_carries_the_shown_quote()
{
	$source = erp_quote_panel_sign_test_source();
	$form = strpos($source, '<input type="hidden" name="erp_action" value="sign" />');

	pg_assert_true($form !== false, 'the sign form is drawn');
	pg_assert_contains('name="d" value="\' . h(erp_signature_document_hash_quote($quote))', substr($source, (int) $form, 400), 'with the hash of the quote it shows');
}

function test_erp_quote_panel_sign_refuses_a_quote_changed_since_it_was_shown()
{
	$source = erp_quote_panel_sign_test_source();
	$action = strpos($source, "if (\$action === 'sign') {");
	$check = strpos($source, "erp_quote_link_shown_matches((string) (\$_POST['d'] ?? ''), \$quote)");
	$store = strpos($source, "erp_signature_store('quote'");

	pg_assert_true(($action !== false) && ($check !== false) && ($check > $action), 'the sign action compares what was shown');
	pg_assert_true(($store !== false) && ($store > $check), 'before the signature is kept');
	pg_assert_contains("'d', 'signer_name'", $source, 'the posted hash is one value');
}
