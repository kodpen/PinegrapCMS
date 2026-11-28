<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Was this helpful?" (includes/fn/kb_votes.php): the posted
 * answer, the cookie that stops a second vote, the record list's form and
 * count cells, the Yes and No bindings of a form item view, and the call
 * sites read from the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_kb_votes_answer_is_yes_or_no()
{
	pg_assert_same(1, pg_kb_vote_answer('yes'));
	pg_assert_same(1, pg_kb_vote_answer(' YES '));
	pg_assert_same(0, pg_kb_vote_answer('no'));
	pg_assert_same(null, pg_kb_vote_answer('maybe'));
	pg_assert_same(null, pg_kb_vote_answer(''));
	pg_assert_same(null, pg_kb_vote_answer(array('yes')), 'an array is no answer');
	pg_assert_same(null, pg_kb_vote_answer(1), 'a number is no answer');
}

// Only positive ids are kept, each once, in their order.
function test_kb_votes_cookie_ids_are_read_strictly()
{
	pg_assert_same(array(), pg_kb_vote_cookie_ids(''));
	pg_assert_same(array(), pg_kb_vote_cookie_ids(null));
	pg_assert_same(array(), pg_kb_vote_cookie_ids(array('1')));
	pg_assert_same(array(12, 7), pg_kb_vote_cookie_ids('12.7.12'));
	pg_assert_same(array(3), pg_kb_vote_cookie_ids('0.-4.x.3.05.1e3'), 'zero, signs, leading zeros and letters dropped');
	pg_assert_same(array(), pg_kb_vote_cookie_ids('12345678901'), 'more than ten digits');
}

// The record goes last, once; the oldest drop out past the limit.
function test_kb_votes_cookie_value_keeps_the_newest()
{
	pg_assert_same('5', pg_kb_vote_cookie_value(array(), 5));
	pg_assert_same('7.5', pg_kb_vote_cookie_value(array(5, 7), 5), 'moved to the end, not repeated');

	$ids = range(1, PG_KB_VOTE_COOKIE_IDS);
	$value = pg_kb_vote_cookie_value($ids, 1000);
	$kept = pg_kb_vote_cookie_ids($value);
	pg_assert_same(PG_KB_VOTE_COOKIE_IDS, count($kept));
	pg_assert_same(2, $kept[0], 'the oldest dropped');
	pg_assert_same(1000, $kept[count($kept) - 1]);
}

function test_kb_votes_given_reads_the_cookie()
{
	$before = isset($_COOKIE[PG_KB_VOTE_COOKIE]) ? $_COOKIE[PG_KB_VOTE_COOKIE] : null;
	$_COOKIE[PG_KB_VOTE_COOKIE] = '4.9';
	pg_assert_true(pg_kb_vote_given(9));
	pg_assert_false(pg_kb_vote_given(94));
	unset($_COOKIE[PG_KB_VOTE_COOKIE]);
	pg_assert_false(pg_kb_vote_given(9));
	if ($before !== null) $_COOKIE[PG_KB_VOTE_COOKIE] = $before;
}

// One form in the simple view or the advanced one; [All] or several is none.
function test_kb_votes_list_form_id()
{
	pg_assert_same(0, pg_kb_vote_list_form_id(array()));
	pg_assert_same(0, pg_kb_vote_list_form_id(array('custom_form' => '[All]')));
	pg_assert_same(42, pg_kb_vote_list_form_id(array('custom_form' => '42')));
	pg_assert_same(0, pg_kb_vote_list_form_id(array('custom_form' => array('42'))));
	pg_assert_same(7, pg_kb_vote_list_form_id(array('advanced_filters' => true, 'custom_form' => '42', 'custom_forms' => array('7'))));
	pg_assert_same(0, pg_kb_vote_list_form_id(array('advanced_filters' => true, 'custom_forms' => array('7', '8'))));
	pg_assert_same(0, pg_kb_vote_list_form_id(array('advanced_filters' => true, 'custom_forms' => array('-3'))));
}

function test_kb_votes_count_cell()
{
	pg_assert_same('', pg_kb_vote_count_cell(null, 'yes'), 'no vote, empty cell');
	pg_assert_same('', pg_kb_vote_count_cell(array('yes' => 0, 'no' => 2), 'yes'));
	pg_assert_same('2', pg_kb_vote_count_cell(array('yes' => 0, 'no' => 2), 'no'));
	pg_assert_same(h(number_format(1250)), pg_kb_vote_count_cell(array('yes' => 1250, 'no' => 0), 'yes'));
}

// The list's two columns stand right before Action, the rows' cells too.
function test_kb_votes_list_columns_before_action()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_list.php');
	$columns = pg_submitted_form_list_vote_columns(array('status' => array('heading' => 'S'), 'actions' => array('heading' => 'A')));
	pg_assert_same(array('status', 'helpful_yes', 'helpful_no', 'actions'), array_keys($columns));
	pg_assert_same('number', $columns['helpful_yes']['kind']);
}

function test_kb_votes_bindings_mark_the_buttons()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		array('type' => 'component', 'props' => array('componentType' => 'btn', 'cssClass' => 'btn btn-outline-primary',
			'_bindings' => array('action' => 'kb_vote_yes')), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'a', 'href' => '/x',
			'_bindings' => array('action' => 'kb_vote_no')), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'button',
			'_bindings' => array('action' => 'ai_summarize')), 'children' => array()),
	));

	$open = $tree;
	_pg_sw_apply_kb_vote_bindings($open, true);
	$attrs = function ($node) {
		$out = array();
		foreach ($node['props']['_attrs'] as $a) $out[$a['name']] = $a['value'];
		return $out;
	};

	$yes = $open['children'][0];
	pg_assert_same('button', $yes['props']['btnElement']);
	pg_assert_same('button', $yes['props']['btnType']);
	pg_assert_contains('d-none', $yes['props']['cssClass']);
	pg_assert_same(array('hidden' => '', 'data-pg-kb-vote' => 'yes', 'data-pg-kb-record' => '^^submitted_form_id^^'), $attrs($yes));

	$no = $open['children'][1];
	pg_assert_same('#', $no['props']['href'], 'a link goes nowhere');
	pg_assert_same('button', $attrs($no)['role']);
	pg_assert_same('no', $attrs($no)['data-pg-kb-vote']);

	pg_assert_false(isset($open['children'][2]['props']['_attrs']), 'another action is left alone');

	// Closed (not a collection, an e-mail): hidden, with nothing the script reads.
	$closed = $tree;
	_pg_sw_apply_kb_vote_bindings($closed, false);
	pg_assert_same(array('hidden' => ''), $attrs($closed['children'][0]));
	pg_assert_contains('d-none', $closed['children'][0]['props']['cssClass']);
}

function test_kb_votes_inject_only_with_a_button()
{
	pg_assert_same('<body></body>', pg_kb_vote_inject('<body></body>'));
	if (!defined('OUTPUT_PATH') || !defined('OUTPUT_SOFTWARE_DIRECTORY')) return;
	$html = pg_kb_vote_inject('<body><button data-pg-kb-vote="yes" data-pg-kb-record="3"></button></body>');
	pg_assert_contains('/assets/js/pg_kb_vote.js', $html);
	pg_assert_contains('window.pgKbVote', $html);
	pg_assert_same($html, pg_kb_vote_inject($html), 'added once');
}

// The renderer, the page, the endpoint, the cookie notice, the list and the
// designer are wired to the module.
function test_kb_votes_call_sites()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	pg_assert_contains("_pg_sw_apply_kb_vote_bindings(\$tree, \$kb_vote_open)", file_get_contents($root . 'includes/fn/widgets.php'));
	pg_assert_contains('pg_kb_vote_inject($content)', file_get_contents($root . 'get_page_content.php'));
	pg_assert_contains('PG_KB_VOTE_COOKIE', file_get_contents($root . 'includes/fn/consent.php'));
	pg_assert_contains('pg_submitted_form_list_vote_cells($rows)', file_get_contents($root . 'includes/fn/submitted_form_list.php'));
	pg_assert_contains("['kb_vote_yes',", file_get_contents($root . 'assets/js/style_designer.src.js'));
	$endpoint = file_get_contents($root . 'kb_vote.php');
	pg_assert_contains('pg_kb_vote_given($record_id)', $endpoint);
	pg_assert_contains('pg_kb_vote_throttled()', $endpoint);
	pg_assert_contains('pg_kb_vote_record_form($record_id)', $endpoint);
	pg_assert_contains("HTTP_X_PG_VOTE", $endpoint);
	pg_assert_contains("'kb_votes',", file_get_contents($root . 'install/index.php'));
	pg_assert_contains('upgrade_2026_11_0_kb_votes();', file_get_contents($root . 'includes/migrations/2026.11.0.php'));
}
