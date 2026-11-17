<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for /topic (/konu), /search (/ara) and /memory (/bellek): their
 * names in both languages, /topic written through ws_channel_update() with
 * its refusal and shown to the writer alone without a text, /search opened
 * by the writing box itself (nothing posted, the server not asked), and
 * /memory written through ws_memory_save() with ws_memory_check()'s
 * refusals, carrying an answered message's link and opening the New entry
 * form filled when it has no text (includes/workspace/commands.php,
 * command_registry.php, assets/js/workspace.js).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/channel_memory.php');

function workspace_command_tsm_test_channel($kind = 'public')
{
	return array('id' => 7, 'kind' => $kind, 'name' => 'general', 'topic' => '', 'archived_at' => 0, 'parent_id' => ($kind === 'thread') ? 6 : 0, 'owner_user_id' => 3);
}

// One PHP function of a file, from its name to its closing brace.
function workspace_command_tsm_test_body($name, $file = 'includes/workspace/commands.php')
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file));
	$start = strpos($source, 'function ' . $name . '(');

	return ($start === false) ? '' : substr($source, $start, strpos($source, "\n}\n", $start) - $start);
}

function workspace_command_tsm_test_js($file)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file));
}

// The three have their entries, names in Turkish and English, a live line
// and the channel's group; /topic and /memory run in the channel itself.
function test_workspace_command_topic_search_memory_entries()
{
	$registry = ws_command_registry();
	$table = ws_command_table();

	foreach (array('konu' => 'topic', 'topic' => 'topic', 'ara' => 'search', 'search' => 'search', 'bellek' => 'memory', 'memory' => 'memory') as $name => $key) {
		pg_assert_same($key, $table[$name] ?? '', '/' . $name);
	}

	foreach (array('topic' => 'konu', 'search' => 'ara', 'memory' => 'bellek') as $key => $tr) {
		pg_assert_same('channel', $registry[$key]['group'], $key . ' is the channel\'s');
		pg_assert_same($tr, $registry[$key]['tr'], $key . ' in Turkish');
		pg_assert_same($key, $registry[$key]['en'], $key . ' in English');
		pg_assert_true($registry[$key]['live'] !== '', $key . ' has a live line');
	}

	pg_assert_same('channel', $registry['topic']['where'], 'a discussion has no settings of its own');
	pg_assert_same('channel', $registry['memory']['where'], 'the Memory tab is the channel\'s');
	pg_assert_same('any', $registry['search']['where'], 'a discussion\'s box searches too');
	pg_assert_same('preview', $registry['search']['api'], 'over the API it writes nothing');
	pg_assert_same('preview', ws_api_command_kind('/bellek', array()), 'without a text the form');
	pg_assert_same('run', ws_api_command_kind('/bellek Faturalar', array()), 'with a text it writes');
	pg_assert_true(in_array('search', ws_api_command_preview_types(), true));
	pg_assert_true(in_array('memory_form', ws_api_command_preview_types(), true));
}

// /topic writes as the channel settings do, refused as they refuse it;
// without a text, the one in force to the writer alone.
function test_workspace_command_topic_refused_as_the_settings_and_shown_alone()
{
	$runner = workspace_command_tsm_test_body('ws_command_run_topic');

	pg_assert_contains("ws_channel_update(\$viewer, \$channel, array('topic' => \$topic))", $runner, 'writes as the settings do');
	pg_assert_contains("array('type' => 'error', 'error' => \$result['error'])", $runner, 'with its refusal');
	pg_assert_same(2, substr_count($runner, "'type' => 'ephemeral'"), 'both answers to the writer alone');
	pg_assert_true(strpos($runner, "if (\$topic === '')") < strpos($runner, 'ws_channel_update('), 'nothing written without a text');

	// The refusal is ws_channel_update()'s own, from the menu's right.
	pg_assert_contains("if (!ws_can_manage_channel(\$viewer, \$channel)) {\n        return array('ok' => false, 'error' => lang('Only the owner of the channel can change it.')", workspace_command_tsm_test_body('ws_channel_update', 'includes/workspace/channels.php'));

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	pg_assert_contains("ws_action_channel(\$viewer, \$request, 'manage')", substr($actions, strpos($actions, "case 'ws_channel_update':"), 200), 'the settings\' right is the same');

	$none = ws_command_topic_html('', false);
	pg_assert_contains(h(lang('This channel does not say what it is about yet.')), $none);
	pg_assert_contains(h(lang('What it is about')), $none);
	pg_assert_contains(h(lang(array('string' => 'Now: {var:1}', 'vars' => '“Spring launch”'))), ws_command_topic_html('Spring launch', false), 'the one in force');
	pg_assert_contains(h(lang(array('string' => 'It now reads: {var:1}', 'vars' => '“Spring launch”'))), ws_command_topic_html('Spring launch', true), 'just set');

	pg_assert_same(lang('Will show what it is about, to you alone'), ws_command_live_topic(null, workspace_command_tsm_test_channel(), '')['line']);
	pg_assert_same(lang(array('string' => 'Will set what it is about: {var:1}', 'vars' => '“Spring launch”')), ws_command_live_topic(null, workspace_command_tsm_test_channel(), 'Spring launch')['line']);
	pg_assert_contains("lang('Only the owner of the channel can change it.')", workspace_command_tsm_test_body('ws_command_live_topic'), 'warned ahead');
}

// /search: the box opens the palette on the channel with the words and
// returns before ws_send; the runner writes nothing.
function test_workspace_command_search_posts_nothing_and_asks_nothing()
{
	$runner = workspace_command_tsm_test_body('ws_command_run_search');

	pg_assert_same(array('type' => 'search', 'query' => 'fatura mart', 'channel_id' => 7), ws_command_run_search(null, workspace_command_tsm_test_channel(), ' fatura mart '));
	pg_assert_true(strpos($runner, 'db(') === false && strpos($runner, 'ws_message_post') === false, 'the runner writes nothing');

	$js = workspace_command_tsm_test_js('workspace.js');
	$send = substr($js, strpos($js, "\n        send: function (options) {"), 12000);
	$open = strpos($send, "window.PGWsPalette.open({ scope: 'here', query: searchWords });");

	pg_assert_true($open !== false, 'the palette opens on this channel with the words');
	pg_assert_true($open < strpos($send, "api('ws_send', data)"), 'before anything is sent');
	pg_assert_true(strpos(substr($send, strpos($send, 'if (searchWords !== null) {'), 300), 'return;') !== false, 'and nothing after it');
	$palette = workspace_command_tsm_test_js('workspace_palette.js');
	pg_assert_contains("ui.input.value = options.query;", $palette, 'the palette takes the words');
	pg_assert_contains("ui.input.setSelectionRange(ui.input.value.length, ui.input.value.length);", $palette, 'the caret at their end, nothing selected');
	pg_assert_contains("} else {\n                ui.input.select();", $palette, 'opened empty, as before');

	pg_assert_same(lang(array('string' => 'Will search #{var:1} for: {var:2}', 'vars' => array('general', '“fatura”'))), ws_command_live_search(null, workspace_command_tsm_test_channel(), 'fatura')['line']);

	if (!preg_match('/\n    function commandSearchWords\([^)]*\) \{\n.*?\n    \}\n/s', $js, $match) || !function_exists('exec')) {
		pg_assert_true(isset($match[0]), 'commandSearchWords() is in workspace.js');

		return;
	}

	$code = 1;
	$out = array();
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgsearchjs');
	file_put_contents($file, $match[0] . '
var COMMANDS = [{ key: "task", names: ["/task", "/görev"] }, { key: "search", names: ["/ara", "/search"] }];
console.log(JSON.stringify([
	commandSearchWords("/ara fatura mart", COMMANDS),
	commandSearchWords("/SEARCH invoice", COMMANDS),
	commandSearchWords("/ara", COMMANDS),
	commandSearchWords("//ara fatura", COMMANDS),
	commandSearchWords("/aralık", COMMANDS),
	commandSearchWords("/görev ara", COMMANDS),
	commandSearchWords("ara fatura", COMMANDS)
]));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(array('fatura mart', 'invoice', '', null, null, null, null), json_decode(implode("\n", $out), true), 'only the line that is /search, in either language');
}

// /memory writes through ws_memory_save() after ws_memory_check(), with its
// refusals; as a reply it carries the message's link; incomplete, the New
// entry form opens filled.
function test_workspace_command_memory_writes_as_new_entry()
{
	$runner = workspace_command_tsm_test_body('ws_command_run_memory');

	pg_assert_contains('$check = ws_memory_check($viewer, $channel, $entry);', $runner, 'checked as New entry is');
	pg_assert_contains("array('type' => 'error', 'error' => \$check['error'])", $runner, 'with its refusals');
	pg_assert_true(strpos($runner, "if (!\$check['ok'])") < strpos($runner, "ws_memory_save(\$channel['id'], \$check['values'], 'user', \$viewer['id'])"), 'written only once checked');
	pg_assert_contains('ws_command_message_link($channel, $message)', $runner, 'a reply carries the message\'s link');
	pg_assert_contains("lang('That message could not be found.')", $runner);
	pg_assert_contains("array('type' => 'memory_form', 'fill' => array('title' => '', 'text' => \$link))", $runner, 'without a text the form, filled');

	pg_assert_same(null, ws_command_memory_entry('  ', ''), 'nothing typed');
	pg_assert_same(array('title' => 'Invoices by the 5th', 'text' => ''), ws_command_memory_entry('Invoices by the 5th', ''));
	pg_assert_same(array('title' => 'Invoices', 'text' => "to accounts\n\nhttps://x/workspace.php?channel=7&message=9"), ws_command_memory_entry("Invoices\r\nto accounts", 'https://x/workspace.php?channel=7&message=9'), 'the link under the text');
	pg_assert_same(array('title' => 'Invoices', 'text' => 'https://x/m'), ws_command_memory_entry('Invoices', 'https://x/m'));

	$long = str_repeat('a', WS_MEMORY_TITLE_MAX + 20);
	$entry = ws_command_memory_entry($long, '');
	pg_assert_same(WS_MEMORY_TITLE_MAX, mb_strlen($entry['title']), 'a title the limit holds');
	pg_assert_same($long, $entry['text'], 'the whole text kept');
	pg_assert_same(null, ws_memory_entry_fault(array('title' => $entry['title'], 'text' => $entry['text']), time()), 'what ws_memory_check() takes');
	pg_assert_same(1, count(ws_command_live_memory(null, workspace_command_tsm_test_channel(), $long)['warnings']), 'warned of the cut');

	pg_assert_contains(h(lang('With the link of the message you answered.')), ws_command_memory_html('Invoices', true));
	pg_assert_true(strpos(ws_command_memory_html('Invoices', false), h(lang('With the link of the message you answered.'))) === false);
	pg_assert_same(lang('Will open New entry of the channel\'s memory'), ws_command_live_memory(null, workspace_command_tsm_test_channel(), '')['line']);

	// The gate: whoever may post, as New entry is offered.
	pg_assert_true(ws_command_gate_memory(null, 'memory', workspace_command_tsm_test_channel()), 'listed without a person');
	pg_assert_contains('ws_can_post_channel($viewer, $channel)', workspace_command_tsm_test_body('ws_command_gate_memory'));

	$js = workspace_command_tsm_test_js('workspace.js');
	pg_assert_contains("result.command.type === 'memory_form'", $js);
	pg_assert_contains('window.PGWsMemory.mount(pane, channel, self.memoryFill || null);', $js, 'the Memory tab opens with the fill');

	$memory = workspace_command_tsm_test_js('workspace_memory.js');
	pg_assert_contains("form(null, fill);", $memory, 'New entry opens filled');
	pg_assert_contains("title.value = entry ? entry.title : (given.title || '');", $memory);
	pg_assert_contains("text.value = entry ? entry.text : (given.text || '');", $memory);
}
