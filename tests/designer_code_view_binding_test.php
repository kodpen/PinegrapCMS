<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for typing a {{token}} in the visual designer's code view: the code
 * view applies what is typed after a short pause, and a token still being
 * typed (or picked from the completion list) must not count as the value,
 * or the binding ends and "{{" becomes the element's design-time text. The
 * code view's binding helpers are cut out of assets/js/style_designer.src.js
 * and run under Node; on a machine without Node the test is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The code view's binding helpers and the one binding writer, with stubs
// for what they call; null without Node.
function designer_code_view_binding_test_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, 'var _SD_CODE_BIND_RE =');
	$to = strpos($source, 'function _sdCodeNodeAtPos(', $from);
	$set_from = strpos($source, 'function _sdNodeSetBinding(');
	$set_to = strpos($source, '// Wire the binding selects', $set_from);
	pg_assert_true(($from !== false) && ($to !== false) && ($set_from !== false) && ($set_to !== false), 'the designer has the helpers');

	$script = 'var _sdCode = {}; var _sharedDirty = {}; var SW_DATE_FIELD_TOKENS = { __date: 1 };'
		. 'function _isInsideSystemWidget(n) { return n.inWidget ? "w1" : ""; }'
		. 'function _getBindableProps(n) { return [{ prop: "text" }, { prop: "alt" }]; }'
		. 'function _sdBindTextSlot(text, token) { return token; }'
		. 'function _sdDecodeEntities(s) { return s; }'
		. 'function sdToast() {} function esc(s) { return s; } function _sdT(s) { return s; }'
		. substr($source, $from, $to - $from)
		. substr($source, $set_from, $set_to - $set_from)
		. 'function state(n) { return JSON.parse(JSON.stringify(n.props)); }'
		. $steps;

	$file = tempnam(sys_get_temp_dir(), 'pgcodebind');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A {{ with no }} after it, or an empty {{}}, is a token still being typed.
function test_designer_code_view_half_token()
{
	$answer = designer_code_view_binding_test_run(
		'console.log(JSON.stringify(["{{", "{{__na", "a }} {{", "{{}}", "{{ }}", "{{__name}}", "{{a}} b", "düz", ""].map(_sdCodeHalfToken)));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(true, true, true, true, true, false, false, false, false), $answer, 'open, half a name, open after a close, empty braces; a whole token, plain text, nothing');
}

// Retyping a bound text's token through the completion list, with a pause
// after "{{": the half-done state is applied as typed, then the finished
// token gets the design-time text and the date format back.
function test_designer_code_view_text_token_retyped()
{
	$answer = designer_code_view_binding_test_run(
		'var out = {};'
		. 'var n = { inWidget: true, props: { text: "Ürün adı", _bindings: { text: "__short_description" } } };'
		. 'n.props.text = _sdCodeTextFromTyped(n, "{{"); out.half = state(n);'
		. 'n.props.text = _sdCodeTextFromTyped(n, "{{__na"); out.half2 = state(n);'
		. 'n.props.text = _sdCodeTextFromTyped(n, "{{__name}}"); out.bound = state(n);'
		. 'n.props.text = _sdCodeTextFromTyped(n, "{{"); n.props.text = _sdCodeTextFromTyped(n, "Düz metin"); out.literal = state(n);'
		. 'n.props.text = _sdCodeTextFromTyped(n, "{{__name}}"); out.afterLiteral = state(n);'
		. 'var d = { inWidget: true, props: { text: "19.10.2026", _bindings: { text: "__date" }, _bindFormats: { text: "d.m.Y" } } };'
		. 'd.props.text = _sdCodeTextFromTyped(d, "{{"); d.props.text = _sdCodeTextFromTyped(d, "{{__date}}"); out.date = state(d);'
		. 'var p = { inWidget: true, props: { text: "Merhaba" } };'
		. 'p.props.text = _sdCodeTextFromTyped(p, "{{__na"); p.props.text = _sdCodeTextFromTyped(p, "{{__name}}"); out.plain = state(p);'
		. 'var e = { inWidget: true, props: {} };'
		. 'e.props.text = _sdCodeTextFromTyped(e, "{{"); e.props.text = _sdCodeTextFromTyped(e, "{{__name}}"); out.empty = state(e);'
		. 'console.log(JSON.stringify(out));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('text' => '{{'), $answer['half'], 'the half-done state is applied as typed, as the code view shows it');
	pg_assert_same(array('text' => '{{__na'), $answer['half2'], 'and again while the name is typed');
	pg_assert_same(array('text' => 'Ürün adı', '_bindings' => array('text' => '__name')), $answer['bound'], 'the finished token binds and the design-time text comes back');
	pg_assert_same(array('text' => 'Düz metin'), $answer['literal'], 'a literal over the token ends the binding');
	pg_assert_same(array('text' => 'Düz metin', '_bindings' => array('text' => '__name')), $answer['afterLiteral'], 'and lets the held text go');
	pg_assert_same(array('text' => '19.10.2026', '_bindings' => array('text' => '__date'), '_bindFormats' => array('text' => 'd.m.Y')), $answer['date'], 'the same date token gets its format back');
	pg_assert_same(array('text' => 'Merhaba', '_bindings' => array('text' => '__name')), $answer['plain'], 'an unbound text keeps its words once bound');
	pg_assert_same(array('text' => '', '_bindings' => array('text' => '__name')), $answer['empty'], 'an element without a text stays without one');
}

// The same for a bound attribute (alt): the caller stores the half-done
// value, the finished token puts the design-time value back.
function test_designer_code_view_attribute_token_retyped()
{
	$answer = designer_code_view_binding_test_run(
		'var out = {};'
		. 'var n = { inWidget: true, props: { alt: "Foto", _bindings: { alt: "__image_alt" } } };'
		. 'out.halfStored = !_sdCodeApplyBoundValue(n, "alt", "{{"); if (out.halfStored) n.props.alt = "{{"; out.half = state(n);'
		. 'out.boundStored = !_sdCodeApplyBoundValue(n, "alt", "{{__name}}"); out.bound = state(n);'
		. 'out.literalStored = !_sdCodeApplyBoundValue(n, "alt", "Yeni"); out.literal = state(n);'
		. 'console.log(JSON.stringify(out));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_true($answer['halfStored'], 'half a token is stored by the caller, as typed');
	pg_assert_same(array('alt' => '{{'), $answer['half'], 'the binding ends meanwhile');
	pg_assert_false($answer['boundStored'], 'the finished token binds');
	pg_assert_same(array('alt' => 'Foto', '_bindings' => array('alt' => '__name')), $answer['bound'], 'with the design-time value back');
	pg_assert_true($answer['literalStored'], 'a literal is stored by the caller');
	pg_assert_same(array('alt' => 'Foto'), $answer['literal'], 'and ends the binding');
}

// A link to another page keeps its page and values (props._bindParams)
// while its href token is retyped: through a full intermediate token, a
// half token and a literal. Only the Bind Data panel drops them.
function test_designer_code_view_page_link_spec_kept()
{
	$answer = designer_code_view_binding_test_run(
		'_getBindableProps = function (n) { return [{ prop: "text" }, { prop: "href" }]; };'
		. 'var out = {};'
		. 'var spec = { href: { page_id: 12, params: [{ name: "board", token: "board" }] } };'
		. 'var n = { inWidget: true, props: { href: "#", _bindings: { href: "__page_link" }, _bindParams: JSON.parse(JSON.stringify(spec)) } };'
		. '_sdCodeApplyBoundValue(n, "href", "{{__page_lin}}"); out.mid = state(n);'
		. '_sdCodeApplyBoundValue(n, "href", "{{__page_link}}"); out.back = state(n);'
		. 'if (!_sdCodeApplyBoundValue(n, "href", "{{")) n.props.href = "{{"; out.half = state(n);'
		. '_sdCodeApplyBoundValue(n, "href", "{{__page_link}}"); out.again = state(n);'
		. 'if (!_sdCodeApplyBoundValue(n, "href", "/x")) n.props.href = "/x"; out.literal = state(n);'
		. 'console.log(JSON.stringify(out));'
	);

	if ($answer === null) {
		return;
	}

	$spec = array('href' => array('page_id' => 12, 'params' => array(array('name' => 'board', 'token' => 'board'))));
	pg_assert_same('__page_lin', $answer['mid']['_bindings']['href'], 'a full intermediate token binds');
	pg_assert_same($spec, $answer['mid']['_bindParams'], 'and leaves the spec');
	pg_assert_same(array('href' => '#', '_bindings' => array('href' => '__page_link'), '_bindParams' => $spec), $answer['back'], 'the token typed again finds its page and values');
	pg_assert_false(isset($answer['half']['_bindings']), 'half a token ends the binding');
	pg_assert_same($spec, $answer['half']['_bindParams'], 'and leaves the spec');
	pg_assert_same('#', $answer['again']['href'], 'the finished token gets the design-time href back');
	pg_assert_same(array('href' => '__page_link'), $answer['again']['_bindings'], 'binds the link again');
	pg_assert_same($spec, $answer['again']['_bindParams'], 'which finds its page and values');
	pg_assert_same(array('href' => '/x', '_bindParams' => $spec), $answer['literal'], 'a literal ends the binding; the spec stays, inert');
}
