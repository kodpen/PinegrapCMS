<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the variant chooser (assets/js/pg_civ_variants.js) writes a
 * bound value, run under Node against a small fake page: a plain-text field
 * (the name, short description, brand, MPN, GTIN, weight, custom fields and
 * reference label) is written as text, as the server's escaped token shows
 * it, from the page's payload and from the get_product refresh alike; only
 * the keys that hold markup (the description, details, stock notice and
 * formatted prices) are written as HTML. A picked variant shows what its
 * page shows: the strike-through price block while an offer lowers the
 * price (_civ_price_shown()), the short description as the photo's alt
 * text (_civ_image_alt()), and a rich text's value inside the markup the
 * design wrapped around it. The browser fetches the changed script again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The bindings of the fake page, one element each.
function civ_variants_text_test_bindings()
{
	return array(
		'text:name', 'html:name', 'text:short_description', 'text:brand', 'text:mpn', 'text:gtin',
		'text:weight', 'text:custom_field_1', 'text:custom_field_4', 'text:reference_label',
		'text:description', 'text:details', 'text:out_of_stock_message', 'text:price_formatted',
		'text:original_price_formatted', 'text:discount_amount_formatted', 'text:reference_price',
		'text:price_range', 'text:price_block_html', 'alt:image_alt',
	);
}

// The strike-through price block of a price lowered by an offer, else of a
// price without one, as _pg_format_price_with_discount() draws them.
function civ_variants_text_test_block($offer)
{
	return $offer
		? '<span class="pg-price-block has-discount"><span class="pg-price-original">&#8378;399,95</span><span class="pg-price-current">&#8378;299,95</span></span>'
		: '<span class="pg-price-block"><span class="pg-price">&#8378;299,95</span></span>';
}

// One variant of the payload, every text field holding markup.
function civ_variants_text_test_variant($id, $option)
{
	return array(
		'id'                        => $id,
		'name'                      => '<img src=x onerror=alert(' . $id . ')>Chair ' . $id,
		'short_description'         => '<b>Soft</b> & warm',
		'description'               => '<p>Rich <em>text</em></p>',
		'details'                   => '<ul><li>Oak</li></ul>',
		'price_formatted'           => civ_variants_text_test_block(true),
		'price_range'               => civ_variants_text_test_block(true),
		'price_block_html'          => civ_variants_text_test_block(true),
		'original_price_formatted'  => '&#8378;399,95',
		'discount_amount_formatted' => '&#8378;100,00',
		'reference_price'           => '₺349,95',
		'reference_label'           => 'Lowest <price>',
		'image_alt'                 => '24" <Monitor>',
		'mpn'                       => '<i>M-' . $id . '</i>',
		'gtin'                      => '<s>869</s>',
		'brand'                     => '<marquee>Acme</marquee>',
		'weight'                    => '<u>2</u>',
		'custom_field_1'            => '<script>x()</script>',
		'custom_field_4'            => '&amp; four',
		'out_of_stock_message'      => '<strong>Gone</strong>',
		'out_of_stock'              => '0',
		'gallery'                   => array(),
		'attrs'                     => array('7' => $option),
	);
}

// Runs the variant script under Node on the fake page: a product group with
// variants 1 (option 71, shown) and 2 (option 72), the get_product replies
// $replies in the order asked. $options: 'variants' (fields of variant 1 or
// 2 to change, by id), 'rich' (elements the server drew with markup around
// the value: the binding, the tag and its kids, each a text or an element
// with tag, attrs and kids) and 'picks' (the options picked one after the
// other, '72' by default). Prints what each element was given after the
// load (the refresh of the variant shown), after each pick and after its
// refresh; a rich element also its markup. Null when there is no Node.
function civ_variants_text_test_run($replies, $options = array())
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

	$variants = array(1 => civ_variants_text_test_variant(1, 71), 2 => civ_variants_text_test_variant(2, 72));
	$changes = isset($options['variants']) ? $options['variants'] : array();

	foreach ($changes as $id => $fields) {
		$variants[$id] = array_merge($variants[$id], $fields);
	}

	$payload = array(
		'group_id'           => 5,
		'group_name'         => 'Chairs',
		'attrs'              => array('7' => array('label' => 'Colour', 'options' => array('71' => 'Oak', '72' => 'Walnut'))),
		'products'           => array_values($variants),
		'default_product_id' => 1,
		'default_selection'  => array('7' => 71),
		'api_url'            => '/software/api.php',
	);

	$script = <<<'JS'
var vm = require('vm');
var fs = require('fs');

var errors = [];
function parse(sel) {
	var m = /^([a-zA-Z]*)((?:\.[\w-]+)*)((?:\[[^\]]+\])*)$/.exec(sel.trim());
	if (!m) { errors.push('selector: ' + sel); throw new Error('selector: ' + sel); }
	var attrs = (m[3].match(/\[[^\]]+\]/g) || []).map(function (a) {
		var am = /^\[([\w-]+)(?:="([^"]*)")?\]$/.exec(a);
		return { name: am[1], value: am[2] };
	});
	return { tag: m[1].toUpperCase(), classes: m[2].split('.').filter(Boolean), attrs: attrs };
}

function esc(s) {
	return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
// A node as markup: a text node escaped, an element with its attributes.
function serialize(n) {
	if (n.nodeType === 3) return esc(n.nodeValue);
	var tag = n.tagName.toLowerCase();
	var attrs = Object.keys(n.attrs).map(function (k) { return ' ' + k + '="' + esc(n.attrs[k]) + '"'; }).join('');
	return '<' + tag + attrs + '>' + n.innerHTML + '</' + tag + '>';
}
function nextSibling() {
	var p = this.parentNode;
	return p ? (p.childNodes[p.childNodes.indexOf(this) + 1] || null) : null;
}

function Text(value) {
	this.nodeType = 3;
	this.nodeName = '#text';
	this.parentNode = null;
	this.nodeValue = String(value);
}
Object.defineProperty(Text.prototype, 'nextSibling', { get: nextSibling });

function El(tag, attrs) {
	this.nodeType = 1;
	this.tagName = this.nodeName = String(tag).toUpperCase();
	this.attrs = {};
	this.childNodes = [];
	// Markup written with innerHTML, kept as written: there is no parser.
	this.raw = null;
	this.parentNode = null;
	this.listeners = {};
	this.writes = [];
	var self = this;
	Object.keys(attrs || {}).forEach(function (k) { self.attrs[k] = String(attrs[k]); });
	this.classList = {
		list: function () { return (self.attrs['class'] || '').split(/\s+/).filter(Boolean); },
		contains: function (c) { return this.list().indexOf(c) !== -1; },
		add: function (c) { var l = this.list(); if (l.indexOf(c) === -1) l.push(c); self.attrs['class'] = l.join(' '); },
		remove: function (c) { self.attrs['class'] = this.list().filter(function (x) { return x !== c; }).join(' '); },
		toggle: function (c, on) { if (on === undefined) on = !this.contains(c); if (on) this.add(c); else this.remove(c); return on; }
	};
}
El.prototype.empty = function () {
	this.childNodes.forEach(function (c) { c.parentNode = null; });
	this.childNodes = [];
	this.raw = null;
};
// What the script writes into an element, and how.
Object.defineProperty(El.prototype, 'innerHTML', {
	get: function () { return this.raw !== null ? this.raw : this.childNodes.map(serialize).join(''); },
	set: function (v) { this.writes.push('html:' + v); this.empty(); this.raw = String(v); }
});
Object.defineProperty(El.prototype, 'textContent', {
	get: function () { return this.raw !== null ? this.raw : this.childNodes.map(function (c) { return c.nodeType === 3 ? c.nodeValue : c.textContent; }).join(''); },
	set: function (v) { this.writes.push('text:' + v); this.empty(); if (String(v) !== '') this.appendChild(new Text(v)); }
});
Object.defineProperty(El.prototype, 'children', { get: function () { return this.childNodes.filter(function (c) { return c.nodeType === 1; }); } });
Object.defineProperty(El.prototype, 'firstChild', { get: function () { return this.childNodes[0] || null; } });
Object.defineProperty(El.prototype, 'nextSibling', { get: nextSibling });
El.prototype.appendChild = function (c) { c.parentNode = this; this.childNodes.push(c); return c; };
El.prototype.contains = function (n) {
	for (; n; n = n.parentNode) { if (n === this) return true; }
	return false;
};
El.prototype.getAttribute = function (n) { return Object.prototype.hasOwnProperty.call(this.attrs, n) ? this.attrs[n] : null; };
El.prototype.setAttribute = function (n, v) { this.attrs[n] = String(v); };
El.prototype.removeAttribute = function (n) { delete this.attrs[n]; };
El.prototype.hasAttribute = function (n) { return Object.prototype.hasOwnProperty.call(this.attrs, n); };
El.prototype.matches = function (sel) {
	var self = this;
	return sel.split(',').some(function (one) {
		var c = parse(one);
		if (c.tag && c.tag !== self.tagName) return false;
		if (!c.classes.every(function (k) { return self.classList.contains(k); })) return false;
		return c.attrs.every(function (a) { return self.hasAttribute(a.name) && (a.value === undefined || self.attrs[a.name] === a.value); });
	});
};
El.prototype.querySelectorAll = function (sel) {
	var out = [];
	(function walk(n) { n.children.forEach(function (c) { if (c.matches(sel)) out.push(c); walk(c); }); })(this);
	return out;
};
El.prototype.querySelector = function (sel) { return this.querySelectorAll(sel)[0] || null; };
El.prototype.closest = function (sel) {
	for (var n = this; n && n.matches; n = n.parentNode) { if (n.matches(sel)) return n; }
	return null;
};
El.prototype.addEventListener = function (type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn); };
El.prototype.dispatchEvent = function (ev) { (this.listeners[ev.type] || []).forEach(function (fn) { fn(ev); }); return true; };

var html = new El('html', { 'data-pg-lang': 'en' });
var body = html.appendChild(new El('body'));
var doc = {
	readyState: 'complete',
	documentElement: html,
	body: body,
	addEventListener: function () {},
	querySelector: function (sel) { return html.querySelector(sel); },
	querySelectorAll: function (sel) { return html.querySelectorAll(sel); },
	createElement: function (tag) { return new El(tag); },
	getElementById: function () { return null; }
};

// The widget as the server draws it: the form, the picker and one element
// per binding.
var form = body.appendChild(new El('form', { 'data-pg-civ-form': '1' }));
var picker = form.appendChild(new El('div', { 'class': 'pg-civ-variant-picker', 'data-pg-civ-group-id': '5' }));
var select = picker.appendChild(new El('select', { 'class': 'pg-civ-variant-attr', 'data-pg-civ-attr': '7' }));
var button = form.appendChild(new El('button', { type: 'submit' }));
button.form = form;
var bound = {};
BINDINGS.forEach(function (spec) {
	bound[spec] = form.appendChild(new El(spec.indexOf('alt:') === 0 ? 'img' : 'span', { 'data-pg-bind': spec }));
});
// Elements drawn with markup around the value: a string is a text node,
// an object an element with its kids.
function build(parent, kids) {
	(kids || []).forEach(function (k) {
		if (typeof k === 'string') parent.appendChild(new Text(k));
		else build(parent.appendChild(new El(k.tag, k.attrs)), k.kids);
	});
}
var rich = RICH.map(function (r) {
	var el = form.appendChild(new El(r.tag, { 'data-pg-bind': r.bind }));
	build(el, r.kids);
	return el;
});

var replies = REPLIES;
var asked = [];
function fakeFetch(url, opts) {
	asked.push(JSON.parse(opts.body).product.id);
	var r = replies.shift();
	if (!r) return Promise.reject(new Error('offline'));
	return Promise.resolve({ json: function () { return Promise.resolve(r); } });
}
function FakeEvent(type, init) { this.type = type; this.bubbles = !!(init && init.bubbles); this.detail = init && init.detail; }

var page = { document: doc, fetch: fakeFetch, CustomEvent: FakeEvent, Event: FakeEvent, URL: URL, pgCivVariants: [PAYLOAD] };
page.window = page;
vm.createContext(page);
vm.runInContext(fs.readFileSync(SCRIPT, 'utf8'), page, { filename: 'pg_civ_variants.js' });

// What each element was given since the last look, what an image's alt is,
// and the markup of the rich elements.
function look() {
	var seen = {};
	Object.keys(bound).forEach(function (spec) {
		seen[spec] = bound[spec].writes;
		bound[spec].writes = [];
	});
	seen.alt = bound['alt:image_alt'].alt === undefined ? null : bound['alt:image_alt'].alt;
	seen.rich = rich.map(function (el) {
		var r = { writes: el.writes, html: el.innerHTML };
		el.writes = [];
		return r;
	});
	return seen;
}
function tick() { return new Promise(function (r) { setTimeout(r, 0); }); }

(async function () {
	var out = {};
	await tick(); await tick();
	out.load = look();
	out.steps = [];
	for (var i = 0; i < PICKS.length; i++) {
		select.value = String(PICKS[i]);
		(select.listeners.change || []).forEach(function (fn) { fn({}); });
		var step = { pick: look() };
		await tick(); await tick();
		step.refresh = look();
		out.steps.push(step);
	}
	out.pick = out.steps[0].pick;
	out.refresh = out.steps[0].refresh;
	out.asked = asked;
	out.disabled = button.disabled;
	out.errors = errors;
	console.log(JSON.stringify(out));
})();
JS;

	$script = strtr($script, array(
		'BINDINGS' => json_encode(civ_variants_text_test_bindings()),
		'RICH'     => json_encode(isset($options['rich']) ? $options['rich'] : array(), JSON_UNESCAPED_UNICODE),
		'PICKS'    => json_encode(isset($options['picks']) ? $options['picks'] : array('72')),
		'REPLIES'  => json_encode($replies, JSON_UNESCAPED_UNICODE),
		'PAYLOAD'  => json_encode($payload, JSON_UNESCAPED_UNICODE),
		'SCRIPT'   => json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_civ_variants.js'),
	));

	$file = @tempnam(sys_get_temp_dir(), 'pgcivtext');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$text = implode("\n", $out);
	$data = json_decode($text, true);

	if (($code !== 0) || !is_array($data)) {
		return array('error' => $text);
	}

	return $data;
}

// A get_product reply for variant $id: raw text, as pg_panel_get_product()
// answers, the formatted prices and the stock notice as markup.
function civ_variants_text_test_reply($id)
{
	return array('status' => 'success', 'product' => array(
		'id'                  => $id,
		'name'                => '<b>API</b> chair ' . $id,
		'short_description'   => '<i>from the API</i>',
		'brand'               => 'Smith & <Sons>',
		'custom_field_1'      => '<svg onload=x()>',
		'details'             => '<ul><li>API ' . $id . '</li></ul>',
		'full_description'    => '<p>Not a token</p>',
		'price_formatted'     => '&#8378;10,00',
		'out_of_stock_notice' => '<strong>Back soon</strong>',
		'out_of_stock'        => 0,
		'gallery'             => array(),
	));
}

// A get_product reply for variant $id with its price, as
// pg_panel_get_product() answers it: price_formatted the amount, and the
// strike-through block while an offer lowers it as price_block_html.
function civ_variants_text_test_price_reply($id, $offer)
{
	return array('status' => 'success', 'product' => array(
		'id'               => $id,
		'price_formatted'  => '&#8378;299,95',
		'has_discount'     => $offer ? '1' : '0',
		'price_block_html' => civ_variants_text_test_block($offer),
		'out_of_stock'     => 0,
		'gallery'          => array(),
	));
}

// The payload's plain text is written as text and its markup as HTML when a
// variant is picked; the refreshes (after the load and after the pick)
// write the API's raw text as text too.
function test_civ_variants_writes_plain_text_as_text()
{
	$r = civ_variants_text_test_run(array(civ_variants_text_test_reply(1), civ_variants_text_test_reply(2)));

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), 'the script runs: ' . (isset($r['error']) ? $r['error'] : ''));
	pg_assert_same(array(), $r['errors'], 'every selector the script asked for was understood');
	pg_assert_same(array(1, 2), $r['asked'], 'refreshed the variant shown, then the one picked');
	pg_assert_false($r['disabled'], 'add to cart usable for the variant picked');

	// The refresh after the load.
	pg_assert_same(array('text:<b>API</b> chair 1'), $r['load']['text:name'], 'the name from the API as text');
	pg_assert_same(array('text:<i>from the API</i>'), $r['load']['text:short_description']);
	pg_assert_same(array('text:Smith & <Sons>'), $r['load']['text:brand']);
	pg_assert_same(array('text:<svg onload=x()>'), $r['load']['text:custom_field_1']);
	pg_assert_same(array('html:<ul><li>API 1</li></ul>'), $r['load']['text:details'], 'details stay HTML');
	pg_assert_same(array('html:&#8378;10,00'), $r['load']['text:price_formatted'], 'the price stays HTML');
	pg_assert_same(array('html:<strong>Back soon</strong>'), $r['load']['text:out_of_stock_message'], 'the stock notice stays HTML');
	pg_assert_same(array(), $r['load']['text:description'], 'get_product has no description key');

	// Picking variant 2 writes its payload row.
	$v = civ_variants_text_test_variant(2, 72);
	foreach (array('name', 'short_description', 'brand', 'mpn', 'gtin', 'weight', 'custom_field_1', 'custom_field_4', 'reference_label') as $key) {
		pg_assert_same(array('text:' . $v[$key]), $r['pick']['text:' . $key], $key . ' written as text');
	}
	pg_assert_same(array('text:' . $v['name']), $r['pick']['html:name'], 'an html binding of a text key is text too, as the server escapes it');
	foreach (array('description', 'details', 'out_of_stock_message', 'price_formatted', 'price_range', 'price_block_html', 'original_price_formatted', 'discount_amount_formatted', 'reference_price') as $key) {
		pg_assert_same(array('html:' . $v[$key]), $r['pick']['text:' . $key], $key . ' written as HTML');
	}
	pg_assert_same(array(), $r['pick']['alt:image_alt'], 'an alt is no content');
	pg_assert_same($v['image_alt'], $r['pick']['alt'], 'the alt as typed');

	// Its refresh.
	pg_assert_same(array('text:<b>API</b> chair 2'), $r['refresh']['text:name'], 'the refresh of the variant picked');
	pg_assert_same(array('html:<ul><li>API 2</li></ul>'), $r['refresh']['text:details']);
}

// The refresh shows the price as the page draws it: the strike-through
// block while an offer lowers it, else the amount, where the formatted
// price or the price range is bound; the price block is the block either
// way.
function test_civ_variants_refresh_shows_the_price_block()
{
	$r = civ_variants_text_test_run(array(civ_variants_text_test_price_reply(1, true), civ_variants_text_test_price_reply(2, false)));

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), 'the script runs: ' . (isset($r['error']) ? $r['error'] : ''));
	pg_assert_same(array(1, 2), $r['asked']);

	$block = civ_variants_text_test_block(true);
	pg_assert_same(array('html:' . $block), $r['load']['text:price_formatted'], 'an offer: the block, not the amount');
	pg_assert_same(array('html:' . $block), $r['load']['text:price_range'], 'the range as the formatted price');
	pg_assert_same(array('html:' . $block), $r['load']['text:price_block_html']);

	pg_assert_same(array('html:&#8378;299,95'), $r['refresh']['text:price_formatted'], 'no offer: the amount');
	pg_assert_same(array('html:&#8378;299,95'), $r['refresh']['text:price_range']);
	pg_assert_same(array('html:' . civ_variants_text_test_block(false)), $r['refresh']['text:price_block_html'], 'the block without a strike');
}

// A rich text keeps the markup the design wrapped around its value
// (pg_binding_text_slot()): the name drawn in a link stays in the link, as
// text, with the spaces around it, on the refresh after the load, on a pick
// and on its refresh. An &nbsp; beside the link is layout, not a second run
// of words; a value's line break, which the page holds as \n, still matches.
function test_civ_variants_rich_text_keeps_its_markup()
{
	$drawn = civ_variants_text_test_variant(1, 71);
	$nbsp = "\xC2\xA0";
	$rich = array(
		array('bind' => 'text:name', 'tag' => 'h2', 'kids' => array(
			array('tag' => 'a', 'attrs' => array('href' => '/chair'), 'kids' => array(' ' . $drawn['name'] . ' ')),
		)),
		array('bind' => 'text:mpn', 'tag' => 'span', 'kids' => array(
			$nbsp,
			array('tag' => 'a', 'attrs' => array('href' => '/m'), 'kids' => array($drawn['mpn'])),
		)),
		array('bind' => 'text:short_description', 'tag' => 'p', 'kids' => array(
			array('tag' => 'strong', 'attrs' => array(), 'kids' => array("Soft\nwarm")),
		)),
	);
	$r = civ_variants_text_test_run(
		array(civ_variants_text_test_reply(1), civ_variants_text_test_reply(2)),
		array('rich' => $rich, 'variants' => array(1 => array('short_description' => "Soft\r\nwarm")))
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), 'the script runs: ' . (isset($r['error']) ? $r['error'] : ''));
	pg_assert_same(array(), $r['errors'], 'every selector the script asked for was understood');

	pg_assert_same('<a href="/chair"> &lt;b&gt;API&lt;/b&gt; chair 1 </a>', $r['load']['rich'][0]['html'], 'the refresh writes the name into the link');
	pg_assert_same(array(), $r['load']['rich'][0]['writes'], 'the heading keeps its link');
	pg_assert_same('<a href="/chair"> &lt;img src=x onerror=alert(2)&gt;Chair 2 </a>', $r['pick']['rich'][0]['html'], 'the name picked in the link, as text');
	pg_assert_same(array(), $r['pick']['rich'][0]['writes']);
	pg_assert_same('<a href="/chair"> &lt;b&gt;API&lt;/b&gt; chair 2 </a>', $r['refresh']['rich'][0]['html'], 'and its refresh');

	pg_assert_same($nbsp . '<a href="/m">&lt;i&gt;M-2&lt;/i&gt;</a>', $r['pick']['rich'][1]['html'], 'the &nbsp; before the link stays');
	pg_assert_same(array(), $r['pick']['rich'][1]['writes']);

	pg_assert_same('<strong>&lt;i&gt;from the API&lt;/i&gt;</strong>', $r['load']['rich'][2]['html'], 'a line break drawn as \n is the value');
}

// A variant whose value is empty keeps the place: the markup stays, and the
// next value goes back into it. A page drawn with the value empty shows no
// words to find the place by: there the value takes the whole content, as
// before, also when the element holds other words (a badge in a link).
function test_civ_variants_rich_text_keeps_the_place_of_an_empty_value()
{
	$rich = array(
		array('bind' => 'text:brand', 'tag' => 'p', 'kids' => array(
			array('tag' => 'i', 'attrs' => array('class' => 'bi bi-tag'), 'kids' => array()),
			' ',
			array('tag' => 'em', 'attrs' => array(), 'kids' => array('Acme')),
		)),
		array('bind' => 'text:weight', 'tag' => 'a', 'kids' => array(
			array('tag' => 'span', 'attrs' => array('class' => 'badge'), 'kids' => array('New')),
		)),
	);
	$r = civ_variants_text_test_run(array(), array(
		'rich'     => $rich,
		'variants' => array(1 => array('brand' => 'Acme', 'weight' => ''), 2 => array('brand' => '')),
		'picks'    => array('72', '71'),
	));

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), 'the script runs: ' . (isset($r['error']) ? $r['error'] : ''));
	pg_assert_same(array(1, 2, 1), $r['asked'], 'variant 2 picked, then variant 1 again');

	pg_assert_same('<i class="bi bi-tag"></i> <em></em>', $r['steps'][0]['pick']['rich'][0]['html'], 'an empty brand keeps its place');
	pg_assert_same(array(), $r['steps'][0]['pick']['rich'][0]['writes']);
	pg_assert_same('<i class="bi bi-tag"></i> <em>Acme</em>', $r['steps'][1]['pick']['rich'][0]['html'], 'the brand back in its place');

	pg_assert_same(array('text:<u>2</u>'), $r['steps'][0]['pick']['rich'][1]['writes'], 'drawn empty beside a badge: the whole content');
	pg_assert_same('&lt;u&gt;2&lt;/u&gt;', $r['steps'][0]['pick']['rich'][1]['html']);
}

// The price the page and the payload show: the strike-through block while
// an offer lowers it, as __price_formatted draws it, else the amount.
function test_civ_variants_price_shown_is_the_page_price()
{
	pg_assert_same(_pg_format_price_with_discount(39995, 29995, '&#8378;'), _civ_price_shown(39995, 29995, '&#8378;'), 'an offer: the block');
	pg_assert_contains('pg-price-original', _civ_price_shown(39995, 29995, '&#8378;'));
	pg_assert_same(pg_visitor_money(299.95, '&#8378;'), _civ_price_shown(29995, 29995, '&#8378;'), 'no offer: the amount');
}

// A product photo's alt text is the name the store shows, the short
// description, else the Product ID / SKU.
function test_civ_variants_image_alt_is_the_store_name()
{
	pg_assert_same('Red chair', _civ_image_alt('Red chair', 'CHAIR-RED'));
	pg_assert_same('CHAIR-RED', _civ_image_alt('', 'CHAIR-RED'), 'no short description: the SKU');
}

// The browser fetches the changed script again.
function test_civ_variants_text_script_version_moved()
{
	$view = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');

	pg_assert_same(1, preg_match('/\$_civ_v_v\s*=\s*\'([0-9]+)\';/', $view, $stamp), 'version stamp');
	pg_assert_true((int) $stamp[1] >= 12, 'stamp moved with the price block and the rich text');
}
