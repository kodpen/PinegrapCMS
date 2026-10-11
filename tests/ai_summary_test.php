<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the AI summary of a form item view: the links that open an AI
 * assistant with a question about the record (pg_ai_assistant_urls(),
 * pg_sw_ai_prompt(), pg_sw_ai_tokens()) and the button and box the
 * browser's built-in AI uses (_pg_sw_apply_ai_summary_bindings()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every assistant gets the whole question, encoded once.
function test_ai_summary_assistant_urls_carry_the_question()
{
	$question = 'Özetle: https://example.com/blog-post?r=AB12&x=1';
	$urls = pg_ai_assistant_urls($question);

	pg_assert_same(array('chatgpt', 'claude', 'perplexity', 'google'), array_keys($urls), 'four assistants');
	pg_assert_same('https://chatgpt.com/?q=' . rawurlencode($question), $urls['chatgpt'], 'ChatGPT');
	pg_assert_same('https://claude.ai/new?q=' . rawurlencode($question), $urls['claude'], 'Claude');
	pg_assert_same('https://www.perplexity.ai/search/new?q=' . rawurlencode($question), $urls['perplexity'], 'Perplexity');
	pg_assert_same('https://www.google.com/search?udm=50&q=' . rawurlencode($question), $urls['google'], 'Google AI Mode');
	pg_assert_false(strpos($urls['claude'], '&x=1'), 'the record address does not leak into the query');
}

// {url} is replaced where the wording puts it; a wording without it gets
// the address at its end.
function test_ai_summary_prompt_places_the_address()
{
	pg_assert_same('Read https://example.com/p and sum up', pg_sw_ai_prompt(array('ai_prompt_text' => 'Read {url} and sum up'), 'https://example.com/p'));
	pg_assert_same('Sum it up https://example.com/p', pg_sw_ai_prompt(array('ai_prompt_text' => ' Sum it up '), 'https://example.com/p'));
	pg_assert_contains('https://example.com/p', pg_sw_ai_prompt(array(), 'https://example.com/p'), 'the default wording carries it');
}

// Without an address (a private record, an e-mail) the links are empty;
// the summary box is always empty on the page.
function test_ai_summary_tokens_empty_without_address()
{
	$tokens = pg_sw_ai_tokens('', array());
	pg_assert_same(array('__ai_summary', '__ai_chatgpt_url', '__ai_claude_url', '__ai_perplexity_url', '__ai_google_url'), array_keys($tokens));
	foreach ($tokens as $name => $value) {
		pg_assert_same('', $value, $name);
	}

	$tokens = pg_sw_ai_tokens('https://example.com/p?r=1', array('ai_prompt_text' => 'Sum up {url}'));
	pg_assert_same('', $tokens['__ai_summary'], 'box stays empty');
	pg_assert_same(h('https://chatgpt.com/?q=' . rawurlencode('Sum up https://example.com/p?r=1')), $tokens['__ai_chatgpt_url'], 'escaped for the attribute');
}

// The bound button becomes a hidden plain button with the script's marker,
// the bound box a hidden live region; a link loses its address.
function test_ai_summary_bindings_mark_button_and_box()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'button', 'cssClass' => 'btn btn-primary d-inline-flex', 'text' => 'Summarize',
			'_bindings' => array('action' => 'ai_summarize')), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'a', 'cssClass' => 'btn', 'href' => '/somewhere', 'text' => 'Go',
			'_bindings' => array('action' => 'ai_summarize')), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'small', 'text' => 'x',
			'_bindings' => array('text' => '__ai_summary')), 'children' => array()),
	));
	_pg_sw_apply_ai_summary_bindings($tree);

	$button = $tree['children'][0]['props'];
	$link   = $tree['children'][1]['props'];
	$box    = $tree['children'][2]['props'];
	$names  = function ($props) {
		$out = array();
		foreach ($props['_attrs'] as $a) $out[$a['name']] = $a['value'];
		return $out;
	};

	pg_assert_same(array('type' => 'button', 'data-pg-ai-summarize' => '', 'hidden' => ''), $names($button), 'button attributes');
	pg_assert_same('btn btn-primary d-inline-flex d-none', $button['cssClass'], 'hidden over a display utility');
	pg_assert_same('#', $link['href'], 'link keeps no address');
	pg_assert_same(array('role' => 'button', 'data-pg-ai-summarize' => '', 'hidden' => ''), $names($link), 'link attributes');
	pg_assert_same(array('data-pg-ai-summary' => '', 'aria-live' => 'polite', 'hidden' => ''), $names($box), 'box attributes');

	// Twice changes nothing more.
	$again = $tree;
	_pg_sw_apply_ai_summary_bindings($again);
	pg_assert_same($tree, $again, 'idempotent');
}
