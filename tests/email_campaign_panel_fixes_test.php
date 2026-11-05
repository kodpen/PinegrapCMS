<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the e-mail campaign screen fixes: an HTML body carries nothing
 * of the sender's session (token, account widgets), bodies are not hard
 * wrapped, posted text fields are strings, a missing page is refused, the
 * Edit Campaign back arrow follows send_to, and the send window closes
 * without an opener. No database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/email_campaign_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_create.php');

function email_campaign_panel_fixes_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_email_campaign_body_loses_the_session_token()
{
	$token = str_repeat('a1b2c3d4', 8);
	$html = '<a href="/pinegrap/remove_recipient.php?id=35&amp;token=' . $token . '&amp;send_to=%2F">x</a>'
		. '<input type="hidden" name="token" value="' . $token . '"><script>var software_token = "' . $token . '";</script>';

	$clean = pg_email_campaign_without_token($html, $token);

	pg_assert_false(strpos($clean, $token) !== false, 'no copy of the token is left');
	pg_assert_contains('remove_recipient.php?id=35&amp;token=&amp;send_to=%2F', $clean, 'the rest of the link stays');
	pg_assert_same($html, pg_email_campaign_without_token($html, ''), 'a blank token cuts nothing');
	pg_assert_same('abc', pg_email_campaign_without_token('abc', 'b'), 'a short value cuts nothing');
}

function test_email_campaign_create_draws_the_page_through_the_helper()
{
	$source = email_campaign_panel_fixes_source('add_email_campaign.php');

	pg_assert_contains('$body = pg_email_campaign_page_body($_POST[\'page_id\']);', $source, 'the body is drawn without the sender\'s session');
	pg_assert_false(strpos($source, 'get_page_content(') !== false, 'no direct render in the sender\'s session');

	$page = email_campaign_panel_fixes_source('get_page_content.php');
	pg_assert_contains('var software_token = "\' . ($email ? \'\' : ($_SESSION[\'software\'][\'token\'] ?? \'\')) . \'";', $page, 'an e-mail render writes no session token');
}

function test_account_widgets_draw_signed_out_in_an_email_render()
{
	$had = array_key_exists('sessionuserid', $_SESSION ?? array());
	$old = $had ? $_SESSION['sessionuserid'] : null;
	$_SESSION['sessionuserid'] = '5';
	$prev = pg_sw_render_context(array('email' => true));

	try {
		pg_assert_false(_pg_acct_signed_in(), 'an e-mail body shows nobody\'s account');
	} finally {
		pg_sw_render_context($prev);

		if ($had) {
			$_SESSION['sessionuserid'] = $old;
		} else {
			unset($_SESSION['sessionuserid']);
		}
	}
}

function test_email_bodies_are_not_hard_wrapped()
{
	foreach (array('includes/fn/email_campaign_create.php', 'includes/fn/mail.php', 'includes/fn/forms.php', 'includes/fn/order_completion.php') as $file) {
		pg_assert_false((bool) preg_match('/wordwrap\(\$(body|html|values)/', email_campaign_panel_fixes_source($file)), $file . ' keeps long lines whole');
	}
}

function test_email_campaign_posted_text_is_a_string()
{
	pg_assert_same('Shop', pg_email_campaign_posted_text(array('from_name' => 'Shop'), 'from_name'));
	pg_assert_same('', pg_email_campaign_posted_text(array('from_name' => array('x')), 'from_name'), 'an array is not given');
	pg_assert_same('', pg_email_campaign_posted_text(array(), 'send_to'), 'a missing field is blank');

	$add = email_campaign_panel_fixes_source('add_email_campaign.php');
	pg_assert_contains("\$_POST['from_name'] = trim(pg_email_campaign_posted_text(\$_POST, 'from_name'));", $add, 'create trims the sender name');

	$edit = email_campaign_panel_fixes_source('edit_email_campaign.php');
	pg_assert_contains("\$_POST['from_name'] = trim(pg_email_campaign_posted_text(\$_POST, 'from_name'));", $edit, 'edit trims the sender name');
	pg_assert_contains("\$_POST['send_to'] = pg_email_campaign_posted_text(\$_POST, 'send_to');", $edit, 'edit reads send_to once as text');
}

function test_email_campaign_create_refuses_a_missing_page()
{
	$source = email_campaign_panel_fixes_source('add_email_campaign.php');

	pg_assert_true((bool) preg_match('/\$row = mysqli_fetch_assoc\(\$result\);\s+\/\/[^\n]*\n[^\n]*\n\s+if \(!\$row\) \{\s+output_error\(lang\(\'Please select a page for the body of the campaign\.\'\)/', $source), 'a page id with no page is refused before anything is stored');
}

function test_edit_email_campaign_back_arrow_follows_send_to()
{
	$source = email_campaign_panel_fixes_source('edit_email_campaign.php');

	pg_assert_contains("'cancel' => array('enable' => 'true', 'url' => pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaigns.php'))", $source, 'the arrow goes where Save goes');
}

function test_send_window_closes_without_an_opener()
{
	$source = email_campaign_panel_fixes_source('send_email_campaign.php');

	pg_assert_false(strpos($source, 'window.opener.location.reload(true); window.close();') !== false, 'no button assumes an opener');
	pg_assert_false(strpos($source, 'onclick="window.close()') !== false, 'no button only closes');
	pg_assert_contains('if (window.opener && !window.opener.closed) {', $source, 'the opener is checked');
	pg_assert_false(strpos($source, '--bs-menu-min-width') !== false, 'the hidden theme menu is gone');
	pg_assert_false(strpos($source, 'material-icons') !== false, 'Bootstrap Icons on the buttons');
}

function test_email_campaign_lists_drop_old_icons()
{
	foreach (array('view_email_campaigns.php', 'view_email_campaign_history.php', 'includes/fn/email_campaign_list.php') as $file) {
		pg_assert_false(strpos(email_campaign_panel_fixes_source($file), '<!--<button') !== false, $file . ' prints no commented-out button');
	}

	pg_assert_contains('<i class="bi bi-trash me-1" aria-hidden="true"></i>', email_campaign_panel_fixes_source('includes/fn/email_campaign_list.php'), 'Delete Selected icon');

	pg_assert_contains('<span class="bi bi-save me-3" aria-hidden="true"></span>', email_campaign_panel_fixes_source('edit_email_campaign.php'), 'the Save icon');
}

function test_email_campaign_contact_group_radios_are_centred()
{
	$source = email_campaign_panel_fixes_source('add_email_campaign.php');

	pg_assert_false(strpos($source, 'style="text-align: center"') !== false, 'no inline centring');
	pg_assert_false(strpos($source, '<td class="text-center"><div class="form-field d-inline-grid me-5">') !== false, 'no end margin pushes a radio off centre');
	pg_assert_contains('<th id="contact_group_include" scope="col" class="text-center">', $source, 'column headings');
}
