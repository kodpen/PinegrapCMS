<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the rule editor of contact groups filled by rules
 * (includes/fn/audiences.php): the catalogue the screen draws from, the
 * ids a rule set names, the posted kind and rules, the count text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_audiences_editor_catalogue_covers_every_condition()
{
	$data = pg_audience_editor_data();
	$catalogue = pg_audience_conditions();
	$operators = pg_audience_operators();

	pg_assert_same(array_keys($catalogue), array_map(function ($c) { return $c['key']; }, $data['conditions']), 'every condition, catalogue order');

	foreach ($data['conditions'] as $condition) {
		$entry = $catalogue[$condition['key']];
		pg_assert_same($entry['value'], $condition['value'], $condition['key'] . ' value kind');
		pg_assert_same(!empty($entry['window']), $condition['window'], $condition['key'] . ' window');
		pg_assert_same($entry['ops'], array_map(function ($o) { return $o['op']; }, $condition['ops']), $condition['key'] . ' operators');

		foreach ($condition['ops'] as $op) {
			pg_assert_same($operators[$op['op']], $op['label']);
		}

		pg_assert_true(isset($data['families'][$condition['family']]), $condition['key'] . ' family label');
	}

	pg_assert_same(array_keys(pg_audience_contact_fields()), array_map(function ($o) { return $o['id']; }, $data['fields']));
	pg_assert_same(array_keys(pg_audience_consents()), array_map(function ($o) { return $o['id']; }, $data['consents']));
	pg_assert_same(pg_audience_subscription_statuses(), array_map(function ($o) { return $o['id']; }, $data['statuses']));
	pg_assert_same(PG_AUDIENCE_MAX_CONDITIONS, $data['max']);
}

function test_audiences_editor_refs_collects_ids_by_kind()
{
	$rules = array('match' => 'all', 'conditions' => array(
		array('key' => 'bought_product', 'op' => 'yes', 'value' => 5),
		array('key' => 'group', 'op' => 'in', 'value' => 3),
		array('match' => 'any', 'conditions' => array(
			array('key' => 'bought_product', 'op' => 'no', 'value' => '9'),
			array('key' => 'bought_product', 'op' => 'yes', 'value' => 5),
			array('key' => 'bought_product_group', 'op' => 'yes', 'value' => 2),
			array('key' => 'submitted_form', 'op' => 'yes', 'value' => 40),
			array('key' => 'form_field', 'op' => 'eq', 'field' => 77, 'value' => 'Yes'),
		)),
		array('key' => 'country', 'op' => 'eq', 'value' => 'DE'),
	));

	pg_assert_same(
		array('product' => array(5, 9), 'product_group' => array(2), 'contact_group' => array(3), 'form' => array(40), 'form_field' => array(77)),
		pg_audience_rules_refs($rules),
		'ids once each, nested set included, a form answer by its field'
	);

	pg_assert_same(
		array('product' => array(), 'product_group' => array(), 'contact_group' => array(), 'form' => array(), 'form_field' => array()),
		pg_audience_rules_refs('not json'),
		'nothing named'
	);
}

function test_audiences_editor_posted_kind_and_rules()
{
	$rules = '{"match":"any","conditions":[{"key":"customer","op":"yes","junk":1},{"key":"nope","op":"eq"}]}';
	$posted = pg_audience_editor_posted(array('kind' => 'dynamic', 'rules' => $rules));

	pg_assert_same('dynamic', $posted['kind']);
	pg_assert_same('{"match":"any","conditions":[{"key":"customer","op":"yes"}]}', $posted['rules'], 'stored cleaned');
	pg_assert_same(1, $posted['count'], 'one condition left');

	$static = pg_audience_editor_posted(array('rules' => $rules));
	pg_assert_same('static', $static['kind'], 'no switch is static');
	pg_assert_same($posted['rules'], $static['rules'], 'the rules are kept for a later switch back');

	$hostile = pg_audience_editor_posted(array('kind' => array('dynamic'), 'rules' => array('x')));
	pg_assert_same('static', $hostile['kind']);
	pg_assert_same('{"match":"all","conditions":[]}', $hostile['rules']);
	pg_assert_same(0, $hostile['count']);
}

function test_audiences_editor_rules_changed()
{
	$a = '{"match":"all","conditions":[{"key":"customer","op":"yes"}]}';

	pg_assert_true(!pg_audience_rules_changed($a, '{"conditions":[{"op":"yes","key":"customer"}]}'), 'same rules in another spelling');
	pg_assert_true(pg_audience_rules_changed($a, '{"match":"any","conditions":[{"key":"customer","op":"yes"}]}'), 'match changed');
	pg_assert_true(pg_audience_rules_changed(null, $a), 'first rules');
}

function test_audiences_editor_count_text()
{
	pg_assert_same('', pg_audience_count_text(null), 'no count, no text');
	pg_assert_same('No contacts', pg_audience_count_text(0));
	pg_assert_same('1 contact', pg_audience_count_text(1));
	pg_assert_same('87 contacts', pg_audience_count_text(87), 'small counts exact');
	pg_assert_same('about 1,240 contacts', pg_audience_count_text(1238));
	pg_assert_same('about 45,700 contacts', pg_audience_count_text(45678));
	pg_assert_same('about 1,235,000 contacts', pg_audience_count_text(1234567));
}

// The "Contact field" condition names its columns as the contact form does:
// contacts.title is the job title, member_id the site's member ID label.
function test_audiences_contact_field_labels_match_contact_form()
{
	$fields = pg_audience_contact_fields();

	pg_assert_same(lang('Job Title'), $fields['title'], 'title is the job title');
	pg_assert_same((defined('MEMBER_ID_LABEL') && (trim((string) MEMBER_ID_LABEL) !== '')) ? trim((string) MEMBER_ID_LABEL) : lang('Member ID'), $fields['member_id'], 'member id follows the site label');
}
