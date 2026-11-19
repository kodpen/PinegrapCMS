<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page strip's plan (assets/js/style_designer.src.js,
 * _pgTabStripPlan() and _pgTabStripOpenGroup()): what the strip draws for
 * its width, the six steps that make room on a narrow strip, a design
 * without groups, "Other", the tabs behind "+N", and the group a page
 * opened from a chip opens. Run in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with the two functions loaded; its `out` comes back
// decoded. Null when Node cannot run.
function designer_tab_strip_node($code)
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

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$a = strpos($js, "    // ── The strip's plan ──");
	$b = ($a !== false) ? strpos($js, '    function _pgTabsRender(', $a) : false;
	pg_assert_true($b !== false, 'the strip\'s plan is in style_designer.src.js');

	if ($b === false) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgtabstrip');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, substr($js, $a, $b - $a) . "\nvar out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);
	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// The drawing's design: the open group "Store" with six tabs, the open page
// third, and four other groups (the last one "Other"). Every tab 80 px, a
// chip 90 px with its count and 70 px without; the design's name gives
// back 100 px as an icon.
function designer_tab_strip_grouped($widths)
{
	$js = 'var base = { gap: 4, designSave: 100, grouped: true, active: "p3", boxExtra: 12,'
		. ' head: { w: 70, wShort: 10 }, more: 36, moreGroups: 64, allGroups: 56,'
		. ' tabs: ["p1","p2","p3","p4","p5","p6"].map(function (k) { return { key: k, w: 80 }; }),'
		. ' chips: ["g2","g3","g4",""].map(function (id) { return { id: id, w: 90, wShort: 70 }; }) };'
		. ' out = ' . json_encode($widths) . '.map(function (w) { var o = Object.assign({}, base, { width: w }); return _pgTabStripPlan(o); });';

	return designer_tab_strip_node($js);
}

function test_designer_tab_strip_one_group_open_draws_everything_when_it_fits()
{
	$out = designer_tab_strip_grouped(array(1000));

	if ($out === null) {
		return;
	}

	pg_assert_same(0, $out[0]['step'], 'everything fits');
	pg_assert_same(array('p1', 'p2', 'p3', 'p4', 'p5', 'p6'), $out[0]['tabs'], 'the open group keeps all its tabs');
	pg_assert_same(array('g2', 'g3', 'g4', ''), $out[0]['chips'], 'every other group is a chip, "Other" last');
	pg_assert_true($out[0]['counts'] && $out[0]['headName'] && !$out[0]['more'], 'counts, the box\'s name, no "+N"');
}

function test_designer_tab_strip_six_steps_in_order()
{
	$out = designer_tab_strip_grouped(array(900, 680, 665, 515, 280, 200, 110, 50));

	if ($out === null) {
		return;
	}

	// Step 1: the chips lose their count, nothing else.
	pg_assert_same(1, $out[0]['step'], '900: step 1');
	pg_assert_true(!$out[0]['counts'] && count($out[0]['tabs']) === 6 && count($out[0]['chips']) === 4, '900: no counts, all tabs and chips');
	// Step 2: the far tabs go behind "+N", the three nearest stay.
	pg_assert_same(2, $out[1]['step'], '680: step 2');
	pg_assert_same(array('p2', 'p3', 'p4'), $out[1]['tabs'], '680: the open page and its neighbours stay');
	pg_assert_same(array('p1', 'p5', 'p6'), $out[1]['hidden'], '680: the others are behind "+N"');
	pg_assert_true($out[1]['more'], '680: "+N" is drawn');
	// Step 3: the chips fold from the right into "+N groups".
	pg_assert_same(3, $out[2]['step'], '665: step 3');
	pg_assert_same(array('g2', 'g3', 'g4'), $out[2]['chips'], '665: the last chip folded first');
	pg_assert_same(array(''), $out[2]['folded'], '665: "Other" is in "+N groups"');
	pg_assert_same('some', $out[2]['chipMode'], '665: "+N groups" is drawn');
	// Step 4: the design's name is an icon, the box loses its name.
	pg_assert_same(4, $out[3]['step'], '515: step 4');
	pg_assert_true($out[3]['designIcon'] && !$out[3]['headName'], '515: icon, no box name');
	pg_assert_same(4, $out[4]['step'], '280: still step 4');
	pg_assert_same(array('p3', 'p4'), $out[4]['tabs'], '280: two tabs, the open one and its right neighbour');
	pg_assert_same(array('g2'), $out[4]['chips'], '280: one chip, the rest in "+N groups"');
	// Step 5: all chips are one.
	pg_assert_same(5, $out[5]['step'], '200: step 5');
	pg_assert_true($out[5]['chipMode'] === 'one' && $out[5]['chips'] === array() && count($out[5]['folded']) === 4, '200: "4 groups"');
	pg_assert_same(5, $out[6]['step'], '110: step 5 with the open tab and "+N"');
	pg_assert_same(array('p3'), $out[6]['tabs'], '110: the open tab');
	// Step 6: the open tab alone, cut.
	pg_assert_same(6, $out[7]['step'], '50: step 6');
	pg_assert_true($out[7]['tabs'] === array('p3') && !$out[7]['more'] && $out[7]['chipMode'] === 'none' && $out[7]['cut'], '50: the open tab alone, cut, no chip');
}

function test_designer_tab_strip_without_groups_and_plus_n()
{
	$js = 'var base = { gap: 4, designSave: 100, grouped: false, active: "p2", more: 36,'
		. ' tabs: ["p1","p2","p3","p4","p5"].map(function (k) { return { key: k, w: 80 }; }) };'
		. ' out = [420, 300, 100, 10].map(function (w) { return _pgTabStripPlan(Object.assign({}, base, { width: w })); });';
	$out = designer_tab_strip_node($js);

	if ($out === null) {
		return;
	}

	pg_assert_true($out[0]['step'] === 0 && count($out[0]['tabs']) === 5 && $out[0]['chipMode'] === 'none' && !$out[0]['headName'], 'no box, no chips, every tab');
	pg_assert_same(array('p1', 'p2', 'p3'), $out[1]['tabs'], 'a tab that does not fit goes behind "+N"');
	pg_assert_same(array('p4', 'p5'), $out[1]['hidden'], 'the far ones are hidden first');
	pg_assert_true($out[1]['more'], '"+N" is drawn');
	pg_assert_true($out[2]['step'] === 4 && $out[2]['designIcon'] && $out[2]['tabs'] === array('p2'), 'narrower: the icon and the open tab with "+N"');
	pg_assert_true($out[3]['step'] === 6 && $out[3]['tabs'] === array('p2') && !$out[3]['more'], 'narrowest: the open tab alone');
}

function test_designer_tab_strip_other_and_opening_from_a_chip()
{
	$js = 'var pages = [{ key: "p1", tabGroup: "g1" }, { key: "p2", tabGroup: "g1" }, { key: "p3", tabGroup: "" }, { key: "p4", tabGroup: "g2" }];'
		. ' out = { first: _pgTabStripOpenGroup(pages, "p1"), other: _pgTabStripOpenGroup(pages, "p3"), fromChip: _pgTabStripOpenGroup(pages, "p4"),'
		. ' plan: _pgTabStripPlan({ width: 1000, gap: 4, grouped: true, active: "p3", boxExtra: 12, head: { w: 60, wShort: 10 }, more: 36, moreGroups: 64, allGroups: 56,'
		. ' tabs: [{ key: "p3", w: 80 }], chips: [{ id: "g1", w: 90, wShort: 70 }, { id: "g2", w: 90, wShort: 70 }] }) };';
	$out = designer_tab_strip_node($js);

	if ($out === null) {
		return;
	}

	pg_assert_same('g1', $out['first'], 'the open page\'s group is the open one');
	pg_assert_same('', $out['other'], 'a page without a group opens "Other"');
	pg_assert_same('g2', $out['fromChip'], 'a page opened from a chip opens its group');
	pg_assert_same(array('p3'), $out['plan']['tabs'], '"Other" open: its tabs in the box');
	pg_assert_same(array('g1', 'g2'), $out['plan']['chips'], '"Other" open: every group a chip');
}
