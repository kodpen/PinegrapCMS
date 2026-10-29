<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a design template's reference field naming another tab's form
 * (props._cf.ref = 'tab:<key>'): the key resolved on Publish
 * (pg_sw_page_link_resolve_tabs() and the editor's _swResolveTreeTabRefs()),
 * the order the forms are written in (pg_cf_reconcile_order()) and the
 * design export and template fill (_pg_dtc_tokenize_props(), _pg_tpl_fill()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A form control (a pick list) whose `_cf` record names a form by $ref.
function _tpl_refs_control($ref, $name = 'category')
{
	return array(
		'type' => 'semantic',
		'props' => array(
			'tag' => 'select',
			'_attrs' => array(array('name' => 'name', 'value' => $name)),
			'_cf' => array('kind' => 'reference', 'ref' => $ref, 'ids' => array('12' => 4)),
		),
		'children' => array(),
	);
}

function test_collections_template_refs_resolve()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		_tpl_refs_control('tab:cats'),
		_tpl_refs_control('tab:gone', 'tag'),
		_tpl_refs_control(9, 'other'),
		array('type' => 'content', 'props' => array('text' => 'tab:cats', 'ref' => 'tab:cats'), 'children' => array()),
	));
	$out = pg_sw_page_link_resolve_tabs($tree, array('cats' => '31'));

	pg_assert_same(31, $out['children'][0]['props']['_cf']['ref'], 'a known key');
	pg_assert_same(0, $out['children'][1]['props']['_cf']['ref'], 'a closed tab: no form');
	pg_assert_same(9, $out['children'][2]['props']['_cf']['ref'], 'an id stays');
	pg_assert_same(array('12' => 4), $out['children'][0]['props']['_cf']['ids'], 'the rest of the record stays');
	pg_assert_same($tree['children'][3], $out['children'][3], 'a text or a prop outside _cf reading tab:');

	// What the save writes from it: a reference into the resolved form.
	$fields = _pg_cf_controls($out, 12);
	pg_assert_same(31, pg_coll_field_reference($fields['category']['kind'], $fields['category']['reference_page_id']));
	pg_assert_same(0, pg_coll_field_reference('reference', 'tab:cats'), 'an unresolved key is no form');

	$plain = array('type' => 'root', 'props' => array(), 'children' => array(_tpl_refs_control(9)));
	pg_assert_true(pg_sw_page_link_resolve_tabs($plain, array('cats' => 31)) === $plain, 'nothing to resolve: the same tree');
}

// The server's row filter finds a tree holding only a reference key.
function test_collections_template_refs_row_filter()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("tree_json LIKE '%\\\"ref\\\":\\\"tab:%'", $src);
	$json = pg_designer_tree_encode(array('type' => 'root', 'props' => array(), 'children' => array(_tpl_refs_control('tab:cats'))));
	pg_assert_contains('"ref":"tab:cats"', $json, 'the stored tree reads as the filter expects');
}

function test_collections_template_refs_editor_resolve()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$start = strpos($js, 'function _swResolveTreeTabRefs(');
	$end = ($start === false) ? false : strpos($js, '// After a save:', $start);
	pg_assert_true($start !== false && $end !== false, 'the editor has the resolver');

	$script = substr($js, $start, $end - $start)
		. 'var t = { props: {}, children: ['
		. '{ props: { _cf: { kind: "reference", ref: "tab:cats" } }, children: [] },'
		. '{ props: { _cf: { kind: "reference", ref: "tab:gone" } }, children: [] },'
		. '{ props: { _cf: { kind: "reference", ref: 9 }, text: "tab:cats" }, children: [] } ] };'
		. 'var changed = _swResolveTreeTabRefs(t, { cats: "31" });'
		. 'var again = _swResolveTreeTabRefs(t, { cats: "31" });'
		. 'console.log(JSON.stringify({ changed: changed, again: again, t: t }));';
	$file = tempnam(sys_get_temp_dir(), 'pgtplrefs');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	$answer = json_decode(implode("\n", $out), true);

	pg_assert_true($answer['changed'], 'a key was swapped');
	pg_assert_false($answer['again'], 'nothing left the second time');
	pg_assert_same(31, $answer['t']['children'][0]['props']['_cf']['ref']);
	pg_assert_same(0, $answer['t']['children'][1]['props']['_cf']['ref'], 'a closed tab');
	pg_assert_same(9, $answer['t']['children'][2]['props']['_cf']['ref']);
	pg_assert_same('tab:cats', $answer['t']['children'][2]['props']['text']);
}

function test_collections_template_refs_order()
{
	$none = array('order' => array(), 'again' => array());
	pg_assert_same($none, pg_cf_reconcile_order(array()));
	pg_assert_same($none, pg_cf_reconcile_order(null));

	pg_assert_same(
		array('order' => array(1, 2, 3), 'again' => array()),
		pg_cf_reconcile_order(array(1 => array(), 2 => array(), 3 => array())),
		'no references: the given order'
	);
	pg_assert_same(
		array('order' => array(3, 1, 2), 'again' => array()),
		pg_cf_reconcile_order(array(1 => array(3), 2 => array(), 3 => array())),
		'the target first'
	);
	pg_assert_same(
		array('order' => array(3, 2, 1), 'again' => array()),
		pg_cf_reconcile_order(array(1 => array(2), 2 => array(3), 3 => array())),
		'a chain'
	);
	pg_assert_same(
		array('order' => array(1, 2), 'again' => array()),
		pg_cf_reconcile_order(array(1 => array(1, 77), 2 => array('1'))),
		'itself and a form outside the save need nothing'
	);
	pg_assert_same(
		array('order' => array(2, 1, 3), 'again' => array(2)),
		pg_cf_reconcile_order(array(1 => array(2), 2 => array(1), 3 => array())),
		'a loop: the one written first is written again'
	);
}

function test_collections_template_refs_export_and_fill()
{
	$ctx = array(
		'page_urls' => array(), 'page_ids' => array(11 => 'blog_new', 12 => 'category_new'),
		'widgets_by_sid' => array(), 'shared_by_sid' => array(), 'folders_by_id' => array(), 'contact_groups_by_id' => array(),
		'site_name' => 'Acme', 'site_email' => 'a@example.com', 'year' => '2026',
	);
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		_tpl_refs_control(12),
		_tpl_refs_control('11', 'related'),
		_tpl_refs_control(40, 'outside'),
	));
	$out = pg_dtc_tokenize_tree($tree, $ctx);

	pg_assert_same('{{tab:category_new}}', $out['children'][0]['props']['_cf']['ref']);
	pg_assert_same('{{tab:blog_new}}', $out['children'][1]['props']['_cf']['ref'], 'digits too');
	pg_assert_same(40, $out['children'][2]['props']['_cf']['ref'], 'a form outside the design keeps its id');
	pg_assert_same('reference', $out['children'][0]['props']['_cf']['kind']);

	$filled = _pg_tpl_fill($out, array('tabs' => array('category_new' => 'tplabc_category_new'), 'pages' => array()));
	pg_assert_same('tab:tplabc_category_new', $filled['children'][0]['props']['_cf']['ref']);
	$resolved = pg_sw_page_link_resolve_tabs($filled, array('tplabc_category_new' => 55));
	pg_assert_same(55, $resolved['children'][0]['props']['_cf']['ref'], 'Publish gives the new form');
}
