<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual designer's half of parts placed outside their detail
 * widget: the widget kinds equal the server's (pg_sw_away_kinds()), and
 * _sdPartOf(), _sdPartRefusal() and _sdPartNode() (run under Node) answer as
 * pg_sw_away_of() and pg_sw_away_read_only() do, so the editor refuses what
 * the server would not draw and writes the node shape the server reads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _wpad_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The pure helpers, from SD_PART_KINDS to the end of _sdPartNode().
function _wpad_block()
{
	$js = _wpad_js();
	$start = strpos($js, "\n    var SD_PART_KINDS = ");
	$fn = ($start === false) ? false : strpos($js, "\n    function _sdPartNode(", $start);
	$end = ($fn === false) ? false : strpos($js, "\n    }\n", $fn);
	return ($start === false || $end === false) ? '' : substr($js, $start, $end - $start + 6);
}

// $script run under Node after the helpers: its JSON answer, or null when
// Node is missing.
function _wpad_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$file = tempnam(sys_get_temp_dir(), 'pgwpad');
	file_put_contents($file, _wpad_block() . "\n" . $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	return json_decode(implode("\n", $out), true);
}

function _wpad_el($tag, $children = array(), $props = array())
{
	return array('type' => 'semantic', 'props' => array('tag' => $tag) + $props, 'children' => $children);
}

// The editor offers parts of the same widget kinds the server draws.
function test_widget_parts_anywhere_designer_kinds_twin()
{
	pg_assert_true(_wpad_block() !== '', 'the helpers are found');
	pg_assert_true(preg_match("/var SD_PART_KINDS = \\[(.*?)\\];/", _wpad_js(), $m) === 1, 'SD_PART_KINDS is found');
	preg_match_all("/'([a-z_]+)'/", $m[1], $kinds);
	pg_assert_same(pg_sw_away_kinds(), $kinds[1]);
}

// Each node: the editor's refusal is '' exactly where the server draws the
// part, with the reason the editor names; partOf reads alike.
function test_widget_parts_anywhere_designer_refusal_twin()
{
	$price = _wpad_el('div', array(), array('_bindings' => array('text' => '__price_formatted')));
	$cases = array(
		array($price, ''),
		array(_wpad_el('div', array($price, _wpad_el('img', array(), array('_bindings' => array('src' => '__image_url'))))), ''),
		array(_wpad_el('nav', array(_wpad_el('ol', array(_wpad_el('li'))))), ''),
		array(_wpad_el('div', array(_wpad_el('input'))), 'control'),
		array(_wpad_el('FORM'), 'control'),
		array(_wpad_el('div', array(_wpad_el('span'), _wpad_el('button'))), 'control'),
		array(_wpad_el('select'), 'control'),
		array(_wpad_el('script'), 'control'),
		array(array('type' => 'content', 'props' => array('contentType' => 'messages'), 'children' => array()), 'messages'),
		array(array('type' => 'content', 'props' => array('contentType' => 'Custom_PHP'), 'children' => array()), 'code'),
		array(array('type' => 'content', 'props' => array('contentType' => 'variant_attr'), 'children' => array()), 'control'),
		array(array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'Hi'), 'children' => array()), ''),
		array(_wpad_el('div', array(), array('_bindings' => array('action' => 'add_to_cart'))), 'action'),
		array(_wpad_el('div', array(), array('_bindings' => array('section' => 'eo_totals'))), 'action'),
		array(_wpad_el('div', array(), array('_bindings' => array('action' => ''))), ''),
		array(_wpad_el('div', array(), array('_bindings' => array('action' => '0'))), ''),
		array(_wpad_el('div', array(), array('_bindings' => array('section' => array()))), ''),
		array(array('type' => 'shared_ref', 'props' => array('sharedId' => 4), 'children' => array()), 'shared'),
		array(array('type' => 'region', 'props' => array(), 'children' => array()), 'region'),
		array(array('type' => 'loop_area', 'props' => array(), 'children' => array()), 'loop'),
		array(array('type' => 'loop_area', 'props' => array('source' => ''), 'children' => array()), 'loop'),
		array(array('type' => 'loop_area', 'props' => array('source' => 'product.images'), 'children' => array($price)), ''),
		array(array('type' => 'recipient_loop_area', 'props' => array(), 'children' => array()), 'loop'),
		array(array('type' => 'semantic', 'children' => array()), ''),
	);
	$of = array(
		array('props' => array('partOf' => 12)),
		array('props' => array('partOf' => '12')),
		array('props' => array('partOf' => '007')),
		array('props' => array('partOf' => '')),
		array('props' => array('partOf' => 'x1')),
		array('props' => array('partOf' => -3)),
		array('props' => array('partOf' => 0)),
		array('props' => array('partOf' => 1.5)),
		array('props' => array()),
		array('type' => 'semantic'),
	);
	$nodes = array();
	foreach ($cases as $c) {
		$nodes[] = $c[0];
	}
	$res = _wpad_node('console.log(JSON.stringify({ why: ' . json_encode($nodes) . '.map(_sdPartRefusal), of: '
		. json_encode($of) . '.map(_sdPartOf) }));');
	if ($res === null) {
		return;
	}
	if (!pg_assert_true(is_array($res) && isset($res['why'], $res['of']), 'the script answers')) {
		return;
	}
	foreach ($cases as $i => $c) {
		pg_assert_same($c[1], $res['why'][$i], 'reason of case ' . $i);
		pg_assert_same(pg_sw_away_read_only($c[0]), $res['why'][$i] === '', 'server agrees on case ' . $i);
	}
	foreach ($of as $i => $node) {
		pg_assert_same(pg_sw_away_of($node), $res['of'][$i], 'partOf of case ' . $i);
	}
}

// The placed node carries partOf as the server reads it, fresh ids, and no
// partOf below it; the server writes its marker without partOf.
function test_widget_parts_anywhere_designer_node_shape()
{
	$inner = _wpad_el('span', array(), array('partOf' => 9, '_bindings' => array('text' => '__price_formatted')));
	$inner['_id'] = 'a2';
	$node = _wpad_el('div', array($inner), array('cssClass' => 'price'));
	$node['_id'] = 'a1';
	$res = _wpad_node('var n = 0; var src = ' . json_encode($node) . ';'
		. 'var part = _sdPartNode(src, "31", function () { return "n" + (++n); });'
		. 'console.log(JSON.stringify({ part: part, src: src }));');
	if ($res === null) {
		return;
	}
	if (!pg_assert_true(is_array($res) && isset($res['part']), 'the script answers')) {
		return;
	}
	$part = $res['part'];
	pg_assert_same(31, $part['props']['partOf']);
	pg_assert_same(31, pg_sw_away_of($part));
	pg_assert_same('n1', $part['_id']);
	pg_assert_same('n2', $part['children'][0]['_id']);
	pg_assert_false(isset($part['children'][0]['props']['partOf']), 'no partOf below the part');
	pg_assert_same('price', $part['props']['cssClass']);
	pg_assert_same($node, $res['src'], 'the source node is left as it was');
	$marker = pg_sw_away_marker($part);
	pg_assert_contains('<!--pg-wpart:31:', $marker);
	pg_assert_false(strpos(base64_decode(strtr(substr($marker, strlen('<!--pg-wpart:31:'), -3), '-_', '+/')), 'partOf') !== false, 'the marker holds no partOf');
}
