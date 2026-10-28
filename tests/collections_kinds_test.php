<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for collection forms and the kinds of their fields
 * (includes/fn/collections.php, 2026.11.0 step 11.0.43): the kinds each
 * field type may have (pg_coll_field_kind()) and the editor's twin
 * (_CF_KINDS), the form a reference points into (pg_coll_field_reference()),
 * the `_cf` record a control carries (_pg_cf_node_record(),
 * _pg_cf_controls()) and the migration step.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _collections_kinds_control($tag, $attrs, $cf = null)
{
	$list = array();
	foreach ($attrs as $name => $value) {
		$list[] = array('name' => $name, 'value' => $value);
	}
	$props = array('tag' => $tag, '_attrs' => $list);
	if ($cf !== null) {
		$props['_cf'] = $cf;
	}
	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

function test_collections_field_kind()
{
	pg_assert_same('number', pg_coll_field_kind('number', 'text box'));
	pg_assert_same('number', pg_coll_field_kind(' Number ', 'text box'), 'trimmed and lowercased');
	pg_assert_same('image', pg_coll_field_kind('image', 'file upload'));
	foreach (array('text box', 'pick list', 'radio button', 'check box') as $type) {
		pg_assert_same('reference', pg_coll_field_kind('reference', $type), $type);
	}

	pg_assert_same('', pg_coll_field_kind('number', 'text area'), 'a kind the type cannot have');
	pg_assert_same('', pg_coll_field_kind('image', 'text box'));
	pg_assert_same('product', pg_coll_field_kind('product', 'pick list'));
	pg_assert_same('page', pg_coll_field_kind('page', 'pick list'));
	pg_assert_same('', pg_coll_field_kind('product', 'text box'), 'a product is picked from a list');
	pg_assert_same('', pg_coll_field_kind('page', 'radio button'));
	pg_assert_same('', pg_coll_field_kind('slug', 'text box'), 'not a kind (yet)');
	pg_assert_same('', pg_coll_field_kind(array('number'), 'text box'), 'not a string');
	pg_assert_same('', pg_coll_field_kind('', 'text box'));
}

function test_collections_field_reference()
{
	pg_assert_same(12, pg_coll_field_reference('reference', 12));
	pg_assert_same(12, pg_coll_field_reference('reference', '12'), 'digits');
	pg_assert_same(0, pg_coll_field_reference('number', 12), 'only a reference points anywhere');
	foreach (array(-3, '-3', 'tab:k', 'x', '', 1.5, null) as $value) {
		pg_assert_same(0, pg_coll_field_reference('reference', $value), var_export($value, true));
	}
}

function test_collections_controls_carry_kind_and_reference()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		_collections_kinds_control('input', array('type' => 'text', 'name' => 'category'), array('kind' => 'reference', 'ref' => 31)),
		_collections_kinds_control('input', array('type' => 'number', 'name' => 'price'), array('kind' => 'number')),
		_collections_kinds_control('input', array('type' => 'text', 'name' => 'title')),
	));
	$fields = _pg_cf_controls($tree, 0);

	pg_assert_same('reference', $fields['category']['kind']);
	pg_assert_same(31, $fields['category']['reference_page_id']);
	pg_assert_same('number', $fields['price']['kind']);
	pg_assert_same('', $fields['title']['kind'], 'no kind');
	pg_assert_same(0, $fields['title']['reference_page_id']);

	$record = _pg_cf_node_record(_collections_kinds_control('input', array(), array('kind' => array('x'), 'ref' => array(1))));
	pg_assert_same('', $record['kind'], 'a kind that is not a string is none');
	pg_assert_same(0, $record['ref'], 'a reference that is not a number is none');
}

// The editor offers the kinds the server keeps, for the same field types.
function test_collections_kinds_editor_twin()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_true(preg_match('/var _CF_KINDS = (\{.*?\});/s', $js, $m) === 1, 'the designer has _CF_KINDS');

	$json = preg_replace(array('/(\w+):/', "/'/"), array('"$1":', '"'), $m[1]);
	pg_assert_same(pg_coll_kinds(), json_decode($json, true));
}

function _collections_kinds_migration_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	return preg_match('/function upgrade_2026_11_0_collections\(\) \{(.*?)\n\}\n/s', $source, $m) ? $m[1] : '';
}

function test_collections_kinds_migration_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_collections();            // 11.0.43\n"), 'called once from upgrade_to_2026_11_0()');
	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);
	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);
	pg_assert_same(1, count(array_keys($labels[1], '11.0.43', true)), 'no other step carries 11.0.43');

	$step = _collections_kinds_migration_step();
	pg_assert_contains("install_add_column('custom_form_pages', 'collection', \"TINYINT(1) UNSIGNED NOT NULL DEFAULT 0\");", $step);
	pg_assert_contains("install_add_column('form_fields', 'collection_kind', \"VARCHAR(32) NOT NULL DEFAULT ''\");", $step);
	pg_assert_contains("install_add_column('form_fields', 'reference_page_id', \"INT(10) UNSIGNED NOT NULL DEFAULT 0\");", $step);
	pg_assert_true(stripos($step, 'ALTER TABLE') === false && strpos($step, 'db(') === false, 'no raw statement');
	pg_assert_true(preg_match("/install_note\\('(?:[^'\\\\]|\\\\.)+'\\);\\s*\$/", $step) === 1, 'ends with a note for the operator');
}
