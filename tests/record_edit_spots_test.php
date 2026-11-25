<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Which bound elements of a page the designer's Preview lets a person
 * correct in place, and with which control
 * (assets/js/pg_record_edit_spots.js, window.PGRecordEditSpots). Runs in
 * Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PGRecordEditSpots loaded as R and the
// fixtures below as F; its `out` comes back decoded. Null when Node cannot
// run.
function record_edit_spots_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_record_edit_spots.js') . "\n"
		. 'var R = window.PGRecordEditSpots, out = null;' . "\n"
		. 'var F = ' . json_encode(record_edit_spots_fixtures()) . ";\n"
		. "function lookup(n) { return F.widgets[n.props.sharedId] || null; }\n"
		. "function brief(list) { return list.map(function (s) { return [s.node, s.prop, s.field, s.control, s.widget]; }); }\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgres');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function record_edit_spots_node_of($id, $props, $children = array())
{
	return array('_id' => $id, 'type' => 'content', 'props' => $props, 'children' => $children);
}

// The fields record_edit_read answers for an item of form 7 and the widgets
// of a page: 10 the record's detail widget, 11 a list of the same form, 12
// a detail widget of another form, 13 a shared component.
function record_edit_spots_fixtures()
{
	$n = 'record_edit_spots_node_of';

	return array(
		'fields' => array(
			array('id' => 1, 'name' => 'Title', 'label' => 'Title', 'control' => 'text', 'rss' => 'title'),
			array('id' => 2, 'name' => 'body', 'label' => 'Story', 'control' => 'rich', 'rss' => 'description'),
			array('id' => 3, 'name' => 'price', 'label' => 'Price', 'control' => 'number', 'rss' => ''),
			array('id' => 4, 'name' => 'publish-date', 'label' => 'Publish date', 'control' => 'date', 'rss' => ''),
			array('id' => 5, 'name' => 'cover', 'label' => 'Cover', 'control' => 'picture', 'rss' => 'media'),
			array('id' => 6, 'name' => 'title_', 'label' => 'Second', 'control' => 'text', 'rss' => ''),
		),
		'widgets' => array(
			'10' => array('kind' => 'form_item_view', 'formId' => 7, 'tree' => $n('w10', array(), array(
				$n('h', array('_bindings' => array('text' => 'title'))),
				$n('b', array('_bindings' => array('html' => 'body'))),
				$n('p', array('_bindings' => array('text' => 'price'))),
				$n('d', array('_bindings' => array('text' => 'publishdate'), '_bindFormats' => array('text' => 'd.m.Y'))),
				$n('i', array('_bindings' => array('src' => 'cover', 'alt' => 'title'))),
				$n('l', array('_bindings' => array('text' => 'Story'))),
				$n('k', array('_bindings' => array('text' => 'field_6'))),
				$n('pt', array('_bindings' => array('text' => '__post_title'))),
				$n('pi', array('_bindings' => array('src' => '__post_image'))),
				$n('ps', array('_bindings' => array('text' => '__post_summary'))),
				$n('x', array('_bindings' => array('text' => 'cover', 'href' => 'title'))),
				$n('u', array('_bindings' => array('text' => 'unknown'))),
			))),
			'11' => array('kind' => 'form_list_view', 'formId' => 7, 'tree' => $n('w11', array(), array(
				$n('lh', array('_bindings' => array('text' => 'title'))),
			))),
			'12' => array('kind' => 'form_item_view', 'formId' => 8, 'tree' => $n('w12', array(), array(
				$n('oh', array('_bindings' => array('text' => 'title'))),
			))),
			'13' => array('kind' => '', 'formId' => 0, 'tree' => $n('c13', array(), array(
				$n('ch', array('_bindings' => array('text' => '__record__field_title'))),
			))),
		),
	);
}

function record_edit_spots_page($first_widget)
{
	$n = 'record_edit_spots_node_of';
	$ref = function ($id, $sid) {
		return array('_id' => $id, 'type' => 'shared_ref', 'props' => array('sharedId' => $sid), 'children' => array());
	};

	return $n('root', array(), array(
		$ref('r' . $first_widget, $first_widget),
		$n('out1', array('_bindings' => array('text' => '__record__field_title'))),
		$n('out2', array('_bindings' => array('text' => '__record__field_publishdate'))),
		$n('out3', array('_bindings' => array('src' => '__record__post_image', 'text' => '__record__post_title'))),
		$n('out4', array('_bindings' => array('text' => '__record__name'))),
		$n('out5', array('_bindings' => array('text' => 'title'))),
		$ref('r13', 13),
		$ref('r11', 11),
		$ref('r12', 12),
		$ref('r10b', 10),
	));
}

// Inside the record's own detail widget each bound element is found by the
// field it names (name, the name without its hyphen, label, field_<id>,
// the RSS title and picture), with the field's control on the property
// that control writes; an address, a summary and a mismatch are not.
function test_record_edit_spots_inside_the_detail_widget()
{
	$out = record_edit_spots_node(
		'out = brief(R.find(F.widgets[10].tree, F.fields, { formId: 7, lookup: lookup, scope: "widget" }));'
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(
		array('h', 'text', 1, 'text', ''),
		array('b', 'html', 2, 'rich', ''),
		array('p', 'text', 3, 'number', ''),
		array('d', 'text', 4, 'date', ''),
		array('i', 'src', 5, 'picture', ''),
		array('i', 'alt', 1, 'text', ''),
		array('l', 'text', 2, 'rich', ''),
		array('k', 'text', 6, 'text', ''),
		array('pt', 'text', 1, 'text', ''),
		array('pi', 'src', 5, 'picture', ''),
	), $out);
}

// A page: the first record widget opens the page's record, so the
// elements outside widgets bound to __record__field_<name> or the RSS
// title and picture print this record, in a shared component too; a list
// and another form's detail widget are not walked; a second copy of the
// detail widget is found again under its own reference.
function test_record_edit_spots_page_record_and_widgets()
{
	$page = json_encode(record_edit_spots_page(10));
	$out = record_edit_spots_node(
		'out = brief(R.find(' . $page . ', F.fields, { formId: 7, lookup: lookup }));'
	);

	if ($out === null) {
		return;
	}

	$expected = array(
		array('h', 'text', 1, 'text', 'r10'),
		array('b', 'html', 2, 'rich', 'r10'),
		array('p', 'text', 3, 'number', 'r10'),
		array('d', 'text', 4, 'date', 'r10'),
		array('i', 'src', 5, 'picture', 'r10'),
		array('i', 'alt', 1, 'text', 'r10'),
		array('l', 'text', 2, 'rich', 'r10'),
		array('k', 'text', 6, 'text', 'r10'),
		array('pt', 'text', 1, 'text', 'r10'),
		array('pi', 'src', 5, 'picture', 'r10'),
		array('out1', 'text', 1, 'text', ''),
		array('out2', 'text', 4, 'date', ''),
		array('out3', 'src', 5, 'picture', ''),
		array('out3', 'text', 1, 'text', ''),
		array('ch', 'text', 1, 'text', 'r13'),
	);

	foreach ($expected as $row) {
		if ($row[4] === 'r10') {
			$copy = $row;
			$copy[4] = 'r10b';
			$expected[] = $copy;
		}
	}

	pg_assert_same($expected, $out, 'the record widget, the page record outside and in a component, a second copy');
}

// When the page's first record widget opens another form's record (or a
// product), the elements outside widgets print that record: none of them
// is this record's.
function test_record_edit_spots_page_record_of_another_form()
{
	$page = json_encode(record_edit_spots_page(12));
	$out = record_edit_spots_node(
		'out = brief(R.find(' . $page . ', F.fields, { formId: 7, lookup: lookup })).filter(function (r) { return r[4] === "" || r[4] === "r13"; });'
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(), $out);
}

// The controls each bound property takes: text the four text controls,
// html formatted text only, src a picture only; alt plain text only.
function test_record_edit_spots_control_per_property()
{
	$out = record_edit_spots_node(
		'out = ["text","html","src","alt","href","value"].map(function (p) {'
		. ' return ["text","rich","number","date","picture"].filter(function (c) { return R.accepts(p, c); }); });'
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(
		array('text', 'rich', 'number', 'date'),
		array('rich'),
		array('picture'),
		array('text'),
		array(),
		array(),
	), $out);
}

// Empty input finds nothing and does not throw; a field without a control
// is never matched; a page with no record widget has no record unless the
// caller says it opens this one (pageRecord).
function test_record_edit_spots_edges()
{
	$out = record_edit_spots_node(
		'out = [R.find(null, F.fields, {}), R.find(F.widgets[10].tree, null, { formId: 7, scope: "widget" }),'
		. ' brief(R.find({ _id: "z", props: { _bindings: { text: "title" } } }, [{ id: 9, name: "Title", control: "" }], { formId: 7, scope: "widget" })),'
		. ' brief(R.find({ _id: "q", props: { _bindings: { text: "__record__field_title" } } }, F.fields, { formId: 7 })),'
		. ' brief(R.find({ _id: "q", props: { _bindings: { text: "__record__field_title" } } }, F.fields, { formId: 7, pageRecord: true }))];'
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(array(), array(), array(), array(), array(array('q', 'text', 1, 'text', ''))), $out);
}
