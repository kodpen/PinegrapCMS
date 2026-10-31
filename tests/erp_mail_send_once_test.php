<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for 'Send the e-mail' on the ERP invoice, quote and purchase
 * order screens. The mail action builds the PDF (dompdf, or on an invoice's
 * first official send the e-document provider's copy) and sends the e-mail
 * inside the request, often longer than the window guard's 3 seconds. A
 * second press then waited for the first and sent the same e-mail again:
 * two messages to the customer or supplier and two history rows. The mail
 * form now sends once and says the send is running.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_mail_send_once_screens()
{
	return array('edit_erp_invoice.php', 'edit_erp_quote.php', 'edit_erp_purchase_order.php');
}

// Each mail form carries the send-once marker and a status line beside its
// button; the button keeps its label and is never disabled.
function test_erp_mail_form_sends_once()
{
	foreach (erp_mail_send_once_screens() as $file) {
		$src = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);

		pg_assert_contains('<form name="mail_form" action="\' . $self_action . \'" method="post" class="row g-3" data-erp-send-once>', $src, $file . ': the mail form sends once');
		pg_assert_contains('<span class="small fg-secondary" role="status" aria-live="polite" data-erp-send-once-status></span>', $src, $file . ': a status line is there');
		pg_assert_contains('lang(\'Send the e-mail\') . \'</button>', $src, $file . ': the button keeps its label');
	}
}

// Each screen prints the window listener: the first submit goes, later ones
// are cancelled until a page restored from the cache resets it.
function test_erp_mail_send_once_script()
{
	foreach (erp_mail_send_once_screens() as $file) {
		$src = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);

		pg_assert_contains('form.hasAttribute("data-erp-send-once")', $src, $file . ': the listener looks for the marker');
		pg_assert_contains('if (sent.indexOf(form) !== -1) { event.preventDefault(); return; }', $src, $file . ': a later submit is cancelled');
		pg_assert_contains('if (event.defaultPrevented) { return; }', $src, $file . ': a cancelled submit does not count');
		pg_assert_contains('window.addEventListener("pageshow", function (event) {', $src, $file . ': a restored page resets it');
		pg_assert_contains('form.setAttribute("aria-busy", "true");', $src, $file . ': the form is busy while it sends');
		pg_assert_contains("escape_javascript(lang('Working on it. Wait for the answer before leaving this page.'))", $src, $file . ': the status line says to wait');
	}
}
