<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pgBindFieldHelp() in backend.src.js: a panel field's visible
 * .form-text help gets an id and is added to the field's aria-describedby,
 * run under Node on a small fake document. Covers the field in the same
 * parent, a switch with its help after the .form-field, ids already in
 * aria-describedby, combobox toggles and chips boxes, a second run, and the
 * cases left alone (radio groups, help inside a label, intros, hand-bound
 * help, popover titles, date and time hooks).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_field_help_test_backend()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
}

// pgDescribedBy() and pgBindFieldHelp(): from the describedby helper to the
// invalid-mark mirror that follows them.
function pg_field_help_test_source()
{
	$source = pg_field_help_test_backend();
	$start = strpos($source, '// Adds or removes one id in aria-describedby');
	$end = strpos($source, '// Mirrors .is-invalid into aria-invalid', (int) $start);

	if ($start === false || $end === false || strpos($source, 'function pgBindFieldHelp', $start) > $end) {
		return '';
	}

	return substr($source, $start, $end - $start);
}

// Runs the scenarios with Node and returns their JSON; null without Node,
// array('error' => output) when the script fails.
function pg_field_help_test_run()
{
	static $result = false;

	if ($result !== false) {
		return $result;
	}

	if (!function_exists('exec')) {
		return $result = null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return $result = null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgfh');

	if ($file === false) {
		return $result = null;
	}

	file_put_contents($file, 'var PG_SOURCE = ' . json_encode(pg_field_help_test_source()) . ";\n" . pg_field_help_test_harness());
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$text = implode("\n", $out);
	$json = json_decode($text, true);

	if ($code !== 0 || !is_array($json)) {
		return $result = array('error' => $text);
	}

	return $result = $json;
}

function pg_field_help_test_harness()
{
	return <<<'JS'
// Simple selectors only: tag, .class and [attr], compounded, comma lists.
function parseSel(one) {
    var m = /^([a-z]*)((?:\.[\w-]+)*)((?:\[[\w-]+\])*)$/i.exec(one.trim());
    return {
        tag: m[1].toUpperCase(),
        classes: m[2] ? m[2].slice(1).split('.') : [],
        attrs: m[3] ? m[3].slice(1, -1).split('][') : []
    };
}
function El(tag, cls, attrs) {
    var self = this;
    this.tagName = tag.toUpperCase();
    this.className = cls || '';
    this.attributes = {};
    var a = attrs || {};
    Object.keys(a).forEach(function (k) { self.attributes[k] = String(a[k]); });
    this.children = [];
    this.parentElement = null;
    this.dataset = {};
    this.classList = {
        contains: function (c) { return self.className.split(/\s+/).indexOf(c) !== -1; }
    };
}
Object.defineProperty(El.prototype, 'id', {
    get: function () { return this.attributes.id || ''; },
    set: function (v) { this.attributes.id = String(v); }
});
Object.defineProperty(El.prototype, 'type', { get: function () {
    return this.tagName === 'INPUT' ? (this.attributes.type || 'text').toLowerCase() : this.tagName.toLowerCase().replace('select', 'select-one');
} });
Object.defineProperty(El.prototype, 'previousElementSibling', { get: function () {
    if (!this.parentElement) { return null; }
    var s = this.parentElement.children;
    return s[s.indexOf(this) - 1] || null;
} });
El.prototype.getAttribute = function (k) { return Object.prototype.hasOwnProperty.call(this.attributes, k) ? this.attributes[k] : null; };
El.prototype.hasAttribute = function (k) { return Object.prototype.hasOwnProperty.call(this.attributes, k); };
El.prototype.setAttribute = function (k, v) { this.attributes[k] = String(v); };
El.prototype.removeAttribute = function (k) { delete this.attributes[k]; };
El.prototype.matches = function (sel) {
    var self = this;
    return sel.split(',').some(function (one) {
        var p = parseSel(one);
        return (!p.tag || p.tag === self.tagName)
            && p.classes.every(function (c) { return self.classList.contains(c); })
            && p.attrs.every(function (k) { return self.hasAttribute(k); });
    });
};
El.prototype.querySelectorAll = function (sel) {
    var found = [];
    (function walk(el) {
        el.children.forEach(function (c) { if (c.matches(sel)) { found.push(c); } walk(c); });
    })(this);
    return found;
};
El.prototype.querySelector = function (sel) { return this.querySelectorAll(sel)[0] || null; };
El.prototype.closest = function (sel) {
    for (var el = this; el; el = el.parentElement) { if (el.matches(sel)) { return el; } }
    return null;
};
function tree(el, kids) { kids.forEach(function (k) { k.parentElement = el; el.children.push(k); }); return el; }
function help(attrs) { return new El('div', 'form-text', attrs); }

var body = new El('body');
var document = {
    querySelectorAll: function (sel) { return body.querySelectorAll(sel); },
    getElementById: function (id) {
        return body.querySelectorAll('[id]').filter(function (e) { return e.id === id; })[0] || null;
    }
};
var window = {};
eval(PG_SOURCE);
window.pgBindFieldHelp = pgBindFieldHelp;

var out = {};
function described(el) { return el.getAttribute('aria-describedby'); }

// The field and its help in one wrapper.
var ups = new El('input', 'form-control', { id: 'ups_account' }), upsHelp = help();
tree(body, [tree(new El('div', 'pg-f-md'), [new El('label', 'form-label', { 'for': 'ups_account' }), ups, upsHelp])]);

// A switch whose help follows its .form-field, among other fields.
var dbl = new El('input', 'form-check-input', { type: 'checkbox', id: 'double_opt_in' }), dblHelp = help();
tree(body, [tree(new El('div', 'card-body'), [
    new El('input', 'form-control', { id: 'other' }),
    tree(new El('div', 'form-field'), [tree(new El('div', 'switch'), [dbl]), new El('label', '', { 'for': 'double_opt_in' })]),
    dblHelp
])]);

// Hidden and submit inputs do not count; an error link stays beside the help.
var code = new El('input', 'form-control is-invalid', { id: 'code', 'aria-describedby': 'code-feedback' }), codeHelp = help({ id: 'code_note' });
tree(body, [tree(new El('div', 'mb-3'), [
    new El('input', '', { type: 'hidden', name: 'token' }), code,
    new El('div', 'invalid-feedback', { id: 'code-feedback' }), codeHelp,
    new El('input', 'btn', { type: 'submit' })
])]);

// A field without id: its help still gets one.
var anon = new El('textarea', 'form-control'), anonHelp = help();
tree(body, [tree(new El('div', 'mb-3'), [anon, anonHelp])]);

// A drawn combobox and chips box carry the link too.
var sel = new El('select', 'form-select', { id: 'redirect_code', 'data-pg-combobox': '' }), selHelp = help();
sel.pgComboboxToggle = new El('button', 'form-select');
tree(body, [tree(new El('div', 'mb-3'), [sel, sel.pgComboboxToggle, selHelp])]);
var tags = new El('input', 'form-control', { id: 'keywords', 'data-pg-chips': '' }), ghost = new El('input', 'form-ghost'), tagsHelp = help();
tags.pgChipsBox = tree(new El('div', 'chip-input'), [ghost]);
tree(body, [tree(new El('div', 'mb-3'), [tags, tags.pgChipsBox, tagsHelp])]);

// Left alone: one help after a radio pair, help inside a label, an intro,
// hand-bound help, a popover title, a date hook.
var r1 = new El('input', '', { type: 'radio', id: 'r1' }), r2 = new El('input', '', { type: 'radio', id: 'r2' }), radioHelp = help();
tree(body, [tree(new El('div', 'mb-3'), [tree(new El('div', 'form-field'), [r1]), tree(new El('div', 'form-field'), [r2]), radioHelp])]);
var inLabelBox = new El('input', '', { type: 'radio', id: 'prep' }), inLabel = help();
tree(body, [tree(new El('div', 'mb-3'), [tree(new El('label'), [inLabelBox, inLabel])])]);
var intro = help();
tree(body, [tree(new El('div', 'card'), [new El('h2'), intro])]);
var bound = new El('input', 'form-control', { id: 'url', 'aria-describedby': 'url_hint' }), boundHelp = help({ id: 'url_hint' });
tree(body, [tree(new El('div', 'mb-3'), [bound, boundHelp])]);
var shared = help({ id: 'tr_auto_hint' }), sharedUser = new El('input', '', { type: 'checkbox', 'aria-describedby': 'tr_auto_hint' });
tree(body, [tree(new El('div', 'mb-3'), [new El('input', 'form-control', { id: 'lonely' }), shared]), sharedUser]);
var titled = new El('input', 'form-control', { id: 'titled', title: 'Hint' }), titledHelp = help();
tree(body, [tree(new El('div', 'mb-3'), [titled, titledHelp])]);
var fixed = new El('input', 'form-control', { id: 'fixed', 'data-bs-original-title': 'Hint' }), fixedHelp = help();
tree(body, [tree(new El('div', 'mb-3'), [fixed, fixedHelp])]);
var date = new El('input', 'form-control', { id: 'start', 'data-pg-date': '' }), dateHelp = help();
tree(body, [tree(new El('div', 'mb-3'), [date, dateHelp])]);

window.pgBindFieldHelp(body);

out.plain = [upsHelp.id, described(ups)];
out.switch_after = [dblHelp.id, described(dbl)];
out.kept_ids = [codeHelp.id, described(code)];
out.anon = [anonHelp.id !== '', described(anon) === anonHelp.id];
out.combobox = [described(sel), described(sel.pgComboboxToggle)];
out.chips = [described(tags), described(ghost)];
out.skipped = [
    radioHelp.id, described(r1), described(r2),
    inLabel.id, described(inLabelBox),
    intro.id,
    described(bound),
    described(document.getElementById('lonely')),
    titledHelp.id, described(titled),
    fixedHelp.id, described(fixed),
    dateHelp.id, described(date)
];

// A second run changes nothing.
window.pgBindFieldHelp(body);
out.second = [described(ups), described(code), described(dbl), described(sel.pgComboboxToggle)];

// A pane injected later: its help is bound on its own run, with a unique id.
var twin = new El('input', 'form-control', { id: 'ups_account' }), twinHelp = help();
tree(body, [tree(new El('div', 'pg-f-md'), [twin, twinHelp])]);
window.pgBindFieldHelp(body);
out.unique = [twinHelp.id !== upsHelp.id && twinHelp.id !== '', described(twin) === twinHelp.id];

console.log(JSON.stringify(out));
JS;
}

function test_field_help_describedby_binds()
{
	pg_assert_true(pg_field_help_test_source() !== '', 'pgBindFieldHelp() found beside pgDescribedBy() in backend.src.js');

	$r = pg_field_help_test_run();

	if ($r === null) {
		return;
	}

	if (isset($r['error'])) {
		pg_assert_same('', $r['error'], 'binder under Node');
		return;
	}

	pg_assert_same(array('ups_account-help', 'ups_account-help'), $r['plain'], 'help in the field\'s wrapper');
	pg_assert_same(array('double_opt_in-help', 'double_opt_in-help'), $r['switch_after'], 'switch: help after its .form-field');
	pg_assert_same(array('code_note', 'code-feedback code_note'), $r['kept_ids'], 'own id kept, error link kept, hidden and submit not counted');
	pg_assert_same(array(true, true), $r['anon'], 'a field without id');
	pg_assert_same(array('redirect_code-help', 'redirect_code-help'), $r['combobox'], 'combobox toggle linked');
	pg_assert_same(array('keywords-help', 'keywords-help'), $r['chips'], 'chips typing box linked');
	pg_assert_same(array('', null, null, '', null, '', 'url_hint', null, '', null, '', null, '', null), $r['skipped'], 'radio pair, help in a label, intro, hand-bound help, popover titles and date hook left alone');
	pg_assert_same(array('ups_account-help', 'code-feedback code_note', 'double_opt_in-help', 'redirect_code-help'), $r['second'], 'a second run changes nothing');
	pg_assert_same(array(true, true), $r['unique'], 'a later pane gets a unique id');
}

function test_field_help_describedby_wired()
{
	$js = pg_field_help_test_backend();

	pg_assert_contains('window.pgBindFieldHelp = pgBindFieldHelp;', $js);
	pg_assert_contains("pgInjectAriaAndRequiredMarkers();\n            pgBindFieldHelp();", $js, 'bound on DOMContentLoaded after the injector');
	pg_assert_contains("window.pgBindFieldHelp(\$scope[0]);\n    }\n\n    // Chips, combobox", $js, 'bound in injected panes before pgFields.init');

	// The output_errors() inline call runs mid-parse: the binder stays out of it.
	$start = strpos($js, 'function pgInjectAriaAndRequiredMarkers(');
	$end = strpos($js, "\n    }\n", (int) $start);
	pg_assert_true($start !== false && strpos(substr($js, $start, $end - $start), 'pgBindFieldHelp') === false, 'not inside pgInjectAriaAndRequiredMarkers');
}
