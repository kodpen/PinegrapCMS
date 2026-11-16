<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure part of a reader's highlights on a message
 * (includes/workspace/highlights.php): the plain text a highlight is kept
 * against (ws_highlight_plain()), finding its quote by occurrence
 * (ws_highlight_find()) and what an edit of the message does to it
 * (ws_highlight_remap_one()). The browser's twin reads the same fixtures.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only: nothing in these files runs a query when loaded.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/render.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/highlights.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// The drawn bodies both twins read: the drawn HTML and its plain text.
function workspace_highlights_test_fixtures()
{
	return array(
		'folded white space' => array(
			'html'  => "<div class=\"ws-text\">  one \t two<br>\n\n three   </div>",
			'plain' => 'one two three',
		),
		'entities and emphasis' => array(
			'html'  => '<div class="ws-text">milk &amp; <strong>eggs</strong>&nbsp;&lt;3 <em>to</em>day</div>',
			'plain' => 'milk & eggs <3 today',
		),
		'blocks without a line break between them' => array(
			'html'  => '<div class="ws-text">see:</div><ul class="ws-list"><li>milk</li><li>bread</li></ul><blockquote class="ws-quote">said</blockquote>',
			'plain' => 'see: milk bread said',
		),
	);
}

function test_workspace_highlights_plain_folds_white_space()
{
	foreach (workspace_highlights_test_fixtures() as $label => $fixture) {
		pg_assert_same($fixture['plain'], ws_highlight_plain($fixture['html']), $label);
	}

	pg_assert_same('', ws_highlight_plain(''), 'an empty body');
	pg_assert_same('', ws_highlight_plain("<div> \n </div>"), 'white space only');
	pg_assert_same('a b', ws_highlight_plain("a\xC2\xA0\xC2\xA0b"), 'a run of no-break spaces is one space');
}

function test_workspace_highlights_plain_of_a_list_and_a_mention()
{
	$refs = array(
		'user:12' => array('type' => 'user', 'id' => 12, 'label' => '@Ayşe Yılmaz', 'url' => '', 'known' => true, 'icon' => '', 'meta' => '', 'status' => ''),
	);
	$html = ws_render_body("Hello <@user:12>, see:\n- milk & eggs\n- bread\n\nthen **go**", $refs);
	$plain = ws_highlight_plain($html);

	pg_assert_same('Hello @Ayşe Yılmaz, see: milk & eggs bread then go', $plain);
	pg_assert_same(6, ws_highlight_find($plain, '@Ayşe Yılmaz, see', 0), 'a quote over a mention, counted in characters');
	pg_assert_same(32, ws_highlight_find($plain, "eggs\nbread", 0), 'a quote across two items of a list');
}

function test_workspace_highlights_find_by_occurrence()
{
	$plain = 'the cat saw the dog and the bird';

	pg_assert_same(0, ws_highlight_find($plain, 'the', 0));
	pg_assert_same(12, ws_highlight_find($plain, 'the', 1));
	pg_assert_same(24, ws_highlight_find($plain, 'the', 2));
	pg_assert_same(null, ws_highlight_find($plain, 'the', 3), 'no fourth one');
	pg_assert_same(null, ws_highlight_find($plain, 'fox', 0), 'not in the text');
	pg_assert_same(null, ws_highlight_find($plain, '', 0), 'an empty quote');
	pg_assert_same(null, ws_highlight_find($plain, '   ', 0), 'a quote of white space');
	pg_assert_same(null, ws_highlight_find($plain, 'the', -1), 'a negative occurrence');
	pg_assert_same(1, ws_highlight_find('aaa', 'aa', 1), 'every start is an occurrence, overlapping ones too');
	pg_assert_same(2, ws_highlight_find('çaçaç', 'ç', 1), 'offsets count characters, not bytes');
}

function test_workspace_highlights_find_across_a_line_break()
{
	$plain = ws_highlight_plain("<div class=\"ws-text\">first line<br>\nsecond line</div>");

	pg_assert_same('first line second line', $plain);
	pg_assert_same(6, ws_highlight_find($plain, "line\nsecond", 0), 'a selection over the break');
	pg_assert_same(6, ws_highlight_find($plain, "line \r\n  second", 0), 'its white space folded the same way');
	pg_assert_same(6, ws_highlight_find($plain, ' line second ', 0), 'white space at its ends left out');
}

function test_workspace_highlights_remap_keeps_moves_or_deletes()
{
	$row = array('id' => 7, 'quote' => 'the', 'occurrence' => 2, 'scope' => 'all');
	$old = 'the cat saw the dog and the bird';

	pg_assert_same($row, ws_highlight_remap_one($row, $old, $old), 'an unchanged text keeps the row');
	pg_assert_same($row, ws_highlight_remap_one($row, $old, 'the cat saw the dog, the bird and the fox'), 'the occurrence is still there: kept');

	$moved = ws_highlight_remap_one($row, $old, 'the cat saw the dog');
	pg_assert_same(1, $moved['occurrence'], 'the occurrence is gone: moved to the last one');
	pg_assert_same(7, $moved['id'], 'the same row');

	pg_assert_same(null, ws_highlight_remap_one($row, $old, 'a cat saw a dog'), 'the quote is gone: deleted');
	pg_assert_same(null, ws_highlight_remap_one(array('quote' => '', 'occurrence' => 0), $old, $old), 'an empty quote is deleted');
}

/* ---------------------------------------------------------------------------
   Who may make, change and remove a highlight; the limits; what is told
   --------------------------------------------------------------------------- */

function workspace_highlights_test_make($over = array())
{
	return ws_highlight_make_check($over + array(
		'message'    => array('id' => 50, 'channel_id' => 2, 'kind' => 'message', 'deleted_at' => 0),
		'can_read'   => true,
		'can_react'  => true,
		'plain'      => 'the cat saw the dog',
		'quote'      => 'the',
		'occurrence' => 1,
		'scope'      => 'all',
		'same'       => null,
		'counts'     => array('mine_here' => 0, 'here' => 0, 'mine' => 0),
	));
}

// H4: "me" by whoever reads the message, "all" by whoever may react to it.
function test_workspace_highlights_who_makes_each_scope()
{
	$made = workspace_highlights_test_make();
	pg_assert_same(array(true, 'all', 'the', 1), array($made['ok'], $made['scope'], $made['quote'], $made['occurrence']), 'a member who may react');

	pg_assert_true(workspace_highlights_test_make(array('can_react' => false, 'scope' => 'me'))['ok'], '"me" where he may not react (an archived channel)');

	$refused = workspace_highlights_test_make(array('can_react' => false));
	pg_assert_same(array(false, 'scope'), array($refused['ok'], $refused['field']), '"all" where he may not react');

	pg_assert_false(workspace_highlights_test_make(array('can_read' => false, 'scope' => 'me'))['ok'], 'not a reader of the message');
	pg_assert_false(workspace_highlights_test_make(array('message' => array('id' => 50, 'kind' => 'message', 'deleted_at' => 9), 'scope' => 'me'))['ok'], 'a deleted message');
	pg_assert_false(workspace_highlights_test_make(array('message' => array('id' => 50, 'kind' => 'system', 'deleted_at' => 0), 'scope' => 'me'))['ok'], 'a system line');

	pg_assert_same('me', workspace_highlights_test_make(array('scope' => 'team'))['scope'], 'an unknown scope is "me"');
}

// H1, H2: the quote is checked against the message's own text.
function test_workspace_highlights_quote_checked()
{
	pg_assert_same('quote', workspace_highlights_test_make(array('quote' => 'the fox'))['field'], 'not in the text');
	pg_assert_same('quote', workspace_highlights_test_make(array('occurrence' => 2))['field'], 'no such occurrence');
	pg_assert_same('quote', workspace_highlights_test_make(array('quote' => " \n "))['field'], 'white space only');

	$long = str_repeat('ğ', 1001);
	pg_assert_same('quote', workspace_highlights_test_make(array('plain' => $long, 'quote' => $long, 'occurrence' => 0))['field'], 'over 1,000 characters');

	$edge = str_repeat('ğ', 1000);
	pg_assert_true(workspace_highlights_test_make(array('plain' => $edge, 'quote' => $edge, 'occurrence' => 0))['ok'], '1,000 characters');
	pg_assert_same('cat saw', workspace_highlights_test_make(array('quote' => "cat\n saw", 'occurrence' => 0))['quote'], 'kept folded');
}

// H9: 10 per person on a message, 30 on a message, 2,000 per person.
function test_workspace_highlights_limits()
{
	pg_assert_true(workspace_highlights_test_make(array('counts' => array('mine_here' => 9, 'here' => 29, 'mine' => 1999)))['ok'], 'one under each');

	$mine_here = workspace_highlights_test_make(array('counts' => array('mine_here' => 10, 'here' => 10, 'mine' => 10)));
	$here = workspace_highlights_test_make(array('counts' => array('mine_here' => 1, 'here' => 30, 'mine' => 1)));
	$mine = workspace_highlights_test_make(array('counts' => array('mine_here' => 1, 'here' => 1, 'mine' => 2000)));

	pg_assert_same('limit_message_mine', $mine_here['limit'], 'his 10 on the message');
	pg_assert_same('limit_message', $here['limit'], '30 on the message');
	pg_assert_same('limit_person', $mine['limit'], 'his 2,000');
	pg_assert_contains('My highlights', $mine['error'], 'the last one says where to remove some');
	pg_assert_false($mine_here['error'] === $here['error'], 'each refusal names its limit');
}

// H5: the same place again changes the row he has; no limit counts it again.
function test_workspace_highlights_same_place_twice()
{
	$rows = array(
		array('id' => 4, 'quote' => 'the', 'occurrence' => 0, 'scope' => 'me'),
		array('id' => 5, 'quote' => 'the', 'occurrence' => 1, 'scope' => 'me'),
	);

	pg_assert_same(5, ws_highlight_same($rows, "the", 1)['id']);
	pg_assert_same(4, ws_highlight_same($rows, " the ", 0)['id'], 'the quote folded');
	pg_assert_same(null, ws_highlight_same($rows, 'the', 2));
	pg_assert_same(null, ws_highlight_same($rows, 'cat', 0));

	$again = workspace_highlights_test_make(array('same' => $rows[1], 'counts' => array('mine_here' => 10, 'here' => 30, 'mine' => 2000)));
	pg_assert_same(array(true, 5), array($again['ok'], $again['same_id']), 'a full message still lets him change the scope of his own');
}

// H6: plain text up to 255 characters.
function test_workspace_highlights_note()
{
	pg_assert_same(array(true, 'see the dog'), array_values(array_intersect_key(ws_highlight_note_clean(" see \n the\tdog "), array('ok' => 1, 'note' => 1))));
	pg_assert_true(ws_highlight_note_clean(str_repeat('ş', 255))['ok'], '255 characters');
	pg_assert_false(ws_highlight_note_clean(str_repeat('ş', 256))['ok'], '256 characters');
	pg_assert_same('', ws_highlight_note_clean('')['note'], 'no note');
	pg_assert_same('a b', ws_highlight_note_clean("a\x00\x07b")['note'], 'control characters dropped');

	pg_assert_true(ws_highlight_can_note(10, array('user_id' => 10, 'scope' => 'me'), false), 'the maker, on his own');
	pg_assert_true(ws_highlight_can_note(10, array('user_id' => 10, 'scope' => 'all'), true), 'the maker, for everyone');
	pg_assert_false(ws_highlight_can_note(10, array('user_id' => 10, 'scope' => 'all'), false), 'not where he may no longer react');
	pg_assert_false(ws_highlight_can_note(11, array('user_id' => 10, 'scope' => 'all'), true), 'nobody else');
}

// H7: the maker; an "all" one also the message's writer and who may delete it.
function test_workspace_highlights_who_removes()
{
	$mine = array('user_id' => 10, 'scope' => 'me');
	$everyone = array('user_id' => 10, 'scope' => 'all');
	$rights = array('can_react' => true, 'writer' => false, 'may_delete' => false);

	pg_assert_true(ws_highlight_can_remove(10, $mine, array('can_react' => false) + $rights), 'the maker of his own, anywhere he reads');
	pg_assert_false(ws_highlight_can_remove(11, $mine, array('writer' => true, 'may_delete' => true) + $rights), 'nobody else removes "me"');

	pg_assert_true(ws_highlight_can_remove(10, $everyone, $rights), 'the maker');
	pg_assert_true(ws_highlight_can_remove(11, $everyone, array('writer' => true) + $rights), 'the writer of the message');
	pg_assert_true(ws_highlight_can_remove(11, $everyone, array('may_delete' => true) + $rights), 'who may delete the message');
	pg_assert_false(ws_highlight_can_remove(11, $everyone, $rights), 'another reader');
	pg_assert_false(ws_highlight_can_remove(10, $everyone, array('can_react' => false) + $rights), 'read-only where he may not react');
}

// H3: a "me" row reaches its maker only.
function test_workspace_highlights_me_never_in_another_map()
{
	$rows = array(
		array('id' => 1, 'message_id' => 50, 'user_id' => 10, 'scope' => 'me', 'quote' => 'the', 'occurrence' => 0, 'note' => 'mine'),
		array('id' => 2, 'message_id' => 50, 'user_id' => 10, 'scope' => 'all', 'quote' => 'cat', 'occurrence' => 0, 'note' => ''),
		array('id' => 3, 'message_id' => 51, 'user_id' => 12, 'scope' => 'me', 'quote' => 'dog', 'occurrence' => 0, 'note' => ''),
		array('id' => 4, 'message_id' => 51, 'user_id' => 12, 'scope' => 'odd', 'quote' => 'dog', 'occurrence' => 0, 'note' => ''),
	);
	$people = array(10 => array('name' => 'Ayşe', 'tone' => 3), 12 => array('name' => 'Can', 'tone' => 5));
	$rights = array(
		50 => array('can_react' => true, 'writer' => false, 'may_delete' => false),
		51 => array('can_react' => true, 'writer' => true, 'may_delete' => false),
	);

	$other = ws_highlights_map_rows($rows, 11, $people, $rights);
	pg_assert_same(array(50), array_keys($other), 'another reader sees only the "all" one');
	pg_assert_same(array(2), array_column($other[50], 'id'));
	pg_assert_same(array(false, false, 'Ayşe', 3), array($other[50][0]['mine'], $other[50][0]['can_remove'], $other[50][0]['name'], $other[50][0]['tone']));

	$maker = ws_highlights_map_rows($rows, 10, $people, $rights);
	pg_assert_same(array(1, 2), array_column($maker[50], 'id'), 'the maker sees both');
	pg_assert_same(array(true, true, 'me'), array($maker[50][0]['mine'], $maker[50][0]['can_remove'], $maker[50][0]['scope']));

	$can = ws_highlights_map_rows($rows, 12, $people, $rights);
	pg_assert_same(array(3, 4), array_column($can[51], 'id'), 'his own, an unknown scope read as "me"');
	pg_assert_same('me', $can[51][1]['scope']);
}

// H8: nothing is sent; an "all" one stamps the message, a "me" one does not.
function test_workspace_highlights_tell_nobody()
{
	pg_assert_false(ws_highlight_stamps(null, 'me'), 'a new "me"');
	pg_assert_true(ws_highlight_stamps(null, 'all'), 'a new "all"');
	pg_assert_false(ws_highlight_stamps('me', 'me'), 'a note on "me"');
	pg_assert_true(ws_highlight_stamps('me', 'all'), '"me" made "all"');
	pg_assert_true(ws_highlight_stamps('all', 'me'), '"all" made "me": the others lose it');
	pg_assert_true(ws_highlight_stamps('all', null), 'an "all" removed');
	pg_assert_false(ws_highlight_stamps('me', null), 'a "me" removed');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/highlights.php');

	foreach (array('ws_inbox', 'ws_notify', 'ws_push', 'ws_bell', 'ws_message_system', 'ws_send(', 'log_activity', 'ws_event(') as $call) {
		pg_assert_false(strpos($source, $call) !== false, 'no ' . $call);
	}

	pg_assert_contains('ws_message_touch(', $source, 'the stamp');
}

// H9: where rows go with what they belong to.
function test_workspace_highlights_cleanup_calls()
{
	$files = array(
		'includes/workspace/messages.php'  => array('ws_highlights_message_deleted(', 'ws_highlights_remap('),
		'includes/workspace/threads.php'   => array('DELETE FROM ws_highlights WHERE channel_id'),
		'includes/workspace/channels.php'  => array('ws_highlights_reach_lost('),
		'includes/workspace/groups.php'    => array('ws_highlights_reach_lost('),
		'includes/fn/user_write.php'       => array('DELETE FROM ws_highlights WHERE user_id'),
	);

	foreach ($files as $file => $needles) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		foreach ($needles as $needle) {
			pg_assert_contains($needle, $source, $file);
		}
	}
}

/* ---------------------------------------------------------------------------
   The browser's twin (assets/js/workspace_highlights.js) under Node
   --------------------------------------------------------------------------- */

// Runs the script under Node with a bare window and a small parser that
// builds a DOM-like tree from HTML; prints, for each case, plainFromHtml(),
// textMap()'s plain text and find() for each quote. Null without Node.
function workspace_highlights_test_twin($cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$parser = <<<'JS'
var SKIPPED = ['ws-msg-edited', 'ws-hl-bar', 'ws-hl-card'];
function tree(html) {
  var root = { nodeType: 1, nodeName: 'DIV', childNodes: [] };
  var stack = [root];
  var re = /<(\/?)([a-z0-9]+)([^>]*)>|([^<]+)/gi, m;
  var decode = function (s) { return s.replace(/&nbsp;/g, ' ').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&amp;/g, '&'); };
  while ((m = re.exec(html)) !== null) {
    var top = stack[stack.length - 1];
    if (m[4] !== undefined) { top.childNodes.push({ nodeType: 3, nodeValue: decode(m[4]) }); continue; }
    if (m[1] === '/') { stack.pop(); continue; }
    var cls = (/class="([^"]*)"/.exec(m[3]) || [])[1] || '';
    var node = { nodeType: 1, nodeName: m[2].toUpperCase(), childNodes: [], className: cls,
      matches: function () { var own = this.className.split(' '); return SKIPPED.some(function (c) { return own.indexOf(c) >= 0; }); } };
    top.childNodes.push(node);
    if (!/^(br|hr|input|img)$/i.test(m[2]) && !/\/$/.test(m[3])) { stack.push(node); }
  }
  return root;
}
JS;

	$script = "'use strict';\nvar window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_highlights.js') . "\n" . $parser
		. "\nvar H = window.PGWsHighlights;\nvar cases = " . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (c) {\n"
		. "  var plain = H.plainFromHtml(c.html);\n"
		. "  return { plain: plain, walked: H.textMap(tree(c.html)).plain,\n"
		. "    found: (c.quotes || []).map(function (q) { return H.find(plain, q[0], q[1]); }),\n"
		. "    at: (c.at || []).map(function (q) { return H.occurrenceAt(plain, q[0], q[1]); }) };\n"
		. "})));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwshl');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);

	return is_array($answer) ? $answer : array();
}

// The browser reads the same plain text and finds the same places as the
// server, on HL1's fixtures, a list, a mention, a block of code and a
// checklist ticked by someone.
function test_workspace_highlights_browser_twin()
{
	$refs = array(
		'user:12' => array('type' => 'user', 'id' => 12, 'label' => '@Ayşe Yılmaz', 'url' => '', 'known' => true, 'icon' => '', 'meta' => '', 'status' => ''),
	);
	$htmls = array();

	foreach (workspace_highlights_test_fixtures() as $fixture) {
		$htmls[] = $fixture['html'];
	}

	$htmls[] = ws_render_body("Hello <@user:12>, see:\n- milk & eggs\n- bread\n\nthen **go** \"now\" it's\n> quoted", $refs);
	$htmls[] = ws_render_body("Look:\n```php\n\$a = 1 < 2;\n```\nafter the code", array());
	$htmls[] = ws_render_body("first line\nsecond line", array());

	$cases = array();

	foreach ($htmls as $html) {
		$plain = ws_highlight_plain($html);
		$words = explode(' ', $plain);
		$quotes = array(array($words[0], 0), array(implode(' ', array_slice($words, 1, 2)), 0), array('e', 1), array('e', 2), array('zzz', 0));
		$cases[] = array('html' => $html, 'plain' => $plain, 'quotes' => $quotes);
	}

	// The marker the screen adds after the text is never part of it.
	$cases[] = array('html' => '<div class="ws-text">done here<span class="ws-msg-edited"><i></i> edited</span></div>', 'plain' => 'done here', 'quotes' => array(), 'walk_only' => true);

	$answer = workspace_highlights_test_twin($cases);

	if ($answer === null) {
		pg_assert_true(true, 'Node is not installed here; CI runs this test with it.');

		return;
	}

	foreach ($cases as $i => $case) {
		if (empty($case['walk_only'])) {
			pg_assert_same($case['plain'], $answer[$i]['plain'], 'plainFromHtml() #' . $i);
		}

		pg_assert_same($case['plain'], $answer[$i]['walked'], 'textMap() #' . $i);

		foreach ($case['quotes'] as $q => $quote) {
			pg_assert_same(ws_highlight_find($case['plain'], $quote[0], $quote[1]), $answer[$i]['found'][$q], 'find() #' . $i . ' "' . $quote[0] . '" ' . $quote[1]);
		}
	}
}

// The occurrence a selection is: how many times its text begins earlier,
// overlapping places too, as ws_highlight_find() counts them.
function test_workspace_highlights_browser_occurrence()
{
	$answer = workspace_highlights_test_twin(array(
		array('html' => 'the cat saw the dog and the bird', 'at' => array(array('the', 0), array('the', 12), array('the', 24), array('aa', 1))),
		array('html' => 'aaa', 'at' => array(array('aa', 0), array('aa', 1))),
	));

	if ($answer === null) {
		pg_assert_true(true, 'Node is not installed here; CI runs this test with it.');

		return;
	}

	pg_assert_same(array(0, 1, 2, 0), $answer[0]['at']);
	pg_assert_same(array(0, 1), $answer[1]['at']);
	pg_assert_same(1, ws_highlight_find('aaa', 'aa', 1), 'the server finds the second at 1, as the browser counts it');
}

// The script is in the Workspace bundle, before workspace.js, which calls
// it after drawing a body; its texts come from the #ws-config block.
function test_workspace_highlights_browser_wiring()
{
	$bundle = pg_js_bundles()['assets/js/workspace.min.js'];
	pg_assert_true(in_array('assets/js/workspace_highlights.js', $bundle, true), 'in the bundle');
	pg_assert_true(array_search('assets/js/workspace_highlights.js', $bundle, true) < array_search('assets/js/workspace.js', $bundle, true), 'before workspace.js');

	$workspace = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	pg_assert_contains('window.PGWsHighlights.attach(message, body, featureHelpers()', $workspace);

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_highlights.js');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	preg_match_all("/h\\.t\\('(hl_[a-z_]+)'/", $script, $keys);

	foreach (array_unique($keys[1]) as $key) {
		pg_assert_contains("'" . $key . "'", $screen, 'the text ' . $key);
	}

	// H10, H11: a toolbar that keeps the selection; "For everyone" only
	// where the reader may react.
	pg_assert_contains("setAttribute('role', 'toolbar')", $script);
	pg_assert_contains('event.preventDefault()', $script);
	pg_assert_contains('if (message.can_react) {', $script);
	pg_assert_contains("'bi-highlighter'", $script);
}

// Runs My highlights' pure part under Node: the address a row leads to, the
// highlight an address asks to flash, what Remove n sends and counts.
function workspace_highlights_test_mine($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgwshlm');
	file_put_contents($file, "'use strict';\nvar window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_highlights.js')
		. "\nvar H = window.PGWsHighlights;\nconsole.log(JSON.stringify(" . $script . "));\n");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);

	return json_decode($printed, true);
}

// (H19) A row opens its conversation at the message, as a note's source
// link does, and names the highlight to flash.
function test_workspace_highlights_mine_row_leads_to_the_message()
{
	$answer = workspace_highlights_test_mine("[H.mineHref('/pinegrap/workspace.php', { id: 77, channel_id: 5, message_id: 901 }),"
		. " H.flashParam('?channel=5&message=901&hl=77'), H.flashParam('?hl=77&channel=5'), H.flashParam('?hl=7x'), H.flashParam(''), H.flashParam('?ahl=3')]");

	if ($answer === null) {
		return;
	}

	pg_assert_same('/pinegrap/workspace.php?channel=5&message=901&hl=77', $answer[0]);
	pg_assert_same(77, $answer[1]);
	pg_assert_same(77, $answer[2]);
	pg_assert_same(0, $answer[3], 'not digits');
	pg_assert_same(0, $answer[4]);
	pg_assert_same(0, $answer[5], 'another name ending in hl');
}

// (H20, H21) Remove n: the ids ticked, or with Select all the whole filter
// (rows not loaded yet too), counted from the filter's chip.
function test_workspace_highlights_mine_bulk_removal_request()
{
	$counts = "{ every: 140, all: 90, me: 50 }";
	$answer = workspace_highlights_test_mine("["
		. "H.mineRemoveRequest({ every: false, filter: 'me', ids: [3, '9', 0, 'x'] }),"
		. "H.mineRemoveRequest({ every: true, filter: 'all', ids: [3] }),"
		. "H.mineRemoveRequest({ every: true, filter: '', ids: [] }),"
		. "H.mineRemoveCount({ every: false, filter: 'all', ids: [3, 9] }, " . $counts . "),"
		. "H.mineRemoveCount({ every: true, filter: 'all', ids: [] }, " . $counts . "),"
		. "H.mineRemoveCount({ every: true, filter: 'me', ids: [] }, " . $counts . "),"
		. "H.mineRemoveCount({ every: true, filter: '', ids: [] }, " . $counts . ")]");

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('ids' => '3,9'), $answer[0]);
	pg_assert_same(array('every' => 'all'), $answer[1], 'Select all sends the filter, not the loaded ids');
	pg_assert_same(array('every' => ''), $answer[2], 'every one of his');
	pg_assert_same(2, $answer[3]);
	pg_assert_same(90, $answer[4], 'Everyone\'s count, beyond the 50 loaded');
	pg_assert_same(50, $answer[5]);
	pg_assert_same(140, $answer[6]);
}

// (H17, H22) The button sits in My Notes' bar beside Import, gated by the
// step, and opens the list in the note's pane; every text the list uses is
// in the Workspace's config, the empty state included.
function test_workspace_highlights_mine_button_and_texts()
{
	$workspace = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_highlights.js');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');
	$import = strpos($workspace, "bar.appendChild(importNotes);");
	$button = strpos($workspace, "button('btn btn-sm btn-text theme-secondary', t('hl_mine'), 'bi-highlighter')");

	pg_assert_true(($import !== false) && ($button !== false) && ($button > $import) && ($button - $import < 600), 'beside Import');
	pg_assert_contains("if (CFG.highlights && window.PGWsHighlights)", $workspace);
	pg_assert_contains('window.PGWsHighlights.myList(pane, featureHelpers()', $workspace);
	pg_assert_contains("'highlights'   => function_exists('ws_highlights_ready') && ws_highlights_ready(),", $screen);

	preg_match_all("/h\\.t\\('(hl_mine[a-z_]*)'/", $script, $used);
	pg_assert_true(count(array_unique($used[1])) >= 10, 'the list\'s texts');

	foreach (array_unique($used[1]) as $key) {
		pg_assert_contains("'" . $key . "'", $screen, $key);
	}

	pg_assert_contains("lang('No highlights yet. Select text in a message and press Highlight.')", $screen);
}

// (H18) The rows come from ws_highlights_mine(): the person's own only, 50
// at a time, newest first, on messages he can still read.
function test_workspace_highlights_mine_reads_his_own_page()
{
	$source = pg_test_function_source('ws_highlights_mine');

	pg_assert_same(50, WS_HIGHLIGHTS_PAGE);
	pg_assert_contains("h.user_id = '\" . \$user_id . \"'", $source);
	pg_assert_contains('ORDER BY h.id DESC', $source);
	pg_assert_contains('ws_can_read_channel($viewer, $channel)', $source);
}
