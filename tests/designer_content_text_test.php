<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the texts a content operator (manager, user) may edit in the
 * visual designer outside a marked area: the editor's answer
 * (_sdIsTextNode(), asked through _sdCanEditNode(node, 'text')) has to match
 * what the save takes (pg_designer_merge_restricted_tree() copies props.text),
 * and the code view offers a text only where it can write it back
 * (_sdCodeLockOf(), _sdCodeTextKind()).
 * The designer's functions are cut out of assets/js/style_designer.src.js and run
 * under Node with the editor's state stubbed; on a machine without Node that
 * part is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');

// The source of one function declaration of the designer, braces matched
// (strings in these functions hold no braces); '' when it is not found.
function designer_content_text_test_function($source, $name)
{
	$from = strpos($source, 'function ' . $name . '(');

	if ($from === false) {
		return '';
	}

	$open = strpos($source, '{', $from);
	$depth = 0;

	for ($i = $open; $i < strlen($source); $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $from, $i - $from + 1);
			}
		}
	}

	return '';
}

// Runs the designer's text and code view lock functions with the stubs and
// prints what $script gives as JSON; null when there is no Node.
function designer_content_text_test_run($script)
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

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$functions = '';

	foreach (array('_sdCanEditNode', '_sdIsTextNode', '_sdCodeLockOf', '_sdCodeTextKind', '_sdIsSentence') as $name) {
		$found = designer_content_text_test_function($source, $name);
		pg_assert_true($found !== '', $name . ' is in the designer');
		$functions .= $found . "\n";
	}

	$stubs = 'var _collab = { mayEdit: true }; var level = "content"; var closed = false;'
		. 'function _sdIsContentLevel() { return level === "content"; }'
		. 'function _sdInClosedShared() { return closed; }'
		. 'function _sdInEditableArea() { return false; }'
		. 'function _sdNodeContentImage() { return null; }'
		. 'function hasLockedAncestor() { return false; }'
		. 'function _resolveSharedSid() { return 0; }';

	$file = @tempnam(sys_get_temp_dir(), 'pgsdtext');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $stubs . "\n" . $functions . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// The label of a ready-made button (component btn) and of a badge is text the
// content level may change, in place and in the read-only panel, as a heading,
// a link and a semantic <button> are; a legacy opaque component such as a
// card is not offered (its title is a prop the save does not take). A closed
// shared component or a system widget refuses everything, and a structural
// change stays refused.
function test_designer_content_text_offers_button_labels()
{
	$result = designer_content_text_test_run(
		'var btn = { type: "component", props: { componentType: "btn", text: "Start now", href: "#" } };'
		. 'var badge = { type: "component", props: { componentType: "badge", text: "New" } };'
		. 'var card = { type: "component", props: { componentType: "card", title: "Title", text: "Body" } };'
		. 'var heading = { type: "content", props: { contentType: "heading", text: "Hello" } };'
		. 'var semBtn = { type: "semantic", props: { tag: "button", text: "Sign in" } };'
		. 'var icon = { type: "component", props: { componentType: "btn" } };'
		. 'var all = [btn, badge, card, heading, semBtn, icon];'
		. 'var ask = function (what) { return all.map(function (n) { return _sdCanEditNode(n, what); }); };'
		. 'var text = ask("text"); var structural = ask();'
		. 'closed = true; var inClosed = ask("text"); closed = false;'
		. 'level = "full"; var full = ask("text");'
		. 'console.log(JSON.stringify({ text: text, structural: structural, inClosed: inClosed, full: full }));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(true, true, false, true, true, false), $result['text'], 'content level, outside a marked area: button and badge labels, heading, <button>; not a card, not a button without a label');
	pg_assert_same(array(false, false, false, false, false, false), $result['structural'], 'no structural change outside a marked area');
	pg_assert_same(array(false, false, false, false, false, false), $result['inClosed'], 'a closed shared component or a system widget refuses its text');
	pg_assert_same(array(true, true, true, true, true, true), $result['full'], 'a designer');
}

// What the editor offers is what the save takes: a content-level save writes
// the label of a ready-made button and of a badge, and keeps the button's
// address and look as stored.
function test_designer_content_text_save_takes_button_labels()
{
	$stored = array('type' => 'root', '_id' => 'r', 'props' => array(), 'children' => array(
		array('type' => 'component', '_id' => 'b', 'props' => array('componentType' => 'btn', 'text' => 'Start now', 'href' => '/start', 'variant' => 'primary'), 'children' => array()),
		array('type' => 'component', '_id' => 'g', 'props' => array('componentType' => 'badge', 'text' => 'New'), 'children' => array()),
	));

	$submitted = $stored;
	$submitted['children'][0]['props']['text'] = 'Book a call';
	$submitted['children'][0]['props']['href'] = '/elsewhere';
	$submitted['children'][0]['props']['variant'] = 'danger';
	$submitted['children'][1]['props']['text'] = 'Sale';

	$merged = pg_designer_merge_restricted_tree($stored, $submitted);

	pg_assert_same('Book a call', $merged['children'][0]['props']['text'], 'button label written');
	pg_assert_same('/start', $merged['children'][0]['props']['href'], 'button address kept');
	pg_assert_same('primary', $merged['children'][0]['props']['variant'], 'button look kept');
	pg_assert_same('Sale', $merged['children'][1]['props']['text'], 'badge text written');
}

// The code view writes a typed text back only for a node whose text kind it
// knows (_sdCodeTextKind()): a content element and a semantic one. A ready-made
// button's or a badge's label is printed by the component, so at content level
// it stays fully locked there instead of taking a keystroke the next redraw
// throws away; the double-click and the read-only panel's Text row edit it.
function test_designer_content_text_code_view_offers_only_writable_text()
{
	$result = designer_content_text_test_run(
		'var btn = { type: "component", props: { componentType: "btn", text: "Start now", href: "#" } };'
		. 'var badge = { type: "component", props: { componentType: "badge", text: "New" } };'
		. 'var card = { type: "component", props: { componentType: "card", title: "Title", text: "Body" } };'
		. 'var heading = { type: "content", props: { contentType: "heading", text: "Hello" } };'
		. 'var semBtn = { type: "semantic", props: { tag: "button", text: "Sign in" } };'
		. 'var all = [btn, badge, card, heading, semBtn];'
		. 'var ask = function () { return all.map(function (n) { var l = _sdCodeLockOf(n); return l.level + "/" + (l.attrs ? "attrs" : "-") + "/" + (l.text ? "text" : "-"); }); };'
		. 'var content = ask();'
		. 'closed = true; var inClosed = ask(); closed = false;'
		. 'level = "full"; var full = all.map(function (n) { var l = _sdCodeLockOf(n); return l.level + "/" + (l.attrs ? "attrs" : "-"); });'
		. 'console.log(JSON.stringify({ content: content, inClosed: inClosed, full: full }));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array('full/-/-', 'full/-/-', 'full/-/-', 'half/-/text', 'half/-/text'), $result['content'], 'content level, outside a marked area: a text island only for a heading and a <button>');
	pg_assert_same(array('full/-/-', 'full/-/-', 'full/-/-', 'full/-/-', 'full/-/-'), $result['inClosed'], 'a closed shared component or a system widget is fully locked');
	pg_assert_same(array('/attrs', '/attrs', '/attrs', '/attrs', '/attrs'), $result['full'], 'a designer: no lock, values may change');
}
