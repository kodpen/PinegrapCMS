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
 * approval mail).
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
