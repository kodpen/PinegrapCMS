<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for lists and quotes in Workspace texts (includes/workspace/render.php):
 * which lines are items ("- ", "* ", "1. ") or quoted ("> "), which runs of
 * them are drawn as a list (ws_text_blocks()) and which stay text as before,
 * how a message draws them (ws_render_body()), how the plain text an
 * assistant, a banner or an e-mail reads keeps them (ws_plain_text(),
 * ws_plain_line()), how a sum in an item is worked out (ws_calc_plain()),
 * and how a guest outside the team reads them (ws_guest_render(), the
 * approval mail); a ==highlight== drawn, read as words and left as text
 * where an old text holds equals signs.
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

// A text drawn with no tags resolved (none of these texts has one).
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
	pg_assert_same(array('kind' => 'bullet', 'number' => 0, 'indent' => 0, 'text' => 'milk'), ws_list_line('- milk'));
	pg_assert_same(array('kind' => 'bullet', 'number' => 0, 'indent' => 0, 'text' => 'bread'), ws_list_line('* bread'));
	pg_assert_same(array('kind' => 'bullet', 'number' => 0, 'indent' => 2, 'text' => 'sub'), ws_list_line('  - sub'));
	pg_assert_same(array('kind' => 'number', 'number' => 1, 'indent' => 0, 'text' => 'first'), ws_list_line('1. first'));
	pg_assert_same(array('kind' => 'number', 'number' => 12, 'indent' => 0, 'text' => 'twelfth'), ws_list_line('12. twelfth'));
	pg_assert_same(array('kind' => 'quote', 'number' => 0, 'indent' => 0, 'text' => 'said'), ws_list_line('> said'));
	pg_assert_same(array('kind' => 'quote', 'number' => 0, 'indent' => 0, 'text' => ''), ws_list_line('>'), 'an empty line of a quote');

	foreach (array('-', '- ', '-milk', '**bold**', '- [ ] task', '- [x] done', '* * *', '- - -', '2026. yılında', '1.5 kg', '>quoted', '->', 'text - dash', '', '01. Intro') as $line) {
		pg_assert_same(null, ws_list_line($line), 'not an item: "' . $line . '"');
	}
}

// Every text here was written before lists were drawn: it looks as it did.
function test_workspace_render_old_shapes_stay_text()
{
	$bodies = array(
		'sub-bullets'                   => "- fruit\n  - apple\n  - pear\n- bread",
		'a paragraph between bullets'   => "- milk\nfrom the corner shop\n- bread",
		'a numbered list broken by text' => "1. Open the box\nCarefully.\n2. Take it out",
		'a Turkish ordinal'             => '1. Lig maçı yarın',
		'one numbered line'             => '1. only one',
		'numbers that skip'             => "1. first\n3. third",
		'numbers that start late'       => "2. second\n3. third",
		'numbers with gaps between'     => "1. first\n\n2. second",
		'text after the items'          => "- milk\n- eggs\nThanks",
		'an indented line after items'  => "- milk\n- eggs\n  (two dozen)",
		'items of two kinds'            => "- milk\n1. eggs",
		'bare quote marks'              => ">\n>",
		'an arrow'                      => '-> next step',
		'a comparison'                  => 'a > b',
	);

	foreach ($bodies as $label => $body) {
		pg_assert_same(ws_render_text($body, array()), workspace_render_test_html($body), $label);
	}
}

function test_workspace_render_draws_lists_and_quotes()
{
	pg_assert_same('<div class="ws-text">Shopping:</div>' . "\n" . '<ul class="ws-list"><li>milk</li>' . "\n" . '<li><strong>bread</strong></li></ul>',
		workspace_render_test_html("Shopping:\n- milk\n* **bread**"));

	pg_assert_same('<ol class="ws-list"><li>one</li>' . "\n" . '<li>two</li>' . "\n" . '<li>three</li></ol>',
		workspace_render_test_html("1. one\n2. two\n3. three"));

	pg_assert_same('<blockquote class="ws-quote">first<br>' . "\n" . '<br>' . "\n" . 'second</blockquote>' . "\n" . '<div class="ws-text">Agreed.</div>',
		workspace_render_test_html("> first\n>\n> second\n\nAgreed."));

	pg_assert_same('<ul class="ws-list"><li>&lt;b&gt;x&lt;/b&gt;</li></ul>', workspace_render_test_html('- <b>x</b>'), 'the words are escaped first');
	pg_assert_same('<ul class="ws-list"><li>a</li>' . "\n" . '<li>b</li></ul>', workspace_render_test_html("  - a\n  - b"), 'items indented alike');
}

function test_workspace_render_lists_beside_other_blocks()
{
	$html = workspace_render_test_html("- a\n- b\n- [ ] task\n\n```\n- code\n```");

	pg_assert_true(strpos($html, '<ul class="ws-list"><li>a</li>' . "\n" . '<li>b</li></ul>') === 0, 'the list before the checklist');
	pg_assert_true(strpos($html, '<ul class="ws-checklist">') !== false, 'the checklist is its own');
	pg_assert_true(strpos($html, '<pre><code>- code</code></pre>') !== false, 'a line of code is no item');
}

function test_workspace_render_plain_text_without_lists_is_one_line()
{
	pg_assert_same('Hello there. Bold - really', workspace_render_test_plain("Hello\n\nthere. **Bold** - really"));
	pg_assert_same('- milk from the shop - bread', workspace_render_test_plain("- milk\nfrom the shop\n- bread"), 'an old shape folds as before');
	pg_assert_same('- x - y', workspace_render_test_plain("**- x**\n**- y**"), 'marks taken off make no list');
	pg_assert_same('- a - b', workspace_render_test_plain("```\n- a\n- b\n```"), 'the lines of code are no items');
}

function test_workspace_render_plain_text_keeps_items_and_numbers()
{
	pg_assert_same("Shopping:\n- milk\n- bread", workspace_render_test_plain("Shopping:\n- milk\n* **bread**"));
	pg_assert_same("1. one\n2. two", workspace_render_test_plain("1. one\n2. two"));
	pg_assert_same("> first\n> second\nAgreed.", workspace_render_test_plain("> first\n>\n> second\n\nAgreed."));
	pg_assert_same('1. Lig maçı yarın', workspace_render_test_plain('1. Lig maçı yarın'), 'the number as written');
}

function test_workspace_render_plain_line_folds_lists_for_stored_bodies()
{
	pg_assert_same('Which day? - Monday - Friday', ws_plain_line(array(), "Which day?\n- Monday\n- Friday", array()));
}

function test_workspace_render_calc_plain_works_out_an_items_words()
{
	pg_assert_same("- 10 - 4 = 6", ws_calc_plain("- 10 - 4 ="), 'the bullet is no minus sign');
	pg_assert_same("1. 2 + 3 = 5\n2. 1 + 1 = 2", ws_calc_plain("1. 2 + 3 =\n2. 1 + 1 ="));
	pg_assert_same('-10 - 4 = -14', ws_calc_plain('-10 - 4 ='), 'a minus sign that is no bullet');
}

function test_workspace_render_guest_page_and_approval_mail()
{
	pg_assert_same('Steps:' . "\n" . '<ol class="ws-list"><li>Sign</li>' . "\n" . '<li>Send</li></ol>', ws_guest_render("Steps:\n1. Sign\n2. Send"));
	pg_assert_same("- a<br>\nb<br>\n- c", ws_guest_render("- a\nb\n- c"), 'an old shape stays text');

	pg_assert_same("Please check:\n- price\n- date\nThanks", ws_approval_guest_details_text("**Approve the offer**\nPlease check:\n- price\n- date\n\nThanks"));
	pg_assert_same("1. Sign\n2. Send", ws_approval_guest_details_text("1. Sign\n2. Send"));
	pg_assert_same("One\nTwo", ws_approval_guest_details_text("One\nTwo"), 'a line break is one line break');
}

function test_workspace_render_draws_a_highlight()
{
	pg_assert_same('a <mark>key point</mark> here', workspace_render_test_html('a ==key point== here'));
	pg_assert_same('<mark><strong>both</strong></mark>', workspace_render_test_html('==**both**=='), 'a mark inside a highlight');
	pg_assert_same('<code>==x==</code>', workspace_render_test_html('`==x==`'), 'code stays as written');
	pg_assert_same('<a href="https://a.test/?q==x==" target="_blank" rel="noopener noreferrer">https://a.test/?q==x==</a>', workspace_render_test_html('https://a.test/?q==x=='), 'an address is set aside');
	pg_assert_same('<a href="https://a.test" target="_blank" rel="noopener noreferrer"><mark>see</mark></a>', workspace_render_test_html('[==see==](https://a.test)'), 'a link\'s words');
	pg_assert_same('<ul class="ws-list"><li><mark>milk</mark></li>' . "\n" . '<li>bread</li></ul>', workspace_render_test_html("- ==milk==\n- bread"), 'in a list item');

	// Pinned: no word boundary is asked, as in the notes import, so two
	// unspaced comparisons in one line read as one highlight.
	pg_assert_same('a<mark>b and c</mark>d', workspace_render_test_html('a==b and c==d'));
}

// Texts stored before highlights were drawn: these shapes look as they did.
function test_workspace_render_old_equals_signs_stay_text()
{
	foreach (array('a == b', 'x === y', '== Title ==', '=====', '== x', 'x ==', '==  ==', "==a\nb==", 'a ==b == c', '== a==', '===x===') as $body) {
		pg_assert_same(ws_render_text($body, array()), workspace_render_test_html($body), 'stays text: "' . $body . '"');
		pg_assert_same(false, strpos(workspace_render_test_html($body), '<mark>'), 'no highlight: "' . $body . '"');
	}
}

function test_workspace_render_plain_text_drops_paired_highlights_only()
{
	pg_assert_same('a key point here', workspace_render_test_plain('a ==key point== here'));
	pg_assert_same('a == b and x === y', workspace_render_test_plain('a == b and x === y'), 'a lone == stays');
	pg_assert_same("- milk\n- bread", workspace_render_test_plain("- ==milk==\n- bread"), 'an item keeps its words');
	pg_assert_same('Sum ==', ws_plain_line(array('id' => 1, 'role' => 0), 'Sum ==', array()), 'one line too');
}

function test_workspace_render_guest_page_draws_a_highlight()
{
	pg_assert_same('a <mark>key</mark> point', ws_guest_render('a ==key== point'));
	pg_assert_same('a == b', ws_guest_render('a == b'), 'a comparison stays text');
	pg_assert_same('<a href="https://a.test/?q==x==" target="_blank" rel="nofollow noopener noreferrer">https://a.test/?q==x==</a>', ws_guest_render('https://a.test/?q==x=='), 'an address is set aside');
}

// Headings and rules are drawn in notes only (ws_render_body()'s $note).
function workspace_render_test_note($body)
{
	return ws_render_body($body, array(), null, false, null, null, true);
}

function test_workspace_render_heading_and_rule_lines()
{
	pg_assert_same(array('level' => 1, 'text' => 'Plan'), ws_heading_line('# Plan'));
	pg_assert_same(array('level' => 2, 'text' => 'Plan **A**'), ws_heading_line('## Plan **A**  '));
	pg_assert_same(array('level' => 3, 'text' => 'Three'), ws_heading_line("###\tThree"));

	foreach (array('#tag', '#', '# ', '#### Four', ' # indented', '\\# escaped', '#order:5', '<#order:5>', '') as $line) {
		pg_assert_same(null, ws_heading_line($line), 'no heading: "' . $line . '"');
	}

	foreach (array('---', '-----', '  ---  ') as $line) {
		pg_assert_same(true, ws_rule_line($line), 'a rule: "' . $line . '"');
	}

	foreach (array('--', '- - -', '***', '___', '--- x', '|---|---|', '') as $line) {
		pg_assert_same(false, ws_rule_line($line), 'no rule: "' . $line . '"');
	}
}

function test_workspace_render_note_draws_headings_and_rules()
{
	pg_assert_same('<h2 class="ws-heading h4">Plan</h2>', workspace_render_test_note('# Plan'));
	pg_assert_same(
		'<h2 class="ws-heading h4">One</h2><h3 class="ws-heading h5">Two <strong>b</strong></h3><h4 class="ws-heading h6">Three &lt;i&gt;</h4>'
			. '<div class="ws-text">#### Four<br>' . "\n" . '#tag</div><hr class="ws-rule"><div class="ws-text">after</div>',
		workspace_render_test_note("# One\n## Two **b**\n### Three <i>\n#### Four\n#tag\n---\nafter")
	);
	pg_assert_same(
		'<div class="ws-text">Shopping:</div><h3 class="ws-heading h5">List</h3><ul class="ws-list"><li>milk</li>' . "\n" . '<li>bread</li></ul>',
		workspace_render_test_note("Shopping:\n## List\n- milk\n- bread"),
		'a heading ends the paragraph before a list'
	);
	$code = workspace_render_test_note("```\n# comment\n---\n```");
	pg_assert_same(true, strpos($code, "<code># comment\n---</code>") !== false, 'lines of code are no headings');
	pg_assert_same(false, strpos($code, '<h2') || strpos($code, '<hr'), 'nothing drawn of them');
}

function test_workspace_render_message_keeps_headings_raw()
{
	foreach (array('# Plan', "## Two\n---\nx", '### x **b**') as $body) {
		pg_assert_same(ws_render_text($body, array()), workspace_render_test_html($body), 'stays text in a message: "' . $body . '"');
	}
}

function test_workspace_render_plain_text_of_headings()
{
	$viewer = array('id' => 1, 'role' => 0);

	pg_assert_same("Plan\nfirst line second line\nTwo\n- milk", ws_plain_text($viewer, "# Plan\nfirst line\nsecond line\n---\n## Two\n- milk", array(), true), 'a note: a heading its own line, a rule none');
	pg_assert_same('# Plan x', ws_plain_text($viewer, "# Plan\n---\nx", array()), 'a message as before: # kept, a line of dashes dropped');
	pg_assert_same('Plan', ws_plain_line($viewer, '# Plan', array(), true), 'one line too');
}

function test_workspace_render_guest_page_keeps_heading_signs()
{
	pg_assert_same('# Plan<br>' . "\n" . '---', ws_guest_render("# Plan\n---"), 'a guest reads messages, where # is text');
}
