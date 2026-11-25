<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the library item model (includes/fn/designer_library.php): a
 * subtree made into an item (its rules, variables, rows and record
 * references), an item inserted into a design (rules added or renamed,
 * variables, references kept, found by name or cleared and marked), the
 * file text, and who may do what.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _library_test_subtree()
{
	return array(
		'_id' => 'sd_4', 'type' => 'section', '_expanded' => true,
		'props' => array('cssClass' => 'py-5 hero-band', '_notes' => 'ask the client', '_notes_by' => 'Ayşe'),
		'children' => array(
			array('_id' => 'sd_5', 'type' => 'div', 'props' => array('cssClass' => 'row'), 'children' => array(
				array('_id' => 'sd_6', 'type' => 'h2', 'props' => array('text' => 'Hello', 'cssClass' => 'hero-title',
					'style' => 'color: var(--brand)')),
				array('_id' => 'sd_7', 'type' => 'shared_ref', 'props' => array('sharedId' => 41, 'sharedName' => 'Signup'), 'children' => array()),
				array('_id' => 'sd_8', 'type' => 'shared_ref', 'props' => array('sharedId' => 42, 'sharedName' => 'Footer note'), 'children' => array()),
				array('_id' => 'sd_9', 'type' => 'shared_ref', 'props' => array('sharedId' => 99), 'children' => array()),
			)),
		),
	);
}

function _library_test_css()
{
	return "/* the band */\n.hero-band { background: var(--band-bg); padding: 2rem }\n"
		. ".hero-title { font-weight: 700; }\n"
		. "@media (min-width: 768px) {\n  .hero-band .row { gap: 1rem; }\n}\n"
		. ".other { color: red; }\n.row { margin: 0; }\n"
		. ":root { --brand: #c00; --band-bg: var(--band-base); --band-base: #eee; --unused: 1px; }\n";
}

function _library_test_item()
{
	$design = array(
		'css' => _library_test_css(),
		'vars' => pg_library_css_var_defs(_library_test_css()),
		'framework' => array('row' => true, 'py-5' => true),
	);
	$site = array(
		'rows' => array(
			41 => array('name' => 'Signup [2]', 'tree' => array('type' => 'div', '_id' => 'w1', 'children' => array()),
				'config' => array('regionType' => 'custom_form', 'page_id' => 12, 'contact_group_id' => '3', 'template_origin' => 'x')),
			42 => array('name' => 'Footer note', 'tree' => array('type' => 'p', 'props' => array('text' => 'Hi')), 'config' => null),
		),
		'pages' => array(12 => 'Contact'),
		'groups' => array(3 => 'Newsletter'),
	);
	return pg_library_item_from_subtree(_library_test_subtree(), array('name' => "  Hero\n band ", 'group' => 'Heroes'), $design, $site);
}

function test_library_item_from_subtree()
{
	$item = _library_test_item();

	pg_assert_same(PG_LIBRARY_FORMAT, $item['format']);
	pg_assert_same('Hero band', $item['name'], 'one line');
	pg_assert_same('Heroes', $item['group']);
	pg_assert_false(isset($item['tree']['_id']), 'the editor ids go');
	pg_assert_false(isset($item['tree']['_expanded']));
	pg_assert_false(isset($item['tree']['props']['_notes']), 'the notes stay on this site');
	pg_assert_false(isset($item['tree']['props']['_notes_by']));
	pg_assert_same(1, $item['dropped'], 'a placement of a row not given is left out');

	$kids = $item['tree']['children'][0]['children'];
	pg_assert_same(3, count($kids));
	pg_assert_same(array('libraryWidget' => 'w41', 'sharedName' => 'Signup'), $kids[1]['props']);
	pg_assert_same(array('libraryShared' => 's42', 'sharedName' => 'Footer note'), $kids[2]['props']);
	pg_assert_same('custom_form', $item['widgets']['w41']['kind']);
	pg_assert_same('Signup', $item['widgets']['w41']['name'], 'the number of a taken name goes');
	pg_assert_false(isset($item['widgets']['w41']['config']['template_origin']));
	pg_assert_false(isset($item['widgets']['w41']['tree']['_id']));
	pg_assert_same('Hi', $item['shared']['s42']['tree']['props']['text']);

	$selectors = array();
	foreach ($item['rules'] as $r) $selectors[] = $r['selector'];
	pg_assert_same(array('.hero-band', '.hero-title', '.hero-band .row'), $selectors, 'its own classes; not .row alone, not .other');
	pg_assert_same(array('@media (min-width: 768px)'), $item['rules'][2]['media']);

	pg_assert_same(array('--band-base' => '#eee', '--band-bg' => 'var(--band-base)', '--brand' => '#c00'), $item['variables'],
		'read by the tree and the rules, and the ones their values read');
	pg_assert_same(array('page' => array(12 => 'Contact'), 'group' => array(3 => 'Newsletter')), $item['refs']);
}

function test_library_item_into_the_same_design()
{
	$item = _library_test_item();
	$out = pg_library_item_into_design($item, array(
		'css' => _library_test_css(),
		'vars' => pg_library_css_var_defs(_library_test_css()),
		'framework' => array('row' => true, 'py-5' => true),
	), array('pages' => array(12 => 'Contact'), 'groups' => array(3 => 'Newsletter'), 'shared' => array(42 => 'Footer note')));

	pg_assert_same('', $out['css'], 'every rule and variable is there already');
	pg_assert_same(array(), $out['renamed']);
	pg_assert_same(0, $out['cleared']);
	pg_assert_same(12, $out['widgets']['w41']['config']['page_id']);
	pg_assert_same('3', $out['widgets']['w41']['config']['contact_group_id'], 'a value keeps its type');
	$kids = $out['tree']['children'][0]['children'];
	pg_assert_same(42, $kids[2]['props']['sharedId'], 'the component the site has is placed again');
	pg_assert_false(isset($kids[2]['props']['libraryShared']));
	pg_assert_same(array(), $out['shared']);
	pg_assert_same('libraryWidget', key($kids[1]['props']), 'a widget is always made anew');
}

function test_library_item_into_another_design()
{
	$item = _library_test_item();
	$css = ".hero-title { font-weight: 300 }\n.hero-title-2 { color: blue }\n:root { --brand: #00c }";
	$out = pg_library_item_into_design($item, array(
		'css' => $css,
		'vars' => pg_library_css_var_defs($css),
		'framework' => array('row' => true, 'py-5' => true),
	), array('pages' => array(7 => 'Contact', 12 => 'About'), 'groups' => array(5 => 'Members'), 'shared' => array()));

	pg_assert_same(array('hero-title' => 'hero-title-3'), $out['renamed'], 'a rule of that name that differs stays; a free name');
	pg_assert_same(3, $out['rules_added']);
	pg_assert_contains('.hero-title-3 {', $out['css']);
	pg_assert_contains(".hero-band {\n    background: var(--band-bg);\n    padding: 2rem\n}", $out['css']);
	pg_assert_contains("@media (min-width: 768px) {\n    .hero-band .row {", $out['css']);
	pg_assert_false(strpos($out['css'], '.other') !== false);
	pg_assert_false(strpos($out['css'], '--brand') !== false, 'the design value wins');
	pg_assert_contains('--band-bg: var(--band-base);', $out['css'], 'a missing variable gets the item value');
	pg_assert_same(array('--band-base' => '#eee', '--band-bg' => 'var(--band-base)'), $out['vars_added']);

	$h2 = $out['tree']['children'][0]['children'][0];
	pg_assert_same('hero-title-3', $h2['props']['cssClass']);
	pg_assert_same('py-5 hero-band', $out['tree']['props']['cssClass']);

	$w = $out['widgets']['w41'];
	pg_assert_same(7, $w['config']['page_id'], 'the page of the same name');
	pg_assert_same('', $w['config']['contact_group_id'], 'no group of that name: cleared');
	pg_assert_same(array('group'), $w['cleared']);
	pg_assert_same(1, $out['cleared']);
	pg_assert_same(array('group'), $out['tree']['children'][0]['children'][1]['props']['_libraryUnbound'], 'the placement is marked');
	pg_assert_same('Footer note', $out['shared']['s42']['name'], 'a component the site lacks is made');
}

function test_library_reference_by_name_must_be_one()
{
	$item = array('format' => PG_LIBRARY_FORMAT, 'version' => 1, 'name' => 'x',
		'tree' => array('type' => 'a', 'props' => array('_bindParams' => array('href' => array('page_id' => '12')))),
		'refs' => array('page' => array(12 => 'Contact')));
	$out = pg_library_item_into_design($item, array(), array('pages' => array(3 => 'Contact', 4 => 'Contact')));
	pg_assert_same('', $out['tree']['props']['_bindParams']['href']['page_id'], 'two of the name: not a guess');
	pg_assert_same(array('page'), $out['tree']['props']['_libraryUnbound']);
}

function test_library_css_rules()
{
	$rules = pg_library_css_rules("a{x:'}'} @font-face{font-family:F} @media screen{@supports (display:grid){.g{display:grid}}} .b{}");
	pg_assert_same(3, count($rules));
	pg_assert_same("x:'}'", $rules[0]['body'], 'a brace in a string');
	pg_assert_same(array('@media screen', '@supports (display:grid)'), $rules[1]['media']);
	pg_assert_same(array('g', 'h'), pg_library_selector_classes('.g > .h:not(.g)'));
	pg_assert_same(array(), pg_library_selector_classes('a[title=".x"]'));
	pg_assert_same(array('--a' => '1px'), pg_library_css_var_defs(':root{--a:1px;--b:}x{}@media print{:root{--c:2}}'));
	pg_assert_same(array(), pg_library_css_var_defs(':root{--a: 1px </style> }'), 'a value that leaves the rule');
}

function test_library_file_round_trip()
{
	$item = _library_test_item();
	$back = pg_library_item_decode(pg_library_item_encode($item));
	pg_assert_true(is_array($back));
	pg_assert_same($item['tree'], $back['tree']);
	pg_assert_same($item['rules'], $back['rules']);
	pg_assert_false(isset($back['dropped']));

	pg_assert_same('not_an_item', pg_library_item_decode('{"format":"x"}'));
	pg_assert_same('version', pg_library_item_decode('{"format":"pinegrap-library-item","version":9}'));
	pg_assert_same('no_name', pg_library_item_decode('{"format":"pinegrap-library-item","version":1,"tree":{"type":"div"},"name":" "}'));
	$bad = pg_library_item_decode('{"format":"pinegrap-library-item","version":1,"tree":{"type":"div"},"name":"n",'
		. '"rules":[{"selector":".a","body":"x:1}</style><script>"},{"selector":".b","body":"y:2","media":["@media x{"]}],'
		. '"variables":{"--ok":"1px","bad":"1","--x":"}"}}');
	pg_assert_same(1, count($bad['rules']), 'a rule that closes its block early is dropped');
	pg_assert_same(array(), $bad['rules'][0]['media']);
	pg_assert_same(array('--ok' => '1px'), $bad['variables']);

	pg_assert_same('library-hero-band', pg_library_file_slug('Hero band!'));
	pg_assert_same('library-item', pg_library_file_slug('Çğ'));
	pg_assert_same(array('Cards', 'heroes'), pg_library_groups(array(array('group' => 'heroes'), array('group' => 'Cards'), array('group' => ''), array('group' => 'Cards'))));
}

function test_library_rights()
{
	pg_assert_true(pg_library_may('use', array('role' => 3)), 'a role that may add elements uses it');
	pg_assert_true(pg_library_may('use', array('role' => 1)));
	foreach (array('save', 'manage', 'export', 'import') as $a) {
		pg_assert_true(pg_library_may($a, array('role' => 0)), $a);
		pg_assert_true(pg_library_may($a, array('role' => 1)), $a);
		pg_assert_false(pg_library_may($a, array('role' => 2)), $a . ' is the designer roles');
	}
	pg_assert_false(pg_library_may('use', array('role' => 9)));
	pg_assert_false(pg_library_may('other', array('role' => 0)));
}

function test_library_store_is_design_files()
{
	pg_assert_contains("files.design = '1'", pg_library_files_where());
	pg_assert_contains("files.name LIKE 'library-%'", pg_library_files_where());
	pg_assert_true(isset(pg_library_framework_classes()['container']), 'Bootstrap classes read from the vocabulary');
}

function test_library_address_kinds()
{
	foreach (array('', '/about', 'about.html', '#top', '?q=1', '//cdn.example.com/a.png', 'https://example.com',
		'http://x', 'mailto:a@b.c', 'tel:+90', '{{item_url}}', 'data:image/png;base64,AAAA', 'data:image/webp,xx') as $ok) {
		pg_assert_true(pg_library_address_ok($ok), 'allowed: ' . $ok);
	}
	foreach (array('javascript:alert(1)', ' JavaScript:x', "java\tscript:x", 'java&#115;cript:x', 'vbscript:x',
		'data:text/html,<b>', 'data:image/svg+xml,<svg>', 'file:///etc/passwd', 'blob:x') as $bad) {
		pg_assert_false(pg_library_address_ok($bad), 'refused: ' . $bad);
	}
}

function test_library_text_problems()
{
	pg_assert_same('', pg_library_text_problem('Learn javascript: the basics'), 'a sentence is not an address');
	pg_assert_same('address', pg_library_text_problem('javascript:alert(1)'), 'a running scheme in any prop');
	pg_assert_same('address', pg_library_text_problem('data:text/html,x', 'href'), 'an address field');
	pg_assert_same('', pg_library_text_problem('data:text/html,x', 'text'), 'not an address field, not a running scheme');
	pg_assert_same('script', pg_library_text_problem('<p>a</p><script>x()</script>'));
	pg_assert_same('script', pg_library_text_problem('<SCRIPT src="/a.js"></SCRIPT>'));
	pg_assert_same('event', pg_library_text_problem('<img src="a.png" onerror="x()">'));
	pg_assert_same('event', pg_library_text_problem('<svg/onload=x()>'));
	pg_assert_same('address', pg_library_text_problem('<a href="javascript:x()">a</a>'));
	pg_assert_same('address', pg_library_text_problem('<a href=javascript:x()>a</a>'), 'an unquoted value');
	pg_assert_same('address', pg_library_text_problem('<img srcset="a.png 1x, javascript:x 2x">'));
	pg_assert_same('script', pg_library_text_problem('<iframe srcdoc="&lt;b&gt;"></iframe>'));
	pg_assert_same('', pg_library_text_problem('<a href="/about" class="btn">About</a> <b>bold</b>'));
	pg_assert_same('', pg_library_text_problem('<p>Tom &amp; Jerry: online now</p>'), 'a word before = is no event');
	pg_assert_same('address', pg_library_text_problem('background: url("javascript:x")'));
	pg_assert_same('script', pg_library_text_problem('width: expression(alert(1))'));
	pg_assert_same('', pg_library_text_problem('background: url(/files/a.jpg) center / cover'));
	pg_assert_same('address', pg_library_text_problem('a.png 1x, vbscript:x 2x', 'srcset'));
}

function test_library_item_problem()
{
	$item = _library_test_item();
	pg_assert_same('', pg_library_item_problem($item), 'an item made here passes');

	$bad = $item;
	$bad['tree']['children'][0]['children'][] = array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => '<script>x()</script>'));
	pg_assert_same('script', pg_library_item_problem($bad), 'a script in a custom HTML node');

	$bad = $item;
	$bad['tree']['props']['_attrs'] = array(array('name' => 'onclick', 'value' => 'x()'));
	pg_assert_same('event', pg_library_item_problem($bad), 'an event attribute');

	$bad = $item;
	$bad['tree']['props']['_attrs'] = array(array('name' => 'href', 'value' => 'vbscript:x'));
	pg_assert_same('address', pg_library_item_problem($bad), 'an attribute address');

	$bad = $item;
	$bad['tree']['type'] = 'script';
	pg_assert_same('script', pg_library_item_problem($bad), 'a script node');

	$bad = $item;
	$bad['shared']['s42']['tree']['props']['href'] = 'javascript:x';
	pg_assert_same('address', pg_library_item_problem($bad), 'inside a shared component tree');

	$bad = $item;
	$bad['widgets']['w41']['config']['redirect_url'] = 'javascript:x';
	pg_assert_same('address', pg_library_item_problem($bad), 'in a widget setting');

	$bad = $item;
	$bad['rules'][] = array('media' => array(), 'selector' => '.hero-band', 'body' => 'background:url(javascript:x)');
	pg_assert_same('address', pg_library_item_problem($bad), 'in a rule');

	$bad = $item;
	$bad['variables']['--bg'] = 'url(data:text/html,x)';
	pg_assert_same('address', pg_library_item_problem($bad), 'in a variable');

	$deep = array('type' => 'div', 'children' => array());
	for ($i = 0; $i < 90; $i++) $deep = array('type' => 'div', 'children' => array($deep));
	$bad = $item;
	$bad['tree'] = $deep;
	pg_assert_same('deep', pg_library_item_problem($bad), 'deeper than the walk reads');
}

function test_library_export_and_import_round_trip()
{
	$item = _library_test_item();
	$item['id'] = 7;
	$item['file'] = 'library-hero-band.json';
	$item['order'] = 3;
	$text = pg_library_export_text(array($item, $item), '2026-11-25');
	$doc = json_decode($text, true);
	pg_assert_same(PG_LIBRARY_EXPORT_FORMAT, $doc['format']);
	pg_assert_same('2026-11-25', $doc['exported']);
	pg_assert_same(2, count($doc['items']));
	pg_assert_false(isset($doc['items'][0]['id']), 'the row id stays on this site');
	pg_assert_false(isset($doc['items'][0]['file']));
	pg_assert_false(isset($doc['items'][0]['dropped']));
	pg_assert_same('my-library-2026-11-25.json', pg_library_export_file_name('2026-11-25'));

	$read = pg_library_import_read($text);
	pg_assert_true(isset($read['items']), 'an export reads back');
	pg_assert_same(2, count($read['items']));
	pg_assert_same($item['tree'], $read['items'][0]['tree']);
	pg_assert_same($item['rules'], $read['items'][0]['rules']);

	// One item's own file is read as well.
	$one = pg_library_import_read(pg_library_item_encode($item));
	pg_assert_same(1, count($one['items']));
}

function test_library_import_refusals()
{
	pg_assert_same('empty', pg_library_import_read('  ')['error']);
	pg_assert_same('not_a_library', pg_library_import_read('{"a":1}')['error']);
	pg_assert_same('not_a_library', pg_library_import_read('<html>')['error']);
	pg_assert_same('version', pg_library_import_read('{"format":"pinegrap-library","version":99,"items":[]}')['error']);
	pg_assert_same('none', pg_library_import_read('{"format":"pinegrap-library","version":1,"items":[]}')['error']);

	$item = _library_test_item();
	$bad = $item;
	$bad['name'] = 'Bad band';
	$bad['tree']['props']['_attrs'] = array(array('name' => 'onmouseover', 'value' => 'x()'));
	$read = pg_library_import_read(pg_library_export_text(array($item, $bad), '2026-11-25'));
	pg_assert_same('event', $read['error'], 'one bad item refuses the file');
	pg_assert_same('Bad band', $read['item'], 'and names it');
	pg_assert_false(isset($read['items']));

	$nameless = $item;
	$nameless['name'] = '';
	$read = pg_library_import_read(pg_library_export_text(array($nameless), '2026-11-25'));
	pg_assert_same('no_name', $read['error']);
	pg_assert_same('#1', $read['item'], 'a nameless item is named by its place');
}

function test_library_import_names()
{
	$existing = array(
		array('name' => 'Hero band', 'group' => 'Heroes', 'order' => 4),
		array('name' => 'Footer', 'group' => '', 'order' => 1),
	);
	$items = array(
		array('name' => 'Hero band', 'group' => 'Heroes'),
		array('name' => 'hero BAND', 'group' => 'heroes'),
		array('name' => 'Hero band', 'group' => 'Other'),
	);
	$out = pg_library_import_names($items, $existing);
	pg_assert_same('Hero band (2)', $out[0]['name'], 'a name in use in the same group');
	pg_assert_same('hero BAND (3)', $out[1]['name'], 'case does not make another name');
	pg_assert_same('Hero band', $out[2]['name'], 'another group keeps it');
	pg_assert_same(array(5, 6, 7), array($out[0]['order'], $out[1]['order'], $out[2]['order']), 'after the library\'s last');

	$long = str_repeat('x', PG_LIBRARY_NAME_MAX);
	$out = pg_library_import_names(array(array('name' => $long, 'group' => '')), array(array('name' => $long, 'group' => '')));
	pg_assert_same(PG_LIBRARY_NAME_MAX, strlen($out[0]['name']), 'the number fits the name\'s limit');
}
