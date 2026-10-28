<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the hosted card fields (Stripe's Payment Element) on the classic
 * order preview and express order pages: where the four card sections print
 * the driver's box and with which total, and how assets/js/payment_hosted.js
 * finds the card section of a page: the marked one of the built-in layouts,
 * the card row of a custom layout saved before the mark, a table's
 * container, and none where the card inputs sit among other fields. The
 * script runs under Node against a small stand-in for the page and for
 * Stripe.js; without Node those tests pass without running.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/* ---------------------------------------------------------------------------
   The four card sections
   --------------------------------------------------------------------------- */

// The source of a page from $start to $end, its whitespace made one space.
function payment_hosted_classic_test_slice($file, $start, $end)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$a = strpos($source, $start);
	$b = ($a !== false) ? strpos($source, $end, $a) : false;
	pg_assert_true(($a !== false) && ($b !== false), $file . ': ' . $start . ' is found');

	return (string) preg_replace('/\s+/', ' ', substr($source, (int) $a, (int) $b - (int) $a));
}

// The built-in layouts print the box in the card section, before the marked
// wrapper of the card table; the surcharge message stays outside the wrapper
// so it is still shown. The card is charged the total with the surcharge.
function test_payment_hosted_classic_builtin_sections()
{
	foreach (array('get_order_preview.php', 'get_express_order.php') as $file) {
		$flat = payment_hosted_classic_test_slice($file, "\$output_surcharge_message = '';", '// if the paypal express checkout payment method should be shown');

		pg_assert_contains('$card_charge_total = $grand_total;', $flat, $file);
		pg_assert_contains('$grand_total_with_surcharge = $grand_total + $surcharge; $card_charge_total = $grand_total_with_surcharge;', $flat, $file);
		pg_assert_contains('$output_hosted_card = _eo_hosted_card_html((int) round($card_charge_total * 100));', $flat, $file);
		pg_assert_contains("\$output_hosted_card_open = (\$output_hosted_card !== '') ? '<div data-pg-eo-cc-fields>' : '';", $flat, $file);
		pg_assert_contains("\$output_hosted_card_close = (\$output_hosted_card !== '') ? '</div>' : '';", $flat, $file);

		$order = array(
			'<div id="credit_debit_card_fields" class="credit_debit_card"',
			"' . \$output_hosted_card . \$output_hosted_card_open . ' <table>",
			"</table> ' . \$output_hosted_card_close . '",
			"' . \$output_surcharge_message . ' </div>",
		);
		$last = -1;

		foreach ($order as $piece) {
			$at = strpos($flat, $piece);
			pg_assert_true(($at !== false) && ($at > $last), $file . ': ' . $piece . ' in order');
			$last = ($at === false) ? $last : $at;
		}
	}
}

// The custom layouts (and the designer's Order Preview, which prints the
// same system output) get the box with the system output, after the card
// fields are prepared.
function test_payment_hosted_classic_custom_layout_sections()
{
	foreach (array('get_order_preview.php', 'get_express_order.php') as $file) {
		$flat = payment_hosted_classic_test_slice($file, '// Use jQuery.payment library to enhance payment card fields', '// If PayPal Express Checkout payment method should be shown, then prepare.');

		pg_assert_contains('$system .= _eo_hosted_card_html((int) round((($total_with_surcharge > 0) ? $total_with_surcharge : $total) * 100));', $flat, $file);
	}
}

/* ---------------------------------------------------------------------------
   payment_hosted.js on the page
   --------------------------------------------------------------------------- */

// Runs payment_hosted.min.js once per page built by $pages (a script giving
// `pages`, an object of name => function returning the form) and prints,
// per page, what became of it. null without Node.
function payment_hosted_classic_test_node($pages)
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

	$dom = <<<'JS'
global.window = global;

function El(tag, attrs, children) {
    this.tagName = tag.toUpperCase();
    this.attrs = attrs || {};
    this.children = [];
    this.parentNode = null;
    this.style = {};
    this.disabled = false;
    this.checked = this.attrs.checked !== undefined;
    this.value = this.attrs.value || '';
    (children || []).forEach(function (child) { this.appendChild(child); }, this);
}
El.prototype.appendChild = function (child) { child.parentNode = this; this.children.push(child); return child; };
El.prototype.insertBefore = function (child, before) {
    if (child.parentNode) { child.parentNode.removeChild(child); }
    var i = this.children.indexOf(before);
    child.parentNode = this;
    this.children.splice(i < 0 ? this.children.length : i, 0, child);
    return child;
};
El.prototype.removeChild = function (child) { this.children.splice(this.children.indexOf(child), 1); child.parentNode = null; return child; };
Object.defineProperty(El.prototype, 'firstChild', { get: function () { return this.children[0] || null; } });
El.prototype.getAttribute = function (name) { return this.attrs[name] === undefined ? null : this.attrs[name]; };
El.prototype.setAttribute = function (name, value) { this.attrs[name] = String(value); };
El.prototype.removeAttribute = function (name) { delete this.attrs[name]; };
El.prototype.addEventListener = function () {};
El.prototype.contains = function (node) { for (; node; node = node.parentNode) { if (node === this) { return true; } } return false; };
El.prototype.matches = function (selector) {
    var el = this;
    return selector.split(',').some(function (part) {
        var m = part.trim().match(/^([a-z]*)((?:\.[\w-]+|\[[\w-]+(?:="[^"]*")?\]|:checked)*)$/);
        if (!m) { throw new Error('selector ' + part); }
        if (m[1] && m[1].toUpperCase() !== el.tagName) { return false; }
        var ok = true;
        m[2].replace(/\.([\w-]+)|\[([\w-]+)(?:="([^"]*)")?\]|(:checked)/g, function (all, cls, attr, value, checked) {
            if (cls) { ok = ok && (' ' + (el.attrs['class'] || '') + ' ').indexOf(' ' + cls + ' ') >= 0; }
            if (attr) { ok = ok && el.attrs[attr] !== undefined && (value === undefined || el.attrs[attr] === value); }
            if (checked) { ok = ok && el.checked; }
        });
        return ok;
    });
};
El.prototype.querySelectorAll = function (selector) {
    var found = [];
    (function walk(node) { node.children.forEach(function (child) { if (child.matches(selector)) { found.push(child); } walk(child); }); })(this);
    return found;
};
El.prototype.querySelector = function (selector) { return this.querySelectorAll(selector)[0] || null; };
El.prototype.closest = function (selector) { for (var node = this; node; node = node.parentNode) { if (node.matches && node.matches(selector)) { return node; } } return null; };

function h(tag, attrs, children) { return new El(tag, attrs, children); }
function input(name, attrs) { return h('input', Object.assign({ name: name, type: 'tel', required: '' }, attrs || {})); }

// The box _eo_hosted_card_html() prints, its hidden token input and nothing else.
function box() {
    return h('div', { 'class': 'pg-eo-hosted-card mb-3', id: 'box', 'data-pg-payment-hosted': JSON.stringify({
        driver: 'stripe',
        client: { publishable_key: 'pk_test_123', amount: 4714, currency: 'try', capture_method: 'automatic', payment_method_types: ['card'] },
        messages: { unavailable: 'unavailable' }
    }) }, [h('div', { 'class': 'pg-eo-hosted-card-element' }), h('div', { 'class': 'pg-eo-hosted-card-error' })]);
}
function token() { return h('input', { type: 'hidden', name: 'payment_token', value: '' }); }

// Stands in for Stripe.js: records where the Payment Element is mounted.
var mounted = null;
window.Stripe = function () {
    return { elements: function () { return { create: function () { return { mount: function (node) { mounted = node; } }; } }; } };
};

var script = require('fs').readFileSync(SCRIPT, 'utf8');
var result = {};

Object.keys(pages).forEach(function (name) {
    var form = pages[name]();
    var root = h('div', {}, [form]);
    mounted = null;
    delete window.pgPaymentHosted;
    global.document = {
        readyState: 'complete',
        addEventListener: function () {},
        querySelectorAll: function (selector) { return root.querySelectorAll(selector); }
    };
    (0, eval)(script);

    var b = root.querySelector('[id="box"]');
    var named = {};
    root.querySelectorAll('[id]').forEach(function (el) {
        named[el.attrs.id] = { hidden: el.style.display === 'none', disabled: el.disabled, required: el.attrs.required !== undefined };
    });
    result[name] = {
        section: b && b.parentNode ? (b.parentNode.attrs.id || b.parentNode.tagName) : null,
        first: !!(b && b.parentNode && b.parentNode.firstChild === b),
        mounted: !!(mounted && b && b.contains(mounted)),
        box: b ? b.style.display || '' : 'gone',
        named: named
    };
});

console.log(JSON.stringify(result));
JS;

	$file = @tempnam(sys_get_temp_dir(), 'pghosted');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var SCRIPT = ' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/payment_hosted.min.js') . ";\n" . $pages . "\n" . $dom);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$json = json_decode(implode("\n", $out), true);
	pg_assert_true(($code === 0) && is_array($json), 'node ran: ' . implode("\n", $out));

	return is_array($json) ? $json : null;
}

function test_payment_hosted_classic_finds_the_card_section()
{
	$result = payment_hosted_classic_test_node(<<<'JS'
var pages = {
    // get_order_preview.php / get_express_order.php built-in: the box before
    // the marked wrapper of the card table, the surcharge message after it.
    builtin: function () {
        return h('form', {}, [
            input('payment_method', { type: 'radio', value: 'Credit/Debit Card', checked: '', id: 'method' }),
            h('div', { id: 'credit_debit_card_fields', 'class': 'credit_debit_card' }, [
                box(),
                h('div', { 'data-pg-eo-cc-fields': '', id: 'wrapper' }, [
                    h('table', { id: 'table' }, [h('tr', {}, [
                        h('td', {}, [input('card_number', { id: 'card_number' })]),
                        h('td', {}, [input('expiration', { id: 'expiration' })]),
                        h('td', {}, [input('card_verification_number', { id: 'cvn' })])
                    ])])
                ]),
                h('div', { 'class': 'software_surcharge_message', id: 'surcharge' })
            ]),
            token()
        ]);
    },
    // A custom layout saved before the mark (includes/templates/order_preview.php):
    // the card row inside .credit_debit_card, the box with the system output.
    custom: function () {
        return h('form', {}, [
            input('billing_email_address', { type: 'email', id: 'email' }),
            h('div', { 'class': 'credit_debit_card', id: 'card' }, [
                h('div', { 'class': 'row', id: 'row' }, [
                    h('div', { 'class': 'col-sm-4', id: 'col1' }, [input('card_number')]),
                    h('div', { 'class': 'col-sm-3', id: 'col2' }, [h('select', { name: 'expiration_month', id: 'month' }), h('select', { name: 'expiration_year' })]),
                    h('div', { 'class': 'col-sm-3', id: 'col3' }, [input('card_verification_number')])
                ]),
                h('p', { 'class': 'text-muted', id: 'surcharge' })
            ]),
            box(),
            token()
        ]);
    },
    // Card inputs in a table of their own: the box goes to the table's
    // container, never into a row.
    table: function () {
        return h('form', {}, [
            input('billing_email_address', { type: 'email' }),
            h('div', { id: 'card' }, [h('table', { id: 'table' }, [h('tr', {}, [
                h('td', {}, [input('card_number')]),
                h('td', {}, [input('expiration')])
            ])])]),
            box(),
            token()
        ]);
    },
    // Card inputs among the billing fields: nothing is hidden, the box stays
    // where it was printed and follows the payment method.
    mixed: function () {
        return h('form', {}, [
            h('div', { id: 'fields' }, [
                input('billing_email_address', { type: 'email', id: 'email' }),
                input('card_number', { id: 'card_number' }),
                input('expiration')
            ]),
            input('payment_method', { type: 'radio', value: 'Offline Payment', checked: '' }),
            box(),
            token()
        ]);
    }
};
JS
	);

	if ($result === null) {
		return;
	}

	$builtin = $result['builtin'];
	pg_assert_same('wrapper', $builtin['section'], 'built-in: the box moves into the marked wrapper');
	pg_assert_true($builtin['first'] && $builtin['mounted'], 'built-in: first in it, the Payment Element mounted in the box');
	pg_assert_true($builtin['named']['table']['hidden'], 'built-in: the card table is hidden');
	pg_assert_false($builtin['named']['surcharge']['hidden'], 'built-in: the surcharge message stays shown');
	pg_assert_true($builtin['named']['card_number']['disabled'] && !$builtin['named']['card_number']['required'], 'built-in: the card number stays, switched off');
	pg_assert_true($builtin['named']['cvn']['disabled'], 'built-in: the security code is switched off');

	$custom = $result['custom'];
	pg_assert_same('row', $custom['section'], 'custom layout: the box moves into the card row');
	pg_assert_true($custom['first'] && $custom['mounted'], 'custom layout: first in the row, mounted');
	pg_assert_true($custom['named']['col1']['hidden'] && $custom['named']['col2']['hidden'] && $custom['named']['col3']['hidden'], 'custom layout: the card columns are hidden');
	pg_assert_false($custom['named']['surcharge']['hidden'], 'custom layout: the surcharge note stays shown');
	pg_assert_false($custom['named']['card']['hidden'], 'custom layout: the card container is left to the checkout script');
	pg_assert_true($custom['named']['month']['disabled'], 'custom layout: an expiration pick list is switched off too');
	pg_assert_false($custom['named']['email']['disabled'], 'custom layout: other fields stay on');

	$table = $result['table'];
	pg_assert_same('card', $table['section'], 'table: the box goes to the table\'s container');
	pg_assert_true($table['named']['table']['hidden'] && $table['mounted'], 'table: the table is hidden, the element mounted');

	$mixed = $result['mixed'];
	pg_assert_same('FORM', $mixed['section'], 'mixed: the box stays where it was printed');
	pg_assert_false($mixed['named']['fields']['hidden'], 'mixed: the billing fields are not hidden');
	pg_assert_true($mixed['named']['card_number']['disabled'], 'mixed: the card number is still switched off');
	pg_assert_same('none', $mixed['box'], 'mixed: the box follows the payment method (offline chosen)');
}
