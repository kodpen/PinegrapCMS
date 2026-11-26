<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The sketch builder of the designer's Prototype view
 * (assets/js/pg_sketch_builder.js, window.PGSketchBuilder): the sections
 * read from a sketch in, the page's header, body and footer out, each built
 * from a starter block and wearing the design's section rhythm. Runs in
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

// Runs `$code` in Node with the reader (R) and the builder (B) loaded, and a
// starter maker `make` that answers small block trees, each with a heading
// and, for the counted blocks, a row of three columns. `made` lists the
// types asked for; the starters are kept in `starters`, so a test can see
// that the builder never changed them. `out` comes back decoded; null when
// Node cannot run.
function sketch_builder_test_node($code)
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
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_sketch_reader.js') . "\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_sketch_builder.js') . "\n"
		. <<<'JS'
var R = window.PGSketchReader, B = window.PGSketchBuilder, out = null, made = [], starters = {};
function n(type, props, kids) { return {_id: 'st', type: type, props: props || {}, children: kids || []}; }
function col() { return n('col', {xs: '12', lg: '4'}, [n('content', {contentType: 'paragraph', text: 'Item'})]); }
function make(type) {
	made.push(type);
	if (type === 'unknown-block') return null;
	if (!starters[type]) {
		var kids = [n('content', {contentType: 'heading', tag: 'h2', text: 'Starter ' + type})];
		if (/^(features|services|image)-/.test(type)) kids.push(n('row', {gutter: '4'}, [col(), col(), col()]));
		starters[type] = n('semantic', {tag: 'section', cssClass: 'py-5', customName: type}, [n('container', {}, kids)]);
	}
	return starters[type];
}
function sec(id, kind, more) {
	var s = {id: id, kind: kind, widget: null, boxes: [id], buttons: 0, columns: 1, heading: '', picture: false, unsure: false};
	for (var k in (more || {})) s[k] = more[k];
	return s;
}
function headingOf(node) {
	if (!node) return null;
	if (node.type === 'content' && node.props.contentType === 'heading') return node.props.text;
	for (var i = 0; i < (node.children || []).length; i++) { var h = headingOf(node.children[i]); if (h !== null) return h; }
	return null;
}
function rowOf(node) {
	if (!node) return null;
	if (node.type === 'row') return node;
	for (var i = 0; i < (node.children || []).length; i++) { var r = rowOf(node.children[i]); if (r) return r; }
	return null;
}
function ids(node, acc) { acc.push(node._id); (node.children || []).forEach(function (c) { ids(c, acc); }); return acc; }

JS
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgsketchb');

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

// The landing page of the reader's test, read and built: the bar on top is
// the header, the bar at the bottom the footer, the rest the body in order,
// each from its block, the band of lines built here.
function test_sketch_builder_builds_a_read_landing_page()
{
	$out = sketch_builder_test_node(<<<'JS'
function b(id, x, y, w, h, more) { var o = {id: id, x: x, y: y, w: w, h: h}; for (var k in (more || {})) o[k] = more[k]; return o; }
var read = R.read({w: 1200, h: 2400}, [
	b('head', 0, 0, 1200, 80),
	b('hero', 0, 100, 1200, 500),
	b('hero-text', 100, 200, 500, 150, {mark: 'lines', parent: 'hero'}),
	b('hero-btn', 100, 400, 200, 60, {parent: 'hero'}),
	b('f1', 20, 650, 360, 250), b('f2', 420, 650, 360, 250), b('f3', 820, 650, 360, 250),
	b('g1', 20, 1400, 270, 270, {mark: 'cross'}), b('g2', 320, 1400, 270, 270, {mark: 'cross'}),
	b('g3', 620, 1400, 270, 270, {mark: 'cross'}), b('g4', 910, 1400, 270, 270, {mark: 'cross'}),
	b('about', 100, 1750, 1000, 400, {mark: 'lines'}),
	b('foot', 0, 2250, 1200, 150)
]);
var page = B.build(read.sections, {make: make, words: {heading: 'About us', paragraph: 'Tell your story.'}});
out = {
	header: [page.header.section, page.header.block, page.header.node.props._sketchId],
	footer: [page.footer.section, page.footer.block],
	body: page.body.map(function (p) { return [p.section, p.kind, p.block]; }),
	gallery: rowOf(page.body[2].node).children.length,
	text: headingOf(page.body[3].node),
	made: made
};
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('head', 'navbar-centered-logo', 'head'), $out['header'], 'the header: no button, the centered logo bar');
	pg_assert_same(array('foot', 'footer-minimal'), $out['footer'], 'the footer');
	pg_assert_same(array(
		array('hero', 'intro', 'hero-centered'),
		array('f1', 'features', 'features-grid'),
		array('g1', 'gallery', 'image-grid'),
		array('about', 'text', null),
	), $out['body'], 'the body, in order from the top');
	pg_assert_same(4, $out['gallery'], 'four crosses in a row: four gallery columns');
	pg_assert_same('About us', $out['text'], 'the text section takes the caller\'s sample heading');
	pg_assert_same(array('navbar-centered-logo', 'hero-centered', 'features-grid', 'image-grid', 'footer-minimal'), $out['made'], 'one starter asked per block section');
}

// The block a section is built from, by kind and what the sketch shows.
function test_sketch_builder_block_for_each_kind()
{
	$out = sketch_builder_test_node(<<<'JS'
out = [
	B.blockFor(sec('a', 'header', {buttons: 1})),
	B.blockFor(sec('a', 'intro', {picture: true, columns: 2})),
	B.blockFor(sec('a', 'intro', {picture: true})),
	B.blockFor(sec('a', 'features', {columns: 2})),
	B.blockFor(sec('a', 'cards', {picture: true})),
	B.blockFor(sec('a', 'cards')),
	B.blockFor(sec('a', 'form')),
	B.blockFor(sec('a', 'cta', {columns: 2})),
	B.blockFor(sec('a', 'cta')),
	B.blockFor(sec('a', 'footer', {columns: 4})),
	B.blockFor(sec('a', 'text')),
	B.blockFor(sec('a', 'picture')),
	B.blockFor(sec('a', 'catalog')),
	B.blockFor(null)
];
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(
		'navbar-light-cta', 'hero-split', 'hero-image-bg', 'features-alt',
		'services-image-cards', 'services-icon-cards', 'contact-form', 'cta-inline', 'cta-simple',
		'footer-columns', null, null, null, null,
	), $out, 'the block of each kind');
}

// A row of equal boxes keeps its count: columns cut or copied (with fresh
// ids), each its share of 12 at lg; more than six is cut to six.
function test_sketch_builder_fits_the_columns()
{
	$out = sketch_builder_test_node(<<<'JS'
var page = B.build([
	sec('f', 'features', {columns: 4}),
	sec('c', 'cards', {columns: 2}),
	sec('g', 'gallery', {columns: 9}),
	sec('x', 'intro', {columns: 3})
], {make: make});
function cols(p) { var r = rowOf(p.node); return r ? r.children.map(function (c) { return c.props.lg + '/' + (c.props.md || ''); }) : null; }
var all = ids(page.body[0].node, []);
out = {
	features: cols(page.body[0]),
	cards: cols(page.body[1]),
	gallery: cols(page.body[2]),
	intro: cols(page.body[3]),
	unique: all.length === all.filter(function (v, i) { return all.indexOf(v) === i; }).length,
	starter: rowOf(starters['features-grid']).children.length
};
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('3/6', '3/6', '3/6', '3/6'), $out['features'], 'four features: a fourth column copied, 3 of 12 each');
	pg_assert_same(array('6/', '6/'), $out['cards'], 'two cards: the third column gone, half each');
	pg_assert_same(array('2/6', '2/6', '2/6', '2/6', '2/6', '2/6'), $out['gallery'], 'nine pictures: cut to six');
	pg_assert_same(null, $out['intro'], 'an intro has no counted row');
	pg_assert_true($out['unique'], 'every node of a built part has its own id');
	pg_assert_same(3, $out['starter'], 'the starter itself is left as it was');
}

// The typed heading goes into the block's first heading, escaped; body
// sections take the section rhythm, the header and footer do not; what the
// root's style had is kept, and the Styles panel's rows with it.
function test_sketch_builder_heading_and_variables()
{
	$out = sketch_builder_test_node(<<<'JS'
starters['cta-simple'] = n('semantic', {tag: 'section', cssClass: 'py-5 bg-body-tertiary', inlineStyle: 'color: red;', _styles: [{prop: 'color', val: 'red'}], _stylesInit: true}, [
	n('content', {contentType: 'heading', tag: 'h2', text: 'Act now'})
]);
var page = B.build([
	sec('h', 'header', {heading: 'Brand'}),
	sec('i', 'intro', {heading: 'Fish & <chips>'}),
	sec('c', 'cta'),
	sec('f', 'footer')
], {make: make});
out = {
	heading: headingOf(page.body[0].node),
	introStyle: page.body[0].node.props.inlineStyle,
	ctaStyle: page.body[1].node.props.inlineStyle,
	ctaRows: page.body[1].node.props._styles.map(function (s) { return s.prop; }),
	ctaHeading: headingOf(page.body[1].node),
	header: page.header.node.props.inlineStyle || '',
	footer: page.footer.node.props.inlineStyle || '',
	starter: starters['cta-simple'].props.inlineStyle
};
JS
	);

	if ($out === null) {
		return;
	}

	$rhythm = 'padding-top: var(--pg-var-space-section, 3rem); padding-bottom: var(--pg-var-space-section, 3rem)';
	pg_assert_same('Fish &amp; &lt;chips&gt;', $out['heading'], 'the typed heading, escaped');
	pg_assert_same($rhythm, $out['introStyle'], 'a body section takes the section rhythm');
	pg_assert_same('color: red; ' . $rhythm, $out['ctaStyle'], 'its own style is kept before it');
	pg_assert_same(array('color', 'padding-top', 'padding-bottom'), $out['ctaRows'], 'the Styles panel rows follow');
	pg_assert_same('Act now', $out['ctaHeading'], 'no typed heading: the starter\'s stays');
	pg_assert_same('', $out['header'], 'the header takes no section rhythm');
	pg_assert_same('', $out['footer'], 'the footer takes no section rhythm');
	pg_assert_same('color: red;', $out['starter'], 'the starter itself is left as it was');
}

// "Build again": an edited section is given back as it is; a system widget is
// left to the caller; a block the editor cannot make falls back to a text
// section; a picture section is built here; the input is not changed.
function test_sketch_builder_kept_widgets_and_fallbacks()
{
	$out = sketch_builder_test_node(<<<'JS'
var edited = n('semantic', {tag: 'section', customName: 'Mine'});
var list = [
	sec('i', 'intro'),
	sec('k', 'catalog', {widget: 'catalog'}),
	sec('p', 'picture', {heading: 'Our shop', unsure: true}),
	sec('t', 'text')
];
var before = JSON.stringify(list);
var page = B.build(list, {make: function (type) { return type === 'hero-centered' ? null : make(type); }, kept: {t: edited}, words: {heading: 'Title', paragraph: 'Words', alt: 'Picture'}});
out = {
	intro: [page.body[0].block, headingOf(page.body[0].node), page.body[0].node.children[0].children[0].children[0].children[1].props.text],
	widget: [page.body[1].widget, page.body[1].node, page.body[1].block],
	picture: [page.body[2].unsure, headingOf(page.body[2].node), page.body[2].node.children[0].children[1].props.alt],
	kept: [page.body[3].kept, page.body[3].node === edited, edited.props.inlineStyle || ''],
	same: JSON.stringify(list) === before,
	none: B.build('x', {}),
	empty: B.build([null, sec('f', 'footer')], {}).footer.block
};
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(null, 'Title', 'Words'), $out['intro'], 'no starter: a text section with the sample words');
	pg_assert_same(array('catalog', null, null), $out['widget'], 'a system widget is placed by the caller');
	pg_assert_same(array(true, 'Our shop', 'Our shop'), $out['picture'], 'a picture section, still marked unsure');
	pg_assert_same(array(true, true, ''), $out['kept'], 'an edited section is given back untouched');
	pg_assert_true($out['same'], 'the sections handed in are not changed');
	pg_assert_same(array('header' => null, 'footer' => null, 'body' => array()), $out['none'], 'no list: nothing built');
	pg_assert_same(null, $out['empty'], 'no maker: the footer falls back and has no block');
}

// What the site knows: a new header and footer of the site's name and the
// pages' links (at most six, by address under the site path), the intro from
// "Page details", the kind's name on a section with no typed word, and the
// number in the heading of a copied column.
function test_sketch_builder_site_page_and_names()
{
	$out = sketch_builder_test_node(<<<'JS'
function links(node, acc) {
	if (node.type === 'content' && node.props.contentType === 'link') acc.push([node.props.text, node.props.href]);
	(node.children || []).forEach(function (c) { links(c, acc); });
	return acc;
}
function texts(node, acc) {
	if (node.type === 'content') acc.push(node.props.text);
	(node.children || []).forEach(function (c) { texts(c, acc); });
	return acc;
}
var pages = [{name: 'Home', address: 'home'}, {name: 'About & us', address: '/about us/'}, {name: '', address: 'x'}, {name: 'No address', address: ''}];
for (var i = 3; i <= 9; i++) pages.push({name: 'P' + i, address: 'p' + i});
var site = {name: 'Fish <Co>', year: 2026, path: '/shop/', pages: pages, toggle: 'Menu'};
var list = [
	sec('h', 'header', {buttons: 1}),
	sec('i', 'intro'),
	sec('f', 'features', {columns: 5}),
	sec('g', 'gallery'),
	sec('c', 'cta', {heading: 'Typed'}),
	sec('o', 'footer', {columns: 3})
];
var page = B.build(list, {make: make, site: site, page: {title: 'Our <menu>', description: 'Fresh daily', name: 'Page 1'}, names: {features: 'Features', gallery: 'Gallery', cta: 'Call to action'}});
function hcol(t) { return n('col', {lg: '4'}, [n('content', {contentType: 'heading', tag: 'h3', text: t})]); }
var grid = n('semantic', {tag: 'section'}, [n('container', {}, [n('content', {contentType: 'heading', text: 'Head'}), n('row', {}, [hcol('Fast'), hcol('Fresh 2')])])]);
var counted = B.build([sec('f', 'features', {columns: 4})], {make: function () { return grid; }});
var cols = rowOf(counted.body[0].node).children.map(function (c) { return headingOf(c); });
var bare = B.build([sec('i', 'intro'), sec('t', 'text')], {make: make, page: {title: '', description: '', name: 'Page 7'}, words: {heading: 'Sample', paragraph: 'Words'}});
var noSite = B.build([sec('h', 'header')], {make: make});
out = {
	header: [page.header.block, page.header.node.type, page.header.node.props.tag, page.header.node.props._sketchId, links(page.header.node, [])],
	footer: [page.footer.block, page.footer.node.props.tag, texts(page.footer.node, []).filter(function (t) { return /Fish/.test(t); }), links(page.footer.node, []).length],
	intro: texts(page.body[0].node, []).slice(0, 2),
	features: [page.body[1].node.props.customName, headingOf(page.body[1].node)],
	cols: cols,
	gallery: headingOf(page.body[2].node),
	cta: headingOf(page.body[3].node),
	bare: [headingOf(bare.body[0].node), headingOf(bare.body[1].node), texts(bare.body[1].node, [])[1]],
	noSite: [noSite.header.block, made.indexOf('navbar-light-cta') === -1]
};
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array(null, 'semantic', 'nav', 'h', array(
		array('Fish &lt;Co&gt;', '/shop/'),
		array('Home', '/shop/home'),
		array('About &amp; us', '/shop/about%20us'),
		array('P3', '/shop/p3'),
		array('P4', '/shop/p4'),
		array('P5', '/shop/p5'),
		array('P6', '/shop/p6'),
	)), $out['header'], 'a new header: the brand, then at most six pages with a name and an address');
	pg_assert_same(array(null, 'footer', array('Fish &lt;Co&gt;', '&copy; 2026 Fish &lt;Co&gt;'), 6), $out['footer'], 'a new footer: the name, the year, the same links');
	pg_assert_same(array('Our &lt;menu&gt;', 'Fresh daily'), $out['intro'], 'the intro: the title and the description of "Page details"');
	pg_assert_same(array('Features', 'Features'), $out['features'], 'no typed word: the kind\'s name is the heading and the name');
	pg_assert_same(array('Fast', 'Fresh 2', 'Fresh 3', 'Fresh 4'), $out['cols'], 'a copied column takes its number in its heading');
	pg_assert_same('Gallery', $out['gallery'], 'a gallery with no typed word');
	pg_assert_same('Typed', $out['cta'], 'a typed word wins');
	pg_assert_same(array('Page 7', 'Sample', 'Words'), $out['bare'], 'no title: the page\'s name; no names: the sample words');
	pg_assert_same(array('navbar-centered-logo', true), $out['noSite'], 'without the site the starter header stays; with it no navbar starter is asked for');
}

// Starters shaped as the palette's are: a badge (a heading drawn as a
// <span>) before the headline, a card's badge before its title, and the
// image-background hero whose picture sits in a style attribute and whose
// padding is not py-5.
function test_sketch_builder_reads_real_starter_shapes()
{
	$out = sketch_builder_test_node(<<<'JS'
function badge(t) { return n('content', {contentType: 'heading', tag: 'span', text: t, cssClass: 'badge'}); }
starters['hero-centered'] = n('semantic', {tag: 'section', cssClass: 'py-5'}, [n('container', {}, [n('semantic', {tag: 'div'}, [
	badge('New!'),
	n('content', {contentType: 'heading', tag: 'h1', text: 'Turn your ideas into reality'}),
	n('content', {contentType: 'paragraph', text: 'Starter words'})
])])]);
starters['hero-image-bg'] = n('semantic', {tag: 'section', cssClass: 'd-flex pg-hero-tall', _attrs: [{name: 'style', value: 'background-image: url(x.jpg)'}]}, [
	n('semantic', {tag: 'div', cssClass: 'py-5'}, [badge('New!'), n('content', {contentType: 'heading', tag: 'h1', text: 'Old'})])
]);
function card(t) { return n('col', {lg: '4', md: '6'}, [badge('30 min'), n('content', {contentType: 'heading', tag: 'h3', text: t})]); }
starters['services-image-cards'] = n('semantic', {tag: 'section', cssClass: 'py-5'}, [n('container', {}, [
	n('content', {contentType: 'heading', tag: 'h2', text: 'Visits'}),
	n('row', {}, [card('First Visit'), card('Follow-up')])
])]);
function heads(node, acc) {
	if (node.type === 'content' && node.props.contentType === 'heading') acc.push(node.props.text);
	(node.children || []).forEach(function (c) { heads(c, acc); });
	return acc;
}
function paras(node, acc) {
	if (node.type === 'content' && node.props.contentType === 'paragraph') acc.push(node.props.text);
	(node.children || []).forEach(function (c) { paras(c, acc); });
	return acc;
}
var page = B.build([
	sec('i', 'intro'),
	sec('p', 'intro', {picture: true, heading: 'Typed'}),
	sec('c', 'cards', {picture: true, columns: 3})
], {make: make, page: {title: 'Our bakery', description: 'Bread every morning.'}});
out = {
	intro: heads(page.body[0].node, []),
	introText: paras(page.body[0].node, []),
	introStyle: page.body[0].node.props.inlineStyle || '',
	picture: heads(page.body[1].node, []),
	pictureStyle: page.body[1].node.props.inlineStyle || '',
	cards: heads(page.body[2].node, []),
	ids: page.body.map(function (p) { return p.node.props._sketchId; })
};
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('New!', 'Our bakery'), $out['intro'], 'the page title goes into the h1, the badge keeps its word');
	pg_assert_same(array('Bread every morning.'), $out['introText'], 'the description is the intro\'s paragraph');
	pg_assert_true($out['introStyle'] !== '', 'a py-5 section takes the section rhythm');
	pg_assert_same(array('New!', 'Typed'), $out['picture'], 'the typed word goes into the h1');
	pg_assert_same('', $out['pictureStyle'], 'a root with a style attribute keeps it: no inline style hides its picture');
	pg_assert_same(array('Visits', '30 min', 'First Visit', '30 min', 'Follow-up', '30 min', 'Follow-up 3'), $out['cards'], 'a copied card numbers its title, not its badge');
	pg_assert_same(array('i', 'p', 'c'), $out['ids'], 'every root carries its section id');
}

// The editor's maker asks for a starter as the palette does
// ('component' with its componentType), and every starter the builder can
// name is one createFromPalette() builds: a ctype branch, a block family's
// item or a COMPONENTS entry.
function test_sketch_builder_starters_are_palette_components()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$at = strpos($src, 'function _sdSketchBuildHost()');
	pg_assert_true($at !== false, '_sdSketchBuildHost() is found');
	$host = substr($src, (int) $at, 6000);
	pg_assert_true((bool) preg_match('/make:\s*function\s*\(type\)\s*\{.*?createFromPalette\(\'component\',\s*\{\s*componentType:\s*type\s*\}\)/s', $host), 'the maker asks createFromPalette(\'component\', {componentType: type})');

	$out = sketch_builder_test_node(<<<'JS'
var seen = {};
['header', 'intro', 'features', 'cards', 'gallery', 'form', 'cta', 'footer', 'text', 'picture', 'catalog', 'blog', 'booking'].forEach(function (kind) {
	[false, true].forEach(function (picture) {
		[0, 1, 2, 3, 4, 7].forEach(function (columns) {
			[0, 1].forEach(function (buttons) {
				var t = B.blockFor(sec('a', kind, {picture: picture, columns: columns, buttons: buttons}));
				if (t) seen[t] = true;
			});
		});
	});
});
out = Object.keys(seen).sort();
JS
	);

	if ($out === null) {
		return;
	}

	pg_assert_true(count($out) >= 10, 'the builder names its starters');
	$start = strpos($src, 'function createFromPalette(');
	// The function's body: up to the next function at its indent.
	$end = strpos($src, "\n    function ", (int) $start + 10);
	$palette = substr($src, (int) $start, $end === false ? null : $end - (int) $start);
	$families = '';
	foreach ((array) glob(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_*.js') as $file) {
		if (substr($file, -7) !== '.min.js') {
			$families .= (string) file_get_contents($file);
		}
	}
	$components = (string) substr($src, (int) strpos($src, 'var COMPONENTS = {'), 20000);
	foreach ($out as $name) {
		$q = preg_quote($name, '/');
		$built = preg_match('/ctype === \'' . $q . '\'/', $palette)
			|| preg_match('/componentType:\s*\'' . $q . '\'/', $families)
			|| preg_match('/^\s*\'?' . $q . '\'?\s*:\s*\{/m', $components);
		pg_assert_true((bool) $built, 'createFromPalette() builds the starter ' . $name);
	}
}
