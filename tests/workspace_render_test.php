<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for lists and quotes in Workspace texts (includes/workspace/render.php):
 * which lines are items ("- ", "* ", "1. ") or quoted ("> "), how they group
 * (ws_text_blocks()), how a message, a note or a task field draws them
 * (ws_render_body(): <ul>, <ol>, <li>, <blockquote>, no attribute, the
 * words escaped first), how the plain text an assistant, a banner or an
 * e-mail reads keeps them (ws_plain_text(), ws_plain_excerpt()), and how a
 * guest outside the team reads them (ws_guest_render(), the approval mail).
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/guests.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approvals.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approval_guests.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// A message drawn with no tags resolved (none of these texts has one).
function workspace_render_test_html($body)
{
	return ws_render_body($body, array());
}

function workspace_render_test_plain($body)
{
	return ws_plain_text(array('id' => 1, 'role' => 0), $body, array());
}

function test_workspace_render_list_line_kinds()
{
	pg_assert_same(array('kind' => 'bullet', 'number' => 0, 'text' => 'milk'), ws_list_line('- milk'));
	pg_assert_same(array('kind' => 'bullet', 'number' => 0, 'text' => 'bread'), ws_list_line('* bread'));
	pg_assert_same(array('kind' => 'bullet', 'number' => 0, 'text' => 'indented'), ws_list_line('   - indented'), 'one level: an indented item is an item');
	pg_assert_same(array('kind' => 'number', 'number' => 1, 'text' => 'first'), ws_list_line('1. first'));
	pg_assert_same(array('kind' => 'number', 'number' => 12, 'text' => 'twelfth'), ws_list_line('12. twelfth'));
	pg_assert_same(array('kind' => 'quote', 'number' => 0, 'text' => 'said'), ws_list_line('> said'));
	pg_assert_same(array('kind' => 'quote', 'number' => 0, 'text' => ''), ws_list_line('>'), 'an empty line of a quote');

	foreach (array('-', '- ', '-milk', '**bold**', '- [ ] task', '- [x] done', '* * *', '- - -', '2026. yılında', '1.5 kg', '>quoted', '>> nested', 'text - dash', '') as $line) {
		pg_assert_same(null, ws_list_line($line), 'not an item: "' . $line . '"');
	}
}

function test_workspace_render_text_blocks_group_lines()
{
	$blocks = ws_text_blocks(array('Shopping:', '- milk', '* bread', 'Thanks'));

	pg_assert_same(array(
		array('kind' => 'text', 'lines' => array('Shopping:')),
		array('kind' => 'bullet', 'lines' => array('milk', 'bread')),
		array('kind' => 'text', 'lines' => array('Thanks')),
	), $blocks);

	pg_assert_same(array(
		array('kind' => 'number', 'lines' => array('one', 'two', 'three')),
	), ws_text_blocks(array('1. one', '2. two', '7. three')), 'any number goes on with a list that started at 1');

	pg_assert_same(array(
		array('kind' => 'text', 'lines' => array('He came', '3. in the race')),
	), ws_text_blocks(array('He came', '3. in the race')), 'a number that starts no list stays text, read as written');

	pg_assert_same(array(
		array('kind' => 'quote', 'lines' => array('a', '', 'b')),
	), ws_text_blocks(array('> a', '>', '> b')));
}

function test_workspace_render_text_blocks_loose_lists()
{
	pg_assert_same(array(
		array('kind' => 'number', 'lines' => array('one', 'two')),
	), ws_text_blocks(array('1. one', '', '2. two')), 'an empty line between two items keeps one list');

	pg_assert_same(array(
		array('kind' => 'bullet', 'lines' => array('a')),
		array('kind' => 'text', 'lines' => array('', 'text')),
	), ws_text_blocks(array('- a', '', 'text')), 'an empty line before text ends the list');

	pg_assert_same(array(
		array('kind' => 'bullet', 'lines' => array('a')),
		array('kind' => 'number', 'lines' => array('b')),
	), ws_text_blocks(array('- a', '1. b')), 'another kind is another list');

	pg_assert_same(array(
		array('kind' => 'quote', 'lines' => array('a')),
		array('kind' => 'text', 'lines' => array('')),
		array('kind' => 'quote', 'lines' => array('b')),
	), ws_text_blocks(array('> a', '', '> b')), 'an empty line ends a quote');
}

function test_workspace_render_body_draws_lists_and_quotes()
{
	pg_assert_same('<div class="ws-text">Shopping:</div><ul><li>milk</li><li>bread</li></ul><div class="ws-text">Thanks</div>',
		workspace_render_test_html("Shopping:\n- milk\n* bread\nThanks"));

	pg_assert_same('<ol><li>one</li><li>two</li></ol>', workspace_render_test_html("1. one\n2. two"));
	pg_assert_same("<blockquote>first<br>\nsecond</blockquote><div class=\"ws-text\">answer</div>", workspace_render_test_html("> first\n> second\nanswer"));

	// The words of an item are drawn as the rest of the text.
	pg_assert_same('<ul><li><strong>bold</strong> and <code>code</code></li></ul>', workspace_render_test_html('- **bold** and `code`'));
}

function test_workspace_render_body_keeps_other_blocks_and_plain_text()
{
	pg_assert_same("Just a line<br>\nand another", workspace_render_test_html("Just a line\nand another"), 'running text is drawn as before');
	pg_assert_same("He came<br>\n3. in the race", workspace_render_test_html("He came\n3. in the race"));

	$html = workspace_render_test_html("- a\n- [ ] task\n> q");

	pg_assert_contains('<ul><li>a</li></ul>', $html);
	pg_assert_contains('<ul class="ws-checklist">', $html, 'a checklist stays a checklist');
	pg_assert_contains('<blockquote>q</blockquote>', $html);

	$code = workspace_render_test_html("```\n- not a list\n> nor a quote\n```");

	pg_assert_true(strpos($code, '<ul>') === false && strpos($code, '<blockquote>') === false, 'code is kept as written');
	pg_assert_contains('- not a list', $code);
}

function test_workspace_render_body_lists_stay_safe()
{
	$html = workspace_render_test_html("- <script>alert(1)</script>\n- <b onclick=\"x()\">y</b>\n> <img src=x onerror=alert(1)>\n1. [z](javascript:alert(1))");

	pg_assert_true(strpos($html, '<script') === false, 'no script');
	pg_assert_true(strpos($html, '<b ') === false && strpos($html, '<img') === false, 'no element of the author\'s');
	pg_assert_contains('&lt;script&gt;', $html, 'escaped');
	pg_assert_true(strpos($html, 'href="javascript') === false, 'only web and mail addresses link');
	pg_assert_same(0, preg_match('/<(ul|ol|li|blockquote)\s/', $html), 'lists and quotes carry no attribute');
}

function test_workspace_render_plain_text_keeps_items_on_lines()
{
	pg_assert_same("Shopping:\n- milk\n- bread\nThanks", workspace_render_test_plain("Shopping:\n- milk\n* bread\nThanks"));
	pg_assert_same("1. one\n2. two\n3. three", workspace_render_test_plain("1. one\n2. two\n7. three"), 'numbered as a reader sees them');
	pg_assert_same("> first\n> second\nanswer", workspace_render_test_plain("> first\n>\n> second\nanswer"));
	pg_assert_same("- bold and code", workspace_render_test_plain('- **bold** and `code`'), 'marks go as before');
	pg_assert_same('Just a line and another', workspace_render_test_plain("Just a line\nand another"), 'running text still folds into one line');
	pg_assert_same('☐ task ☑ done', workspace_render_test_plain("- [ ] task\n- [x] done"), 'a checklist reads as before');
}

function test_workspace_render_excerpt_folds_lines()
{
	$source = pg_test_function_source('ws_plain_excerpt');

	pg_assert_contains("preg_replace('/\\s+/u', ' ', ws_plain_text(", $source, 'a banner or an e-mail line stays one line');
}

function test_workspace_render_guest_draws_lists_and_quotes()
{
	pg_assert_same('Shopping:<ul><li>milk</li><li><strong>bread</strong></li></ul>Thanks', ws_guest_render("Shopping:\n- milk\n- **bread**\nThanks"));
	pg_assert_same('<ol><li>one</li><li>two</li></ol><blockquote>said</blockquote>', ws_guest_render("1. one\n2. two\n> said"));
	pg_assert_same("a<br>\nb", ws_guest_render("a\nb"), 'running text as before');
	pg_assert_true(strpos(ws_guest_render('- <script>x</script>'), '<script') === false, 'escaped');
}

function test_workspace_render_approval_mail_reads_lists_as_lines()
{
	$text = ws_approval_guest_details_text("**Buy a laptop**\nWhy:\n- faster\n- lighter\n1. order\n2. pay\n> as agreed");

	pg_assert_same("Why:\n- faster\n- lighter\n1. order\n2. pay\n> as agreed", $text);

	// A line break stays one; an empty line stays one.
	pg_assert_same("line one\nline two\n\nafter a gap", ws_approval_guest_details_text("**Title**\nline one\nline two\n\nafter a gap"));
}
