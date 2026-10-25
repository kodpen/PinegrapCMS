<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the favourite heart of the catalog widgets: the button a listing
 * card binds to catalog_wishlist and a product page to wishlist
 * (_pg_catalog_wishlist_heart() in the two binding passes), the heart taken
 * out of a product group's card (_pg_catalog_wishlist_strip()) and out of a
 * site whose wishlist table is missing, the script a page holding a heart
 * gets (pg_wishlist_inject()), the editor's offer and starter cards, and the
 * script itself (assets/js/pg_wishlist.js) run under Node against a small
 * fake document: it fills the hearts from the state reply, saves and removes
 * with every heart of the product following, puts a refused change back,
 * reads the list again on a page the browser brings back from its
 * back/forward cache and follows the variant a product page switches to.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The attributes of a node as name => value.
function wishlist_heart_test_attrs($node)
{
	$out = array();
	$attrs = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array();

	foreach ($attrs as $a) {
		$out[$a['name']] = $a['value'];
	}

	return $out;
}

// A listing card: an icon-only heart button, a heart on a plain <button>
// with words, and the card's add-to-cart button.
function wishlist_heart_test_card($action = 'catalog_wishlist')
{
	return array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'card'), 'children' => array(
			array('type' => 'component', 'props' => array('componentType' => 'btn', 'btnElement' => 'a', 'href' => '#',
				'variant' => 'light', 'text' => '', 'cssClass' => 'rounded-circle', '_bindings' => array('action' => $action)), 'children' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'i', 'cssClass' => 'bi bi-heart',
					'_attrs' => array(array('name' => 'aria-hidden', 'value' => 'true'))), 'children' => array()),
			)),
			array('type' => 'semantic', 'props' => array('tag' => 'button', 'cssClass' => 'btn btn-link', 'text' => 'Save',
				'_bindings' => array('action' => $action)), 'children' => array()),
			array('type' => 'component', 'props' => array('componentType' => 'btn', 'text' => 'Add to Cart',
				'_bindings' => array('action' => 'catalog_add_to_cart')), 'children' => array()),
		)),
	));
}

// The source of one PHP function.
function wishlist_heart_test_source($name)
{
	$f = new ReflectionFunction($name);
	$lines = file($f->getFileName());

	return implode('', array_slice($lines, $f->getStartLine() - 1, $f->getEndLine() - $f->getStartLine() + 1));
}

// A listing card's heart becomes a hidden plain button marked with the
// row's product; a heart without words is named for a screen reader.
function test_wishlist_heart_listing_binding_marks_the_button()
{
	$tree = wishlist_heart_test_card();
	$used = array();
	_apply_catalog_listing_bindings($tree, array('wishlist_ready' => true), $used);

	$card  = $tree['children'][0];
	$icon  = $card['children'][0];
	$plain = $card['children'][1];

	pg_assert_true(!empty($used['action_catalog_wishlist']), 'the pass records the heart');

	pg_assert_same('button', $icon['props']['btnElement'], 'a real button');
	pg_assert_same('button', $icon['props']['btnType'], 'that submits nothing');
	pg_assert_same('', $icon['props']['href'], 'no address');
	pg_assert_same('rounded-circle', $icon['props']['cssClass'], 'the designer\'s classes as they are');
	pg_assert_same(array(
		'data-pg-wishlist'        => '^^__id^^',
		'aria-pressed'            => 'false',
		'aria-label'              => lang('Add to favourites'),
		'data-pg-wishlist-hidden' => '1',
	), wishlist_heart_test_attrs($icon), 'icon-only heart');

	pg_assert_same('btn btn-link', $plain['props']['cssClass'], 'plain button, hidden by its mark');
	pg_assert_same(array(
		'type'                    => 'button',
		'data-pg-wishlist'        => '^^__id^^',
		'aria-pressed'            => 'false',
		'data-pg-wishlist-hidden' => '1',
	), wishlist_heart_test_attrs($plain), 'a heart with words keeps them as its name');

	// Twice changes nothing more.
	$again = $tree;
	_apply_catalog_listing_bindings($again, array('wishlist_ready' => true), $used);
	pg_assert_same($tree, $again, 'idempotent');
}

// The heart prints as <button type="button">, and the row's product fills
// the mark; the add-to-cart button is left to its own branch.
function test_wishlist_heart_listing_card_renders()
{
	$tree = wishlist_heart_test_card();
	$used = array();
	_apply_catalog_listing_bindings($tree, array('wishlist_ready' => true), $used);

	$html = str_replace('^^__id^^', '42', _render_tree_node($tree['children'][0]['children'][0], 0, 0));

	pg_assert_contains('<button type="button" class="btn btn-light rounded-circle"', $html, 'button');
	pg_assert_contains('data-pg-wishlist="42"', $html, 'the product');
	pg_assert_contains('data-pg-wishlist-hidden="1"', $html, 'hidden until the script shows it');
	pg_assert_contains('aria-pressed="false"', $html, 'not pressed until the script knows');
	pg_assert_contains('class="bi bi-heart"', $html, 'the icon');
	pg_assert_true(!empty($used['action_catalog_add_to_cart']), 'add to cart still bound');
}

// On a site without the wishlist table the heart is not drawn at all.
function test_wishlist_heart_not_ready_drops_the_heart()
{
	$tree = wishlist_heart_test_card();
	$used = array();
	_apply_catalog_listing_bindings($tree, array('wishlist_ready' => false), $used);

	$card = $tree['children'][0];

	pg_assert_same(1, count($card['children']), 'only add to cart is left');
	pg_assert_same('catalog_add_to_cart', $card['children'][0]['props']['_bindings']['action']);
	pg_assert_true(empty($used['action_catalog_wishlist']), 'no heart recorded');

	$view = wishlist_heart_test_card('wishlist');
	_apply_catalog_item_view_bindings($view, array('wishlist_ready' => false));
	pg_assert_same(1, count($view['children'][0]['children']), 'the product page drops it too');
}

// The editor's Preview and the canvas cards the server draws show the heart
// as designed: no script runs there to show it.
function test_wishlist_heart_preview_keeps_it_visible()
{
	$tree = wishlist_heart_test_card();
	$used = array();
	_apply_catalog_listing_bindings($tree, array('wishlist_ready' => true, 'wishlist_preview' => true), $used);

	pg_assert_same('rounded-circle', $tree['children'][0]['children'][0]['props']['cssClass'], 'no d-none');
	pg_assert_same('false', wishlist_heart_test_attrs($tree['children'][0]['children'][0])['aria-pressed'], 'still marked');
	pg_assert_false(isset(wishlist_heart_test_attrs($tree['children'][0]['children'][0])['data-pg-wishlist-hidden']), 'nothing for a script to show');
}

// A heart with display classes of the designer's (d-none d-md-inline-block,
// or d-md-inline-flex alone) keeps them and is hidden by the mark all the
// same: from md up Bootstrap's d-md-* outranks a d-none, so a d-none could
// not hide it there, while the page's rule on the mark does
// (pg_wishlist_inject()).
function test_wishlist_heart_designer_display_classes_stay()
{
	$tree = wishlist_heart_test_card();
	$tree['children'][0]['children'][0]['props']['cssClass'] = 'rounded-circle d-none d-md-inline-block';
	$tree['children'][0]['children'][1]['props']['cssClass'] = 'btn btn-link d-md-inline-flex';
	$used = array();
	_apply_catalog_listing_bindings($tree, array('wishlist_ready' => true), $used);

	$hidden = $tree['children'][0]['children'][0];
	pg_assert_same('rounded-circle d-none d-md-inline-block', $hidden['props']['cssClass'], 'the designer\'s classes as they were');
	pg_assert_same('1', wishlist_heart_test_attrs($hidden)['data-pg-wishlist-hidden'], 'marked though it has a d-none of its own');

	$shown = $tree['children'][0]['children'][1];
	pg_assert_same('btn btn-link d-md-inline-flex', $shown['props']['cssClass'], 'no d-none added');
	pg_assert_same('1', wishlist_heart_test_attrs($shown)['data-pg-wishlist-hidden'], 'marked');

	// A second pass writes the mark once more, not twice.
	$again = $tree;
	_apply_catalog_listing_bindings($again, array('wishlist_ready' => true), $used);
	pg_assert_same($tree, $again, 'idempotent');
}

// The product page's heart sits in the add-to-cart form: a plain button
// with the page's product, never a submit.
function test_wishlist_heart_item_view_binding()
{
	$tree = wishlist_heart_test_card('wishlist');
	_apply_catalog_item_view_bindings($tree, array('wishlist_ready' => true));

	$icon = $tree['children'][0]['children'][0];
	$attrs = wishlist_heart_test_attrs($icon);

	pg_assert_same('button', $icon['props']['btnElement']);
	pg_assert_same('button', $icon['props']['btnType'], 'never submits the add-to-cart form');
	pg_assert_same('^^__id^^', $attrs['data-pg-wishlist'], 'the product shown');
	pg_assert_same('false', $attrs['aria-pressed']);
	pg_assert_same('rounded-circle', $icon['props']['cssClass'], 'no class of the server\'s');
	pg_assert_same('1', $attrs['data-pg-wishlist-hidden'], 'hidden until the script shows it');

	// The listing's action means nothing on a product page.
	$other = wishlist_heart_test_card('catalog_wishlist');
	_apply_catalog_item_view_bindings($other, array('wishlist_ready' => true));
	pg_assert_same(array(), wishlist_heart_test_attrs($other['children'][0]['children'][0]), 'catalog_wishlist is the listing\'s');
}

// A product group's card has no single product: its heart goes, the other
// buttons stay.
function test_wishlist_heart_group_card_loses_the_heart()
{
	$html = '<div class="card"><button type="button" class="btn d-none" data-pg-wishlist="12" aria-pressed="false">'
		. "\n" . '<i class="bi bi-heart"></i>' . "\n" . '</button><a href="/x" class="btn">View</a>'
		. '<button type="button" class="btn">Other</button></div>';

	pg_assert_same('<div class="card"><a href="/x" class="btn">View</a><button type="button" class="btn">Other</button></div>',
		_pg_catalog_wishlist_strip($html));
	pg_assert_same('<p>no heart</p>', _pg_catalog_wishlist_strip('<p>no heart</p>'), 'nothing to take out');

	pg_assert_contains('_pg_catalog_wishlist_strip($g_rendered)', wishlist_heart_test_source('_render_system_widget_catalog_listing'), 'the group loop strips it');
}

// Both renderers tell their pass whether the table is there, and whether
// the editor's Preview is drawing.
function test_wishlist_heart_renderers_pass_readiness()
{
	foreach (array('_render_system_widget_catalog_listing', '_render_system_widget_catalog_item_view') as $name) {
		$source = wishlist_heart_test_source($name);
		pg_assert_contains("'wishlist_ready'", $source, $name);
		pg_assert_contains('pg_wishlist_ready()', $source, $name);
		pg_assert_contains("'wishlist_preview'", $source, $name);
	}
}

// The script and its settings go in once, before the last </body>, only on
// a page holding a heart; the rule that hides a marked heart goes in the
// head, ahead of the first heart, or with the script when there is no head
// before it.
function test_wishlist_heart_inject()
{
	$rule = '<style>[data-pg-wishlist][data-pg-wishlist-hidden]{display:none!important}</style>' . "\n";

	$html = '<html><body><pre>&lt;/body&gt; </body></pre><button data-pg-wishlist="3">x</button></body></html>';
	$out = pg_wishlist_inject($html, '/software/assets/js/pg_wishlist.js?v=1', '/software/wishlist.php');

	pg_assert_same(1, substr_count($out, 'pg_wishlist.js'), 'one script');
	pg_assert_contains('<script src="/software/assets/js/pg_wishlist.js?v=1" defer></script>' . "\n" . '</body></html>', $out);
	pg_assert_contains('window.pgWishlist = {"url":"/software/wishlist.php","labels":{"failed":', $out, 'settings');
	pg_assert_contains('</pre><button data-pg-wishlist="3">x</button>' . $rule . '<script>window.pgWishlist', $out, 'no head: the rule with the script');
	pg_assert_same($out, pg_wishlist_inject($out, '/software/assets/js/pg_wishlist.js?v=1', '/software/wishlist.php'), 'a second call adds nothing');

	$page = '<html><head><title>x</title></head><body><button data-pg-wishlist="3" data-pg-wishlist-hidden="1">x</button></body></html>';
	$out = pg_wishlist_inject($page, '/s.js', '/w.php');
	pg_assert_contains('<title>x</title>' . $rule . '</head>', $out, 'in the head, before any heart is drawn');
	pg_assert_same(1, substr_count($out, '<style>'), 'the rule once');
	pg_assert_contains('<script src="/s.js" defer></script>' . "\n" . '</body></html>', $out, 'the script still at the end');

	// A </head> after the first heart is not the page's head.
	$late = '<div><button data-pg-wishlist="3">x</button><p></head></p></div>';
	pg_assert_contains('<p></head></p></div>' . $rule . '<script>', pg_wishlist_inject($late, '/s.js', '/w.php'), 'the rule with the script');

	$plain = '<html><body><p>data-pg-wishlist is a word here</p></body></html>';
	pg_assert_same($plain, pg_wishlist_inject($plain, '/s.js', '/w.php'), 'no heart, no script');

	$page = file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	pg_assert_true(strpos($page, '$content = pg_wishlist_inject($content);') !== false, 'the page asks for it');
}

// The editor offers the actions on buttons of the two catalog widgets, and
// the starter cards carry a heart.
function test_wishlist_heart_editor_offer_and_starters()
{
	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$has = function ($needle) use ($js) {
		return strpos($js, $needle) !== false;
	};

	pg_assert_true($has("aOpts.push(['catalog_wishlist', _sdT('Favourite (heart) — per card (catalog_listing loop)')]);"), 'listing');
	pg_assert_true($has("aOpts.push(['wishlist', _sdT('Favourite (heart)')]);"), 'product page');
	pg_assert_true($has("_sdWishlistHeart('catalog_wishlist'),"), 'listing starter');
	pg_assert_true($has("_sdWishlistHeart('wishlist')\n"), 'product page starter');
	pg_assert_true($has("btnElement: 'button', btnType: 'button',\n            variant: 'secondary', outline: true, size: 'sm',\n            text: '', customName: _sdT('Favourite'),\n            _bindings: { action: action } }"), 'the starter heart is a plain button bound to the action');
}

// The nodes of a tree of type $type, at any depth.
function wishlist_heart_test_nodes($node, $type)
{
	$found = array();

	if (!is_array($node)) {
		return $found;
	}

	if (isset($node['type']) && $node['type'] === $type) {
		$found[] = $node;
	}

	foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
		$found = array_merge($found, wishlist_heart_test_nodes($child, $type));
	}

	return $found;
}

// How many nodes of a tree are bound to the action $action.
function wishlist_heart_test_bound($node, $action)
{
	if (!is_array($node)) {
		return 0;
	}

	$n = (isset($node['props']['_bindings']['action']) && $node['props']['_bindings']['action'] === $action) ? 1 : 0;

	foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
		$n += wishlist_heart_test_bound($child, $action);
	}

	return $n;
}

// Every product card the store, boutique and bookshop templates draw (the
// loop of each catalog listing with a tree of its own) carries exactly one
// heart; the listings on the starter tree get it from the starter.
function test_wishlist_heart_design_templates()
{
	foreach (array('pinegrap-store', 'pinegrap-boutique', 'pinegrap-bookshop') as $name) {
		$tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $name . '.php');
		$listings = 0;

		foreach ((isset($tpl['widgets']) && is_array($tpl['widgets'])) ? $tpl['widgets'] : array() as $key => $w) {
			if (!isset($w['config']['regionType']) || $w['config']['regionType'] !== 'catalog_listing' || !is_array($w['tree'])) {
				continue;
			}

			$loops = wishlist_heart_test_nodes($w['tree'], 'loop_area');
			pg_assert_true(count($loops) > 0, $name . ' ' . $key . ': a card loop');

			foreach ($loops as $loop) {
				pg_assert_same(1, wishlist_heart_test_bound($loop, 'catalog_wishlist'), $name . ' ' . $key . ': the card carries one heart');
			}

			$listings++;
		}

		pg_assert_true($listings > 0, $name . ': product lists with cards of their own');
	}
}

// A variant picked on a product page is announced, so the heart follows it:
// applyVariant() hands the new id to announceProduct(), which sends a
// bubbling pg-civ-product-change from the form. The script's version stamp
// moved with the change.
function test_wishlist_heart_variant_event()
{
	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_civ_variants.js');

	// One function of the script: from its name to the next function.
	$body = function ($name) use ($js) {
		$start = strpos($js, 'function ' . $name . '(');

		if ($start === false) {
			return '';
		}

		$end = preg_match('/\n\s*function\s/', $js, $m, PREG_OFFSET_CAPTURE, $start + 1) ? $m[0][1] : strlen($js);

		return substr($js, $start, $end - $start);
	};

	$apply = $body('applyVariant');
	pg_assert_contains('if (form) announceProduct(form, p.id);', $apply, 'a picked variant is announced');

	$announce = $body('announceProduct');
	pg_assert_contains('var detail = { productId: String(productId) };', $announce, 'with its id');
	pg_assert_contains("new CustomEvent('pg-civ-product-change', { bubbles: true, detail: detail })", $announce, 'bubbling from the form');
	pg_assert_contains("ev.initCustomEvent('pg-civ-product-change', true, false, detail);", $announce, 'old browsers too');
	pg_assert_contains('form.dispatchEvent(ev);', $announce, 'sent');

	$source = wishlist_heart_test_source('_render_system_widget_catalog_item_view');
	pg_assert_same(1, preg_match('/\$_civ_v_v\s*=\s*\'([0-9]+)\';/', $source, $stamp), 'version stamp');
	pg_assert_true((int)$stamp[1] >= 5, 'version stamp moved with the event');
}

// Runs assets/js/pg_wishlist.js under Node with the steps given; null when
// there is no Node.
function wishlist_heart_test_node($steps)
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

	$prelude = <<<'JS'
var W = require(SCRIPT);

function El(tag, attrs, children) {
	this.tagName = String(tag).toUpperCase();
	this.attrs = {};
	this.children = [];
	this.parentNode = null;
	this.textContent = '';
	var self = this;
	Object.keys(attrs || {}).forEach(function (k) { self.attrs[k] = String(attrs[k]); });
	(children || []).forEach(function (c) { self.appendChild(c); });
	this.classList = {
		list: function () { return (self.attrs['class'] || '').split(/\s+/).filter(Boolean); },
		contains: function (c) { return this.list().indexOf(c) !== -1; },
		add: function (c) { var l = this.list(); if (l.indexOf(c) === -1) l.push(c); self.attrs['class'] = l.join(' '); },
		remove: function (c) { self.attrs['class'] = this.list().filter(function (x) { return x !== c; }).join(' '); },
		toggle: function (c, on) { if (on === undefined) on = !this.contains(c); if (on) this.add(c); else this.remove(c); return on; }
	};
}
El.prototype.appendChild = function (c) { c.parentNode = this; this.children.push(c); return c; };
El.prototype.getAttribute = function (n) { return Object.prototype.hasOwnProperty.call(this.attrs, n) ? this.attrs[n] : null; };
El.prototype.setAttribute = function (n, v) { this.attrs[n] = String(v); };
El.prototype.removeAttribute = function (n) { delete this.attrs[n]; };
El.prototype.hasAttribute = function (n) { return Object.prototype.hasOwnProperty.call(this.attrs, n); };
El.prototype.matches = function (sel) {
	if (sel === '[data-pg-wishlist]') return this.hasAttribute('data-pg-wishlist');
	if (sel === 'i') return this.tagName === 'I';
	return false;
};
El.prototype.querySelectorAll = function (sel) {
	var out = [];
	(function walk(n) { n.children.forEach(function (c) { if (c.matches(sel)) out.push(c); walk(c); }); })(this);
	return out;
};
El.prototype.closest = function (sel) {
	for (var n = this; n; n = n.parentNode) { if (n.matches && n.matches(sel)) return n; }
	return null;
};

var html = new El('html', { 'data-pg-lang': 'en' });
var body = html.appendChild(new El('body'));
var listeners = {};
var doc = {
	readyState: 'complete',
	documentElement: html,
	body: body,
	addEventListener: function (type, fn) { (listeners[type] = listeners[type] || []).push(fn); },
	querySelectorAll: function (sel) { return html.querySelectorAll(sel); },
	createElement: function (tag) { return new El(tag); },
	getElementById: function (id) {
		var found = null;
		(function walk(n) { n.children.forEach(function (c) { if (!found && c.getAttribute('id') === id) found = c; walk(c); }); })(html);
		return found;
	}
};
function fire(type, event) { (listeners[type] || []).forEach(function (fn) { fn(event); }); }

// The page's window, for the back/forward cache's pageshow.
var winListeners = {};
var win = { addEventListener: function (type, fn) { (winListeners[type] = winListeners[type] || []).push(fn); } };
function fireWin(type, event) { (winListeners[type] || []).forEach(function (fn) { fn(event); }); }

// A heart as the server draws it: the designer's classes, hidden by the mark.
function heart(id, cls) {
	var attrs = { type: 'button', 'class': cls || 'btn', 'data-pg-wishlist': id, 'aria-pressed': 'false', 'data-pg-wishlist-hidden': '1' };
	return new El('button', attrs, [new El('i', { 'class': 'bi bi-heart', 'aria-hidden': 'true' })]);
}
function look(el) {
	return {
		id: el.getAttribute('data-pg-wishlist'),
		pressed: el.getAttribute('aria-pressed'),
		cls: el.getAttribute('class'),
		icon: el.children[0].getAttribute('class'),
		hidden: el.getAttribute('data-pg-wishlist-hidden')
	};
}

var calls = [];
var replies = [];
function fakeFetch(url, opts) {
	calls.push({ url: url, method: (opts && opts.method) || 'GET', body: (opts && opts.body) || '',
		xhr: !!(opts && opts.headers && opts.headers['X-Requested-With'] === 'XMLHttpRequest'),
		cache: (opts && opts.cache) || '', credentials: (opts && opts.credentials) || '' });
	var r = replies.shift();
	if (!r) return Promise.reject(new Error('offline'));
	var response = { ok: r.status >= 200 && r.status < 300, status: r.status,
		json: function () { return Promise.resolve(r.data); } };
	// A held reply answers only when the step calls release().
	if (r.hold) return new Promise(function (resolve) { held.push(function () { resolve(response); }); });
	return Promise.resolve(response);
}
var held = [];
function release() { var fn = held.shift(); if (fn) fn(); }
function tick() { return new Promise(function (r) { setTimeout(r, 0); }); }
JS;

	$file = @tempnam(sys_get_temp_dir(), 'pgwishlist');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, str_replace('SCRIPT', json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_wishlist.js'), $prelude) . "\n" . $steps . "\n");

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

// The state reply fills every heart; hearts of one product follow a save and
// a removal together; a refused change goes back and the button stays
// usable; a picked variant moves the product page's heart.
function test_wishlist_heart_script_under_node()
{
	$steps = <<<'JS'
var a = body.appendChild(heart('7'));
var b = body.appendChild(heart('7'));
var c = body.appendChild(heart('9'));
var d = body.appendChild(heart(''));
var form = body.appendChild(new El('form', { 'data-pg-civ-form': '1' }));
var e = form.appendChild(heart('7'));
var out = {};

var w = W.create({ document: doc, fetch: fakeFetch, config: { url: '/software/wishlist.php', labels: { failed: 'Failed' } } });
replies.push({ status: 200, data: { ids: [9], token: 'T0k', signed_in: false } });

w.start().then(function () {
	out.state_call = calls[0];
	out.after_state = { a: look(a), c: look(c), d: look(d) };
	var box = doc.getElementById('pg-wishlist-status');
	out.box_at_start = box ? { text: box.textContent, role: box.getAttribute('role') } : null;

	// A click on the icon inside the heart saves the product.
	replies.push({ status: 200, data: { product_id: 7, on: true } });
	var prevented = false;
	fire('click', { target: a.children[0], preventDefault: function () { prevented = true; } });
	out.prevented = prevented;
	out.optimistic = { a: look(a).pressed, b: look(b).pressed, e: look(e).pressed };
	return tick();
}).then(function () {
	out.add_call = calls[1];
	out.after_add = { a: look(a), b: look(b), e: look(e) };

	// The server refuses the removal: both go back to saved.
	replies.push({ status: 429, data: { error: 'rate', message: 'Too many' } });
	fire('click', { target: b, preventDefault: function () {} });
	return tick();
}).then(function () {
	out.remove_call = calls[2];
	out.after_refusal = { a: look(a).pressed, b: look(b).pressed, b_busy: b.getAttribute('aria-busy') };
	var status = doc.getElementById('pg-wishlist-status');
	out.status = status ? status.textContent : null;

	// Still usable: the next click is sent.
	replies.push({ status: 200, data: { product_id: 7, on: false } });
	fire('click', { target: b, preventDefault: function () {} });
	return tick();
}).then(function () {
	out.retry_call = calls[3];
	out.after_retry = { a: look(a).pressed, b: look(b).pressed };

	// The product page switches to variant 9, which is saved.
	fire('pg-civ-product-change', { target: form, detail: { productId: 9 } });
	out.after_variant = { e: look(e), a: look(a).id };

	// A click on something else does nothing.
	var before = calls.length;
	fire('click', { target: body, preventDefault: function () {} });
	out.other_click_calls = calls.length - before;

	console.log(JSON.stringify(out));
}).catch(function (err) { console.log('ERR ' + (err && err.stack)); });
JS;

	$r = wishlist_heart_test_node($steps);

	if ($r === null) {
		return;
	}

	pg_assert_true(!isset($r['error']), isset($r['error']) ? $r['error'] : '');

	if (isset($r['error'])) {
		return;
	}

	pg_assert_same('/software/wishlist.php?action=state&pg_lang=en', $r['state_call']['url'], 'state address');
	pg_assert_same('GET', $r['state_call']['method']);
	pg_assert_same('no-store', $r['state_call']['cache'], 'never from the browser cache');
	pg_assert_same('same-origin', $r['state_call']['credentials']);

	pg_assert_same(array('id' => '7', 'pressed' => 'false', 'cls' => 'btn', 'icon' => 'bi bi-heart', 'hidden' => null), $r['after_state']['a'], 'not saved, shown');
	pg_assert_same(array('id' => '9', 'pressed' => 'true', 'cls' => 'btn active', 'icon' => 'bi bi-heart-fill', 'hidden' => null), $r['after_state']['c'], 'saved, filled');
	pg_assert_same('1', $r['after_state']['d']['hidden'], 'a heart without a product stays hidden');
	pg_assert_same(array('text' => '', 'role' => 'status'), $r['box_at_start'], 'the status line is on the page, empty, before its first message');

	pg_assert_true($r['prevented'], 'the click is the heart\'s');
	pg_assert_same(array('a' => 'true', 'b' => 'true', 'e' => 'true'), $r['optimistic'], 'every heart of the product at once');
	pg_assert_same('/software/wishlist.php?pg_lang=en', $r['add_call']['url'], 'write address');
	pg_assert_same('POST', $r['add_call']['method']);
	pg_assert_true($r['add_call']['xhr'], 'answered in JSON');
	pg_assert_same('action=add&product_id=7&token=T0k', $r['add_call']['body'], 'the token from the state reply');
	pg_assert_same('bi bi-heart-fill', $r['after_add']['b']['icon'], 'the other card follows');
	pg_assert_same('btn active', $r['after_add']['e']['cls'], 'the product page follows');

	pg_assert_same('action=remove&product_id=7&token=T0k', $r['remove_call']['body'], 'removal');
	pg_assert_same(array('a' => 'true', 'b' => 'true', 'b_busy' => null), $r['after_refusal'], 'refused: back to saved, not busy');
	pg_assert_same('Too many', $r['status'], 'the server\'s words are announced');

	pg_assert_same('action=remove&product_id=7&token=T0k', $r['retry_call']['body'], 'the button stayed usable');
	pg_assert_same(array('a' => 'false', 'b' => 'false'), $r['after_retry']);

	pg_assert_same(array('id' => '9', 'pressed' => 'true', 'cls' => 'btn active', 'icon' => 'bi bi-heart-fill', 'hidden' => null), $r['after_variant']['e'], 'the variant\'s state');
	pg_assert_same('7', $r['after_variant']['a'], 'hearts outside the form keep their product');
	pg_assert_same(0, $r['other_click_calls']);
}

// Without an answer to the state request every heart stays hidden, one the
// designer shows from md up as well.
function test_wishlist_heart_script_state_failure()
{
	$steps = <<<'JS'
var a = body.appendChild(heart('7'));
var b = body.appendChild(heart('7', 'btn d-none d-md-inline-block'));
var w = W.create({ document: doc, fetch: fakeFetch, config: { url: '/software/wishlist.php', labels: {} } });
replies.push({ status: 503, data: { error: 'unavailable', message: 'x' } });
w.start().then(function () {
	var first = { a: look(a).hidden, b: look(b).hidden, b_cls: look(b).cls };
	fire('click', { target: a, preventDefault: function () {} });
	console.log(JSON.stringify({ hidden: first, calls: calls.length }));
});
JS;

	$r = wishlist_heart_test_node($steps);

	if ($r === null) {
		return;
	}

	pg_assert_true(!isset($r['error']), isset($r['error']) ? $r['error'] : '');

	if (isset($r['error'])) {
		return;
	}

	pg_assert_same(array('a' => '1', 'b' => '1', 'b_cls' => 'btn d-none d-md-inline-block'), $r['hidden'], 'both keep the mark that hides them');
	pg_assert_same(1, $r['calls'], 'a click on a hidden heart sends nothing');
}

// A heart moved to another variant while its product is being saved does
// not stay busy: the busy mark follows the product the heart stands for, so
// a heart moved back onto the saving product is busy again until it is done.
function test_wishlist_heart_script_busy_follows_variant()
{
	$steps = <<<'JS'
var a = body.appendChild(heart('5'));
var form = body.appendChild(new El('form', { 'data-pg-civ-form': '1' }));
var e = form.appendChild(heart('5'));
var out = {};

var w = W.create({ document: doc, fetch: fakeFetch, config: { url: '/software/wishlist.php', labels: {} } });
replies.push({ status: 200, data: { ids: [], token: 't' } });

w.start().then(function () {
	replies.push({ status: 200, data: { product_id: 5, on: true }, hold: true });
	fire('click', { target: e, preventDefault: function () {} });
	out.during = { a: a.getAttribute('aria-busy'), e: e.getAttribute('aria-busy') };

	fire('pg-civ-product-change', { target: form, detail: { productId: 6 } });
	out.moved = { a: a.getAttribute('aria-busy'), e: e.getAttribute('aria-busy'), e_pressed: look(e).pressed };

	fire('pg-civ-product-change', { target: form, detail: { productId: 5 } });
	out.back = e.getAttribute('aria-busy');

	fire('pg-civ-product-change', { target: form, detail: { productId: 6 } });
	release();
	return tick();
}).then(function () {
	out.after = { a: a.getAttribute('aria-busy'), e: e.getAttribute('aria-busy'), e_id: look(e).id, a_pressed: look(a).pressed, e_pressed: look(e).pressed };
	console.log(JSON.stringify(out));
}).catch(function (err) { console.log('ERR ' + (err && err.stack)); });
JS;

	$r = wishlist_heart_test_node($steps);

	if ($r === null) {
		return;
	}

	pg_assert_true(!isset($r['error']), isset($r['error']) ? $r['error'] : '');

	if (isset($r['error'])) {
		return;
	}

	pg_assert_same(array('a' => 'true', 'e' => 'true'), $r['during'], 'both hearts of the product are busy');
	pg_assert_same(array('a' => 'true', 'e' => null, 'e_pressed' => 'false'), $r['moved'], 'the moved heart is not busy, its new product not saved');
	pg_assert_same('true', $r['back'], 'back on the saving product: busy again');
	pg_assert_same(array('a' => null, 'e' => null, 'e_id' => '6', 'a_pressed' => 'true', 'e_pressed' => 'false'), $r['after'], 'nothing stays busy once the save is done');
}

// The script shows a heart by taking its mark away and never touches its
// classes: a heart the designer hid on small screens (d-none
// d-md-inline-block) keeps them, and a heart that loses its product is
// hidden by the mark again.
function test_wishlist_heart_script_leaves_display_classes()
{
	$steps = <<<'JS'
var own = body.appendChild(heart('7', 'btn d-none d-md-inline-block'));
var form = body.appendChild(new El('form', { 'data-pg-civ-form': '1' }));
var ours = form.appendChild(heart('7'));
var out = {};

var w = W.create({ document: doc, fetch: fakeFetch, config: { url: '/software/wishlist.php', labels: {} } });
replies.push({ status: 200, data: { ids: [7], token: 't' } });

w.start().then(function () {
	out.own = { cls: look(own).cls, flag: look(own).hidden, pressed: look(own).pressed };
	out.ours = { cls: look(ours).cls, flag: look(ours).hidden };

	fire('pg-civ-product-change', { target: form, detail: { productId: 0 } });
	out.no_product = { cls: look(ours).cls, flag: look(ours).hidden };

	fire('pg-civ-product-change', { target: form, detail: { productId: 7 } });
	out.again = { cls: look(ours).cls, flag: look(ours).hidden };
	console.log(JSON.stringify(out));
}).catch(function (err) { console.log('ERR ' + (err && err.stack)); });
JS;

	$r = wishlist_heart_test_node($steps);

	if ($r === null) {
		return;
	}

	pg_assert_true(!isset($r['error']), isset($r['error']) ? $r['error'] : '');

	if (isset($r['error'])) {
		return;
	}

	pg_assert_same(array('cls' => 'btn d-none d-md-inline-block active', 'flag' => null, 'pressed' => 'true'), $r['own'], 'the designer\'s classes stay, the mark goes');
	pg_assert_same(array('cls' => 'btn active', 'flag' => null), $r['ours'], 'the mark goes');
	pg_assert_same(array('cls' => 'btn active', 'flag' => '1'), $r['no_product'], 'hidden again by the mark, no class added');
	pg_assert_same(array('cls' => 'btn active', 'flag' => null), $r['again'], 'shown again');
}

// A save that gets no answer at all (offline, a dropped connection) goes
// back, and the page's own words are read out, not the browser's English
// error text.
function test_wishlist_heart_script_network_failure()
{
	$steps = <<<'JS'
var a = body.appendChild(heart('7'));
var w = W.create({ document: doc, config: { url: '/software/wishlist.php', labels: { failed: 'Bir şeyler ters gitti.' } },
	fetch: function (url, opts) {
		if (opts && opts.method === 'POST') return Promise.reject(new TypeError('Failed to fetch'));
		return fakeFetch(url, opts);
	} });
replies.push({ status: 200, data: { ids: [], token: 't' } });

w.start().then(function () {
	return w.toggle(a);
}).then(function (ok) {
	var status = doc.getElementById('pg-wishlist-status');
	console.log(JSON.stringify({ ok: ok, pressed: look(a).pressed, busy: a.getAttribute('aria-busy'), status: status ? status.textContent : null }));
}).catch(function (err) { console.log('ERR ' + (err && err.stack)); });
JS;

	$r = wishlist_heart_test_node($steps);

	if ($r === null) {
		return;
	}

	pg_assert_true(!isset($r['error']), isset($r['error']) ? $r['error'] : '');

	if (isset($r['error'])) {
		return;
	}

	pg_assert_same(array('ok' => false, 'pressed' => 'false', 'busy' => null, 'status' => 'Bir şeyler ters gitti.'), $r, 'back, usable, the page\'s words');
}

// A page the browser brings back from its back/forward cache runs no script
// again: its pageshow (persisted) reads the list and the token anew, so a
// product saved on another page meanwhile shows filled and the next save
// carries the session's current token. An ordinary pageshow reads nothing.
function test_wishlist_heart_script_back_forward_cache()
{
	$steps = <<<'JS'
var a = body.appendChild(heart('42'));
var b = body.appendChild(heart('42'));
var out = {};

var w = W.create({ document: doc, window: win, fetch: fakeFetch, config: { url: '/software/wishlist.php', labels: {} } });
replies.push({ status: 200, data: { ids: [], token: 'old' } });

w.start().then(function () {
	out.first = look(a).pressed;
	replies.push({ status: 200, data: { ids: [42], token: 'new' } });
	fireWin('pageshow', { persisted: true });
	return tick();
}).then(function () {
	out.restored = { a: look(a), b: look(b).pressed, calls: calls.length, url: calls[calls.length - 1].url };
	fireWin('pageshow', { persisted: false });
	out.plain_calls = calls.length;
	replies.push({ status: 200, data: { product_id: 42, on: false } });
	return w.toggle(a);
}).then(function () {
	out.write = calls[calls.length - 1].body;
	console.log(JSON.stringify(out));
}).catch(function (err) { console.log('ERR ' + (err && err.stack)); });
JS;

	$r = wishlist_heart_test_node($steps);

	if ($r === null) {
		return;
	}

	pg_assert_true(!isset($r['error']), isset($r['error']) ? $r['error'] : '');

	if (isset($r['error'])) {
		return;
	}

	pg_assert_same('false', $r['first'], 'not saved at first');
	pg_assert_same(array('id' => '42', 'pressed' => 'true', 'cls' => 'btn active', 'icon' => 'bi bi-heart-fill', 'hidden' => null), $r['restored']['a'], 'saved meanwhile: filled');
	pg_assert_same('true', $r['restored']['b'], 'every heart of it');
	pg_assert_same(2, $r['restored']['calls'], 'the state read again');
	pg_assert_same('/software/wishlist.php?action=state&pg_lang=en', $r['restored']['url']);
	pg_assert_same(2, $r['plain_calls'], 'an ordinary pageshow reads nothing');
	pg_assert_same('action=remove&product_id=42&token=new', $r['write'], 'the new token');
}
