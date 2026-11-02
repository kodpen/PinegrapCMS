<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The classic order pages (the My Account order view, the order receipt and
 * the order preview) write their labels through lang(): the billing block's
 * Phone, Fax and PO Number, the shipping row's method and requested arrival
 * date, and in the order view the table headings, totals rows, payment
 * schedule, gift card labels and the Back button. Before, a Turkish shop
 * showed them in English.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source with its comments blanked out and every lang('...') key folded.
function order_pages_lang_code($file)
{
	$code = '';
	foreach (token_get_all((string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file)) as $token) {
		if (is_array($token)) {
			$code .= in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true) ? preg_replace('/[^\n]/', ' ', $token[1]) : $token[1];
		} else {
			$code .= $token;
		}
	}
	return preg_replace("/lang\\('[^']*'\\)/", 'lang()', $code);
}

// The English literals each page printed.
function order_pages_lang_literals()
{
	$shared = array("'Phone: '", "'Fax: '", "'PO Number: '", "'Shipping Method: '", "'; Requested Arrival Date: '");

	return array(
		'get_order_receipt.php' => $shared,
		'get_order_preview.php' => $shared,
		'get_view_order_screen_content.php' => array_merge($shared, array(
			'>Start Date:<', '>Payment Schedule<', '>Frequency:<', '>Number of Payments:<', '>Amount:<',
			'>Recipient Email:<', '>Your Name:<', '>Message:<', '>Delivery Date:<', '>Item<',
			'>Description<', '>Amount<', '>Frequency<', '>Discount:<', '>Tax:<', '>Shipping:<',
			'>Surcharge:<', '>Number of Installments:<', '>Installment Charge:<', '>Recurring Charges<',
			'>Applied Gift Cards<', '>Back<',
		)),
	);
}

function test_order_pages_lang_no_english_literals()
{
	foreach (order_pages_lang_literals() as $file => $literals) {
		$code = order_pages_lang_code($file);

		foreach ($literals as $literal) {
			pg_assert_same(false, strpos($code, $literal), $file . ': ' . $literal . ' is written in English');
		}
	}
}
