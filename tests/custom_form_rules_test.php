<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visitor rules of a designed form field (form_fields.rules,
 * includes/fn/custom_form.php): "Not before today" on a date and "Required
 * when" another field has one of the given answers. The rules are cleaned
 * into one shape, written by the designer sync, checked on site submissions
 * only (custom_form.php) and set by the Cafe and Clinic templates.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_cf_rules_source($path)
{
	return (string)file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The rules a control of a widget tree is derived with, by field name.
function _test_cf_rules_of_tree($tree, $name)
{
	$fields = _pg_cf_controls($tree, 0);
	return isset($fields[$name]) ? $fields[$name]['rules'] : null;
}

function _test_cf_rules_input($type, $name, $cf = array())
{
	$props = array('tag' => 'input', '_attrs' => array(
		array('name' => 'type', 'value' => $type),
		array('name' => 'name', 'value' => $name),
	));
	if ($cf) $props['_cf'] = $cf;
	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

// ── Cleaning ───────────────────────────────────────────────────────────

function test_custom_form_rules_clean_shapes()
{
	pg_assert_same(array(), pg_cf_rules_clean(null), 'NULL is no rule');
	pg_assert_same(array(), pg_cf_rules_clean(''), 'empty is no rule');
	pg_assert_same(array(), pg_cf_rules_clean('not json'), 'broken JSON is no rule');
	pg_assert_same(array('min_date' => 'today'), pg_cf_rules_clean('{"min_date":"today"}', 'date'), 'JSON text');
	pg_assert_same(array(), pg_cf_rules_clean(array('min_date' => 'yesterday'), 'date'), 'an unknown min_date value is dropped');
	pg_assert_same(array(), pg_cf_rules_clean(array('min_date' => 'today'), 'text box'), 'min_date only on a date');
	pg_assert_same(
		array('required_when' => array('field' => 'delivery', 'answers' => array('Delivered to us', 'Courier'))),
		pg_cf_rules_clean(array('required_when' => array('field' => ' delivery ', 'answers' => array(' Delivered to us', '', 'Courier', 'Delivered to us')))),
		'answers trimmed, empty and repeated ones dropped');
	pg_assert_same(array(), pg_cf_rules_clean(array('required_when' => array('field' => '', 'answers' => array('a')))), 'no field, no rule');
	pg_assert_same(array(), pg_cf_rules_clean(array('required_when' => array('field' => 'delivery', 'answers' => array()))), 'no answer, no rule');
	pg_assert_same(array('required_when' => array('field' => 'delivery', 'answers' => array('a', 'b'))),
		pg_cf_rules_clean(array('required_when' => array('field' => 'delivery', 'answers' => "a\n b \n\n"))), 'answers given one per line');
}

function test_custom_form_rules_json()
{
	pg_assert_same(null, pg_cf_rules_json(array()), 'no rule is NULL');
	pg_assert_same(null, pg_cf_rules_json(array('min_date' => 'today'), 'text box'), 'a date rule on a text box is NULL');
	pg_assert_same('{"min_date":"today"}', pg_cf_rules_json(array('min_date' => 'today'), 'date'));
	pg_assert_same('{"required_when":{"field":"teslimat","answers":["Bize teslim edilsin"]}}',
		pg_cf_rules_json(array('required_when' => array('field' => 'teslimat', 'answers' => array('Bize teslim edilsin')))), 'UTF-8 kept as is');
}

// ── Not before today ───────────────────────────────────────────────────

function test_custom_form_rules_min_date_today_boundary()
{
	$rules = array('min_date' => 'today');
	pg_assert_same('', pg_cf_rule_verdict($rules, '2026-10-30', null, '2026-10-30', true), 'today passes');
	pg_assert_same('', pg_cf_rule_verdict($rules, '2026-10-31', null, '2026-10-30', true), 'tomorrow passes');
	pg_assert_same('min_date', pg_cf_rule_verdict($rules, '2026-10-29', null, '2026-10-30', true), 'yesterday is refused');
	pg_assert_same('min_date', pg_cf_rule_verdict($rules, '2025-12-31', null, '2026-10-30', false), 'refused when saved for later too');
	pg_assert_same('', pg_cf_rule_verdict($rules, '', null, '2026-10-30', true), 'an empty optional date passes');
	pg_assert_same('', pg_cf_rule_verdict($rules, 'garbage', null, '2026-10-30', true), 'a value that is no date is the type check\'s');
}

// Today is the site's: the default time zone init.php sets from TIMEZONE.
function test_custom_form_rules_today_in_site_zone()
{
	$was = date_default_timezone_get();
	date_default_timezone_set('Pacific/Kiritimati');
	$ahead = pg_cf_rules_today();
	date_default_timezone_set('Pacific/Pago_Pago');
	$behind = pg_cf_rules_today();
	date_default_timezone_set($was);
	pg_assert_true(preg_match('/^\d{4}-\d{2}-\d{2}$/', $ahead) === 1, 'Y-m-d');
	pg_assert_true($ahead > $behind, 'UTC+14 is a day ahead of UTC-11');
}

// ── Required when ──────────────────────────────────────────────────────

function test_custom_form_rules_required_when_one_answer()
{
	$rules = array('required_when' => array('field' => 'delivery', 'answers' => array('Delivered to us')));
	pg_assert_same('required', pg_cf_rule_verdict($rules, '', 'Delivered to us', '2026-10-30', true), 'empty when the answer is chosen');
	pg_assert_same('required', pg_cf_rule_verdict($rules, '  ', ' delivered TO us ', '2026-10-30', true), 'case and spaces alike');
	pg_assert_same('', pg_cf_rule_verdict($rules, 'Sea Road 5', 'Delivered to us', '2026-10-30', true), 'filled in');
	pg_assert_same('', pg_cf_rule_verdict($rules, '', 'We collect it', '2026-10-30', true), 'another answer');
	pg_assert_same('', pg_cf_rule_verdict($rules, '', 'Delivered to us', '2026-10-30', false), 'saved for later, nothing is required');
}

function test_custom_form_rules_required_when_several_answers()
{
	$rules = array('required_when' => array('field' => 'extras', 'answers' => array('Cake', 'Flowers')));
	pg_assert_same('required', pg_cf_rule_verdict($rules, '', array('Coffee', 'Flowers'), '2026-10-30', true), 'one of the ticked boxes');
	pg_assert_same('required', pg_cf_rule_verdict($rules, array(), 'Cake', '2026-10-30', true), 'an empty box group is empty');
	pg_assert_same('', pg_cf_rule_verdict($rules, array('x'), 'Cake', '2026-10-30', true), 'a ticked box is a value');
	pg_assert_same('', pg_cf_rule_verdict($rules, '', array('Coffee'), '2026-10-30', true), 'none of them');
}

// The other field is looked up by name: missing or renamed, the rule asks nothing.
function test_custom_form_rules_other_field_by_name()
{
	$fields = array(
		array('id' => '7', 'name' => 'Delivery'),
		array('id' => '8', 'name' => 'address'),
	);
	$rules = array('required_when' => array('field' => 'delivery', 'answers' => array('Delivered to us')));
	$other = pg_cf_rule_other_field($rules, $fields);
	pg_assert_same('7', $other['id'], 'name in any case');
	pg_assert_same(null, pg_cf_rule_other_field(array('required_when' => array('field' => 'shipping', 'answers' => array('x'))), $fields), 'renamed');
	pg_assert_same(null, pg_cf_rule_other_field(array('min_date' => 'today'), $fields), 'no such rule');
	pg_assert_same('', pg_cf_rule_verdict($rules, '', null, '2026-10-30', true), 'a missing field requires nothing');
}

// ── Where it is read, written and checked ──────────────────────────────

function test_custom_form_rules_migration_step()
{
	$src = _test_cf_rules_source('includes/migrations/2026.11.0.php');
	pg_assert_contains("upgrade_2026_11_0_form_field_rules();", $src, 'the step is called');
	pg_assert_contains("install_add_column('form_fields', 'rules', \"TEXT NULL\")", $src, 'the column by the idempotent helper');
	pg_assert_true(preg_match('/db\(\s*["\']ALTER TABLE form_fields/i', $src) !== 1, 'no bare ALTER');
}

function test_custom_form_rules_derived_from_the_control()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'form'), 'children' => array(
		_test_cf_rules_input('date', 'reservation_date', array('rules' => array('min_date' => 'today'))),
		_test_cf_rules_input('text', 'address', array('rules' => array('required_when' => array('field' => 'delivery', 'answers' => array('Delivered to us'))))),
		_test_cf_rules_input('text', 'note', array('rules' => array('min_date' => 'today'))),
		_test_cf_rules_input('month', 'month', array('rules' => array('min_date' => 'today'))),
		_test_cf_rules_input('text', 'plain'),
	));
	pg_assert_same(array('min_date' => 'today'), _test_cf_rules_of_tree($tree, 'reservation_date'), 'a date control');
	pg_assert_same(array('required_when' => array('field' => 'delivery', 'answers' => array('Delivered to us'))),
		_test_cf_rules_of_tree($tree, 'address'), 'a text control');
	pg_assert_same(array(), _test_cf_rules_of_tree($tree, 'note'), 'not before today is for a date control');
	pg_assert_same(array(), _test_cf_rules_of_tree($tree, 'month'), 'nor a month control');
	pg_assert_same(array(), _test_cf_rules_of_tree($tree, 'plain'), 'no rule');
}

function test_custom_form_rules_sync_and_site_check()
{
	$fn = _test_cf_rules_source('includes/fn/custom_form.php');
	pg_assert_contains("\$set['rules'] = pg_cf_rules_json(", $fn, 'the sync writes the column');
	$site = _test_cf_rules_source('custom_form.php');
	pg_assert_contains('pg_cf_check_rules($liveform, $field, $fields', $site, 'site submissions are checked');
	pg_assert_true(strpos($site, 'pg_cf_check_rules(') > strpos($site, 'validate_date('), 'after the type checks');
}

// Staff paths are exempt: the panel edit, and the collection check it shares.
function test_custom_form_rules_staff_paths_exempt()
{
	pg_assert_true(strpos(_test_cf_rules_source('includes/forms/submitted_form_form.php'), 'pg_cf_check_rules') === false, 'panel edit');
	$coll = _test_cf_rules_source('includes/fn/collections.php');
	$start = strpos($coll, 'function pg_coll_check_field(');
	pg_assert_true($start !== false, 'pg_coll_check_field() exists');
	$end = strpos($coll, "\nfunction ", $start + 10);
	pg_assert_true(strpos(substr($coll, $start, $end === false ? null : $end - $start), 'rules') === false, 'pg_coll_check_field() applies no rule');
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/fn/api*.php') as $f) {
		pg_assert_true(strpos((string)file_get_contents($f), 'pg_cf_check_rules') === false, basename($f));
	}
}

// ── Templates ──────────────────────────────────────────────────────────

function _test_cf_rules_template_node($id, $node_id)
{
	$tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[] = isset($p['tree']) ? $p['tree'] : null;
	foreach ((isset($tpl['widgets']) ? $tpl['widgets'] : array()) as $w) $trees[] = isset($w['tree']) ? $w['tree'] : null;
	$stack = $trees;
	while ($stack) {
		$n = array_pop($stack);
		if (!is_array($n)) continue;
		if (isset($n['props']['id']) && $n['props']['id'] === $node_id) return $n;
		if (!empty($n['children']) && is_array($n['children'])) foreach ($n['children'] as $c) $stack[] = $c;
	}
	return null;
}

function test_custom_form_rules_templates()
{
	foreach (array(
		array('pinegrap-cafe', 'cf-res-date'),
		array('pinegrap-cafe', 'cf-quote-date'),
		array('pinegrap-clinic', 'cl-appt-date'),
	) as $c) {
		$node = _test_cf_rules_template_node($c[0], $c[1]);
		pg_assert_true($node !== null, $c[1] . ' exists');
		pg_assert_same(array('min_date' => 'today'), pg_cf_rules_clean($node['props']['_cf']['rules'], 'date'), $c[0] . ' ' . $c[1]);
	}

	$address = _test_cf_rules_template_node('pinegrap-cafe', 'cf-quote-address');
	pg_assert_same(array('required_when' => array('field' => 'delivery', 'answers' => array(lang('Delivered to us')))),
		pg_cf_rules_clean($address['props']['_cf']['rules']), 'the delivery address');
	$delivered = _test_cf_rules_template_node('pinegrap-cafe', 'cf-quote-delivery-1');
	pg_assert_true($delivered !== null, 'the first delivery answer exists');
	$value = null;
	foreach ($delivered['props']['_attrs'] as $a) if ($a['name'] === 'value') $value = $a['value'];
	pg_assert_same(lang('Delivered to us'), $value, 'the rule names the answer as the control posts it');
}
