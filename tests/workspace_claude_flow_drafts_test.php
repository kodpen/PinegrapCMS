<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a flow Claude drafts in a channel: the instructions the site
 * serves tell it to read the kinds of box and to put the flow into its
 * answer's changes as a draft; the channel answer takes a flow draft, which
 * applications already could propose; and boxes sent without places are
 * laid out. Before, Claude answered that none of its tools could build a
 * flow and wrote the design out in words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function workspace_claude_flow_drafts_test_code($function)
{
	$code = '';

	foreach (pg_test_source_tokens(pg_test_function_source($function)) as $token) {
		$code .= pg_test_source_text($token);
	}

	return $code;
}

// What stops publishing, from the graph's own check alone (no database).
function workspace_claude_flow_drafts_test_problems($graph)
{
	$texts = array();

	foreach (ws_flow_graph_check(ws_flow_graph_normalize($graph)) as $problem) {
		if (empty($problem['warning'])) {
			$texts[] = (string) $problem['text'];
		}
	}

	return $texts;
}

// The instructions name the kinds of box, the draft's shape and what the
// person does with it.
function test_workspace_claude_flow_drafts_instructions()
{
	$text = ws_claude_routine_prompt('https://shop.example/pinegrap/integration.php');

	pg_assert_contains('GET /workspace/flow-kinds', $text, 'the kinds of box');
	pg_assert_contains('{"type": "flow", "action": "draft", "data": {"name": "...", "nodes": [', $text, 'the draft in changes');
	pg_assert_contains('exactly one box that starts it', $text, 'one start');
	pg_assert_contains('question put to people is an approval box', $text, 'the question box');
	pg_assert_contains('leave x and y out', $text, 'no places');
	pg_assert_contains('a value box (kind value) whose', $text, 'the value box');
	pg_assert_contains('rule reads two values (' . implode(', ', array('join', 'compare', 'add', 'subtract', 'add_days', 'replace', 'cut', 'split')) . ') needs both', $text, 'its rules with two values');
	pg_assert_same(array(), array_diff(ws_flow_derived_two_inputs(), array('join', 'compare', 'add', 'subtract', 'add_days', 'replace', 'cut', 'split')), 'every rule with two values named');
	pg_assert_contains('join writes a, a space, then b, so split a text between them', $text, 'a text split between a and b');
	pg_assert_contains('opens it on the Flows canvas and publishes it', $text, 'the person publishes');
}

// The channel answer takes a flow draft as an application's proposal does;
// a run on a task still does not.
function test_workspace_claude_flow_drafts_answer_route()
{
	$answer = workspace_claude_flow_drafts_test_code('ws_api_claude_answer');
	pg_assert_contains("ws_changes_input(\$params['changes'] ?? array(), (int) \$row['requested_by'], (int) \$row['channel_id'], 0, false, true)", $answer, 'the channel answer takes drafts');
	pg_assert_contains("ws_changes_input(\$params['changes'] ?? array(), (int) \$row['requested_by'], 0, (int) \$row['task_id'])", $answer, 'a task run does not');

	$input = workspace_claude_flow_drafts_test_code('ws_changes_input');
	pg_assert_contains("(\$type === 'flow') && (\$for_proposal || \$drafts)", $input, 'a flow draft with either flag');
}

// Boxes sent all in one place (none, or the same x and y) are laid out from
// the start; boxes with places of their own keep them.
function test_workspace_claude_flow_drafts_layout()
{
	$sent = array('type' => 'flow', 'action' => 'draft', 'data' => array(
		'name'  => 'Kampanya',
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual'),
			array('id' => 'ask', 'kind' => 'approval', 'config' => array('title' => 'Başlasın mı?', 'channel_id' => 3, 'approvers' => array(7))),
			array('id' => 'go', 'kind' => 'post', 'config' => array('body' => 'Başladı', 'channel_id' => 3)),
		),
		'edges' => array(array('from' => 'start', 'to' => 'ask', 'on' => 'next'), array('from' => 'ask', 'to' => 'go', 'on' => 'approved')),
	));
	$read = ws_flow_draft_proposal_input($sent);
	$at = array();

	foreach ($read['data']['graph']['nodes'] as $node) {
		$at[$node['id']] = array($node['x'], $node['y']);
	}

	pg_assert_true($read['ok'], 'read');
	pg_assert_same(array(WS_FLOW_LAYOUT_ORIGIN, WS_FLOW_LAYOUT_ORIGIN), $at['start'], 'the start first');
	pg_assert_same(array(WS_FLOW_LAYOUT_ORIGIN + 2 * WS_FLOW_LAYOUT_STEP_X, WS_FLOW_LAYOUT_ORIGIN), $at['go'], 'a column per step');

	foreach ($sent['data']['nodes'] as $index => $node) {
		$sent['data']['nodes'][$index] += array('x' => 0, 'y' => 0);
	}

	pg_assert_same($read['data']['graph']['nodes'], ws_flow_draft_proposal_input($sent)['data']['graph']['nodes'], 'all at 0, 0 is no place either');

	$sent['data']['nodes'][0]['x'] = 400;
	$placed = ws_flow_draft_proposal_input($sent)['data']['graph']['nodes'];
	pg_assert_same(400, $placed[0]['x'], 'places of their own are kept');
	pg_assert_same(0, $placed[2]['x'], 'and the others too');
}

// What stops a draft from being published comes back with the answer
// refused, with the hints Pinegrap AI gets; one that can be published is
// taken. Claude's draft of 2026-11-04 had the flow's name as its start box's
// subject and went to the channel unpublishable.
function test_workspace_claude_flow_drafts_refused_while_unpublishable()
{
	$catalogue = ws_flow_ai_catalogue_hints(ws_flow_kind_catalogue(), array('order', 'contact'));
	$broken = array(
		'nodes' => array(
			array('id' => 'start', 'kind' => 'manual', 'config' => array('subject' => 'kampanyahazrlkak')),
			array('id' => 'title', 'kind' => 'value', 'config' => array('rule' => 'join', 'a' => 'Autumn - 20%', 'b' => '')),
			array('id' => 'say', 'kind' => 'post', 'config' => array('body' => '{{node.title.value}}', 'channel_id' => 3)),
		),
		'edges' => array(array('from' => 'start', 'to' => 'say', 'on' => 'next')),
	);
	// ws_flow_problems() also asks the database which kinds of record the
	// Workspace knows: the graph's own check, and the start box's line as it
	// would add it.
	$problems = workspace_claude_flow_drafts_test_problems($broken);
	$problems[] = 'The start box Elle başlat is about a kind of record the Workspace does not know (kampanyahazrlkak).';
	$refusal = ws_flow_draft_answer_refusal($broken, $catalogue, $problems);

	pg_assert_true(count($problems) === 2, 'two problems: the subject and the empty second value');
	pg_assert_contains('This flow could not be published like this: ', $refusal, 'refused');
	pg_assert_contains('kampanyahazrlkak', $refusal, 'the invented subject named');
	pg_assert_contains('subject of a Started by hand box is one of: order, contact', $refusal, 'with the kinds it may be');
	pg_assert_contains($problems[0], $refusal, 'the empty second value too');
	pg_assert_contains('Fix it and send the answer again with all of it, or leave the flow out and say why.', $refusal, 'what to do');

	$broken['nodes'][0]['config']['subject'] = '';
	$broken['nodes'][1]['config'] = array('rule' => 'join', 'a' => 'Autumn -', 'b' => '20%');
	pg_assert_same(array(), workspace_claude_flow_drafts_test_problems($broken), 'fixed');
	pg_assert_same('', ws_flow_draft_answer_refusal($broken, $catalogue, array()), 'one that can be published is taken');

	$input = workspace_claude_flow_drafts_test_code('ws_changes_input');
	pg_assert_contains("\$unpublishable = \$drafts ? ws_flow_draft_answer_refusal(\$drafted['row']['snapshot']['data']['graph'] ?? array()) : '';", $input, 'only for Claude\'s channel answer');
	pg_assert_contains('return $refuse($unpublishable);', $input, 'the answer refused');

	$text = ws_claude_routine_prompt('https://shop.example/pinegrap/integration.php');
	pg_assert_contains('the subject of a box started by hand (kind manual) is the kind of record', $text, 'the subject');
	pg_assert_contains('never the flow\'s', $text, 'not a name');
	pg_assert_contains('A flow that could not be published is refused with', $text, 'the refusal said');
}

// The drafts list is one column no wider than itself, so a wide flow drawing
// scrolls in its pane instead of widening the card and the channel.
function test_workspace_claude_flow_drafts_card_fits_the_column()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	preg_match('~\n\.ws-drafts \{([^}]*)\}~', $css, $rule);

	pg_assert_contains('grid-template-columns: minmax(0, 1fr);', $rule[1] ?? '', 'the column');
	pg_assert_contains('max-width: 34rem;', $rule[1] ?? '', 'as wide as before at most');
}

// The answer is sent once: a refused one posts nothing, so no trial answer
// is sent to see what the site says. On 2026-11-04 Claude answered a request
// with "probe" and a two-box draft named "x", and the flow asked for never came.
function test_workspace_claude_flow_drafts_no_trial_answer()
{
	$text = ws_claude_routine_prompt('https://shop.example/pinegrap/integration.php');

	pg_assert_contains('A refused answer posts nothing and the request stays open', $text, 'a refusal writes nothing');
	pg_assert_contains('never a trial or test answer,', $text, 'no trial answer');
	pg_assert_contains('nor a change made only to see what the site says', $text, 'no trial draft');
	pg_assert_contains('is posted to the channel and ends the request', $text, 'a taken answer is final');
}
