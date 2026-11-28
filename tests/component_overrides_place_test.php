<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A shared component's open fields at one place: what the save keeps of a
 * place's values (pg_co_clean_place(), pg_co_clean_tree_places()), a content
 * editor's place values taken by the content-role gate
 * (pg_designer_merge_restricted_tree()), and the "This place" rules of the
 * part assets/js/style_designer_overrides.js run in Node with a stub kit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');

function test_component_overrides_place_clean_keeps_fitting_values()
{
	$clean = pg_co_clean_place(array(
		'of_title' => 'Lunch',
		'of_pic' => array('src' => '/b.jpg', 'alt' => 'B', 'width' => 9),
		'of_note' => false,
		'Bad id' => 'x',
		'of_num' => 42,
		'of_arr' => array('x' => 1),
	));
	pg_assert_same(array('of_title' => 'Lunch', 'of_pic' => array('src' => '/b.jpg', 'alt' => 'B'), 'of_note' => false), $clean,
		'only valid ids with a text, a switch or a picture are kept');
	pg_assert_same(array(), pg_co_clean_place('nope'), 'no object keeps nothing');
}

function test_component_overrides_place_clean_tree()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('_id' => 'a', 'type' => 'shared_ref', 'props' => array('sharedId' => 5, '_place' => array('of_t' => 'Hi', 'Bad' => 1)), 'children' => array()),
		array('_id' => 'b', 'type' => 'container', 'props' => array(), 'children' => array(
			array('_id' => 'c', 'type' => 'shared_ref', 'props' => array('sharedId' => 5, '_place' => array('Bad' => 1)), 'children' => array()),
		)),
	));
	pg_co_clean_tree_places($tree);
	pg_assert_same(array('of_t' => 'Hi'), $tree['children'][0]['props']['_place'], 'a place keeps its fitting values');
	pg_assert_true(!isset($tree['children'][1]['children'][0]['props']['_place']), 'a place left with nothing keeps no _place');
}

// A content editor changes a place's open fields on a page that is theirs;
// the component reference itself stays as stored.
function test_component_overrides_place_content_editor_save()
{
	$stored = array('type' => 'root', 'props' => array(), 'children' => array(
		array('_id' => 'r1', 'type' => 'shared_ref', 'props' => array('sharedId' => 5, '_place' => array('of_t' => 'Old')), 'children' => array()),
		array('_id' => 'r2', 'type' => 'shared_ref', 'props' => array('sharedId' => 5), 'children' => array()),
	));
	$sent = array('type' => 'root', 'props' => array(), 'children' => array(
		array('_id' => 'r1', 'type' => 'shared_ref', 'props' => array('sharedId' => 9, 'cssClass' => 'x', '_place' => array('of_t' => '<!--pg-shared-ref:3--> New')), 'children' => array()),
		array('_id' => 'r2', 'type' => 'shared_ref', 'props' => array('sharedId' => 5, '_place' => array('of_pic' => array('src' => '/c.jpg'))), 'children' => array()),
	));
	$out = pg_designer_merge_restricted_tree($stored, $sent);
	pg_assert_same(5, $out['children'][0]['props']['sharedId'], 'the component it places stays as stored');
	pg_assert_true(!isset($out['children'][0]['props']['cssClass']), 'nothing else of the place is taken');
	pg_assert_same('<!-- pg-shared-ref:3--> New', $out['children'][0]['props']['_place']['of_t'], 'a place text is taken, its markers made inert');
	pg_assert_same(array('of_pic' => array('src' => '/c.jpg')), $out['children'][1]['props']['_place'], 'a place value is added');

	unset($sent['children'][0]['props']['_place']);
	$out = pg_designer_merge_restricted_tree($stored, $sent);
	pg_assert_true(!isset($out['children'][0]['props']['_place']), 'reset to the component removes the values');
}

// Runs the part in Node with a stub kit; `out` decoded, null without Node.
// `$kit` runs after the stub kit is made and before the part takes it.
function component_overrides_place_node($code, $kit = '')
{
	$pre = "var StyleDesigner = { definePart: function (n, f) { part = f(kit); } }, part = null;\n"
		. "var kit = { _sdT: function (k, v) { return v === undefined ? k : k.replace('{var}', v); }, esc: function (s) { return String(s); },\n"
		. " getNodeLabel: function (n) { return n._id; }, sharedCache: {} };\n"
		. $kit . "\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_overrides.js') . "\n";
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
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_component_overrides.js') . "\n"
		. "var out = null;\n" . $pre . $code . "\n"
		. 'console.log(JSON.stringify(out));';
	$file = @tempnam(sys_get_temp_dir(), 'pgcp');
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

function test_component_overrides_place_part_rules()
{
	$code = "var title = {id: 'of_t', kind: 'text', value: 'Our menu'}, link = {id: 'of_l', kind: 'link', value: '/book'},\n"
		. " pic = {id: 'of_p', kind: 'picture', value: {src: '/a.jpg', alt: 'A'}}, shown = {id: 'of_s', kind: 'shown', value: true};\n"
		. "out = {\n"
		. " text: part.placeSet({}, title, 'Lunch'),\n"
		. " same: part.placeSet({of_t: 'Lunch'}, title, 'Our menu'),\n"
		. " empty: part.placeSet({of_t: 'Lunch', of_l: '/x'}, title, '  '),\n"
		. " bad: part.placeSet({}, link, 'javascript:alert(1)'),\n"
		. " link: part.placeSet({}, link, ' /order '),\n"
		. " pic: part.placeSet({}, pic, '/b.jpg'),\n"
		. " hide: part.placeSet({}, shown, false),\n"
		. " show: part.placeSet({of_s: false}, shown, true),\n"
		. " changed: [part.placeChanged({of_t: 'x'}, title), part.placeChanged({of_t: 'x'}, link), part.placeChanged({of_s: true}, shown)],\n"
		. " reset: part.placeReset({of_t: 'x', of_l: '/y'}, 'of_t'), resetLast: part.placeReset({of_t: 'x'}, 'of_t')\n"
		. "};";
	$js = component_overrides_place_node($code);
	if ($js === null) {
		return;
	}
	pg_assert_same(array('values' => array('of_t' => 'Lunch'), 'refused' => ''), $js['text'], 'a typed text becomes this place\'s own');
	pg_assert_same(array('values' => null, 'refused' => ''), $js['same'], 'the component\'s own value is no change');
	pg_assert_same(array('values' => array('of_l' => '/x'), 'refused' => ''), $js['empty'], 'clearing a field resets it');
	pg_assert_true($js['bad']['refused'] !== '' && $js['bad']['values'] === null, 'a link that runs a script is refused and not kept');
	pg_assert_same(array('of_l' => '/order'), $js['link']['values'], 'a link is kept trimmed');
	pg_assert_same(array('of_p' => array('src' => '/b.jpg')), $js['pic']['values'], 'a picture keeps its address');
	pg_assert_same(array('of_s' => false), $js['hide']['values'], 'hiding the element here is a change');
	pg_assert_same(null, $js['show']['values'], 'shown as the component is no change');
	pg_assert_same(array(true, false, false), $js['changed'], 'a row is changed while its value differs from the component');
	pg_assert_same(array('of_l' => '/y'), $js['reset'], 'reset drops one value');
	pg_assert_same(null, $js['resetLast'], 'reset of the last value leaves none');
}

// The core: "This place" first in the panel of a placed component with an
// open field, its elements read-only until "Edit the component".
function test_component_overrides_place_core_entry_points()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($src, "_sdCoPlaceSect(n, sid, cached)) +") !== false, 'This place is drawn before the Shared Component section');
	pg_assert_true(preg_match('/function _sdCanEditNode\(node, what\) \{\s*if \(!_collab\.mayEdit\) return false;\s*if \(_sdCoLocked\(node\)\) return false;/', $src) === 1,
		'an element of a component with open fields is read-only until Edit the component');
	pg_assert_true(strpos($src, "_sdPartCall('overrides', 'fillPlace'") !== false, 'the rows are the part\'s, loaded on first use');
	pg_assert_true(strpos($src, "id: 'act_edit_component'") !== false, 'the command palette has Edit the component');
}

// A refused address under "This place" shows its message; Escape gives the
// field back what it held, and the message goes with the rose mark.
function test_component_overrides_place_escape_clears_the_refusal()
{
	$code = "var cls = {}, why = { textContent: '', style: {} };\n"
		. "var ctl = { value: '', defaultValue: '', tagName: 'INPUT', type: 'text', blur: function () {},\n"
		. " getAttribute: function (a) { return a === 'data-co-field' ? 'of_l' : null; }, hasAttribute: function () { return false; },\n"
		. " classList: { add: function (c) { cls[c] = true; }, remove: function (c) { delete cls[c]; } } };\n"
		. "var on = {}, holder = { set innerHTML(v) {}, closest: function () { return null; }, addEventListener: function (t, f) { on[t] = f; },\n"
		. " querySelector: function (s) { return /data-co-why/.test(s) ? why : (/data-co-field/.test(s) ? ctl : null); } };\n"
		. "var comp = {_id: 'c', type: 'root', props: {}, children: [{_id: 'b', type: 'content', props: {contentType: 'link', text: 'Book', href: '/book',\n"
		. " _open: [{id: 'of_l', kind: 'link', label: 'Link'}]}, children: []}]};\n"
		. "kit.sharedCache = {5: {tree: comp}}; kit.saveState = function () {}; kit.render = function () {};\n"
		. "var ref = {_id: 'p', type: 'shared_ref', props: {sharedId: 5}, children: []};\n"
		. "part.fillPlace(holder, ref, 5, {});\n"
		. "ctl.value = 'javascript:alert(1)';\n"
		. "on.change({ target: ctl, stopPropagation: function () {} });\n"
		. "out = { shown: why.textContent !== '' && why.style.display === 'block' && !!cls['is-invalid'] };\n"
		. "on.keydown({ key: 'Escape', target: ctl, preventDefault: function () {}, stopPropagation: function () {} });\n"
		. "out.after = { text: why.textContent, display: why.style.display, invalid: !!cls['is-invalid'], value: ctl.value, kept: !ref.props._place };";
	$js = component_overrides_place_node($code);
	if ($js === null) {
		return;
	}
	pg_assert_true($js['shown'], 'the refusal is shown');
	pg_assert_same(array('text' => '', 'display' => '', 'invalid' => false, 'value' => '', 'kept' => true), $js['after'],
		'Escape leaves no message behind and keeps nothing');
}
