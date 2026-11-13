<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a custom form's WhatsApp message to its sender
 * (includes/fn/whatsapp_forms.php): the stored choice, the fields offered for
 * the number and the variables, what a post of the form's section asks for,
 * the record's answers, and where it is wired. No database and no call to
 * Meta.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function whatsapp_forms_test_fields()
{
	return array(
		array('id' => 11, 'label' => 'Your name', 'name' => 'name', 'type' => 'text box', 'contact_field' => 'first_name'),
		array('id' => 12, 'label' => '', 'name' => 'phone', 'type' => 'text box', 'contact_field' => ''),
		array('id' => 13, 'label' => 'Mobile <b>phone</b>', 'name' => 'mobile', 'type' => 'text box', 'contact_field' => 'mobile_phone'),
		array('id' => 14, 'label' => 'Topic', 'name' => 'topic', 'type' => 'pick list', 'contact_field' => ''),
	);
}

function whatsapp_forms_test_offered()
{
	return pg_whatsapp_message_offered(array(array(
		'name' => 'form_thanks', 'language' => 'tr', 'category' => 'UTILITY', 'status' => 'APPROVED', 'parameter_format' => 'POSITIONAL',
		'components' => array(array('type' => 'BODY', 'text' => 'Thanks {{1}}, your request {{2}} is in.')),
	)));
}

function test_whatsapp_forms_normalize()
{
	pg_assert_same(array('on' => false, 'phone_field' => 0, 'template' => '', 'vars' => array()), pg_whatsapp_form_normalize(null, 5));

	$options = array('forms' => array(
		'5' => array('on' => true, 'phone_field' => 13, 'template' => 'form_thanks|tr', 'vars' => array('body:1' => 'field:11', 'body:2' => 7)),
		'6' => array('on' => true, 'template' => 'form_thanks|tr'),
	));
	pg_assert_same(array('on' => true, 'phone_field' => 13, 'template' => 'form_thanks|tr', 'vars' => array('body:1' => 'field:11')), pg_whatsapp_form_normalize($options, 5));
	pg_assert_false(pg_whatsapp_form_normalize($options, 6)['on'], 'no number field, not on');
	pg_assert_false(pg_whatsapp_form_normalize($options, 7)['on'], 'another form');
}

function test_whatsapp_forms_choices()
{
	// Text boxes only, the contact's mobile phone first; a label without
	// markup, the name when there is no label.
	pg_assert_same(array(13 => 'Mobile phone', 11 => 'Your name', 12 => 'phone'), pg_whatsapp_form_phone_choices(whatsapp_forms_test_fields()));

	$choices = pg_whatsapp_form_value_choices(whatsapp_forms_test_fields());
	pg_assert_same(array('store', 'reference_code', 'field:11', 'field:12', 'field:13', 'field:14'), array_keys($choices));
	pg_assert_same('Topic', $choices['field:14']);
}

function test_whatsapp_forms_decide()
{
	$before = pg_whatsapp_form_normalize(null, 5);
	$offered = whatsapp_forms_test_offered();
	$fields = whatsapp_forms_test_fields();
	$var = function ($position) {
		return pg_whatsapp_choice_var_name('wa_form', 'form_thanks|tr', $position);
	};

	pg_assert_same(null, pg_whatsapp_form_settings_decide(array(), $before, $offered, $fields));

	// Nothing chosen, nothing changes.
	$decision = pg_whatsapp_form_settings_decide(array('wa_form_card' => '1'), $before, $offered, $fields);
	pg_assert_false($decision['changed']);
	pg_assert_same(array(), $decision['marks']);

	// On without a number field or a template.
	$decision = pg_whatsapp_form_settings_decide(array('wa_form_card' => '1', 'wa_form_on' => '1'), $before, $offered, $fields);
	pg_assert_true(isset($decision['marks']['wa_form_phone_field']));
	pg_assert_true(isset($decision['marks']['wa_form_template']));

	// A pick list cannot hold the number; a value of another form is refused.
	$post = array('wa_form_card' => '1', 'wa_form_on' => '1', 'wa_form_phone_field' => '14', 'wa_form_template' => 'form_thanks|tr', $var(0) => 'field:99', $var(1) => 'reference_code');
	$decision = pg_whatsapp_form_settings_decide($post, $before, $offered, $fields);
	pg_assert_true(isset($decision['marks']['wa_form_phone_field']), 'pick list');
	pg_assert_true(isset($decision['marks'][$var(0)]), 'unknown field');

	// All chosen.
	$post['wa_form_phone_field'] = '13';
	$post[$var(0)] = 'field:11';
	$decision = pg_whatsapp_form_settings_decide($post, $before, $offered, $fields);
	pg_assert_same(array(), $decision['marks']);
	pg_assert_true($decision['changed']);
	pg_assert_same(array('on' => true, 'phone_field' => 13, 'template' => 'form_thanks|tr', 'vars' => array('body:1' => 'field:11', 'body:2' => 'reference_code')), $decision['row']);

	// Saved again unchanged.
	pg_assert_false(pg_whatsapp_form_settings_decide($post, $decision['row'], $offered, $fields)['changed']);
}

function test_whatsapp_forms_values()
{
	pg_assert_same(array('field:11' => 'Ann', 'field:15' => 'red, blue'), pg_whatsapp_form_values(array(
		array('form_field_id' => '11', 'data' => ' Ann '),
		array('form_field_id' => '15', 'data' => 'red'),
		array('form_field_id' => '15', 'data' => 'blue'),
		array('form_field_id' => '16', 'data' => ''),
	)));
}

// The record's answers fill the template as the order values do.
function test_whatsapp_forms_components()
{
	$template = array('id' => 1, 'status' => 'APPROVED', 'parameter_format' => 'POSITIONAL',
		'components' => array(array('type' => 'BODY', 'text' => 'Thanks {{1}}, your request {{2}} is in.')));
	$filled = pg_whatsapp_message_components($template, array('body:1' => 'field:11', 'body:2' => 'reference_code'), array('field:11' => 'Ann', 'reference_code' => 'R-77'));
	pg_assert_same(array(array('type' => 'body', 'parameters' => array(array('type' => 'text', 'text' => 'Ann'), array('type' => 'text', 'text' => 'R-77')))), $filled['components']);
}

function test_whatsapp_forms_wiring()
{
	pg_assert_contains('includes/fn/whatsapp_forms.php', file_get_contents(PG_FUNCTIONS_DIR . '/functions.php'));

	$mails = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/form_mails.php');
	pg_assert_contains("if (empty(\$mail['fee_owed'])) {\n        pg_whatsapp_form_message(\$page_id, \$form_id);", $mails, 'held while a fee is owed');

	$page_form = file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');
	pg_assert_contains('pg_whatsapp_form_section_html($page_id)', $page_form);
	pg_assert_contains('pg_whatsapp_form_save($page_id, $_POST, USER_ID);', $page_form);

	pg_assert_contains('pg_whatsapp_form_errors($_POST[\'type\'], (int) $_POST[\'id\'], $_POST)', file_get_contents(PG_FUNCTIONS_DIR . '/edit_page.php'));
	pg_assert_contains('pg_whatsapp_form_errors($page_type, $page_id, $_POST)', file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_type_pane.php'));
}
