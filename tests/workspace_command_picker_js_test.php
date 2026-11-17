<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the composer's "/" list in workspace.js: a discussion's box
 * leaves out a command that runs only in the channel itself, the list is
 * drawn under the headers of the command groups (in the order of
 * ws_command_group_labels()) only when nothing is typed and it is longer
 * than it shows, and a header is never picked by the arrows, Enter, Tab or
 * a click. The Node parts are skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of workspace.js.
function workspace_command_picker_js_test_source()
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'));
}

// One top-level "function name(" of workspace.js; '' when it is not there.
function workspace_command_picker_js_test_function($name)
{
	return preg_match('/\n    function ' . $name . '\([^)]*\) \{\n.*?\n    \}\n/s', workspace_command_picker_js_test_source(), $match) ? $match[0] : '';
}

// One method "name: function (" of the channel screen, as a plain
// function of that name; '' when it is not there.
function workspace_command_picker_js_test_method($name)
{
	if (!preg_match('/\n        ' . $name . ': function (\([^)]*\)) \{\n.*?\n        \},?\n/s', workspace_command_picker_js_test_source(), $match)) {
		return '';
	}

	return 'function ' . $name . preg_replace('/,\n$/', "\n", substr($match[0], strlen("\n        " . $name . ': function '))) . "\n";
}

// Runs a script with Node and answers what it printed as JSON; null without
// Node.
function workspace_command_picker_js_test_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgpickjs');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : null;
}

// The registry as BOOT.commands gives it, in its own order: the groups
// interleave (/schedule of "channel" comes before /away of "plan").
function workspace_command_picker_js_test_commands()
{
	return 'var COMMANDS = [
	{ command: "Task", name: "/task", names: ["/task", "/görev"], description: "d", example: "e", group: "tasks", group_label: "Tasks", where: "any" },
	{ command: "Schedule", name: "/schedule", names: ["/schedule", "/zamanla"], description: "d", example: "e", group: "channel", group_label: "The channel", where: "any" },
	{ command: "Away", name: "/away", names: ["/away", "/izin"], description: "d", example: "e", group: "plan", group_label: "Plan", where: "any" },
	{ command: "Agent channel", name: "/agent-channel", names: ["/agent-channel", "/ajan-kanalı"], description: "d", example: "e", group: "agents", group_label: "Agents and applications", where: "channel" },
	{ command: "Help", name: "/help", names: ["/help", "/yardım"], description: "d", example: "e", group: "channel", group_label: "The channel", where: "any" },
	{ command: "Price", name: "/price", names: ["/price", "/fiyat"], description: "d", example: "e", group: "records", group_label: "Records", where: "any" }
];
var ORDER = ["tasks", "plan", "channel", "records", "agents"];
function labels(rows) { return rows.map(function (row) { return row.header ? "#" + row.label : row.label; }); }
';
}

function test_workspace_command_picker_js_rows_by_channel_and_groups()
{
	$fold = workspace_command_picker_js_test_function('foldText');
	$rows = workspace_command_picker_js_test_function('commandPickerRows');
	$groups = workspace_command_picker_js_test_function('commandPickerGroups');

	pg_assert_true($rows !== '', 'commandPickerRows() is in workspace.js');
	pg_assert_true($groups !== '', 'commandPickerGroups() is in workspace.js');

	$result = workspace_command_picker_js_test_node($fold . $rows . $groups . workspace_command_picker_js_test_commands() . '
console.log(JSON.stringify({
	channel: labels(commandPickerRows(COMMANDS, "", false)),
	thread: labels(commandPickerRows(COMMANDS, "", true)),
	typedChannel: labels(commandPickerRows(COMMANDS, "aj", false)),
	typedThread: labels(commandPickerRows(COMMANDS, "aj", true)),
	folded: labels(commandPickerRows(COMMANDS, "gore", false)),
	grouped: labels(commandPickerGroups(commandPickerRows(COMMANDS, "", false), ORDER)),
	groupedThread: labels(commandPickerGroups(commandPickerRows(COMMANDS, "", true), ORDER)),
	noOrder: labels(commandPickerGroups(commandPickerRows(COMMANDS, "", false), null)),
	none: commandPickerRows(null, "", false).length
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('/task', '/schedule', '/away', '/agent-channel', '/help', '/price'), $result['channel'], 'the channel\'s box offers every command, in the registry\'s order');
	pg_assert_same(array('/task', '/schedule', '/away', '/help', '/price'), $result['thread'], 'a discussion\'s box leaves out /agent-channel');
	pg_assert_same(array('/ajan-kanalı'), $result['typedChannel'], 'typed, it is found in the channel by its Turkish name');
	pg_assert_same(array(), $result['typedThread'], 'typed in a discussion, it is not found');
	pg_assert_same(array('/görev'), $result['folded'], 'the folded name still finds a command');
	pg_assert_same(array('#Tasks', '/task', '#Plan', '/away', '#The channel', '/schedule', '/help', '#Records', '/price', '#Agents and applications', '/agent-channel'), $result['grouped'], 'a header per group, in the groups\' order');
	pg_assert_same(array('#Tasks', '/task', '#Plan', '/away', '#The channel', '/schedule', '/help', '#Records', '/price'), $result['groupedThread'], 'a group left empty in a discussion has no header');
	pg_assert_same(array('#Tasks', '/task', '#The channel', '/schedule', '/help', '#Plan', '/away', '#Agents and applications', '/agent-channel', '#Records', '/price'), $result['noOrder'], 'without an order the groups come as met');
	pg_assert_same(0, $result['none'], 'no registry, no rows');
}

function test_workspace_command_picker_js_headers_never_picked()
{
	$fold = workspace_command_picker_js_test_function('foldText');
	$rows = workspace_command_picker_js_test_function('commandPickerRows');
	$groups = workspace_command_picker_js_test_function('commandPickerGroups');
	$draw = workspace_command_picker_js_test_method('drawPicker');
	$key = workspace_command_picker_js_test_method('pickerKey');

	pg_assert_true($draw !== '', 'drawPicker() is in workspace.js');
	pg_assert_true($key !== '', 'pickerKey() is in workspace.js');

	// A fake DOM, just enough for the two methods.
	$dom = '
function FakeNode(tag, cls, text) {
	var node = this;
	this.tag = tag; this.className = cls || ""; this.textContent = text || ""; this.children = []; this.listeners = {}; this.attrs = {}; this.parentNode = null;
	this.classList = {
		contains: function (name) { return node.className.split(" ").indexOf(name) !== -1; },
		toggle: function (name, on) { var list = node.className.split(" ").filter(function (part) { return part && part !== name; }); if (on) { list.push(name); } node.className = list.join(" "); },
		add: function (name) { this.toggle(name, true); }
	};
}
FakeNode.prototype.appendChild = function (child) { child.parentNode = this; this.children.push(child); return child; };
FakeNode.prototype.addEventListener = function (type, fn) { this.listeners[type] = fn; };
FakeNode.prototype.setAttribute = function (name, value) { this.attrs[name] = value; };
FakeNode.prototype.all = function () { var out = []; this.children.forEach(function (child) { out.push(child); out = out.concat(child.all()); }); return out; };
FakeNode.prototype.querySelectorAll = function (selector) { var name = selector.slice(1); return this.all().filter(function (node) { return node.classList.contains(name); }); };
FakeNode.prototype.querySelector = function (selector) { return this.querySelectorAll(selector)[0] || null; };
Object.defineProperty(FakeNode.prototype, "previousElementSibling", { get: function () { var list = this.parentNode ? this.parentNode.children : []; var at = list.indexOf(this); return at > 0 ? list[at - 1] : null; } });
function el(tag, cls, text) { return new FakeNode(tag, cls, text); }
function clear(node) { node.children = []; return node; }
function badgedIcon() { return el("i", "bi"); }
function avatar() { return el("span", "avatar"); }
function pickerAgentFace() { return el("span", "face"); }
function touchScreen() { return false; }
function t(key) { return key; }
var BOOT = { ref_types: {} };
var picked = [];
var self = { picker: el("div", "ws-picker"), pickerMode: "command", notesMode: false, caretPicker: false, placePicker: function () {}, fitPicker: function () {}, pick: function (item) { picked.push(item.label); } };
self.drawPicker = drawPicker; self.pickerKey = pickerKey;
function press(key) { var stopped = false; var handled = self.pickerKey({ key: key, shiftKey: false, preventDefault: function () { stopped = true; } }); return handled && stopped; }
function active() { var row = self.picker.querySelector(".active"); return row ? row.querySelector(".ws-picker-label").textContent : null; }
';

	$result = workspace_command_picker_js_test_node($fold . $rows . $groups . $draw . $key . $dom . workspace_command_picker_js_test_commands() . '
self.drawPicker(commandPickerGroups(commandPickerRows(COMMANDS, "", false), ORDER));
var heads = self.picker.querySelectorAll(".ws-picker-group");
var out = { heads: heads.map(function (head) { return head.textContent; }), headButtons: heads.filter(function (head) { return head.tag === "button" || head.classList.contains("ws-picker-item"); }).length, items: self.pickerItems.map(function (item) { return item.label; }), first: active() };
var down = [];
for (var i = 0; i < 6; i++) { press("ArrowDown"); down.push(active()); }
out.down = down;
press("ArrowUp");
out.up = active();
press("Enter");
var event = { prevented: false, preventDefault: function () { this.prevented = true; } };
heads[0].listeners.mousedown(event);
out.headClickPrevented = event.prevented;
out.picked = picked.slice();
self.picker.querySelectorAll(".ws-picker-item")[2].listeners.mousedown({ preventDefault: function () {} });
out.rowClick = picked[picked.length - 1];
self.drawPicker(commandPickerRows(COMMANDS, "a", false));
out.typedHeads = self.picker.querySelectorAll(".ws-picker-group").length;
out.typedItems = self.pickerItems.map(function (item) { return item.label; });
console.log(JSON.stringify(out));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('Tasks', 'Plan', 'The channel', 'Records', 'Agents and applications'), $result['heads'], 'a header row per group is drawn');
	pg_assert_same(0, $result['headButtons'], 'a header is no button and no row of the list');
	pg_assert_same(array('/task', '/away', '/schedule', '/help', '/price', '/agent-channel'), $result['items'], 'the rows the keys walk have no header');
	pg_assert_same('/task', $result['first'], 'the first command is chosen, not the first header');
	pg_assert_same(array('/away', '/schedule', '/help', '/price', '/agent-channel', '/task'), $result['down'], 'the arrow down skips every header and comes round');
	pg_assert_same('/agent-channel', $result['up'], 'the arrow up skips them too');
	pg_assert_same('/agent-channel', $result['picked'][0] ?? null, 'Enter picks the chosen command');
	pg_assert_true($result['headClickPrevented'], 'a click on a header keeps the focus in the box');
	pg_assert_same(1, count($result['picked']), 'a click on a header picks nothing');
	pg_assert_same('/schedule', $result['rowClick'], 'a click on a row still picks it');
	pg_assert_same(0, $result['typedHeads'], 'with a word typed the headers go');
	pg_assert_same(array('/away', '/agent-channel'), $result['typedItems'], 'and the word filters across the groups');
}

function test_workspace_command_picker_js_wiring()
{
	$source = workspace_command_picker_js_test_source();

	// The composer passes whether it is a discussion's, and groups only an
	// untyped list longer than it shows.
	pg_assert_contains("commandPickerRows(BOOT.commands, query, !!self.isThread || !!(self.channel && (self.channel.kind === 'thread')))", $source, 'the list knows a discussion\'s box');
	pg_assert_contains("if ((query === '') && shown && (shown.scrollHeight > shown.clientHeight + 1)) {", $source, 'headers only when nothing is typed and the list is longer than it shows');
	pg_assert_contains('commandPickerGroups(commands, BOOT.command_groups)', $source, 'the groups in the server\'s order');

	// The server gives that order, as /help has it.
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	pg_assert_contains("'command_groups' => array_keys(ws_command_group_labels()),", $actions, 'BOOT carries the groups\' order');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_contains('.ws-picker-group {', $css, 'the header has its rule');
}
