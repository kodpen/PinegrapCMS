<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a link in a form list row or on a form detail page that opens
 * another page of the site with the record's values in its address
 * (props._bindings.href = '__page_link', props._bindParams.href): the
 * stored spec (pg_sw_page_link_spec() and its editor twin
 * _swPageLinkSpec()), the renderer's pre-pass (pg_sw_page_link_bindings(),
 * pg_sw_page_link_tokens()), the page address (pg_sw_page_link_base()), the
 * raw values (pg_sw_raw_token_value()), the address built from them and
 * escaped once (pg_sw_page_link_url(), pg_sw_page_link_values()), the
 * sentinel a path that does not expand it prints (_apply_bindings()), the
 * unpublished pages resolved on Publish (pg_sw_page_link_resolve_tabs() and
 * the editor's _swResolveTreeTabRefs()), the template export and fill, and
 * the editor's panel wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _page_link_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The source of style_designer.js between two markers, '' when either is
// missing.
function _page_link_js_slice($js, $from, $to)
{
	$start = strpos($js, $from);

	if ($start === false) {
		return '';
	}

	$end = strpos($js, $to, $start + strlen($from));

	return ($end === false) ? '' : substr($js, $start, $end - $start);
}

// Runs $steps under Node after the editor's link helpers (the constants, the
// URL parameter rules, _swPageLinkSpec(), the panel's warnings and the tab
// resolver), with stubs for what they call; the JSON the steps print, or null
// without Node.
function _page_link_node($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$js = _page_link_js();
	$constants = _page_link_js_slice($js, 'var SW_PAGE_LINK_TOKEN =', 'var _SW_FORM_FIELD_WINS =');
	$names = _page_link_js_slice($js, 'var _SW_URL_PARAM_RESERVED = [', 'function _swFormListFiltersHtml(');
	$tabs = _page_link_js_slice($js, 'function _swResolveTreeTabRefs(', '// A three-way question.');
	$binned = _page_link_js_slice($js, 'function _swPageBinned(', "\n");
	pg_assert_true(($constants !== '') && ($names !== '') && ($tabs !== '') && ($binned !== ''), 'the designer has the helpers');

	$script = 'function _sdT(s) { return s; } function esc(s) { return s; }'
		. 'function _sdSystemWidgetCfgOf(n) { return n.cfg || null; }'
		. 'function _sdSharedTreeJSON(t) { return JSON.stringify(t); }'
		. 'var _sharedCache = {}, _sharedSnapshots = {}, _pages = [], _activeKey = "";'
		. $constants . $names . $tabs . $binned . "\n"
		. $steps;

	$file = tempnam(sys_get_temp_dir(), 'pgpagelink');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A content link bound to the sentinel, with its spec.
function _page_link_node_tree($spec, $href = '__page_link')
{
	return array(
		'type' => 'content',
		'props' => array(
			'contentType' => 'link',
			'text' => 'Board',
			'href' => '#',
			'_bindings' => array('href' => $href),
			'_bindParams' => array('href' => $spec),
		),
		'children' => array(),
	);
}

// -- the stored spec ----------------------------------------------------------

function test_designer_page_link_spec_page_id()
{
	pg_assert_same(array('page_id' => 0, 'params' => array()), pg_sw_page_link_spec(null), 'not an array');
	pg_assert_same(array('page_id' => 0, 'params' => array()), pg_sw_page_link_spec('x'), 'a string');
	pg_assert_same(7, pg_sw_page_link_spec(array('page_id' => 7))['page_id']);
	pg_assert_same(7, pg_sw_page_link_spec(array('page_id' => '7'))['page_id'], 'digits');

	foreach (array('tab:abc', '0', -3, 'x', '', 0, 1.5, array(7)) as $value) {
		pg_assert_same(0, pg_sw_page_link_spec(array('page_id' => $value))['page_id'], var_export($value, true));
	}
}

function test_designer_page_link_spec_params()
{
	$spec = pg_sw_page_link_spec(array('page_id' => 5, 'params' => array(
		array('name' => ' Board ', 'token' => 'board'),
		array('name' => 'when', 'token' => 'pub<b>-Date'),
		array('name' => 'empty', 'token' => ''),
		array('name' => 'other', 'token' => '<>'),
		array('name' => 'board', 'token' => 'second'),
		'not a pair',
	)));
	pg_assert_same(array(
		array('name' => 'board', 'token' => 'board'),
		array('name' => 'when', 'token' => 'pubb-date'),
	), $spec['params'], 'trimmed and lowercased names, tokens cut to the characters a binding keeps, an empty token and a repeated name dropped');
}

// The names the URL filters refuse are never written.
function test_designer_page_link_spec_refused_names()
{
	foreach (array('name', 'query', 'r', 't', 'utm_x', 'pg_x', '1x', 'a-b', str_repeat('a', 33), '') as $name) {
		$spec = pg_sw_page_link_spec(array('params' => array(array('name' => $name, 'token' => 'board'))));
		pg_assert_same(array(), $spec['params'], var_export($name, true));
	}
}

function test_designer_page_link_spec_max()
{
	pg_assert_same(8, pg_sw_page_link_max());
	$params = array();

	for ($i = 0; $i < 9; $i++) {
		$params[] = array('name' => 'p' . $i, 'token' => 'f' . $i);
	}

	$spec = pg_sw_page_link_spec(array('params' => $params));
	pg_assert_same(8, count($spec['params']));
	pg_assert_same('p7', $spec['params'][7]['name'], 'the ninth is the one dropped');
}

// The editor keeps what is being typed and leaves the dropping to the server.
function test_designer_page_link_editor_spec()
{
	$answer = _page_link_node(
		'var rows = []; for (var i = 0; i < 10; i++) rows.push({ name: "p" + i, token: "f" + i });'
		. 'console.log(JSON.stringify({'
		. ' max: _SW_PAGE_LINK_MAX, token: SW_PAGE_LINK_TOKEN,'
		. ' tab: _swPageLinkSpec({ page_id: "tab:k" }).page_id,'
		. ' digits: _swPageLinkSpec({ page_id: "7" }).page_id,'
		. ' bad: _swPageLinkSpec({ page_id: "x" }).page_id,'
		. ' none: _swPageLinkSpec(null),'
		. ' typed: _swPageLinkSpec({ params: [{ name: " Query ", token: "pub<b>-Date" }, { name: "", token: "" }] }).params,'
		. ' capped: _swPageLinkSpec({ params: rows }).params.length,'
		. ' offered: [_swPageLinkOffered({ cfg: { regionType: "blog_list" } }), _swPageLinkOffered({ cfg: { regionType: "form_item_view" } }),'
		. '           _swPageLinkOffered({ cfg: { regionType: "catalog_listing" } }), _swPageLinkOffered({ cfg: { regionType: "unknown_kind" } }), _swPageLinkOffered({})],'
		. ' warn: [_swPageLinkNameWarn([{ name: "board", token: "board" }], 0), _swPageLinkNameWarn([{ name: "query", token: "board" }], 0) !== "",'
		. '        _swPageLinkNameWarn([{ name: "board", token: "board" }, { name: "board", token: "x" }], 1) !== "", _swPageLinkNameWarn([{ name: "" }], 0)]'
		. '}));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(pg_sw_page_link_max(), $answer['max'], 'the twin of pg_sw_page_link_max()');
	pg_assert_same('__page_link', $answer['token']);
	pg_assert_same('tab:k', $answer['tab'], 'an unsaved tab is kept');
	pg_assert_same(7, $answer['digits']);
	pg_assert_same(0, $answer['bad']);
	pg_assert_same(array('page_id' => 0, 'params' => array()), $answer['none']);
	pg_assert_same(array(array('name' => 'query', 'token' => 'pubb-Date'), array('name' => '', 'token' => '')), $answer['typed'], 'a refused name is kept to be warned about; an empty row is kept to be filled in');
	pg_assert_same(8, $answer['capped']);
	pg_assert_same(array(true, true, false, false, false), $answer['offered'], 'only the kinds the server expands it in');
	pg_assert_same(array('', true, true, ''), $answer['warn'], 'refused and repeated names are warned about');
}

// A repeated name is warned about only when the earlier row is one the server
// sends: pg_sw_page_link_spec() drops a row with no field or a refused name
// before it takes the name.
function test_designer_page_link_editor_repeated_name_follows_server()
{
	$cases = array(
		'no field yet' => array(array(array('name' => 'board', 'token' => ''), array('name' => 'board', 'token' => 'board')), false),
		'a field cut to nothing' => array(array(array('name' => 'board', 'token' => '<>'), array('name' => 'board', 'token' => 'board')), false),
		'both sent' => array(array(array('name' => 'board', 'token' => 'board'), array('name' => 'board', 'token' => 'x')), true),
		'the later one has no field yet' => array(array(array('name' => 'board', 'token' => 'board'), array('name' => 'board', 'token' => '')), true),
		'typed with spaces and capitals' => array(array(array('name' => ' Board ', 'token' => 'board'), array('name' => 'board', 'token' => 'x')), true),
		'another name' => array(array(array('name' => 'board', 'token' => 'board'), array('name' => 'member', 'token' => 'x')), false),
	);
	$rows = array();

	foreach ($cases as $label => $case) {
		$rows[$label] = $case[0];
	}

	$answer = _page_link_node(
		'var cases = ' . json_encode($rows) . ';'
		. 'var out = {}; Object.keys(cases).forEach(function (k) { out[k] = _swPageLinkNameWarn(cases[k], 1) !== ""; });'
		. 'console.log(JSON.stringify(out));'
	);

	if ($answer === null) {
		return;
	}

	foreach ($cases as $label => $case) {
		pg_assert_same($case[1], $answer[$label], $label);
	}

	// The server's side: with no field on the first row the second is sent,
	// with a field on both the first one is.
	$spec = pg_sw_page_link_spec(array('page_id' => 4, 'params' => $rows['no field yet']));
	pg_assert_same(array(array('name' => 'board', 'token' => 'board')), $spec['params']);
	$spec = pg_sw_page_link_spec(array('page_id' => 4, 'params' => $rows['both sent']));
	pg_assert_same(array(array('name' => 'board', 'token' => 'board')), $spec['params']);
}

// The deleted-page warning: a tab of this design is live even when the page
// list, fetched once per session, was fetched before the tab was saved.
function test_designer_page_link_editor_page_gone()
{
	$answer = _page_link_node(
		'console.log(JSON.stringify(['
		. ' _swPageLinkPageGone(12, [{ page_id: 12, key: "p12" }], [{ page_id: "3" }]),'
		. ' _swPageLinkPageGone(12, [], [{ page_id: "3" }]),'
		. ' _swPageLinkPageGone(12, [], null),'
		. ' _swPageLinkPageGone(12, [], [{ page_id: "12", binned: 0 }]),'
		. ' _swPageLinkPageGone(12, [], [{ page_id: "12", binned: "1" }]),'
		. ' _swPageLinkPageGone(12, [{ page_id: 0, key: "k" }], []),'
		. ' _swPageLinkPageGone("tab:k", [], []),'
		. ' _swPageLinkPageGone(0, [], [])'
		. ']));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(
		array(false, true, false, false, true, true, false, false),
		$answer,
		'a tab of the design, missing, list not loaded yet, listed, in the bin, an unsaved tab only, a tab key, no page'
	);
}

// The panel itself: a page saved in this session after the page list was
// fetched draws no warning; a page neither list holds does.
function test_designer_page_link_editor_panel_new_tab()
{
	$rows = _page_link_js_slice(_page_link_js(), 'function _sdPageLinkRows(n, fields) {', 'var rowsHtml = \'\';');
	pg_assert_true($rows !== '', 'the panel rows are found');

	$answer = _page_link_node(
		'var _allPagesCache = [{ page_id: "3", page_name: "home" }];'
		. 'function _fetchAllPages() {} function renderProperties() {} function _swPageOptions() { return ""; }'
		. 'function _swFormFieldToken(ff) { return ff.name; } function _builtinLabel(o) { return o[1]; }'
		. 'var SW_STANDARD_FIELD_OPTIONS = [["board", "Board"]]; function _swStandardYields() { return false; } function _sdPageLinkRowTokens() { return []; }'
		. $rows
		. 'var link = { props: { _bindParams: { href: { page_id: 12, params: [{ name: "board", token: "" }, { name: "board", token: "board" }] } } } };'
		. '_pages = [{ page_id: 12, key: "p12", page_name: "board" }];'
		. 'var saved = _sdPageLinkRows(link, []);'
		. '_pages = [];'
		. 'var gone = _sdPageLinkRows(link, []);'
		. 'var warn = "This page is deleted or in the Recycle Bin";'
		. 'var dup = "An earlier value already uses this name";'
		. 'console.log(JSON.stringify({ saved: saved.indexOf(warn) !== -1, gone: gone.indexOf(warn) !== -1, dup: saved.indexOf(dup) !== -1 }));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_false($answer['saved'], 'a tab saved after the list was fetched is not called deleted');
	pg_assert_true($answer['gone'], 'a page no list holds is');
	pg_assert_false($answer['dup'], 'a row with no field yet does not make the next one a repeat');
}

// -- the pre-pass ---------------------------------------------------------------

function test_designer_page_link_bindings_tokens()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		_page_link_node_tree(array('page_id' => 4, 'params' => array(array('name' => 'board', 'token' => 'Board')))),
		array('type' => 'section', 'props' => array(), 'children' => array(
			_page_link_node_tree(array('page_id' => '9', 'params' => array(array('name' => 'member', 'token' => 'submitter_name')))),
			_page_link_node_tree(array('page_id' => 3), 'form_item_view'),
		)),
	));
	$links = pg_sw_page_link_bindings($tree);

	pg_assert_same(array('__page_link_0', '__page_link_1'), array_keys($links), 'one token per link, in walk order');
	pg_assert_same('__page_link_0', $tree['children'][0]['props']['_bindings']['href']);
	pg_assert_same('__page_link_1', $tree['children'][1]['children'][0]['props']['_bindings']['href']);
	pg_assert_same(array('page_id' => 9, 'params' => array(array('name' => 'member', 'token' => 'submitter_name')), 'base' => ''), $links['__page_link_1']);
	pg_assert_same('form_item_view', $tree['children'][1]['children'][1]['props']['_bindings']['href'], 'a spec under another binding is left alone');
	pg_assert_same(array('board' => true, 'submitter_name' => true), pg_sw_page_link_tokens($links), 'the tokens the record query reads');
	pg_assert_same(array(), pg_sw_page_link_bindings($tree), 'a tree already expanded has nothing left to expand');
}

function test_designer_page_link_base()
{
	pg_assert_same('', pg_sw_page_link_base(null, array()), 'a page that is gone');
	pg_assert_same('', pg_sw_page_link_base(array('page_name' => 'board', 'page_folder' => 5), array(5, 6)), 'a page in the bin');
	pg_assert_same('/my%20board', pg_sw_page_link_base(array('page_name' => 'my board', 'page_folder' => 2), array(5), '/'));
	pg_assert_same('/site/board', pg_sw_page_link_base(array('page_name' => 'board', 'page_folder' => 0), array(), '/site/'));
}

// A link with no page chosen asks the database nothing and prints '#'.
function test_designer_page_link_resolve_without_pages()
{
	$links = array('__page_link_0' => array('page_id' => 0, 'params' => array(), 'base' => 'stale'));
	pg_sw_page_link_resolve($links);
	pg_assert_same('', $links['__page_link_0']['base']);
	$none = array();
	pg_sw_page_link_resolve($none);
	pg_assert_same(array(), $none);
}

// -- raw values -------------------------------------------------------------------

function test_designer_page_link_raw_value_order()
{
	$row = array('id' => '3', 'page_id' => '2', 'user_id' => '8', 'form_editor_user_id' => '9', 'reference_code' => 'ABC', 'submitter_name' => 'Ann');
	$custom = array('board' => array('data' => 'Tom & Jerry', 'type' => 'text box', 'wysiwyg' => 0), 'body' => array('data' => '<p>Hi</p>', 'type' => 'text area', 'wysiwyg' => 1));
	$extra_raw = array('form_item_view' => '/post?r=ABC', 'reference_code' => 'from extra');

	pg_assert_same('from extra', pg_sw_raw_token_value('reference_code', $row, $custom, $extra_raw), 'a value the renderer worked out wins');
	pg_assert_same('/post?r=ABC', pg_sw_raw_token_value('form_item_view', $row, $custom, $extra_raw));
	pg_assert_same('ABC', pg_sw_raw_token_value('REFERENCE_CODE', $row, $custom, array()), 'a standard field the query selected; names are matched in lower case');
	pg_assert_same('Ann', pg_sw_raw_token_value('submitter_name', $row, $custom, array()));
	pg_assert_same('Tom & Jerry', pg_sw_raw_token_value('board', $row, $custom, array()), 'a form field as stored, not escaped');
	pg_assert_same('<p>Hi</p>', pg_sw_raw_token_value('body', $row, $custom, array()), 'rich text as stored');

	foreach (array('id', 'page_id', 'user_id', 'form_editor_user_id', 'missing', '__edit_url', '') as $name) {
		pg_assert_same('', pg_sw_raw_token_value($name, $row, $custom, array()), var_export($name, true));
	}

	pg_assert_same('', pg_sw_raw_token_value('number_of_views', $row, $custom, array()), 'a standard field the query did not select');
}

// A form's own submitter_name field wins over the standard one, as on the page.
function test_designer_page_link_raw_value_form_field_wins()
{
	$row = array('submitter_name' => 'Contact Name');
	$custom = array('submitter_name' => array('data' => 'Typed Name', 'type' => 'text box', 'wysiwyg' => 0));
	pg_assert_same('Typed Name', pg_sw_raw_token_value('submitter_name', $row, $custom, array()));
	$custom = array('reference_code' => array('data' => 'form field', 'type' => 'text box', 'wysiwyg' => 0));
	pg_assert_same('XYZ', pg_sw_raw_token_value('reference_code', array('reference_code' => 'XYZ'), $custom, array()), 'other standard names beat a form field');
}

// -- the address ------------------------------------------------------------------

function test_designer_page_link_url_base_and_order()
{
	pg_assert_same('#', pg_sw_page_link_url('', array('board' => 'x')), 'no page');
	pg_assert_same('/board', pg_sw_page_link_url('/board', array()), 'no values: the bare page');
	pg_assert_same('/board', pg_sw_page_link_url('/board', array('board' => '', 'member' => '  ')), 'only empty values: the bare page');
	pg_assert_same('/board?z=1&y=2', pg_sw_page_link_url('/board', array('z' => '1', 'y' => '2')), 'the designed order is kept');
	pg_assert_same('/board?b=2', pg_sw_page_link_url('/board', array('query' => '1', 'b' => '2')), 'a refused name is never sent');
}

function test_designer_page_link_url_encodes_once()
{
	$value = ' & # ? + % = " \' < > ^ / ü';
	pg_assert_same('/t?v=' . rawurlencode(trim($value)), pg_sw_page_link_url('/t', array('v' => $value)));
	pg_assert_same('/t?v=%26%20%23%20%3F%20%2B%20%25%20%3D%20%22%20%27%20%3C%20%3E%20%5E%20%2F%20%C3%BC', pg_sw_page_link_url('/t', array('v' => $value)));
}

// A value is sent only as the filter on the other page reads it.
function test_designer_page_link_url_value_rule()
{
	pg_assert_same('/t', pg_sw_page_link_url('/t', array('v' => "a\nb")), 'a newline');
	pg_assert_same('/t', pg_sw_page_link_url('/t', array('v' => "a\xE2\x80\x8Bb")), 'a zero-width space');
	pg_assert_same('/t', pg_sw_page_link_url('/t', array('v' => str_repeat('a', 101))), '101 characters');
	pg_assert_same('/t?v=' . rawurlencode(str_repeat('ü', 100)), pg_sw_page_link_url('/t', array('v' => str_repeat('ü', 100))), '100 characters');
	pg_assert_same('/t?v=x', pg_sw_page_link_url('/t', array('v' => ' x ')), 'trimmed');
	pg_assert_same('/t', pg_sw_page_link_url('/t', array('v' => "\xC3\x28")), 'not UTF-8');
	pg_assert_same('/t?w=1', pg_sw_page_link_url('/t', array('v' => array('x'), 'w' => '1')), 'not a scalar');
}

// The token value is escaped exactly once: pg_sw_apply_tokens() prints $extra
// values as they are.
function test_designer_page_link_values_escape_once()
{
	$links = array('__page_link_0' => array('page_id' => 4, 'base' => '/board', 'params' => array(
		array('name' => 'board', 'token' => 'board'),
		array('name' => 'member', 'token' => 'submitter_name'),
	)));
	$custom = array('board' => array('data' => 'Tom & Jerry', 'type' => 'text box', 'wysiwyg' => 0));
	$row = array('submitter_name' => 'Ann');
	$values = pg_sw_page_link_values($links, $row, $custom, array());

	pg_assert_same(array('__page_link_0' => h('/board?board=Tom%20%26%20Jerry&member=Ann')), $values);
	pg_assert_same(1, substr_count($values['__page_link_0'], '&amp;'), 'one &amp; between the two values');
	pg_assert_false(strpos($values['__page_link_0'], '&amp;amp;') !== false, 'never escaped twice');

	$html = pg_sw_apply_tokens('<a href="^^__page_link_0^^">x</a>', $row, $custom, $values);
	pg_assert_same('<a href="/board?board=Tom%20%26%20Jerry&amp;member=Ann">x</a>', $html);
}

// A value that reads like a token is encoded and can never form one.
function test_designer_page_link_values_token_lookalike()
{
	$links = array('__page_link_0' => array('page_id' => 4, 'base' => '/t', 'params' => array(array('name' => 'v', 'token' => 'board'))));
	$custom = array('board' => array('data' => '^^reference_code^^', 'type' => 'text box', 'wysiwyg' => 0));
	$values = pg_sw_page_link_values($links, array('reference_code' => 'ABC'), $custom, array());
	$html = pg_sw_sweep_tokens(pg_sw_apply_tokens('<a href="^^__page_link_0^^">x</a>', array('reference_code' => 'ABC'), $custom, $values));
	pg_assert_same('<a href="/t?v=%5E%5Ereference_code%5E%5E">x</a>', $html);
}

function test_designer_page_link_values_missing_page_and_bare()
{
	$links = array(
		'__page_link_0' => array('page_id' => 4, 'base' => '', 'params' => array(array('name' => 'v', 'token' => 'board'))),
		'__page_link_1' => array('page_id' => 5, 'base' => '/b', 'params' => array(array('name' => 'v', 'token' => 'board'))),
	);
	pg_assert_same(array('__page_link_0' => '#', '__page_link_1' => '/b'), pg_sw_page_link_values($links, array(), array(), array()), 'a missing page prints #; no record opens the bare page');
}

// -- rendering ----------------------------------------------------------------------

// A sentinel no renderer expanded prints '#', never a token swept to href="".
function test_designer_page_link_apply_bindings_sentinel()
{
	$props = _apply_bindings(array('href' => '/typed', '_bindings' => array('href' => '__page_link', 'text' => 'title')));
	pg_assert_same('#', $props['href']);
	pg_assert_same('^^title^^', $props['text']);
	pg_assert_same(array(array('name' => 'data-pg-bind', 'value' => 'text:title')), $props['_attrs'], 'no data-pg-bind pair for the href');

	$props = _apply_bindings(array('_bindings' => array('href' => '__page_link_0')));
	pg_assert_same('^^__page_link_0^^', $props['href'], 'an expanded link prints its token');

	$props = _apply_bindings(array('_bindings' => array('text' => '__page_link')));
	pg_assert_same('^^__page_link^^', $props['text'], 'only the href is the sentinel');

	$html = _render_tree_node(_page_link_node_tree(array('page_id' => 4), '__page_link_0'), 0);
	pg_assert_contains('href="^^__page_link_0^^"', $html, 'the renderer\'s own escaping leaves the token whole');
}

// -- Publish and templates ------------------------------------------------------------

function test_designer_page_link_resolve_tabs()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'section', 'props' => array('page_id' => 'tab:k'), 'children' => array(
			_page_link_node_tree(array('page_id' => 'tab:k', 'params' => array(array('name' => 'board', 'token' => 'board')))),
			_page_link_node_tree(array('page_id' => 'tab:gone')),
		)),
		array('type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'tab:k', 'href' => '#', '_attrs' => array(array('name' => 'data-x', 'value' => 'tab:k'))), 'children' => array()),
	));
	$out = pg_sw_page_link_resolve_tabs($tree, array('k' => '12'));

	pg_assert_same(12, $out['children'][0]['children'][0]['props']['_bindParams']['href']['page_id'], 'a known key');
	pg_assert_same(0, $out['children'][0]['children'][1]['props']['_bindParams']['href']['page_id'], 'an unknown key');
	pg_assert_same(array(array('name' => 'board', 'token' => 'board')), $out['children'][0]['children'][0]['props']['_bindParams']['href']['params']);
	pg_assert_same('tab:k', $out['children'][0]['props']['page_id'], 'a page_id prop outside _bindParams');
	pg_assert_same($tree['children'][1], $out['children'][1], 'a text and an attribute reading tab:');

	$plain = array('type' => 'root', 'props' => array(), 'children' => array(_page_link_node_tree(array('page_id' => 7))));
	pg_assert_true(pg_sw_page_link_resolve_tabs($plain, array('k' => 12)) === $plain, 'nothing to resolve: the same tree');

	// Written back through the tree encoder, an empty root props stays an
	// object, and so does an empty _bindParams: as [] the editor would set
	// a property on an array that JSON.stringify then drops.
	pg_assert_contains('"props":{}', pg_designer_tree_encode($out));
	$empty = array('type' => 'content', 'props' => array('contentType' => 'link', '_bindParams' => array()), 'children' => array());
	pg_assert_contains('"_bindParams":{}', pg_designer_tree_encode($empty));
}

// The editor makes the same swap in its cached widget trees and in their
// snapshots, so a save that changed nothing else still finds nothing to send.
function test_designer_page_link_editor_resolve_tabs()
{
	$answer = _page_link_node(
		'var link = { type: "content", props: { contentType: "link", text: "tab:k", _bindings: { href: "__page_link" }, _bindParams: { href: { page_id: "tab:k", params: [] } } }, children: [] };'
		. 'var other = { type: "content", props: { page_id: "tab:k", _bindParams: { href: { page_id: "tab:gone" } } }, children: [] };'
		. '_sharedCache = { 5: { tree: { type: "root", props: {}, children: [link, other] }, system_region_config: JSON.stringify({ detail_page_id: "tab:k" }) } };'
		. '_sharedSnapshots = { 5: JSON.stringify(_sharedCache[5].tree) };'
		. '_swResolveTabRefs({ k: "12" });'
		. 'console.log(JSON.stringify({ tree: _sharedCache[5].tree, cfg: JSON.parse(_sharedCache[5].system_region_config), same: _sharedSnapshots[5] === JSON.stringify(_sharedCache[5].tree) }));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(12, $answer['tree']['children'][0]['props']['_bindParams']['href']['page_id']);
	pg_assert_same(0, $answer['tree']['children'][1]['props']['_bindParams']['href']['page_id'], 'a closed tab');
	pg_assert_same('tab:k', $answer['tree']['children'][1]['props']['page_id'], 'a prop outside _bindParams is not read');
	pg_assert_same('tab:k', $answer['tree']['children'][0]['props']['text']);
	pg_assert_same(12, $answer['cfg']['detail_page_id'], 'the config as before');
	pg_assert_true($answer['same'], 'the snapshot got the same swap');
}

// From a design: the page of the design becomes {{tab:<key>}}, the values and
// the binding stay; the fill turns the placeholder into the tab again.
function test_designer_page_link_template_export_and_fill()
{
	$ctx = array(
		'page_urls' => array('/home' => 'home', '/board' => 'board'),
		'page_ids' => array(11 => 'home', 12 => 'board'),
		'widgets_by_sid' => array(), 'shared_by_sid' => array(), 'folders_by_id' => array(), 'contact_groups_by_id' => array(),
		'site_name' => 'Acme', 'site_email' => 'a@example.com', 'year' => '2026',
	);
	$params = array(array('name' => 'home', 'token' => '/home'));
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		_page_link_node_tree(array('page_id' => 12, 'params' => $params)),
		_page_link_node_tree(array('page_id' => '40', 'params' => $params)),
	));
	$out = pg_dtc_tokenize_tree($tree, $ctx);

	pg_assert_same('{{tab:board}}', $out['children'][0]['props']['_bindParams']['href']['page_id']);
	pg_assert_same($params, $out['children'][0]['props']['_bindParams']['href']['params'], 'the values are copied untouched');
	pg_assert_same('40', $out['children'][1]['props']['_bindParams']['href']['page_id'], 'a page outside the design keeps its id');
	pg_assert_same(array('href' => '__page_link'), $out['children'][0]['props']['_bindings']);

	$filled = _pg_tpl_fill($out, array('tabs' => array('board' => 'tplabc_board'), 'pages' => array()));
	pg_assert_same('tab:tplabc_board', $filled['children'][0]['props']['_bindParams']['href']['page_id']);
	$filled = _pg_tpl_fill($out, array('tabs' => array(), 'pages' => array()));
	pg_assert_same('0', $filled['children'][0]['props']['_bindParams']['href']['page_id'], 'an unknown key');
	pg_assert_same(0, pg_sw_page_link_spec($filled['children'][0]['props']['_bindParams']['href'])['page_id']);
}

// -- the editor's panel ----------------------------------------------------------------

function test_designer_page_link_editor_wiring()
{
	$js = _page_link_js();

	$writer = _page_link_js_slice($js, 'function _sdNodeSetBinding(', '// Wire the binding selects');
	pg_assert_true($writer !== '', 'the binding writer is found');
	pg_assert_false(strpos($writer, '_bindParams') !== false, 'the one binding writer, shared with the code view, leaves the spec alone');
	pg_assert_false(strpos($writer, 'SW_PAGE_LINK_TOKEN') !== false, 'and does not name the sentinel');

	$set = _page_link_js_slice($js, 'function _setBinding(propName, value) {', 'function _sdPageLinkEdit(');
	pg_assert_contains('if (bp && value === SW_PAGE_LINK_TOKEN) {', $set, 'the panel seeds the spec for the link');
	pg_assert_contains('delete bp._bindParams[propName];', $set, 'and drops it for any other value');

	pg_assert_contains("var v = _swPageVal(this);\n                _sdPageLinkEdit(", $js, 'the page select is read with _swPageVal');

	$rows = _page_link_js_slice($js, 'function _sdPageLinkRows(n, fields) {', 'var rowsHtml = \'\';');
	pg_assert_contains('_swPageOptions(cur, _allPagesCache, { none: _sdT(\'— Choose a page —\') })', $rows);
	pg_assert_contains('if (_swStandardYields(o[0], fields)) return;', $rows, 'a standard name the form overrides is not offered');
	pg_assert_contains('_swPageLinkNameWarn(spec.params, i)', $rows);
	pg_assert_contains('var usable = _swUrlParamName(name);', _page_link_js_slice($js, 'function _swPageLinkNameWarn(', 'function _swFormListFiltersHtml('), 'the warning uses the URL filter rule');
	pg_assert_contains('_swPageLinkPageGone(spec.page_id, (typeof _pages !== \'undefined\' && _pages) ? _pages : [], _allPagesCache)', $rows, 'the deleted-page warning asks the design\'s tabs first');

	$resolve = _page_link_js_slice($js, 'function _swResolveTabRefs(map) {', '// A three-way question.');
	pg_assert_contains('_swResolveTreeTabRefs(c.tree, map) && _sharedSnapshots[sid]', $resolve);

	// The sentinel is in no token palette: the {{ list would offer a link
	// without a page, and check_bindings would ask for a renderer key.
	preg_match_all('/var (SW_[A-Z_]*(?:TOKEN|OPTIONS)[A-Z_]*)\s*=\s*\[/', $js, $palettes, PREG_OFFSET_CAPTURE);
	pg_assert_true(count($palettes[0]) > 5, 'the palettes are found');

	foreach ($palettes[0] as $i => $palette) {
		$body = substr($js, $palette[1], strpos($js, '];', $palette[1]) - $palette[1]);
		pg_assert_false(strpos($body, '__page_link') !== false || strpos($body, 'SW_PAGE_LINK_TOKEN') !== false, $palettes[1][$i][0]);
	}
}
