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
starters['cta-simple'] = n('semantic', {tag: 'section', inlineStyle: 'color: red;', _styles: [{prop: 'color', val: 'red'}], _stylesInit: true}, [
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
