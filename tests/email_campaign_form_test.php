<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the e-mail campaign form parts shared by the create and the edit
 * screens (includes/forms/email_campaign_form.php): editable and read-only
 * output, the schedule card gate, and that both screens use the helpers
 * while keeping their own queries.
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

function test_email_campaign_form_editable_fields()
{
	$html = pg_email_campaign_subject_row('Sale & "News"');
	pg_assert_contains('<input value="Sale &amp; &quot;News&quot;" type="text" name="subject"', $html, 'subject input escaped');

	$html = pg_email_campaign_from_card(array(
		'from_name' => 'Shop <A>',
		'from_email_address' => 'a@example.com',
		'reply_email_address' => 'b@example.com',
	));
	pg_assert_contains(lang('E-Mail Message From'), $html, 'from card title');
	pg_assert_contains('value="Shop &lt;A&gt;" type="text" class="form-control" id="from_name" name="from_name"', $html, 'from name input');
	pg_assert_contains('value="a@example.com" type="text" class="form-control text-end" id="from_email_address" name="from_email_address"', $html, 'from address input');
	pg_assert_contains('id="reply_email_address" name="reply_email_address"', $html, 'reply-to input');

	$html = pg_email_campaign_purpose_card('commercial');
	pg_assert_contains('value="commercial" checked="checked"', $html, 'commercial checked');
	pg_assert_false(strpos($html, 'value="transactional" checked') !== false, 'transactional not checked');

	$html = pg_email_campaign_purpose_card('transactional');
	pg_assert_contains('value="transactional" checked="checked"', $html, 'transactional checked');
}

function test_email_campaign_form_readonly_prints_text()
{
	$html = pg_email_campaign_subject_row('Sale & more', true);
	pg_assert_contains('<p>Sale &amp; more</p>', $html, 'subject as text');
	pg_assert_false(strpos($html, '<input') !== false, 'no subject input');

	$html = pg_email_campaign_from_card(array('from_name' => 'Shop', 'from_email_address' => 'a@example.com', 'reply_email_address' => 'b@example.com'), true);
	pg_assert_contains('<p>Shop</p>', $html, 'from name as text');
	pg_assert_contains('<p>a@example.com</p>', $html, 'from address as text');
	pg_assert_contains('<p>b@example.com</p>', $html, 'reply-to as text');
	pg_assert_false(strpos($html, '<input') !== false, 'no sender inputs');

	$html = pg_email_campaign_purpose_card('commercial', true);
	pg_assert_contains('<p>' . lang('Commercial') . '</p>', $html, 'purpose as text');
	pg_assert_false(strpos($html, 'type="radio"') !== false, 'no purpose radios');

	$html = pg_email_campaign_schedule_card('', true, true);
	pg_assert_contains('<p>' . lang('Immediately') . '</p>', $html, 'blank start time reads Immediately');
	pg_assert_false(strpos($html, 'data-pg-datetime') !== false, 'no date field');

	$html = pg_email_campaign_format_row(lang('HTML'), true);
	pg_assert_contains('<p>' . lang('HTML') . '</p>', $html, 'fixed format as text');
}

function test_email_campaign_form_schedule_card_gate()
{
	pg_assert_same('', pg_email_campaign_schedule_card('', false, false), 'no card without the campaign job');
	pg_assert_same('', pg_email_campaign_schedule_card('', true, false), 'no read-only card without the campaign job');

	$html = pg_email_campaign_schedule_card('0000-00-00 00:00:00', false, true);
	pg_assert_contains(lang('E-Mail Message Delivery Schedule'), $html, 'card title');
	pg_assert_contains('<input value="" type="text" maxlength="19" class="form-control" id="start_time" name="start_time" data-pg-datetime />', $html, 'zero start time is blank');
	pg_assert_contains('datetimepicker_options', $html, 'picker options printed');
}

function test_email_campaign_form_used_by_both_screens()
{
	foreach (array('add_email_campaign.php', 'edit_email_campaign.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains("require_once(dirname(__FILE__) . '/includes/forms/email_campaign_form.php');", $source, $file . ' loads the shared form');
		foreach (array('pg_campaign_content_card(', 'pg_email_campaign_from_card(', 'pg_email_campaign_schedule_card(', 'pg_email_campaign_purpose_card(') as $call) {
			pg_assert_contains($call, $source, $file . ' calls ' . $call);
		}
		pg_assert_false(strpos($source, 'id="purpose_commercial"') !== false, $file . ' prints no own purpose radios');
		pg_assert_false(strpos($source, "lang('E-Mail Message Delivery Schedule')") !== false, $file . ' prints no own schedule card');
	}

	$edit = file_get_contents(PG_FUNCTIONS_DIR . '/edit_email_campaign.php');
	pg_assert_contains(", \$readonly)", $edit, 'edit passes the read-only flag');
}

function test_email_campaign_form_layout_columns()
{
	$html = pg_email_campaign_layout('<p>FORM</p>', '<p>SIDE</p>', '<button>Save</button>');
	pg_assert_contains('<div class="col-12 lg:col-8 2xl:col-9 order-1">', $html, 'form column');
	pg_assert_contains('<div class="col-12 lg:col-4 2xl:col-3 order-2">', $html, 'status column');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $html, 'the box inside the status column sticks');
	pg_assert_contains('<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">', $html, 'sticky save bar');
	pg_assert_true(strpos($html, 'FORM') < strpos($html, 'SIDE'), 'the status column comes after the form column');
	pg_assert_contains('<button>Save</button>', $html, 'buttons in the bar');

	pg_assert_false(strpos(pg_email_campaign_layout('F', 'S', ''), 'buttons navigation') !== false, 'no bar without buttons (completed campaign)');
	pg_assert_false(strpos(pg_email_campaign_layout('F', '', 'B'), 'lg:col-4') !== false, 'no status column without cards');
}

function test_email_campaign_form_side_cards_fit_the_column()
{
	$html = pg_email_campaign_schedule_card('', false, true);
	pg_assert_contains('<div class="col-12 my-3">', $html, 'the start time takes the narrow column');
	pg_assert_false(strpos($html, 'lg:col-4') !== false, 'no wide-screen width in the status column');

	$html = pg_email_campaign_purpose_card('commercial');
	pg_assert_contains('<label class="" for="purpose_commercial">' . lang('Commercial') . '</label>', $html, 'short radio label');
	pg_assert_contains('<div class="form-text" id="purpose_commercial_help">' . lang('Send e-mail to opted-in contacts only. Example: \'We have an offer for you\'') . '</div>', $html, 'the example under the radio');
	pg_assert_contains('aria-describedby="purpose_commercial_help"', $html, 'the radio is described by its example');
}

function test_email_campaign_form_screens_use_the_layout()
{
	foreach (array('add_email_campaign.php', 'edit_email_campaign.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_email_campaign_layout(', $source, $file . ' draws the two columns');
		pg_assert_false(strpos($source, 'nav class="buttons navigation') !== false, $file . ' prints no own save bar');
	}

	$edit = file_get_contents(PG_FUNCTIONS_DIR . '/edit_email_campaign.php');
	pg_assert_contains('<label class="form-label" for="status">', $edit, 'the status select has a visible label');
}
