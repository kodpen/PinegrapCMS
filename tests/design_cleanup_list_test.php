<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the lists of the "Clean up" window
 * (includes/fn/design_cleanup_list.php): the references a text makes, the
 * design's own stylesheets, and the four lists with what they leave out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/design_cleanup_list.php');

function test_design_cleanup_list_refs_read_file_names_and_variables()
{
	$refs = array('files' => array(), 'vars' => array());
	pg_cleanup_refs_add($refs, '<img src="/files/Winter-Hero.JPG"> url(pattern%2Ddots.png) style="color: var(--pg-var-c-accent-old)"');
	pg_assert_true(isset($refs['files']['winter-hero.jpg']), 'a name without its folder, lower case');
	pg_assert_true(isset($refs['files']['pattern-dots.png']), 'a URL-encoded name decoded');
	pg_assert_true(isset($refs['vars']['--pg-var-c-accent-old']), 'a variable token');
}

function test_design_cleanup_list_own_sheets_leave_out_links_and_framework_rows()
{
	$json = json_encode(array(
		array('name' => 'theme.css', 'type' => 'css', 'content' => '.a{}'),
		array('name' => 'lib.css', 'type' => 'external-css', 'content' => 'https://cdn.example/lib.css'),
		array('name' => 'Bootstrap', 'type' => 'css', 'meta' => 'bootstrap-css', 'content' => '.btn{}'),
		array('name' => 'x', 'type' => 'css', 'meta' => 'user-styles', 'content' => '.b{}'),
		array('name' => 'empty.css', 'type' => 'css', 'content' => '  '),
	));
	$sheets = pg_cleanup_own_sheets($json);
	pg_assert_same(2, count($sheets), 'the inline sheet and the Styles');
	pg_assert_same('theme.css', $sheets[0]['name']);
	pg_assert_same('.b{}', $sheets[1]['css']);
}

function test_design_cleanup_list_rules_largest_first_with_text_and_notes()
{
	$state = pg_cleanup_state(array('known' => pg_cleanup_framework_classes('.btn{}.row>.col-6{}')));
	pg_cleanup_add_classes($state, 'used');
	$sheets = array(
		array('name' => 'theme.css', 'css' => ".gone{a:b}\n.used{}\n.btn{}\n.long-one{color:red;background:blue}"),
		array('name' => 'bad.css', 'css' => '.x{'),
	);
	$list = pg_cleanup_rule_list($sheets, $state);
	pg_assert_same(array('.long-one', '.gone'), array_map(function ($r) {
		return $r['selector'];
	}, $list['rows']), 'unused rules, the largest first; a framework class is known');
	pg_assert_same('.gone{a:b}', $list['rows'][1]['text']);
	pg_assert_same('theme.css', $list['rows'][0]['sheet']);
	pg_assert_same($list['rows'][0]['bytes'] + $list['rows'][1]['bytes'], $list['bytes']);
	pg_assert_same(array(array('sheet' => 'bad.css', 'reason' => 'unreadable', 'at' => '')), $list['notes']);

	pg_cleanup_block($state, 'bound_class', 'Shop');
	$list = pg_cleanup_rule_list(array($sheets[0]), $state);
	pg_assert_same(array(), $list['rows'], 'nothing listed while the state cannot tell');
	pg_assert_same('Shop', $list['notes'][0]['at']);
}

function test_design_cleanup_list_files_skip_referenced_odd_and_kept_names()
{
	$refs = array('files' => array('used.jpg' => true), 'vars' => array());
	$files = array(
		array('id' => 1, 'name' => 'used.jpg', 'size' => 10, 'timestamp' => 1),
		array('id' => 2, 'name' => 'small.pdf', 'size' => 20, 'timestamp' => 2),
		array('id' => 3, 'name' => 'big.png', 'size' => 30, 'timestamp' => 3),
		array('id' => 4, 'name' => 'with space.png', 'size' => 40, 'timestamp' => 4),
		array('id' => 5, 'name' => 'design-variables-7.css', 'size' => 50, 'timestamp' => 5),
	);
	$list = pg_cleanup_file_list($files, $refs, array('design-variables-7.css'));
	pg_assert_same(array(3, 2), array_map(function ($r) {
		return $r['id'];
	}, $list['rows']), 'unreferenced, plain names, the largest first');
	pg_assert_true($list['rows'][0]['image'], 'a picture');
	pg_assert_false($list['rows'][1]['image'], 'a PDF is no picture');
	pg_assert_same(50, $list['bytes']);
}

function test_design_cleanup_list_components_placed_nowhere_by_name()
{
	$components = array(
		array('id' => 1, 'name' => 'Zebra', 'tree_json' => '{"type":"semantic"}'),
		array('id' => 2, 'name' => 'Placed', 'tree_json' => '{}'),
		array('id' => 3, 'name' => 'Inside another', 'tree_json' => '{}'),
		array('id' => 4, 'name' => 'apple', 'tree_json' => 'not json'),
		array('id' => 5, 'name' => 'Unknown usage', 'tree_json' => '{}'),
	);
	$usage = array(1 => array(), 2 => array(array('page_id' => 9)), 3 => array(), 4 => array());
	$list = pg_cleanup_component_list($components, $usage, array(3 => true));
	pg_assert_same(array('apple', 'Zebra'), array_map(function ($r) {
		return $r['name'];
	}, $list['rows']));
	pg_assert_same(null, $list['rows'][0]['tree'], 'a tree that does not read has no Show drawing');
	pg_assert_same('semantic', $list['rows'][1]['tree']['type']);
}

function test_design_cleanup_list_variables_only_unread_own_ones()
{
	$custom = array(
		array('key' => 'space-xxl', 'name' => 'Space XXL', 'kind' => 'size', 'value' => '96px'),
		array('key' => 'accent-old', 'name' => 'Accent old', 'kind' => 'color', 'light' => '#c9773c', 'dark' => '#000'),
		array('key' => 'brand-2', 'name' => 'Brand 2', 'kind' => 'color', 'light' => '#111111', 'dark' => '#222222'),
	);
	$refs = array('files' => array(), 'vars' => array('--pg-var-c-brand-2' => true));
	$list = pg_cleanup_variable_list($custom, $refs);
	pg_assert_same(array('Accent old', 'Space XXL'), array_map(function ($r) {
		return $r['name'];
	}, $list['rows']));
	pg_assert_same('#c9773c', $list['rows'][0]['value']);
	pg_assert_same('--pg-var-c-space-xxl', $list['rows'][1]['token']);
}

function _dc_list_js($expr)
{
	if (!function_exists('exec')) {
		return null;
	}
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$file = @tempnam(sys_get_temp_dir(), 'pgcu');
	if ($file === false) {
		return null;
	}
	file_put_contents($file, file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_design_cleanup.js')
		. "\nconsole.log(JSON.stringify(" . $expr . '));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return json_decode(implode("\n", $out), true);
}

function test_design_cleanup_window_lists_order_keys_and_first_open()
{
	$answer = '{ status: "success", files: { rows: [{ id: 7, bytes: 10 }], bytes: 10 }, variables: { rows: [{ key: "x" }] }, rules: { rows: [], notes: [{ sheet: "a.css" }] } }';
	$got = _dc_list_js('(function () { var l = PgDesignCleanup.lists(' . $answer . '); return {'
		. ' kinds: l.map(function (x) { return x.kind; }), keys: [l[1].rows[0].key, l[3].rows[0].key], bytes: [l[0].bytes, l[2].bytes],'
		. ' notes: l[0].notes.length, open: PgDesignCleanup.firstOpen(l, ""), fromCheck: PgDesignCleanup.firstOpen(l, "publishcheck") }; })()');
	if ($got === null) {
		return;
	}
	pg_assert_same(array('rules', 'files', 'components', 'variables'), $got['kinds'], 'always in this order');
	pg_assert_same(array('files:7', 'variables:x'), $got['keys']);
	pg_assert_same(array(0, null), $got['bytes'], 'only rules and files have a size');
	pg_assert_same(1, $got['notes']);
	pg_assert_same(array('files' => true), $got['open'], 'the first list with rows opens');
	pg_assert_same(array('rules' => true), $got['fromCheck'], 'from the Publish check the rules open');
}

function test_design_cleanup_window_ticks_sizes_and_views()
{
	$got = _dc_list_js('(function () { var C = PgDesignCleanup;'
		. ' var l = C.lists({ status: "success", rules: { rows: [{ bytes: 412 }, { bytes: 1126 }] }, components: { rows: [{ id: 3 }] } });'
		. ' var some = { "rules:0": true }; var both = { "rules:0": true, "rules:1": true, "components:3": true };'
		. ' return { none: C.tickState(l[0], {}), some: C.tickState(l[0], some), all: C.tickState(l[0], both), ticked: C.ticked(l, both),'
		. ' sizes: [C.size(412), C.size(1126), C.size(1887437), C.size(25600)],'
		. ' views: [C.view(true, true, null), C.view(false, true, null), C.view(false, false, { status: "error" }), C.view(false, false, { status: "success" }), C.view(false, false, { status: "success", rules: { rows: [], notes: [{}] } })] }; })()');
	if ($got === null) {
		return;
	}
	pg_assert_same(array('none', 'some', 'all'), array($got['none'], $got['some'], $got['all']));
	pg_assert_same(array('count' => 3, 'bytes' => 1538, 'kinds' => array('rules' => 2, 'components' => 1)), $got['ticked']);
	pg_assert_same(array('412 B', '1.1 KB', '1.8 MB', '25 KB'), $got['sizes']);
	pg_assert_same(array('save', 'busy', 'failed', 'empty', 'lists'), $got['views'], 'unsaved changes come before everything');
}
