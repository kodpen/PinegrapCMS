<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the form variables a layout can use (includes/fn/custom_form.php):
 * the yes/no text a check box prints for ^^name^^%%Yes|No%%, and the list of
 * variables the form item view screen offers for a form's fields and the
 * standard fields.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A ticked box prints the first text, an empty one the second.
function test_form_item_view_variables_checkbox_ticked_and_empty()
{
	pg_assert_same('Yes', pg_cf_checkbox_format_text('1', 'Yes|No'), 'stored 1');
	pg_assert_same('Yes', pg_cf_checkbox_format_text('on', 'Yes|No'), 'stored option value');
	pg_assert_same('No', pg_cf_checkbox_format_text('', 'Yes|No'), 'empty');
	pg_assert_same('No', pg_cf_checkbox_format_text('0', 'Yes|No'), 'stored 0');
	pg_assert_same('No', pg_cf_checkbox_format_text(null, 'Yes|No'), 'no row');
	pg_assert_same('No', pg_cf_checkbox_format_text('  ', 'Yes|No'), 'blank');
}

// Either text may be empty: "Given|" prints nothing for an empty box.
function test_form_item_view_variables_checkbox_empty_side()
{
	pg_assert_same('Given', pg_cf_checkbox_format_text('1', 'Given|'), 'ticked');
	pg_assert_same('', pg_cf_checkbox_format_text('', 'Given|'), 'empty');
	pg_assert_same('', pg_cf_checkbox_format_text('1', '|Missing'), 'ticked, empty first text');
	pg_assert_same('Missing', pg_cf_checkbox_format_text('', '|Missing'), 'empty, second text');
}

// Without a | the format is not a yes/no pair; the caller keeps its own path.
function test_form_item_view_variables_checkbox_not_a_pair()
{
	pg_assert_same(null, pg_cf_checkbox_format_text('1', ''), 'no format');
	pg_assert_same(null, pg_cf_checkbox_format_text('1', 'd.m.Y'), 'date format');
}

// A group with several ticked boxes counts as ticked; only the first | splits.
function test_form_item_view_variables_checkbox_group_and_extra_bar()
{
	pg_assert_same('Yes', pg_cf_checkbox_format_text('Red, Blue', 'Yes|No'), 'joined group values');
	pg_assert_same('Yes', pg_cf_checkbox_format_text(array('Red', 'Blue'), 'Yes|No'), 'array of values');
	pg_assert_same('No', pg_cf_checkbox_format_text(array(), 'Yes|No'), 'empty array');
	pg_assert_same('No', pg_cf_checkbox_format_text(array('', '0'), 'Yes|No'), 'array of empty values');
	pg_assert_same('No|Maybe', pg_cf_checkbox_format_text('', 'Yes|No|Maybe'), 'rest after the first bar');
}

// Form fields become tokens with their label and type; information fields and
// nameless fields are left out, a missing label falls back to the name.
function test_form_item_view_variables_help_fields()
{
	$help = pg_cf_variables_help_build(array(
		array('name' => 'full_name', 'label' => 'Full Name', 'type' => 'text box'),
		array('name' => '', 'label' => 'No name', 'type' => 'text box'),
		array('name' => 'intro', 'label' => 'Intro', 'type' => 'information'),
		array('name' => 'consent', 'label' => '', 'type' => 'check box'),
		array('name' => 'birthday', 'label' => 'Birthday', 'type' => 'date'),
		array('name' => 'meeting', 'label' => 'Meeting', 'type' => 'date and time'),
	), array(), 'Evet', 'Hayır');

	pg_assert_same(4, count($help['fields']), 'four usable fields');
	pg_assert_same('^^full_name^^', $help['fields'][0]['token'], 'token');
	pg_assert_same('Full Name', $help['fields'][0]['label'], 'label');
	pg_assert_same('text box', $help['fields'][0]['type'], 'type');
	pg_assert_same('', $help['fields'][0]['example'], 'no example for a text box');
	pg_assert_same('consent', $help['fields'][1]['label'], 'label falls back to the name');
	pg_assert_same('^^consent^^%%Evet|Hayır%%', $help['fields'][1]['example'], 'check box example');
	pg_assert_same('^^birthday^^%%d.m.Y%%', $help['fields'][2]['example'], 'date example');
	pg_assert_same('^^meeting^^%%d.m.Y H:i%%', $help['fields'][3]['example'], 'date and time example');
}

// Standard fields come from get_standard_fields_for_view(): value is the token.
function test_form_item_view_variables_help_standard_fields()
{
	$help = pg_cf_variables_help_build(array(), array(
		array('name' => 'Reference Code', 'value' => 'reference_code', 'sql_name' => 'forms.reference_code', 'type' => ''),
		array('name' => 'Submitted Date & Time', 'value' => 'submitted_date_and_time', 'sql_name' => 'x', 'type' => 'date and time'),
	));

	pg_assert_same(array(), $help['fields'], 'no form fields');
	pg_assert_same(2, count($help['standard']), 'two standard fields');
	pg_assert_same('^^reference_code^^', $help['standard'][0]['token'], 'reference code token');
	pg_assert_same('Reference Code', $help['standard'][0]['label'], 'reference code label');
	pg_assert_same('', $help['standard'][0]['example'], 'no example');
	pg_assert_same('^^submitted_date_and_time^^%%d.m.Y H:i%%', $help['standard'][1]['example'], 'date and time example');
}

// The list the screen draws holds every standard field the renderer knows.
function test_form_item_view_variables_help_lists_all_standard_fields()
{
	$standard = get_standard_fields_for_view();
	$help = pg_cf_variables_help_build(array(), $standard);
	pg_assert_same(count($standard), count($help['standard']), 'one entry per standard field');
	$tokens = array();
	foreach ($help['standard'] as $item) $tokens[] = $item['token'];
	pg_assert_true(in_array('^^referring_url^^', $tokens, true), 'referring url listed');
	pg_assert_true(in_array('^^submitter^^', $tokens, true), 'submitter listed');
}

// The format samples use the form's first fitting fields, else placeholders.
function test_form_item_view_variables_help_syntax_samples()
{
	$help = pg_cf_variables_help_build(array(
		array('name' => 'full_name', 'label' => 'Full Name', 'type' => 'text box'),
		array('name' => 'consent', 'label' => 'Consent', 'type' => 'check box'),
		array('name' => 'newsletter', 'label' => 'Newsletter', 'type' => 'check box'),
		array('name' => 'birthday', 'label' => 'Birthday', 'type' => 'date'),
	), array(), 'Evet', 'Hayır');
	pg_assert_same('[[Full Name: ^^full_name^^ || -]]', $help['syntax']['conditional'], 'conditional on the first field');
	pg_assert_same('^^birthday^^%%d.m.Y%%', $help['syntax']['date'], 'date on the date field');
	pg_assert_same('^^consent^^%%Evet|Hayır%%', $help['syntax']['checkbox'], 'check box on the first check box');

	$empty = pg_cf_variables_help_build(array(), array(), 'Yes', 'No');
	pg_assert_same('[[example: ^^example^^ || -]]', $empty['syntax']['conditional'], 'conditional placeholder');
	pg_assert_same('^^submitted_date_and_time^^%%d.m.Y H:i%%', $empty['syntax']['date'], 'date on the submitted date');
	pg_assert_same('^^example^^%%Yes|No%%', $empty['syntax']['checkbox'], 'check box placeholder');
}

// The links only the sender's e-mail fills in are a group of their own.
function test_form_item_view_variables_help_mail_links()
{
	$help = pg_cf_variables_help_build(array(), array(), 'Yes', 'No', array(
		'email_preferences_url' => 'E-posta tercihleri',
	));

	pg_assert_same(2, count($help['mail']), 'two mail links');
	pg_assert_same('^^email_preferences_url^^', $help['mail'][0]['token'], 'preferences token');
	pg_assert_same('E-posta tercihleri', $help['mail'][0]['label'], 'preferences label');
	pg_assert_same('^^confirm_subscription_url^^', $help['mail'][1]['token'], 'confirmation token');
	pg_assert_same('confirm_subscription_url', $help['mail'][1]['label'], 'label falls back to the name');
	pg_assert_same('', $help['mail'][0]['example'], 'no format example');

	$tokens = array();
	foreach (pg_cf_variables_help_build(array(), get_standard_fields_for_view())['standard'] as $item) $tokens[] = $item['token'];
	pg_assert_false(in_array('^^email_preferences_url^^', $tokens, true), 'not a standard field');
}
