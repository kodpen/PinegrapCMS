<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two panel refusals that left the user on a bare page: the Send
 * E-mail Campaign window's refusals (access denied, campaign cancelled) are
 * drawn inside the window's own frame with its Close button, and the packing
 * slip's missing recipient is translated and answers 404.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_refusal_function_body($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}", $start);

	return substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
}

// The popup refusal prints a whole panel document: frame, alert, Close, footer.
function test_panel_refusal_email_campaign_popup_framed()
{
	$body = pg_test_refusal_function_body(file_get_contents(PG_FUNCTIONS_DIR . '/send_email_campaign.php'), 'output_error_in_popup');

	pg_assert_true($body !== '', 'output_error_in_popup() exists');
	pg_assert_true(strpos($body, "output_header_secure(array('title'=>lang('Send E-mail Campaign'),'icon'=>'campaign'))") !== false, 'popup refusal opens the window frame');
	pg_assert_true(strpos($body, 'alert theme-danger') !== false, 'popup refusal is a danger alert');
	pg_assert_true(strpos($body, 'window.opener.location.reload(true); window.close();') !== false, 'popup refusal has the Close button');
	pg_assert_true(strpos($body, 'output_footer_secure()') !== false, 'popup refusal closes the frame');
	pg_assert_true(strpos($body, '<div style="color: red">') === false, 'no bare red div');
	pg_assert_true(strpos($body, 'exit') !== false, 'popup refusal stops the script');
}

// The cancelled sentence goes through lang() with its existing key.
function test_panel_refusal_email_campaign_cancelled_translated()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/send_email_campaign.php');

	pg_assert_true(strpos($source, "output_error_in_popup(lang('The e-mail campaign may not be sent because it is cancelled.  Please update the status in order to send the e-mail campaign.'))") !== false, 'cancelled sentence passed through lang()');
}

// The packing slip's missing recipient is translated and answers 404.
function test_panel_refusal_packing_slip_recipient_translated_404()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/print_packing_slip.php');

	pg_assert_true(strpos($source, "output_error(lang('The recipient could not be found.') . ' <a href=\"javascript:history.go(-1)\">' . lang('Go back') . '</a>.', 404);") !== false, 'translated refusal with 404');
	pg_assert_true(strpos($source, "'The recipient could not be found. <a") === false, 'no untranslated sentence left');
}
