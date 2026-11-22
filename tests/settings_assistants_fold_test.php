<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Assistants and agents as folded rows: each part's one line
 * of state, which row opens (the PHP default and the dialog's rule in
 * backend.src.js, run under Node where it can), the row's markup, and the
 * names that stay: the cards' ids, fields and drawers sit unchanged inside
 * the rows, so the search index and the save read them as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/search.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/assistants_fold.php');

function settings_assistants_fold_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_settings_assistants_fold_claude_line()
{
	$now = mktime(15, 0, 0, 11, 20, 2026);

	$ready = ws_assistants_fold_claude(array('enabled' => true, 'missing' => array(), 'last_run' => mktime(14, 5, 0, 11, 20, 2026)), $now);
	pg_assert_same(lang('Ready') . ' · ' . lang(array('string' => 'last run {var:1}', 'vars' => '14:05')), $ready['line'], 'ready, run today: the hour');
	pg_assert_same(array(lang('Ready'), 'pg-tone-teal'), $ready['pill'], 'a teal pill');
	pg_assert_false($ready['attention'], 'nothing to look at');

	$older = ws_assistants_fold_claude(array('enabled' => true, 'missing' => array(), 'last_run' => mktime(9, 30, 0, 11, 2, 2026)), $now);
	pg_assert_contains('02.11.2026 09:30', $older['line'], 'a run on another day names the day');

	$never = ws_assistants_fold_claude(array('enabled' => true, 'missing' => array(), 'last_run' => 0), $now);
	pg_assert_same(lang('Ready') . ' · ' . lang('no run yet'), $never['line'], 'never run');

	$short = ws_assistants_fold_claude(array('enabled' => true, 'missing' => array('No application is chosen for Claude.', 'x'), 'last_run' => 0), $now);
	pg_assert_same('No application is chosen for Claude.', $short['line'], 'not ready: the first thing missing');
	pg_assert_same(array(lang('Not ready'), 'pg-tone-amber'), $short['pill'], 'an amber pill');
	pg_assert_true($short['attention'], 'switched on but not ready asks for attention');

	$off = ws_assistants_fold_claude(array('enabled' => false, 'missing' => array('Claude is switched off.'), 'last_run' => 0), $now);
	pg_assert_same(lang('Switched off'), $off['line'], 'off');
	pg_assert_same(array(lang('Off'), 'pg-tone-gray'), $off['pill'], 'a gray pill');
	pg_assert_false($off['attention'], 'off is a choice, not a fault');
}

function test_settings_assistants_fold_ai_and_voice_lines()
{
	$ai = ws_assistants_fold_ai(array('enabled' => true, 'missing' => array()));
	pg_assert_same(lang('On · answers in the channels'), $ai['line'], 'Pinegrap AI ready');
	pg_assert_same('pg-tone-teal', $ai['pill'][1], 'ready pill');

	$ai = ws_assistants_fold_ai(array('enabled' => true, 'missing' => array('The licence is not valid.')));
	pg_assert_same('The licence is not valid.', $ai['line'], 'not ready: what is missing');
	pg_assert_true($ai['attention'], 'and it asks for attention');

	$ai = ws_assistants_fold_ai(array('enabled' => false, 'missing' => array('x')));
	pg_assert_same(lang('Switched off'), $ai['line'], 'off');

	$voice = ws_assistants_fold_voice(array('enabled' => false, 'missing' => array('x')));
	pg_assert_same(lang('Voice messages are not turned into text'), $voice['line'], 'voice off');
	pg_assert_same(array(lang('Off'), 'pg-tone-gray'), $voice['pill'], 'off pill');

	$voice = ws_assistants_fold_voice(array('enabled' => true, 'missing' => array()));
	pg_assert_same(lang('Voice messages are turned into text'), $voice['line'], 'voice ready');

	$voice = ws_assistants_fold_voice(array('enabled' => true, 'missing' => array('No key is entered.')));
	pg_assert_same('No key is entered.', $voice['line'], 'voice not ready');
	pg_assert_true($voice['attention'], 'asks for attention');
}

function test_settings_assistants_fold_count_lines()
{
	$agents = ws_assistants_fold_agents(array('total' => 3, 'not_seen' => 1, 'waiting' => 1));
	pg_assert_same(lang(array('string' => '{var:1} agents', 'vars' => 3)) . ' · ' . lang(array('string' => '{var:1} not seen yet', 'vars' => 1)), $agents['line'], 'three agents, one not seen');
	pg_assert_same(array(lang(array('string' => '{var:1} waiting', 'vars' => 1)), 'pg-tone-amber'), $agents['pill'], 'waiting requests in the pill');
	pg_assert_false($agents['attention'], 'a waiting request is no fault of the settings');

	$agents = ws_assistants_fold_agents(array('total' => 1, 'not_seen' => 0, 'waiting' => 0));
	pg_assert_same(lang('1 agent'), $agents['line'], 'one agent');
	pg_assert_same(null, $agents['pill'], 'no pill when nothing waits');

	$agents = ws_assistants_fold_agents(array('total' => 0, 'not_seen' => 0, 'waiting' => 0));
	pg_assert_same(lang('No agent yet'), $agents['line'], 'none');

	$mcp = ws_assistants_fold_mcp(array('total' => 2, 'sign_in' => 1, 'unreachable' => 0, 'ready' => 1));
	pg_assert_same(lang(array('string' => '{var:1} servers', 'vars' => 2)) . ' · ' . lang(array('string' => '{var:1} asks for a sign-in', 'vars' => 1)), $mcp['line'], 'two servers, one asks for a sign-in');
	pg_assert_same(array(lang('Sign-in needed'), 'pg-tone-amber'), $mcp['pill'], 'sign-in pill');
	pg_assert_true($mcp['attention'], 'a sign-in asks for attention');

	$mcp = ws_assistants_fold_mcp(array('total' => 1, 'sign_in' => 0, 'unreachable' => 1, 'ready' => 0));
	pg_assert_same(array(lang('Cannot be reached'), 'pg-tone-rose'), $mcp['pill'], 'unreachable pill');
	pg_assert_true($mcp['attention'], 'and attention');

	$mcp = ws_assistants_fold_mcp(array('total' => 2, 'sign_in' => 0, 'unreachable' => 0, 'ready' => 2));
	pg_assert_same(array(lang('Ready'), 'pg-tone-teal'), $mcp['pill'], 'all ready');
	pg_assert_false($mcp['attention'], 'nothing to look at');

	$mcp = ws_assistants_fold_mcp(array('total' => 0, 'sign_in' => 0, 'unreachable' => 0, 'ready' => 0));
	pg_assert_same(lang('No server yet'), $mcp['line'], 'no server');
	pg_assert_same(null, $mcp['pill'], 'and no pill');

	pg_assert_same(lang('No template yet'), ws_assistants_fold_templates(0)['line'], 'no template');
	pg_assert_same(lang('1 template'), ws_assistants_fold_templates(1)['line'], 'one template');
	pg_assert_same(lang(array('string' => '{var:1} templates', 'vars' => 6)), ws_assistants_fold_templates(6)['line'], 'six templates');

	pg_assert_same(lang('Bulk deletes are off'), ws_assistants_fold_bulk(false)['line'], 'bulk delete off');
	pg_assert_same(lang('Bulk deletes may be proposed'), ws_assistants_fold_bulk(true)['line'], 'bulk delete on');
}

function test_settings_assistants_fold_default_open()
{
	$rows = array(
		array('key' => 'ws-claude', 'attention' => false),
		array('key' => 'ws-ai', 'attention' => false),
		array('key' => 'ws-mcp', 'attention' => true),
		array('key' => 'ws-voice', 'attention' => true),
	);

	pg_assert_same('ws-mcp', pg_settings_fold_pick($rows), 'the first part that needs attention');

	$rows[2]['attention'] = false;
	$rows[3]['attention'] = false;
	pg_assert_same('ws-claude', pg_settings_fold_pick($rows), 'else the first row');
	pg_assert_same('', pg_settings_fold_pick(array()), 'no rows, none open');
}

// Runs pgSettingsFoldPick() on each [keys, asks] pair; null when the
// function is not found, false when Node cannot run.
function settings_assistants_fold_test_js($cases)
{
	if (!preg_match('/^window\.pgSettingsFoldPick = function \(keys, asks\) \{.*?^\};$/ms', settings_assistants_fold_test_source('assets/js/backend.src.js'), $m)) {
		return null;
	}

	if (!function_exists('exec')) {
		return false;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return false;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgfold');

	if ($file === false) {
		return false;
	}

	file_put_contents($file, 'var window = {};' . "\n" . $m[0] . "\n"
		. 'console.log(JSON.stringify(' . json_encode($cases) . '.map(function (c) { return window.pgSettingsFoldPick(c[0], c[1]); })));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_settings_assistants_fold_what_opens_a_row()
{
	$keys = array('ws-claude', 'ws-ai', 'ws-agents', 'ws-mcp');
	$cases = array(
		// The row holding an asked card, field or drawer comes first.
		array($keys, array('ws-mcp', 'ws-ai')),
		// None asked: the row open before the save.
		array($keys, array('', 'ws-agents')),
		// A row that is no longer there is passed over.
		array($keys, array('ws-gone', null, 'ws-ai')),
		// Nothing asked: nothing changes (the pane keeps the server's pick).
		array($keys, array()),
		array(array(), array('ws-ai')),
	);

	$picked = settings_assistants_fold_test_js($cases);

	pg_assert_true($picked !== null, 'pgSettingsFoldPick() is in backend.src.js');

	if ($picked === false) {
		return;
	}

	pg_assert_same(array('ws-mcp', 'ws-agents', 'ws-ai', '', ''), $picked, 'the first asked row of the pane');

	// Every way into a card goes through the reveal: a link or a search hit
	// (jump()), a field the save marked (refused()), a drawer the save opens
	// again (reopen()), the page's #pgset- anchor, and the row kept open
	// over a save (spot() / paint()).
	$js = settings_assistants_fold_test_source('assets/js/backend.src.js');

	foreach (array('function jump(section)', 'function refused(fields, drawer)', 'function reopen(place)', 'function initSettingsAnchors()') as $name) {
		$from = strpos($js, $name);
		$body = ($from === false) ? '' : substr($js, $from, 2200);
		pg_assert_contains('pgSettingsFoldReveal(', $body, $name . ' opens the row it lands in');
	}

	$spot = substr($js, (int) strpos($js, 'function spot(button)'), 900);
	pg_assert_contains('fold:', $spot, 'a save notes the open row');
	pg_assert_contains('pgSettingsFoldPick(', substr($js, (int) strpos($js, 'function paint(key, response, place)'), 9000), 'and the pane read back opens it again');
}

function test_settings_assistants_fold_row_markup()
{
	$html = pg_settings_fold(array(
		array('key' => 'ws-claude', 'name' => 'Claude', 'icon' => 'bi-stars', 'tone' => 'pg-tone-violet', 'line' => 'Ready <b>', 'pill' => array('Ready', 'pg-tone-teal'), 'attention' => false,
			'html' => '<div id="pgset-ws-claude" class="pg-set-card"><div class="card"><div class="card-body"><label for="a">Routine address</label><input id="a" name="ws_claude_routine_url"></div></div></div>'),
		array('key' => 'ws-bulk', 'name' => 'Bulk changes', 'icon' => 'bi-collection', 'tone' => '', 'line' => 'Bulk deletes are off', 'pill' => null, 'attention' => false,
			'html' => '<div id="pgset-ws-bulk" class="pg-set-card"><div class="card"><div class="card-body"><label for="b">The assistants may propose deleting records in bulk</label><input id="b" name="ws_bulk_delete"></div></div></div>'),
	));

	pg_assert_contains('class="pg-set-fold"', $html, 'one list of rows');
	pg_assert_contains('id="pg-fold-ws-claude"', $html, 'a row per part');
	pg_assert_contains('aria-expanded="true" aria-controls="pg-fold-ws-claude-body"', $html, 'the first row open');
	pg_assert_contains('aria-expanded="false" aria-controls="pg-fold-ws-bulk-body"', $html, 'the other folded');
	pg_assert_contains('id="pg-fold-ws-bulk-body" role="region" aria-labelledby="pg-fold-ws-bulk-head" hidden', $html, 'a folded body is hidden');
	pg_assert_contains('Ready &lt;b&gt;', $html, 'the line is escaped');
	pg_assert_contains('<span class="pg-pill pg-tone-teal">Ready</span>', $html, 'the state pill');
	pg_assert_contains('pg-icon-tile pg-icon-tile-lg pg-tone-violet', $html, 'the icon tile in its tone');
	pg_assert_false(preg_match('/id="pgset-(?!ws-claude"|ws-bulk")/', $html) === 1, 'no row id is taken for a card');

	// The cards' own ids, names and labels are what the search and the save
	// read; inside the rows they read the same.
	pg_assert_contains('name="ws_claude_routine_url"', $html, 'the field names stay');
	$labels = pg_settings_card_labels($html, array('pgset-ws-claude', 'pgset-ws-bulk'));
	pg_assert_same(array('Routine address'), $labels['pgset-ws-claude'], 'Claude\'s labels, none of the next row');
	pg_assert_same(array('The assistants may propose deleting records in bulk'), $labels['pgset-ws-bulk'], 'bulk\'s label');
}

function test_settings_assistants_fold_pane_order_and_names()
{
	$pane = settings_assistants_fold_test_source('includes/settings/workspace_assistants.php');

	pg_assert_contains('pg_settings_fold(', $pane, 'the pane is drawn as folded rows');
	pg_assert_same(array('ws-claude', 'ws-ai', 'ws-agents', 'ws-mcp', 'ws-voice', 'ws-canvas-templates', 'ws-bulk'), ws_assistants_fold_order(), 'the rows in the drawing\'s order');

	// The cards and drawers are still drawn by their own functions, so field
	// names, button names, values and drawer ids do not change.
	foreach (array('ws_claude_settings_card(', 'ws_agents_settings_card(', 'ws_ai_settings_card(', 'ws_voice_settings_card(', 'ws_mcp_settings_card(', 'ws_bulk_settings_card(', 'ws_canvas_templates_settings_card(', 'ws_settings_agent_drawer(', 'ws_settings_mcp_drawer(') as $call) {
		pg_assert_contains($call, $pane, $call . ' still draws its part');
	}

	// A save keeps reading the same fields: the save module is untouched.
	pg_assert_false(strpos(settings_assistants_fold_test_source('includes/settings/workspace_assistants.save.php'), 'fold') !== false, 'the save knows nothing of rows');
}
