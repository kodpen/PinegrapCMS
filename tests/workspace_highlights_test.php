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
