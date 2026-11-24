<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a visitor on a page in another site language who pays or sends
 * a form: the payment service sends them back to an address that carries
 * the language (pg_tr_script_url()), so the order is completed, its receipt
 * mailed and its receipt page shown in that language; and the subject of
 * the e-mail a form sends its sender is offered for translation and read in
 * that language.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/translate.php');

function test_multi_language_payment_return_address_carries_language()
{
	pg_assert_same('/software/order_preview.php?mode=iyzico_return&page_id=7&pg_lang=en',
		pg_tr_script_url('/software/order_preview.php?mode=iyzico_return&page_id=7', 'en'));
	pg_assert_same('/software/booking_slots.php?mode=stripe_return',
		pg_tr_script_url('/software/booking_slots.php?mode=stripe_return', ''), 'the source language adds nothing');
}

// Every return address handed to a payment service goes through
// pg_tr_script_url(): none is built on a bare PATH . SOFTWARE_DIRECTORY.
function test_multi_language_payment_return_addresses_use_the_helper()
{
	$files = array(
		'submit_order.php' => 2,
		'includes/fn/form_payment_step.php' => 1,
		'includes/fn/booking_deposit_pay.php' => 1,
	);

	foreach ($files as $file => $count) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_same(0, preg_match_all("~HOSTNAME \\. PATH \\. SOFTWARE_DIRECTORY \\. '/[^']*\\.php\\?mode=~", $source), $file . ': no return address without the language');
		pg_assert_same($count, preg_match_all("~HOSTNAME \\. pg_tr_script_url\\(PATH \\. SOFTWARE_DIRECTORY~", $source), $file . ': the return addresses through pg_tr_script_url()');
	}

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	pg_assert_true(strpos($source, "\$paypal_express_checkout_cancelurl = URL_SCHEME . HOSTNAME . (defined('LANGUAGE_PATH') ? LANGUAGE_PATH : PATH) . get_page_name(") !== false,
		'PayPal Express Checkout cancels back to the order page in its language');
}

function test_multi_language_form_sender_subject_is_translated()
{
	$extract = file_get_contents(PG_FUNCTIONS_DIR . '/includes/translate/content.php');
	pg_assert_true(strpos($extract, "pg_tr_field_segments('submitter_email_subject', (string) \$row['submitter_email_subject'], \$position)") !== false,
		'the subject is offered for translation with the form');

	$mails = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/form_mails.php');
	$translated = strpos($mails, "\$submitter_email_subject = (string) pg_tr_text(\$submitter_email_subject);");
	$filled = strpos($mails, "\$subject = pg_cf_fill_mail_links(\$submitter_email_subject,");
	pg_assert_true(($translated !== false) && ($filled !== false) && ($translated < $filled),
		'the stored subject is translated before the field values go in');
}

// PayTR's own return pages (after its payment frame) are this site's pages
// in the language the order was placed in.
function test_multi_language_paytr_return_pages_in_language()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/payments/paytr.php');
	pg_assert_true(strpos($source, "\$base = URL_SCHEME . HOSTNAME . (defined('LANGUAGE_PATH') ? LANGUAGE_PATH : PATH);") !== false,
		'ok_url and fail_url under the language directory');
}
