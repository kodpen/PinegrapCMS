<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a custom form that opens a support request
 * (includes/fn/support_forms.php): the stored choice, the fields offered for
 * the address and the subject, what a post of the form's section asks for,
 * the mail built from a record, who the request is tied to, and where it is
 * wired. No database and no mailbox.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function support_forms_test_fields()
{
	return array(
		array('id' => 21, 'label' => 'Your name:', 'name' => 'name', 'type' => 'text box', 'contact_field' => 'first_name'),
		array('id' => 22, 'label' => 'Other address', 'name' => 'other', 'type' => 'email address', 'contact_field' => ''),
		array('id' => 23, 'label' => 'E-mail', 'name' => 'email', 'type' => 'text box', 'contact_field' => 'email_address'),
		array('id' => 24, 'label' => 'Topic', 'name' => 'topic', 'type' => 'pick list', 'contact_field' => ''),
		array('id' => 25, 'label' => 'Message', 'name' => 'message', 'type' => 'text area', 'contact_field' => ''),
	);
}

function test_support_forms_normalize()
{
	pg_assert_same(array('on' => false, 'box_id' => 0, 'email_field' => 0, 'subject_field' => 0), pg_support_form_normalize(null, 5));

	$options = array('forms' => array(
		'5' => array('on' => true, 'box_id' => 3, 'email_field' => 23, 'subject_field' => 24),
		'6' => array('on' => true, 'box_id' => 3),
		'7' => array('on' => true, 'email_field' => 23),
	));
	pg_assert_same(array('on' => true, 'box_id' => 3, 'email_field' => 23, 'subject_field' => 24), pg_support_form_normalize($options, 5));
	pg_assert_false(pg_support_form_normalize($options, 6)['on'], 'no address field, not on');
	pg_assert_false(pg_support_form_normalize($options, 7)['on'], 'no box, not on');
	pg_assert_false(pg_support_form_normalize($options, 8)['on'], 'another form');
}

function test_support_forms_choices()
{
	// E-mail address fields and the field tied to the contact's address, that
	// one first.
	pg_assert_same(array(23 => 'E-mail', 22 => 'Other address'), pg_support_form_email_choices(support_forms_test_fields()));
	// A short answer only: a text area is the request's text, not its subject.
	pg_assert_same(array(21 => 'Your name', 23 => 'E-mail', 24 => 'Topic'), pg_support_form_subject_choices(support_forms_test_fields()));

	pg_assert_same('Help desk (help@example.com)', pg_support_form_box_label(array('name' => 'Help desk', 'address' => 'help@example.com')));
	pg_assert_same('help@example.com', pg_support_form_box_label(array('name' => '', 'address' => 'help@example.com')));
}

function test_support_forms_box_offered()
{
	pg_assert_true(pg_support_form_box_offered(array('enabled' => 1, 'transport' => 'imap', 'settings' => '')), 'a box saved before the switch is a support box');
	pg_assert_true(pg_support_form_box_offered(array('enabled' => 1, 'transport' => 'gmail', 'settings' => '{"support":true}')));
	pg_assert_false(pg_support_form_box_offered(array('enabled' => 1, 'transport' => 'imap', 'settings' => '{"support":false}')), 'a plain box keeps no state');
	pg_assert_false(pg_support_form_box_offered(array('enabled' => 0, 'transport' => 'imap', 'settings' => '')), 'switched off');
	pg_assert_false(pg_support_form_box_offered(array('enabled' => 1, 'transport' => 'whatsapp', 'settings' => '')), 'no e-mail answer from a phone box');
}

function test_support_forms_decide()
{
	$boxes = array(3 => 'Help desk (help@example.com)');
	$before = pg_support_form_normalize(null, 5);
	$fields = support_forms_test_fields();

	pg_assert_same(null, pg_support_form_settings_decide(array(), $before, $boxes, $fields), 'no section in the post');

	$decided = pg_support_form_settings_decide(array('support_form_card' => '1', 'support_form_on' => '1', 'support_form_box' => '3', 'support_form_email_field' => '23', 'support_form_subject_field' => '24'), $before, $boxes, $fields);
	pg_assert_same(array('on' => true, 'box_id' => 3, 'email_field' => 23, 'subject_field' => 24), $decided['row']);
	pg_assert_true($decided['changed']);
	pg_assert_same(array(), $decided['marks']);

	// On without a box or an address field is refused, each with its sentence.
	$decided = pg_support_form_settings_decide(array('support_form_card' => '1', 'support_form_on' => '1'), $before, $boxes, $fields);
	pg_assert_same(array('support_form_box', 'support_form_email_field'), array_keys($decided['marks']));
	pg_assert_false($decided['row']['on']);

	// A box or field the form does not offer.
	$decided = pg_support_form_settings_decide(array('support_form_card' => '1', 'support_form_box' => '9', 'support_form_email_field' => '21', 'support_form_subject_field' => '25'), $before, $boxes, $fields);
	pg_assert_same(array('support_form_box', 'support_form_email_field', 'support_form_subject_field'), array_keys($decided['marks']));

	// Off keeps the choices for the day it is on again.
	$decided = pg_support_form_settings_decide(array('support_form_card' => '1', 'support_form_box' => '3', 'support_form_email_field' => '23'), $before, $boxes, $fields);
	pg_assert_same(array('on' => false, 'box_id' => 3, 'email_field' => 23, 'subject_field' => 0), $decided['row']);
	pg_assert_same(array(), $decided['marks']);

	$same = pg_support_form_settings_decide(array('support_form_card' => '1', 'support_form_box' => '3', 'support_form_email_field' => '23'), $decided['row'], $boxes, $fields);
	pg_assert_false($same['changed'], 'nothing to write');
}

function test_support_forms_subject_and_body()
{
	pg_assert_same('Printer broken', pg_support_form_subject('  Printer broken ', 'Contact us', 'AB12CD34EF'));
	pg_assert_same('Contact us AB12CD34EF', pg_support_form_subject('', 'Contact us', 'AB12CD34EF'), 'no subject field: the form and the record');
	pg_assert_same(255, mb_strlen(pg_support_form_subject(str_repeat('ş', 300), 'Contact us', 'X')));

	$labels = array(21 => 'Your name', 24 => 'Topic', 25 => 'Message');
	$rows = array(
		array('form_field_id' => 21, 'data' => 'Ayşe'),
		array('form_field_id' => 24, 'data' => 'Billing'),
		array('form_field_id' => 24, 'data' => 'Refund'),
		array('form_field_id' => 25, 'data' => "Line one\nLine two"),
		array('form_field_id' => 26, 'data' => ''),
	);
	pg_assert_same("Your name: Ayşe\nTopic: Billing, Refund\nMessage:\nLine one\nLine two\n\nReference code: AB12CD34EF", pg_support_form_body($rows, $labels, 'AB12CD34EF', 'Reference code'));
}

function test_support_forms_mail_raw()
{
	$raw = pg_support_form_mail_raw(array(
		'from_email' => 'ayse@example.com',
		'from_name'  => 'Ayşe Yılmaz',
		'to'         => 'help@example.com',
		'subject'    => 'Yazıcı çalışmıyor',
		'text'       => "Merhaba\nYazıcı bozuk.",
		'message_id' => 'pg-form-41@example.org',
		'date'       => 1764240000,
	));

	pg_assert_contains("Message-ID: <pg-form-41@example.org>\r\n", $raw);
	pg_assert_contains("To: help@example.com\r\n", $raw);
	pg_assert_false(stripos($raw, 'Auto-Submitted') !== false, 'not taken for an automatic mail by the loop gate');

	$parsed = pg_mail_parse($raw, array('with_content' => true));
	pg_assert_same('ayse@example.com', $parsed['from']['email']);
	pg_assert_same('Ayşe Yılmaz', $parsed['from']['name']);
	pg_assert_same('Yazıcı çalışmıyor', $parsed['subject']);
	pg_assert_same('pg-form-41@example.org', $parsed['message_id']);
	pg_assert_same("Merhaba\nYazıcı bozuk.", trim(str_replace("\r\n", "\n", (string) $parsed['text'])));

	// A header line break typed into a field cannot add a header.
	$raw = pg_support_form_mail_raw(array('from_email' => 'a@example.com', 'from_name' => "A\r\nBcc: x@example.com", 'to' => 'help@example.com', 'subject' => "S\nBcc: y@example.com", 'text' => '', 'message_id' => 'm@example.org', 'date' => 1));
	pg_assert_false(strpos($raw, "\nBcc:") !== false);
}

function test_support_forms_tie()
{
	// The signed-in user and the record's contact when their address is the
	// request's; a typed address of someone else is tied to nobody.
	pg_assert_same(array('contact_id' => 7, 'user_id' => 4), pg_support_form_tie('Ayse@Example.com', 4, 'ayse@example.com', 7, 'AYSE@example.com'));
	pg_assert_same(array('contact_id' => 0, 'user_id' => 0), pg_support_form_tie('other@example.com', 4, 'ayse@example.com', 7, 'ayse@example.com'));
	pg_assert_same(array('contact_id' => 0, 'user_id' => 0), pg_support_form_tie('ayse@example.com', 0, '', 0, ''));
}

function test_support_forms_wiring()
{
	pg_assert_contains('includes/fn/support_forms.php', file_get_contents(PG_FUNCTIONS_DIR . '/functions.php'));

	$mails = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/form_mails.php');
	pg_assert_contains("pg_support_form_request(\$page_id, \$form_id);", $mails, 'opened when the e-mails go, not while a fee is owed');

	$page_form = file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');
	pg_assert_contains('pg_support_form_section_html($page_id)', $page_form);
	pg_assert_contains('pg_support_form_save($page_id, $_POST, USER_ID);', $page_form);

	pg_assert_contains('pg_support_form_errors($_POST[\'type\'], (int) $_POST[\'id\'], $_POST)', file_get_contents(PG_FUNCTIONS_DIR . '/edit_page.php'));
	pg_assert_contains('pg_support_form_errors($page_type, $page_id, $_POST)', file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_type_pane.php'));

	// The intake takes the request's customer from the form, not from the
	// mail's address alone.
	$ingest = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail.php');
	pg_assert_contains("\$options['tie']", $ingest);
}
