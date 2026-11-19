<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Make a task" on a prototype comment (includes/fn/
 * prototype_comments.php, includes/panel/designer.php,
 * assets/js/style_designer_comments.js): the task's fields, the guest's
 * words quoted as data, a task of its own for every press, and no button
 * with the Workspace off.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_comments.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// A prototype_comments row, laid over the given fields.
function prototype_comments_task_test_row($over = array())
{
	return array_merge(array(
		'id' => 4, 'link_id' => 7, 'style_id' => 31, 'page_id' => 120, 'node_id' => 'n1',
		'pos_x' => 1250, 'pos_y' => 5000, 'parent_id' => 0, 'author_name' => 'Ayşe', 'user_id' => 0,
		'body' => 'Bigger, please.', 'created_at' => 1000, 'resolved_at' => 0, 'resolved_by' => 0,
	), $over);
}

function test_proto_comment_task_offered_only_with_the_workspace_on()
{
	pg_assert_same(false, pg_proto_comment_task_offered(false, true), 'no button with the Workspace off');
	pg_assert_same(false, pg_proto_comment_task_offered(true, false), 'none for a designer taken out of the team');
	pg_assert_same(true, pg_proto_comment_task_offered(true, true), 'offered with the Workspace on to a member');
}

function test_proto_comment_task_fields()
{
	$input = pg_proto_comment_task_input(prototype_comments_task_test_row(), 'home', '2026-11-19');

	pg_assert_same(array(array('type' => 'page', 'id' => 120)), $input['refs'], 'the page is tagged');
	pg_assert_same(0, $input['channel_id'], 'on no channel');
	pg_assert_same(array(), $input['assignees'], 'nobody named: the designer\'s own task');
	pg_assert_same('2026-11-19', $input['due_date'], 'due today');
	pg_assert_true(array_key_exists('remind_minutes', $input) && ($input['remind_minutes'] === null), 'due at once, so no reminder');
	pg_assert_true(strpos($input['title'], 'home') !== false, 'the title names the page');
	pg_assert_same("Ayşe (guest, outside the team) commented on the prototype of home:\n> Bigger, please.", $input['description'], 'who wrote it and where, the words quoted');

	$data = ws_followup_task_data(3, $input, strtotime('2026-11-19 09:00:00'));
	pg_assert_true($data['ok'], 'the follow-up takes these fields');
	pg_assert_same(array(3), $data['data']['assignees'], 'the task is the designer\'s who pressed');
	pg_assert_same(array('<#page:120>'), $data['data']['refs'], 'tagged with the page alone');
	pg_assert_same(null, $data['data']['remind_minutes'], 'no reminder');
}

// A guest's words are data: no tag of the panel, no markup of a block, no
// line out of the quote.
function test_proto_comment_task_quotes_the_guests_words_as_data()
{
	$quote = pg_proto_comment_task_quote("Make it <b>red</b> <@user:1> <#order:5>\n> not a quote\n```\n:::note\nend");

	pg_assert_same("> Make it ‹b›red‹/b› ‹@user:1› ‹#order:5›\n> › not a quote\n>\n> note\n> end", $quote, 'every line quoted, tags and brackets disarmed, block starts dropped');
	pg_assert_same(array(), ws_tokens($quote), 'no tag a task would store');

	$input = pg_proto_comment_task_input(prototype_comments_task_test_row(array(
		'author_name' => '<@user:2> Ayşe',
		'body' => "<#page:9> one\ntwo",
	)), 'home', '2026-11-19');

	pg_assert_same(array(), ws_tokens($input['description']), 'neither the name nor the words tag anything');

	$html = ws_render_body($input['description'], array());
	pg_assert_true(strpos($html, '<blockquote class="ws-quote">') !== false, 'the task shows the words as a quote');
	pg_assert_true(strpos($html, '&lsaquo;') !== false || strpos($html, '‹') !== false, 'the disarmed tag shows as written');
	pg_assert_true(strpos($html, 'ws-tag') === false && strpos($html, '<a ') === false, 'no chip or link from the guest\'s words');
	pg_assert_same(array(array('type' => 'page', 'id' => 120)), $input['refs'], 'only the comment\'s page is tagged');
	pg_assert_true(strpos($input['description'], "\n> ‹#page:9› one\n> two") !== false, 'each line of the words quoted');
}

// P16: a second comment on the same page, or the same one asked again,
// opens a task of its own: $once is false.
function test_proto_comment_task_opens_a_task_of_its_own_as_the_designer()
{
	$source = pg_test_function_source('pg_proto_comment_task_open');

	pg_assert_true((bool) preg_match('/ws_followup_task_open\(\$viewer,\s*pg_proto_comment_task_input\([^;]*\),\s*false\)/', $source), 'opened through ws_followup_task_open() with $once false');
	pg_assert_true(strpos($source, 'ws_viewer($user)') !== false, 'as the designer who pressed');
	pg_assert_true(strpos($source, "(int) \$row['style_id'] !== (int) \$style_id") !== false, 'only a comment of the design the list was asked for');
	pg_assert_true(strpos($source, 'pg_proto_comment_task_offered(') !== false, 'refused with the Workspace off');
}

function test_proto_comment_task_action_is_designers_only_and_clean()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');

	pg_assert_true(strpos($source, "case 'proto_comment_task':") !== false, 'an action of the designer');
	pg_assert_true(!preg_match("/content_ok = array\([^)]*'proto_comment_task'/s", $source), 'not opened to a content operator');
	pg_assert_true(strpos($source, "'task'   => pg_proto_comment_task_offered_to(\$user)") !== false, 'the list says whether the button is offered');

	$cache = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_cache.php');
	pg_assert_true(strpos($cache, "'proto_comment_task'") !== false, 'clean for the page cache');
}

function test_proto_comment_task_button_only_where_offered()
{
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_comments.js');

	pg_assert_true(strpos($part, "if (typeof res.task === 'boolean') S.task = res.task;") !== false, 'the part reads the offer from the list');
	pg_assert_true((bool) preg_match('/S\.task && !resolved && page\.name !== \'\'/', $part), 'the button only where offered, on an open thread of a page that is there');
	pg_assert_true(strpos($part, "_pgApiPost('proto_comment_task'") !== false, 'pressed through the designer action');
	pg_assert_true(strpos($part, "_sdT('Make a task')") !== false, 'the button\'s words');
}
