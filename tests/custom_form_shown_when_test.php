<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Shown when", the visitor rule that shows a designed form field
 * only for some answers of another field (form_fields.rules `shown_when`,
 * includes/fn/custom_form.php): the cleaned shape, the verdict, the set of
 * hidden fields of a submission (cascading through a hidden field), the
 * attributes drawn on the control, the browser script, the submission path
 * that drops a hidden field's value and the designer row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_cf_shown_source($path)
{
	return (string)file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// A form_fields row as custom_form.php selects it.
function _test_cf_shown_row($id, $name, $type = 'text box', $rules = null)
{
	return array(
		'id' => (string)$id, 'name' => $name, 'type' => $type, 'required' => '0', 'multiple' => '0',
		'office_use_only' => '0', 'contact_field' => '', 'wysiwyg' => '0', 'label' => $name,
		'use_folder_name_for_default_value' => '0', 'default_value' => '', 'validation_message' => '',
		'rules' => ($rules === null) ? null : json_encode($rules),
	);
}

function _test_cf_shown_rule($field, $answers)
{
	return array('shown_when' => array('field' => $field, 'answers' => $answers));
}

// ── Cleaning and the verdict ───────────────────────────────────────────

function test_custom_form_shown_when_clean()
{
	pg_assert_same(array('shown_when' => array('field' => 'company', 'answers' => array('Yes', 'Maybe'))),
		pg_cf_rules_clean(array('shown_when' => array('field' => ' company ', 'answers' => " Yes\n\nMaybe\nYes"))),
		'the required_when shape: trimmed, one per line, no repeats');
	pg_assert_same(array(), pg_cf_rules_clean(array('shown_when' => array('field' => 'company', 'answers' => array()))), 'no answer, no rule');
	pg_assert_same(array(), pg_cf_rules_clean(array('shown_when' => array('field' => '', 'answers' => array('Yes')))), 'no field, no rule');
	pg_assert_same(array(), pg_cf_rules_clean(array('shown_when' => 'company')), 'not an object');
	$both = pg_cf_rules_clean(array(
		'required_when' => array('field' => 'a', 'answers' => array('1')),
		'shown_when'    => array('field' => 'b', 'answers' => array('2')),
	));
	pg_assert_same(array('field' => 'a', 'answers' => array('1')), $both['required_when'], 'beside required_when');
	pg_assert_same(array('field' => 'b', 'answers' => array('2')), $both['shown_when'], 'both kept');
	pg_assert_same('{"shown_when":{"field":"b","answers":["2"]}}',
		pg_cf_rules_json(array('shown_when' => array('field' => 'b', 'answers' => array('2')))), 'the column value');
}

function test_custom_form_shown_when_verdict()
{
	$rules = _test_cf_shown_rule('company', array('Yes'));
	pg_assert_same('hidden', pg_cf_rule_verdict($rules, 'Acme', '', '2026-11-03', true, 'No'), 'another answer hides it');
	pg_assert_same('hidden', pg_cf_rule_verdict($rules, '', '', '2026-11-03', false, ''), 'no answer hides it, saved for later too');
	pg_assert_same('', pg_cf_rule_verdict($rules, 'Acme', '', '2026-11-03', true, ' yes '), 'the answer shows it, case and spaces alike');
	pg_assert_same('', pg_cf_rule_verdict($rules, '', '', '2026-11-03', true, array('No', 'Yes')), 'one of the ticked boxes');
	pg_assert_same('', pg_cf_rule_verdict($rules, '', '', '2026-11-03', true, null), 'a missing field hides nothing');
	pg_assert_same('', pg_cf_rule_verdict($rules, '', '', '2026-11-03', true), 'not given: shown, as before');
	pg_assert_same('', pg_cf_rule_verdict(array(), '', '', '2026-11-03', true, 'No'), 'no rule hides nothing');
}

// ── The hidden fields of a submission ──────────────────────────────────

function test_custom_form_shown_when_hidden_set()
{
	$fields = array(
		_test_cf_shown_row(1, 'is_company', 'radio button'),
		_test_cf_shown_row(2, 'company', 'text box', _test_cf_shown_rule('Is_Company', array('Yes'))),
		_test_cf_shown_row(3, 'tax_office', 'text box', _test_cf_shown_rule('company', array('Acme', 'Other'))),
		_test_cf_shown_row(4, 'note', 'text area', _test_cf_shown_rule('gone', array('x'))),
		_test_cf_shown_row(5, 'plain'),
	);
	pg_assert_same(array(), pg_cf_shown_when_hidden($fields, array('1' => 'Yes', '2' => 'Acme')), 'every branch chosen');
	pg_assert_same(array('3' => true), pg_cf_shown_when_hidden($fields, array('1' => 'Yes', '2' => 'Elsewhere')), 'one level');
	pg_assert_same(array('2' => true, '3' => true), pg_cf_shown_when_hidden($fields, array('1' => 'No', '2' => 'Acme')),
		'a field shown by a hidden field is hidden too, whatever was posted for it');
	pg_assert_same(array('2' => true, '3' => true), pg_cf_shown_when_hidden($fields, array()), 'nothing posted');
}

function test_custom_form_shown_when_hidden_set_groups_and_cycles()
{
	$fields = array(
		_test_cf_shown_row(1, 'extras', 'check box'),
		_test_cf_shown_row(2, 'cake_text', 'text box', _test_cf_shown_rule('extras', array('Cake'))),
		_test_cf_shown_row(3, 'a', 'text box', _test_cf_shown_rule('b', array('x'))),
		_test_cf_shown_row(4, 'b', 'text box', _test_cf_shown_rule('a', array('y'))),
	);
	pg_assert_same(array(), pg_cf_shown_when_hidden(array_slice($fields, 0, 2), array('1' => array('Coffee', 'Cake'))), 'a ticked box');
	pg_assert_same(array('2' => true), pg_cf_shown_when_hidden(array_slice($fields, 0, 2), array('1' => array('Coffee'))), 'none of them');
	pg_assert_same(array(), pg_cf_shown_when_hidden(array_slice($fields, 2), array('3' => 'y', '4' => 'x')),
		'two fields showing each other stay while both answers hold');
	pg_assert_same(array('3' => true, '4' => true), pg_cf_shown_when_hidden(array_slice($fields, 2), array('3' => 'y', '4' => 'z')),
		'and both go when one fails');
}

// ── Drawn on the control ───────────────────────────────────────────────

function _test_cf_shown_control($attrs)
{
	$list = array();
	foreach ($attrs as $name => $value) $list[] = array('name' => $name, 'value' => $value);
	return array('type' => 'semantic', 'props' => array('tag' => 'input', '_attrs' => $list), 'children' => array());
}

function test_custom_form_shown_when_drawn()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		_test_cf_shown_control(array('type' => 'radio', 'name' => 'is_company', 'value' => 'Yes')),
		_test_cf_shown_control(array('type' => 'text', 'name' => 'company')),
		_test_cf_shown_control(array('type' => 'checkbox', 'name' => 'size', 'value' => 'S')),
		_test_cf_shown_control(array('type' => 'checkbox', 'name' => 'size', 'value' => 'M')),
		_test_cf_shown_control(array('type' => 'text', 'name' => 'plain')),
	));
	$company = _test_cf_shown_row(12, 'company', 'text box', _test_cf_shown_rule('is_company', array('Yes', 'Évet')));
	$company['required'] = '1';
	$map = array(
		'is_company' => _test_cf_shown_row(11, 'is_company', 'radio button'),
		'company'    => $company,
		'size'       => _test_cf_shown_row(13, 'size', 'check box', _test_cf_shown_rule('is_company', array('Yes'))),
		'plain'      => _test_cf_shown_row(14, 'plain'),
	);
	pg_cf_apply_field_bindings($tree, $map, null, 0, false);
	$html = _render_tree_node($tree, 0, 0);

	pg_assert_contains('name="12" data-pg-shown-when="11" data-pg-shown-answers="[&quot;Yes&quot;,&quot;Évet&quot;]"', $html,
		'a required field carries the rule too (it is not required while hidden)');
	pg_assert_same(2, substr_count($html, 'data-pg-shown-when="11" data-pg-shown-answers="[&quot;Yes&quot;]"'), 'every box of a group');
	pg_assert_same(3, substr_count($html, 'data-pg-shown-when='), 'nothing on the fields without the rule');
}

// A form without the rule draws as before: no attribute, and the shared
// script carries the switch whatever the form holds.
function test_custom_form_shown_when_parity()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		_test_cf_shown_control(array('type' => 'text', 'name' => 'plain')),
	));
	pg_cf_apply_field_bindings($tree, array('plain' => _test_cf_shown_row(15, 'plain')), null, 0, false);
	pg_assert_same("<div>\n    <input id=\"cf_15\" type=\"text\" name=\"15\">\n</div>", trim(_render_tree_node($tree, 0, 0)), 'byte for byte');
}

function test_custom_form_shown_when_script()
{
	$fn    = _test_cf_shown_source('includes/fn/custom_form.php');
	$start = strpos($fn, 'function pg_cf_validation_script()');
	$body  = substr($fn, $start, strpos($fn, "\n}\n", $start) - $start);
	pg_assert_contains('[data-pg-shown-when]', $body, 'the script reads the rule');
	pg_assert_contains('.hidden=', $body, 'hides the row');
	pg_assert_contains('.disabled=', $body, 'disables the controls, which are then neither checked nor posted');
	pg_assert_true(strpos($body, '[data-pg-shown-when]') < strpos($body, '[data-pg-required-when]'),
		'shown first, so a hidden field requires nothing of another');
	pg_assert_contains('o.disabled', $body, 'a disabled control is no answer to a required_when rule');
}

// ── The submission ─────────────────────────────────────────────────────

function test_custom_form_shown_when_submission()
{
	$site = _test_cf_shown_source('custom_form.php');
	$at   = strpos($site, '$shown_when_hidden = pg_cf_shown_when_hidden(');
	pg_assert_true($at !== false, 'the hidden fields are worked out');
	pg_assert_true($at < strpos($site, '// loop through all fields in order to validate fields'), 'before the checks');
	pg_assert_contains("\$liveform->assign_field_value(\$field['id'], '')", $site, 'a hidden field\'s value is dropped');
	pg_assert_contains("&& !isset(\$shown_when_hidden[\$field['id']])", $site, 'and it is not checked');
	pg_assert_true(substr_count($site, "!isset(\$shown_when_hidden[\$field['id']])") >= 2, 'nor written to the contact');
}

// ── The designer ───────────────────────────────────────────────────────

function test_custom_form_shown_when_designer()
{
	$js = _test_cf_shown_source('assets/js/style_designer.src.js');
	pg_assert_contains("_sdT('Shown when')", $js, 'the Form Field row');
	pg_assert_contains("on('sd-cf-shown-field'", $js, 'the field pick writes the rule');
	pg_assert_contains("on('sd-cf-shown-answers'", $js, 'and the answers');
	pg_assert_contains('sd-cf-shown-chip', $js, 'a chip on the canvas control');
}
